<?php

@include "../config.php";

session_start();

$user_id = $_SESSION["user_id"];

if (!isset($user_id)) {
    header("location:login.php");
}

if (isset($_POST["send"])) {
    $name = $_POST["name"];
    $name = filter_var($name, FILTER_SANITIZE_STRING);
    $email = $_POST["email"];
    $email = filter_var($email, FILTER_SANITIZE_STRING);
    $number = $_POST["number"];
    $number = filter_var($number, FILTER_SANITIZE_STRING);
    $number = preg_replace("/\s+/", "", $number);
    $msg = $_POST["msg"];
    $msg = filter_var($msg, FILTER_SANITIZE_STRING);

    if (!preg_match('/^\d{10}$/', $number)) {
        $message[] = "invalid phone number.";
    } else {
        $select_message = $conn->prepare(
            "SELECT * FROM `messages` WHERE name = ? AND email = ? AND number = ? AND message = ?",
        );
        $select_message->execute([$name, $email, $number, $msg]);

        if ($select_message->rowCount() > 0) {
            $message[] = "already sent message!";
        } else {
            $insert_message = $conn->prepare(
                "INSERT INTO `messages`(user_id, name, email, number, message) VALUES(?,?,?,?,?)",
            );
            $insert_message->execute([$user_id, $name, $email, $number, $msg]);

            $message[] = "sent message successfully!";
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
   <title>contact</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/user_style.css">

</head>
<body>

<?php include "user_header.php"; ?>

<section class="contact">

   <h1 class="title">get in touch</h1>

   <form action="" method="POST">
      <input type="text" name="name" class="box" required placeholder="enter your name">
      <input type="email" name="email" class="box" required placeholder="enter your email">
      <input type="tel" name="number" class="box" required placeholder="enter your number" pattern="[0-9]{10}" inputmode="numeric" maxlength="10" autocomplete="tel">
      <textarea name="msg" class="box" required placeholder="enter your message" cols="30" rows="10"></textarea>
      <input type="submit" value="send message" class="btn" name="send">
   </form>

</section>








<?php include "user_footer.php"; ?>

<script src="js/user.js"></script>

</body>
</html>
