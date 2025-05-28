-- Create the database
CREATE DATABASE IF NOT EXISTS maranadaraDB;
USE maranadara_db;

-- Table: members
CREATE TABLE members (
                         id INT AUTO_INCREMENT PRIMARY KEY,
                         member_id VARCHAR(10) UNIQUE NOT NULL, -- e.g., MS-001
                         full_name VARCHAR(100) NOT NULL,
                         date_of_birth DATE NOT NULL,
                         gender ENUM('Male', 'Female', 'Other') NOT NULL,
                         nic_number VARCHAR(20) UNIQUE NOT NULL,
                         address TEXT NOT NULL,
                         contact_number VARCHAR(15) NOT NULL, -- e.g., +94771234567
                         email VARCHAR(100),
                         occupation VARCHAR(100),
                         date_of_joining DATE NOT NULL,
                         membership_type ENUM('Individual', 'Family', 'Senior Citizen') NOT NULL,
                         contribution_amount DECIMAL(10, 2) NOT NULL,
                         payment_status ENUM('Active', 'Pending', 'Inactive') NOT NULL,
                         member_status ENUM('Active', 'Deceased', 'Resigned') NOT NULL
);

-- Table: incidents
CREATE TABLE incidents (
                           id INT AUTO_INCREMENT PRIMARY KEY,
                           incident_id VARCHAR(10) UNIQUE NOT NULL, -- e.g., INC-001
                           member_id INT NOT NULL,
                           incident_type VARCHAR(50) NOT NULL,
                           incident_datetime DATETIME NOT NULL,
                           remarks TEXT,
                           FOREIGN KEY (member_id) REFERENCES members(member_id)
);

-- Table: documents
CREATE TABLE documents (
                           id INT AUTO_INCREMENT PRIMARY KEY,
                           member_id INT NOT NULL,
                           document_type VARCHAR(50) NOT NULL,
                           file_path VARCHAR(255) NOT NULL,
                           notes TEXT,
                           upload_date DATE NOT NULL,
                           FOREIGN KEY (member_id) REFERENCES members(id)
);

CREATE TABLE IF NOT EXISTS family_details (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    member_id INT(11) NOT NULL,
    spouse_name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    spouse_dob DATE DEFAULT NULL,
    spouse_gender ENUM('Male', 'Female', 'Other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    INDEX (member_id)
) 



CREATE TABLE children (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    member_id INT(11) NOT NULL,
    name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    child_dob DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    INDEX (member_id)
);

-- Create dependents table
CREATE TABLE dependents (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    member_id INT(11) NOT NULL,
    name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    relationship VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    dependant_dob DATE DEFAULT NULL,
    dependant_address VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    INDEX (member_id)
);

-- Table: loans
CREATE TABLE loans (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       member_id INT NOT NULL,
                       amount DECIMAL(10, 2) NOT NULL,
                       interest_rate DECIMAL(5, 2) NOT NULL,
                       duration INT NOT NULL, -- Duration in months
                       monthly_payment DECIMAL(10, 2) NOT NULL,
                       total_payable DECIMAL(10, 2) NOT NULL,
                       start_date DATE NOT NULL,
                       end_date DATE NOT NULL,
                       status ENUM('Applied', 'Pending', 'Settled') NOT NULL DEFAULT 'Applied',
                       remarks TEXT,
                       is_confirmed BOOLEAN DEFAULT FALSE,
                       confirmed_by VARCHAR(50),
                       FOREIGN KEY (member_id) REFERENCES members(id)
);

-- Table: users
CREATE TABLE users (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       member_id INT,
                       username VARCHAR(50) UNIQUE NOT NULL,
                       password VARCHAR(255) NOT NULL, -- Hashed password
                       role VARCHAR(20) NOT NULL, -- user
                       FOREIGN KEY (member_id) REFERENCES members(id)
);

-- Table: payments
CREATE TABLE payments (
                          id INT AUTO_INCREMENT PRIMARY KEY,
                          member_id INT NOT NULL,
                          amount DECIMAL(10, 2) NOT NULL,
                          date DATE NOT NULL,
                          payment_mode VARCHAR(20) NOT NULL, -- e.g., Cash
                          payment_type VARCHAR(50) NOT NULL, -- e.g., Membership Fee, Loan Settlement
                          receipt_number VARCHAR(50),
                          remarks TEXT,
                          loan_id INT,
                          is_confirmed BOOLEAN DEFAULT FALSE,
                          confirmed_by VARCHAR(50),
                          FOREIGN KEY (member_id) REFERENCES members(id),
                          FOREIGN KEY (loan_id) REFERENCES loans(id)
);