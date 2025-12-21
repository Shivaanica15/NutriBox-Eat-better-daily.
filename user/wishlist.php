<?php

@include "../config.php";

session_start();

$user_id = $_SESSION["user_id"];

if (!isset($user_id)) {
    header("location:login.php");
}

if (isset($_GET["delete"])) {
    $delete_id = $_GET["delete"];
    $delete_wishlist_item = $conn->prepare(
        "DELETE FROM `wishlist` WHERE id = ?",
    );
    $delete_wishlist_item->execute([$delete_id]);
    header("location:wishlist.php");
}

if (isset($_GET["delete_all"])) {
    $delete_wishlist_item = $conn->prepare(
        "DELETE FROM `wishlist` WHERE user_id = ?",
    );
    $delete_wishlist_item->execute([$user_id]);
    header("location:wishlist.php");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>wishlist</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/user_style.css">

</head>
<body>

<?php include "user_header.php"; ?>

<section class="wishlist">

   <h1 class="title">saved meal plans</h1>

   <div class="box-container">

   <?php
   $grand_total = 0;
   $select_wishlist = $conn->prepare(
       "SELECT w.id, m.id AS meal_plan_id, m.name, m.price, m.image
          FROM `wishlist` AS w
          INNER JOIN `meal_plans` AS m ON w.meal_plan_id = m.id
          WHERE w.user_id = ?",
   );
   $select_wishlist->execute([$user_id]);
   $wishlist_items = $select_wishlist->fetchAll(PDO::FETCH_ASSOC);
   if ($wishlist_items) {
       foreach ($wishlist_items as $fetch_wishlist) { ?>
   <div class="box">
      <a href="wishlist.php?delete=<?= $fetch_wishlist[
          "id"
      ] ?>" class="fas fa-times" onclick="return confirm('delete this plan from wishlist?');"></a>
      <a href="view_page.php?pid=<?= $fetch_wishlist[
          "meal_plan_id"
      ] ?>" class="fas fa-eye"></a>
      <img src="uploaded_img/<?= $fetch_wishlist["image"] ?>" alt="">
      <div class="name"><?= $fetch_wishlist["name"] ?></div>
      <div class="price">$<?= $fetch_wishlist["price"] ?>/-</div>
   </div>
   <?php $grand_total += $fetch_wishlist["price"];}
   } else {
       echo '<p class="empty">your wishlist is empty</p>';
   }
   ?>
   </div>

   <div class="wishlist-total">
      <p>estimated total : <span>$<?= $grand_total ?>/-</span></p>
      <a href="shop.php" class="option-btn">browse meal plans</a>
      <a href="wishlist.php?delete_all" class="delete-btn <?= $grand_total > 1
          ? ""
          : "disabled" ?>">delete all</a>
   </div>

</section>

<?php include "user_footer.php"; ?>

<script src="js/user.js"></script>

</body>
</html>
