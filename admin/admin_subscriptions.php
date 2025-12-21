<?php

@include "../config.php";
require_once __DIR__ . "/../notifications_helper.php";

session_start();

$admin_id = $_SESSION["admin_id"];

if (!isset($admin_id)) {
    header("location:login.php");
}

function log_subscription_action($conn, $subscription_id, $action)
{
    $log = $conn->prepare(
        "INSERT INTO `subscription_logs` (subscription_id, action) VALUES (?, ?)",
    );
    $log->execute([$subscription_id, $action]);
    return $conn->lastInsertId();
}

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
            $log_id = log_subscription_action($conn, $row["id"], "EXPIRED");
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

function subscription_is_expired($subscription)
{
    if (!$subscription) {
        return false;
    }
    if ($subscription["status"] === "Expired") {
        return true;
    }
    if (!empty($subscription["end_date"])) {
        $today = new DateTime(date("Y-m-d"));
        $end_date = new DateTime($subscription["end_date"]);
        if ($end_date < $today) {
            return true;
        }
    }
    return false;
}

function status_badge_color($status)
{
    switch ($status) {
        case "Active":
            return "green";
        case "Paused":
            return "orange";
        case "ChangeRequested":
            return "purple";
        case "Pending":
            return "blue";
        case "Expired":
            return "gray";
        default:
            return "black";
    }
}

function approval_badge_color($status)
{
    switch ($status) {
        case "Approved":
            return "green";
        case "Rejected":
            return "red";
        case "Pending":
            return "orange";
        default:
            return "black";
    }
}

expire_all_subscriptions($conn);

if (isset($_POST["approve_payment"])) {
    // Approve payment only when approval_status is Pending.
    $subscription_id = $_POST["subscription_id"];
    $subscription_id = filter_var($subscription_id, FILTER_SANITIZE_NUMBER_INT);

    $select_subscription = $conn->prepare(
        "SELECT id, user_id, status, approval_status, end_date FROM `subscriptions` WHERE id = ?",
    );
    $select_subscription->execute([$subscription_id]);
    $subscription = $select_subscription->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        $message[] = "subscription not found!";
    } elseif (subscription_is_expired($subscription)) {
        $message[] = "cannot approve an expired subscription.";
    } elseif ($subscription["status"] !== "Pending") {
        $message[] =
            "payment approval is only allowed for pending subscriptions.";
    } elseif ($subscription["approval_status"] !== "Pending") {
        $message[] = "payment already processed.";
    } else {
        try {
            $conn->beginTransaction();
            $approve = $conn->prepare(
                "UPDATE `subscriptions` SET approval_status = 'Approved', status = 'Active' WHERE id = ?",
            );
            $approve->execute([$subscription_id]);
            $log_id = log_subscription_action(
                $conn,
                $subscription_id,
                "PAYMENT_APPROVED",
            );
            notify_user(
                $conn,
                $subscription["user_id"],
                $subscription_id,
                "PAYMENT_APPROVED",
                ["ref" => $log_id],
            );
            $conn->commit();
            $message[] = "payment approved successfully!";
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $message[] = "failed to approve payment.";
        }
    }
}

if (isset($_POST["reject_payment"])) {
    // Reject payment only when approval_status is Pending.
    $subscription_id = $_POST["subscription_id"];
    $subscription_id = filter_var($subscription_id, FILTER_SANITIZE_NUMBER_INT);

    $select_subscription = $conn->prepare(
        "SELECT id, user_id, status, approval_status, end_date FROM `subscriptions` WHERE id = ?",
    );
    $select_subscription->execute([$subscription_id]);
    $subscription = $select_subscription->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        $message[] = "subscription not found!";
    } elseif (subscription_is_expired($subscription)) {
        $message[] = "cannot reject an expired subscription.";
    } elseif ($subscription["status"] !== "Pending") {
        $message[] =
            "payment rejection is only allowed for pending subscriptions.";
    } elseif ($subscription["approval_status"] !== "Pending") {
        $message[] = "payment already processed.";
    } else {
        try {
            $conn->beginTransaction();
            $reject = $conn->prepare(
                "UPDATE `subscriptions` SET approval_status = 'Rejected' WHERE id = ?",
            );
            $reject->execute([$subscription_id]);
            $log_id = log_subscription_action(
                $conn,
                $subscription_id,
                "PAYMENT_REJECTED",
            );
            notify_user(
                $conn,
                $subscription["user_id"],
                $subscription_id,
                "PAYMENT_REJECTED",
                ["ref" => $log_id],
            );
            $conn->commit();
            $message[] = "payment rejected.";
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $message[] = "failed to reject payment.";
        }
    }
}

if (isset($_POST["approve_plan_change"])) {
    // Approve plan change only when status is ChangeRequested.
    $subscription_id = $_POST["subscription_id"];
    $subscription_id = filter_var($subscription_id, FILTER_SANITIZE_NUMBER_INT);
    $override_price = isset($_POST["override_price"])
        ? trim($_POST["override_price"])
        : "";
    $override_price =
        $override_price === ""
            ? null
            : filter_var(
                $override_price,
                FILTER_SANITIZE_NUMBER_FLOAT,
                FILTER_FLAG_ALLOW_FRACTION,
            );

    $select_subscription = $conn->prepare(
        "SELECT id, user_id, status, approval_status, change_requested_plan_id, end_date FROM `subscriptions` WHERE id = ?",
    );
    $select_subscription->execute([$subscription_id]);
    $subscription = $select_subscription->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        $message[] = "subscription not found!";
    } elseif (subscription_is_expired($subscription)) {
        $message[] = "cannot approve plan change for an expired subscription.";
    } elseif (
        $subscription["status"] !== "ChangeRequested" ||
        $subscription["approval_status"] !== "Pending"
    ) {
        $message[] = "no pending plan change found.";
    } elseif (empty($subscription["change_requested_plan_id"])) {
        $message[] = "requested plan is missing.";
    } else {
        if (
            $override_price !== null &&
            (!is_numeric($override_price) || (float) $override_price <= 0)
        ) {
            $message[] = "override price must be a positive number.";
        } else {
            $plan_select = $conn->prepare(
                "SELECT duration, price FROM `meal_plans` WHERE id = ?",
            );
            $plan_select->execute([$subscription["change_requested_plan_id"]]);
            $plan = $plan_select->fetch(PDO::FETCH_ASSOC);

            if (!$plan) {
                $message[] = "requested plan not found.";
            } else {
                $original_price = (int) $plan["price"];
                $final_price =
                    $override_price === null
                        ? $original_price
                        : (int) $override_price;
                try {
                    $conn->beginTransaction();
                    $approve_change = $conn->prepare(
                        "UPDATE `subscriptions`
                SET meal_plan_id = ?,
                    change_requested_plan_id = NULL,
                    status = 'Active',
                    approval_status = 'Approved',
                    end_date = DATE_ADD(CURDATE(), INTERVAL ? DAY),
                    original_price = ?,
                    overridden_price = ?
                WHERE id = ?",
                    );
                    $approve_change->execute([
                        $subscription["change_requested_plan_id"],
                        $plan["duration"],
                        $original_price,
                        $final_price,
                        $subscription_id,
                    ]);
                    if ($final_price !== $original_price) {
                        log_subscription_action(
                            $conn,
                            $subscription_id,
                            "PRICE_OVERRIDE: " .
                                $original_price .
                                " -> " .
                                $final_price,
                        );
                    }
                    $log_id = log_subscription_action(
                        $conn,
                        $subscription_id,
                        "PLAN_CHANGED",
                    );
                    notify_user(
                        $conn,
                        $subscription["user_id"],
                        $subscription_id,
                        "PLAN_CHANGED",
                        ["ref" => $log_id],
                    );
                    $conn->commit();
                    $message[] = "plan change approved.";
                } catch (Exception $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    $message[] = "failed to approve plan change.";
                }
            }
        }
    }
}

if (isset($_POST["reject_plan_change"])) {
    // Reject plan change only when status is ChangeRequested.
    $subscription_id = $_POST["subscription_id"];
    $subscription_id = filter_var($subscription_id, FILTER_SANITIZE_NUMBER_INT);

    $select_subscription = $conn->prepare(
        "SELECT id, user_id, status, approval_status, end_date FROM `subscriptions` WHERE id = ?",
    );
    $select_subscription->execute([$subscription_id]);
    $subscription = $select_subscription->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        $message[] = "subscription not found!";
    } elseif (subscription_is_expired($subscription)) {
        $message[] = "cannot reject plan change for an expired subscription.";
    } elseif (
        $subscription["status"] !== "ChangeRequested" ||
        $subscription["approval_status"] !== "Pending"
    ) {
        $message[] = "no pending plan change found.";
    } else {
        try {
            $conn->beginTransaction();
            $reject_change = $conn->prepare(
                "UPDATE `subscriptions`
             SET change_requested_plan_id = NULL,
                 status = 'Active',
                 approval_status = 'Approved'
             WHERE id = ?",
            );
            $reject_change->execute([$subscription_id]);
            $log_id = log_subscription_action(
                $conn,
                $subscription_id,
                "PLAN_CHANGE_REJECTED",
            );
            notify_user(
                $conn,
                $subscription["user_id"],
                $subscription_id,
                "PLAN_CHANGE_REJECTED",
                ["ref" => $log_id],
            );
            $conn->commit();
            $message[] = "plan change rejected.";
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $message[] = "failed to reject plan change.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>subscriptions</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/admin_style.css">

</head>
<body class="admin-body">

<?php include "admin_header.php"; ?>

<section class="placed-orders">

   <h1 class="title">subscriptions</h1>

   <div class="box-container">

      <?php
      $select_subscriptions = $conn->prepare(
          "SELECT s.*, u.name AS user_name, u.email AS user_email,
                    m.name AS meal_name, r.name AS requested_name
             FROM `subscriptions` AS s
             LEFT JOIN `users` AS u ON s.user_id = u.id
             LEFT JOIN `meal_plans` AS m ON s.meal_plan_id = m.id
             LEFT JOIN `meal_plans` AS r ON s.change_requested_plan_id = r.id
             ORDER BY s.id DESC",
      );
      $select_subscriptions->execute();
      if ($select_subscriptions->rowCount() > 0) {
          while (
              $subscription = $select_subscriptions->fetch(PDO::FETCH_ASSOC)
          ) {

              $status_color = status_badge_color($subscription["status"]);
              $approval_color = approval_badge_color(
                  $subscription["approval_status"],
              );
              ?>
      <div class="box">
         <p> user : <span><?= $subscription["user_name"] ?></span> </p>
         <p> email : <span><?= $subscription["user_email"] ?></span> </p>
         <p> meal plan : <span><?= $subscription["meal_name"] ?></span> </p>
         <p> status : <span style="color:<?= $status_color ?>; font-weight:700;"><?= $subscription[
    "status"
] ?></span> </p>
         <p> approval : <span style="color:<?= $approval_color ?>; font-weight:700;"><?= $subscription[
    "approval_status"
] ?></span> </p>
         <?php if (!empty($subscription["requested_name"])) { ?>
            <p> requested plan : <span><?= $subscription[
                "requested_name"
            ] ?></span> </p>
         <?php } ?>

         <div class="flex-btn" style="margin-top:1rem;">
            <?php if (
                $subscription["approval_status"] === "Pending" &&
                $subscription["status"] === "Pending"
            ) { ?>
               <form action="" method="POST" style="display:inline-block;">
                  <input type="hidden" name="subscription_id" value="<?= $subscription[
                      "id"
                  ] ?>">
                  <input type="submit" name="approve_payment" class="btn" value="approve payment">
               </form>
               <form action="" method="POST" style="display:inline-block;">
                  <input type="hidden" name="subscription_id" value="<?= $subscription[
                      "id"
                  ] ?>">
                  <input type="submit" name="reject_payment" class="delete-btn" value="reject payment">
               </form>
            <?php } ?>

            <?php if (
                $subscription["status"] === "ChangeRequested" &&
                $subscription["approval_status"] === "Pending"
            ) { ?>
               <form action="" method="POST" style="display:inline-block;">
                  <input type="hidden" name="subscription_id" value="<?= $subscription[
                      "id"
                  ] ?>">
                  <label for="override_price_<?= $subscription[
                      "id"
                  ] ?>">override price (optional)</label>
                  <input type="number" min="1" step="1" name="override_price" id="override_price_<?= $subscription[
                      "id"
                  ] ?>" class="box" placeholder="enter price">
                  <input type="submit" name="approve_plan_change" class="option-btn" value="approve plan change">
               </form>
               <form action="" method="POST" style="display:inline-block;">
                  <input type="hidden" name="subscription_id" value="<?= $subscription[
                      "id"
                  ] ?>">
                  <input type="submit" name="reject_plan_change" class="delete-btn" value="reject plan change">
               </form>
            <?php } ?>
         </div>

         <div class="details" style="margin-top:1rem;">
            <strong>logs</strong>
            <?php
            $select_logs = $conn->prepare(
                "SELECT * FROM `subscription_logs` WHERE subscription_id = ? ORDER BY id DESC",
            );
            $select_logs->execute([$subscription["id"]]);
            if ($select_logs->rowCount() > 0) {
                while ($log = $select_logs->fetch(PDO::FETCH_ASSOC)) {

                    $timestamp = "";
                    if (isset($log["created_at"]) && $log["created_at"]) {
                        $timestamp = " - " . $log["created_at"];
                    }
                    ?>
               <div><?= $log["action"] . $timestamp ?></div>
            <?php
                }
            } else {
                echo "<div>No logs yet.</div>";
            }
            ?>
         </div>
      </div>
      <?php
          }
      } else {
          echo '<p class="empty">no subscriptions found!</p>';
      }
      ?>

   </div>

</section>

<?php include "admin_footer.php"; ?>

</body>
</html>


