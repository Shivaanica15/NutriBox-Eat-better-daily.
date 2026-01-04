<?php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../db_guard.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$admin_id = $_SESSION["admin_id"] ?? null;
if (!$admin_id) {
    header("location:login.php");
    exit();
}

$select_admin = $conn->prepare(
    "SELECT id, user_type FROM `users` WHERE id = ? LIMIT 1",
);
$select_admin->execute([$admin_id]);
$admin = $select_admin->fetch(PDO::FETCH_ASSOC);

if (!$admin || $admin["user_type"] !== "admin") {
    session_unset();
    session_destroy();
    header("location:login.php");
    exit();
}

?>
