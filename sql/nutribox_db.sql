CREATE DATABASE IF NOT EXISTS nutribox_db;
USE nutribox_db;

-- Core Tables
CREATE TABLE IF NOT EXISTS users (
  id INT(100) NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  password VARCHAR(255) NOT NULL,
  user_type VARCHAR(20) NOT NULL DEFAULT 'user',
  image VARCHAR(100) NOT NULL DEFAULT 'default-avatar.svg',
  PRIMARY KEY (id),
  UNIQUE KEY unique_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS meal_plans (
  id INT(100) NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(500) NOT NULL,
  price INT(100) NOT NULL,
  duration INT(10) NOT NULL COMMENT 'Duration in days',
  calories INT(10) NOT NULL,
  diet_type VARCHAR(50) NOT NULL,
  image VARCHAR(100) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subscriptions (
  id INT(100) NOT NULL AUTO_INCREMENT,
  user_id INT(100) NOT NULL,
  meal_plan_id INT(100) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Pending',
  payment_status VARCHAR(20) NOT NULL DEFAULT 'Unpaid',
  approval_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
  change_requested_plan_id INT(100) DEFAULT NULL,
  pause_start DATE DEFAULT NULL,
  pause_end DATE DEFAULT NULL,
  original_price INT(100) DEFAULT NULL,
  overridden_price INT(100) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_user_id (user_id),
  KEY idx_meal_plan_id (meal_plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subscription_orders (
  id INT(100) NOT NULL AUTO_INCREMENT,
  user_id INT(100) NOT NULL,
  meal_plan_id INT(100) NOT NULL,
  plan_summary VARCHAR(1000) NOT NULL,
  total_price INT(100) NOT NULL,
  payment_method VARCHAR(50) NOT NULL,
  placed_on DATE NOT NULL,
  payment_status VARCHAR(20) NOT NULL DEFAULT 'Paid',
  PRIMARY KEY (id),
  KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
  id INT(100) NOT NULL AUTO_INCREMENT,
  user_id INT(100) NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  number VARCHAR(12) NOT NULL,
  message VARCHAR(500) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wishlist (
  id INT(100) NOT NULL AUTO_INCREMENT,
  user_id INT(100) NOT NULL,
  meal_plan_id INT(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY unique_user_plan (user_id, meal_plan_id),
  KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Additional Tables
CREATE TABLE IF NOT EXISTS subscription_logs (
  id INT(100) NOT NULL AUTO_INCREMENT,
  subscription_id INT(100) NOT NULL,
  action VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_subscription_id (subscription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id INT(100) NOT NULL AUTO_INCREMENT,
  user_id INT(100) NOT NULL,
  subscription_id INT(100) DEFAULT NULL,
  event VARCHAR(50) NOT NULL,
  title VARCHAR(150) NOT NULL,
  message VARCHAR(1000) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user_id (user_id),
  KEY idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subscription_pickup_slots (
  id INT(100) NOT NULL AUTO_INCREMENT,
  subscription_id INT(100) NOT NULL,
  pickup_slot_id INT(100) DEFAULT NULL,
  pickup_date DATE NOT NULL,
  time_from TIME NOT NULL,
  time_to TIME NOT NULL,
  location VARCHAR(255) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Assigned',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_subscription_id (subscription_id),
  KEY idx_pickup_slot_id (pickup_slot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subscription_meals (
  id INT(100) NOT NULL AUTO_INCREMENT,
  subscription_id INT(100) NOT NULL,
  meal_date DATE NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_subscription_meal_date (subscription_id, meal_date),
  KEY idx_subscription_id (subscription_id),
  KEY idx_meal_date (meal_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pickup_slot_templates (
  id INT(100) NOT NULL AUTO_INCREMENT,
  slot_type VARCHAR(20) NOT NULL,
  slot_date DATE DEFAULT NULL,
  weekday TINYINT(1) DEFAULT NULL,
  time_from TIME NOT NULL,
  time_to TIME NOT NULL,
  location VARCHAR(255) NOT NULL,
  max_capacity INT(10) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pickup_slots (
  id INT(100) NOT NULL AUTO_INCREMENT,
  template_id INT(100) DEFAULT NULL,
  pickup_date DATE NOT NULL,
  time_from TIME NOT NULL,
  time_to TIME NOT NULL,
  location VARCHAR(255) NOT NULL,
  max_capacity INT(10) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Available',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_template_id (template_id),
  KEY idx_pickup_date (pickup_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_profiles (
  id INT(100) NOT NULL AUTO_INCREMENT,
  user_id INT(100) NOT NULL,
  goal VARCHAR(50) NOT NULL,
  calorie_target INT(100) DEFAULT NULL,
  allergies TEXT DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS schema_migrations (
  id INT(100) NOT NULL AUTO_INCREMENT,
  version INT(10) NOT NULL,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Data: Admin User and Regular User
INSERT INTO users (name, email, password, user_type, image) VALUES
('Admin User', 'admin@nutribox.com', 'admin123', 'admin', 'default-avatar.svg'),
('John Doe', 'user@nutribox.com', 'user123', 'user', 'default-avatar.svg');

-- Insert Data: 10 Meal Plans
INSERT INTO meal_plans (name, description, price, duration, calories, diet_type, image) VALUES
('7-Day Vegan Detox Plan', 'A light vegan meal plan focused on detox and digestion. Perfect for cleansing your body with plant-based nutrition.', 3500, 7, 1500, 'Vegan', 'vegan_7.jpg'),
('14-Day Keto Weight Loss Plan', 'Low-carb keto meals designed for rapid fat burning. High fat, moderate protein, minimal carbs.', 6500, 14, 1800, 'Keto', 'keto_14.jpg'),
('30-Day High Protein Fitness Plan', 'High protein meals ideal for muscle building and fitness. Supports recovery and growth.', 12000, 30, 2200, 'High Protein', 'protein_30.jpg'),
('14-Day Balanced Nutrition Plan', 'Balanced meals suitable for everyday healthy living. All food groups included.', 5500, 14, 2000, 'Balanced', 'balanced_14.jpg'),
('21-Day Mediterranean Diet', 'Heart-healthy Mediterranean cuisine with olive oil, fish, and fresh vegetables.', 8500, 21, 1900, 'Mediterranean', 'mediterranean_21.jpg'),
('7-Day Gluten-Free Plan', 'Completely gluten-free meals for those with celiac disease or gluten sensitivity.', 4000, 7, 1700, 'Gluten-Free', 'glutenfree_7.jpg'),
('30-Day Paleo Plan', 'Caveman diet with whole foods, lean meats, and no processed foods.', 11000, 30, 2100, 'Paleo', 'paleo_30.jpg'),
('14-Day Low-Carb Plan', 'Reduced carbohydrate intake for weight management and blood sugar control.', 6000, 14, 1600, 'Low-Carb', 'lowcarb_14.jpg'),
('7-Day Raw Food Plan', 'Uncooked, unprocessed plant-based foods for maximum nutrition.', 4500, 7, 1400, 'Raw', 'raw_7.jpg'),
('28-Day Weight Loss Plan', 'Calorie-controlled meals designed for sustainable weight loss.', 10000, 28, 1500, 'Weight Loss', 'weightloss_28.jpg');

-- Insert Data: 10 Subscriptions (for regular user - user_id = 2)
INSERT INTO subscriptions (user_id, meal_plan_id, start_date, end_date, status, payment_status, approval_status, original_price) VALUES
(2, 1, '2025-01-01', '2025-01-07', 'Expired', 'Paid', 'Approved', 3500),
(2, 2, '2025-01-15', '2025-01-28', 'Active', 'Paid', 'Approved', 6500),
(2, 3, '2025-02-01', '2025-03-02', 'Active', 'Paid', 'Approved', 12000),
(2, 4, '2025-02-10', '2025-02-23', 'Pending', 'Unpaid', 'Pending', 5500),
(2, 5, '2025-03-01', '2025-03-21', 'Active', 'Paid', 'Approved', 8500),
(2, 6, '2025-03-15', '2025-03-21', 'Paused', 'Paid', 'Approved', 4000),
(2, 7, '2025-04-01', '2025-04-30', 'Active', 'Paid', 'Approved', 11000),
(2, 8, '2025-04-10', '2025-04-23', 'Pending', 'Unpaid', 'Pending', 6000),
(2, 9, '2025-05-01', '2025-05-07', 'Active', 'Paid', 'Approved', 4500),
(2, 10, '2025-05-15', '2025-06-11', 'Active', 'Paid', 'Approved', 10000);

-- Insert Data: 10 Subscription Orders (for regular user - user_id = 2)
INSERT INTO subscription_orders (user_id, meal_plan_id, plan_summary, total_price, payment_method, placed_on, payment_status) VALUES
(2, 1, '7-Day Vegan Detox Plan | 1500 Calories/day', 3500, 'Cash On Delivery', '2025-01-01', 'Paid'),
(2, 2, '14-Day Keto Weight Loss Plan | 1800 Calories/day', 6500, 'Card Payment', '2025-01-15', 'Paid'),
(2, 3, '30-Day High Protein Fitness Plan | 2200 Calories/day', 12000, 'Online Payment', '2025-02-01', 'Paid'),
(2, 4, '14-Day Balanced Nutrition Plan | 2000 Calories/day', 5500, 'Cash On Delivery', '2025-02-10', 'Pending'),
(2, 5, '21-Day Mediterranean Diet | 1900 Calories/day', 8500, 'Card Payment', '2025-03-01', 'Paid'),
(2, 6, '7-Day Gluten-Free Plan | 1700 Calories/day', 4000, 'Online Payment', '2025-03-15', 'Paid'),
(2, 7, '30-Day Paleo Plan | 2100 Calories/day', 11000, 'Card Payment', '2025-04-01', 'Paid'),
(2, 8, '14-Day Low-Carb Plan | 1600 Calories/day', 6000, 'Cash On Delivery', '2025-04-10', 'Pending'),
(2, 9, '7-Day Raw Food Plan | 1400 Calories/day', 4500, 'Online Payment', '2025-05-01', 'Paid'),
(2, 10, '28-Day Weight Loss Plan | 1500 Calories/day', 10000, 'Card Payment', '2025-05-15', 'Paid');

-- Insert Data: 10 Messages (for regular user - user_id = 2)
INSERT INTO messages (user_id, name, email, number, message) VALUES
(2, 'John Doe', 'user@nutribox.com', '0771234567', 'I would like to know if you have vegetarian meal options.'),
(2, 'John Doe', 'user@nutribox.com', '0771234567', 'Can I change my meal plan after subscribing?'),
(2, 'John Doe', 'user@nutribox.com', '0771234567', 'What is the delivery schedule for meal plans?'),
(2, 'John Doe', 'user@nutribox.com', '0771234567', 'Do you offer custom meal plans for specific dietary restrictions?'),
(2, 'John Doe', 'user@nutribox.com', '0771234567', 'How can I pause my subscription temporarily?'),
(2, 'John Doe', 'user@nutribox.com', '0771234567', 'Are the ingredients organic and locally sourced?'),
(2, 'John Doe', 'user@nutribox.com', '0771234567', 'Can I get nutritional information for each meal?'),
(2, 'John Doe', 'user@nutribox.com', '0771234567', 'What payment methods do you accept?'),
(2, 'John Doe', 'user@nutribox.com', '0771234567', 'Is there a minimum subscription period?'),
(2, 'John Doe', 'user@nutribox.com', '0771234567', 'How do I cancel my subscription?');

-- Insert Data: 10 Wishlist Items (for regular user - user_id = 2)
INSERT INTO wishlist (user_id, meal_plan_id) VALUES
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5),
(2, 6),
(2, 7),
(2, 8),
(2, 9),
(2, 10);

-- Insert Data: 10 Subscription Logs
INSERT INTO subscription_logs (subscription_id, action) VALUES
(1, 'CREATED'),
(1, 'APPROVED'),
(1, 'PAID'),
(2, 'CREATED'),
(2, 'APPROVED'),
(2, 'PAID'),
(2, 'ACTIVATED'),
(3, 'CREATED'),
(3, 'APPROVED'),
(3, 'PAID');

-- Insert Data: 10 Notifications (for regular user - user_id = 2)
INSERT INTO notifications (user_id, subscription_id, event, title, message, is_read) VALUES
(2, 1, 'subscription_approved', 'Subscription Approved', 'Your 7-Day Vegan Detox Plan subscription has been approved!', 0),
(2, 2, 'payment_received', 'Payment Received', 'Payment for your 14-Day Keto Weight Loss Plan has been received.', 0),
(2, 3, 'subscription_active', 'Subscription Active', 'Your 30-Day High Protein Fitness Plan is now active.', 1),
(2, 4, 'payment_pending', 'Payment Pending', 'Please complete payment for your 14-Day Balanced Nutrition Plan.', 0),
(2, 5, 'meal_ready', 'Meal Ready for Pickup', 'Your Mediterranean meal is ready for pickup today.', 0),
(2, 6, 'subscription_paused', 'Subscription Paused', 'Your 7-Day Gluten-Free Plan subscription has been paused.', 1),
(2, 7, 'reminder', 'Pickup Reminder', 'Don\'t forget to pick up your Paleo meal today between 10 AM - 12 PM.', 0),
(2, 8, 'subscription_expiring', 'Subscription Expiring Soon', 'Your Low-Carb Plan subscription will expire in 3 days.', 0),
(2, 9, 'new_plan', 'New Plan Available', 'Check out our new Raw Food Plan - perfect for detox!', 0),
(2, 10, 'subscription_renewed', 'Subscription Renewed', 'Your Weight Loss Plan has been automatically renewed.', 1);

-- Insert Data: 10 Subscription Pickup Slots
INSERT INTO subscription_pickup_slots (subscription_id, pickup_date, time_from, time_to, location, status) VALUES
(1, '2025-01-02', '10:00:00', '12:00:00', 'Main Store - Downtown', 'Assigned'),
(2, '2025-01-16', '14:00:00', '16:00:00', 'Branch Store - Uptown', 'Assigned'),
(3, '2025-02-02', '10:00:00', '12:00:00', 'Main Store - Downtown', 'Assigned'),
(4, '2025-02-11', '09:00:00', '11:00:00', 'Branch Store - Uptown', 'Pending'),
(5, '2025-03-02', '13:00:00', '15:00:00', 'Main Store - Downtown', 'Assigned'),
(6, '2025-03-16', '10:00:00', '12:00:00', 'Branch Store - Uptown', 'Assigned'),
(7, '2025-04-02', '11:00:00', '13:00:00', 'Main Store - Downtown', 'Assigned'),
(8, '2025-04-11', '14:00:00', '16:00:00', 'Branch Store - Uptown', 'Pending'),
(9, '2025-05-02', '10:00:00', '12:00:00', 'Main Store - Downtown', 'Assigned'),
(10, '2025-05-16', '15:00:00', '17:00:00', 'Branch Store - Uptown', 'Assigned');

-- Insert Data: 10 Subscription Meals
INSERT INTO subscription_meals (subscription_id, meal_date, status) VALUES
(1, '2025-01-02', 'PickedUp'),
(1, '2025-01-03', 'PickedUp'),
(1, '2025-01-04', 'PickedUp'),
(2, '2025-01-16', 'PickedUp'),
(2, '2025-01-17', 'Pending'),
(3, '2025-02-02', 'PickedUp'),
(3, '2025-02-03', 'PickedUp'),
(5, '2025-03-02', 'PickedUp'),
(7, '2025-04-02', 'PickedUp'),
(9, '2025-05-02', 'Pending');

-- Insert Data: 10 Pickup Slot Templates
INSERT INTO pickup_slot_templates (slot_type, weekday, time_from, time_to, location, max_capacity, is_active) VALUES
('Daily', 1, '10:00:00', '12:00:00', 'Main Store - Downtown', 50, 1),
('Daily', 2, '10:00:00', '12:00:00', 'Main Store - Downtown', 50, 1),
('Daily', 3, '10:00:00', '12:00:00', 'Main Store - Downtown', 50, 1),
('Daily', 4, '10:00:00', '12:00:00', 'Main Store - Downtown', 50, 1),
('Daily', 5, '10:00:00', '12:00:00', 'Main Store - Downtown', 50, 1),
('Afternoon', 1, '14:00:00', '16:00:00', 'Branch Store - Uptown', 30, 1),
('Afternoon', 2, '14:00:00', '16:00:00', 'Branch Store - Uptown', 30, 1),
('Afternoon', 3, '14:00:00', '16:00:00', 'Branch Store - Uptown', 30, 1),
('Evening', 4, '17:00:00', '19:00:00', 'Main Store - Downtown', 40, 1),
('Evening', 5, '17:00:00', '19:00:00', 'Main Store - Downtown', 40, 1);

-- Insert Data: 10 Pickup Slots
INSERT INTO pickup_slots (template_id, pickup_date, time_from, time_to, location, max_capacity, status) VALUES
(1, '2025-12-22', '10:00:00', '12:00:00', 'Main Store - Downtown', 50, 'Available'),
(2, '2025-12-23', '10:00:00', '12:00:00', 'Main Store - Downtown', 50, 'Available'),
(3, '2025-12-24', '10:00:00', '12:00:00', 'Main Store - Downtown', 50, 'Available'),
(4, '2025-12-25', '10:00:00', '12:00:00', 'Main Store - Downtown', 50, 'Available'),
(5, '2025-12-26', '10:00:00', '12:00:00', 'Main Store - Downtown', 50, 'Available'),
(6, '2025-12-22', '14:00:00', '16:00:00', 'Branch Store - Uptown', 30, 'Available'),
(7, '2025-12-23', '14:00:00', '16:00:00', 'Branch Store - Uptown', 30, 'Available'),
(8, '2025-12-24', '14:00:00', '16:00:00', 'Branch Store - Uptown', 30, 'Available'),
(9, '2025-12-25', '17:00:00', '19:00:00', 'Main Store - Downtown', 40, 'Available'),
(10, '2025-12-26', '17:00:00', '19:00:00', 'Main Store - Downtown', 40, 'Available');

-- Insert Data: User Profile (for regular user - user_id = 2)
INSERT INTO user_profiles (user_id, goal, calorie_target, allergies, notes) VALUES
(2, 'Weight Loss', 2000, 'None', 'Prefer organic ingredients when possible');

-- Insert Data: Schema Migrations (mark all migrations as applied)
INSERT INTO schema_migrations (version) VALUES
(1), (2), (3), (4), (5), (6), (7), (8), (9), (10);
