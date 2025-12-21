<?php

if (!function_exists("notify_user")) {
    function notify_user(
        $conn,
        $user_id,
        $subscription_id,
        $event,
        $payload = [],
    ) {
        if (empty($user_id) || empty($event)) {
            return;
        }

        $titles = [
            "PAYMENT_APPROVED" => "Payment approved",
            "PAYMENT_REJECTED" => "Payment rejected",
            "PLAN_CHANGED" => "Plan changed",
            "PLAN_CHANGE_REJECTED" => "Plan change rejected",
            "PAUSED" => "Subscription paused",
            "RESUMED" => "Subscription resumed",
            "EXPIRED" => "Subscription expired",
            "PICKUP_ASSIGNED" => "Pickup slot assigned",
            "PICKUP_REASSIGNED" => "Pickup slot updated",
            "PICKUP_CANCELLED" => "Pickup slot cancelled",
            "MEAL_PREPARED" => "Meal prepared",
            "MEAL_MISSED" => "Meal missed",
        ];

        $title = $titles[$event] ?? "Notification";
        $ref = isset($payload["ref"]) ? trim($payload["ref"]) : "";

        switch ($event) {
            case "PAYMENT_APPROVED":
                $message =
                    "Your payment was approved for subscription #" .
                    $subscription_id .
                    ".";
                break;
            case "PAYMENT_REJECTED":
                $message =
                    "Your payment was rejected for subscription #" .
                    $subscription_id .
                    ".";
                break;
            case "PLAN_CHANGED":
                $message =
                    "Your plan change was approved for subscription #" .
                    $subscription_id .
                    ".";
                break;
            case "PLAN_CHANGE_REJECTED":
                $message =
                    "Your plan change was rejected for subscription #" .
                    $subscription_id .
                    ".";
                break;
            case "PAUSED":
                $message =
                    "Your subscription #" . $subscription_id . " was paused.";
                break;
            case "RESUMED":
                $message =
                    "Your subscription #" . $subscription_id . " was resumed.";
                break;
            case "EXPIRED":
                $message =
                    "Your subscription #" . $subscription_id . " has expired.";
                break;
            case "PICKUP_ASSIGNED":
                $message = "Pickup slot assigned";
                if (!empty($payload["pickup_date"])) {
                    $message .= " for " . $payload["pickup_date"];
                }
                if (
                    !empty($payload["time_from"]) &&
                    !empty($payload["time_to"])
                ) {
                    $message .=
                        " (" .
                        $payload["time_from"] .
                        "-" .
                        $payload["time_to"] .
                        ")";
                }
                if (!empty($payload["location"])) {
                    $message .= " at " . $payload["location"];
                }
                $message .= ".";
                break;
            case "PICKUP_REASSIGNED":
                $message = "Pickup slot updated";
                if (!empty($payload["pickup_date"])) {
                    $message .= " to " . $payload["pickup_date"];
                }
                if (
                    !empty($payload["time_from"]) &&
                    !empty($payload["time_to"])
                ) {
                    $message .=
                        " (" .
                        $payload["time_from"] .
                        "-" .
                        $payload["time_to"] .
                        ")";
                }
                if (!empty($payload["location"])) {
                    $message .= " at " . $payload["location"];
                }
                $message .= ".";
                break;
            case "PICKUP_CANCELLED":
                $message = "Your pickup slot was cancelled.";
                break;
            case "MEAL_PREPARED":
                $message = "Your meal is ready for pickup.";
                break;
            case "MEAL_MISSED":
                $message = "You missed a scheduled meal pickup.";
                break;
            default:
                $message = "You have a new update.";
                break;
        }

        if ($ref !== "") {
            $message .= " Ref #" . $ref . ".";
        }

        $check = $conn->prepare(
            "SELECT id FROM `notifications`
         WHERE user_id = ?
           AND event = ?
           AND message = ?
           AND ((subscription_id IS NULL AND ? IS NULL) OR subscription_id = ?)
         LIMIT 1",
        );
        $check->execute([
            $user_id,
            $event,
            $message,
            $subscription_id,
            $subscription_id,
        ]);
        if ($check->rowCount() > 0) {
            return;
        }

        $insert = $conn->prepare(
            "INSERT INTO `notifications`
        (user_id, subscription_id, event, title, message, is_read, created_at)
        VALUES(?,?,?,?,?,0,NOW())",
        );
        $insert->execute([
            $user_id,
            $subscription_id,
            $event,
            $title,
            $message,
        ]);
    }
}

?>
