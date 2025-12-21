<?php

@include '../config.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if(!isset($admin_id)){
   header('location:login.php');
};

if(isset($_POST['add_meal_plan'])){

   $name = $_POST['name'];
   $name = filter_var($name, FILTER_SANITIZE_STRING);
   $description = $_POST['description'];
   $description = filter_var($description, FILTER_SANITIZE_STRING);
   $diet_type = $_POST['diet_type'];
   $diet_type = filter_var($diet_type, FILTER_SANITIZE_STRING);

   $price = filter_var($_POST['price'], FILTER_VALIDATE_INT);
   $duration = filter_var($_POST['duration'], FILTER_VALIDATE_INT);
   $calories = filter_var($_POST['calories'], FILTER_VALIDATE_INT);

   $image = $_FILES['image']['name'];
   $image = filter_var($image, FILTER_SANITIZE_STRING);
   $image_size = $_FILES['image']['size'];
   $image_tmp_name = $_FILES['image']['tmp_name'];
   $image_folder = '../uploaded_img/'.$image;

   $select_plans = $conn->prepare("SELECT * FROM `meal_plans` WHERE name = ?");
   $select_plans->execute([$name]);

   if($select_plans->rowCount() > 0){
      $message[] = 'meal plan name already exists!';
   }elseif($price === false || $price < 0 || $duration === false || $duration < 1 || $calories === false || $calories < 0){
      $message[] = 'please enter valid numeric values!';
   }else{

      $insert_plan = $conn->prepare("INSERT INTO `meal_plans`(name, description, price, duration, calories, diet_type, image) VALUES(?,?,?,?,?,?,?)");
      $insert_plan->execute([$name, $description, $price, $duration, $calories, $diet_type, $image]);

      if($insert_plan){
         if($image_size > 2000000){
            $message[] = 'image size is too large!';
         }else{
            move_uploaded_file($image_tmp_name, $image_folder);
            $message[] = 'new meal plan added!';
         }
      }

   }

};

if(isset($_GET['delete'])){

   $delete_id = $_GET['delete'];
   $select_delete_image = $conn->prepare("SELECT image FROM `meal_plans` WHERE id = ?");
   $select_delete_image->execute([$delete_id]);
   $fetch_delete_image = $select_delete_image->fetch(PDO::FETCH_ASSOC);
   if($fetch_delete_image && $fetch_delete_image['image'] !== '' && file_exists('../uploaded_img/'.$fetch_delete_image['image'])){
      unlink('../uploaded_img/'.$fetch_delete_image['image']);
   }
   $delete_plans = $conn->prepare("DELETE FROM `meal_plans` WHERE id = ?");
   $delete_plans->execute([$delete_id]);
   $delete_wishlist = $conn->prepare("DELETE FROM `wishlist` WHERE meal_plan_id = ?");
   $delete_wishlist->execute([$delete_id]);
   header('location:admin_products.php');

}

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>manage meal plans</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/admin_style.css">

</head>
<body class="admin-body">

<?php include 'admin_header.php'; ?>

<section class="add-products">

   <h1 class="title">add new meal plan</h1>

   <form action="" method="POST" enctype="multipart/form-data">
      <div class="flex">
         <div class="inputBox">
            <input type="text" name="name" class="box" required placeholder="enter meal plan name">
            <input type="text" name="diet_type" class="box" required placeholder="enter diet type">
         </div>
         <div class="inputBox">
            <input type="number" min="0" name="price" class="box" required placeholder="enter price">
            <input type="number" min="1" name="duration" class="box" required placeholder="enter duration (days)">
         </div>
         <div class="inputBox">
            <input type="number" min="0" name="calories" class="box" required placeholder="enter calories per day">
            <input type="file" name="image" required class="box" accept="image/jpg, image/jpeg, image/png">
         </div>
      </div>
      <textarea name="description" class="box" required placeholder="enter meal plan description" cols="30" rows="10"></textarea>
      <input type="submit" class="btn" value="add meal plan" name="add_meal_plan">
   </form>

</section>

<section class="show-products">

   <h1 class="title">meal plans added</h1>

   <div class="box-container">

   <?php
      $show_plans = $conn->prepare("SELECT * FROM `meal_plans`");
      $show_plans->execute();
      if($show_plans->rowCount() > 0){
         while($fetch_plan = $show_plans->fetch(PDO::FETCH_ASSOC)){
   ?>
   <div class="box">
      <div class="price">$<?= $fetch_plan['price']; ?></div>
      <img src="../uploaded_img/<?= $fetch_plan['image']; ?>" alt="">
      <div class="name"><?= $fetch_plan['name']; ?></div>
      <div class="cat"><?= $fetch_plan['diet_type']; ?></div>
      <div class="details"><?= $fetch_plan['description']; ?></div>
      <div class="details">Calories: <?= $fetch_plan['calories']; ?> / day</div>
      <div class="details">Duration: <?= $fetch_plan['duration']; ?> days</div>
      <div class="flex-btn">
         <a href="admin_update_product.php?update=<?= $fetch_plan['id']; ?>" class="option-btn">update</a>
         <a href="admin_products.php?delete=<?= $fetch_plan['id']; ?>" class="delete-btn" onclick="return confirm('delete this meal plan?');">delete</a>
      </div>
   </div>
   <?php
      }
   }else{
      echo '<p class="empty">no meal plans added yet!</p>';
   }
   ?>

   </div>

</section>

<?php include "admin_footer.php"; ?>

</body>
</html>


