<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_guard.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location:login.php');
}

function get_active_templates_for_date($conn, $date)
{
    $weekday = (int) (new DateTime($date))->format('N');
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
        $check->execute([$template['id'], $date]);
        if ($check->rowCount() > 0) {
            continue;
        }
        $insert->execute([
            $template['id'],
            $date,
            $template['time_from'],
            $template['time_to'],
            $template['location'],
            $template['max_capacity'],
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
    return isset($row['total']) ? (int) $row['total'] : 0;
}

function compute_slot_status($slot, $used)
{
    $today = new DateTime(date('Y-m-d'));
    $pickup_date = new DateTime($slot['pickup_date']);
    if ($pickup_date < $today) {
        return 'Expired';
    }

    if ($pickup_date == $today) {
        $now = new DateTime(date('H:i:s'));
        $time_to = new DateTime($slot['time_to']);
        if ($time_to < $now) {
            return 'Expired';
        }
    }

    if ($used >= (int) $slot['max_capacity']) {
        return 'Full';
    }

    return 'Available';
}

function refresh_slot_status($conn, $slot, $used)
{
    $new_status = compute_slot_status($slot, $used);
    if ($new_status === $slot['status']) {
        return $new_status;
    }
    $update = $conn->prepare(
        "UPDATE `pickup_slots` SET status = ? WHERE id = ?",
    );
    $update->execute([$new_status, $slot['id']]);
    return $new_status;
}

$view_date = isset($_GET['date']) ? trim($_GET['date']) : '';
if ($view_date === '') {
    $view_date = date('Y-m-d');
}

if (table_exists($conn, 'pickup_slot_templates') && table_exists($conn, 'pickup_slots')) {
    materialize_slots_for_date($conn, $view_date);
}

$slots = [];
if (table_exists($conn, 'pickup_slots')) {
    $select_slots = $conn->prepare(
        "SELECT * FROM `pickup_slots` WHERE pickup_date = ? ORDER BY time_from ASC",
    );
    $select_slots->execute([$view_date]);
    $slots = $select_slots->fetchAll(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>pickup slot availability</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">

</head>
<body class="admin-body">

<?php include 'admin_header.php'; ?>

<section class="placed-orders">

   <h1 class="title">pickup slot availability</h1>

   <div class="box-container">

      <div class="box" style="width:100%;">
         <form action="" method="GET">
            <label for="date">select date</label>
            <input type="date" name="date" id="date" class="box" value="<?= $view_date; ?>" required>
            <input type="submit" class="btn" value="view slots">
         </form>
      </div>

      <?php if (!$slots) {
          echo '<p class="empty">no slots found for this date.</p>';
      } else {
          foreach ($slots as $slot) {
              $used = get_slot_usage($conn, $slot['id']);
              $status = refresh_slot_status($conn, $slot, $used);
              $remaining = (int) $slot['max_capacity'] - $used;
      ?>
      <div class="box">
         <p> time : <span><?= $slot['time_from']; ?> - <?= $slot['time_to']; ?></span> </p>
         <p> location : <span><?= $slot['location']; ?></span> </p>
         <p> capacity : <span><?= $used; ?> / <?= $slot['max_capacity']; ?> (<?= $remaining; ?> left)</span> </p>
         <p> status : <span><?= $status; ?></span> </p>
      </div>
      <?php }
      }
      ?>

   </div>

</section>

<?php include "admin_footer.php"; ?>

</body>
</html>


