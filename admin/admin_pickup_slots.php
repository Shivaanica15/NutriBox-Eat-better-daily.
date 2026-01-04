<?php

require_once __DIR__ . "/admin_auth.php";
require_once __DIR__ . "/../notifications_helper.php";

function log_subscription_action($conn, $subscription_id, $action)
{
    $log = $conn->prepare(
        "INSERT INTO `subscription_logs` (subscription_id, action) VALUES (?, ?)",
    );
    $log->execute([$subscription_id, $action]);
    return $conn->lastInsertId();
}

function subscription_is_expired($subscription)
{
    if (!$subscription) {
        return false;
    }
    if ($subscription["status"] === "Expired") {
        return true;
    }
    if (!empty($subscription["end_date"])) {
        $today = new DateTime(date("Y-m-d"));
        $end_date = new DateTime($subscription["end_date"]);
        if ($end_date < $today) {
            return true;
        }
    }
    return false;
}

function get_active_templates_for_date($conn, $date)
{
    $weekday = (int) (new DateTime($date))->format("N");
    $select = $conn->prepare(
        "SELECT * FROM `pickup_slot_templates`
         WHERE is_active = 1
           AND ((slot_type = 'Date' AND slot_date = ?)
                OR (slot_type = 'Weekday' AND weekday = ?))",
    );
    $select->execute([$date, $weekday]);
    return $select->fetchAll(PDO::FETCH_ASSOC);
}

function materialize_slots_for_date($conn, $date)
{
    if (
        !table_exists($conn, "pickup_slot_templates") ||
        !table_exists($conn, "pickup_slots")
    ) {
        return;
    }

    $templates = get_active_templates_for_date($conn, $date);
    if (!$templates) {
        return;
    }

    $check = $conn->prepare(
        "SELECT id FROM `pickup_slots` WHERE template_id = ? AND pickup_date = ? LIMIT 1",
    );
    $insert = $conn->prepare(
        "INSERT INTO `pickup_slots`
         (template_id, pickup_date, time_from, time_to, location, max_capacity, status, created_at)
         VALUES(?,?,?,?,?,?, 'Available', NOW())",
    );

    foreach ($templates as $template) {
        $check->execute([$template["id"], $date]);
        if ($check->rowCount() > 0) {
            continue;
        }
        $insert->execute([
            $template["id"],
            $date,
            $template["time_from"],
            $template["time_to"],
            $template["location"],
            $template["max_capacity"],
        ]);
    }
}

function get_slot_usage($conn, $slot_id)
{
    $count = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM `subscription_pickup_slots`
         WHERE pickup_slot_id = ? AND status != 'Cancelled'",
    );
    $count->execute([$slot_id]);
    $row = $count->fetch(PDO::FETCH_ASSOC);
    return isset($row["total"]) ? (int) $row["total"] : 0;
}

function compute_slot_status($slot, $used)
{
    $today = new DateTime(date("Y-m-d"));
    $pickup_date = new DateTime($slot["pickup_date"]);
    if ($pickup_date < $today) {
        return "Expired";
    }

    if ($pickup_date == $today) {
        $now = new DateTime(date("H:i:s"));
        $time_to = new DateTime($slot["time_to"]);
        if ($time_to < $now) {
            return "Expired";
        }
    }

    if ($used >= (int) $slot["max_capacity"]) {
        return "Full";
    }

    return "Available";
}

function refresh_slot_status($conn, $slot, $used)
{
    $new_status = compute_slot_status($slot, $used);
    if ($new_status === $slot["status"]) {
        return $new_status;
    }
    $update = $conn->prepare(
        "UPDATE `pickup_slots` SET status = ? WHERE id = ?",
    );
    $update->execute([$new_status, $slot["id"]]);
    return $new_status;
}

function available_pickup_slots($conn, $start_date, $end_date)
{
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify("+1 day");

    $period = new DatePeriod($start, new DateInterval("P1D"), $end);
    foreach ($period as $date) {
        materialize_slots_for_date($conn, $date->format("Y-m-d"));
    }

    $select = $conn->prepare(
        "SELECT * FROM `pickup_slots`
         WHERE pickup_date BETWEEN ? AND ?
         ORDER BY pickup_date ASC, time_from ASC",
    );
    $select->execute([$start_date, $end_date]);

    $available = [];
    while ($slot = $select->fetch(PDO::FETCH_ASSOC)) {
        $used = get_slot_usage($conn, $slot["id"]);
        $status = refresh_slot_status($conn, $slot, $used);
        if ($status === "Available") {
            $slot["used_capacity"] = $used;
            $available[] = $slot;
        }
    }

    return $available;
}

if (isset($_POST["assign_slot"])) {
    $subscription_id = $_POST["subscription_id"];
    $subscription_id = filter_var($subscription_id, FILTER_SANITIZE_NUMBER_INT);
    $pickup_slot_id = $_POST["pickup_slot_id"];
    $pickup_slot_id = filter_var($pickup_slot_id, FILTER_SANITIZE_NUMBER_INT);

    if (empty($subscription_id) || empty($pickup_slot_id)) {
        $message[] = "subscription and slot are required.";
    } else {
        $select_subscription = $conn->prepare(
            "SELECT id, user_id, status, approval_status, end_date FROM `subscriptions` WHERE id = ?",
        );
        $select_subscription->execute([$subscription_id]);
        $subscription = $select_subscription->fetch(PDO::FETCH_ASSOC);

        if (!$subscription) {
            $message[] = "subscription not found.";
        } elseif (subscription_is_expired($subscription)) {
            $message[] = "expired subscriptions are read-only.";
        } elseif (
            $subscription["status"] !== "Active" ||
            $subscription["approval_status"] !== "Approved"
        ) {
            $message[] =
                "pickup slots require active and approved subscriptions.";
        } else {
            try {
                $conn->beginTransaction();
                $slot_select = $conn->prepare(
                    "SELECT * FROM `pickup_slots` WHERE id = ? FOR UPDATE",
                );
                $slot_select->execute([$pickup_slot_id]);
                $slot = $slot_select->fetch(PDO::FETCH_ASSOC);

                if (!$slot) {
                    $conn->rollBack();
                    $message[] = "pickup slot not found.";
                } else {
                    $assignments = $conn->prepare(
                        "SELECT id FROM `subscription_pickup_slots`
                         WHERE pickup_slot_id = ? AND status != 'Cancelled' FOR UPDATE",
                    );
                    $assignments->execute([$pickup_slot_id]);
                    $used = $assignments->rowCount();
                    $status = refresh_slot_status($conn, $slot, $used);

                    if ($status !== "Available") {
                        $conn->rollBack();
                        $message[] = "pickup slot is not available.";
                    } else {
                        $duplicate = $conn->prepare(
                            "SELECT id FROM `subscription_pickup_slots`
                             WHERE subscription_id = ? AND pickup_date = ? AND status != 'Cancelled' LIMIT 1",
                        );
                        $duplicate->execute([
                            $subscription_id,
                            $slot["pickup_date"],
                        ]);
                        if ($duplicate->rowCount() > 0) {
                            $conn->rollBack();
                            $message[] =
                                "subscription already has a pickup slot for this date.";
                        } elseif ($used >= (int) $slot["max_capacity"]) {
                            refresh_slot_status($conn, $slot, $used);
                            $conn->rollBack();
                            $message[] = "pickup slot is full.";
                        } else {
                            $insert = $conn->prepare(
                                "INSERT INTO `subscription_pickup_slots`
                                 (subscription_id, pickup_slot_id, pickup_date, time_from, time_to, location, status, created_at, updated_at)
                                 VALUES(?,?,?,?,?,?, 'Assigned', NOW(), NOW())",
                            );
                            $insert->execute([
                                $subscription_id,
                                $pickup_slot_id,
                                $slot["pickup_date"],
                                $slot["time_from"],
                                $slot["time_to"],
                                $slot["location"],
                            ]);

                            $log_id = log_subscription_action(
                                $conn,
                                $subscription_id,
                                "PICKUP_ASSIGNED: slot_id=" .
                                    $pickup_slot_id .
                                    " date=" .
                                    $slot["pickup_date"] .
                                    " time=" .
                                    $slot["time_from"] .
                                    "-" .
                                    $slot["time_to"] .
                                    " location=" .
                                    $slot["location"],
                            );
                            notify_user(
                                $conn,
                                $subscription["user_id"],
                                $subscription_id,
                                "PICKUP_ASSIGNED",
                                [
                                    "pickup_date" => $slot["pickup_date"],
                                    "time_from" => $slot["time_from"],
                                    "time_to" => $slot["time_to"],
                                    "location" => $slot["location"],
                                    "ref" => $log_id,
                                ],
                            );

                            $used += 1;
                            refresh_slot_status($conn, $slot, $used);
                            $conn->commit();
                            $message[] = "pickup slot assigned.";
                        }
                    }
                }
            } catch (Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $message[] = "failed to assign pickup slot.";
            }
        }
    }
}

if (isset($_POST["reassign_slot"])) {
    $assignment_id = $_POST["assignment_id"];
    $assignment_id = filter_var($assignment_id, FILTER_SANITIZE_NUMBER_INT);
    $pickup_slot_id = $_POST["pickup_slot_id"];
    $pickup_slot_id = filter_var($pickup_slot_id, FILTER_SANITIZE_NUMBER_INT);

    if (empty($assignment_id) || empty($pickup_slot_id)) {
        $message[] = "assignment and slot are required.";
    } else {
        try {
            $conn->beginTransaction();
            $assignment = $conn->prepare(
                "SELECT sps.id, sps.subscription_id, sps.status AS assignment_status,
                        s.user_id, s.status AS subscription_status, s.approval_status, s.end_date
                 FROM `subscription_pickup_slots` AS sps
                 INNER JOIN `subscriptions` AS s ON sps.subscription_id = s.id
                 WHERE sps.id = ? FOR UPDATE",
            );
            $assignment->execute([$assignment_id]);
            $current = $assignment->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                $conn->rollBack();
                $message[] = "assignment not found.";
            } elseif (subscription_is_expired($current)) {
                $conn->rollBack();
                $message[] = "expired subscriptions are read-only.";
            } elseif (
                $current["subscription_status"] !== "Active" ||
                $current["approval_status"] !== "Approved"
            ) {
                $conn->rollBack();
                $message[] =
                    "pickup slots require active and approved subscriptions.";
            } elseif ($current["assignment_status"] !== "Assigned") {
                $conn->rollBack();
                $message[] = "only assigned slots can be reassigned.";
            } else {
                $slot_select = $conn->prepare(
                    "SELECT * FROM `pickup_slots` WHERE id = ? FOR UPDATE",
                );
                $slot_select->execute([$pickup_slot_id]);
                $slot = $slot_select->fetch(PDO::FETCH_ASSOC);

                if (!$slot) {
                    $conn->rollBack();
                    $message[] = "pickup slot not found.";
                } else {
                    $assignments = $conn->prepare(
                        "SELECT id FROM `subscription_pickup_slots`
                         WHERE pickup_slot_id = ? AND status != 'Cancelled' FOR UPDATE",
                    );
                    $assignments->execute([$pickup_slot_id]);
                    $used = $assignments->rowCount();
                    $status = refresh_slot_status($conn, $slot, $used);

                    if ($status !== "Available") {
                        $conn->rollBack();
                        $message[] = "pickup slot is not available.";
                    } else {
                        $duplicate = $conn->prepare(
                            "SELECT id FROM `subscription_pickup_slots`
                             WHERE subscription_id = ? AND pickup_date = ? AND status != 'Cancelled' AND id != ? LIMIT 1",
                        );
                        $duplicate->execute([
                            $current["subscription_id"],
                            $slot["pickup_date"],
                            $assignment_id,
                        ]);
                        if ($duplicate->rowCount() > 0) {
                            $conn->rollBack();
                            $message[] =
                                "subscription already has a pickup slot for this date.";
                        } elseif ($used >= (int) $slot["max_capacity"]) {
                            refresh_slot_status($conn, $slot, $used);
                            $conn->rollBack();
                            $message[] = "pickup slot is full.";
                        } else {
                            $update = $conn->prepare(
                                "UPDATE `subscription_pickup_slots`
                             SET pickup_slot_id = ?, pickup_date = ?, time_from = ?, time_to = ?, location = ?, updated_at = NOW()
                             WHERE id = ?",
                            );
                            $update->execute([
                                $pickup_slot_id,
                                $slot["pickup_date"],
                                $slot["time_from"],
                                $slot["time_to"],
                                $slot["location"],
                                $assignment_id,
                            ]);

                            $log_id = log_subscription_action(
                                $conn,
                                $current["subscription_id"],
                                "PICKUP_REASSIGNED: slot_id=" .
                                    $pickup_slot_id .
                                    " date=" .
                                    $slot["pickup_date"] .
                                    " time=" .
                                    $slot["time_from"] .
                                    "-" .
                                    $slot["time_to"] .
                                    " location=" .
                                    $slot["location"],
                            );
                            notify_user(
                                $conn,
                                $current["user_id"],
                                $current["subscription_id"],
                                "PICKUP_REASSIGNED",
                                [
                                    "pickup_date" => $slot["pickup_date"],
                                    "time_from" => $slot["time_from"],
                                    "time_to" => $slot["time_to"],
                                    "location" => $slot["location"],
                                    "ref" => $log_id,
                                ],
                            );

                            $used += 1;
                            refresh_slot_status($conn, $slot, $used);
                            $conn->commit();
                            $message[] = "pickup slot reassigned.";
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $message[] = "failed to reassign pickup slot.";
        }
    }
}

if (isset($_POST["update_slot_status"])) {
    $assignment_id = $_POST["assignment_id"];
    $assignment_id = filter_var($assignment_id, FILTER_SANITIZE_NUMBER_INT);
    $new_status = $_POST["slot_status"];
    $new_status = trim($new_status);

    $allowed_statuses = ["Completed", "Missed", "Cancelled"];

    if (empty($assignment_id) || empty($new_status)) {
        $message[] = "slot status is required.";
    } elseif (!in_array($new_status, $allowed_statuses, true)) {
        $message[] = "invalid slot status.";
    } else {
        $select = $conn->prepare(
            "SELECT sps.id, sps.subscription_id, sps.pickup_slot_id, sps.status AS assignment_status,
                    s.user_id, s.status AS subscription_status, s.approval_status, s.end_date
             FROM `subscription_pickup_slots` AS sps
             INNER JOIN `subscriptions` AS s ON sps.subscription_id = s.id
             WHERE sps.id = ?",
        );
        $select->execute([$assignment_id]);
        $assignment = $select->fetch(PDO::FETCH_ASSOC);

        if (!$assignment) {
            $message[] = "assignment not found.";
        } elseif (subscription_is_expired($assignment)) {
            $message[] = "expired subscriptions are read-only.";
        } elseif (
            $assignment["subscription_status"] !== "Active" ||
            $assignment["approval_status"] !== "Approved"
        ) {
            $message[] =
                "pickup slots require active and approved subscriptions.";
        } elseif ($assignment["assignment_status"] !== "Assigned") {
            $message[] = "only assigned slots can be updated.";
        } else {
            try {
                $conn->beginTransaction();
                $update = $conn->prepare(
                    "UPDATE `subscription_pickup_slots`
                     SET status = ?, updated_at = NOW()
                     WHERE id = ?",
                );
                $update->execute([$new_status, $assignment_id]);

                if ($new_status === "Cancelled") {
                    $log_id = log_subscription_action(
                        $conn,
                        $assignment["subscription_id"],
                        "PICKUP_CANCELLED: slot_id=" .
                            $assignment["pickup_slot_id"],
                    );
                    notify_user(
                        $conn,
                        $assignment["user_id"],
                        $assignment["subscription_id"],
                        "PICKUP_CANCELLED",
                        ["ref" => $log_id],
                    );
                    $slot_select = $conn->prepare(
                        "SELECT * FROM `pickup_slots` WHERE id = ?",
                    );
                    $slot_select->execute([$assignment["pickup_slot_id"]]);
                    $slot = $slot_select->fetch(PDO::FETCH_ASSOC);
                    if ($slot) {
                        $used = get_slot_usage($conn, $slot["id"]);
                        refresh_slot_status($conn, $slot, $used);
                    }
                }

                $conn->commit();
                $message[] = "pickup slot updated.";
            } catch (Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $message[] = "failed to update pickup slot.";
            }
        }
    }
}

$available_slots = [];
$available_start = date("Y-m-d");
$available_end = date("Y-m-d", strtotime("+14 days"));
if (
    table_exists($conn, "pickup_slots") &&
    table_exists($conn, "pickup_slot_templates")
) {
    $available_slots = available_pickup_slots(
        $conn,
        $available_start,
        $available_end,
    );
}

$select_assignments = $conn->prepare(
    "SELECT sps.*, s.status AS subscription_status, s.approval_status, u.name AS user_name, u.email,
            m.name AS meal_name
     FROM `subscription_pickup_slots` AS sps
     INNER JOIN `subscriptions` AS s ON sps.subscription_id = s.id
     INNER JOIN `users` AS u ON s.user_id = u.id
     INNER JOIN `meal_plans` AS m ON s.meal_plan_id = m.id
     ORDER BY sps.pickup_date DESC, sps.id DESC",
);
$select_assignments->execute();

$eligible = $conn->prepare(
    "SELECT s.id, u.name AS user_name, m.name AS meal_name
     FROM `subscriptions` AS s
     INNER JOIN `users` AS u ON s.user_id = u.id
     INNER JOIN `meal_plans` AS m ON s.meal_plan_id = m.id
     WHERE s.status = 'Active' AND s.approval_status = 'Approved'
     ORDER BY s.id DESC",
);
$eligible->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>pickup slot assignments</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">

</head>
<body class="admin-body">

<?php include "admin_header.php"; ?>

<section class="placed-orders">

   <h1 class="title">pickup slot assignments</h1>

   <div class="box-container">

      <div class="box">
         <h3>assign pickup slot</h3>
         <form action="" method="POST">
            <label for="subscription_id">subscription</label>
            <select name="subscription_id" id="subscription_id" class="box" required>
               <option value="" selected disabled>select subscription</option>
               <?php while ($row = $eligible->fetch(PDO::FETCH_ASSOC)) { ?>
                  <option value="<?= $row["id"] ?>">#<?= $row[
    "id"
] ?> - <?= $row["user_name"] ?> (<?= $row["meal_name"] ?>)</option>
               <?php } ?>
            </select>
            <label for="pickup_slot_id">available pickup slots (next 14 days)</label>
            <select name="pickup_slot_id" id="pickup_slot_id" class="box" required>
               <option value="" selected disabled>select slot</option>
               <?php foreach ($available_slots as $slot) { ?>
                  <option value="<?= $slot["id"] ?>">
                     <?= $slot["pickup_date"] ?> | <?= $slot[
     "time_from"
 ] ?>-<?= $slot["time_to"] ?> | <?= $slot["location"] ?>
                  </option>
               <?php } ?>
            </select>
            <input type="submit" name="assign_slot" class="btn" value="assign slot">
         </form>
      </div>

      <?php if ($select_assignments->rowCount() > 0) {
          while ($assignment = $select_assignments->fetch(PDO::FETCH_ASSOC)) {
              $read_only =
                  $assignment["subscription_status"] !== "Active" ||
                  $assignment["approval_status"] !== "Approved" ||
                  $assignment["status"] !== "Assigned"; ?>
      <div class="box">
         <p> user : <span><?= $assignment["user_name"] ?></span> </p>
         <p> email : <span><?= $assignment["email"] ?></span> </p>
         <p> plan : <span><?= $assignment["meal_name"] ?></span> </p>
         <p> subscription : <span>#<?= $assignment[
             "subscription_id"
         ] ?></span> </p>
         <p> pickup date : <span><?= $assignment["pickup_date"] ?></span> </p>
         <p> time : <span><?= $assignment["time_from"] ?> - <?= $assignment[
     "time_to"
 ] ?></span> </p>
         <p> location : <span><?= $assignment["location"] ?></span> </p>
         <p> status : <span><?= $assignment["status"] ?></span> </p>

         <form action="" method="POST" style="margin-top:1rem;">
            <input type="hidden" name="assignment_id" value="<?= $assignment[
                "id"
            ] ?>">
            <label for="reassign_slot_<?= $assignment[
                "id"
            ] ?>">reassign slot</label>
            <select name="pickup_slot_id" id="reassign_slot_<?= $assignment[
                "id"
            ] ?>" class="box" required <?= $read_only ? "disabled" : "" ?>>
               <option value="" selected disabled>select slot</option>
               <?php foreach ($available_slots as $slot) { ?>
                  <option value="<?= $slot["id"] ?>">
                     <?= $slot["pickup_date"] ?> | <?= $slot[
     "time_from"
 ] ?>-<?= $slot["time_to"] ?> | <?= $slot["location"] ?>
                  </option>
               <?php } ?>
            </select>
            <input type="submit" name="reassign_slot" class="option-btn" value="reassign" <?= $read_only
                ? "disabled"
                : "" ?>>
         </form>

         <form action="" method="POST" style="margin-top:1rem;">
            <input type="hidden" name="assignment_id" value="<?= $assignment[
                "id"
            ] ?>">
            <label for="slot_status_<?= $assignment[
                "id"
            ] ?>">update slot status</label>
            <select name="slot_status" id="slot_status_<?= $assignment[
                "id"
            ] ?>" class="box" required <?= $read_only ? "disabled" : "" ?>>
               <option value="" selected disabled>select status</option>
               <option value="Completed">Completed</option>
               <option value="Missed">Missed</option>
               <option value="Cancelled">Cancelled</option>
            </select>
            <input type="submit" name="update_slot_status" class="btn" value="update status" <?= $read_only
                ? "disabled"
                : "" ?>>
         </form>
      </div>
      <?php
          }
      } else {
          echo '<p class="empty">no pickup slots assigned yet!</p>';
      } ?>

   </div>

</section>

<?php include "admin_footer.php"; ?>

</body>
</html>
