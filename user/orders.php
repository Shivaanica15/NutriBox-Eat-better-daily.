<?php

@include "../config.php";

session_start();

$user_id = $_SESSION["user_id"];

if (!isset($user_id)) {
    header("location:login.php");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>orders</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/user_style.css">

</head>
<body>

<?php include "user_header.php"; ?>

<section class="placed-orders">

   <h1 class="title">Current Subscription</h1>

   <div class="box-container">

   <?php
   $select_current = $conn->prepare(
       "SELECT s.start_date AS placed_on,
               s.status AS subscription_status,
               s.payment_status,
               m.name AS meal_name,
               m.duration,
               m.price
        FROM `subscriptions` AS s
        LEFT JOIN `meal_plans` AS m ON s.meal_plan_id = m.id
        WHERE s.user_id = ?
          AND s.status IN ('Active','Pending','Paused','ChangeRequested')
        ORDER BY s.id DESC
        LIMIT 1",
   );
   $select_current->execute([$user_id]);
   $current = $select_current->fetch(PDO::FETCH_ASSOC);

   if ($current) {
       $plan_name =
           $current["meal_name"] !== null ? $current["meal_name"] : "N/A"; ?>
   <div class="box">
      <p> placed on : <span><?= $current["placed_on"] ?></span> </p>
      <p> meal plan : <span><?= $plan_name ?></span> </p>
      <p> duration : <span><?= $current["duration"] !== null
          ? $current["duration"] . " days"
          : "N/A" ?></span> </p>
      <p> total price : <span>$<?= $current["price"] ?? "N/A" ?>/-</span> </p>
      <p> payment status : <span style="color:<?php if (
          $current["payment_status"] == "Unpaid"
      ) {
          echo "red";
      } else {
          echo "green";
      } ?>"><?= $current["payment_status"] ?></span> </p>
      <p> subscription status : <span><?= $current[
          "subscription_status"
      ] ?></span> </p>
   </div>
   <?php
   } else {
       echo '<p class="empty">no subscriptions placed yet!</p>';
   }
   ?>

   </div>

</section>

<?php include "user_footer.php"; ?>

<script src="js/user.js"></script>

</body>
</html>
