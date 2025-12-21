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

   <h1 class="title">subscription history</h1>

   <div class="box-container">

   <?php
   $select_orders = $conn->prepare(
       "SELECT so.*, m.name AS meal_name, m.duration
          FROM `subscription_orders` AS so
          LEFT JOIN `meal_plans` AS m ON so.meal_plan_id = m.id
          WHERE so.user_id = ?
          ORDER BY so.placed_on DESC",
   );
   $select_orders->execute([$user_id]);
   $orders = $select_orders->fetchAll(PDO::FETCH_ASSOC);

   if (!$orders) {
       $select_orders = $conn->prepare(
           "SELECT s.start_date AS placed_on, s.status AS subscription_status,
                   s.payment_status, m.name AS meal_name, m.duration, m.price
            FROM `subscriptions` AS s
            LEFT JOIN `meal_plans` AS m ON s.meal_plan_id = m.id
            WHERE s.user_id = ?
            ORDER BY s.start_date DESC",
       );
       $select_orders->execute([$user_id]);
       $orders = $select_orders->fetchAll(PDO::FETCH_ASSOC);
   }

   if ($orders) {
       foreach ($orders as $fetch_orders) {
           $plan_name =
               $fetch_orders["meal_name"] !== null
                   ? $fetch_orders["meal_name"]
                   : $fetch_orders["plan_summary"] ?? "N/A"; ?>
   <div class="box">
      <p> placed on : <span><?= $fetch_orders["placed_on"] ?></span> </p>
      <p> meal plan : <span><?= $plan_name ?></span> </p>
      <p> duration : <span><?= $fetch_orders["duration"] !== null
          ? $fetch_orders["duration"] . " days"
          : "N/A" ?></span> </p>
      <p> total price : <span>$<?= isset($fetch_orders["total_price"])
          ? $fetch_orders["total_price"]
          : $fetch_orders["price"] ?? "N/A" ?>/-</span> </p>
      <p> payment status : <span style="color:<?php if (
          $fetch_orders["payment_status"] == "Unpaid"
      ) {
          echo "red";
      } else {
          echo "green";
      } ?>"><?= $fetch_orders["payment_status"] ?></span> </p>
      <?php if (isset($fetch_orders["subscription_status"])) { ?>
      <p> subscription status : <span><?= $fetch_orders[
          "subscription_status"
      ] ?></span> </p>
      <?php } ?>
   </div>
   <?php
       }
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
