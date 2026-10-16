<?php
ob_start(); // Start output buffering to prevent header errors
/**
 * GAS IMPORT - COMPLETE WITH TEAM PAGE & FUNCTIONAL MINE
 * (Includes updated Help page, white background, deposit/withdrawal reminders)
 * Optimized for all devices and hosting environments.
 */
session_start();

// ============================================
// ENVIRONMENT CHECK
// ============================================
if (!extension_loaded('mysqli')) {
    die('Error: MySQLi extension is required. Please enable it in your PHP configuration.');
}

// ============================================
// DATABASE CONFIGURATION
// ============================================
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'gas_power';

define('BANK_ACCOUNT', '1000528598173');
define('BANK_NAME', 'Commercial Bank of Ethiopia');
define('ACCOUNT_NAME', 'ABUSH TILAHUN');
define('MIN_DEPOSIT', 500);
define('MAX_DEPOSIT', 1000000);
define('MIN_WITHDRAWAL', 200);
define('REFERRAL_CODE', 'vwqegc');

function getDBConnection() {
    global $db_host, $db_user, $db_pass, $db_name;
    $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        // Try to create database if it doesn't exist
        $temp_conn = new mysqli($db_host, $db_user, $db_pass);
        if ($temp_conn->connect_error) {
            die("Database connection failed: " . $temp_conn->connect_error);
        }
        $temp_conn->query("CREATE DATABASE IF NOT EXISTS $db_name");
        $temp_conn->close();
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        if ($conn->connect_error) {
            die("Database connection failed: " . $conn->connect_error);
        }
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

$conn = getDBConnection();

// Helper function to safely add a unique index if it doesn't exist
function addUniqueIndexIfNotExists($conn, $table, $columns, $indexName) {
    $result = $conn->query("SHOW INDEX FROM $table WHERE Key_name = '$indexName'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE $table ADD UNIQUE INDEX $indexName ($columns)");
    }
}

// Remove duplicate products before adding unique constraint
$conn->query("CREATE TABLE IF NOT EXISTS products_clean AS SELECT MIN(id) id FROM products GROUP BY name");
$conn->query("DELETE FROM products WHERE id NOT IN (SELECT id FROM products_clean)");
$conn->query("DROP TABLE IF EXISTS products_clean");
addUniqueIndexIfNotExists($conn, 'products', 'name', 'unique_name');

// Remove duplicate tasks
$conn->query("CREATE TABLE IF NOT EXISTS tasks_clean AS SELECT MIN(id) id FROM tasks GROUP BY name, task_group");
$conn->query("DELETE FROM tasks WHERE id NOT IN (SELECT id FROM tasks_clean)");
$conn->query("DROP TABLE IF EXISTS tasks_clean");
addUniqueIndexIfNotExists($conn, 'tasks', 'name, task_group', 'unique_task');


// ============================================
// CREATE TABLES (if not exist)
// ============================================
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    phone VARCHAR(20) UNIQUE,
    email VARCHAR(100) UNIQUE,
    referral_code VARCHAR(20) UNIQUE,
    referred_by VARCHAR(20),
    balance DECIMAL(10,2) DEFAULT 100,
    recharge_wallet DECIMAL(10,2) DEFAULT 0.00,
    total_income DECIMAL(10,2) DEFAULT 100,
    day_income DECIMAL(10,2) DEFAULT 0.00,
    week_income DECIMAL(10,2) DEFAULT 0.00,
    month_income DECIMAL(10,2) DEFAULT 0.00,
    team_bonus DECIMAL(10,2) DEFAULT 0.00,
    vip_no VARCHAR(50) UNIQUE,
    check_in_points INT DEFAULT 0,
    last_check_in DATE,
    role ENUM('user', 'admin') DEFAULT 'user',
    status ENUM('active', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total_income DECIMAL(10,2) NOT NULL,
    day_income DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active'
)");

// Add total_days if missing
$check_column = $conn->query("SHOW COLUMNS FROM products LIKE 'total_days'");
if ($check_column && $check_column->num_rows == 0) {
    $conn->query("ALTER TABLE products ADD COLUMN total_days INT NOT NULL DEFAULT 60 AFTER day_income");
}

// Insert products with IGNORE (unique name prevents duplicates) – gas import products
// Updated image URLs as per user request
$conn->query("INSERT IGNORE INTO products (name, price, total_income, day_income, total_days, image_url) VALUES
    ('LPG Cylinder', 500.00, 2835.00, 53.00, 60, 'https://tse2.mm.bing.net/th/id/OIP.gZIks1QeOZ6OFyFLmbcB-wHaEc?pid=Api&h=220&P=0'),
    ('Natural Gas', 1000.00, 4235.00, 100.00, 60, 'https://tse1.mm.bing.net/th/id/OIP.HDBkaVGUbEa3pPPXm5VgXQHaEo?pid=Api&h=220&P=0'),
    ('Methane', 1500.00, 5596.00, 120.00, 60, 'https://tse2.mm.bing.net/th/id/OIP.QA71TUnicTCWnU02WbrhTQHaEK?pid=Api&h=220&P=0'),
    ('Propane', 2000.00, 6740.00, 170.00, 60, 'https://tse4.mm.bing.net/th/id/OIP.rQvgojsittF5zo1abAc3hgHaFc?pid=Api&h=220&P=0'),
    ('Butane', 3000.00, 12500.00, 210.00, 60, 'https://tse1.mm.bing.net/th/id/OIP.8nLwJ0l0Y3otyI4HE7yEZQHaDt?pid=Api&h=220&P=0'),
    ('CNG', 4000.00, 16800.00, 270.00, 60, 'https://tse1.mm.bing.net/th/id/OIP.EbXXnACCTZ1QTMDZWmTvYwHaEo?pid=Api&h=220&P=0'),
    ('LNG', 5000.00, 20000.00, 390.00, 60, 'https://tse4.mm.bing.net/th/id/OIP.QF4RmbRC1OH_6Kk_-BuRqAHaE8?pid=Api&h=220&P=0'),
    ('Industrial Gas', 10000.00, 30000.00, 1000.00, 60, 'https://thumbs.dreamstime.com/b/lng-tanker-vessel-carrying-liquefied-natural-gas-energy-transportation-cryogenic-storage-maritime-export-lindustrial-shipment-360536265.jpg'),
    ('Medical Oxygen', 20000.00, 75000.00, 2000.00, 60, 'https://img.freepik.com/premium-photo/export-technology-norway-oil-gas-technology-renewable-energy-systems_1291600-23725.jpg'),
    ('Helium', 35000.00, 120000.00, 5000.00, 60, 'https://tse4.mm.bing.net/th/id/OIP.fEAby5_QQQfes8uo2iJZ2wHaEO?pid=Api&h=220&P=0'),
    ('Argon', 9000.00, 19320.00, 850.00, 60, 'https://nai500.com/wp-content/uploads/2025/10/drilling-rig.png')");

$conn->query("CREATE TABLE IF NOT EXISTS investments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    total_income DECIMAL(10,2) DEFAULT 100,
    day_income DECIMAL(10,2) NOT NULL,
    daily_profit DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('active', 'completed') DEFAULT 'active',
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('deposit', 'withdrawal', 'purchase', 'profit', 'bonus', 'gift') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    sender_name VARCHAR(100),
    sender_phone VARCHAR(20),
    account_number VARCHAR(50),
    reference VARCHAR(100) UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS check_ins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    check_in_date DATE NOT NULL,
    points INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_checkin (user_id, check_in_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    target INT NOT NULL,
    reward DECIMAL(10,2) NOT NULL,
    task_group ENUM('group1', 'group2', 'center') DEFAULT 'group1',
    status ENUM('active', 'inactive') DEFAULT 'active'
)");

// Insert tasks with IGNORE – updated values as requested
$conn->query("INSERT IGNORE INTO tasks (name, target, reward, task_group) VALUES
    ('Invite 1 people', 1, 100.00, 'group1'),
    ('Invite 2 people', 2, 170.00, 'group1'),
    ('Invite 3 people', 3, 300.00, 'group1'),
    ('Invite 5 people', 5, 500.00, 'group1'),
    ('Invite 10 people', 10, 3000.00, 'group1'),
    ('Invite 15 people', 15, 4000.00, 'group1'),
    ('Invite 30 people', 30, 5000.00, 'group1'),
    ('Invest 500 ETB', 500, 10.00, 'group2'),
    ('Invest 1000 ETB', 1000, 15.00, 'group2'),
    ('Invest 2000 ETB', 2000, 20.00, 'group2'),
    ('Invest 1500 ETB daily', 1500, 25.00, 'group2'),
    ('Invest 3000 ETB daily', 3000, 30.00, 'group2'),
    ('Invest 4000 ETB daily', 4000, 35.00, 'group2'),
    ('Invest 5000 ETB daily', 5000, 50.00, 'group2'),
    ('Invest 10000 ETB daily', 10000, 55.00, 'group2'),
    ('Invest 20000 ETB daily', 20000, 60.00, 'group2'),
    ('Invest 35000 ETB daily', 35000, 100.00, 'group2'),
    ('Complete profile', 1, 50.00, 'center'),
    ('Join Telegram channel', 1, 30.00, 'center'),
    ('First deposit', 1, 100.00, 'center'),
    ('Invite a friend', 1, 200.00, 'center'),
    ('Spin wheel 3 times', 3, 75.00, 'center')");

$conn->query("CREATE TABLE IF NOT EXISTS user_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    task_id INT NOT NULL,
    progress INT DEFAULT 0,
    completed BOOLEAN DEFAULT FALSE,
    reward_claimed BOOLEAN DEFAULT FALSE,
    week_start DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_task_week (user_id, task_id, week_start)
)");

$conn->query("CREATE TABLE IF NOT EXISTS referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL,
    referred_id INT NOT NULL,
    level ENUM('A', 'B', 'C') DEFAULT 'B',
    commission_earned DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_referral (referrer_id, referred_id)
)");

$conn->query("CREATE TABLE IF NOT EXISTS financial_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    record_type ENUM('income', 'expense', 'withdrawal', 'deposit') NOT NULL,
    amount DECIMAL(5,2) NOT NULL,
    category VARCHAR(50),
    description TEXT,
    record_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255),
    content TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE,
    setting_value TEXT
)");

$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('exchange_rate', '1.00'),
    ('referral_code', 'vwqegc'),
    ('website_name', 'GAS IMPORT'),
    ('website_url', 'https://ethiopia.gasimport.net')");

// Create cash_gifts table for one-time gift
$conn->query("CREATE TABLE IF NOT EXISTS cash_gifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(5,2) NOT NULL,
    claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (user_id)
)");

// Create profit_claims table for daily profit claims
$conn->query("CREATE TABLE IF NOT EXISTS profit_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    investment_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(5,2) NOT NULL,
    claim_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (investment_id) REFERENCES investments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (investment_id, claim_date)
)");

$check_admin = $conn->query("SELECT id FROM users WHERE role = 'admin'");
if ($check_admin && $check_admin->num_rows == 0) {
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $vip = 'VIP' . rand(100000000, 999999999);
    $ref_code = 'ADMIN' . rand(1000, 9999);
    $admin_user = 'admin';
    $admin_full = 'Administrator';
    $admin_role = 'admin';
    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role, vip_no, referral_code) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssss", $admin_user, $admin_pass, $admin_full, $admin_role, $vip, $ref_code);
        $stmt->execute();
        $stmt->close();
    } else {
        die("Failed to prepare admin insert statement: " . $conn->error);
    }
}

// ============================================
// HANDLE FORM SUBMISSIONS
// ============================================

// Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $referral = isset($_POST['referral']) ? trim($_POST['referral']) : REFERRAL_CODE;
    
    $errors = [];
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    if (!preg_match('/^[0-9]{9,15}$/', $phone)) $errors[] = "Invalid phone number";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email";
    
    $vip_no = 'VIPO' . rand(100000000, 999999999);
    $ref_code = substr(md5(uniqid()), 0, 8);
    
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $full_phone = '+251' . $phone;
        
        $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, phone, email, vip_no, referral_code, referred_by) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            $_SESSION['error'] = "Database error: " . $conn->error;
            header("Location: " . $_SERVER['PHP_SELF'] . "?action=register");
            exit();
        }
        $stmt->bind_param("ssssssss", $username, $hashed, $full_name, $full_phone, $email, $vip_no, $ref_code, $referral);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $conn->query("INSERT INTO messages (user_id, title, content) 
                         VALUES ($user_id, 'Welcome to GAS IMPORT!', 'Thank you for joining. Start your gas investment journey today!')");
            
            // If referral code was provided, record the referral
            if (!empty($referral) && $referral != REFERRAL_CODE) {
                // Use prepared statement to avoid SQL injection
                $ref_stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
                if ($ref_stmt) {
                    $ref_stmt->bind_param("s", $referral);
                    $ref_stmt->execute();
                    $ref_result = $ref_stmt->get_result();
                    $referrer = $ref_result->fetch_assoc();
                    $ref_stmt->close();
                    if ($referrer) {
                        $referrer_id = $referrer['id'];
                        $conn->query("INSERT INTO referrals (referrer_id, referred_id, level) VALUES ($referrer_id, $user_id, 'B')");
                        
                        // GROUP 1 TASKS ARE NO LONGER UPDATED HERE – NOW DONE ON FIRST INVESTMENT
                    }
                }
            }
            
            $_SESSION['success'] = "Registration successful! Your VIP: $vip_no";
            header("Location: " . $_SERVER['PHP_SELF'] . "?action=login");
            exit();
        } else {
            $errors[] = "Registration failed - username or email may already exist";
        }
    }
    $_SESSION['errors'] = $errors;
    header("Location: " . $_SERVER['PHP_SELF'] . "?action=register");
    exit();
}

// Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['full_name'] = $row['full_name'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['balance'] = $row['balance'];
            $_SESSION['vip_no'] = $row['vip_no'];
            header("Location: " . $_SERVER['PHP_SELF'] . "?action=wealth");
            exit();
        }
    }
    $_SESSION['error'] = "Invalid username or password";
    header("Location: " . $_SERVER['PHP_SELF'] . "?action=login");
    exit();
}

// Daily Check-in
if (isset($_GET['check_in']) && isset($_SESSION['user_id'])) {
    $today = date('Y-m-d');
    $user_id = $_SESSION['user_id'];
    $check = $conn->query("SELECT id FROM check_ins WHERE user_id = $user_id AND check_in_date = '$today'");
    if ($check && $check->num_rows == 0) {
        $conn->query("INSERT INTO check_ins (user_id, check_in_date) VALUES ($user_id, '$today')");
        $conn->query("UPDATE users SET check_in_points = check_in_points + 1 WHERE id = $user_id");
        $_SESSION['success'] = "Check-in successful! +1 Point";
    } else {
        $_SESSION['error'] = "Already checked in today";
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?action=integral");
    exit();
}

// Daily Gift Claim (existing)
if (isset($_GET['claim_gift']) && isset($_SESSION['user_id'])) {
    $today = date('Y-m-d');
    $user_id = $_SESSION['user_id'];
    $check = $conn->query("SELECT id FROM check_ins WHERE user_id = $user_id AND check_in_date = '$today'");
    if ($check && $check->num_rows == 0) {
        $conn->query("INSERT INTO check_ins (user_id, check_in_date) VALUES ($user_id, '$today')");
        $conn->query("UPDATE users SET check_in_points = check_in_points + 5, balance = balance + 5 WHERE id = $user_id");
        $_SESSION['success'] = "Daily gift claimed! +5 points and 5 ETB!";
    } else {
        $_SESSION['error'] = "You already claimed your daily gift today";
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?action=task");
    exit();
}

// Cash Gift (one-time)
if (isset($_GET['claim_cash_gift']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $check = $conn->query("SELECT id FROM cash_gifts WHERE user_id = $user_id");
    if ($check && $check->num_rows == 0) {
        $amount = 5.00;
        $conn->query("INSERT INTO cash_gifts (user_id, amount) VALUES ($user_id, $amount)");
        $conn->query("UPDATE users SET balance = balance + $amount WHERE id = $user_id");
        $_SESSION['success'] = "Cash gift of $amount ETB claimed!";
    } else {
        $_SESSION['error'] = "You have already claimed your cash gift.";
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?action=help");
    exit();
}

// Deposit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_deposit']) && isset($_SESSION['user_id'])) {
    $amount = floatval($_POST['amount']);
    $method = $_POST['payment_method'];
    $transaction_id = trim($_POST['transaction_id']);
    $sender = trim($_POST['sender_name']);
    $phone = trim($_POST['sender_phone']);
    
    $errors = [];
    if ($amount < MIN_DEPOSIT) $errors[] = "Minimum deposit is " . MIN_DEPOSIT . " ETB";
    if ($amount > MAX_DEPOSIT) $errors[] = "Maximum deposit is " . MAX_DEPOSIT . " ETB";
    if (empty($transaction_id)) $errors[] = "Transaction ID required";
    if (empty($sender)) $errors[] = "Sender name required";
    
    if (empty($errors)) {
        $reference = 'DEP' . time() . rand(100, 999);
        $stmt = $conn->prepare("INSERT INTO transactions 
            (user_id, type, amount, status, payment_method, transaction_id, sender_name, sender_phone, reference) 
            VALUES (?, 'deposit', ?, 'pending', ?, ?, ?, ?, ?)");
        if (!$stmt) {
            $_SESSION['error'] = "Database error: " . $conn->error;
            header("Location: " . $_SERVER['PHP_SELF'] . "?action=deposit");
            exit();
        }
        $stmt->bind_param("idsssss", $_SESSION['user_id'], $amount, $method, $transaction_id, $sender, $phone, $reference);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Deposit request submitted! Reference: $reference";
        } else {
            $_SESSION['error'] = "Failed to submit deposit";
        }
    } else {
        $_SESSION['errors'] = $errors;
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?action=deposit");
    exit();
}

// Withdrawal (immediate balance deduction)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_withdrawal']) && isset($_SESSION['user_id'])) {
    $amount = floatval($_POST['amount']);
    $method = $_POST['withdrawal_method'];
    $account = trim($_POST['account_number']);
    
    $user = $conn->query("SELECT balance FROM users WHERE id = " . $_SESSION['user_id'])->fetch_assoc();
    if ($amount <= 100) {
        $_SESSION['error'] = "Invalid amount";
    } elseif ($amount > $user['balance']) {
        $_SESSION['error'] = "Insufficient balance";
    } elseif ($amount < MIN_WITHDRAWAL) {
        $_SESSION['error'] = "Minimum withdrawal is " . MIN_WITHDRAWAL . " ETB";
    } else {
        // Begin transaction to ensure consistency
        $conn->begin_transaction();
        try {
            // Deduct the full amount from user's balance immediately
            $conn->query("UPDATE users SET balance = balance - $amount WHERE id = " . $_SESSION['user_id']);
            
            $reference = 'WDR' . time() . rand(100, 999);
            $desc = "Withdrawal request to $method - Account: $account (fee 10% deducted)";
            
            $sql = "INSERT INTO transactions 
                    (user_id, type, amount, status, payment_method, reference, description) 
                    VALUES (?, 'withdrawal', ?, 'pending', ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
            }
            $stmt->bind_param("idsss", $_SESSION['user_id'], $amount, $method, $reference, $desc);
            if (!$stmt->execute()) {
                throw new Exception("Withdrawal insert failed: " . $stmt->error);
            }
            $stmt->close();
            
            $conn->commit();
            $_SESSION['success'] = "Withdrawal request submitted! Amount deducted from your balance. Reference: $reference";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Withdrawal failed: " . $e->getMessage();
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?action=withdrawal");
    exit();
}

// Purchase Gas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_gas']) && isset($_SESSION['user_id'])) {
    $product_id = intval($_POST['product_id']);
    $product = $conn->query("SELECT * FROM products WHERE id = $product_id");
    if (!$product) {
        $_SESSION['error'] = "Product query failed: " . $conn->error;
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=home");
        exit();
    }
    $product = $product->fetch_assoc();
    if ($product) {
        $amount = $product['price'];
        $user = $conn->query("SELECT balance FROM users WHERE id = " . $_SESSION['user_id'])->fetch_assoc();
        if ($amount <= $user['balance']) {
            $conn->query("UPDATE users SET balance = balance - $amount WHERE id = " . $_SESSION['user_id']);
            $stmt = $conn->prepare("INSERT INTO investments (user_id, product_id, product_name, amount, total_income, day_income, daily_profit) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                $_SESSION['error'] = "Prepare failed: " . $conn->error;
                header("Location: " . $_SERVER['PHP_SELF'] . "?action=home");
                exit();
            }
            $stmt->bind_param("iisdddd", $_SESSION['user_id'], $product_id, $product['name'], $amount, $product['total_income'], $product['day_income'], $product['day_income']);
            if (!$stmt->execute()) {
                $_SESSION['error'] = "Investment insert failed: " . $stmt->error;
                header("Location: " . $_SERVER['PHP_SELF'] . "?action=home");
                exit();
            }
            $conn->query("UPDATE users SET total_income = total_income + {$product['total_income']}, day_income = day_income + {$product['day_income']} WHERE id = " . $_SESSION['user_id']);
            $_SESSION['balance'] = $user['balance'] - $amount;
            $_SESSION['success'] = "Gas purchased successfully!";
            
            // Update group2 tasks (Invest 500/1000/2000 ETB)
            $week_start = date('Y-m-d', strtotime('monday this week'));
            $invest_tasks = $conn->query("SELECT id, target FROM tasks WHERE task_group = 'group2' AND name LIKE 'Invest%' AND name NOT LIKE '% daily' AND status='active'");
            if ($invest_tasks) {
                while ($task = $invest_tasks->fetch_assoc()) {
                    // Get total invested this week
                    $total_invested = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM investments WHERE user_id = " . $_SESSION['user_id'] . " AND purchase_date >= '$week_start'")->fetch_assoc()['total'];
                    $progress = $conn->query("SELECT id, progress, completed FROM user_tasks WHERE user_id = " . $_SESSION['user_id'] . " AND task_id = {$task['id']} AND week_start = '$week_start'")->fetch_assoc();
                    if ($progress) {
                        if (!$progress['completed']) {
                            $new_progress = min($total_invested, $task['target']);
                            $completed = ($new_progress >= $task['target']) ? 1 : 0;
                            $conn->query("UPDATE user_tasks SET progress = $new_progress, completed = $completed WHERE id = {$progress['id']}");
                        }
                    } else {
                        $progress_val = min($total_invested, $task['target']);
                        $completed = ($progress_val >= $task['target']) ? 1 : 0;
                        $conn->query("INSERT INTO user_tasks (user_id, task_id, progress, completed, week_start) VALUES (" . $_SESSION['user_id'] . ", {$task['id']}, $progress_val, $completed, '$week_start')");
                    }
                }
            }

            // --- Update group1 tasks for referrer if this is the referred user's first investment ---
            $referred_by = $conn->query("SELECT referred_by FROM users WHERE id = " . $_SESSION['user_id'])->fetch_assoc()['referred_by'];
            if (!empty($referred_by) && $referred_by != REFERRAL_CODE) {
                // Use prepared statement to avoid SQL injection
                $ref_stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
                if ($ref_stmt) {
                    $ref_stmt->bind_param("s", $referred_by);
                    $ref_stmt->execute();
                    $ref_result = $ref_stmt->get_result();
                    $referrer = $ref_result->fetch_assoc();
                    $ref_stmt->close();
                    if ($referrer) {
                        $referrer_id = $referrer['id'];
                        // Check if this is the first investment for the referred user
                        $inv_count = $conn->query("SELECT COUNT(*) as cnt FROM investments WHERE user_id = " . $_SESSION['user_id'])->fetch_assoc()['cnt'];
                        if ($inv_count == 1) { // after insertion, count includes this one
                            $week_start = date('Y-m-d', strtotime('monday this week'));
                            // Get all active group1 tasks
                            $group1_tasks = $conn->query("SELECT id, target FROM tasks WHERE task_group = 'group1' AND status='active'");
                            if ($group1_tasks) {
                                while ($task = $group1_tasks->fetch_assoc()) {
                                    // Get current progress for this task for referrer this week
                                    $progress_row = $conn->query("SELECT id, progress, completed FROM user_tasks WHERE user_id = $referrer_id AND task_id = {$task['id']} AND week_start = '$week_start'")->fetch_assoc();
                                    if ($progress_row) {
                                        if (!$progress_row['completed']) {
                                            $new_progress = $progress_row['progress'] + 1;
                                            $completed = ($new_progress >= $task['target']) ? 1 : 0;
                                            $conn->query("UPDATE user_tasks SET progress = $new_progress, completed = $completed WHERE id = {$progress_row['id']}");
                                        }
                                    } else {
                                        $progress = 1;
                                        $completed = ($progress >= $task['target']) ? 1 : 0;
                                        $conn->query("INSERT INTO user_tasks (user_id, task_id, progress, completed, week_start) VALUES ($referrer_id, {$task['id']}, $progress, $completed, '$week_start')");
                                    }
                                }
                            }
                        }
                    }
                }
            }
            // -----------------------------------------------------------------------------------------

        } else {
            $_SESSION['error'] = "Insufficient balance";
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?action=home");
    exit();
}

// Claim Task Reward – UPDATED with robust error handling
if (isset($_GET['claim_task']) && isset($_SESSION['user_id'])) {
    $task_id = intval($_GET['claim_task']);
    $user_id = $_SESSION['user_id'];
    
    // Get task details
    $task_query = $conn->query("SELECT * FROM tasks WHERE id = $task_id");
    if (!$task_query) {
        $_SESSION['error'] = "Task query failed: " . $conn->error;
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=task");
        exit();
    }
    $task = $task_query->fetch_assoc();
    if (!$task) {
        $_SESSION['error'] = "Task not found.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=task");
        exit();
    }
    
    // Get user's progress for this week
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $user_task_query = $conn->query("SELECT * FROM user_tasks WHERE user_id = $user_id AND task_id = $task_id AND week_start = '$week_start'");
    if (!$user_task_query) {
        $_SESSION['error'] = "User task query failed: " . $conn->error;
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=task");
        exit();
    }
    $user_task = $user_task_query->fetch_assoc();
    if (!$user_task) {
        $_SESSION['error'] = "No progress for this task.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=task");
        exit();
    }
    
    if (!$user_task['completed']) {
        $_SESSION['error'] = "Task not completed yet.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=task");
        exit();
    }
    
    if ($user_task['reward_claimed']) {
        $_SESSION['error'] = "Reward already claimed.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=task");
        exit();
    }
    
    // Claim reward
    $reward = $task['reward'];
    $conn->begin_transaction();
    try {
        // Update user balance
        if (!$conn->query("UPDATE users SET balance = balance + $reward WHERE id = $user_id")) {
            throw new Exception("Balance update failed: " . $conn->error);
        }
        // Mark reward as claimed
        if (!$conn->query("UPDATE user_tasks SET reward_claimed = TRUE WHERE id = {$user_task['id']}")) {
            throw new Exception("Task update failed: " . $conn->error);
        }
        // Record transaction
        $ref = 'TASK' . time();
        if (!$conn->query("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES ($user_id, 'bonus', $reward, 'completed', '$ref', 'Task reward: {$task['name']}')")) {
            throw new Exception("Transaction insert failed: " . $conn->error);
        }
        $conn->commit();
        $_SESSION['success'] = "Reward of $reward ETB claimed!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Claim failed: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?action=task");
    exit();
}

// Claim Daily Profit – UPDATED with robust error handling
if (isset($_GET['claim_profit']) && isset($_SESSION['user_id'])) {
    $investment_id = intval($_GET['claim_profit']);
    $user_id = $_SESSION['user_id'];

    // Get investment details
    $inv_query = $conn->query("SELECT * FROM investments WHERE id = $investment_id AND user_id = $user_id");
    if (!$inv_query) {
        $_SESSION['error'] = "Investment query failed: " . $conn->error;
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=orders");
        exit();
    }
    $inv = $inv_query->fetch_assoc();
    if (!$inv) {
        $_SESSION['error'] = "Investment not found.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=orders");
        exit();
    }

    // Check if investment is still active
    if ($inv['status'] != 'active') {
        $_SESSION['error'] = "This investment is no longer active.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=orders");
        exit();
    }

    // Check if already claimed in the last 24 hours
    $last_claim_query = $conn->query("SELECT claim_date FROM profit_claims WHERE investment_id = $investment_id ORDER BY claim_date DESC LIMIT 1");
    if (!$last_claim_query) {
        $_SESSION['error'] = "Failed to check last claim: " . $conn->error;
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=orders");
        exit();
    }
    $last_claim = $last_claim_query->fetch_assoc();
    if ($last_claim) {
        $last_time = strtotime($last_claim['claim_date']);
        if (time() - $last_time < 86400) { // 24 hours
            $next_claim = $last_time + 86400;
            $hours_left = ceil(($next_claim - time()) / 3600);
            $_SESSION['error'] = "You can claim again in about $hours_left hours.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?action=orders");
            exit();
        }
    }

    // Check if investment has expired (purchase_date + total_days)
    $product_query = $conn->query("SELECT total_days FROM products WHERE id = " . $inv['product_id']);
    if (!$product_query) {
        $_SESSION['error'] = "Product query failed: " . $conn->error;
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=orders");
        exit();
    }
    $product = $product_query->fetch_assoc();
    if (!$product) {
        $_SESSION['error'] = "Product not found for this investment.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=orders");
        exit();
    }
    $expiry_date = date('Y-m-d H:i:s', strtotime($inv['purchase_date'] . " + " . $product['total_days'] . " days"));
    if (strtotime($expiry_date) < time()) {
        // Mark investment as completed
        if (!$conn->query("UPDATE investments SET status = 'completed' WHERE id = $investment_id")) {
            $_SESSION['error'] = "Failed to mark investment as completed: " . $conn->error;
            header("Location: " . $_SERVER['PHP_SELF'] . "?action=orders");
            exit();
        }
        $_SESSION['error'] = "This investment has expired.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=orders");
        exit();
    }

    // Claim profit
    $profit = $inv['day_income'];
    $conn->begin_transaction();
    try {
        // Update balance
        if (!$conn->query("UPDATE users SET balance = balance + $profit WHERE id = $user_id")) {
            throw new Exception("Balance update failed: " . $conn->error);
        }
        // Record profit claim
        if (!$conn->query("INSERT INTO profit_claims (investment_id, user_id, amount) VALUES ($investment_id, $user_id, $profit)")) {
            throw new Exception("Profit claim insert failed: " . $conn->error);
        }
        // Record transaction
        $ref = 'PROFIT' . time();
        if (!$conn->query("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES ($user_id, 'profit', $profit, 'completed', '$ref', 'Daily profit from {$inv['product_name']}')")) {
            throw new Exception("Transaction insert failed: " . $conn->error);
        }
        // Update user's total income
        if (!$conn->query("UPDATE users SET total_income = total_income + $profit, day_income = day_income + $profit WHERE id = $user_id")) {
            throw new Exception("Income update failed: " . $conn->error);
        }

        // Update group2 daily investment tasks
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $tasks_query = $conn->query("SELECT id, target FROM tasks WHERE task_group = 'group2' AND name LIKE '% daily' AND status='active'");
        if (!$tasks_query) {
            throw new Exception("Tasks query failed: " . $conn->error);
        }
        while ($task = $tasks_query->fetch_assoc()) {
            // Get current progress
            $progress_query = $conn->query("SELECT progress, completed FROM user_tasks WHERE user_id = $user_id AND task_id = {$task['id']} AND week_start = '$week_start'");
            if (!$progress_query) {
                throw new Exception("Progress query failed: " . $conn->error);
            }
            $progress_row = $progress_query->fetch_assoc();
            if ($progress_row) {
                if (!$progress_row['completed']) {
                    $new_progress = $progress_row['progress'] + $profit;
                    if ($new_progress >= $task['target']) {
                        $new_progress = $task['target'];
                        if (!$conn->query("UPDATE user_tasks SET progress = $new_progress, completed = TRUE WHERE user_id = $user_id AND task_id = {$task['id']} AND week_start = '$week_start'")) {
                            throw new Exception("Task update failed: " . $conn->error);
                        }
                    } else {
                        if (!$conn->query("UPDATE user_tasks SET progress = $new_progress WHERE user_id = $user_id AND task_id = {$task['id']} AND week_start = '$week_start'")) {
                            throw new Exception("Task update failed: " . $conn->error);
                        }
                    }
                }
            } else {
                $progress = min($profit, $task['target']);
                $completed = ($progress >= $task['target']) ? 1 : 0;
                if (!$conn->query("INSERT INTO user_tasks (user_id, task_id, progress, completed, week_start) VALUES ($user_id, {$task['id']}, $progress, $completed, '$week_start')")) {
                    throw new Exception("Task insert failed: " . $conn->error);
                }
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Daily profit of $profit ETB claimed!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Claim failed: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?action=orders");
    exit();
}

// Wheel of Fortune Spin
if (isset($_GET['spin_wheel']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user = $conn->query("SELECT check_in_points FROM users WHERE id = $user_id")->fetch_assoc();
    if ($user && $user['check_in_points'] >= 10) {
        $prizes = [5, 10, 20, 50, 100, 0];
        $prize = $prizes[array_rand($prizes)];
        $conn->query("UPDATE users SET check_in_points = check_in_points - 10, balance = balance + $prize WHERE id = $user_id");
        $conn->query("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES ($user_id, 'bonus', $prize, 'completed', 'WHEEL' . UNIX_TIMESTAMP(), 'Wheel of fortune prize')");
        $_SESSION['success'] = "You won $prize ETB!";
    } else {
        $_SESSION['error'] = "You need at least 10 points to spin";
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?action=wheel");
    exit();
}

// Language change
if (isset($_GET['set_lang'])) {
    $_SESSION['lang'] = $_GET['set_lang'];
    header("Location: " . $_SERVER['PHP_SELF'] . "?action=language");
    exit();
}

// Profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile']) && isset($_SESSION['user_id'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    
    $user = $conn->query("SELECT password FROM users WHERE id = " . $_SESSION['user_id'])->fetch_assoc();
    if ($user && password_verify($current, $user['password'])) {
        if (!empty($new)) {
            if (strlen($new) < 6) {
                $_SESSION['error'] = "New password must be at least 6 characters";
            } else {
                $hashed = password_hash($new, PASSWORD_DEFAULT);
                $conn->query("UPDATE users SET full_name = '$full_name', email = '$email', password = '$hashed' WHERE id = " . $_SESSION['user_id']);
                $_SESSION['full_name'] = $full_name;
                $_SESSION['success'] = "Profile updated";
            }
        } else {
            $conn->query("UPDATE users SET full_name = '$full_name', email = '$email' WHERE id = " . $_SESSION['user_id']);
            $_SESSION['full_name'] = $full_name;
            $_SESSION['success'] = "Profile updated";
        }
    } else {
        $_SESSION['error'] = "Current password is incorrect";
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?action=settings");
    exit();
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Admin Approvals
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    if (isset($_GET['approve_deposit'])) {
        $id = intval($_GET['approve_deposit']);
        $txn = $conn->query("SELECT * FROM transactions WHERE id = $id")->fetch_assoc();
        if ($txn && $txn['status'] == 'pending') {
            $conn->query("UPDATE users SET balance = balance + {$txn['amount']} WHERE id = {$txn['user_id']}");
            $conn->query("UPDATE transactions SET status = 'completed' WHERE id = $id");
            $_SESSION['success'] = "Deposit approved";
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=admin");
        exit();
    }
    if (isset($_GET['reject_deposit'])) {
        $id = intval($_GET['reject_deposit']);
        $txn = $conn->query("SELECT * FROM transactions WHERE id = $id")->fetch_assoc();
        if ($txn && $txn['status'] == 'pending') {
            // If it's a withdrawal, we need to refund the amount back to user (since it was deducted at request)
            if ($txn['type'] == 'withdrawal') {
                $conn->query("UPDATE users SET balance = balance + {$txn['amount']} WHERE id = {$txn['user_id']}");
            }
            $conn->query("UPDATE transactions SET status = 'failed' WHERE id = $id");
            $_SESSION['success'] = "Transaction rejected and funds refunded (if applicable)";
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=admin");
        exit();
    }
    if (isset($_GET['approve_withdrawal'])) {
        $id = intval($_GET['approve_withdrawal']);
        $txn = $conn->query("SELECT * FROM transactions WHERE id = $id")->fetch_assoc();
        if ($txn && $txn['status'] == 'pending') {
            // Do NOT deduct again – it was already deducted at request. Just mark as completed.
            $conn->query("UPDATE transactions SET status = 'completed' WHERE id = $id");
            $_SESSION['success'] = "Withdrawal approved";
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=admin");
        exit();
    }
}

$action = isset($_GET['action']) ? $_GET['action'] : 'home';
$user_data = null;
$transactions = null;
$investments = null;
$check_ins = null;
$referrals = null;
$unread_count = 0;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $result = $conn->query("SELECT * FROM users WHERE id = $user_id");
    if ($result && $result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $_SESSION['balance'] = $user_data['balance'];
        
        $transactions = $conn->query("SELECT * FROM transactions WHERE user_id = $user_id ORDER BY created_at DESC");
        $investments = $conn->query("SELECT * FROM investments WHERE user_id = $user_id ORDER BY purchase_date DESC");
        $check_ins = $conn->query("SELECT DAY(check_in_date) as day FROM check_ins WHERE user_id = $user_id AND DATE_FORMAT(check_in_date, '%Y-%m') = '" . date('Y-m') . "'");
        $referrals = $conn->query("SELECT u.username, u.full_name, u.created_at, r.level FROM referrals r JOIN users u ON r.referred_id = u.id WHERE r.referrer_id = $user_id");
        $unread = $conn->query("SELECT COUNT(*) as count FROM messages WHERE user_id = $user_id AND is_read = 0");
        $unread_count = ($unread && $unread->num_rows > 0) ? $unread->fetch_assoc()['count'] : 0;
    } else {
        session_destroy();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

$settings = [];
$settings_result = $conn->query("SELECT * FROM settings");
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$lang = $_SESSION['lang'] ?? 'en';
$lang_text = [
    'en' => ['Home' => 'Home', 'Wealth' => 'Wealth', 'Team' => 'Team', 'Task' => 'Task', 'Mine' => 'Mine'],
    'am' => ['Home' => 'መነሻ', 'Wealth' => 'ሀብት', 'Team' => 'ቡድን', 'Task' => 'ተግባር', 'Mine' => 'የእኔ'],
    'zh' => ['Home' => '主页', 'Wealth' => '财富', 'Team' => '团队', 'Task' => '任务', 'Mine' => '我的']
];
$t = $lang_text[$lang] ?? $lang_text['en'];

// Helper function to check if current time is within withdrawal window (10am-8pm Ethiopia)
function isWithinWithdrawalHours() {
    date_default_timezone_set('Africa/Addis_Ababa');
    $hour = (int)date('H');
    return ($hour >= 10 && $hour < 20);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($settings['website_name'] ?? 'GAS IMPORT'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        
        body {
            background: #ffffff;
            min-height: 100vh;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            padding: 15px;
            background: #ffffff;
            border-radius: 20px 20px 0 0;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.1);
        }
        
        .app-header {
            background: #32CD32; /* green (replaced gold) */
            color: white;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .logo {
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
        }
        .logo span {
            color: #FFB6C1; /* pink */
            font-size: 0.9rem;
            margin-left: 5px;
        }
        .header-icons {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .header-icons a {
            color: white;
            position: relative;
        }
        .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            font-size: 0.6rem;
            padding: 2px 5px;
            border-radius: 10px;
        }
        .live-time {
            background: rgba(0,0,0,0.2);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .live-time i {
            font-size: 1rem;
        }
        .withdrawal-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 5px;
        }
        .indicator-open {
            background: #32CD32; /* lime */
            box-shadow: 0 0 5px #32CD32;
        }
        .indicator-closed {
            background: #dc3545;
            box-shadow: 0 0 5px #dc3545;
        }
        
        .nav-tabs {
            display: flex;
            background: #FFB6C1; /* pink */
            border-radius: 12px;
            padding: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
        }
        .nav-tab {
            flex: 1 1 auto;
            text-align: center;
            padding: 12px 5px;
            color: #333;
            text-decoration: none;
            font-size: 0.9rem;
            border-radius: 8px;
            transition: all 0.3s;
            min-width: 60px;
        }
        .nav-tab i {
            display: block;
            font-size: 1.2rem;
            margin-bottom: 3px;
            color: #32CD32; /* green (replaced gold) */
        }
        .nav-tab.active {
            background: #32CD32; /* green (replaced gold) */
            color: white;
        }
        .nav-tab.active i {
            color: white;
        }
        
        .card {
            background: #FFB6C1; /* pink */
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.83);
            color: #333;
        }
        .gold-card {
            background: linear-gradient(135deg, #32CD32, #228B22); /* green gradient */
            color: #000;
        }
        .gold-card .stat-label,
        .gold-card .stat-value {
            color: #000;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        .stat-item {
            background: #FFB6C1; /* pink */
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
            color: #333;
            border: 1px solid #FF69B4;
        }
        .stat-item:hover {
            background: #FF69B4;
            transform: translateY(-2px);
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #32CD32; /* green (replaced gold) */
            word-break: break-word;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #333;
            margin-top: 5px;
        }
        
        /* Product grid - updated for image above text */
        .products-grid { display: grid; gap: 15px; }
        .product-card {
            background: #FFB6C1; /* pink */
            border-radius: 12px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 1px solid #FF69B4;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
        }
        .product-image {
            width: 100%;
            max-width: 200px;
            height: auto;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .product-info { width: 100%; }
        .product-info h3 { color: #32CD32; margin-bottom: 5px; font-size: 1.1rem; } /* green */
        .product-price { font-size: 1.2rem; font-weight: 700; color: #333; }
        .product-income { color: #32CD32; font-size: 0.9rem; } /* already lime, keep as is */
        .product-card form {
            width: 100%;
        }
        .buy-btn {
            background: #32CD32; /* green (replaced gold) */
            color: #333;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }
        .buy-btn:hover {
            background: #228B22;
        }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #32CD32; font-weight: 500; } /* green */
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 8px;
            color: #333;
        }
        .btn {
            width: 100%;
            padding: 15px;
            background: #32CD32; /* green (replaced gold) */
            color: #333;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn:hover {
            background: #228B22;
        }
        .btn-outline {
            background: transparent;
            border: 2px solid #32CD32; /* green */
            color: #32CD32; /* green */
        }
        .btn-outline:hover {
            background: #32CD32; /* green */
            color: #333;
        }
        
        .table { width: 100%; border-collapse: collapse; overflow-x: auto; display: block; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; color: #333; white-space: nowrap; }
        .table th { color: #32CD32; font-weight: 600; background: #FF69B4; } /* green */
        
        .badge-pending { background: #FFC107; color: #000; padding: 3px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; white-space: nowrap; }
        .badge-completed { background: #32CD32; color: #fff; padding: 3px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; white-space: nowrap; }
        .badge-failed { background: #dc3545; color: #fff; padding: 3px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; white-space: nowrap; }
        
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            margin: 15px 0;
        }
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #FFB6C1;
            border-radius: 8px;
            font-size: 0.8rem;
            color: #333;
        }
        .calendar-day.checked { background: #32CD32; color: #000; font-weight: 600; } /* green */
        .calendar-day.today { border: 2px solid #32CD32; } /* green */
        
        .qr-container { text-align: center; padding: 20px; background: #FFB6C1; border-radius: 10px; margin: 15px 0; }
        .qr-placeholder {
            width: 200px; height: 200px; background: #e9ecef; margin: 0 auto;
            display: flex; align-items: center; justify-content: center; color: #666; font-family: monospace;
        }
        
        .alert {
            padding: 12px; border-radius: 8px; margin-bottom: 15px; text-align: center;
        }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .errors { list-style: none; margin-bottom: 15px; }
        .errors li {
            background: #f8d7da; border: 1px solid #f5c6cb;
            color: #721c24; padding: 10px; margin-bottom: 5px; border-radius: 5px;
        }
        
        .support-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #FFB6C1;
            margin: 10px 0;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #FF69B4;
            flex-wrap: wrap;
            gap: 10px;
        }
        .support-link a {
            color: #32CD32; /* green */
            text-decoration: none;
            word-break: break-all;
            flex: 1;
            margin: 0 10px;
        }
        .copy-btn {
            background: #32CD32; /* green */
            color: #333;
            border: none;
            padding: 5px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .copy-btn:hover {
            background: #228B22;
        }

        .home-image-container {
            width: 100%;
            margin-bottom: 20px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            background: transparent;
        }
        .home-image {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .home-telegram-btn {
            display: inline-block;
            background: #32CD32; /* green */
            color: #333;
            padding: 12px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            margin: 15px 0;
            box-shadow: 0 4px 10px rgba(50,205,50,0.3);
            transition: 0.3s;
        }
        .home-telegram-btn:hover {
            background: #228B22;
            transform: scale(1.05);
        }
        .home-telegram-btn i {
            margin-right: 8px;
        }
        
        /* Non-functional Product List button */
        .product-list-btn {
            background: #dc3545; /* red */
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            margin: 10px 0;
            cursor: default;
            opacity: 0.8;
            text-align: center;
            display: inline-block;
        }
        
        .reminder-box {
            background: #fff3cd;
            border-left: 4px solid #FFC107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            color: #856404;
            font-size: 0.9rem;
        }
        .reminder-box h4 {
            margin-bottom: 8px;
            color: #856404;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .reminder-box ul {
            list-style-type: disc;
            padding-left: 20px;
            margin: 5px 0;
        }
        .reminder-box li {
            margin-bottom: 5px;
        }

        /* Referral program box on Team page */
        .referral-info {
            background: #fff3cd;
            border-left: 4px solid #FFC107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            color: #856404;
        }
        .referral-info h4 {
            color: #856404;
            margin-bottom: 10px;
        }
        .referral-info ol {
            padding-left: 20px;
            margin: 10px 0;
        }
        .referral-info li {
            margin-bottom: 8px;
        }
        .referral-info .kind-reminder {
            margin-top: 15px;
            font-style: italic;
        }

        /* Responsive adjustments for very small screens */
        @media (max-width: 400px) {
            .nav-tab {
                font-size: 0.8rem;
                padding: 8px 2px;
            }
            .nav-tab i {
                font-size: 1rem;
            }
            .stat-value {
                font-size: 1.2rem;
            }
            .product-card {
                flex-direction: column;
                text-align: center;
            }
            .product-image {
                max-width: 150px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="app-header">
            <div class="logo">
                <?php echo htmlspecialchars($settings['website_name'] ?? 'GAS IMPORT'); ?>
                <span><?php echo htmlspecialchars($settings['website_url'] ?? ''); ?></span>
            </div>
            <div class="header-icons">
                <div class="live-time" id="liveClock">
                    <i class="far fa-clock"></i>
                    <span id="timeDisplay"></span>
                    <span class="withdrawal-indicator <?php echo isWithinWithdrawalHours() ? 'indicator-open' : 'indicator-closed'; ?>" title="Withdrawal window: 10am-8pm"></span>
                </div>
                <a href="?action=messages"><i class="fas fa-envelope"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?action=settings"><i class="fas fa-cog"></i></a>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['errors'])): ?>
            <ul class="errors">
                <?php foreach ($_SESSION['errors'] as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; unset($_SESSION['errors']); ?>
            </ul>
        <?php endif; ?>

        <?php if (!isset($_SESSION['user_id'])): ?>
            <!-- Guest pages -->
            <?php if ($action === 'login'): ?>
                <div class="card">
                    <h2 style="text-align: center; color: #32CD32; margin-bottom: 20px;">Login</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label>Username or Email</label>
                            <input type="text" name="username" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" required>
                        </div>
                        <button type="submit" name="login" class="btn">Login</button>
                    </form>
                    <p style="text-align: center; margin-top: 15px;">
                        Don't have account? <a href="?action=register" style="color: #32CD32;">Register</a>
                    </p>
                </div>
            <?php elseif ($action === 'register'): 
                // Pre-fill referral code from URL if provided
                $ref_from_url = isset($_GET['ref']) ? trim($_GET['ref']) : '';
            ?>
                <div class="card">
                    <h2 style="text-align: center; color: #32CD32; margin-bottom: 20px;">Register</h2>
                    <form method="POST">
                        <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
                        <div class="form-group"><label>Full Name</label><input type="text" name="full_name" required></div>
                        <div class="form-group"><label>Phone (9 digits)</label><input type="tel" name="phone" placeholder="912345678" required></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                        <div class="form-group"><label>Password (min 6 chars)</label><input type="password" name="password" required></div>
                        <div class="form-group"><label>Referral Code (Optional)</label>
                            <input type="text" name="referral" value="<?php echo htmlspecialchars($ref_from_url ?: REFERRAL_CODE); ?>">
                        </div>
                        <button type="submit" name="register" class="btn">Register</button>
                    </form>
                    <p style="text-align: center; margin-top: 15px;">Already have account? <a href="?action=login" style="color: #32CD32;">Login</a></p>
                </div>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: 40px;">
                    <i class="fas fa-gas-pump" style="font-size: 4rem; color: #32CD32; margin-bottom: 20px;"></i>
                    <h2>Welcome to GAS IMPORT</h2>
                    <p style="color: #333; margin: 20px 0;">Invest in premium gas import products</p>
                    <a href="?action=login" class="btn" style="margin-bottom: 10px;">Login</a>
                    <a href="?action=register" class="btn btn-outline">Register</a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Logged in users - top tabs -->
            <div class="nav-tabs">
                <a href="?action=home" class="nav-tab <?php echo $action === 'home' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> <?php echo htmlspecialchars($t['Home']); ?>
                </a>
                <a href="?action=wealth" class="nav-tab <?php echo $action === 'wealth' ? 'active' : ''; ?>">
                    <i class="fas fa-coins"></i> <?php echo htmlspecialchars($t['Wealth']); ?>
                </a>
                <a href="?action=team" class="nav-tab <?php echo $action === 'team' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> <?php echo htmlspecialchars($t['Team']); ?>
                </a>
                <a href="?action=task" class="nav-tab <?php echo $action === 'task' ? 'active' : ''; ?>">
                    <i class="fas fa-tasks"></i> <?php echo htmlspecialchars($t['Task']); ?>
                </a>
                <a href="?action=mine" class="nav-tab <?php echo $action === 'mine' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($t['Mine']); ?>
                </a>
            </div>

            <!-- HOME PAGE -->
            <?php if ($action === 'home'): ?>
                <div class="home-image-container">
                    <img src="https://img.freepik.com/premium-photo/lng-liquified-natural-gas-tanker-anchored-gas-terminal-gas-tanks-storage-oil-crude-gas-tanker-ship-lpg-tanker-bay-petroleum-chemical-methane-freighter-export-import_1033579-76243.jpg" alt="Gas storage tanks" class="home-image">
                </div>
                <div style="text-align: center;">
                    <a href="?action=help" class="home-telegram-btn">
                        <i class="fab fa-telegram"></i> Telegram Service
                    </a>
                </div>

                <!-- Non-functional Product List button -->
                <div style="text-align:color: #32CD32; margin-bottom: 15px; center; margin: 10px 0;">
                    <span class="product-list-btn">Product List</span>
                </div>

                <h2 style="color: #32CD32; margin-bottom: 15px;"></h2>
                <div class="products-grid">
                    <?php
                    $products = $conn->query("SELECT * FROM products WHERE status = 'active'");
                    while ($product = $products->fetch_assoc()):
                    ?>
                    <div class="product-card">
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                        <div class="product-info">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-price"><?php echo number_format($product['price'], 2); ?> ETB</div>
                            <div class="product-income"><i class="fas fa-chart-line"></i> Total: <?php echo number_format($product['total_income'], 2); ?> ETB</div>
                            <div class="product-income"><i class="fas fa-sun"></i> Daily: <?php echo number_format($product['day_income'], 2); ?> ETB</div>
                            <div class="product-days"><i class="far fa-calendar-alt"></i> Duration: <?php echo $product['total_days']; ?> days</div>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <button type="submit" name="purchase_gas" class="buy-btn">Buy Gas</button>
                        </form>
                    </div>
                    <?php endwhile; ?>
                </div>

            <!-- WEALTH PAGE -->
            <?php elseif ($action === 'wealth'): ?>
                <div class="card">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #333;">VIPO</span>
                        <span style="color: #32CD32; font-weight: 700;"><?php echo htmlspecialchars($user_data['vip_no'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                <div class="stats-grid">
                    <div class="stat-item"><div class="stat-value"><?php echo number_format($user_data['balance'] ?? 0, 2); ?></div><div class="stat-label">Balance</div></div>
                    <div class="stat-item"><div class="stat-value"><?php echo number_format($user_data['recharge_wallet'] ?? 0, 2); ?></div><div class="stat-label">Recharge</div></div>
                </div>
                <div class="card gold-card">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                        <span>Gas Exchange</span>
                        <span><?php echo htmlspecialchars($settings['exchange_rate'] ?? '1.00'); ?> ></span>
                    </div>
                    <div class="stats-grid">
                        <div><div style="font-size:0.8rem;">Yesterday</div><div style="font-size:1.5rem;"><?php echo number_format(($user_data['day_income'] ?? 0)*0.8,2); ?> ETB</div></div>
                        <div><div style="font-size:0.8rem;">Today</div><div style="font-size:1.5rem;"><?php echo number_format($user_data['day_income'] ?? 0,2); ?> ETB</div></div>
                        <div><div style="font-size:0.8rem;">This Week</div><div style="font-size:1.5rem;"><?php echo number_format($user_data['week_income'] ?? 0,2); ?> ETB</div></div>
                        <div><div style="font-size:0.8rem;">This Month</div><div style="font-size:1.5rem;"><?php echo number_format($user_data['month_income'] ?? 0,2); ?> ETB</div></div>
                    </div>
                </div>
                <div class="stats-grid">
                    <div class="stat-item"><div class="stat-value"><?php echo number_format($user_data['team_bonus'] ?? 0,2); ?></div><div class="stat-label">Team Bonus</div></div>
                    <div class="stat-item"><div class="stat-value"><?php echo number_format($user_data['day_income'] ?? 0,2); ?></div><div class="stat-label">Pay Income</div></div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <a href="?action=deposit" class="btn">Deposit</a>
                    <a href="?action=withdrawal" class="btn btn-outline">Withdraw</a>
                </div>

            <!-- DEPOSIT PAGE -->
            <?php elseif ($action === 'deposit'): ?>
                <div class="card gold-card">
                    <h3 style="margin-bottom: 15px;">Bank Transfer Details</h3>
                    <div style="background: rgba(0,0,0,0.1); padding: 15px; border-radius: 8px;">
                        <div><strong>Bank:</strong> <?php echo BANK_NAME; ?></div>
                        <div><strong>Account:</strong> <?php echo BANK_ACCOUNT; ?></div>
                        <div><strong>Name:</strong> <?php echo ACCOUNT_NAME; ?></div>
                    </div>
                    <button class="btn-outline" style="margin-top: 10px;" onclick="copyToClipboard('<?php echo BANK_ACCOUNT; ?>')">
                        <i class="fas fa-copy"></i> Copy Account
                    </button>
                </div>

                <div class="reminder-box">
                    <h4><i class="fas fa-exclamation-triangle"></i> Deposit Reminder</h4>
                    <ul>
                        <li>Deposit automatically recharge it takes few minute max = 20 minutes</li>
                        <li>The actual payment amount must match the amount specified in the order.</li>
                        <li>The actual payment amount must be consistent with the order input amount, otherwise the loss will be borne by you. If you do not understand how to complete the recharge payment, please contact online customer service in the Telegram service on the HOME page.</li>
                    </ul>
                </div>

                <div class="card">
                    <h3 style="color: #32CD32; margin-bottom: 15px;">Submit Deposit</h3>
                    <form method="POST">
                        <div class="form-group"><label>Amount (ETB) - Min <?php echo MIN_DEPOSIT; ?></label><input type="number" name="amount" min="<?php echo MIN_DEPOSIT; ?>" max="<?php echo MAX_DEPOSIT; ?>" required></div>
                        <div class="form-group"><label>Payment Method</label><select name="payment_method"><option>CBE Bank Transfer</option><option>CBE Birr</option><option>Other Bank</option></select></div>
                        <div class="form-group"><label>Transaction ID / Reference</label><input type="text" name="transaction_id" required></div>
                        <div class="form-group"><label>Sender Full Name</label><input type="text" name="sender_name" value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" required></div>
                        <div class="form-group"><label>Sender Phone</label><input type="tel" name="sender_phone" required></div>
                        <button type="submit" name="submit_deposit" class="btn">Submit Deposit Request</button>
                    </form>
                </div>
                <h3 style="color: #32CD32; margin: 20px 0 10px;">Recent Deposits</h3>
                <div class="card">
                    <table class="table">
                        <thead>汽<th>Amount</th><th>Status</th><th>Date</th> </thead>
                        <tbody>
                            <?php $deposits = $conn->query("SELECT * FROM transactions WHERE user_id = $user_id AND type = 'deposit' ORDER BY created_at DESC LIMIT 5");
                            while ($dep = $deposits->fetch_assoc()): ?>
                              <tr><td><?php echo number_format($dep['amount'],2); ?> ETB</td><td><span class="badge-<?php echo $dep['status']; ?>"><?php echo $dep['status']; ?></span></td><td><?php echo date('m/d',strtotime($dep['created_at'])); ?></td></tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <!-- WITHDRAWAL PAGE -->
            <?php elseif ($action === 'withdrawal'): ?>
                <div class="card gold-card">
                    <div style="text-align:center;"><div style="font-size:0.9rem;">Available Balance</div><div style="font-size:2rem;"><?php echo number_format($user_data['balance'] ?? 0,2); ?> ETB</div></div>
                </div>

                <div class="reminder-box">
                    <h4><i class="fas fa-exclamation-triangle"></i> Withdrawal Reminder</h4>
                    <ul>
                        <li>Withdrawal time: 1PM to 6PM meaning: withdrawal always at night.</li>
                        <li>Withdrawal is limited to once per day.</li>
                        <li>Banks are closed on weekends and will not process withdrawals.</li>
                        <li><strong>The withdrawal fee is 10% of the withdrawal amount and will be deducted from your balance immediately upon request.</strong> You will receive the net amount if approved.</li>
                    </ul>
                </div>

                <div class="card">
                    <h3 style="color:#32CD32;margin-bottom:15px;">Withdrawal Request</h3>
                    <form method="POST">
                        <div class="form-group"><label>Amount (ETB) - Min <?php echo MIN_WITHDRAWAL; ?></label><input type="number" name="amount" min="<?php echo MIN_WITHDRAWAL; ?>" max="<?php echo $user_data['balance'] ?? 0; ?>" required></div>
                        <div class="form-group"><label>Withdrawal Method</label><select name="withdrawal_method"><option>CBE Bank</option><option>CBE Birr</option><option>Tele Birr</option></select></div>
                        <div class="form-group"><label>Account Number</label><input type="text" name="account_number" required></div>
                        <button type="submit" name="submit_withdrawal" class="btn">Request Withdrawal</button>
                    </form>
                </div>
                <h3 style="color:#32CD32;margin:20px 0 10px;">Withdrawal History</h3>
                <div class="card">
                    <table class="table"><thead><tr><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>
                    <?php $withdrawals = $conn->query("SELECT * FROM transactions WHERE user_id = $user_id AND type = 'withdrawal' ORDER BY created_at DESC LIMIT 5");
                    while ($wd = $withdrawals->fetch_assoc()): ?>
                    <tr><td><?php echo number_format($wd['amount'],2); ?> ETB</td><td><span class="badge-<?php echo $wd['status']; ?>"><?php echo $wd['status']; ?></span></td><td><?php echo date('m/d',strtotime($wd['created_at'])); ?></td></tr>
                    <?php endwhile; ?></tbody></table>
                </div>

            <!-- TEAM PAGE -->
            <?php elseif ($action === 'team'): ?>
                <div class="nav-tabs" style="margin-bottom:15px;">
                    <a href="?action=team" class="nav-tab <?php echo !isset($_GET['invite'])?'active':'';?>">Team</a>
                    <a href="?action=team&invite=1" class="nav-tab <?php echo isset($_GET['invite'])?'active':'';?>">Invite</a>
                </div>

                <?php if (isset($_GET['invite'])): ?>
                    <!-- Invite page -->
                    <div class="card" style="text-align:center;">
                        <h3 style="color:#32CD32;margin-bottom:15px;">Invite QR Code</h3>
                        <div class="qr-container"><div class="qr-placeholder">QR Code<br><?php echo htmlspecialchars($user_data['referral_code'] ?? ''); ?></div></div>
                        <div style="margin:20px 0;border:2px solid #32CD32;padding:15px;border-radius:10px;">
                            <div style="font-size:2rem;font-weight:700;color:#32CD32;"><?php echo htmlspecialchars($user_data['referral_code'] ?? ''); ?></div>
                            <p style="color:#333;">Promo Code</p>
                            <button class="btn-outline" onclick="copyToClipboard('<?php echo htmlspecialchars($user_data['referral_code'] ?? ''); ?>')">Copy</button>
                        </div>
                        <div style="margin:20px 0;">
                            <div style="background:#FFB6C1;padding:10px;border-radius:5px;word-break:break-all;">
                                <?php 
                                    $invite_link = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=register&ref=' . urlencode($user_data['referral_code'] ?? '');
                                ?>
                                <?php echo htmlspecialchars($invite_link); ?>
                            </div>
                            <p style="color:#333;">Invitation Link</p>
                            <button class="btn-outline" onclick="copyToClipboard('<?php echo htmlspecialchars($invite_link); ?>')"><i class="fas fa-copy"></i> Copy Link</button>
                        </div>
                        <p style="color:#333;margin-top:15px;">Share your link or invite code with friends and earn commissions when they invest!</p>
                    </div>
                <?php else: ?>
                    <!-- Main Team page with referral program info -->
                    <div class="card gold-card">
                        <div class="stats-grid">
                            <div>
                                <div style="font-size:0.8rem;">Effective/Total Team</div>
                                <div style="font-size:1.5rem;">
                                    <?php $effective=$conn->query("SELECT COUNT(*) as count FROM referrals WHERE referrer_id=$user_id AND level='B'")->fetch_assoc()['count']; $total=$conn->query("SELECT COUNT(*) as count FROM referrals WHERE referrer_id=$user_id")->fetch_assoc()['count']; echo "$effective / $total"; ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-size:0.8rem;">Team Revenue</div>
                                <div style="font-size:1.5rem;"><?php echo number_format($user_data['team_bonus'] ?? 0,2); ?> ETB</div>
                            </div>
                        </div>
                    </div>

                    <!-- Referral program process box -->
                    <div class="referral-info">
                        <h4><i class="fas fa-gift"></i> Referral Program Process</h4>
                        <ol>
                            <li><strong>Share your link or invite code:</strong> Copy your exclusive recruitment link or invite code and screenshot.</li>
                            <li><strong>Send to friend:</strong> Share this link with family or friends looking for work.</li>
                            <li><strong>Get reward:</strong> When someone registers via your link, they become your direct subordinate. Once they become regular employees, you will receive referral rewards and valuable task management fees.</li>
                        </ol>
                        <p class="kind-reminder"><strong>Kind reminder:</strong> Your rank must be higher than or equal to your subordinates. Reach a certain level to get rewards from daily tasks.</p>
                    </div>

                    <div class="card">
                        <h4 style="color:#32CD32;margin-bottom:10px;">My Invitation Link</h4>
                        <div style="background:#FFB6C1;padding:10px;border-radius:5px;word-break:break-all;">
                            <?php 
                                $invite_link = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=register&ref=' . urlencode($user_data['referral_code'] ?? '');
                            ?>
                            <?php echo htmlspecialchars($invite_link); ?>
                        </div>
                        <button class="btn-outline" style="margin-top:10px;" onclick="copyToClipboard('<?php echo htmlspecialchars($invite_link); ?>')"><i class="fas fa-copy"></i> Copy Link</button>
                    </div>

                    <h3 style="color:#32CD32;margin:20px 0 10px;">Group B</h3>
                    <?php if ($referrals && $referrals->num_rows>0): while ($ref=$referrals->fetch_assoc()): ?>
                    <div class="card">
                        <div style="display:flex;justify-content:space-between;">
                            <div><span style="font-weight:600;"><?php echo htmlspecialchars($ref['full_name']); ?></span><span style="color:#333;font-size:0.8rem;margin-left:10px;">Level <?php echo $ref['level']; ?></span></div>
                            <span style="color:#32CD32;">Active</span>
                        </div>
                        <div style="color:#333;font-size:0.8rem;margin-top:5px;">Joined: <?php echo date('M d, Y',strtotime($ref['created_at'])); ?></div>
                    </div>
                    <?php endwhile; else: ?>
                    <div class="card" style="text-align:center;color:#333;">No team members yet. Start inviting!</div>
                    <?php endif; ?>
                <?php endif; ?>

            <!-- TASK PAGE -->
            <?php elseif ($action === 'task'): ?>
                <div class="nav-tabs" style="margin-bottom:15px;">
                    <a href="?action=task&group=1" class="nav-tab <?php echo (!isset($_GET['group'])||$_GET['group']==1)?'active':'';?>">Group 1</a>
                    <a href="?action=task&group=2" class="nav-tab <?php echo isset($_GET['group'])&&$_GET['group']==2?'active':'';?>">Group 2</a>
                    <a href="?action=task&center=1" class="nav-tab">Center</a>
                </div>
                <div class="card gold-card" style="text-align:center;">
                    <h3>🎁 Daily Gift</h3>
                    <p>Claim your free daily reward!</p>
                    <?php
                    $claimed_today = $conn->query("SELECT id FROM check_ins WHERE user_id=$user_id AND check_in_date=CURDATE()")->num_rows > 0;
                    if (!$claimed_today): ?>
                        <a href="?claim_gift=1" class="btn" style="margin-top:10px;">Claim Now</a>
                    <?php else: ?>
                        <p style="color:#000;font-weight:bold;">Already claimed today</p>
                    <?php endif; ?>
                </div>

                <?php
                $group = isset($_GET['group']) ? $_GET['group'] : 1;
                $tasks = $conn->query("SELECT * FROM tasks WHERE task_group='group$group' AND status='active'");
                while ($task = $tasks->fetch_assoc()):
                    $week_start = date('Y-m-d', strtotime('monday this week'));
                    $progress = $conn->query("SELECT progress, completed, reward_claimed FROM user_tasks WHERE user_id=$user_id AND task_id={$task['id']} AND week_start='$week_start'")->fetch_assoc();

                    $current = 0;
                    $completed = false;
                    $reward_claimed = false;
                    if ($progress) {
                        $current = (int)$progress['progress'];
                        $completed = (bool)$progress['completed'];
                        $reward_claimed = (bool)$progress['reward_claimed'];
                    }
                    $percentage = ($current / $task['target']) * 100;
                ?>
                    <div class="card">
                        <h4 style="color:#32CD32;"><?php echo htmlspecialchars($task['name']); ?></h4>
                        <p style="color:#333;margin:5px 0;">Reward: <?php echo number_format($task['reward'], 2); ?> ETB</p>
                        <div style="background:#e9ecef;height:8px;border-radius:4px;margin:10px 0;">
                            <div style="background:#32CD32;width:<?php echo $percentage;?>%;height:100%;border-radius:4px;"></div>
                        </div>
                        <div style="display:flex;justify-content:space-between;color:#333;font-size:0.8rem;">
                            <span>Progress: <?php echo $current; ?>/<?php echo $task['target']; ?></span>
                            <span><?php echo round($percentage); ?>%</span>
                        </div>
                        <?php if ($current >= $task['target'] && !$completed): ?>
                            <a href="?claim_task=<?php echo $task['id']; ?>" class="btn" style="margin-top:10px;">Claim Reward</a>
                        <?php elseif ($completed && !$reward_claimed): ?>
                            <a href="?claim_task=<?php echo $task['id']; ?>" class="btn" style="margin-top:10px;">Claim Reward</a>
                        <?php elseif ($completed && $reward_claimed): ?>
                            <p style="color:#32CD32;margin-top:10px;">✓ Claimed</p>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>

                <div class="card" style="background: #FFB6C1; border-left: 4px solid #32CD32;">
                    <p style="color:#333;font-size:0.9rem;">
                        <i class="fas fa-info-circle" style="color:#32CD32;"></i>
                        Note: Activity progress resets weekly. Invited members only count toward your Group 1 tasks after they make their first gas purchase.
                    </p>
                </div>

            <!-- MINE PAGE -->
            <?php elseif ($action === 'mine'): ?>
                <div class="card" style="text-align: center;">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: #32CD32; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; color: #333; font-size: 2rem;">
                        <?php echo strtoupper(substr($user_data['username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <h3 style="color:#32CD32;"><?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?></h3>
                    <p style="color: #333;">VIP: <?php echo htmlspecialchars($user_data['vip_no'] ?? ''); ?></p>
                </div>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 15px;">
                    <a href="?action=orders" class="stat-item"><i class="fas fa-shopping-bag" style="font-size:2rem; color:#32CD32;"></i><div class="stat-label">Order</div></a>
                    <a href="?action=coffee" class="stat-item"><i class="fas fa-gas-pump" style="font-size:2rem; color:#32CD32;"></i><div class="stat-label">Gas</div></a>
                    <a href="?action=financial" class="stat-item"><i class="fas fa-chart-pie" style="font-size:2rem; color:#32CD32;"></i><div class="stat-label">Financial</div></a>
                    <a href="?action=contract" class="stat-item"><i class="fas fa-file-contract" style="font-size:2rem; color:#32CD32;"></i><div class="stat-label">Contract</div></a>
                    <a href="?action=financial_records" class="stat-item"><i class="fas fa-history" style="font-size:2rem; color:#32CD32;"></i><div class="stat-label">Financial records</div></a>
                    <a href="?action=wheel" class="stat-item"><i class="fas fa-circle-notch" style="font-size:2rem; color:#32CD32;"></i><div class="stat-label">Wheel of fortune</div></a>
                    <a href="?action=transaction_record" class="stat-item"><i class="fas fa-exchange-alt" style="font-size:2rem; color:#32CD32;"></i><div class="stat-label">Transaction record</div></a>
                    <a href="?action=messages" class="stat-item"><i class="fas fa-envelope" style="font-size:2rem; color:#32CD32;"></i><div class="stat-label">Message</div><?php if($unread_count>0):?><span class="badge" style="position:relative; top:-10px;"><?php echo $unread_count; ?></span><?php endif;?></a>
                    <a href="?action=language" class="stat-item"><i class="fas fa-globe" style="font-size:2rem; color:#32CD32;"></i><div class="stat-label">Language</div></a>
                    <a href="?action=settings" class="stat-item"><i class="fas fa-cog" style="font-size:2rem; color:#32CD32;"></i><div class="stat-label">Set up</div></a>
                </div>
                <a href="?logout" class="btn" style="background: #dc3545; color: white;" onclick="return confirm('Logout?')">Sign Out</a>

            <!-- ORDERS PAGE -->
            <?php elseif ($action === 'orders'): ?>
                <h2 style="color:#32CD32;margin-bottom:15px;">Your Orders</h2>
                <div class="card">
                    <?php
                    $investments = $conn->query("SELECT * FROM investments WHERE user_id = $user_id ORDER BY purchase_date DESC");
                    if ($investments && $investments->num_rows > 0):
                    ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Amount</th>
                                    <th>Daily</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($inv = $investments->fetch_assoc()):
                                    // Check if investment is still within duration
                                    $product_info = $conn->query("SELECT total_days FROM products WHERE id = {$inv['product_id']}")->fetch_assoc();
                                    $expiry = strtotime($inv['purchase_date'] . " + " . $product_info['total_days'] . " days");
                                    $is_active = ($inv['status'] == 'active' && time() < $expiry);

                                    // Get last claim time
                                    $last_claim = $conn->query("SELECT claim_date FROM profit_claims WHERE investment_id = {$inv['id']} ORDER BY claim_date DESC LIMIT 1")->fetch_assoc();
                                    $can_claim = true;
                                    $next_claim_time = '';
                                    if ($last_claim) {
                                        $last_time = strtotime($last_claim['claim_date']);
                                        if (time() - $last_time < 86400) {
                                            $can_claim = false;
                                            $next_claim = $last_time + 86400;
                                            $hours_left = ceil(($next_claim - time()) / 3600);
                                            $minutes_left = ceil(($next_claim - time()) / 60);
                                            if ($hours_left > 0) {
                                                $next_claim_time = "$hours_left hours";
                                            } else {
                                                $next_claim_time = "$minutes_left minutes";
                                            }
                                        }
                                    }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($inv['product_name']); ?></td>
                                    <td><?php echo number_format($inv['amount'], 2); ?> ETB</td>
                                    <td><?php echo number_format($inv['day_income'], 2); ?> ETB</td>
                                    <td><span class="badge-<?php echo $inv['status']; ?>"><?php echo $inv['status']; ?></span></td>
                                    <td>
                                        <?php if ($is_active): ?>
                                            <?php if ($can_claim): ?>
                                                <a href="?claim_profit=<?php echo $inv['id']; ?>" class="btn-outline" style="padding:0.5px 5px;">Claim</a>
                                            <?php else: ?>
                                                <span class="btn-outline" style="padding:0.5px 5px; opacity:1; cursor:not-allowed;" title="Next claim in <?php echo $next_claim_time; ?>">Claim</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="btn-outline" style="padding:0.5px 5px; opacity:1;">Expired</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No orders yet. <a href="?action=home">Buy Gas</a></p>
                    <?php endif; ?>
                    <a href="?action=home" class="btn" style="margin-top:15px;">Buy More Gas</a>
                </div>

            <!-- GAS (same as home) -->
            <?php elseif ($action === 'coffee'): ?>
                <h2 style="color:#32CD32;margin-bottom:15px;">Available Gas Products</h2>
                <div class="products-grid"><?php $products = $conn->query("SELECT * FROM products WHERE status='active'"); while($product=$products->fetch_assoc()):?><div class="product-card"><img src="<?php echo htmlspecialchars($product['image_url']);?>" alt="<?php echo htmlspecialchars($product['name']);?>" class="product-image"><div class="product-info"><h3><?php echo htmlspecialchars($product['name']);?></h3><div class="product-price"><?php echo number_format($product['price'],2);?> ETB</div><div class="product-income"><i class="fas fa-chart-line"></i> Total: <?php echo number_format($product['total_income'],2);?> ETB</div><div class="product-income"><i class="fas fa-sun"></i> Daily: <?php echo number_format($product['day_income'],2);?> ETB</div><div class="product-days"><i class="far fa-calendar-alt"></i> Duration: <?php echo $product['total_days']; ?> days</div></div><form method="POST"><input type="hidden" name="product_id" value="<?php echo $product['id'];?>"><button type="submit" name="purchase_gas" class="buy-btn">Buy Gas</button></form></div><?php endwhile;?></div>

            <!-- Other pages (financial, contract, etc.) remain unchanged, with added htmlspecialchars where needed -->
            <?php elseif ($action === 'financial'): ?>
                <div class="card gold-card"><h3 style="margin-bottom:15px;">Financial Summary</h3><div class="stats-grid"><div><div style="font-size:0.8rem;">Balance</div><div style="font-size:1.5rem;"><?php echo number_format($user_data['balance'] ?? 0,2);?> ETB</div></div><div><div style="font-size:0.8rem;">Total Income</div><div style="font-size:1.5rem;"><?php echo number_format($user_data['total_income'] ?? 0,2);?> ETB</div></div><div><div style="font-size:0.8rem;">Team Bonus</div><div style="font-size:1.5rem;"><?php echo number_format($user_data['team_bonus'] ?? 0,2);?> ETB</div></div><div><div style="font-size:0.8rem;">Check-in Points</div><div style="font-size:1.5rem;"><?php echo $user_data['check_in_points'] ?? 0;?></div></div></div></div>

            <?php elseif ($action === 'contract'): ?>
                <h2 style="color:#32CD32;margin-bottom:15px;">Active Contracts</h2><div class="card"><?php $active=$conn->query("SELECT * FROM investments WHERE user_id=$user_id AND status='active'"); if($active && $active->num_rows>0): while($a=$active->fetch_assoc()):?><div style="margin-bottom:15px;padding:10px;background:#FFB6C1;border-radius:8px;"><h4 style="color:#32CD32;"><?php echo htmlspecialchars($a['product_name']);?></h4><p>Amount: <?php echo number_format($a['amount'],2);?> ETB</p><p>Daily Profit: <?php echo number_format($a['day_income'],2);?> ETB</p><p>Purchased: <?php echo date('M d, Y',strtotime($a['purchase_date']));?></p></div><?php endwhile; else:?><p>No active contracts.</p><?php endif;?></div>

            <?php elseif ($action === 'financial_records' || $action === 'transaction_record'): ?>
                <h2 style="color:#32CD32;margin-bottom:15px;">Transaction Records</h2><div class="card"><?php if($transactions && $transactions->num_rows>0):?><table class="table"><thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Status</th></tr></thead><tbody><?php while($tx=$transactions->fetch_assoc()):?> <tr><td><?php echo date('m/d',strtotime($tx['created_at']));?></td><td><?php echo ucfirst($tx['type']);?></td><td><?php echo number_format($tx['amount'],2);?> ETB</td><td><span class="badge-<?php echo $tx['status'];?>"><?php echo $tx['status'];?></span></td></tr><?php endwhile;?></tbody></table><?php else:?><p>No transactions yet.</p><?php endif;?></div>

            <?php elseif ($action === 'wheel'): ?>
                <div class="card gold-card" style="text-align:center;"><h3>Wheel of Fortune</h3><p>Spin using 10 points</p><div style="width:200px;height:200px;border-radius:50%;background:conic-gradient(#32CD32 0deg 60deg, #228B22 60deg 120deg, #006400 120deg 180deg, #32CD32 180deg 240deg, #228B22 240deg 300deg, #006400 300deg 360deg);margin:20px auto;display:flex;align-items:center;justify-content:center;"><div style="background:#32CD32;width:50px;height:50px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#333;">SPIN</div></div><p>Your points: <?php echo $user_data['check_in_points'] ?? 0;?></p><?php if(($user_data['check_in_points'] ?? 0) >=10):?><a href="?spin_wheel=1" class="btn">Spin (10 points)</a><?php else:?><button class="btn" disabled>Need 10 points</button><?php endif;?></div>

            <?php elseif ($action === 'messages'): ?>
                <h2 style="color:#32CD32;margin-bottom:15px;">Messages</h2><?php $msgs=$conn->query("SELECT * FROM messages WHERE user_id=$user_id ORDER BY created_at DESC"); if($msgs && $msgs->num_rows>0): while($msg=$msgs->fetch_assoc()):?><div class="card"><h4 style="color:#32CD32;"><?php echo htmlspecialchars($msg['title']);?></h4><p style="color:#333;margin:10px 0;"><?php echo htmlspecialchars($msg['content']);?></p><small><?php echo date('M d, Y H:i',strtotime($msg['created_at']));?></small></div><?php endwhile; else:?><div class="card">No messages.</div><?php endif;?>

            <?php elseif ($action === 'language'): ?>
                <div class="card"><h3 style="color:#32CD32;margin-bottom:20px;">Select Language</h3><a href="?set_lang=en" class="stat-item" style="display:block;margin-bottom:10px;">English (EN)</a><a href="?set_lang=am" class="stat-item" style="display:block;margin-bottom:10px;">አማርኛ (አግርኛ)</a><a href="?set_lang=zh" class="stat-item" style="display:block;margin-bottom:10px;">中文</a></div>

            <?php elseif ($action === 'settings'): ?>
                <div class="card"><h3 style="color:#32CD32;margin-bottom:20px;">Profile Settings</h3><form method="POST"><div class="form-group"><label>Full Name</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($user_data['full_name'] ?? '');?>" required></div><div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? '');?>" required></div><div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div><div class="form-group"><label>New Password (leave blank to keep current)</label><input type="password" name="new_password"></div><button type="submit" name="update_profile" class="btn">Update</button></form></div>

            <?php elseif ($action === 'integral'): ?>
                <div class="card gold-card"><div style="display:flex;justify-content:space-between;"><span>Integral</span><span><?php echo htmlspecialchars($settings['exchange_rate']??'1.00');?>></span></div><div style="text-align:center;padding:20px;"><div style="font-size:3rem;"><?php echo $user_data['check_in_points'] ?? 0;?></div><div>Points</div></div></div>
                <div class="card"><h4 style="color:#32CD32;margin-bottom:15px;">Daily Check-In +1 Points</h4><div class="calendar"><?php $today=date('j');$checked_days=[]; if($check_ins){while($c=$check_ins->fetch_assoc()){$checked_days[]=$c['day'];}} for($i=1;$i<=date('t');$i++): $class=in_array($i,$checked_days)?' checked':''; $class.=($i==$today)?' today':'';?><div class="calendar-day<?php echo $class;?>"><?php echo sprintf("%02d",$i);?></div><?php endfor;?></div><a href="?check_in=1" class="btn" style="margin-top:15px;"><?php $checked_today=$conn->query("SELECT id FROM check_ins WHERE user_id=$user_id AND check_in_date=CURDATE()")->num_rows>0; echo $checked_today?'Checked in today':'Check in now';?></a></div>

            <!-- NEWS PAGE -->
            <?php elseif ($action === 'news'): ?>
                <div class="card">
                    <h2 style="color: #32CD32; margin-bottom: 15px;">News</h2>
                    <p style="color: #333; line-height: 1.6;">
                      🤝 A New Era: The $4.2 Billion China-Ethiopia Gas Partnership
A landmark agreement in March 2026 is set to transform Ethiopia's gas sector. Chinese energy conglomerate GCL Group signed a 25-year, $4.2 billion deal with Nigeria's Dangote Group to power a massive fertilizer plant in Ethiopia .

The key point is that the natural gas for this project will not be imported from China. Instead, GCL Group will develop the Calub Gas Field in Ethiopia's Ogaden Basin. The gas will be transported via a new 108-kilometer pipeline directly to a $2.5 billion fertilizer plant in the Somali Region, which is expected to begin operations in 2029 .

Once completed, this facility will be East Africa's largest modern fertilizer producer. It aims to make Ethiopia self-sufficient in fertilizer, a major shift from its current reliance on imports, and will support regional food security .

💡 Why Does Ethiopia Import Gas?
Despite having domestic natural gas reserves, Ethiopia continues to import gas for several reasons:

Insufficient Refining Capacity: The country lacks the infrastructure to process its natural gas into LPG (cooking gas) or other consumer products on a large scale .

High Demand: Rapid urbanization and population growth are increasing energy demand that domestic supply cannot yet meet .

Strategic Investment: Projects like the GCL-Dangote deal are designed to change this by creating the infrastructure to utilize domestic resources, thereby reducing long-term dependence on imports .
</p>
                    <a href="?action=help" class="btn" style="margin-top: 15px;">Back to Help</a>
                </div>

            <!-- ABOUT PAGE -->
            <?php elseif ($action === 'about'): ?>
                <div class="card">
                    <h2 style="color: #32CD32; margin-bottom: 15px;">About Ethiopian Gas Import</h2>
                    <p style="color: #333; line-height: 1.6;">
                        Here is a breakdown of Ethiopia's gas imports by type and origin:
                    </p>
                    <ul style="color: #333; padding-left: 20px; line-height: 1.6;">
                        <li><strong>Liquefied Petroleum Gas (LPG) / Cooking Gas:</strong> Imported to meet household and commercial energy needs, with the UAE being the largest supplier, followed by Kenya and the United States </li>
                        <li><strong>Natural Gas (Liquefied & Gaseous):</strong>  Imported in very small quantities (for specialized uses) from countries like China, Turkey, and Italy .</li>
                        <li><strong>Liquid or Liquefied-gas Fuels:</strong> Imported in containers, primarily from China and South Korea . </li>
                        <li><strong>United Arab Emirates (UAE):</strong>However, the most significant development is a new strategic partnership where a Chinese company is developing Ethiopia's domestic gas fields to supply a major industrial project, representing a shift from simply importing finished products to utilizing local resources with foreign expertise . </li>
                    </ul>
                    <a href="?action=help" class="btn" style="margin-top: 15px;">Back to Help</a>
                </div>

            <!-- CASH GIFT PAGE -->
            <?php elseif ($action === 'cash_gift_info'): ?>
                <div class="card" style="text-align: center;">
                    <h2 style="color: #32CD32;">🎁 Cash Gift</h2>
                    <?php
                    $already_claimed = $conn->query("SELECT id FROM cash_gifts WHERE user_id = $user_id")->num_rows > 0;
                    ?>
                    <p style="color: #333; margin: 20px 0;">
                        <?php if ($already_claimed): ?>
                            You have already claimed your one-time cash gift.
                        <?php else: ?>
                            Claim your one-time cash gift of <strong>5 ETB</strong>!
                        <?php endif; ?>
                    </p>
                    <?php if (!$already_claimed): ?>
                        <a href="?claim_cash_gift=1" class="btn">Claim Now</a>
                    <?php endif; ?>
                    <a href="?action=help" class="btn btn-outline" style="margin-top: 10px;">Back</a>
                </div>

            <!-- TELEGRAM PAGE -->
            <?php elseif ($action === 'help'): ?>
                <div class="card" style="text-align: center;">
                    <h2 style="color: #32CD32;">📞 Telegram Service</h2>
                    <div style="font-size: 1.5rem; margin: 20px 0; background: #FFB6C1; padding: 15px; border-radius: 10px; color: #333;">
                        📞 contact customer support.
                    </div>
                    <h3 style="color: #32CD32;"># Gas Importer</h3>
                    <p style="color: #333;">The first checkpoint of quality control</p>

                    <div style="margin: 30px 0;">
                        <h4 style="color: #32CD32;">Telegram Service</h4>
                        <div class="support-link">
                            <i class="fab fa-telegram" style="color: #32CD32; font-size: 1.5rem;"></i>
                            <a href="tg://resolve?domain=@GasimportBot" target="_blank">@GasimportBot</a>
                            <button class="copy-btn" onclick="copyToClipboard('@GasimportBot')">Copy</button>
                        </div>
                        <div class="support-link">
                            <i class="fab fa-telegram-plane" style="color: #32CD32; font-size: 1.5rem;"></i>
                            <a href="tg://resolve?domain=gasimportw" target="_blank">t.me/gasimportw</a>
                            <button class="copy-btn" onclick="copyToClipboard('https://t.me/gasimportw')">Copy</button>
                        </div>
                        <div class="support-link">
                            <i class="fab fa-telegram" style="color: #32CD32; font-size: 1.5rem;"></i>
                            <a href="tg://resolve?domain=gasimpor" target="_blank">t.me/gasimpor</a>
                            <button class="copy-btn" onclick="copyToClipboard('https://t.me/gasimpor')">Copy</button>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(2,1fr); gap: 10px; margin: 20px 0;">
                        <a href="?action=news" class="stat-item">📰 News</a>
                        <a href="?action=about" class="stat-item">ℹ️ About</a>
                        <a href="?action=cash_gift_info" class="stat-item">🎁 Cash gift</a>
                        <a href="?action=help" class="stat-item">❓ Help</a>
                    </div>

                    <a href="?action=mine" class="btn">Back to Mine</a>
                </div>

            <!-- ADMIN PANEL -->
            <?php elseif ($action === 'admin' && $_SESSION['role'] === 'admin'): ?>
                <h2 style="color:#32CD32;margin-bottom:15px;">Admin Panel</h2>
                <?php $pending_deposits=$conn->query("SELECT t.*,u.username,u.full_name FROM transactions t JOIN users u ON t.user_id=u.id WHERE t.type='deposit' AND t.status='pending'"); $pending_withdrawals=$conn->query("SELECT t.*,u.username,u.full_name FROM transactions t JOIN users u ON t.user_id=u.id WHERE t.type='withdrawal' AND t.status='pending'"); ?>
                <h3 style="color:#32CD32;">Pending Deposits</h3><div class="card"><?php if($pending_deposits->num_rows>0):?><table class="table"><thead><tr><th>User</th><th>Amount</th><th>Ref</th><th>Action</th></tr></thead><tbody><?php while($dep=$pending_deposits->fetch_assoc()):?> <tr><td><?php echo htmlspecialchars($dep['full_name']);?></td><td><?php echo number_format($dep['amount'],2);?> ETB</td><td><?php echo htmlspecialchars($dep['reference']);?></td><td><a href="?approve_deposit=<?php echo $dep['id'];?>" class="btn-outline" style="padding:5px;">Approve</a> <a href="?reject_deposit=<?php echo $dep['id'];?>" class="btn-outline" style="background:#dc3545; border-color:#dc3545; color:white;">Reject</a></td></tr><?php endwhile;?></tbody></table><?php else:?><p>No pending deposits.</p><?php endif;?></div>
                <h3 style="color:#32CD32;">Pending Withdrawals</h3><div class="card"><?php if($pending_withdrawals->num_rows>0):?><table class="table"><thead><tr><th>User</th><th>Amount</th><th>Account</th><th>Action</th></tr></thead><tbody><?php while($wd=$pending_withdrawals->fetch_assoc()):?> <tr><td><?php echo htmlspecialchars($wd['full_name']);?></td><td><?php echo number_format($wd['amount'],2);?> ETB</td><td><?php echo htmlspecialchars($wd['account_number']);?></td><td><a href="?approve_withdrawal=<?php echo $wd['id'];?>" class="btn-outline">Approve</a> <a href="?reject_deposit=<?php echo $wd['id'];?>" class="btn-outline" style="background:#dc3545; border-color:#dc3545; color:white;">Reject</a></td></tr><?php endwhile;?></tbody></table><?php else:?><p>No pending withdrawals.</p><?php endif;?></div>

            <?php else: ?>
                <?php header("Location: " . $_SERVER['PHP_SELF'] . "?action=home"); exit(); ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => alert('Copied!')).catch(() => {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('Copied!');
            });
        }
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        document.querySelectorAll('a[href^="tg://"]').forEach(link => {
            link.addEventListener('click', (e) => {
                setTimeout(() => {
                    window.location = 'https://' + link.href.replace('tg://resolve?domain=', 't.me/');
                }, 500);
            });
        });

        function updateClock() {
            const now = new Date();
            const ethiopiaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Africa/Addis_Ababa' }));
            const hours = ethiopiaTime.getHours().toString().padStart(2, '0');
            const minutes = ethiopiaTime.getMinutes().toString().padStart(2, '0');
            const seconds = ethiopiaTime.getSeconds().toString().padStart(2, '0');
            document.getElementById('timeDisplay').textContent = `${hours}:${minutes}:${seconds}`;

            const h = ethiopiaTime.getHours();
            const indicator = document.querySelector('.withdrawal-indicator');
            if (h >= 10 && h < 20) {
                indicator.className = 'withdrawal-indicator indicator-open';
                indicator.title = 'Withdrawal window: open (10am-8pm)';
            } else {
                indicator.className = 'withdrawal-indicator indicator-closed';
                indicator.title = 'Withdrawal window: closed';
            }
        }
        updateClock();
        setInterval(updateClock, 1000);
    </script>
</body>
</html>
<?php $conn->close();
ob_end_flush();
?>