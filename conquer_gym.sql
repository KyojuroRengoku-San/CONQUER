-- Create database
CREATE DATABASE IF NOT EXISTS conquer_gym;
USE conquer_gym;

-- Members table (existing)
CREATE TABLE gym_members (
    ID INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(100) NOT NULL,
    Age INT(3) NOT NULL,
    MembershipPlan VARCHAR(50) NOT NULL,
    ContactNumber VARCHAR(15) NOT NULL,
    Email VARCHAR(100) UNIQUE NOT NULL,
    JoinDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    MembershipStatus ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active'
);

-- Users table for login system
CREATE TABLE users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    user_type ENUM('member', 'trainer', 'admin') DEFAULT 'member',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- Success stories table
CREATE TABLE success_stories (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED,
    title VARCHAR(200) NOT NULL,
    before_image VARCHAR(255),
    after_image VARCHAR(255),
    story_text TEXT NOT NULL,
    weight_loss DECIMAL(5,2),
    months_taken INT(3),
    trainer_id INT(11) UNSIGNED,
    is_featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Trainers table
CREATE TABLE trainers (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED UNIQUE,
    specialty VARCHAR(100) NOT NULL,
    certification VARCHAR(200),
    years_experience INT(3),
    bio TEXT,
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_reviews INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Classes table - UPDATED WITH ALL COLUMNS
CREATE TABLE classes (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(100) NOT NULL,
    trainer_id INT(11) UNSIGNED,
    schedule DATETIME NOT NULL,
    duration_minutes INT NOT NULL,
    duration VARCHAR(20),
    max_capacity INT NOT NULL,
    location VARCHAR(100),
    current_enrollment INT DEFAULT 0,
    class_type VARCHAR(50),
    difficulty_level VARCHAR(50),
    intensity_level VARCHAR(50),
    description TEXT,
    status ENUM('active', 'inactive', 'cancelled') DEFAULT 'active',
    FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE SET NULL
);

-- Bookings table - UPDATED
CREATE TABLE bookings (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED,
    class_id INT(11) UNSIGNED,
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    status ENUM('pending', 'confirmed', 'cancelled', 'attended', 'no-show') DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

-- Payments table
CREATE TABLE payments (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED,
    amount DECIMAL(10,2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_method ENUM('credit_card', 'debit_card', 'paypal', 'bank_transfer', 'cash'),
    status ENUM('completed', 'pending', 'failed', 'refunded'),
    subscription_period VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Equipment table
CREATE TABLE equipment (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_name VARCHAR(100) NOT NULL,
    brand VARCHAR(100),
    purchase_date DATE,
    last_maintenance DATE,
    next_maintenance DATE,
    status ENUM('active', 'maintenance', 'retired'),
    location VARCHAR(100)
);

-- Contact messages table
CREATE TABLE contact_messages (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    subject VARCHAR(200),
    message TEXT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('new', 'read', 'replied', 'closed') DEFAULT 'new'
);

-- Insert sample data with proper password hash (password: "password")
INSERT INTO users (username, email, password_hash, full_name, user_type, is_active) VALUES
('admin', 'admin@conquergym.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', 1),
('markj', 'mark@conquergym.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mark Johnson', 'trainer', 1),
('sarahc', 'sarah@conquergym.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Chen', 'trainer', 1),
('john_doe', 'john@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', 'member', 1),
('jane_smith', 'jane@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Smith', 'member', 1),
('bob_wilson', 'bob@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob Wilson', 'member', 1);

INSERT INTO gym_members (Name, Age, MembershipPlan, ContactNumber, Email, MembershipStatus) VALUES
('John Doe', 28, 'Legend', '555-0101', 'john@email.com', 'Active'),
('Jane Smith', 32, 'Champion', '555-0102', 'jane@email.com', 'Active'),
('Bob Wilson', 45, 'Warrior', '555-0103', 'bob@email.com', 'Active');

INSERT INTO trainers (user_id, specialty, certification, years_experience, bio, rating, total_reviews) VALUES
(2, 'Strength & Conditioning', 'NASM Certified, CrossFit Level 2', 10, 'Former professional athlete with 10+ years training experience', 4.8, 50),
(3, 'Yoga & Mobility', 'RYT 500, ACE Certified', 8, 'Specialized in yoga therapy and mobility training', 4.9, 45);

-- Insert sample classes with all columns
INSERT INTO classes (class_name, trainer_id, schedule, duration_minutes, duration, max_capacity, location, current_enrollment, class_type, difficulty_level, intensity_level, description, status) VALUES
('Morning Yoga', 2, DATE_ADD(NOW(), INTERVAL 1 DAY), 60, '60 min', 20, 'Yoga Studio', 15, 'Yoga', 'Beginner', 'Low', 'Start your day with peaceful yoga stretches and breathing exercises', 'active'),
('HIIT Blast', 1, DATE_ADD(NOW(), INTERVAL 2 DAY), 45, '45 min', 15, 'Main Studio', 12, 'HIIT', 'Intermediate', 'High', 'High-intensity interval training for maximum calorie burn', 'active'),
('Strength Training', 2, DATE_ADD(NOW(), INTERVAL 3 DAY), 60, '60 min', 10, 'Weight Room', 8, 'Strength', 'Advanced', 'Medium', 'Build muscle and strength with compound exercises', 'active'),
('Cardio Kickboxing', 1, DATE_ADD(NOW(), INTERVAL 4 DAY), 50, '50 min', 25, 'Main Studio', 20, 'Cardio', 'Intermediate', 'High', 'Fun cardio workout combining kickboxing moves', 'active'),
('CrossFit WOD', 2, DATE_ADD(NOW(), INTERVAL 5 DAY), 60, '60 min', 15, 'CrossFit Area', 10, 'CrossFit', 'Advanced', 'High', 'Daily CrossFit workout of the day', 'active'),
('Evening Pilates', 1, DATE_ADD(NOW(), INTERVAL 6 DAY), 55, '55 min', 12, 'Pilates Studio', 5, 'Pilates', 'Beginner', 'Low', 'Core strengthening and flexibility training', 'active');

-- Insert sample bookings
INSERT INTO bookings (user_id, class_id, notes, status) VALUES
(4, 1, 'First time trying yoga', 'confirmed'),
(4, 2, 'Bring water bottle', 'confirmed'),
(5, 3, 'Need modifications for back', 'confirmed'),
(6, 4, 'Regular attendee', 'confirmed'),
(4, 5, NULL, 'pending');

-- Insert sample payments
INSERT INTO payments (user_id, amount, payment_method, status, subscription_period) VALUES
(4, 49.99, 'credit_card', 'completed', 'Monthly'),
(5, 79.99, 'debit_card', 'completed', 'Monthly'),
(6, 29.99, 'paypal', 'completed', 'Monthly'),
(4, 49.99, 'credit_card', 'completed', 'Monthly'),
(5, 79.99, 'debit_card', 'pending', 'Monthly');

-- Insert sample success stories
INSERT INTO success_stories (user_id, title, story_text, weight_loss, months_taken, trainer_id, approved) VALUES
(4, 'Lost 50lbs in 6 months!', 'Thanks to CONQUER Gym and my amazing trainer Mark, I transformed my life... Starting at 220lbs and now down to 170lbs!', 50.5, 6, 2, 1),
(5, 'From Couch to 5K in 3 months', 'Sarah helped me build confidence and stamina I never knew I had... Now I run 5K every morning!', 30.2, 3, 3, 1),
(6, 'Gained Strength, Lost Body Fat', 'The combination of strength training and proper nutrition changed everything... Added 20lbs of muscle while losing fat!', 25.7, 4, 2, 1);

-- Insert sample equipment
INSERT INTO equipment (equipment_name, brand, purchase_date, last_maintenance, next_maintenance, status, location) VALUES
('Treadmill Pro 5000', 'LifeFitness', '2023-01-15', '2023-12-01', '2024-06-01', 'active', 'Cardio Zone'),
('Leg Press Machine', 'Hammer Strength', '2022-05-20', '2023-11-15', '2024-05-15', 'active', 'Strength Area'),
('Multi-Station Gym', 'Cybex', '2021-08-10', '2023-10-30', '2024-04-30', 'maintenance', 'Functional Zone'),
('Dumbbell Set (5-50kg)', 'Rogue', '2023-03-05', '2023-12-10', '2024-06-10', 'active', 'Free Weights');

-- Insert sample contact messages
INSERT INTO contact_messages (name, email, phone, subject, message, status) VALUES
('Alice Johnson', 'alice@email.com', '555-0123', 'Membership Inquiry', 'I would like to know more about your family plans...', 'read'),
('Michael Brown', 'michael@email.com', '555-0124', 'Personal Training', 'Looking for a trainer specialized in weight loss...', 'new'),
('Sarah Miller', 'sarah.m@email.com', '555-0125', 'Class Schedule', 'When are your evening yoga classes?', 'replied');

-- Create indexes for performance
CREATE INDEX idx_members_email ON gym_members(Email);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_stories_featured ON success_stories(is_featured, approved);
CREATE INDEX idx_classes_schedule ON classes(schedule);
CREATE INDEX idx_classes_type ON classes(class_type);
CREATE INDEX idx_payments_user ON payments(user_id, payment_date);
CREATE INDEX idx_bookings_user ON bookings(user_id, status);
CREATE INDEX idx_bookings_class ON bookings(class_id);