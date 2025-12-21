<?php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../db_guard.php";

session_start();

$admin_id = $_SESSION["admin_id"];

if (!isset($admin_id)) {
    header("location:login.php");
}

$allowed_goals = ["Weight Loss", "Muscle Gain", "Diabetic", "Custom"];

if (isset($_POST["save_profile"])) {
    $user_id = filter_var($_POST["user_id"] ?? null, FILTER_VALIDATE_INT);
    $goal = isset($_POST["goal"]) ? trim($_POST["goal"]) : "";
    $calorie_target_input = isset($_POST["calorie_target"])
        ? trim($_POST["calorie_target"])
        : "";
    $allergies = isset($_POST["allergies"]) ? trim($_POST["allergies"]) : "";
    $notes = isset($_POST["notes"]) ? trim($_POST["notes"]) : "";

    if (!$user_id) {
        $message[] = "invalid user selected.";
    } elseif ($goal === "" || !in_array($goal, $allowed_goals, true)) {
        $message[] = "goal is required and must be valid.";
    } else {
        $calorie_target = null;
        if ($calorie_target_input !== "") {
            $calorie_target = filter_var(
                $calorie_target_input,
                FILTER_VALIDATE_INT,
            );
            if ($calorie_target === false || $calorie_target <= 0) {
                $message[] = "calorie target must be a positive number.";
            }
        }
    }

    if (!isset($message)) {
        if (!table_exists($conn, "user_profiles")) {
            $message[] =
                "user profiles table is missing. Run the schema installer first.";
        } else {
            $allergies = $allergies === "" ? null : $allergies;
            $notes = $notes === "" ? null : $notes;

            $check_user = $conn->prepare("SELECT id FROM `users` WHERE id = ?");
            $check_user->execute([$user_id]);
            $user_exists = $check_user->fetch(PDO::FETCH_ASSOC);

            if (!$user_exists) {
                $message[] = "user not found.";
            } else {
                try {
                    $conn->beginTransaction();
                    $profile_check = $conn->prepare(
                        "SELECT id FROM `user_profiles` WHERE user_id = ?",
                    );
                    $profile_check->execute([$user_id]);
                    $profile = $profile_check->fetch(PDO::FETCH_ASSOC);

                    if ($profile) {
                        $update = $conn->prepare(
                            "UPDATE `user_profiles`
                         SET goal = ?, calorie_target = ?, allergies = ?, notes = ?
                         WHERE user_id = ?",
                        );
                        $update->execute([
                            $goal,
                            $calorie_target,
                            $allergies,
                            $notes,
                            $user_id,
                        ]);
                        $message[] = "profile updated successfully.";
                    } else {
                        $insert = $conn->prepare(
                            "INSERT INTO `user_profiles`
                         (user_id, goal, calorie_target, allergies, notes)
                         VALUES (?, ?, ?, ?, ?)",
                        );
                        $insert->execute([
                            $user_id,
                            $goal,
                            $calorie_target,
                            $allergies,
                            $notes,
                        ]);
                        $message[] = "profile created successfully.";
                    }

                    $conn->commit();
                } catch (Exception $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    $message[] = "failed to save profile.";
                }
            }
        }
    }
}

$profiles_available = table_exists($conn, "user_profiles");
if ($profiles_available) {
    $select_users = $conn->prepare(
        "SELECT u.id, u.name, u.email, u.image,
                p.id AS profile_id, p.goal, p.calorie_target, p.allergies, p.notes, p.updated_at
         FROM `users` AS u
         LEFT JOIN `user_profiles` AS p ON p.user_id = u.id
         ORDER BY u.id DESC",
    );
} else {
    $select_users = $conn->prepare(
        "SELECT u.id, u.name, u.email, u.image,
                NULL AS profile_id, NULL AS goal, NULL AS calorie_target, NULL AS allergies, NULL AS notes, NULL AS updated_at
         FROM `users` AS u
         ORDER BY u.id DESC",
    );
}
$select_users->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>user profiles</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/admin_style.css">

</head>
<body class="admin-body">

<?php include "admin_header.php"; ?>

<section class="user-accounts">

   <h1 class="title">user health profiles</h1>

   <div class="box-container">

      <?php if ($select_users->rowCount() > 0) {
          while ($user = $select_users->fetch(PDO::FETCH_ASSOC)) {
              $has_profile = !empty($user["profile_id"]); ?>
      <div class="box">
         <img src="../uploaded_img/<?= $user["image"] ?>" alt="">
         <p> user id : <span><?= $user["id"] ?></span></p>
         <p> username : <span><?= $user["name"] ?></span></p>
         <p> email : <span><?= $user["email"] ?></span></p>
         <p> profile : <span style="color:<?= $has_profile
             ? "green"
             : "gray" ?>; font-weight:700;"><?= $has_profile
    ? "yes"
    : "no" ?></span></p>
         <?php if (!empty($user["updated_at"])) { ?>
            <p> updated : <span><?= $user["updated_at"] ?></span></p>
         <?php } ?>

         <form action="" method="POST" style="margin-top:1rem;">
            <input type="hidden" name="user_id" value="<?= $user["id"] ?>">

            <select name="goal" class="box" required>
               <option value="" disabled <?= $has_profile
                   ? ""
                   : "selected" ?>>select goal</option>
               <?php foreach ($allowed_goals as $goal_option) { ?>
                  <option value="<?= $goal_option ?>" <?= $user["goal"] ===
$goal_option
    ? "selected"
    : "" ?>><?= $goal_option ?></option>
               <?php } ?>
            </select>

            <input type="number" name="calorie_target" min="1" step="1" placeholder="calorie target (optional)" class="box" value="<?= $user[
                "calorie_target"
            ] !== null
                ? $user["calorie_target"]
                : "" ?>">

            <textarea name="allergies" placeholder="allergies (optional)" class="box" cols="30" rows="3"><?= $user[
                "allergies"
            ] ?? "" ?></textarea>

            <textarea name="notes" placeholder="notes (optional)" class="box" cols="30" rows="4"><?= $user[
                "notes"
            ] ?? "" ?></textarea>

            <input type="submit" name="save_profile" class="btn" value="save profile">
         </form>
      </div>
      <?php
          }
      } else {
          echo '<p class="empty">no users found!</p>';
      } ?>
   </div>

</section>

<?php include "admin_footer.php"; ?>

</body>
</html>


