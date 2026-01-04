<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (isset($_SESSION["admin_id"])) {
    header("location:admin_page.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>admin registration</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">
</head>
<body class="admin-body">

<div class="box-container" style="max-width:520px;margin:3rem auto;">
   <div class="box">
      <h1 class="title" style="margin-bottom:1rem;">admin registration</h1>
      <p>Admin accounts are managed by the system. Please contact the site owner to create an admin user.</p>
      <a href="login.php" class="btn" style="margin-top:1rem;display:inline-block;">back to login</a>
   </div>
</div>

</body>
</html>
