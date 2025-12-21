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
   <title>about</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/user_style.css">

</head>
<body>

<?php include "user_header.php"; ?>

<section class="about">

   <div class="about-card">
      <div class="row">

         <div class="box">
            <img src="../images/about-img-1.png" alt="Freshly prepared meal ingredients">
            <h3>why choose us?</h3>
            <p>NutriBox offers chef-prepared meals designed with nutrition-first portions and clear calorie targets. Every plan supports consistent healthy eating without sacrificing taste or variety.</p>
            <a href="contact.php" class="btn">Contact Us</a>
         </div>

         <div class="box">
            <img src="../images/about-img-2.png" alt="Balanced nutrition-focused meals">
            <h3>what we provide?</h3>
            <p>Weekly rotating meal plans, fresh ingredients, and convenient ordering with pickup slots that fit your schedule. Choose from balanced, high-protein, or plant-based plans tailored to your goals.</p>
            <a href="shop.php" class="btn">Our Meal Plans</a>
         </div>

      </div>
   </div>

</section>

<section class="reviews">

   <h1 class="title">client reviews</h1>

   <div class="box-container">

      <div class="box">
         <img src="../images/pic-1.png" alt="Fresh meal review">
         <p>Meals taste fresh, portions are consistent, and the nutrition labels make tracking easy.</p>
         <div class="stars">
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star-half-alt"></i>
         </div>
         <h3>Karthik steve</h3>
      </div>

      <div class="box">
         <img src="../images/pic-2.png" alt="Healthy meal review">
         <p>The pickup slots are reliable and the meals stay fresh for days.</p>
         <div class="stars">
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star-half-alt"></i>
         </div>
         <h3>Santos Keerthika</h3>
      </div>

      <div class="box">
         <img src="../images/pic-3.png" alt="Chef-prepared meal review">
         <p>Great variety each week and the high-protein plan keeps me on track.</p>
         <div class="stars">
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star-half-alt"></i>
         </div>
         <h3>Rina Patel</h3>
      </div>

      <div class="box">
         <img src="../images/pic-4.png" alt="Meal subscription review">
         <p>Healthy meals without the prep work. The flavors are balanced and clean.</p>
         <div class="stars">
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star-half-alt"></i>
         </div>
         <h3>Sharika Kishor</h3>
      </div>

      <div class="box">
         <img src="../images/pic-5.png" alt="Nutrition-focused meal review">
         <p>Clear ingredient lists and calories. It feels like a premium service.</p>
         <div class="stars">
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star-half-alt"></i>
         </div>
         <h3>Kumaran Theepan</h3>
      </div>

      <div class="box">
         <img src="../images/pic-6.png" alt="Fresh meal delivery review">
         <p>Consistent quality, no oily or heavy meals, and easy ordering.</p>
         <div class="stars">
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star-half-alt"></i>
         </div>
         <h3>Mala Perera</h3>
      </div>

   </div>

</section>









<?php include "user_footer.php"; ?>

<script src="js/user.js"></script>

</body>
</html>
