<?php

require_once __DIR__ . "/admin_auth.php";

if (isset($_POST["update_order"])) {
    $order_id = $_POST["order_id"];
    $update_payment = $_POST["update_payment"];
    $update_payment = filter_var($update_payment, FILTER_SANITIZE_STRING);
    $update_orders = $conn->prepare(
        "UPDATE `subscription_orders` SET payment_status = ? WHERE id = ?",
    );
    $update_orders->execute([$update_payment, $order_id]);
    $message[] = "payment has been updated!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>subscription orders</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/admin_style.css">

</head>
<body class="admin-body">

<?php include "admin_header.php"; ?>

<section class="placed-orders">

   <h1 class="title">subscription orders</h1>

   <div class="box-container">

      <?php
      $select_orders = $conn->prepare(
          "SELECT so.*, u.name AS user_name, u.email AS user_email, m.name AS meal_name
             FROM `subscription_orders` AS so
             LEFT JOIN `users` AS u ON so.user_id = u.id
             LEFT JOIN `meal_plans` AS m ON so.meal_plan_id = m.id",
      );
      $select_orders->execute();
      if ($select_orders->rowCount() > 0) {
          while ($fetch_orders = $select_orders->fetch(PDO::FETCH_ASSOC)) { ?>
      <div class="box">
         <p> user : <span><?= $fetch_orders["user_name"] ?></span> </p>
         <p> email : <span><?= $fetch_orders["user_email"] ?></span> </p>
         <p> meal plan : <span><?= $fetch_orders["meal_name"] !== null
             ? $fetch_orders["meal_name"]
             : $fetch_orders["plan_summary"] ?></span> </p>
         <p> placed on : <span><?= $fetch_orders["placed_on"] ?></span> </p>
         <p> total price : <span>$<?= $fetch_orders[
             "total_price"
         ] ?>/-</span> </p>
         <p> payment method : <span><?= $fetch_orders[
             "payment_method"
         ] ?></span> </p>
         <form action="" method="POST">
            <input type="hidden" name="order_id" value="<?= $fetch_orders[
                "id"
            ] ?>">
            <select name="update_payment" class="drop-down">
               <option value="" selected disabled><?= $fetch_orders[
                   "payment_status"
               ] ?></option>
               <option value="Paid">Paid</option>
               <option value="Unpaid">Unpaid</option>
            </select>
            <div class="flex-btn">
               <input type="submit" name="update_order" class="option-btn" value="update">
            </div>
         </form>
      </div>
      <?php }
      } else {
          echo '<p class="empty">no subscription orders placed yet!</p>';
      }
      ?>

   </div>

</section>

<?php include "admin_footer.php"; ?>

</body>
</html>
