<?php

@include "../config.php";

session_start();

$user_id = $_SESSION["user_id"];

if (!isset($user_id)) {
    header("location:login.php");
}

$slots = $conn->prepare(
    "SELECT sps.*, s.status AS subscription_status, s.approval_status, m.name AS meal_name
     FROM `subscription_pickup_slots` AS sps
     INNER JOIN `subscriptions` AS s ON sps.subscription_id = s.id
     INNER JOIN `meal_plans` AS m ON s.meal_plan_id = m.id
     WHERE s.user_id = ?
     ORDER BY sps.pickup_date DESC, sps.time_from DESC, sps.id DESC",
);
$slots->execute([$user_id]);

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>pickup slots</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/user_style.css">

</head>
<body>

<?php include "user_header.php"; ?>

<section class="placed-orders">

   <h1 class="title">pickup slots</h1>

   <div class="box-container">

      <?php if ($slots->rowCount() > 0) {
          while ($slot = $slots->fetch(PDO::FETCH_ASSOC)) { ?>
      <div class="box">
         <p> plan : <span><?= $slot["meal_name"] ?></span> </p>
         <p> subscription status : <span><?= $slot[
    "subscription_status"
] ?></span> </p>
         <p> approval : <span><?= $slot["approval_status"] ?></span> </p>
         <p> pickup date : <span><?= $slot["pickup_date"] ?></span> </p>
         <p> time : <span><?= $slot["time_from"] ?> - <?= $slot[
    "time_to"
] ?></span> </p>
         <p> location : <span><?= $slot["location"] ?></span> </p>
         <p> slot status : <span><?= $slot["status"] ?></span> </p>
      </div>
      <?php }
      } else {
          echo '<p class="empty">no pickup slots assigned yet!</p>';
      }
      ?>

   </div>

</section>

<?php include "user_footer.php"; ?>

<script src="js/user.js"></script>

</body>
</html>

