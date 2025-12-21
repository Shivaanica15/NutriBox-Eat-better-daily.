<?php

require_once __DIR__ . "/../notifications_helper.php";
@include "../db_guard.php";

if (isset($conn, $user_id) && $user_id) {
    $missing_tables = [];
    if (!table_exists($conn, "notifications")) {
        $missing_tables[] = "notifications";
    }
    if (!table_exists($conn, "subscription_meals")) {
        $missing_tables[] = "subscription_meals";
    }
    if (!table_exists($conn, "subscription_pickup_slots")) {
        $missing_tables[] = "subscription_pickup_slots";
    }
    db_guard_warn_missing($missing_tables);

    if (!function_exists("log_subscription_action")) {
        function log_subscription_action($conn, $subscription_id, $action)
        {
            $log = $conn->prepare(
                "INSERT INTO `subscription_logs` (subscription_id, action) VALUES (?, ?)",
            );
            $log->execute([$subscription_id, $action]);
            return $conn->lastInsertId();
        }
    }

    if (!function_exists("expire_user_subscriptions")) {
        function expire_user_subscriptions($conn, $user_id)
        {
            $select = $conn->prepare(
                "SELECT id, user_id, end_date FROM `subscriptions` WHERE user_id = ? AND end_date IS NOT NULL AND end_date < CURDATE() AND status != 'Expired'",
            );
            $select->execute([$user_id]);
            $expired = $select->fetchAll(PDO::FETCH_ASSOC);
            if (!$expired) {
                return;
            }

            foreach ($expired as $row) {
                try {
                    $conn->beginTransaction();
                    $update = $conn->prepare(
                        "UPDATE `subscriptions` SET status = 'Expired' WHERE id = ? AND user_id = ?",
                    );
                    $update->execute([$row["id"], $user_id]);
                    $log_id = log_subscription_action(
                        $conn,
                        $row["id"],
                        "EXPIRED",
                    );
                    notify_user($conn, $row["user_id"], $row["id"], "EXPIRED", [
                        "ref" => $log_id,
                    ]);
                    $conn->commit();
                } catch (Exception $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                }
            }
        }
    }

    expire_user_subscriptions($conn, $user_id);
}

if (isset($message)) {
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
}
?>

<header class="header">

   <div class="flex">

      <a href="home.php" class="logo">NutriBox<span>.</span></a>

      <nav class="navbar">
         <a href="home.php">Home</a>
         <a href="about.php">About</a>
         <a href="contact.php">Contact</a>
         <a href="shop.php">Meal Plans</a>
         <a href="orders.php">Current Subscription</a>
         <a href="pickup_slots.php">Pickup Slots</a>
         <a href="daily_meals.php">Daily Meals</a>


      </nav>

      <div class="header-actions">
         <div id="menu-btn" class="fas fa-bars"></div>
         <div id="user-btn" class="fas fa-user"></div>
         <a href="search_page.php" class="fas fa-search"></a>
         <?php
         $count_wishlist_items = $conn->prepare(
             "SELECT COUNT(*) AS total FROM `wishlist` WHERE user_id = ?",
         );
         $count_wishlist_items->execute([$user_id]);
         $wishlist_total = (int) $count_wishlist_items->fetch(PDO::FETCH_ASSOC)[
             "total"
         ];
         $count_subscriptions = $conn->prepare(
             "SELECT COUNT(*) AS total FROM `subscriptions` WHERE user_id = ? AND status IN ('Active','Pending')",
         );
         $count_subscriptions->execute([$user_id]);
         $subscription_total = (int) $count_subscriptions->fetch(
             PDO::FETCH_ASSOC,
         )["total"];
         $unread_notifications = 0;
         if (table_exists($conn, "notifications")) {
             $count_notifications = $conn->prepare(
                 "SELECT COUNT(*) AS total FROM `notifications` WHERE user_id = ? AND is_read = 0",
             );
             $count_notifications->execute([$user_id]);
             $unread_notifications = (int) $count_notifications->fetch(
                 PDO::FETCH_ASSOC,
             )["total"];
         }
         ?>
         <a href="wishlist.php"><i class="fas fa-heart"></i><span>(<?= $wishlist_total ?>)</span></a>
         <a href="orders.php"><i class="fas fa-clipboard-list"></i><span>(<?= $subscription_total ?>)</span></a>
         <a href="notifications.php"><i class="fas fa-bell"></i><span>(<?= $unread_notifications ?>)</span></a>
         <?php if (isset($user_id)) { ?>
         <a href="logout.php" class="logout-link"><i class="fas fa-right-from-bracket"></i><span>Logout</span></a>
         <?php } ?>
      </div>

      <div class="profile">
         <?php
         $select_profile = $conn->prepare("SELECT * FROM `users` WHERE id = ?");
         $select_profile->execute([$user_id]);
         $fetch_profile = $select_profile->fetch(PDO::FETCH_ASSOC);
         ?>
         <img src="../uploaded_img/<?= $fetch_profile["image"] ?>" alt="">
         <p><?= $fetch_profile["name"] ?></p>
         <a href="user_profile_update.php" class="btn" style="display:block;width:100%;text-align:center;">update profile</a>
      </div>

   </div>

</header>
