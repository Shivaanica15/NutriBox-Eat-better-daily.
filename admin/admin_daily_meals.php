<?php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../notifications_helper.php";

session_start();

$admin_id = $_SESSION["admin_id"];

if (!isset($admin_id)) {
    header("location:login.php");
}

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
    $status = isset($subscription["subscription_status"])
        ? $subscription["subscription_status"]
        : $subscription["status"] ?? "";
    if ($status === "Expired") {
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

function get_pause_ranges($conn, $subscription_id)
{
    $logs = $conn->prepare(
        "SELECT action, created_at
         FROM `subscription_logs`
         WHERE subscription_id = ?
           AND action IN ('PAUSED','RESUMED')
         ORDER BY created_at ASC",
    );
    $logs->execute([$subscription_id]);

    $ranges = [];
    $pause_start = null;

    while ($log = $logs->fetch(PDO::FETCH_ASSOC)) {
        if (empty($log["created_at"])) {
            continue;
        }
        $date = new DateTime($log["created_at"]);
        $date->setTime(0, 0, 0);

        if ($log["action"] === "PAUSED") {
            $pause_start = $date;
        }

        if ($log["action"] === "RESUMED" && $pause_start) {
            $ranges[] = [clone $pause_start, $date];
            $pause_start = null;
        }
    }

    if ($pause_start) {
        $today = new DateTime(date("Y-m-d"));
        $today->modify("+1 day");
        $ranges[] = [clone $pause_start, $today];
    }

    return $ranges;
}

function is_paused_on_date($date, $ranges)
{
    foreach ($ranges as $range) {
        $start = $range[0];
        $end = $range[1];
        if ($date >= $start && $date < $end) {
            return true;
        }
    }
    return false;
}

function generate_meals_for_subscription($conn, $subscription)
{
    if (
        $subscription["status"] !== "Active" ||
        $subscription["approval_status"] !== "Approved"
    ) {
        return;
    }

    if (
        empty($subscription["start_date"]) ||
        empty($subscription["end_date"])
    ) {
        return;
    }

    $today = new DateTime(date("Y-m-d"));
    $start_date = new DateTime($subscription["start_date"]);
    $end_date = new DateTime($subscription["end_date"]);

    if ($end_date < $start_date || $end_date < $today) {
        return;
    }

    $generate_until = $end_date < $today ? $end_date : $today;
    if ($generate_until < $start_date) {
        return;
    }

    $existing = $conn->prepare(
        "SELECT meal_date FROM `subscription_meals` WHERE subscription_id = ? AND meal_date BETWEEN ? AND ?",
    );
    $existing->execute([
        $subscription["id"],
        $start_date->format("Y-m-d"),
        $generate_until->format("Y-m-d"),
    ]);
    $existing_dates = [];
    while ($row = $existing->fetch(PDO::FETCH_ASSOC)) {
        $existing_dates[$row["meal_date"]] = true;
    }

    $pause_ranges = get_pause_ranges($conn, $subscription["id"]);

    $insert = $conn->prepare(
        "INSERT INTO `subscription_meals` (subscription_id, meal_date, status, created_at)
         VALUES(?,?, 'Pending', NOW())",
    );

    $conn->beginTransaction();
    try {
        $date_cursor = clone $start_date;
        while ($date_cursor <= $generate_until) {
            $date_str = $date_cursor->format("Y-m-d");
            if (!isset($existing_dates[$date_str])) {
                if (!is_paused_on_date($date_cursor, $pause_ranges)) {
                    $insert->execute([$subscription["id"], $date_str]);
                }
            }
            $date_cursor->modify("+1 day");
        }
        $conn->commit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
    }
}

function normalize_ids($ids)
{
    if (!is_array($ids)) {
        return [];
    }
    $clean = [];
    foreach ($ids as $id) {
        $value = (int) $id;
        if ($value > 0) {
            $clean[$value] = true;
        }
    }
    return array_keys($clean);
}

$active_subscriptions = $conn->prepare(
    "SELECT id, user_id, meal_plan_id, start_date, end_date, status, approval_status
     FROM `subscriptions`
     WHERE status = 'Active' AND approval_status = 'Approved' AND end_date >= CURDATE()",
);
$active_subscriptions->execute();
while ($sub = $active_subscriptions->fetch(PDO::FETCH_ASSOC)) {
    generate_meals_for_subscription($conn, $sub);
}

if (isset($_POST["bulk_action"])) {
    $action = $_POST["bulk_action"];
    $meal_ids = normalize_ids($_POST["meal_ids"] ?? []);

    if (!$meal_ids) {
        $message[] = "no meals selected.";
    } else {
        $expected_status = "";
        $new_status = "";
        $log_action = "";
        $notify_event = null;

        if ($action === "bulk_prepared") {
            $expected_status = "Pending";
            $new_status = "Prepared";
            $log_action = "MEAL_PREPARED";
            $notify_event = "MEAL_PREPARED";
        } elseif ($action === "bulk_picked_up") {
            $expected_status = "Prepared";
            $new_status = "PickedUp";
            $log_action = "MEAL_PICKED_UP";
        } elseif ($action === "bulk_missed") {
            $expected_status = "Prepared";
            $new_status = "Missed";
            $log_action = "MEAL_MISSED";
            $notify_event = "MEAL_MISSED";
        }

        if ($expected_status === "") {
            $message[] = "invalid bulk action.";
        } else {
            try {
                $conn->beginTransaction();
                $placeholders = implode(
                    ",",
                    array_fill(0, count($meal_ids), "?"),
                );
                $select = $conn->prepare(
                    "SELECT sm.id, sm.subscription_id, sm.meal_date, sm.status AS meal_status,
                            s.user_id, s.status AS subscription_status, s.approval_status, s.end_date
                     FROM `subscription_meals` AS sm
                     INNER JOIN `subscriptions` AS s ON sm.subscription_id = s.id
                     WHERE sm.id IN ($placeholders)
                     FOR UPDATE",
                );
                $select->execute($meal_ids);

                $eligible = [];
                $skipped = 0;
                while ($meal = $select->fetch(PDO::FETCH_ASSOC)) {
                    $is_valid =
                        $meal["meal_status"] === $expected_status &&
                        $meal["subscription_status"] === "Active" &&
                        $meal["approval_status"] === "Approved" &&
                        !subscription_is_expired($meal);
                    if ($is_valid) {
                        $eligible[] = $meal;
                    } else {
                        $skipped += 1;
                    }
                }

                if (!$eligible) {
                    $conn->commit();
                    $message[] =
                        "0 updated, " . $skipped . " skipped (invalid status)";
                } else {
                    $eligible_ids = array_column($eligible, "id");
                    $update_placeholders = implode(
                        ",",
                        array_fill(0, count($eligible_ids), "?"),
                    );
                    $update = $conn->prepare(
                        "UPDATE `subscription_meals`
                         SET status = ?
                         WHERE id IN ($update_placeholders) AND status = ?",
                    );
                    $params = array_merge([$new_status], $eligible_ids, [
                        $expected_status,
                    ]);
                    $update->execute($params);

                    foreach ($eligible as $meal) {
                        $log_id = log_subscription_action(
                            $conn,
                            $meal["subscription_id"],
                            $log_action .
                                ": meal_id=" .
                                $meal["id"] .
                                " date=" .
                                $meal["meal_date"],
                        );
                        if ($notify_event) {
                            notify_user(
                                $conn,
                                $meal["user_id"],
                                $meal["subscription_id"],
                                $notify_event,
                                ["ref" => $log_id],
                            );
                        }
                    }

                    $conn->commit();
                    $message[] =
                        count($eligible) .
                        " updated, " .
                        $skipped .
                        " skipped (invalid status)";
                }
            } catch (Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $message[] = "bulk update failed.";
            }
        }
    }
}

if (isset($_POST["mark_prepared"])) {
    $meal_id = $_POST["mark_prepared"];
    $meal_id = filter_var($meal_id, FILTER_SANITIZE_NUMBER_INT);

    if (empty($meal_id)) {
        $message[] = "meal not found.";
    } else {
        $select = $conn->prepare(
            "SELECT sm.id, sm.subscription_id, sm.meal_date, sm.status AS meal_status,
                    s.user_id, s.status AS subscription_status, s.approval_status, s.end_date
             FROM `subscription_meals` AS sm
             INNER JOIN `subscriptions` AS s ON sm.subscription_id = s.id
             WHERE sm.id = ?",
        );
        $select->execute([$meal_id]);
        $meal = $select->fetch(PDO::FETCH_ASSOC);

        if (!$meal) {
            $message[] = "meal not found.";
        } elseif (subscription_is_expired($meal)) {
            $message[] = "expired subscriptions are read-only.";
        } elseif (
            $meal["subscription_status"] !== "Active" ||
            $meal["approval_status"] !== "Approved"
        ) {
            $message[] =
                "meal updates require active and approved subscriptions.";
        } elseif ($meal["meal_status"] !== "Pending") {
            $message[] = "only pending meals can be prepared.";
        } else {
            try {
                $conn->beginTransaction();
                $update = $conn->prepare(
                    "UPDATE `subscription_meals` SET status = 'Prepared' WHERE id = ?",
                );
                $update->execute([$meal_id]);
                $log_id = log_subscription_action(
                    $conn,
                    $meal["subscription_id"],
                    "MEAL_PREPARED: meal_id=" .
                        $meal_id .
                        " date=" .
                        $meal["meal_date"],
                );
                notify_user(
                    $conn,
                    $meal["user_id"],
                    $meal["subscription_id"],
                    "MEAL_PREPARED",
                    ["ref" => $log_id],
                );
                $conn->commit();
                $message[] = "meal marked as prepared.";
            } catch (Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $message[] = "failed to update meal.";
            }
        }
    }
}

if (isset($_POST["mark_picked_up"])) {
    $meal_id = $_POST["mark_picked_up"];
    $meal_id = filter_var($meal_id, FILTER_SANITIZE_NUMBER_INT);

    if (empty($meal_id)) {
        $message[] = "meal not found.";
    } else {
        $select = $conn->prepare(
            "SELECT sm.id, sm.subscription_id, sm.meal_date, sm.status AS meal_status,
                    s.user_id, s.status AS subscription_status, s.approval_status, s.end_date
             FROM `subscription_meals` AS sm
             INNER JOIN `subscriptions` AS s ON sm.subscription_id = s.id
             WHERE sm.id = ?",
        );
        $select->execute([$meal_id]);
        $meal = $select->fetch(PDO::FETCH_ASSOC);

        if (!$meal) {
            $message[] = "meal not found.";
        } elseif (subscription_is_expired($meal)) {
            $message[] = "expired subscriptions are read-only.";
        } elseif (
            $meal["subscription_status"] !== "Active" ||
            $meal["approval_status"] !== "Approved"
        ) {
            $message[] =
                "meal updates require active and approved subscriptions.";
        } elseif ($meal["meal_status"] !== "Prepared") {
            $message[] = "only prepared meals can be picked up.";
        } else {
            try {
                $conn->beginTransaction();
                $update = $conn->prepare(
                    "UPDATE `subscription_meals` SET status = 'PickedUp' WHERE id = ?",
                );
                $update->execute([$meal_id]);
                log_subscription_action(
                    $conn,
                    $meal["subscription_id"],
                    "MEAL_PICKED_UP: meal_id=" .
                        $meal_id .
                        " date=" .
                        $meal["meal_date"],
                );
                $conn->commit();
                $message[] = "meal marked as picked up.";
            } catch (Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $message[] = "failed to update meal.";
            }
        }
    }
}

if (isset($_POST["mark_missed"])) {
    $meal_id = $_POST["mark_missed"];
    $meal_id = filter_var($meal_id, FILTER_SANITIZE_NUMBER_INT);

    if (empty($meal_id)) {
        $message[] = "meal not found.";
    } else {
        $select = $conn->prepare(
            "SELECT sm.id, sm.subscription_id, sm.meal_date, sm.status AS meal_status,
                    s.user_id, s.status AS subscription_status, s.approval_status, s.end_date
             FROM `subscription_meals` AS sm
             INNER JOIN `subscriptions` AS s ON sm.subscription_id = s.id
             WHERE sm.id = ?",
        );
        $select->execute([$meal_id]);
        $meal = $select->fetch(PDO::FETCH_ASSOC);

        if (!$meal) {
            $message[] = "meal not found.";
        } elseif (subscription_is_expired($meal)) {
            $message[] = "expired subscriptions are read-only.";
        } elseif (
            $meal["subscription_status"] !== "Active" ||
            $meal["approval_status"] !== "Approved"
        ) {
            $message[] =
                "meal updates require active and approved subscriptions.";
        } elseif ($meal["meal_status"] !== "Prepared") {
            $message[] = "only prepared meals can be marked missed.";
        } else {
            try {
                $conn->beginTransaction();
                $update = $conn->prepare(
                    "UPDATE `subscription_meals` SET status = 'Missed' WHERE id = ?",
                );
                $update->execute([$meal_id]);
                $log_id = log_subscription_action(
                    $conn,
                    $meal["subscription_id"],
                    "MEAL_MISSED: meal_id=" .
                        $meal_id .
                        " date=" .
                        $meal["meal_date"],
                );
                notify_user(
                    $conn,
                    $meal["user_id"],
                    $meal["subscription_id"],
                    "MEAL_MISSED",
                    ["ref" => $log_id],
                );
                $conn->commit();
                $message[] = "meal marked as missed.";
            } catch (Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $message[] = "failed to update meal.";
            }
        }
    }
}

$filter_date = isset($_GET["date"]) ? trim($_GET["date"]) : "";
$filter_plan = isset($_GET["plan_id"]) ? trim($_GET["plan_id"]) : "";
$filter_status = isset($_GET["status"]) ? trim($_GET["status"]) : "";

$plans = $conn->prepare("SELECT id, name FROM `meal_plans` ORDER BY name ASC");
$plans->execute();

$conditions = [];
$params = [];

if ($filter_date !== "") {
    $conditions[] = "sm.meal_date = ?";
    $params[] = $filter_date;
}
if ($filter_plan !== "") {
    $conditions[] = "m.id = ?";
    $params[] = $filter_plan;
}
if ($filter_status !== "") {
    $conditions[] = "sm.status = ?";
    $params[] = $filter_status;
}

$where_sql = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

$meals = $conn->prepare(
    "SELECT sm.id, sm.subscription_id, sm.meal_date, sm.status AS meal_status, sm.created_at,
            s.status AS subscription_status, s.approval_status, s.end_date, u.name AS user_name, u.email,
            m.name AS meal_name, sps.time_from, sps.time_to, sps.location, sps.status AS pickup_status
     FROM `subscription_meals` AS sm
     INNER JOIN `subscriptions` AS s ON sm.subscription_id = s.id
     INNER JOIN `users` AS u ON s.user_id = u.id
     INNER JOIN `meal_plans` AS m ON s.meal_plan_id = m.id
     LEFT JOIN `subscription_pickup_slots` AS sps
       ON sps.subscription_id = sm.subscription_id AND sps.pickup_date = sm.meal_date AND sps.status != 'Cancelled'
     $where_sql
     ORDER BY sm.meal_date DESC, sm.id DESC",
);
$meals->execute($params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>daily meals</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">

</head>
<body class="admin-body">

<?php include "admin_header.php"; ?>

<section class="placed-orders">

   <h1 class="title">daily meals</h1>

   <div class="box-container">

      <div class="box" style="width:100%;">
         <form action="" method="GET">
            <label for="date">date</label>
            <input type="date" name="date" id="date" class="box" value="<?= $filter_date ?>">
            <label for="plan_id">meal plan</label>
            <select name="plan_id" id="plan_id" class="box">
               <option value="" selected>all plans</option>
               <?php while ($plan = $plans->fetch(PDO::FETCH_ASSOC)) { ?>
                  <option value="<?= $plan["id"] ?>" <?= $filter_plan ==
$plan["id"]
    ? "selected"
    : "" ?>>
                     <?= $plan["name"] ?>
                  </option>
               <?php } ?>
            </select>
            <label for="status">status</label>
            <select name="status" id="status" class="box">
               <option value="" selected>all statuses</option>
               <option value="Pending" <?= $filter_status === "Pending"
                   ? "selected"
                   : "" ?>>Pending</option>
               <option value="Prepared" <?= $filter_status === "Prepared"
                   ? "selected"
                   : "" ?>>Prepared</option>
               <option value="PickedUp" <?= $filter_status === "PickedUp"
                   ? "selected"
                   : "" ?>>PickedUp</option>
               <option value="Missed" <?= $filter_status === "Missed"
                   ? "selected"
                   : "" ?>>Missed</option>
            </select>
            <input type="submit" class="btn" value="filter meals">
         </form>
      </div>

      <form action="" method="POST" style="width:100%;">
         <div class="box" style="width:100%;">
            <label><input type="checkbox" id="select-all"> select all</label>
            <div class="flex-btn" style="margin-top:1rem;">
               <button type="submit" name="bulk_action" value="bulk_prepared" class="option-btn">mark prepared</button>
               <button type="submit" name="bulk_action" value="bulk_picked_up" class="btn">mark picked up</button>
               <button type="submit" name="bulk_action" value="bulk_missed" class="delete-btn">mark missed</button>
            </div>
         </div>

         <?php if ($meals->rowCount() > 0) {
             $current_date = "";
             while ($meal = $meals->fetch(PDO::FETCH_ASSOC)) {

                 if ($current_date !== $meal["meal_date"]) {
                     $current_date = $meal["meal_date"]; ?>
                     <div class="box" style="width:100%;">
                        <strong><?= $current_date ?></strong>
                     </div>
                 <?php
                 }

                 $read_only =
                     $meal["subscription_status"] !== "Active" ||
                     $meal["approval_status"] !== "Approved" ||
                     $meal["meal_status"] === "PickedUp" ||
                     $meal["meal_status"] === "Missed" ||
                     subscription_is_expired($meal);
                 ?>
         <div class="box">
            <label>
               <input type="checkbox" name="meal_ids[]" value="<?= $meal[
                   "id"
               ] ?>" class="meal-checkbox">
               select meal
            </label>
            <p> user : <span><?= $meal["user_name"] ?></span> </p>
            <p> email : <span><?= $meal["email"] ?></span> </p>
            <p> plan : <span><?= $meal["meal_name"] ?></span> </p>
            <p> subscription : <span>#<?= $meal[
                "subscription_id"
            ] ?></span> </p>
            <p> meal date : <span><?= $meal["meal_date"] ?></span> </p>
            <p> status : <span><?= $meal["meal_status"] ?></span> </p>
            <p> pickup slot : <span>
               <?php if (!empty($meal["time_from"])) { ?>
                  <?= $meal["time_from"] ?> - <?= $meal[
     "time_to"
 ] ?> (<?= $meal["location"] ?>)
               <?php } else { ?>
                  not assigned
               <?php } ?>
            </span></p>

            <div class="flex-btn" style="margin-top:1rem;">
               <button type="submit" name="mark_prepared" value="<?= $meal[
                   "id"
               ] ?>" class="option-btn" <?= $read_only ||
$meal["meal_status"] !== "Pending"
    ? "disabled"
    : "" ?>>prepared</button>
               <button type="submit" name="mark_picked_up" value="<?= $meal[
                   "id"
               ] ?>" class="btn" <?= $read_only ||
$meal["meal_status"] !== "Prepared"
    ? "disabled"
    : "" ?>>picked up</button>
               <button type="submit" name="mark_missed" value="<?= $meal[
                   "id"
               ] ?>" class="delete-btn" <?= $read_only ||
$meal["meal_status"] !== "Prepared"
    ? "disabled"
    : "" ?>>missed</button>
            </div>
         </div>
         <?php
             }
         } else {
             echo '<p class="empty">no daily meals found!</p>';
         } ?>
      </form>

   </div>

</section>

<?php include "admin_footer.php"; ?>
<script>
const selectAll = document.getElementById('select-all');
if (selectAll) {
   selectAll.addEventListener('change', function () {
      const boxes = document.querySelectorAll('.meal-checkbox');
      boxes.forEach((box) => {
         box.checked = selectAll.checked;
      });
   });
}
</script>

</body>
</html>


