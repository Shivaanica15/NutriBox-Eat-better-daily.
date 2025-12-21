<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_guard.php';

session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    header('location:login.php');
    exit;
}

$can_send = table_exists($conn, 'notifications');
$pending_count = 0;
$message = [];

if ($can_send) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM `notifications` WHERE is_read = 0");
    $count_stmt->execute();
    $pending_count = (int) $count_stmt->fetchColumn();
} else {
    $message[] = 'notifications table missing.';
}

if (isset($_POST['send_notifications'])) {
    if (!$can_send) {
        $message[] = 'cannot send notifications without notifications table.';
    } else {
        if (SMTP_HOST !== '') {
            ini_set('SMTP', SMTP_HOST);
        }
        if (SMTP_PORT) {
            ini_set('smtp_port', SMTP_PORT);
        }
        if (FROM_EMAIL !== '') {
            ini_set('sendmail_from', FROM_EMAIL);
        }

        $select = $conn->prepare(
            "SELECT n.id, n.user_id, n.title, n.message, u.email, u.name
             FROM `notifications` AS n
             INNER JOIN `users` AS u ON n.user_id = u.id
             WHERE n.is_read = 0
             ORDER BY n.id ASC",
        );
        $select->execute();

        $update = $conn->prepare("UPDATE `notifications` SET is_read = 1 WHERE id = ?");

        $sent = 0;
        $failed = 0;

        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $to = trim($row['email']);
            if ($to === '') {
                $failed += 1;
                continue;
            }

            $subject = trim($row['title']) !== '' ? $row['title'] : 'Notification';
            $body = trim($row['message']) !== '' ? $row['message'] : 'You have a new notification.';

            $headers = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . ">\r\n";
            $headers .= 'Reply-To: ' . FROM_EMAIL . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            $success = mail($to, $subject, $body, $headers);
            if ($success) {
                $update->execute([$row['id']]);
                $sent += 1;
            } else {
                $failed += 1;
            }
        }

        $message[] = 'sent ' . $sent . ', failed ' . $failed . '.';
        $pending_count = max(0, $pending_count - $sent);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>send notifications</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">

</head>
<body class="admin-body">

<?php include 'admin_header.php'; ?>

<section class="placed-orders">

   <h1 class="title">send notifications</h1>

   <div class="box-container">

      <div class="box" style="width:100%;">
         <p>pending notifications : <span><?= $pending_count; ?></span></p>
         <form action="" method="POST">
            <input type="submit" name="send_notifications" class="btn" value="send email notifications" <?= $can_send && $pending_count > 0 ? '' : 'disabled'; ?>>
         </form>
      </div>

   </div>

</section>

<?php include "admin_footer.php"; ?>

</body>
</html>


