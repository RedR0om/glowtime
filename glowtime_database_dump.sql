-- ============================
-- GLOWTIME SALON MANAGEMENT SYSTEM
-- Database Dump File
-- ============================

-- Create database (optional - uncomment if needed)
-- CREATE DATABASE IF NOT EXISTS glowtime_system;
-- USE glowtime_system;

-- ============================
-- USERS table (for clients & admins)
-- ============================
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  email VARCHAR(191) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(50),
  role ENUM('client','admin') DEFAULT 'client',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================
-- SERVICES table
-- ============================
CREATE TABLE IF NOT EXISTS services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  duration_minutes INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================
-- APPOINTMENTS table
-- ============================
CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_ref VARCHAR(50) UNIQUE NOT NULL, -- unique booking code
  client_id INT NOT NULL,
  service_id INT NOT NULL,
  booking_type ENUM('salon','home') DEFAULT 'salon',
  location_address TEXT NULL,
  style VARCHAR(191) NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  down_payment DECIMAL(10,2) DEFAULT 0, -- required partial payment
  transport_fee DECIMAL(10,2) DEFAULT 0,
  payment_proof VARCHAR(255) NULL, -- uploaded receipt/proof
  payment_status ENUM('pending','verified','rejected') DEFAULT 'pending', -- payment verification
  status ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending', -- appointment status
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

-- ============================
-- SEED SAMPLE SERVICES
-- ============================
INSERT INTO services (name, duration_minutes, price) VALUES
('Haircut', 45, 250.00),
('Hair Color', 120, 1800.00),
('Hair Spa', 90, 1200.00),
('Manicure', 30, 150.00),
('Pedicure', 45, 200.00),
('Facial Treatment', 60, 400.00),
('Eyebrow Shaping', 20, 100.00),
('Hair Styling', 30, 300.00);

-- ============================
-- ADMIN ACCOUNT
-- Email: admin@glowtime.com
-- Password: admin123
-- ============================
DELETE FROM users WHERE email='admin@glowtime.com';

INSERT INTO users (name, email, password, role)
VALUES (
  'Administrator',
  'admin@glowtime.com',
  -- password_hash('admin123', PASSWORD_DEFAULT)
  '$2y$10$h5jEGqyYfrRmVK68r.ScIu2gwEFh6nxflN0uh1k8LSUjRI62NVvsC',
  'admin'
);

-- ============================
-- SAMPLE CLIENT ACCOUNTS
-- ============================
INSERT INTO users (name, email, password, phone, role) VALUES
('John Doe', 'john@example.com', '$2y$10$h5jEGqyYfrRmVK68r.ScIu2gwEFh6nxflN0uh1k8LSUjRI62NVvsC', '09123456789', 'client'),
('Jane Smith', 'jane@example.com', '$2y$10$h5jEGqyYfrRmVK68r.ScIu2gwEFh6nxflN0uh1k8LSUjRI62NVvsC', '09987654321', 'client'),
('Maria Garcia', 'maria@example.com', '$2y$10$h5jEGqyYfrRmVK68r.ScIu2gwEFh6nxflN0uh1k8LSUjRI62NVvsC', '09111222333', 'client');

-- ============================
-- SAMPLE APPOINTMENTS
-- ============================
INSERT INTO appointments (booking_ref, client_id, service_id, style, start_at, end_at, down_payment, payment_status, status) VALUES
('GT001', 2, 1, 'Short bob cut', '2024-01-15 10:00:00', '2024-01-15 10:45:00', 125.00, 'verified', 'confirmed'),
('GT002', 3, 2, 'Balayage highlights', '2024-01-16 14:00:00', '2024-01-16 16:00:00', 900.00, 'verified', 'confirmed'),
('GT003', 4, 3, 'Deep conditioning treatment', '2024-01-17 09:00:00', '2024-01-17 10:30:00', 600.00, 'pending', 'pending');

-- ============================
-- INDEXES FOR BETTER PERFORMANCE
-- ============================
CREATE INDEX idx_appointments_client_id ON appointments(client_id);
CREATE INDEX idx_appointments_service_id ON appointments(service_id);
CREATE INDEX idx_appointments_start_at ON appointments(start_at);
CREATE INDEX idx_appointments_status ON appointments(status);
CREATE INDEX idx_appointments_payment_status ON appointments(payment_status);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);

-- ============================
-- END OF DUMP FILE
-- ============================