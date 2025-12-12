-- Organization Resource Management System Database Schema
-- Run this in phpMyAdmin or MySQL command line

CREATE DATABASE IF NOT EXISTS resource_management;
USE resource_management;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    department VARCHAR(100),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Resource categories
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Resources table
CREATE TABLE resources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category_id INT,
    description TEXT,
    location VARCHAR(100),
    capacity INT DEFAULT 1,
    status ENUM('available', 'maintenance', 'retired') DEFAULT 'available',
    image_url VARCHAR(255),
    specifications TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Bookings table
CREATE TABLE bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resource_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled', 'completed') DEFAULT 'pending',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('booking', 'approval', 'rejection', 'reminder', 'system') DEFAULT 'system',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity log
CREATE TABLE activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default categories
INSERT INTO categories (name, description, icon) VALUES
('Meeting Rooms', 'Conference and meeting spaces', 'door-open'),
('Projectors', 'Presentation equipment', 'projector'),
('Laptops', 'Portable computers', 'laptop'),
('Vehicles', 'Company transportation', 'car'),
('Audio Equipment', 'Microphones, speakers, etc.', 'volume-2'),
('Other Equipment', 'Miscellaneous items', 'box');

-- Insert sample resources
INSERT INTO resources (name, category_id, description, location, capacity, status) VALUES
('Conference Room A', 1, 'Large meeting room with video conferencing', 'Building A, Floor 2', 20, 'available'),
('Conference Room B', 1, 'Medium meeting room', 'Building A, Floor 3', 10, 'available'),
('Epson Projector #1', 2, '4K HD Projector', 'IT Storage', 1, 'available'),
('Dell Laptop #1', 3, 'Dell XPS 15, i7, 16GB RAM', 'IT Department', 1, 'available'),
('Dell Laptop #2', 3, 'Dell XPS 13, i5, 8GB RAM', 'IT Department', 1, 'available'),
('Toyota Hiace Van', 4, '12-seater van for group transport', 'Parking Lot B', 12, 'available'),
('Honda Civic', 4, 'Sedan for official use', 'Parking Lot A', 4, 'available'),
('Wireless Mic Set', 5, 'Shure wireless microphone system', 'Audio Room', 2, 'available');

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password, full_name, role, department) VALUES
('admin', 'admin@organization.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 'IT Department');

-- Insert sample users (password: password123)
INSERT INTO users (username, email, password, full_name, role, department) VALUES
('john.doe', 'john@organization.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', 'user', 'Marketing'),
('jane.smith', 'jane@organization.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Smith', 'user', 'Finance');
