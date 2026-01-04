<?php

require_once __DIR__ . "/admin_auth.php"; ?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>admin page</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/admin_style.css">

</head>
<body class="admin-body">

<?php include "admin_header.php"; ?>

<section class="dashboard">

   <h1 class="title">dashboard</h1>

   <div class="box-container">

      <div class="box">
      <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
      <?php
      $total_revenue = 0;
      $select_revenue = $conn->prepare("SELECT * FROM `subscription_orders`");
      $select_revenue->execute();
      while ($fetch_revenue = $select_revenue->fetch(PDO::FETCH_ASSOC)) {
          $total_revenue += $fetch_revenue["total_price"];
      }
      ?>
      <h3 class="stat-value" data-count="<?= $total_revenue ?>" data-prefix="$" data-suffix="/-">$<?= $total_revenue ?>/-</h3>
      <p class="stat-label">total revenue</p>
      <a href="admin_orders.php" class="btn">see orders</a>
      </div>

      <div class="box">
      <div class="stat-icon"><i class="fas fa-bolt"></i></div>
      <?php
      $select_active = $conn->prepare(
          "SELECT * FROM `subscriptions` WHERE status = ?",
      );
      $select_active->execute(["Active"]);
      $number_of_active = $select_active->rowCount();
      ?>
      <h3 class="stat-value" data-count="<?= $number_of_active ?>"><?= $number_of_active ?></h3>
      <p class="stat-label">active subscriptions</p>
      <a href="admin_orders.php" class="btn">see orders</a>
      </div>

      <div class="box">
      <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
      <?php
      $select_pending = $conn->prepare(
          "SELECT * FROM `subscriptions` WHERE status = ?",
      );
      $select_pending->execute(["Pending"]);
      $number_of_pending = $select_pending->rowCount();
      ?>
      <h3 class="stat-value" data-count="<?= $number_of_pending ?>"><?= $number_of_pending ?></h3>
      <p class="stat-label">pending subscriptions</p>
      <a href="admin_orders.php" class="btn">see orders</a>
      </div>

      <div class="box">
      <div class="stat-icon"><i class="fas fa-utensils"></i></div>
      <?php
      $select_plans = $conn->prepare("SELECT * FROM `meal_plans`");
      $select_plans->execute();
      $number_of_plans = $select_plans->rowCount();
      ?>
      <h3 class="stat-value" data-count="<?= $number_of_plans ?>"><?= $number_of_plans ?></h3>
      <p class="stat-label">meal plans added</p>
      <a href="admin_products.php" class="btn">see meal plans</a>
      </div>

      <div class="box">
      <div class="stat-icon"><i class="fas fa-users"></i></div>
      <?php
      $select_users = $conn->prepare(
          "SELECT * FROM `users` WHERE user_type = ?",
      );
      $select_users->execute(["user"]);
      $number_of_users = $select_users->rowCount();
      ?>
      <h3 class="stat-value" data-count="<?= $number_of_users ?>"><?= $number_of_users ?></h3>
      <p class="stat-label">total users</p>
      <a href="admin_users.php" class="btn">see accounts</a>
      </div>

      <div class="box">
      <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
      <?php
      $select_admins = $conn->prepare(
          "SELECT * FROM `users` WHERE user_type = ?",
      );
      $select_admins->execute(["admin"]);
      $number_of_admins = $select_admins->rowCount();
      ?>
      <h3 class="stat-value" data-count="<?= $number_of_admins ?>"><?= $number_of_admins ?></h3>
      <p class="stat-label">total admins</p>
      <a href="admin_users.php" class="btn">see accounts</a>
      </div>

      <div class="box">
      <div class="stat-icon"><i class="fas fa-id-badge"></i></div>
      <?php
      $select_accounts = $conn->prepare("SELECT * FROM `users`");
      $select_accounts->execute();
      $number_of_accounts = $select_accounts->rowCount();
      ?>
      <h3 class="stat-value" data-count="<?= $number_of_accounts ?>"><?= $number_of_accounts ?></h3>
      <p class="stat-label">total accounts</p>
      <a href="admin_users.php" class="btn">see accounts</a>
      </div>

      <div class="box">
      <div class="stat-icon"><i class="fas fa-envelope"></i></div>
      <?php
      $select_messages = $conn->prepare("SELECT * FROM `messages`");
      $select_messages->execute();
      $number_of_messages = $select_messages->rowCount();
      ?>
      <h3 class="stat-value" data-count="<?= $number_of_messages ?>"><?= $number_of_messages ?></h3>
      <p class="stat-label">total messages</p>
      <a href="admin_contacts.php" class="btn">see messages</a>
      </div>

      <div class="box">
      <div class="stat-icon"><i class="fas fa-bell"></i></div>
      <?php
      $number_of_notifications = 0;
      if (table_exists($conn, "notifications")) {
          $select_notifications = $conn->prepare(
              "SELECT COUNT(*) FROM `notifications` WHERE is_read = 0",
          );
          $select_notifications->execute();
          $number_of_notifications = (int) $select_notifications->fetchColumn();
      }
      ?>
      <h3 class="stat-value" data-count="<?= $number_of_notifications ?>"><?= $number_of_notifications ?></h3>
      <p class="stat-label">pending notifications</p>
      <a href="admin_send_notifications.php" class="btn">send notifications</a>
      </div>

   </div>

</section>

<script>
   document.querySelectorAll(".stat-value").forEach((valueEl) => {
      const raw = valueEl.getAttribute("data-count");
      if (!raw) {
         return;
      }

      const prefix = valueEl.getAttribute("data-prefix") || "";
      const suffix = valueEl.getAttribute("data-suffix") || "";
      const target = Number(raw);
      if (!Number.isFinite(target)) {
         return;
      }

      const hasDecimal = raw.includes(".");
      const duration = 900;
      const start = performance.now();

      const formatValue = (val) => {
         if (hasDecimal) {
            return val.toFixed(2);
         }
         return Math.round(val).toLocaleString();
      };

      const tick = (now) => {
         const progress = Math.min((now - start) / duration, 1);
         const eased = 1 - Math.pow(1 - progress, 3);
         const current = target * eased;
         valueEl.textContent = `${prefix}${formatValue(current)}${suffix}`;
         if (progress < 1) {
            requestAnimationFrame(tick);
         }
      };

   requestAnimationFrame(tick);
});
</script>

<?php include "admin_footer.php"; ?>

</body>
</html>
