<?php

@include "../config.php";
require_once __DIR__ . "/../notifications_helper.php";

session_start();

$user_id = $_SESSION["user_id"];

if (!isset($user_id)) {
    header("location:login.php");
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

$active_subscriptions = $conn->prepare(
    "SELECT id, meal_plan_id, start_date, end_date, status, approval_status
     FROM `subscriptions`
     WHERE user_id = ? AND status = 'Active' AND approval_status = 'Approved' AND end_date >= CURDATE()",
);
$active_subscriptions->execute([$user_id]);
while ($sub = $active_subscriptions->fetch(PDO::FETCH_ASSOC)) {
    generate_meals_for_subscription($conn, $sub);
}

$upcoming = $conn->prepare(
    "SELECT sm.*, m.name AS meal_name, sps.time_from, sps.time_to, sps.location, sps.status AS pickup_status
     FROM `subscription_meals` AS sm
     INNER JOIN `subscriptions` AS s ON sm.subscription_id = s.id
     INNER JOIN `meal_plans` AS m ON s.meal_plan_id = m.id
     LEFT JOIN `subscription_pickup_slots` AS sps
       ON sps.subscription_id = sm.subscription_id AND sps.pickup_date = sm.meal_date AND sps.status != 'Cancelled'
     WHERE s.user_id = ? AND sm.meal_date >= CURDATE()
     ORDER BY sm.meal_date ASC, sm.id ASC",
);
$upcoming->execute([$user_id]);

$history = $conn->prepare(
    "SELECT sm.*, m.name AS meal_name, sps.time_from, sps.time_to, sps.location, sps.status AS pickup_status
     FROM `subscription_meals` AS sm
     INNER JOIN `subscriptions` AS s ON sm.subscription_id = s.id
     INNER JOIN `meal_plans` AS m ON s.meal_plan_id = m.id
     LEFT JOIN `subscription_pickup_slots` AS sps
       ON sps.subscription_id = sm.subscription_id AND sps.pickup_date = sm.meal_date AND sps.status != 'Cancelled'
     WHERE s.user_id = ? AND sm.meal_date < CURDATE()
     ORDER BY sm.meal_date DESC, sm.id DESC",
);
$history->execute([$user_id]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>daily meals</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/user_style.css">

</head>
<body>

<?php include "user_header.php"; ?>

<section class="placed-orders">

   <h1 class="title">upcoming meals</h1>

   <div class="box-container">

      <?php if ($upcoming->rowCount() > 0) {
          while ($meal = $upcoming->fetch(PDO::FETCH_ASSOC)) { ?>
      <div class="box">
         <p> plan : <span><?= $meal["meal_name"] ?></span> </p>
         <p> meal date : <span><?= $meal["meal_date"] ?></span> </p>
         <p> status : <span><?= $meal["status"] ?></span> </p>
         <p> pickup slot : <span>
            <?php if (!empty($meal["time_from"])) { ?>
               <?= $meal["time_from"] ?> - <?= $meal["time_to"] ?> (<?= $meal[
     "location"
 ] ?>)
            <?php } else { ?>
               not assigned
            <?php } ?>
         </span></p>
      </div>
      <?php }
      } else {
          echo '<p class="empty">no upcoming meals found!</p>';
      } ?>

   </div>

</section>

<section class="placed-orders" style="margin-top:2rem;">

   <h1 class="title">meal history</h1>

   <div class="box-container">

      <?php if ($history->rowCount() > 0) {
          while ($meal = $history->fetch(PDO::FETCH_ASSOC)) { ?>
      <div class="box">
         <p> plan : <span><?= $meal["meal_name"] ?></span> </p>
         <p> meal date : <span><?= $meal["meal_date"] ?></span> </p>
         <p> status : <span><?= $meal["status"] ?></span> </p>
         <p> pickup slot : <span>
            <?php if (!empty($meal["time_from"])) { ?>
               <?= $meal["time_from"] ?> - <?= $meal["time_to"] ?> (<?= $meal[
     "location"
 ] ?>)
            <?php } else { ?>
               not assigned
            <?php } ?>
         </span></p>
      </div>
      <?php }
      } else {
          echo '<p class="empty">no meal history yet!</p>';
      } ?>

   </div>

</section>

<?php include "user_footer.php"; ?>

<script src="js/user.js"></script>

</body>
</html>

