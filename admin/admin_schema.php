<?php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../db_guard.php";

session_start();

$admin_id = $_SESSION["admin_id"];

if (!isset($admin_id)) {
    header("location:login.php");
}

function column_exists($conn, $table_name, $column_name)
{
    try {
        $check = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?",
        );
        $check->execute([$table_name, $column_name]);
        $result = $check->fetch(PDO::FETCH_ASSOC);
        return isset($result["total"]) && (int) $result["total"] > 0;
    } catch (Exception $e) {
        return false;
    }
}

function index_exists($conn, $table_name, $index_name)
{
    try {
        $check = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?",
        );
        $check->execute([$table_name, $index_name]);
        $result = $check->fetch(PDO::FETCH_ASSOC);
        return isset($result["total"]) && (int) $result["total"] > 0;
    } catch (Exception $e) {
        return false;
    }
}

function ensure_schema_migrations_table($conn)
{
    if (table_exists($conn, "schema_migrations")) {
        return;
    }
    $conn->exec(
        "CREATE TABLE `schema_migrations` (
            `id` INT(100) NOT NULL AUTO_INCREMENT,
            `version` INT(10) NOT NULL,
            `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_version` (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    );
}

function get_applied_versions($conn)
{
    if (!table_exists($conn, "schema_migrations")) {
        return [];
    }
    $select = $conn->prepare("SELECT version FROM `schema_migrations`");
    $select->execute();
    $versions = [];
    while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
        $versions[(int) $row["version"]] = true;
    }
    return $versions;
}

function record_migration($conn, $version)
{
    $insert = $conn->prepare(
        "INSERT INTO `schema_migrations` (version) VALUES (?)",
    );
    $insert->execute([$version]);
}

function apply_migration($conn, $version)
{
    switch ($version) {
        case 1:
            ensure_schema_migrations_table($conn);
            return;
        case 2:
            if (!table_exists($conn, "subscription_logs")) {
                $conn->exec(
                    "CREATE TABLE `subscription_logs` (
                        `id` INT(100) NOT NULL AUTO_INCREMENT,
                        `subscription_id` INT(100) NOT NULL,
                        `action` VARCHAR(255) NOT NULL,
                        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                );
            }
            return;
        case 3:
            if (!column_exists($conn, "subscriptions", "approval_status")) {
                $conn->exec(
                    "ALTER TABLE `subscriptions` ADD COLUMN `approval_status` VARCHAR(20) NOT NULL DEFAULT 'Pending'",
                );
            }
            if (
                !column_exists(
                    $conn,
                    "subscriptions",
                    "change_requested_plan_id",
                )
            ) {
                $conn->exec(
                    "ALTER TABLE `subscriptions` ADD COLUMN `change_requested_plan_id` INT(100) NULL",
                );
            }
            if (!column_exists($conn, "subscriptions", "pause_start")) {
                $conn->exec(
                    "ALTER TABLE `subscriptions` ADD COLUMN `pause_start` DATE NULL",
                );
            }
            if (!column_exists($conn, "subscriptions", "pause_end")) {
                $conn->exec(
                    "ALTER TABLE `subscriptions` ADD COLUMN `pause_end` DATE NULL",
                );
            }
            if (!column_exists($conn, "subscriptions", "original_price")) {
                $conn->exec(
                    "ALTER TABLE `subscriptions` ADD COLUMN `original_price` INT(100) NULL",
                );
            }
            if (!column_exists($conn, "subscriptions", "overridden_price")) {
                $conn->exec(
                    "ALTER TABLE `subscriptions` ADD COLUMN `overridden_price` INT(100) NULL",
                );
            }
            return;
        case 4:
            if (!table_exists($conn, "notifications")) {
                $conn->exec(
                    "CREATE TABLE `notifications` (
                        `id` INT(100) NOT NULL AUTO_INCREMENT,
                        `user_id` INT(100) NOT NULL,
                        `subscription_id` INT(100) DEFAULT NULL,
                        `event` VARCHAR(50) NOT NULL,
                        `title` VARCHAR(150) NOT NULL,
                        `message` VARCHAR(1000) NOT NULL,
                        `is_read` TINYINT(1) NOT NULL DEFAULT 0,
                        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                );
            }
            return;
        case 5:
            if (!table_exists($conn, "subscription_pickup_slots")) {
                $conn->exec(
                    "CREATE TABLE `subscription_pickup_slots` (
                        `id` INT(100) NOT NULL AUTO_INCREMENT,
                        `subscription_id` INT(100) NOT NULL,
                        `pickup_date` DATE NOT NULL,
                        `time_from` TIME NOT NULL,
                        `time_to` TIME NOT NULL,
                        `location` VARCHAR(255) NOT NULL,
                        `status` VARCHAR(20) NOT NULL DEFAULT 'Assigned',
                        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                );
            }
            return;
        case 6:
            if (!table_exists($conn, "subscription_meals")) {
                $conn->exec(
                    "CREATE TABLE `subscription_meals` (
                        `id` INT(100) NOT NULL AUTO_INCREMENT,
                        `subscription_id` INT(100) NOT NULL,
                        `meal_date` DATE NOT NULL,
                        `status` VARCHAR(20) NOT NULL DEFAULT 'Pending',
                        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                );
            }
            return;
        case 7:
            if (!table_exists($conn, "pickup_slot_templates")) {
                $conn->exec(
                    "CREATE TABLE `pickup_slot_templates` (
                        `id` INT(100) NOT NULL AUTO_INCREMENT,
                        `slot_type` VARCHAR(20) NOT NULL,
                        `slot_date` DATE DEFAULT NULL,
                        `weekday` TINYINT(1) DEFAULT NULL,
                        `time_from` TIME NOT NULL,
                        `time_to` TIME NOT NULL,
                        `location` VARCHAR(255) NOT NULL,
                        `max_capacity` INT(10) NOT NULL,
                        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                );
            }
            return;
        case 8:
            if (!table_exists($conn, "pickup_slots")) {
                $conn->exec(
                    "CREATE TABLE `pickup_slots` (
                        `id` INT(100) NOT NULL AUTO_INCREMENT,
                        `template_id` INT(100) DEFAULT NULL,
                        `pickup_date` DATE NOT NULL,
                        `time_from` TIME NOT NULL,
                        `time_to` TIME NOT NULL,
                        `location` VARCHAR(255) NOT NULL,
                        `max_capacity` INT(10) NOT NULL,
                        `status` VARCHAR(20) NOT NULL DEFAULT 'Available',
                        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                );
            }
            return;
        case 9:
            if (
                !column_exists(
                    $conn,
                    "subscription_pickup_slots",
                    "pickup_slot_id",
                )
            ) {
                $conn->exec(
                    "ALTER TABLE `subscription_pickup_slots` ADD COLUMN `pickup_slot_id` INT(100) NULL",
                );
            }
            if (
                !index_exists(
                    $conn,
                    "subscription_pickup_slots",
                    "idx_pickup_slot_id",
                )
            ) {
                $conn->exec(
                    "CREATE INDEX `idx_pickup_slot_id` ON `subscription_pickup_slots` (`pickup_slot_id`)",
                );
            }
            return;
        case 10:
            if (!table_exists($conn, "user_profiles")) {
                $conn->exec(
                    "CREATE TABLE `user_profiles` (
                        `id` INT(100) NOT NULL AUTO_INCREMENT,
                        `user_id` INT(100) NOT NULL,
                        `goal` VARCHAR(50) NOT NULL,
                        `calorie_target` INT(100) DEFAULT NULL,
                        `allergies` TEXT DEFAULT NULL,
                        `notes` TEXT DEFAULT NULL,
                        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `uniq_user_id` (`user_id`),
                        CONSTRAINT `fk_user_profiles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                );
            }
            return;
    }
}

$migrations = [
    1 => "Create schema_migrations table",
    2 => "Create subscription_logs table",
    3 => "Add subscription workflow columns",
    4 => "Create notifications table",
    5 => "Create subscription_pickup_slots table",
    6 => "Create subscription_meals table",
    7 => "Create pickup_slot_templates table",
    8 => "Create pickup_slots table",
    9 => "Add pickup_slot_id to subscription_pickup_slots",
    10 => "Create user_profiles table",
];

$applied_versions = get_applied_versions($conn);
$migration_messages = [];

if (isset($_POST["apply_migrations"])) {
    try {
        ensure_schema_migrations_table($conn);
        $applied_versions = get_applied_versions($conn);
        foreach ($migrations as $version => $label) {
            if (!isset($applied_versions[$version])) {
                apply_migration($conn, $version);
                record_migration($conn, $version);
                $migration_messages[] = "applied migration {$version}: {$label}";
            }
        }
        $applied_versions = get_applied_versions($conn);
        if (!$migration_messages) {
            $migration_messages[] = "no pending migrations to apply.";
        }
    } catch (Exception $e) {
        $migration_messages[] = "migration failed: " . $e->getMessage();
    }
}

$active_db = "unknown";
try {
    $db_stmt = $conn->query("SELECT DATABASE() AS db");
    $active_db = $db_stmt->fetch(PDO::FETCH_ASSOC)["db"] ?? "unknown";
} catch (Exception $e) {
    $active_db = "unknown";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>schema installer</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">

</head>
<body class="admin-body">

<?php include "admin_header.php"; ?>

<section class="placed-orders">

   <h1 class="title">schema installer</h1>

   <div class="box-container">

      <div class="box" style="width:100%;">
         <p> active database : <span><?= $active_db ?></span> </p>
      </div>

      <?php if ($migration_messages) { ?>
      <div class="box" style="width:100%;">
         <?php foreach ($migration_messages as $msg) { ?>
            <div><?= $msg ?></div>
         <?php } ?>
      </div>
      <?php } ?>

      <div class="box" style="width:100%;">
         <h3>migrations</h3>
         <?php foreach ($migrations as $version => $label) {
             $status = isset($applied_versions[$version])
                 ? "applied"
                 : "pending"; ?>
            <p> v<?= $version ?> : <span><?= $label ?> (<?= $status ?>)</span> </p>
         <?php
         } ?>
         <form action="" method="POST" style="margin-top:1rem;">
            <input type="submit" name="apply_migrations" class="btn" value="apply pending migrations">
         </form>
      </div>

   </div>

</section>

<?php include "admin_footer.php"; ?>

</body>
</html>


