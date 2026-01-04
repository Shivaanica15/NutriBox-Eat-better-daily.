<?php

require_once __DIR__ . "/admin_auth.php";

function template_payload_from_post()
{
    $slot_type = isset($_POST["slot_type"]) ? trim($_POST["slot_type"]) : "";
    $slot_date = isset($_POST["slot_date"]) ? trim($_POST["slot_date"]) : "";
    $weekday = isset($_POST["weekday"]) ? trim($_POST["weekday"]) : "";
    $time_from = isset($_POST["time_from"]) ? trim($_POST["time_from"]) : "";
    $time_to = isset($_POST["time_to"]) ? trim($_POST["time_to"]) : "";
    $location = isset($_POST["location"]) ? trim($_POST["location"]) : "";
    $max_capacity = isset($_POST["max_capacity"])
        ? trim($_POST["max_capacity"])
        : "";
    $is_active = isset($_POST["is_active"]) ? 1 : 0;

    $weekday = $weekday === "" ? null : (int) $weekday;
    $slot_date = $slot_date === "" ? null : $slot_date;
    $max_capacity = $max_capacity === "" ? null : (int) $max_capacity;

    return [
        $slot_type,
        $slot_date,
        $weekday,
        $time_from,
        $time_to,
        $location,
        $max_capacity,
        $is_active,
    ];
}

function validate_template_payload($payload)
{
    [
        $slot_type,
        $slot_date,
        $weekday,
        $time_from,
        $time_to,
        $location,
        $max_capacity,
    ] = $payload;

    if (!in_array($slot_type, ["Date", "Weekday"], true)) {
        return "invalid slot type.";
    }
    if ($slot_type === "Date" && empty($slot_date)) {
        return "date is required for date-based slots.";
    }
    if (
        $slot_type === "Weekday" &&
        ($weekday === null || $weekday < 1 || $weekday > 7)
    ) {
        return "weekday is required for recurring slots.";
    }
    if (empty($time_from) || empty($time_to)) {
        return "time range is required.";
    }
    if (strtotime($time_from) === false || strtotime($time_to) === false) {
        return "invalid time format.";
    }
    if (strtotime($time_from) >= strtotime($time_to)) {
        return "time to must be later than time from.";
    }
    if (empty($location)) {
        return "location is required.";
    }
    if ($max_capacity === null || $max_capacity <= 0) {
        return "max capacity must be a positive number.";
    }

    return "";
}

function materialize_slot_from_template($conn, $template)
{
    if ($template["slot_type"] !== "Date" || empty($template["slot_date"])) {
        return;
    }

    $exists = $conn->prepare(
        "SELECT id FROM `pickup_slots` WHERE template_id = ? AND pickup_date = ? LIMIT 1",
    );
    $exists->execute([$template["id"], $template["slot_date"]]);
    if ($exists->rowCount() > 0) {
        return;
    }

    $insert = $conn->prepare(
        "INSERT INTO `pickup_slots`
         (template_id, pickup_date, time_from, time_to, location, max_capacity, status, created_at)
         VALUES(?,?,?,?,?,?, 'Available', NOW())",
    );
    $insert->execute([
        $template["id"],
        $template["slot_date"],
        $template["time_from"],
        $template["time_to"],
        $template["location"],
        $template["max_capacity"],
    ]);
}

$templates_available = table_exists($conn, "pickup_slot_templates");
if (!$templates_available) {
    $message[] = "pickup slot templates table missing.";
}

if (isset($_POST["create_template"])) {
    $payload = template_payload_from_post();
    $error = validate_template_payload($payload);

    if ($error !== "") {
        $message[] = $error;
    } else {
        try {
            $conn->beginTransaction();
            $insert = $conn->prepare(
                "INSERT INTO `pickup_slot_templates`
                 (slot_type, slot_date, weekday, time_from, time_to, location, max_capacity, is_active)
                 VALUES(?,?,?,?,?,?,?,?)",
            );
            $insert->execute($payload);
            $template_id = $conn->lastInsertId();
            $template = [
                "id" => $template_id,
                "slot_type" => $payload[0],
                "slot_date" => $payload[1],
                "weekday" => $payload[2],
                "time_from" => $payload[3],
                "time_to" => $payload[4],
                "location" => $payload[5],
                "max_capacity" => $payload[6],
            ];
            materialize_slot_from_template($conn, $template);
            $conn->commit();
            $message[] = "pickup slot template created.";
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $message[] = "failed to create template.";
        }
    }
}

if (isset($_POST["update_template"])) {
    $template_id = $_POST["template_id"];
    $template_id = filter_var($template_id, FILTER_SANITIZE_NUMBER_INT);
    $payload = template_payload_from_post();
    $error = validate_template_payload($payload);

    if (empty($template_id)) {
        $message[] = "template not found.";
    } elseif ($error !== "") {
        $message[] = $error;
    } else {
        try {
            $conn->beginTransaction();
            $update = $conn->prepare(
                "UPDATE `pickup_slot_templates`
                 SET slot_type = ?, slot_date = ?, weekday = ?, time_from = ?, time_to = ?, location = ?, max_capacity = ?, is_active = ?
                 WHERE id = ?",
            );
            $update->execute([
                $payload[0],
                $payload[1],
                $payload[2],
                $payload[3],
                $payload[4],
                $payload[5],
                $payload[6],
                $payload[7],
                $template_id,
            ]);
            $template = [
                "id" => $template_id,
                "slot_type" => $payload[0],
                "slot_date" => $payload[1],
                "weekday" => $payload[2],
                "time_from" => $payload[3],
                "time_to" => $payload[4],
                "location" => $payload[5],
                "max_capacity" => $payload[6],
            ];
            materialize_slot_from_template($conn, $template);
            $conn->commit();
            $message[] = "pickup slot template updated.";
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $message[] = "failed to update template.";
        }
    }
}

if (isset($_POST["delete_template"])) {
    $template_id = $_POST["template_id"];
    $template_id = filter_var($template_id, FILTER_SANITIZE_NUMBER_INT);

    if (empty($template_id)) {
        $message[] = "template not found.";
    } else {
        $slot_check = $conn->prepare(
            "SELECT id FROM `pickup_slots` WHERE template_id = ? LIMIT 1",
        );
        $slot_check->execute([$template_id]);
        if ($slot_check->rowCount() > 0) {
            $message[] = "template has generated slots and cannot be deleted.";
        } else {
            try {
                $conn->beginTransaction();
                $delete = $conn->prepare(
                    "DELETE FROM `pickup_slot_templates` WHERE id = ?",
                );
                $delete->execute([$template_id]);
                $conn->commit();
                $message[] = "pickup slot template deleted.";
            } catch (Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $message[] = "failed to delete template.";
            }
        }
    }
}

if ($templates_available) {
    $templates = $conn->prepare(
        "SELECT * FROM `pickup_slot_templates` ORDER BY id DESC",
    );
    $templates->execute();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>pickup slot templates</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">

</head>
<body class="admin-body">

<?php include "admin_header.php"; ?>

<section class="placed-orders">

   <h1 class="title">pickup slot templates</h1>

   <div class="box-container">

      <div class="box">
         <h3>create template</h3>
         <form action="" method="POST">
            <label for="slot_type">slot type</label>
            <select name="slot_type" id="slot_type" class="box" required>
               <option value="" selected disabled>select type</option>
               <option value="Date">Date</option>
               <option value="Weekday">Weekday</option>
            </select>
            <label for="slot_date">date (for date-based)</label>
            <input type="date" name="slot_date" id="slot_date" class="box">
            <label for="weekday">weekday (for recurring)</label>
            <select name="weekday" id="weekday" class="box">
               <option value="" selected disabled>select weekday</option>
               <option value="1">Monday</option>
               <option value="2">Tuesday</option>
               <option value="3">Wednesday</option>
               <option value="4">Thursday</option>
               <option value="5">Friday</option>
               <option value="6">Saturday</option>
               <option value="7">Sunday</option>
            </select>
            <label for="time_from">time from</label>
            <input type="time" name="time_from" id="time_from" class="box" required>
            <label for="time_to">time to</label>
            <input type="time" name="time_to" id="time_to" class="box" required>
            <label for="location">location</label>
            <input type="text" name="location" id="location" class="box" required>
            <label for="max_capacity">max capacity</label>
            <input type="number" min="1" name="max_capacity" id="max_capacity" class="box" required>
            <label for="is_active">active</label>
            <input type="checkbox" name="is_active" id="is_active" checked>
            <input type="submit" name="create_template" class="btn" value="create template">
         </form>
      </div>

      <?php if ($templates_available && $templates->rowCount() > 0) {
          while ($template = $templates->fetch(PDO::FETCH_ASSOC)) {

              $type_label = $template["slot_type"];
              $schedule =
                  $type_label === "Date"
                      ? $template["slot_date"]
                      : "Weekday " . $template["weekday"];
              ?>
      <div class="box">
         <p> type : <span><?= $type_label ?></span> </p>
         <p> schedule : <span><?= $schedule ?></span> </p>
         <p> time : <span><?= $template["time_from"] ?> - <?= $template[
     "time_to"
 ] ?></span> </p>
         <p> location : <span><?= $template["location"] ?></span> </p>
         <p> max capacity : <span><?= $template["max_capacity"] ?></span> </p>
         <p> active : <span><?= $template["is_active"]
             ? "yes"
             : "no" ?></span> </p>

         <form action="" method="POST" style="margin-top:1rem;">
            <input type="hidden" name="template_id" value="<?= $template[
                "id"
            ] ?>">
            <label>slot type</label>
            <select name="slot_type" class="box" required>
               <option value="Date" <?= $template["slot_type"] === "Date"
                   ? "selected"
                   : "" ?>>Date</option>
               <option value="Weekday" <?= $template["slot_type"] === "Weekday"
                   ? "selected"
                   : "" ?>>Weekday</option>
            </select>
            <label>date (for date-based)</label>
            <input type="date" name="slot_date" class="box" value="<?= $template[
                "slot_date"
            ] ?>">
            <label>weekday (for recurring)</label>
            <select name="weekday" class="box">
               <option value="" disabled>select weekday</option>
               <option value="1" <?= (int) $template["weekday"] === 1
                   ? "selected"
                   : "" ?>>Monday</option>
               <option value="2" <?= (int) $template["weekday"] === 2
                   ? "selected"
                   : "" ?>>Tuesday</option>
               <option value="3" <?= (int) $template["weekday"] === 3
                   ? "selected"
                   : "" ?>>Wednesday</option>
               <option value="4" <?= (int) $template["weekday"] === 4
                   ? "selected"
                   : "" ?>>Thursday</option>
               <option value="5" <?= (int) $template["weekday"] === 5
                   ? "selected"
                   : "" ?>>Friday</option>
               <option value="6" <?= (int) $template["weekday"] === 6
                   ? "selected"
                   : "" ?>>Saturday</option>
               <option value="7" <?= (int) $template["weekday"] === 7
                   ? "selected"
                   : "" ?>>Sunday</option>
            </select>
            <label>time from</label>
            <input type="time" name="time_from" class="box" value="<?= $template[
                "time_from"
            ] ?>" required>
            <label>time to</label>
            <input type="time" name="time_to" class="box" value="<?= $template[
                "time_to"
            ] ?>" required>
            <label>location</label>
            <input type="text" name="location" class="box" value="<?= $template[
                "location"
            ] ?>" required>
            <label>max capacity</label>
            <input type="number" min="1" name="max_capacity" class="box" value="<?= $template[
                "max_capacity"
            ] ?>" required>
            <label>active</label>
            <input type="checkbox" name="is_active" <?= $template["is_active"]
                ? "checked"
                : "" ?>>
            <div class="flex-btn" style="margin-top:1rem;">
               <input type="submit" name="update_template" class="btn" value="update">
               <input type="submit" name="delete_template" class="delete-btn" value="delete">
            </div>
         </form>
      </div>
      <?php
          }
      } else {
          echo '<p class="empty">no templates found!</p>';
      } ?>

   </div>

</section>

<?php include "admin_footer.php"; ?>

</body>
</html>
