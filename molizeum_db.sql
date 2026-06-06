-- Molizeum — Электронный журнал компьютерного клуба
-- База данных: molizeum

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `molizeum` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `molizeum`;

-- --------------------------------------------------------
-- Таблица пользователей (клиенты / сотрудники / админы)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `first_name`   VARCHAR(50)  NOT NULL,
  `last_name`    VARCHAR(50)  NOT NULL,
  `email`        VARCHAR(100) NOT NULL UNIQUE,
  `phone`        VARCHAR(20)  NOT NULL,
  `password`     VARCHAR(255) NOT NULL,
  `role`         ENUM('admin','employee','client') NOT NULL DEFAULT 'client',
  `status`       ENUM('active','blocked','unconfirmed') NOT NULL DEFAULT 'active',
  `balance`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `schedule`     VARCHAR(100) DEFAULT NULL COMMENT 'График работы сотрудника',
  `salary`       INT DEFAULT NULL COMMENT 'Зарплата сотрудника (руб)',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Залы
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `halls` (
  `id`     INT AUTO_INCREMENT PRIMARY KEY,
  `number` INT NOT NULL,
  `name`   VARCHAR(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Оборудование (ПК)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `equipment` (
  `id`       INT AUTO_INCREMENT PRIMARY KEY,
  `number`   INT NOT NULL,
  `cpu`      VARCHAR(60) NOT NULL,
  `gpu`      VARCHAR(60) NOT NULL,
  `ram`      VARCHAR(20) NOT NULL,
  `monitor`  VARCHAR(60) NOT NULL,
  `keyboard` VARCHAR(60) NOT NULL,
  `mouse`    VARCHAR(60) NOT NULL,
  `status`   ENUM('free','busy','repair') NOT NULL DEFAULT 'free',
  `hall_id`  INT NOT NULL,
  FOREIGN KEY (`hall_id`) REFERENCES `halls`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Тарифы
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `services` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `name`             VARCHAR(80) NOT NULL,
  `category`         ENUM('pc_rent','food','additional') NOT NULL DEFAULT 'pc_rent',
  `price`            DECIMAL(10,2) NOT NULL,
  `duration_minutes` INT DEFAULT NULL COMMENT 'Для аренды: минут за цену',
  `status`           ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Акции
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `promotions` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `name`             VARCHAR(80) NOT NULL,
  `description`      TEXT NOT NULL,
  `discount_percent` INT NOT NULL DEFAULT 0,
  `start_date`       DATE DEFAULT NULL,
  `end_date`         DATE DEFAULT NULL,
  `status`           ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Бронирования
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bookings` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `client_id`     INT NOT NULL,
  `employee_id`   INT NOT NULL,
  `equipment_id`  INT NOT NULL,
  `service_id`    INT NOT NULL,
  `promotion_id`  INT DEFAULT NULL,
  `start_time`    DATETIME NOT NULL,
  `duration_hours` INT NOT NULL DEFAULT 1,
  `end_time`      DATETIME DEFAULT NULL,
  `status`        ENUM('active','finished','cancelled') NOT NULL DEFAULT 'active',
  `total_amount`  DECIMAL(10,2) DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`)    REFERENCES `users`(`id`),
  FOREIGN KEY (`employee_id`)  REFERENCES `users`(`id`),
  FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`),
  FOREIGN KEY (`service_id`)   REFERENCES `services`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Продажи снеков/напитков
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `snack_sales` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `client_id`  INT NOT NULL,
  `service_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `quantity`   INT NOT NULL DEFAULT 1,
  `total_price` DECIMAL(10,2) NOT NULL,
  `sold_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`)   REFERENCES `users`(`id`),
  FOREIGN KEY (`service_id`)  REFERENCES `services`(`id`),
  FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ТЕСТОВЫЕ ДАННЫЕ
-- ============================================================

-- Залы
INSERT INTO `halls` (`number`, `name`) VALUES
(1, 'VIP-зал'),
(2, '1 зал');

-- Оборудование
INSERT INTO `equipment` (`number`, `cpu`, `gpu`, `ram`, `monitor`, `keyboard`, `mouse`, `status`, `hall_id`) VALUES
(1, 'Intel i7-12700K', 'RTX 3070', '16GB', 'ASUS TUF 240Hz', 'HyperX Alloy FPS', 'Logitech G Pro X', 'free', 1),
(2, 'AMD Ryzen 7 5800X', 'RTX 3080', '32GB', 'BenQ ZOWIE 144Hz', 'Corsair K70 RGB', 'Razer DeathAdder V3', 'busy', 1),
(3, 'Intel i5-12400', 'RTX 3060', '16GB', 'AOC Gaming 144Hz', 'A4Tech Bloody B810R', 'SteelSeries Rival 3', 'free', 2),
(4, 'AMD Ryzen 5 5600', 'RTX 3060 Ti', '16GB', 'LG 144Hz', 'Logitech G213', 'Logitech G102', 'free', 2);

-- Тарифы
INSERT INTO `services` (`name`, `category`, `price`, `duration_minutes`, `status`) VALUES
('Стандарт', 'pc_rent', 100.00, 60, 'active'),
('VIP', 'pc_rent', 200.00, 60, 'active'),
('Ночной', 'pc_rent', 80.00, 60, 'active'),
('Coca-Cola 0.5л', 'food', 80.00, NULL, 'active'),
('Чипсы Lays', 'food', 100.00, NULL, 'active'),
('Сникерс', 'food', 60.00, NULL, 'active'),
('Red Bull', 'food', 150.00, NULL, 'active'),
('Печать', 'additional', 10.00, NULL, 'active');

-- Акции
INSERT INTO `promotions` (`name`, `description`, `discount_percent`, `start_date`, `end_date`, `status`) VALUES
('Счастливые часы', 'Скидка 30% на игровое время с 14:00 до 17:00 по будням', 30, '2026-05-01', '2026-12-31', 'active'),
('Выходные с друзьями', 'При бронировании 3-х и более мест — скидка 20%', 20, '2026-05-01', '2026-12-31', 'active'),
('Летняя акция', 'Праздничная скидка 50% на все тарифы в июне', 50, '2026-06-01', '2026-06-30', 'inactive');

-- Пользователи (пароли НЕ хешированы — для учебного проекта)
INSERT INTO `users` (`first_name`, `last_name`, `email`, `phone`, `password`, `role`, `status`, `balance`, `schedule`, `salary`) VALUES
('Администратор', 'Системный', 'admin@molizeum.ru', '+79001000000', 'admin123', 'admin', 'active', 0, NULL, NULL),
('Иван', 'Петров', 'ivan@molizeum.ru', '+79991234567', '1234', 'employee', 'active', 0, 'Пн, Вт, Ср 09:00-17:00', 35000),
('Мария', 'Сидорова', 'maria@example.com', '+79992345678', '1234', 'client', 'active', 500, NULL, NULL),
('Алексей', 'Смирнов', 'alex@example.com', '+79993456789', '1234', 'client', 'active', 1200, NULL, NULL),
('Дмитрий', 'Иванов', 'dmitry@example.com', '+79994567890', '1234', 'client', 'active', 500, NULL, NULL),
('Ольга', 'Петрова', 'olga@example.com', '+79995678901', '1234', 'client', 'active', 1200, NULL, NULL);

-- --------------------------------------------------------
-- История транзакций баланса клиента
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT NOT NULL,
  `amount`      DECIMAL(10,2) NOT NULL COMMENT 'Положительное — пополнение, отрицательное — списание',
  `type`        ENUM('topup','booking','snack') NOT NULL DEFAULT 'topup',
  `description` VARCHAR(120) NOT NULL DEFAULT '',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Тестовые транзакции
INSERT INTO `payments` (`user_id`, `amount`, `type`, `description`, `created_at`) VALUES
(3, 1000, 'topup',    'Пополнение баланса', '2025-11-20 14:30:00'),
(3, -300, 'booking',  'Оплата игровой сессии (ПК #1, 3 ч)', '2025-11-19 18:00:00'),
(3,  500, 'topup',    'Пополнение баланса', '2025-11-18 12:15:00'),
(4, 1500, 'topup',    'Пополнение баланса', '2025-11-21 10:00:00'),
(4, -200, 'booking',  'Оплата игровой сессии (ПК #2, 2 ч)', '2025-11-22 15:30:00');
