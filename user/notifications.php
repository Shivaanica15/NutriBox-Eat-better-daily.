<?php

@include "../config.php";
require_once __DIR__ . "/../notifications_helper.php";

session_start();

$user_id = $_SESSION["user_id"];

if (!isset($user_id)) {
    header("location:login.php");
}

if (isset($_POST["mark_read"])) {
    $notification_id = $_POST["notification_id"];
    $notification_id = filter_var($notification_id, FILTER_SANITIZE_NUMBER_INT);

    if (empty($notification_id)) {
        $message[] = "notification not found.";
    } else {
        try {
            $conn->beginTransaction();
            $update = $conn->prepare(
                "UPDATE `notifications` SET is_read = 1 WHERE id = ? AND user_id = ? AND is_read = 0",
            );
            $update->execute([$notification_id, $user_id]);
            $conn->commit();
            $message[] = "notification marked as read.";
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $message[] = "failed to update notification.";
        }
    }
}

$notifications = $conn->prepare(
    "SELECT * FROM `notifications` WHERE user_id = ? ORDER BY created_at DESC, id DESC",
);
$notifications->execute([$user_id]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>notifications</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/user_style.css">

</head>
<body>

<?php include "user_header.php"; ?>

<section class="placed-orders">

   <h1 class="title">notifications</h1>

   <div class="box-container">

      <?php if ($notifications->rowCount() > 0) {
          while ($note = $notifications->fetch(PDO::FETCH_ASSOC)) { ?>
      <div class="box">
         <p> title : <span><?= $note["title"] ?></span> </p>
         <p> message : <span><?= $note["message"] ?></span> </p>
         <p> event : <span><?= $note["event"] ?></span> </p>
         <p> status : <span><?= $note["is_read"]
             ? "read"
             : "unread" ?></span> </p>
         <p> time : <span><?= $note["created_at"] ?></span> </p>
         <?php if ((int) $note["is_read"] === 0) { ?>
            <form action="" method="POST" style="margin-top:1rem;">
               <input type="hidden" name="notification_id" value="<?= $note[
                   "id"
               ] ?>">
               <input type="submit" name="mark_read" class="btn" value="mark as read">
            </form>
         <?php } ?>
      </div>
      <?php }
      } else {
          echo '<p class="empty">no notifications yet!</p>';
      } ?>

   </div>

</section>

<?php include "user_footer.php"; ?>

<script src="js/user.js"></script>

</body>
</html>

