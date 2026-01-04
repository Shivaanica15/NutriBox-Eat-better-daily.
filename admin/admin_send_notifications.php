<?php

require_once __DIR__ . "/admin_auth.php";
require_once __DIR__ . "/../mailer.php";

$can_send = table_exists($conn, "notifications");
$pending_count = 0;
$message = [];
$smtp_error = "";

function safe_message($text)
{
    $text = preg_replace("/[\x00-\x1F\x7F]/", "", (string) $text);
    return trim($text);
}

function smtp_status_issues()
{
    $issues = [];
    if (SMTP_HOST === "") {
        $issues[] = "SMTP_HOST";
    }
    if (SMTP_PORT <= 0) {
        $issues[] = "SMTP_PORT";
    }
    if (SMTP_USERNAME === "") {
        $issues[] = "SMTP_USERNAME";
    }
    if (SMTP_PASSWORD === "") {
        $issues[] = "SMTP_PASSWORD";
    }
    if (SMTP_FROM_EMAIL === "") {
        $issues[] = "SMTP_FROM_EMAIL";
    }
    if (SMTP_FROM_NAME === "") {
        $issues[] = "SMTP_FROM_NAME";
    }
    if (
        SMTP_ENCRYPTION !== "" &&
        SMTP_ENCRYPTION !== "tls" &&
        SMTP_ENCRYPTION !== "ssl"
    ) {
        $issues[] = "SMTP_ENCRYPTION";
    }
    return $issues;
}

if ($can_send) {
    $count_stmt = $conn->prepare(
        "SELECT COUNT(*) FROM `notifications` WHERE is_read = 0",
    );
    $count_stmt->execute();
    $pending_count = (int) $count_stmt->fetchColumn();
} else {
    $message[] = safe_message("notifications table missing.");
}

$smtp_error = smtp_config_error();
$smtp_ready = $smtp_error === "";
if ($smtp_error !== "") {
    $message[] = safe_message("SMTP config error: " . $smtp_error);
}

$smtp_issues = smtp_status_issues();
$smtp_ready = smtp_config_valid();

if (isset($_POST["send_notifications"])) {
    if (!$can_send) {
        $message[] = safe_message(
            "cannot send notifications without notifications table.",
        );
    } elseif (!$smtp_ready) {
        $message[] = safe_message("fix SMTP settings before sending.");
    } elseif ($pending_count <= 0) {
        $message[] = safe_message("no unread notifications to send.");
    } else {
        $select = $conn->prepare(
            "SELECT n.id, n.user_id, n.title, n.message, u.email, u.name
             FROM `notifications` AS n
             INNER JOIN `users` AS u ON n.user_id = u.id
             WHERE n.is_read = 0
             ORDER BY n.id ASC",
        );
        $select->execute();

        $update = $conn->prepare(
            "UPDATE `notifications` SET is_read = 1 WHERE id = ?",
        );

        $sent = 0;
        $failed = 0;
        $last_error = "";

        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $to = trim($row["email"]);
            if ($to === "" || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $failed += 1;
                continue;
            }

            $subject =
                trim($row["title"]) !== "" ? $row["title"] : "Notification";
            $body =
                trim($row["message"]) !== ""
                    ? $row["message"]
                    : "You have a new notification.";

            [$success, $error] = send_notification_email($to, $subject, $body);
            if ($success) {
                $update->execute([$row["id"]]);
                $sent += 1;
            } else {
                $failed += 1;
                if ($error !== "") {
                    $last_error = safe_message($error);
                }
            }
        }

        $summary = "sent " . $sent . ", failed " . $failed . ".";
        if ($last_error !== "") {
            $summary .= " last error: " . $last_error;
        }
        $message[] = safe_message($summary);
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

<?php include "admin_header.php"; ?>

<section class="placed-orders">

   <h1 class="title">send notifications</h1>

   <div class="box-container">

      <div class="box" style="width:100%;">
         <?php if ($smtp_ready) { ?>
            <p>SMTP status : <span style="color:green;font-weight:700;">configured and ready</span></p>
         <?php } else { ?>
            <p>SMTP status : <span style="color:red;font-weight:700;">not configured</span></p>
            <?php if ($smtp_issues) { ?>
               <p>missing or invalid : <span><?= implode(
                   ", ",
                   $smtp_issues,
               ) ?></span></p>
            <?php } ?>
         <?php } ?>
      </div>

      <div class="box" style="width:100%;">
         <p>pending notifications : <span><?= $pending_count ?></span></p>
         <form action="" method="POST">
            <input type="submit" name="send_notifications" class="btn" value="send email notifications" <?= $can_send &&
            $smtp_ready &&
            $pending_count > 0
                ? ""
                : "disabled" ?>>
         </form>
         <?php if ($can_send && $pending_count <= 0) { ?>
            <p class="empty" style="margin-top:1rem;">no pending notifications to send.</p>
         <?php } ?>
      </div>

   </div>

</section>

<?php include "admin_footer.php"; ?>

</body>
</html>
