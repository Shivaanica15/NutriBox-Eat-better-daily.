<?php
if (isset($_GET["admin"])) {
    header("Location: admin/admin_page.php");
} else {
    header("Location: user/home.php");
}
exit();
