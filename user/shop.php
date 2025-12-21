<?php

@include "../config.php";

session_start();

$user_id = $_SESSION["user_id"];

if (!isset($user_id)) {
    header("location:login.php");
}

if (isset($_POST["add_to_wishlist"])) {
    $meal_plan_id = $_POST["meal_plan_id"];
    $meal_plan_id = filter_var($meal_plan_id, FILTER_SANITIZE_NUMBER_INT);

    $check_wishlist_numbers = $conn->prepare(
        "SELECT COUNT(*) AS total FROM `wishlist` WHERE meal_plan_id = ? AND user_id = ?",
    );
    $check_wishlist_numbers->execute([$meal_plan_id, $user_id]);
    $wishlist_total = (int) $check_wishlist_numbers->fetch(PDO::FETCH_ASSOC)[
        "total"
    ];

    $check_subscription = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM `subscriptions`
         WHERE user_id = ? AND meal_plan_id = ? AND status IN ('Active','Pending')",
    );
    $check_subscription->execute([$user_id, $meal_plan_id]);
    $subscription_total = (int) $check_subscription->fetch(PDO::FETCH_ASSOC)[
        "total"
    ];

    if ($wishlist_total > 0) {
        $message[] = "already saved!";
    } elseif ($subscription_total > 0) {
        $message[] = "plan already selected for subscription!";
    } else {
        $insert_wishlist = $conn->prepare(
            "INSERT INTO `wishlist`(user_id, meal_plan_id) VALUES(?,?)",
        );
        $insert_wishlist->execute([$user_id, $meal_plan_id]);
        $message[] = "plan saved!";
    }
}

if (isset($_POST["subscribe"])) {
    $meal_plan_id = $_POST["meal_plan_id"];
    $meal_plan_id = filter_var($meal_plan_id, FILTER_SANITIZE_NUMBER_INT);

    $select_plan = $conn->prepare(
        "SELECT duration FROM `meal_plans` WHERE id = ?",
    );
    $select_plan->execute([$meal_plan_id]);
    $plan = $select_plan->fetch(PDO::FETCH_ASSOC);

    $check_existing = $conn->prepare(
        "SELECT COUNT(*) AS total FROM `subscriptions` WHERE user_id = ? AND status IN ('Pending','Active')",
    );
    $check_existing->execute([$user_id]);
    $existing_total = (int) $check_existing->fetch(PDO::FETCH_ASSOC)["total"];

    if (!$plan) {
        $message[] = "meal plan not found!";
    } elseif ($existing_total > 0) {
        $message[] = "You already subscribed to a plan.";
    } else {
        $start_date = date("Y-m-d");
        $end_date = date(
            "Y-m-d",
            strtotime($start_date . " +" . $plan["duration"] . " days"),
        );

        $insert_subscription = $conn->prepare(
            "INSERT INTO `subscriptions`(user_id, meal_plan_id, start_date, end_date, status, payment_status) VALUES(?,?,?,?,?,?)",
        );
        $insert_subscription->execute([
            $user_id,
            $meal_plan_id,
            $start_date,
            $end_date,
            "Pending",
            "unpaid",
        ]);

        $message[] = "plan selected for subscription!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>meal plans</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/user_style.css">

</head>
<body>

<?php include "user_header.php"; ?>

<section class="products">

   <h1 class="title">all meal plans</h1>

   <div class="box-container">

   <?php
   $select_plans = $conn->prepare("SELECT * FROM `meal_plans`");
   $select_plans->execute();
   if ($select_plans->rowCount() > 0) {
       while ($fetch_plan = $select_plans->fetch(PDO::FETCH_ASSOC)) { ?>
   <form action="" class="box" method="POST">
      <div class="price">$<span><?= $fetch_plan["price"] ?></span></div>
      <img src="uploaded_img/<?= $fetch_plan["image"] ?>" alt="">
      <div class="name"><?= $fetch_plan["name"] ?></div>
      <p><?= $fetch_plan["description"] ?></p>
      <p><strong>Diet:</strong> <?= $fetch_plan["diet_type"] ?></p>
      <p><strong>Calories:</strong> <?= $fetch_plan["calories"] ?> / day</p>
      <p><strong>Duration:</strong> <?= $fetch_plan["duration"] ?> days</p>
      <input type="hidden" name="meal_plan_id" value="<?= $fetch_plan["id"] ?>">
      <input type="submit" value="save plan" class="option-btn" name="add_to_wishlist">
      <input type="submit" value="subscribe now" class="btn" name="subscribe">
   </form>
   <?php }
   } else {
       echo '<p class="empty">no meal plans added yet!</p>';
   }
   ?>

   </div>

</section>

<?php include "user_footer.php"; ?>

<script src="js/user.js"></script>

</body>
</html>
