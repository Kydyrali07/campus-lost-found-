-- Campus Lost & Found: MySQL 8.0 schema (Assignment 3, Part A3)
DROP DATABASE IF EXISTS lostfound;
CREATE DATABASE lostfound CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lostfound;

CREATE TABLE users (
  user_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(120) NOT NULL,
  password_hash CHAR(60)     NOT NULL,                 -- bcrypt hash, never plain text
  full_name     VARCHAR(100) NOT NULL,
  role          ENUM('student','admin') NOT NULL DEFAULT 'student',
  is_blocked    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email),
  CONSTRAINT chk_users_email CHECK (email LIKE '%@astanait.edu.kz')
) ENGINE=InnoDB;

CREATE TABLE categories (
  category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(40)  NOT NULL,
  PRIMARY KEY (category_id),
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB;

CREATE TABLE locations (
  location_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  building    VARCHAR(60)  NOT NULL,
  place       VARCHAR(80)  NOT NULL,
  latitude    DECIMAL(9,6) NULL,
  longitude   DECIMAL(9,6) NULL,
  PRIMARY KEY (location_id),
  UNIQUE KEY uq_locations (building, place),
  CONSTRAINT chk_lat CHECK (latitude  IS NULL OR latitude  BETWEEN -90  AND 90),
  CONSTRAINT chk_lng CHECK (longitude IS NULL OR longitude BETWEEN -180 AND 180)
) ENGINE=InnoDB;

CREATE TABLE items (
  item_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NOT NULL,
  item_type   ENUM('lost','found')    NOT NULL,
  title       VARCHAR(100) NOT NULL,
  description TEXT         NULL,
  event_date  DATE         NOT NULL,
  status      ENUM('open','resolved') NOT NULL DEFAULT 'open',
  photo_url   VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (item_id),
  KEY idx_items_status_date (status, event_date),
  KEY idx_items_category (category_id),
  FULLTEXT KEY ft_items_text (title, description),
  CONSTRAINT fk_items_user     FOREIGN KEY (user_id)     REFERENCES users(user_id)           ON DELETE CASCADE,
  CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(category_id)  ON DELETE RESTRICT,
  CONSTRAINT fk_items_location FOREIGN KEY (location_id) REFERENCES locations(location_id)   ON DELETE RESTRICT,
  CONSTRAINT chk_items_title CHECK (CHAR_LENGTH(title) >= 3)
) ENGINE=InnoDB;

-- Linking table: resolves the many-to-many relationship between users (who contacts whom about which item)
CREATE TABLE messages (
  message_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id     INT UNSIGNED NOT NULL,
  sender_id   INT UNSIGNED NOT NULL,
  receiver_id INT UNSIGNED NOT NULL,
  body        VARCHAR(1000) NOT NULL,
  sent_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (message_id),
  KEY idx_messages_item (item_id),
  CONSTRAINT fk_msg_item     FOREIGN KEY (item_id)     REFERENCES items(item_id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_sender   FOREIGN KEY (sender_id)   REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED NOT NULL,
  message_id      INT UNSIGNED NOT NULL,
  type            ENUM('reply','status') NOT NULL DEFAULT 'reply',
  is_read         TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (notification_id),
  KEY idx_notif_user (user_id, is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id)    REFERENCES users(user_id)       ON DELETE CASCADE,
  CONSTRAINT fk_notif_msg  FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reports (
  report_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id     INT UNSIGNED NOT NULL,
  reporter_id INT UNSIGNED NOT NULL,
  reason      VARCHAR(255) NOT NULL,
  status      ENUM('new','reviewed') NOT NULL DEFAULT 'new',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (report_id),
  CONSTRAINT fk_rep_item     FOREIGN KEY (item_id)     REFERENCES items(item_id) ON DELETE CASCADE,
  CONSTRAINT fk_rep_reporter FOREIGN KEY (reporter_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE admin_logs (
  log_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id   INT UNSIGNED NOT NULL,
  item_id    INT UNSIGNED NULL,
  action     ENUM('delete_post','block_user','unblock_user') NOT NULL,
  details    VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (log_id),
  CONSTRAINT fk_log_admin FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_log_item  FOREIGN KEY (item_id)  REFERENCES items(item_id) ON DELETE SET NULL
) ENGINE=InnoDB;
