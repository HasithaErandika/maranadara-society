


# Maranadara Society Management System

A PHP-based web application designed for the Maranadara Society to manage members, loans, payments, incidents, and documents efficiently. The system provides role-based access with separate dashboards for admins and users, ensuring streamlined society operations.

---

## 📖 Table of Contents

- [Project Structure](#-project-structure)
- [Features](#-features)
- [Technologies Used](#-technologies-used)
- [Prerequisites](#-prerequisites)
- [Installation & Setup](#%EF%B8%8F-installation--setup)
  - [Local Setup (XAMPP)](#local-setup-xampp)
  - [HostGator Deployment](#hostgator-deployment)
- [Database Configuration](#-database-configuration)
- [Security](#-security)
- [Admin Login](#-admin-login)
- [Testing](#-testing)
- [Contribution](#-contribution)
- [License](#-license)

---

## 🗂 Project Structure

```
maranadara-society/
├── database/
│   └── dbStructure.sql     # Database structure
├── assets/
│   ├── css/              # Stylesheets
│   │   └── styles.css
│   ├── images/           # Image assets
│   └── js/               # JavaScript files
│       └── script.js
├── classes/
│   ├── Database.php      # Database connection class
│   ├── Document.php      # Document management
│   ├── Family.php        # Family details management
│   ├── Incident.php      # Incident reporting
│   ├── Loan.php          # Loan management
│   ├── Member.php        # Member management
│   ├── User.php          # User authentication
│   └── Payment.php       # Payment processing
├── config/
│   └── db_config.php     # Database connection configuration
├── includes/
│   ├── footer.php        # Common footer
│   ├── header.php        # Common header
│   └── sidepanel.php     # Admin/user side navigation
├── pages/
│   ├── login.php             # Login interface
│   ├── test_hash.php         # Password hashing test utility
│   ├── admin/
│   │   ├── add_member.php
│   │   ├── dashboard.php
│   │   ├── getloans.php
│   │   ├── incidents.php
│   │   ├── loans.php
│   │   ├── members.php
│   │   └── payments.php
│   └── user/
│       └── dashboard.php
├── .gitignore
├── .htaccess             # URL rewriting and security
├── index.php             # Landing page / route handler
├── login.php             # for users 
├── admin-login.php             # Only for admins 
├── README.md             # Project documentation
└── LICENSE               # MIT License
```

---

## 🚀 Features

- **Member Management**: Add, edit, view, and manage society members.
- **Loan Tracking**: Apply, track, and manage loans with interest and payment schedules.
- **Payment Processing**: Record and confirm membership fees, loan settlements, and other payments.
- **Incident Reporting**: Log and review incidents related to members.
- **Document Management**: Upload and store member-related documents.
- **Role-Based Access**: Separate dashboards for admins (full control) and users (limited access).
- **Dashboard Analytics**: Summarized statistics for quick insights.
- **Modular Design**: Reusable components for scalability and maintenance.

---

## 🛠 Technologies Used

- **PHP 8+**: Backend logic and server-side processing.
- **MySQL**: Database for storing members, loans, payments, etc.
- **HTML5 / CSS3 / JavaScript**: Frontend structure, styling, and interactivity.
- **Bootstrap**: Responsive design framework (optional).
- **Apache Server**: Via XAMPP (local) or HostGator (production).
- **phpMyAdmin**: Database management tool.

---

## 📋 Prerequisites

- **Local Development**:
  - XAMPP, MAMP, or Laragon (with PHP 8+ and MySQL).
  - Git for cloning the repository.
  - Web browser (Chrome, Firefox, etc.).
- **HostGator Hosting**:
  - HostGator shared or business hosting plan.
  - FTP client (e.g., FileZilla) or HostGator File Manager.
  - Access to cPanel for database setup.

---

## ⚙️ Installation & Setup

### Local Setup (XAMPP)

1. **Clone the Repository**:
   ```bash
   git clone https://github.com/yourusername/maranadara-society.git
   cd maranadara-society
   ```

2. **Set Up XAMPP**:
    - Install XAMPP and start Apache and MySQL.
    - Move the project folder to `C:\xampp\htdocs\maranadara-society`.

3. **Create the Database**:
    - Open `http://localhost/phpmyadmin`.
    - Create a database named `suramalr_maranadaraDB`.
    - Import `database/dbStructure.sql` to set up tables.

4. **Configure Database Credentials**:
    - Edit `config/db_config.php`:
      ```php
      <?php
      define('DB_HOST', 'localhost');
      define('DB_USER', 'root');
      define('DB_PASS', ''); // Default XAMPP MySQL password
      define('DB_NAME', 'suramalr_maranadaraDB');
      ?>
      ```

5. **Access the Application**:
    - Open `http://localhost/maranadara-society/` in your browser.

### HostGator Deployment

1. **Purchase a Hosting Plan**:
    - Choose a HostGator shared or business plan suitable for PHP/MySQL applications.

2. **Upload Files**:
    - Use an FTP client (e.g., FileZilla) or HostGator’s File Manager in cPanel.
    - Upload the entire `maranadara-society` folder to the `public_html` directory (or a subdirectory, e.g., `public_html/maranadara-society`).

3. **Create the Database**:
    - Log in to cPanel.
    - Navigate to **MySQL Databases**.
    - Create a database named `suramalr_maranadaraDB`.
    - Create a MySQL user and assign it to the database with full privileges.
    - Note the database name, username, and password.

4. **Import Database Structure**:
    - In cPanel, go to **phpMyAdmin**.
    - Select the `suramalr_maranadaraDB` database.
    - Import `database/dbStructure.sql` from your project.

5. **Update Database Configuration**:
    - Edit `config/db_config.php` with HostGator’s credentials:
      ```php
      <?php
      if (!defined('APP_START')) {
      exit('No direct script access allowed');}
      define('DB_HOST', 'localhost'); // HostGator typically uses 'localhost'
      define('DB_NAME', 'suramalr_maranadaraDB');
      ?>
      ```

6. **Test the Application**:
    - Access the site at `http://yourdomain.com/maranadara-society/` (or the subdirectory path).
    - Ensure `.htaccess` is configured for clean URLs if needed.

---

## 🗄 Database Configuration

The database `suramalr_maranadaraDB` includes the following tables:

```sql
-- Table: members
CREATE TABLE members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(10) UNIQUE NOT NULL, -- e.g., MS-001
    full_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    nic_number VARCHAR(20) UNIQUE NOT NULL,
    address TEXT NOT NULL,
    contact_number VARCHAR(15) NOT NULL,
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
    reporter_name VARCHAR(100) NOT NULL,
    reporter_member_id VARCHAR(10) NOT NULL,
    remarks TEXT,
    FOREIGN KEY (member_id) REFERENCES members(id)
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

-- Table: family_details
CREATE TABLE family_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    spouse_name VARCHAR(100),
    children_info TEXT,
    dependents_info TEXT,
    FOREIGN KEY (member_id) REFERENCES members(id)
);

-- Table: loans
CREATE TABLE loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    interest_rate DECIMAL(5, 2) NOT NULL,
    duration INT NOT NULL,
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

-- Table: payments
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    date DATE NOT NULL,
    payment_mode VARCHAR(20) NOT NULL,
    payment_type VARCHAR(50) NOT NULL,
    receipt_number VARCHAR(50),
    remarks TEXT,
    loan_id INT,
    is_confirmed BOOLEAN DEFAULT FALSE,
    confirmed_by VARCHAR(50),
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (loan_id) REFERENCES loans(id)
);
```

- Import `database/dbStructure.sql` to set up the schema.
- Ensure foreign key constraints are enabled in MySQL.

---

## 🔐 Security

- **Password Hashing**: Passwords are hashed using PHP’s `password_hash()` (see `test_hash.php`).
- **Session Authentication**: User access is controlled via secure sessions.
- **HTTPS**: Enforce HTTPS on HostGator using `.htaccess`:
  ```apache
  RewriteEngine On
  RewriteCond %{HTTPS} off
  RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
  ```
- **Input Validation**: Sanitize and validate all user inputs to prevent SQL injection and XSS.
- **File Uploads**: Restrict document uploads to safe file types and sizes.

---

## 🔑 Admin Login

- **Clarification**: Admin login uses application-specific credentials stored in the database (not MySQL database username/password).
- **Setup**:
    - Create an admin user in the `users` table (if applicable, not shown in provided schema).
    - Alternatively, use `test_hash.php` to generate a hashed password and manually insert an admin user.
- **Access**: Navigate to `http://yourdomain.com/maranadara-society/login.php` and log in with admin credentials.

---

## 🧪 Testing

- **Password Hashing**: Run `test_hash.php` to verify password hashing.
- **Functional Testing**:
    - Test login, member management, loan applications, and payment processing in-browser.
    - Verify admin and user dashboard functionality.
- **Future Improvements**:
    - Add PHPUnit for backend logic testing.
    - Implement automated frontend testing with tools like Selenium.

---

## 🙋 Contribution

To contribute:

1. Fork the repository.
2. Create a feature branch:
   ```bash
   git checkout -b feature/YourFeature
   ```
3. Commit changes:
   ```bash
   git commit -m 'Add some feature'
   ```
4. Push to the branch:
   ```bash
   git push origin feature/YourFeature
   ```
5. Submit a pull request.

---

## 📄 License

This project is licensed under the MIT License.

```text
MIT License

Copyright (c) 2025 Hasitha Erandika

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

**Note**: Replace `yourusername` and `yourdomain.com` with your actual GitHub username and domain. If you need further assistance with HostGator setup or database migration, let me know!
```

### Key Updates and Notes

1. **HostGator Deployment**: Added a dedicated section for deploying on HostGator, including file upload, database setup, and configuration steps.
2. **Database Clarification**: Corrected the database name to `suramalr_maranadaraDB` as per your input and included the full schema.
3. **Admin Login**: Addressed the confusion about admin login by clarifying that it uses application credentials, not MySQL credentials.
4. **File Extensions**: Updated file extensions (e.g., `styles.css`, `script.js`) for clarity, assuming standard naming conventions.
5. **Security**: Enhanced the security section with HTTPS enforcement and input validation tips.
6. **Structure**: Organized the README with a table of contents and clear sections for better navigation.
7. **Testing**: Kept testing lightweight but suggested future automation tools.

