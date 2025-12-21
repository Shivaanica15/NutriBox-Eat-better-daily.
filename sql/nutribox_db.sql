CREATE DATABASE nutribox_db;
USE nutribox_db;

CREATE TABLE users (
  id INT(100) NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  password VARCHAR(255) NOT NULL,
  user_type VARCHAR(20) NOT NULL DEFAULT 'user',
  image VARCHAR(100) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE meal_plans (
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

CREATE TABLE subscriptions (
  id INT(100) NOT NULL AUTO_INCREMENT,
  user_id INT(100) NOT NULL,
  meal_plan_id INT(100) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Pending',
  payment_status VARCHAR(20) NOT NULL DEFAULT 'Unpaid',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE subscription_orders (
  id INT(100) NOT NULL AUTO_INCREMENT,
  user_id INT(100) NOT NULL,
  meal_plan_id INT(100) NOT NULL,
  plan_summary VARCHAR(1000) NOT NULL,
  total_price INT(100) NOT NULL,
  payment_method VARCHAR(50) NOT NULL,
  placed_on DATE NOT NULL,
  payment_status VARCHAR(20) NOT NULL DEFAULT 'Paid',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE messages (
  id INT(100) NOT NULL AUTO_INCREMENT,
  user_id INT(100) NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  number VARCHAR(12) NOT NULL,
  message VARCHAR(500) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE wishlist (
  id INT(100) NOT NULL AUTO_INCREMENT,
  user_id INT(100) NOT NULL,
  meal_plan_id INT(100) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (name, email, password, user_type, image) VALUES
('Admin User', 'admin@nutribox.com', '0192023a7bbd73250516f069df18b500', 'admin', 'admin.jpg'),
('John Doe', 'john@gmail.com', '6e0b7076126a29d5dfcbd54835387b7b', 'user', 'user1.jpg'),
('Sarah Smith', 'sarah@gmail.com', 'ec26202651ed221cf8f993668c459d46', 'user', 'user2.jpg'),
('Michael Lee', 'michael@gmail.com', '56cf01f6edfe9598b5e23407fe290990', 'user', 'user3.jpg');

INSERT INTO meal_plans (name, description, price, duration, calories, diet_type, image) VALUES
(
  '7-Day Vegan Detox Plan',
  'A light vegan meal plan focused on detox and digestion.',
  3500,
  7,
  1500,
  'Vegan',
  'vegan_7.jpg'
),
(
  '14-Day Keto Weight Loss Plan',
  'Low-carb keto meals designed for rapid fat burning.',
  6500,
  14,
  1800,
  'Keto',
  'keto_14.jpg'
),
(
  '30-Day High Protein Fitness Plan',
  'High protein meals ideal for muscle building and fitness.',
  12000,
  30,
  2200,
  'High Protein',
  'protein_30.jpg'
),
(
  '14-Day Balanced Nutrition Plan',
  'Balanced meals suitable for everyday healthy living.',
  5500,
  14,
  2000,
  'Balanced',
  'balanced_14.jpg'
);

INSERT INTO subscriptions (user_id, meal_plan_id, start_date, end_date, status, payment_status) VALUES
(
  2,
  1,
  '2025-01-01',
  '2025-01-07',
  'Expired',
  'Paid'
),
(
  3,
  3,
  '2025-02-01',
  '2025-03-02',
  'Active',
  'Paid'
),
(
  4,
  2,
  '2025-02-10',
  '2025-02-24',
  'Pending',
  'Unpaid'
);

INSERT INTO subscription_orders
(user_id, meal_plan_id, plan_summary, total_price, payment_method, placed_on, payment_status)
VALUES
(
  2,
  1,
  '7-Day Vegan Detox Plan | 1500 Calories/day',
  3500,
  'Cash On Delivery',
  '2025-01-01',
  'Paid'
),
(
  3,
  3,
  '30-Day High Protein Fitness Plan | 2200 Calories/day',
  12000,
  'Card Payment',
  '2025-02-01',
  'Paid'
);

INSERT INTO messages (user_id, name, email, number, message) VALUES
(
  2,
  'John Doe',
  'john@gmail.com',
  '0771234567',
  'I would like to know if you have vegetarian meal options.'
),
(
  3,
  'Sarah Smith',
  'sarah@gmail.com',
  '0719876543',
  'Can I change my meal plan after subscribing?'
);

INSERT INTO wishlist (user_id, meal_plan_id) VALUES
(2, 3),
(3, 1),
(3, 4),
(4, 2);
