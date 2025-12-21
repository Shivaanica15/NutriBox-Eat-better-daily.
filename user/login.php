<?php

@include "../config.php";

session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["form_type"])) {
    if ($_POST["form_type"] === "login") {
        $email = $_POST["email"] ?? "";
        $email = filter_var($email, FILTER_SANITIZE_STRING);
        $pass = $_POST["pass"] ?? "";
        $pass = filter_var($pass, FILTER_SANITIZE_STRING);

        $sql = "SELECT * FROM `users` WHERE email = ? AND password = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$email, $pass]);
        $rowCount = $stmt->rowCount();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($rowCount > 0) {
            if ($row["user_type"] == "admin") {
                $_SESSION["admin_id"] = $row["id"];
                header("location:../admin/admin_page.php");
                exit();
            } elseif ($row["user_type"] == "user") {
                $_SESSION["user_id"] = $row["id"];
                header("location:home.php");
                exit();
            } else {
                $message[] = "no user found!";
            }
        } else {
            $message[] = "incorrect email or password!";
        }
    }

    if ($_POST["form_type"] === "register") {
        $name = $_POST["name"] ?? "";
        $name = filter_var($name, FILTER_SANITIZE_STRING);
        $email = $_POST["email"] ?? "";
        $email = filter_var($email, FILTER_SANITIZE_STRING);
        $pass = $_POST["pass"] ?? "";
        $pass = filter_var($pass, FILTER_SANITIZE_STRING);
        $cpass = $_POST["cpass"] ?? "";
        $cpass = filter_var($cpass, FILTER_SANITIZE_STRING);

        $image = "";
        $image_size = 0;
        $image_tmp_name = "";
        if (isset($_FILES["image"]) && !empty($_FILES["image"]["name"])) {
            $image = filter_var(
                $_FILES["image"]["name"],
                FILTER_SANITIZE_STRING,
            );
            $image_size = $_FILES["image"]["size"] ?? 0;
            $image_tmp_name = $_FILES["image"]["tmp_name"] ?? "";
        }

        $select = $conn->prepare("SELECT * FROM `users` WHERE email = ?");
        $select->execute([$email]);

        if ($select->rowCount() > 0) {
            $message[] = "user email already exist!";
        } else {
            if ($pass != $cpass) {
                $message[] = "confirm password not matched!";
            } else {
                $final_image = $image !== "" ? $image : "pic-4.png";
                $insert = $conn->prepare(
                    "INSERT INTO `users`(name, email, password, image) VALUES(?,?,?,?)",
                );
                $insert->execute([$name, $email, $pass, $final_image]);

                if ($insert) {
                    if ($image !== "" && $image_size > 2000000) {
                        $message[] = "image size is too large!";
                    } elseif ($image !== "" && $image_tmp_name !== "") {
                        $image_folder = "uploaded_img/" . $image;
                        move_uploaded_file($image_tmp_name, $image_folder);
                        $message[] = "registered successfully!";
                        header("location:login.php");
                        exit();
                    } else {
                        $message[] = "registered successfully!";
                        header("location:login.php");
                        exit();
                    }
                }
            }
        }
    }
}

$default_mode = $default_mode ?? null;
if (
    $default_mode === null &&
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["form_type"])
) {
    $default_mode = $_POST["form_type"] === "register" ? "register" : "login";
}
$default_mode = $default_mode ?? ($_GET["mode"] ?? "login");
$default_mode = $default_mode === "register" ? "register" : "login";
$page_title =
    $default_mode === "register" ? "NutriBox | Register" : "NutriBox | Login";
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title><?= $page_title ?></title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/user_base.css">
</head>
<body>

<?php if (isset($message)) {
    foreach ($message as $message) {
        echo '
      <div class="message">
         <span>' .
            $message .
            '</span>
         <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
      </div>
      ';
    }
} ?>

<section class="form-container">
   <?php if ($default_mode === "register") { ?>
   <form action="" method="POST" enctype="multipart/form-data">
      <h3>Create Account</h3>
      <input type="hidden" name="form_type" value="register">
      <input type="text" name="name" class="box" placeholder="enter your name" required>
      <input type="email" name="email" class="box" placeholder="enter your email" required>
      <input type="password" name="pass" class="box" placeholder="enter your password" required>
      <input type="password" name="cpass" class="box" placeholder="confirm your password" required>
      <input type="file" name="image" class="box" accept="image/jpg, image/jpeg, image/png">
      <input type="submit" class="btn" value="Create account">
      <p>already have an account? <a href="login.php">login now</a></p>
   </form>
   <?php } else { ?>
   <form action="" method="POST">
      <h3>Welcome Back</h3>
      <input type="hidden" name="form_type" value="login">
      <input type="email" name="email" class="box" placeholder="enter your email" required>
      <input type="password" name="pass" class="box" placeholder="enter your password" required>
      <input type="submit" class="btn" value="Login">
      <p>don't have an account? <a href="register.php">register now</a></p>
   </form>
   <?php } ?>
</section>
</body>
</html>
