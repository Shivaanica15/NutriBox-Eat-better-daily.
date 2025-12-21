<?php

@include '../config.php';

session_start();

$user_id = $_SESSION['user_id'];

if(!isset($user_id)){
   header('location:login.php');
};

$pending_plan = null;
$pending_query = $conn->prepare(
   "SELECT s.id, s.meal_plan_id, m.name, m.description, m.price, m.duration, m.calories, m.diet_type
    FROM `subscriptions` AS s
    INNER JOIN `meal_plans` AS m ON s.meal_plan_id = m.id
    WHERE s.user_id = ? AND s.status = 'Pending'
    LIMIT 1"
);
$pending_query->execute([$user_id]);
$pending_plan = $pending_query->fetch(PDO::FETCH_ASSOC);

$active_check = $conn->prepare("SELECT id FROM `subscriptions` WHERE user_id = ? AND status = 'Active'");
$active_check->execute([$user_id]);

if(isset($_POST['checkout'])){

   $method = $_POST['method'];
   $method = filter_var($method, FILTER_SANITIZE_STRING);

   if(!$pending_plan){
      $message[] = 'no pending subscription found!';
   }elseif($active_check->rowCount() > 0){
      $message[] = 'you already have an active subscription!';
   }else{
      $placed_on = date('Y-m-d');
      $plan_summary = $pending_plan['name'].' | '.$pending_plan['calories'].' Calories/day';

      $insert_order = $conn->prepare(
         "INSERT INTO `subscription_orders`
         (user_id, meal_plan_id, plan_summary, total_price, payment_method, placed_on, payment_status)
         VALUES(?,?,?,?,?,?,?)"
      );
      $insert_order->execute([
         $user_id,
         $pending_plan['meal_plan_id'],
         $plan_summary,
         $pending_plan['price'],
         $method,
         $placed_on,
         'Paid'
      ]);

      $activate_subscription = $conn->prepare(
         "UPDATE `subscriptions` SET status = 'Active', payment_status = 'Paid' WHERE id = ? AND user_id = ?"
      );
      $activate_subscription->execute([$pending_plan['id'], $user_id]);

      $message[] = 'subscription activated successfully!';
      $pending_plan = null;
   }

}

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>checkout</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/user_style.css">

</head>
<body>

<?php include 'user_header.php'; ?>

<section class="display-orders">

   <?php if($pending_plan){ ?>
      <p><?= $pending_plan['name']; ?> <span>(<?= $pending_plan['diet_type']; ?>, <?= $pending_plan['calories']; ?> calories/day)</span></p>
      <p><?= $pending_plan['description']; ?></p>
      <p>duration: <span><?= $pending_plan['duration']; ?> days</span></p>
      <div class="grand-total">total price : <span>$<?= $pending_plan['price']; ?>/-</span></div>
   <?php }else{ ?>
      <p class="empty">no pending subscription found!</p>
   <?php } ?>

</section>

<section class="checkout-orders">

   <form action="" method="POST">

      <h3>confirm your subscription</h3>

      <div class="flex">
         <div class="inputBox">
            <span>payment method :</span>
            <select name="method" class="box" required>
               <option value="cash on delivery">cash on delivery</option>
               <option value="credit card">credit card</option>
               <option value="paytm">paytm</option>
               <option value="paypal">paypal</option>
            </select>
         </div>
      </div>

      <input type="submit" name="checkout" class="btn <?= ($pending_plan)?'':'disabled'; ?>" value="activate subscription">

   </form>

</section>

<?php include 'user_footer.php'; ?>

<script src="js/user.js"></script>

</body>
</html>

