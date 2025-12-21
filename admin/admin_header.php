<?php

require_once __DIR__ . "/../notifications_helper.php";
require_once __DIR__ . "/../db_guard.php";

if (isset($conn)) {
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

    if (!function_exists("expire_all_subscriptions")) {
        function expire_all_subscriptions($conn)
        {
            $select = $conn->prepare(
                "SELECT id, user_id, end_date FROM `subscriptions` WHERE end_date IS NOT NULL AND end_date < CURDATE() AND status != 'Expired'",
            );
            $select->execute();
            $expired = $select->fetchAll(PDO::FETCH_ASSOC);
            if (!$expired) {
                return;
            }

            foreach ($expired as $row) {
                try {
                    $conn->beginTransaction();
                    $update = $conn->prepare(
                        "UPDATE `subscriptions` SET status = 'Expired' WHERE id = ?",
                    );
                    $update->execute([$row["id"]]);
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

    expire_all_subscriptions($conn);
}
?>

<div class="admin-shell">
   <?php include "admin_sidebar.php"; ?>

   <main class="admin-main">
      <div class="admin-topbar">
         <button class="icon-btn" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
         </button>
         <div class="topbar-title">NutriBox Admin</div>
      </div>

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
      }
?>

