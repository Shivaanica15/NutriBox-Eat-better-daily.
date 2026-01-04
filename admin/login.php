<?php

require_once __DIR__ . "/../config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (isset($_SESSION["admin_id"])) {
    header("location:admin_page.php");
    exit();
}

$message = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = filter_var($_POST["email"] ?? "", FILTER_SANITIZE_STRING);
    $pass = $_POST["password"] ?? "";

    if ($email === "" || $pass === "") {
        $message[] = "email and password are required.";
    } else {
        $select = $conn->prepare(
            "SELECT id, password, user_type FROM `users` WHERE email = ? LIMIT 1",
        );
        $select->execute([$email]);
        $user = $select->fetch(PDO::FETCH_ASSOC);

        $password_ok = false;
        if ($user) {
            $stored = $user["password"];
            if (password_verify($pass, $stored)) {
                $password_ok = true;
            } elseif (hash_equals($stored, $pass)) {
                $password_ok = true;
            }
        }

        if (!$user || $user["user_type"] !== "admin" || !$password_ok) {
            $message[] = "invalid admin credentials.";
        } else {
            session_regenerate_id(true);
            $_SESSION["admin_id"] = $user["id"];
            header("location:admin_page.php");
            exit();
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
   <title>admin login</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">
</head>
<body class="admin-body">

<div class="box-container" style="max-width:460px;margin:3rem auto;">
   <div class="box">
      <h1 class="title" style="margin-bottom:1rem;">admin login</h1>

      <?php if ($message) { ?>
         <?php foreach ($message as $msg) { ?>
            <div class="message">
               <span><?= $msg ?></span>
               <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
            </div>
         <?php } ?>
      <?php } ?>

      <form action="" method="POST">
         <label for="email">email</label>
         <input type="email" name="email" id="email" class="box" required>
         <label for="password">password</label>
         <input type="password" name="password" id="password" class="box" required>
         <input type="submit" class="btn" value="login">
      </form>
   </div>
</div>

</body>
</html>
