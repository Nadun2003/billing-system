CREATE DATABASE IF NOT EXISTS electricity_billing
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE electricity_billing;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meters (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    meter_number VARCHAR(100) NOT NULL,
    unit_rate DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_meters_meter_number (meter_number),
    KEY idx_meters_customer_id (customer_id),
    CONSTRAINT fk_meters_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bills (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    meter_id INT UNSIGNED NOT NULL,
    units DECIMAL(10, 2) NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    bill_date DATE NOT NULL,
    PRIMARY KEY (id),
    KEY idx_bills_meter_id (meter_id),
    CONSTRAINT fk_bills_meter
        FOREIGN KEY (meter_id) REFERENCES meters (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bill_id INT UNSIGNED NOT NULL,
    method VARCHAR(30) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    payment_date DATE NOT NULL,
    PRIMARY KEY (id),
    KEY idx_payments_bill_id (bill_id),
    CONSTRAINT fk_payments_bill
        FOREIGN KEY (bill_id) REFERENCES bills (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (username, password)
VALUES ('admin', MD5('admin123'))
ON DUPLICATE KEY UPDATE username = VALUES(username);
