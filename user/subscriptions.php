<?php

@include "../config.php";
require_once __DIR__ . "/../notifications_helper.php";

session_start();

$user_id = $_SESSION["user_id"];

if (!isset($user_id)) {
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

function expire_user_subscriptions($conn, $user_id)
{
    $select = $conn->prepare(
        "SELECT id, user_id, end_date FROM `subscriptions` WHERE user_id = ? AND end_date IS NOT NULL AND end_date < CURDATE() AND status != 'Expired'",
    );
    $select->execute([$user_id]);
    $expired = $select->fetchAll(PDO::FETCH_ASSOC);
    if (!$expired) {
        return;
    }

    foreach ($expired as $row) {
        try {
            $conn->beginTransaction();
            $update = $conn->prepare(
                "UPDATE `subscriptions` SET status = 'Expired' WHERE id = ? AND user_id = ?",
            );
            $update->execute([$row["id"], $user_id]);
            $log_id = log_subscription_action($conn, $row["id"], "EXPIRED");
            notify_user($conn, $row["user_id"], $row["id"], "EXPIRED", [
                "ref" => $log_id,
            ]);
            $conn->commit();
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
        }
    }
}

function calculate_pause_days($logs, $range_start = null, $range_end = null)
{
    $total = 0;
    $pause_start = null;

    foreach ($logs as $log) {
        if (empty($log["created_at"])) {
            continue;
        }
        $created_at = new DateTime($log["created_at"]);

        if ($log["action"] === "PAUSED") {
            $pause_start = $created_at;
        }

        if ($log["action"] === "RESUMED" && $pause_start) {
            $pause_end = $created_at;
            $total += calculate_overlap_days(
                $pause_start,
                $pause_end,
                $range_start,
                $range_end,
            );
            $pause_start = null;
        }
    }

    if ($pause_start) {
        $pause_end = new DateTime(date("Y-m-d"));
        $total += calculate_overlap_days(
            $pause_start,
            $pause_end,
            $range_start,
            $range_end,
        );
    }

    return $total;
}

function calculate_overlap_days(
    $start,
    $end,
    $range_start = null,
    $range_end = null,
) {
    if ($range_start && $start < $range_start) {
        $start = clone $range_start;
    }
    if ($range_end && $end > $range_end) {
        $end = clone $range_end;
    }
    if ($end < $start) {
        return 0;
    }
    return (int) $start->diff($end)->format("%a");
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

function get_user_goal($conn, $user_id)
{
    if (isset($_SESSION["user_goal"]) && $_SESSION["user_goal"] !== "") {
        return $_SESSION["user_goal"];
    }

    return null;
}

function goal_allowed_diet_types($goal)
{
    $map = [
        "Gym users" => ["High Protein", "Balanced"],
        "Busy professionals" => ["Balanced", "Keto"],
        "Diabetics" => ["Balanced", "Vegan"],
        "Weight loss" => ["Keto", "Vegan"],
        "General health" => ["Balanced", "High Protein", "Vegan"],
    ];

    return $map[$goal] ?? [];
}

expire_user_subscriptions($conn, $user_id);

$select_current = $conn->prepare(
    "SELECT s.*, m.name AS meal_name, m.description, m.price, m.duration, m.calories, m.diet_type, m.image,
           cr.name AS requested_name, cr.price AS requested_price
    FROM `subscriptions` AS s
    LEFT JOIN `meal_plans` AS m ON s.meal_plan_id = m.id
    LEFT JOIN `meal_plans` AS cr ON s.change_requested_plan_id = cr.id
    WHERE s.user_id = ?
    ORDER BY s.id DESC
    LIMIT 1",
);
$select_current->execute([$user_id]);
$current_subscription = $select_current->fetch(PDO::FETCH_ASSOC);

$count_current = $conn->prepare(
    "SELECT COUNT(*) AS total FROM `subscriptions` WHERE user_id = ? AND status IN ('Pending','Active')",
);
$count_current->execute([$user_id]);
$has_current_subscription =
    (int) $count_current->fetch(PDO::FETCH_ASSOC)["total"] > 0;

$subscription_logs = [];
if ($current_subscription) {
    $logs = $conn->prepare(
        "SELECT action, created_at FROM `subscription_logs` WHERE subscription_id = ? ORDER BY created_at ASC",
    );
    $logs->execute([$current_subscription["id"]]);
    $subscription_logs = $logs->fetchAll(PDO::FETCH_ASSOC);
}

$remaining_days = null;
if ($current_subscription && !empty($current_subscription["end_date"])) {
    $today = new DateTime(date("Y-m-d"));
    $end_date = new DateTime($current_subscription["end_date"]);
    $remaining_days = (int) $today->diff($end_date)->format("%r%a");
    if ($remaining_days < 0) {
        $remaining_days = 0;
    }

    if (
        $current_subscription["status"] === "Paused" &&
        !empty($current_subscription["pause_start"])
    ) {
        $pause_start = new DateTime($current_subscription["pause_start"]);
        $paused_days = (int) $pause_start->diff($today)->format("%r%a");
        if ($paused_days > 0) {
            $remaining_days += $paused_days;
        }
    }
}

$pause_days_total = 0;
$pause_days_month = 0;
if ($subscription_logs) {
    $pause_days_total = calculate_pause_days($subscription_logs);
    $month_start = new DateTime(date("Y-m-01"));
    $month_end = new DateTime(date("Y-m-t"));
    $pause_days_month = calculate_pause_days(
        $subscription_logs,
        $month_start,
        $month_end,
    );
}

$available_plans = [];
if ($current_subscription && $current_subscription["status"] === "Active") {
    $goal = get_user_goal($conn, $user_id);
    $allowed_diets = goal_allowed_diet_types($goal);

    if ($allowed_diets) {
        $placeholders = implode(",", array_fill(0, count($allowed_diets), "?"));
        $params = array_merge(
            [$current_subscription["meal_plan_id"]],
            $allowed_diets,
        );
        $plans = $conn->prepare(
            "SELECT id, name, price FROM `meal_plans` WHERE id != ? AND diet_type IN ($placeholders)",
        );
        $plans->execute($params);
    } else {
        $plans = $conn->prepare(
            "SELECT id, name, price FROM `meal_plans` WHERE id != ?",
        );
        $plans->execute([$current_subscription["meal_plan_id"]]);
    }
    $available_plans = $plans->fetchAll(PDO::FETCH_ASSOC);
}

if (isset($_POST["pause_subscription"]) && $current_subscription) {
    // Pause only when Active and Approved.
    $subscription_id = $_POST["subscription_id"];
    $subscription_id = filter_var($subscription_id, FILTER_SANITIZE_NUMBER_INT);

    if ($current_subscription["id"] != $subscription_id) {
        $message[] = "subscription not found!";
    } elseif (subscription_is_expired($current_subscription)) {
        $message[] = "subscription is expired.";
    } elseif (
        $current_subscription["status"] !== "Active" ||
        $current_subscription["approval_status"] !== "Approved"
    ) {
        $message[] = "subscription cannot be paused right now.";
    } elseif ($pause_days_month >= 7) {
        $message[] = "pause limit reached for this month.";
        log_subscription_action($conn, $subscription_id, "PAUSE_DENIED");
    } else {
        try {
            $conn->beginTransaction();
            $pause = $conn->prepare(
                "UPDATE `subscriptions` SET status = 'Paused', pause_start = CURDATE(), pause_end = NULL WHERE id = ? AND user_id = ?",
            );
            $pause->execute([$subscription_id, $user_id]);
            $log_id = log_subscription_action(
                $conn,
                $subscription_id,
                "PAUSED",
            );
            notify_user($conn, $user_id, $subscription_id, "PAUSED", [
                "ref" => $log_id,
            ]);
            $conn->commit();
            $message[] = "subscription paused successfully!";
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $message[] = "failed to pause subscription.";
        }
    }
}

if (isset($_POST["resume_subscription"]) && $current_subscription) {
    // Resume only when Paused.
    $subscription_id = $_POST["subscription_id"];
    $subscription_id = filter_var($subscription_id, FILTER_SANITIZE_NUMBER_INT);

    if ($current_subscription["id"] != $subscription_id) {
        $message[] = "subscription not found!";
    } elseif (subscription_is_expired($current_subscription)) {
        $message[] = "subscription is expired.";
    } elseif ($current_subscription["status"] !== "Paused") {
        $message[] = "subscription is not paused.";
    } elseif (empty($current_subscription["pause_start"])) {
        $message[] = "pause start date is missing.";
    } else {
        $pause_start = new DateTime($current_subscription["pause_start"]);
        $today = new DateTime(date("Y-m-d"));
        $paused_days = (int) $pause_start->diff($today)->format("%r%a");
        if ($paused_days < 0) {
            $paused_days = 0;
        }

        try {
            $conn->beginTransaction();
            $resume = $conn->prepare(
                "UPDATE `subscriptions`
             SET end_date = DATE_ADD(end_date, INTERVAL ? DAY),
                 pause_start = NULL,
                 pause_end = CURDATE(),
                 status = 'Active'
             WHERE id = ? AND user_id = ?",
            );
            $resume->execute([$paused_days, $subscription_id, $user_id]);
            $log_id = log_subscription_action(
                $conn,
                $subscription_id,
                "RESUMED",
            );
            notify_user($conn, $user_id, $subscription_id, "RESUMED", [
                "ref" => $log_id,
            ]);
            $conn->commit();
            $message[] = "subscription resumed successfully!";
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $message[] = "failed to resume subscription.";
        }
    }
}

if (isset($_POST["request_plan_change"]) && $current_subscription) {
    // Request plan change only when Active and Approved.
    $subscription_id = $_POST["subscription_id"];
    $subscription_id = filter_var($subscription_id, FILTER_SANITIZE_NUMBER_INT);
    $requested_plan_id = $_POST["requested_plan_id"];
    $requested_plan_id = filter_var(
        $requested_plan_id,
        FILTER_SANITIZE_NUMBER_INT,
    );

    if ($current_subscription["id"] != $subscription_id) {
        $message[] = "subscription not found!";
    } elseif (subscription_is_expired($current_subscription)) {
        $message[] = "subscription is expired.";
    } elseif (
        $current_subscription["status"] !== "Active" ||
        $current_subscription["approval_status"] !== "Approved"
    ) {
        $message[] = "plan change is only available for active subscriptions.";
    } elseif ($requested_plan_id == $current_subscription["meal_plan_id"]) {
        $message[] = "please choose a different plan.";
    } else {
        $plan_check = $conn->prepare(
            "SELECT id, price FROM `meal_plans` WHERE id = ?",
        );
        $plan_check->execute([$requested_plan_id]);
        $requested_plan = $plan_check->fetch(PDO::FETCH_ASSOC);

        if (!$requested_plan) {
            $message[] = "selected plan does not exist.";
        } else {
            if (
                $remaining_days !== null &&
                $remaining_days < 5 &&
                $requested_plan["price"] < $current_subscription["price"]
            ) {
                $message[] =
                    "downgrade not allowed with less than 5 days remaining.";
            } else {
                $cycle_check = $conn->prepare(
                    "SELECT COUNT(*) AS total
                FROM `subscription_logs`
                WHERE subscription_id = ?
                  AND action IN ('PLAN_CHANGED','PLAN_CHANGE_REQUESTED')
                  AND created_at BETWEEN ? AND ?",
                );
                $cycle_check->execute([
                    $subscription_id,
                    $current_subscription["start_date"],
                    $current_subscription["end_date"],
                ]);
                $cycle_count = (int) $cycle_check->fetch(PDO::FETCH_ASSOC)[
                    "total"
                ];

                if ($cycle_count > 0) {
                    $message[] =
                        "only one plan change is allowed per billing cycle.";
                } else {
                    try {
                        $conn->beginTransaction();
                        $request = $conn->prepare(
                            "UPDATE `subscriptions`
                      SET status = 'ChangeRequested',
                          approval_status = 'Pending',
                          change_requested_plan_id = ?
                      WHERE id = ? AND user_id = ?",
                        );
                        $request->execute([
                            $requested_plan_id,
                            $subscription_id,
                            $user_id,
                        ]);
                        log_subscription_action(
                            $conn,
                            $subscription_id,
                            "PLAN_CHANGE_REQUESTED",
                        );
                        $conn->commit();
                        $message[] = "plan change request submitted!";
                    } catch (Exception $e) {
                        if ($conn->inTransaction()) {
                            $conn->rollBack();
                        }
                        $message[] = "failed to request plan change.";
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>subscription</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/user_style.css">

</head>
<body>

<?php include "user_header.php"; ?>

<section class="placed-orders">

   <h1 class="title"><?= $has_current_subscription
       ? "Current Subscription"
       : "Subscription History" ?></h1>

   <div class="box-container">

   <?php if ($current_subscription) { ?>
      <div class="box">
         <p> meal plan : <span><?= $current_subscription[
             "meal_name"
         ] ?></span> </p>
         <p> status : <span><?= $current_subscription["status"] ?></span> </p>
         <p> approval : <span><?= $current_subscription[
             "approval_status"
         ] ?></span> </p>
         <p> duration : <span><?= $current_subscription[
             "duration"
         ] ?> days</span> </p>
         <p> calories : <span><?= $current_subscription[
             "calories"
         ] ?> / day</span> </p>
         <p> price : <span>$<?= $current_subscription["price"] ?>/-</span> </p>
         <p> remaining days : <span><?= $remaining_days !== null
             ? $remaining_days
             : "N/A" ?></span> </p>
         <p> total paused days : <span><?= $pause_days_total ?></span> </p>
         <p> paused this month : <span><?= $pause_days_month ?> / 7</span> </p>
         <?php if (
             $current_subscription["status"] === "ChangeRequested" &&
             !empty($current_subscription["requested_name"])
         ) { ?>
            <p> requested plan : <span><?= $current_subscription[
                "requested_name"
            ] ?></span> </p>
         <?php } ?>

         <div class="flex-btn" style="margin-top:1rem;">
            <?php if (
                $current_subscription["status"] === "Active" &&
                $current_subscription["approval_status"] === "Approved"
            ) { ?>
               <form action="" method="POST" style="display:inline-block;">
                  <input type="hidden" name="subscription_id" value="<?= $current_subscription[
                      "id"
                  ] ?>">
                  <input type="submit" name="pause_subscription" class="option-btn" value="pause">
               </form>
            <?php } ?>

            <?php if ($current_subscription["status"] === "Paused") { ?>
               <form action="" method="POST" style="display:inline-block;">
                  <input type="hidden" name="subscription_id" value="<?= $current_subscription[
                      "id"
                  ] ?>">
                  <input type="submit" name="resume_subscription" class="btn" value="resume">
               </form>
            <?php } ?>
         </div>

         <?php if (
             $current_subscription["status"] === "Active" &&
             $current_subscription["approval_status"] === "Approved"
         ) { ?>
            <form action="" method="POST" style="margin-top:1.5rem;">
               <input type="hidden" name="subscription_id" value="<?= $current_subscription[
                   "id"
               ] ?>">
               <label for="requested_plan_id">change plan</label>
               <select name="requested_plan_id" id="requested_plan_id" class="box" required>
                  <option value="" selected disabled>select a new meal plan</option>
                  <?php foreach ($available_plans as $plan) { ?>
                     <option value="<?= $plan["id"] ?>"><?= $plan[
    "name"
] ?></option>
                  <?php } ?>
               </select>
               <input type="submit" name="request_plan_change" class="btn" value="request change">
            </form>
         <?php } ?>
      </div>
   <?php } else { ?>
      <p class="empty">no subscription found yet!</p>
   <?php } ?>

   </div>

</section>

<?php include "user_footer.php"; ?>

<script src="js/user.js"></script>

</body>
</html>
