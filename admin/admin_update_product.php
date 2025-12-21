<?php

@include '../config.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if(!isset($admin_id)){
   header('location:login.php');
};

if(isset($_POST['update_meal_plan'])){

   $pid = $_POST['pid'];
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
   $old_image = $_POST['old_image'];

   if($price === false || $price < 0 || $duration === false || $duration < 1 || $calories === false || $calories < 0){
      $message[] = 'please enter valid numeric values!';
   }else{
      $update_plan = $conn->prepare("UPDATE `meal_plans` SET name = ?, description = ?, price = ?, duration = ?, calories = ?, diet_type = ? WHERE id = ?");
      $update_plan->execute([$name, $description, $price, $duration, $calories, $diet_type, $pid]);

      $message[] = 'meal plan updated successfully!';

      if(!empty($image)){
         if($image_size > 2000000){
            $message[] = 'image size is too large!';
         }else{

            $update_image = $conn->prepare("UPDATE `meal_plans` SET image = ? WHERE id = ?");
            $update_image->execute([$image, $pid]);

            if($update_image){
               move_uploaded_file($image_tmp_name, $image_folder);
               if($old_image !== '' && file_exists('../uploaded_img/'.$old_image)){
                  unlink('../uploaded_img/'.$old_image);
               }
               $message[] = 'image updated successfully!';
            }
         }
      }
   }

}

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>update meal plan</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/admin_style.css">

</head>
<body class="admin-body">

<?php include 'admin_header.php'; ?>

<section class="update-product">

   <h1 class="title">update meal plan</h1>

   <?php
      $update_id = $_GET['update'];
      $select_plans = $conn->prepare("SELECT * FROM `meal_plans` WHERE id = ?");
      $select_plans->execute([$update_id]);
      if($select_plans->rowCount() > 0){
         while($fetch_plan = $select_plans->fetch(PDO::FETCH_ASSOC)){
   ?>
   <form action="" method="post" enctype="multipart/form-data">
      <input type="hidden" name="old_image" value="<?= $fetch_plan['image']; ?>">
      <input type="hidden" name="pid" value="<?= $fetch_plan['id']; ?>">
      <img src="../uploaded_img/<?= $fetch_plan['image']; ?>" alt="">
      <input type="text" name="name" placeholder="enter meal plan name" required class="box" value="<?= $fetch_plan['name']; ?>">
      <input type="text" name="diet_type" placeholder="enter diet type" required class="box" value="<?= $fetch_plan['diet_type']; ?>">
      <input type="number" name="price" min="0" placeholder="enter price" required class="box" value="<?= $fetch_plan['price']; ?>">
      <input type="number" name="duration" min="1" placeholder="enter duration (days)" required class="box" value="<?= $fetch_plan['duration']; ?>">
      <input type="number" name="calories" min="0" placeholder="enter calories per day" required class="box" value="<?= $fetch_plan['calories']; ?>">
      <textarea name="description" required placeholder="enter meal plan description" class="box" cols="30" rows="10"><?= $fetch_plan['description']; ?></textarea>
      <input type="file" name="image" class="box" accept="image/jpg, image/jpeg, image/png">
      <div class="flex-btn">
         <input type="submit" class="btn" value="update meal plan" name="update_meal_plan">
         <a href="admin_products.php" class="option-btn">go back</a>
      </div>
   </form>
   <?php
         }
      }else{
         echo '<p class="empty">no meal plans found!</p>';
      }
   ?>

</section>

<?php include "admin_footer.php"; ?>

</body>
</html>


