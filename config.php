<?php

$db_name = "mysql:host=localhost;dbname=nutribox_db";
$username = "root";
$password = "";

$conn = new PDO($db_name, $username, $password);

if (!defined("SMTP_HOST")) {
    define("SMTP_HOST", "smtp.example.com");
}
if (!defined("SMTP_PORT")) {
    define("SMTP_PORT", 587);
}
if (!defined("SMTP_USERNAME")) {
    define("SMTP_USERNAME", "no-reply@example.com");
}
if (!defined("SMTP_PASSWORD")) {
    define("SMTP_PASSWORD", "change-me");
}
if (!defined("FROM_EMAIL")) {
    define("FROM_EMAIL", "no-reply@example.com");
}
if (!defined("FROM_NAME")) {
    define("FROM_NAME", "NutriBox");
}

?>
