<?php
$current_page = basename($_SERVER["PHP_SELF"]);

function admin_nav_link($label, $href, $icon, $aliases = [])
{
    $current_page = basename($_SERVER["PHP_SELF"]);
    $active = in_array($current_page, array_merge([$href], $aliases), true);
    $active_class = $active ? " active" : "";
    echo '<a class="nav-link' .
        $active_class .
        '" href="' .
        $href .
        '"><i class="fas ' .
        $icon .
        '"></i><span>' .
        $label .
        "</span></a>";
}
?>

<aside class="admin-sidebar">
   <div class="sidebar-brand">
      <a href="admin_page.php" class="logo">NutriBox Admin</a>
      <button class="icon-btn sidebar-close" type="button" data-sidebar-toggle aria-label="Close sidebar">
         <i class="fas fa-times"></i>
      </button>
   </div>

   <nav class="sidebar-nav">
      <?php
      admin_nav_link("Dashboard", "admin_page.php", "fa-gauge-high");
      admin_nav_link("Meal Plans", "admin_products.php", "fa-clipboard-list");
      admin_nav_link("Subscriptions", "admin_subscriptions.php", "fa-receipt");
      admin_nav_link(
          "Pickup Slots",
          "admin_pickup_slots.php",
          "fa-calendar-check",
          [
              "admin_pickup_slot_templates.php",
              "admin_pickup_slots_availability.php",
          ],
      );
      admin_nav_link("Daily Meals", "admin_daily_meals.php", "fa-bowl-food");
      admin_nav_link("User Profiles", "admin_user_profiles.php", "fa-id-card");
      admin_nav_link("Users", "admin_users.php", "fa-users");
      admin_nav_link("Messages", "admin_contacts.php", "fa-envelope");
      admin_nav_link(
          "Notifications",
          "admin_send_notifications.php",
          "fa-bell",
      );
      admin_nav_link("Schema Installer", "admin_schema.php", "fa-database");
      ?>
   </nav>

   <div class="sidebar-footer">
      <?php
      $select_profile = $conn->prepare("SELECT * FROM `users` WHERE id = ?");
      $select_profile->execute([$admin_id]);
      $fetch_profile = $select_profile->fetch(PDO::FETCH_ASSOC);
      ?>
      <div class="profile-card">
         <img src="../uploaded_img/<?= $fetch_profile["image"] ?>" alt="">
         <div class="profile-info">
            <p class="profile-name"><?= $fetch_profile["name"] ?></p>
            <p class="profile-role">Administrator</p>
         </div>
      </div>
      <a href="admin_update_profile.php" class="btn">update profile</a>
      <a href="logout.php" class="delete-btn">logout</a>
   </div>
</aside>

<div class="admin-overlay" data-sidebar-toggle></div>
