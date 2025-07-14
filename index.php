<?php

/**
 * Poor People Walfare - Community Blood Donation Platform
 *
 * A full-fledged, single-file application for managing blood donors, requests, and drives,
 * with enhanced UI/UX, new features, and a focus on production readiness.
 *
 * @author Yasin Ullah (Pakistani)
 * @version 3.0.0
 * @package LifeFlowConnect
 */

session_start();
ob_start(); // Start output buffering
error_reporting(E_ALL); // Report all errors except notices, strict, and deprecated
ini_set('display_errors', 1); // Do not display errors in production
date_default_timezone_set('Asia/Karachi'); // Set timezone for Pakistan

// --- Configuration Constants ---
define('DB_FILE', __DIR__ . '/_data/lifeflow_connect.sqlite');
define('SITE_NAME', 'Poor People Walfare');
define('APP_VERSION', '3.0.0');
define('SESSION_LIFETIME', 7200); // 2 hours in seconds
define('UPLOAD_DIR_BASE', __DIR__ . '/uploads/'); // Base directory for uploads
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2 MB
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// --- Global Variables (for convenience and consistency) ---
$roles = ['donor', 'admin'];
$blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$urgencies = ['normal', 'urgent', 'emergency'];
$request_statuses = ['pending', 'fulfilled', 'closed'];
$drive_statuses = ['upcoming', 'completed', 'cancelled'];
$donation_types = ['request', 'drive', 'voluntary'];
$blood_bag_statuses = ['available', 'used', 'expired', 'quarantined'];

// --- Database Functions ---

/**
 * Gets the PDO database connection object.
 * @return PDO The PDO database object.
 */
function get_db()
{
    static $db = null;
    if ($db === null) {
        try {
            // Ensure the directory exists and is writable
            $db_dir = dirname(DB_FILE);
            if (!is_dir($db_dir)) {
                mkdir($db_dir, 0755, true);
            }
            if (!is_writable($db_dir)) {
                throw new Exception("Database directory is not writable: " . $db_dir);
            }

            $db = new PDO('sqlite:' . DB_FILE);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $db->exec("PRAGMA foreign_keys = ON;"); // Enforce foreign key constraints
            $db->exec("PRAGMA journal_mode = WAL;"); // Improve concurrency and durability
            $db->exec("PRAGMA synchronous = NORMAL;"); // Balance durability and performance
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            http_response_code(500);
            die("
                <div style='font-family: sans-serif; padding: 20px; border: 2px solid #dc3545; background: #f8d7da; color: #721c24; margin: 50px; border-radius: 8px;'>
                    <h1 style='color: #dc3545;'>&#x26A0; Application Error</h1>
                    <p>We are currently experiencing technical difficulties. Please try again later.</p>
                    <p style='font-size: 0.8em; color: #721c24;'>If you are the administrator, please check server permissions for the database file (<code>" . htmlspecialchars(basename(DB_FILE)) . "</code>) and the application directory (<code>" . htmlspecialchars($db_dir) . "</code>).</p>
                </div>
            ");
        } catch (Exception $e) {
            error_log("Application Setup Error: " . $e->getMessage());
            http_response_code(500);
            die("
                <div style='font-family: sans-serif; padding: 20px; border: 2px solid #dc3545; background: #f8d7da; color: #721c24; margin: 50px; border-radius: 8px;'>
                    <h1 style='color: #dc3545;'>&#x26A0; Application Setup Error</h1>
                    <p>There was an issue setting up the application. Please ensure the <code>_data</code> directory exists and is writable.</p>
                    <p style='font-size: 0.8em; color: #721c24;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>
                </div>
            ");
        }
    }
    return $db;
}

/**
 * Initializes the database schema and seeds it with an admin user and sample data.
 */
function init_db()
{
    $db = get_db();

    // Create tables
    $db->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        );

        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            full_name TEXT NOT NULL,
            contact_number TEXT,
            role TEXT NOT NULL DEFAULT 'donor' CHECK(role IN ('donor', 'admin')),
            approved INTEGER DEFAULT 0, -- 0 for pending, 1 for approved
            first_login INTEGER DEFAULT 1, -- 1 for first login, 0 after password change
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER UNIQUE NOT NULL,
            blood_group TEXT NOT NULL,
            city TEXT,
            last_donation_date DATE,
            is_available INTEGER DEFAULT 1,
            total_donations INTEGER DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_name TEXT NOT NULL,
            blood_group TEXT NOT NULL,
            city TEXT NOT NULL,
            hospital_name TEXT NOT NULL,
            required_units INTEGER DEFAULT 1,
            urgency TEXT NOT NULL DEFAULT 'normal' CHECK(urgency IN ('normal', 'urgent', 'emergency')),
            contact_person TEXT NOT NULL,
            contact_number TEXT NOT NULL,
            details TEXT,
            status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'fulfilled', 'closed')),
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS drives (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            drive_date DATETIME NOT NULL,
            location TEXT NOT NULL,
            location_url TEXT,
            organizer TEXT,
            description TEXT,
            status TEXT DEFAULT 'upcoming' CHECK(status IN ('upcoming', 'completed', 'cancelled')),
            created_by INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS donations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            donation_date DATE NOT NULL,
            type TEXT NOT NULL CHECK(type IN ('request', 'drive', 'voluntary')),
            request_id INTEGER,
            drive_id INTEGER,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE SET NULL,
            FOREIGN KEY (drive_id) REFERENCES drives(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS announcements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            created_by INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS stories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            image_url TEXT,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS news (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            image_url TEXT,
            published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS blood_stock (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            blood_group TEXT UNIQUE NOT NULL,
            units INTEGER DEFAULT 0,
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS blood_inventory (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bag_id TEXT UNIQUE NOT NULL,
            blood_group TEXT NOT NULL,
            donor_id INTEGER,
            collection_date DATE NOT NULL,
            expiry_date DATE NOT NULL,
            status TEXT NOT NULL DEFAULT 'available' CHECK(status IN ('available', 'used', 'expired', 'quarantined')),
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id INTEGER,
            sender_name TEXT NOT NULL,
            sender_email TEXT NOT NULL,
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            read_status INTEGER DEFAULT 0, -- 0 for unread, 1 for read
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
        );
    ");

    // Create indexes for performance
    $db->exec("
        CREATE INDEX IF NOT EXISTS idx_users_username ON users (username);
        CREATE INDEX IF NOT EXISTS idx_users_email ON users (email);
        CREATE INDEX IF NOT EXISTS idx_profiles_blood_group ON profiles (blood_group);
        CREATE INDEX IF NOT EXISTS idx_profiles_city ON profiles (city);
        CREATE INDEX IF NOT EXISTS idx_requests_blood_group ON requests (blood_group);
        CREATE INDEX IF NOT EXISTS idx_requests_city ON requests (city);
        CREATE INDEX IF NOT EXISTS idx_requests_status ON requests (status);
        CREATE INDEX IF NOT EXISTS idx_drives_drive_date ON drives (drive_date);
        CREATE INDEX IF NOT EXISTS idx_donations_user_id ON donations (user_id);
        CREATE INDEX IF NOT EXISTS idx_donations_donation_date ON donations (donation_date);
        CREATE INDEX IF NOT EXISTS idx_announcements_created_at ON announcements (created_at);
        CREATE INDEX IF NOT EXISTS idx_stories_created_at ON stories (created_at);
        CREATE INDEX IF NOT EXISTS idx_news_published_at ON news (published_at);
        CREATE INDEX IF NOT EXISTS idx_inventory_blood_group ON blood_inventory (blood_group);
        CREATE INDEX IF NOT EXISTS idx_inventory_status ON blood_inventory (status);
        CREATE INDEX IF NOT EXISTS idx_inventory_expiry_date ON blood_inventory (expiry_date);
        CREATE INDEX IF NOT EXISTS idx_messages_created_at ON messages (created_at);
    ");

    // Seed initial admin user if none exists
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $admin_user = 'admin';
        $admin_pass = 'admin123';
        $hashed_pass = password_hash($admin_pass, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, role, approved, contact_number, first_login) VALUES (?, ?, ?, ?, 'admin', 1, ?, 1)");
        $stmt->execute([$admin_user, 'admin@lifeflow.com', $hashed_pass, 'Administrator', '03001234567']);
        set_flash_message('success', "Initial Admin user created: <strong>Username:</strong> {$admin_user}, <strong>Password:</strong> {$admin_pass}. You will be prompted to change this password on your first login for security.");
    }

    // Ensure all blood groups are in blood_stock table
    foreach ($GLOBALS['blood_groups'] as $bg) {
        $stmt = $db->prepare("INSERT OR IGNORE INTO blood_stock (blood_group, units) VALUES (?, 0)");
        $stmt->execute([$bg]);
    }

    // Seed sample data if no users exist (implies fresh install)
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() <= 1) { // If only admin or no users
        seed_sample_data($db);
    }
}

/**
 * Seeds the database with sample data.
 * @param PDO $db The database object.
 */
function seed_sample_data($db)
{
    try {
        $db->beginTransaction();

        // Sample Users and Profiles
        $users_data = [
            ['john', 'john.doe@example.com', 'John Doe', '03001112233', 'A+', 'Karachi', date('Y-m-d', strtotime('-4 months')), 1],
            ['jane', 'jane.smith@example.com', 'Jane Smith', '03214455667', 'O-', 'Lahore', date('Y-m-d', strtotime('-5 months')), 1],
            ['ahmed', 'ahmed.khan@example.com', 'Ahmed Khan', '03337788990', 'B+', 'Islamabad', date('Y-m-d', strtotime('-2 months')), 1],
            ['fatima', 'fatima.ali@example.com', 'Fatima Ali', '03451234567', 'AB+', 'Rawalpindi', date('Y-m-d', strtotime('-7 months')), 0],
            ['umar', 'umar.farooq@example.com', 'Umar Farooq', '03029876543', 'A-', 'Faisalabad', date('Y-m-d', strtotime('-1 months')), 1],
            ['sara', 'sara.hassan@example.com', 'Sara Hassan', '03362233445', 'O+', 'Multan', date('Y-m-d', strtotime('-6 months')), 1],
            ['ali', 'ali.raza@example.com', 'Ali Raza', '03009876543', 'B-', 'Karachi', date('Y-m-d', strtotime('-10 months')), 1],
            ['zainab', 'zainab.nadeem@example.com', 'Zainab Nadeem', '03211234567', 'O-', 'Lahore', date('Y-m-d', strtotime('-1 month')), 1],
        ];

        $user_stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, contact_number, role, approved) VALUES (?, ?, ?, ?, ?, 'donor', 1)");
        $profile_stmt = $db->prepare("INSERT INTO profiles (user_id, blood_group, city, last_donation_date, is_available, total_donations) VALUES (?, ?, ?, ?, ?, ?)");
        $hashed_pass = password_hash('password123', PASSWORD_DEFAULT);

        $user_ids = [];
        foreach ($users_data as $ud) {
            $user_stmt->execute([$ud[0], $ud[1], $hashed_pass, $ud[2], $ud[3]]);
            $user_id = $db->lastInsertId();
            $profile_stmt->execute([$user_id, $ud[4], $ud[5], $ud[6], $ud[7], rand(1, 5)]);
            $user_ids[] = $user_id;
        }

        // Sample Requests
        $requests_data = [
            ['Ali Raza', 'A+', 'Lahore', 'General Hospital', 2, 'urgent', 'Kamran', '03123456789', 'Patient needs immediate transfusion.', $user_ids[0]],
            ['Sana Javed', 'O-', 'Islamabad', 'PIMS Hospital', 1, 'emergency', 'Javed Iqbal', '03019876543', 'Critical condition, requires O- urgently.', $user_ids[1]],
            ['Baby of Aisha', 'B+', 'Karachi', 'Aga Khan Hospital', 1, 'normal', 'Imran', '03225554433', 'For a newborn baby.', $user_ids[0]],
            ['Zubair Ahmed', 'AB-', 'Peshawar', 'LRH', 3, 'urgent', 'Fazal', '03346677889', 'Accident victim.', $user_ids[3]],
            ['Nida Khan', 'O+', 'Karachi', 'Ziauddin Hospital', 2, 'normal', 'Aslam', '03001231234', 'Scheduled surgery.', $user_ids[0]],
            ['Tariq Malik', 'B-', 'Lahore', 'Shaukat Khanum', 1, 'emergency', 'Fahad', '03219988776', 'Cancer patient.', $user_ids[1]],
            ['Amna Bibi', 'A-', 'Multan', 'Nishtar Hospital', 1, 'normal', 'Rashid', '03331122334', 'Routine checkup.', $user_ids[5]],
            ['Usman Ghani', 'AB+', 'Quetta', 'Civil Hospital', 2, 'urgent', 'Farhan', '03456789012', 'Post-surgery complications.', $user_ids[6]],
        ];
        $req_stmt = $db->prepare("INSERT INTO requests (patient_name, blood_group, city, hospital_name, required_units, urgency, contact_person, contact_number, details, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($requests_data as $rd) {
            $req_stmt->execute($rd);
        }

        // Sample Drives
        $drives_data = [
            ['City Center Blood Drive', date('Y-m-d H:i:s', strtotime('+2 week')), 'Main Atrium, Dolmen Mall, Karachi', 'https://maps.app.goo.gl/example1', 'Red Crescent Society', 'Join us to make a difference!', 'upcoming'],
            ['University Campus Donation Camp', date('Y-m-d H:i:s', strtotime('+1 month')), 'University of Karachi, Main Campus', 'https://maps.app.goo.gl/example2', 'University Health Services', 'Students and faculty, come donate!', 'upcoming'],
            ['Annual Life Saver Event', date('Y-m-d H:i:s', strtotime('+2 month')), 'Expo Center, Lahore', 'https://maps.app.goo.gl/example3', SITE_NAME, 'Largest drive of the year!', 'upcoming'],
            ['Community Support Drive', date('Y-m-d H:i:s', strtotime('-1 month')), 'F-9 Park, Islamabad', 'https://maps.app.goo.gl/example4', 'Local Community', 'Successful drive last month.', 'completed']
        ];
        $drive_stmt = $db->prepare("INSERT INTO drives (title, drive_date, location, location_url, organizer, description, created_by, status) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
        foreach ($drives_data as $dd) {
            $drive_stmt->execute([$dd[0], $dd[1], $dd[2], $dd[3], $dd[4], $dd[5], $dd[6]]);
        }

        // Sample Announcements
        $ann_stmt = $db->prepare("INSERT INTO announcements (title, content, created_by) VALUES (?, ?, 1)");
        $ann_stmt->execute(['Welcome to ' . SITE_NAME, 'We are excited to launch this platform to connect blood donors with those in need. Register today and become a hero!']);
        $ann_stmt->execute(['Benefits of Blood Donation', 'Donating blood not only saves lives but also has health benefits for the donor, such as reducing the risk of heart disease.']);
        $ann_stmt->execute(['Urgent Need for O- Blood', 'There is a critical shortage of O- blood group. If you are O- and eligible, please consider donating immediately.']);

        // Sample Stories
        $story_stmt = $db->prepare("INSERT INTO stories (title, content, created_by, image_url) VALUES (?, ?, ?, ?)");
        $story_stmt->execute([
            'A Drop of Hope: My First Donation Experience',
            'I was nervous for my first blood donation, but the staff at Poor People Walfare made it such a comfortable and rewarding experience. Knowing that my donation could save a life filled me with immense pride. I encourage everyone to try it!',
            $user_ids[0], // John Doe
            'https://i.imgur.com/example_story1.jpg' // Placeholder image
        ]);
        $story_stmt->execute([
            'From Despair to Hope: How a Donor Saved My Child',
            'My child was in critical condition, needing a rare blood type. Thanks to Poor People Walfare, we found a matching donor within hours. Their selfless act gave my child a second chance at life. We are eternally grateful.',
            null, // Anonymous story
            'https://i.imgur.com/example_story2.jpg' // Placeholder image
        ]);
        $story_stmt->execute([
            'The Power of Community: A Success Story',
            'When a local hospital faced a severe shortage of AB+ blood, our community rallied. Through the Poor People Walfare platform, we organized an emergency drive that brought in dozens of donors, averting a crisis. It truly showed the power of collective action.',
            1, // Admin
            'https://i.imgur.com/example_story3.jpg' // Placeholder image
        ]);
        $story_stmt->execute([
            'My Journey as a Regular Donor',
            'For years, I\'ve been a regular blood donor. It\'s a small commitment that makes a huge difference. Every time I donate, I feel a sense of purpose, knowing I\'m contributing to someone\'s recovery. Poor People Walfare makes it easy to track my donations and find opportunities.',
            $user_ids[4], // Umar Farooq
            'https://i.imgur.com/example_story4.jpg' // Placeholder image
        ]);

        // Sample News
        $news_stmt = $db->prepare("INSERT INTO news (title, content, created_by, image_url) VALUES (?, ?, ?, ?)");
        $news_stmt->execute([
            'Poor People Walfare Partners with Major Hospitals',
            'We are proud to announce our new partnerships with leading hospitals across Pakistan, streamlining the process of blood requests and donations. This collaboration aims to enhance efficiency and reach more patients in need.',
            1,
            'https://i.imgur.com/example_news1.jpg' // Placeholder image
        ]);
        $news_stmt->execute([
            'Record-Breaking Donations at Annual Drive',
            'Our recent annual blood drive saw an unprecedented turnout, collecting over 500 units of blood. A huge thank you to all the donors and volunteers who made this possible!',
            1,
            'https://i.imgur.com/example_news2.jpg' // Placeholder image
        ]);
        $news_stmt->execute([
            'New Guidelines for Blood Donation Eligibility',
            'The Ministry of Health has issued updated guidelines for blood donation eligibility. We encourage all potential donors to review these changes on our website or contact us for more information to ensure compliance and safety.',
            1,
            'https://i.imgur.com/example_news3.jpg' // Placeholder image
        ]);
        $news_stmt->execute([
            'Celebrating World Blood Donor Day',
            'On World Blood Donor Day, we extend our deepest gratitude to all voluntary blood donors. Your selfless act saves millions of lives each year and is a testament to human kindness. Let\'s continue to inspire more people to donate.',
            1,
            'https://i.imgur.com/example_news4.jpg' // Placeholder image
        ]);

        // Sample Blood Inventory (and update blood_stock)
        $inventory_data = [
            ['BAG-A1B2C3D4E5F6', 'A+', $user_ids[0], date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('+32 days')), 'available', 'Routine collection'],
            ['BAG-F7E8D9C0B1A2', 'O-', $user_ids[1], date('Y-m-d', strtotime('-5 days')), date('Y-m-d', strtotime('+37 days')), 'available', 'Urgent collection'],
            ['BAG-C3B4A5F6E7D8', 'B+', $user_ids[2], date('Y-m-d', strtotime('-20 days')), date('Y-m-d', strtotime('+22 days')), 'available', 'From drive'],
            ['BAG-901234567890', 'AB+', $user_ids[3], date('Y-m-d', strtotime('-60 days')), date('Y-m-d', strtotime('-18 days')), 'expired', 'Expired due to age'],
            ['BAG-112233445566', 'A-', $user_ids[4], date('Y-m-d', strtotime('-15 days')), date('Y-m-d', strtotime('+27 days')), 'available', 'Regular donor'],
            ['BAG-778899001122', 'O+', $user_ids[5], date('Y-m-d', strtotime('-3 days')), date('Y-m-d', strtotime('+39 days')), 'available', 'Newest collection'],
            ['BAG-334455667788', 'B-', $user_ids[6], date('Y-m-d', strtotime('-2 days')), date('Y-m-d', strtotime('+40 days')), 'available', 'From Ali Raza'],
            ['BAG-990011223344', 'O-', $user_ids[7], date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('+41 days')), 'available', 'From Zainab Nadeem'],
            ['BAG-556677889900', 'A+', $user_ids[0], date('Y-m-d', strtotime('-25 days')), date('Y-m-d', strtotime('+17 days')), 'available', 'Second donation from John'],
            ['BAG-1234567890AB', 'B+', $user_ids[2], date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('+12 days')), 'used', 'Used for patient Ali Raza'],
        ];

        $inventory_stmt = $db->prepare("INSERT INTO blood_inventory (bag_id, blood_group, donor_id, collection_date, expiry_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($inventory_data as $id) {
            $inventory_stmt->execute($id);
        }

        // Update blood_stock based on initial inventory
        $db->exec("UPDATE blood_stock SET units = 0, last_updated = CURRENT_TIMESTAMP;"); // Reset first
        $db->exec("
            INSERT INTO blood_stock (blood_group, units, last_updated)
            SELECT blood_group, COUNT(*) as units, MAX(created_at)
            FROM blood_inventory
            WHERE status = 'available'
            GROUP BY blood_group
            ON CONFLICT(blood_group) DO UPDATE SET
                units = excluded.units,
                last_updated = excluded.last_updated;
        ");

        $db->commit();
        set_flash_message('info', 'Sample data has been seeded into the database.');
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Error seeding sample data: ' . $e->getMessage());
        set_flash_message('danger', 'Error seeding sample data: ' . $e->getMessage());
    }
}

// --- Helper Functions ---

/**
 * Safely sanitizes data for output (HTML escaping).
 * @param string|array|null $data The data to sanitize.
 * @return string|array|null The sanitized data.
 */
function sanitize($data)
{
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

/**
 * Validates and sanitizes input data.
 * @param string $input The input string.
 * @param string $type The type of validation ('email', 'int', 'string', 'tel', 'date', 'datetime', 'url', 'blood_group', 'role', 'status', 'urgency', 'blood_bag_status').
 * @param array $options Additional options (e.g., 'min_len', 'max_len').
 * @return mixed The sanitized/validated value or false on failure.
 */
function validate_input($input, $type, $options = [])
{
    $input = trim($input);
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);
        case 'string':
            $min_len = $options['min_len'] ?? 0;
            $max_len = $options['max_len'] ?? 255;
            if (mb_strlen($input) < $min_len || mb_strlen($input) > $max_len) {
                return false;
            }
            return sanitize($input);
        case 'tel':
            // Allow Pakistani mobile numbers (03xxxxxxxxx or +923xxxxxxxxx)
            if (preg_match('/^(03\d{9}|\+923\d{9})$/', $input)) {
                return sanitize($input);
            }
            return false;
        case 'date':
            $d = DateTime::createFromFormat('Y-m-d', $input);
            return $d && $d->format('Y-m-d') === $input ? $input : false;
        case 'datetime':
            $d = DateTime::createFromFormat('Y-m-d H:i:s', $input);
            return $d && $d->format('Y-m-d H:i:s') === $input ? $input : false;
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL);
        case 'blood_group':
            global $blood_groups;
            return in_array($input, $blood_groups) ? $input : false;
        case 'role':
            global $roles;
            return in_array($input, $roles) ? $input : false;
        case 'status': // For requests, drives
            global $request_statuses, $drive_statuses;
            $valid_statuses = array_merge($request_statuses, $drive_statuses);
            return in_array($input, $valid_statuses) ? $input : false;
        case 'urgency':
            global $urgencies;
            return in_array($input, $urgencies) ? $input : false;
        case 'blood_bag_status':
            global $blood_bag_statuses;
            return in_array($input, $blood_bag_statuses) ? $input : false;
        default:
            return false;
    }
}

/**
 * Generates a CSRF token and stores it in the session.
 * @return string The generated token.
 */
function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a CSRF token from the request against the session token.
 * @param string $token The token from the request.
 * @return bool True if valid, false otherwise.
 */
function validate_csrf_token($token)
{
    if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        set_flash_message('danger', 'Invalid CSRF token. Please try again.');
        return false;
    }
    return true;
}

/**
 * Redirects to a specified page.
 * @param string $page The page to redirect to.
 * @param array $params Optional query parameters.
 * @param int $status_code HTTP status code for redirection (e.g., 303 See Other).
 */
function redirect($page = 'dashboard', $params = [], $status_code = 303)
{
    $url = $_SERVER['PHP_SELF'];
    $params['page'] = $page;
    $url .= '?' . http_build_query($params);
    header("Location: " . $url, true, $status_code);
    exit;
}

/**
 * Sets a flash message in the session.
 * @param string $type The message type (e.g., 'success', 'danger', 'warning', 'info').
 * @param string $text The message text.
 */
function set_flash_message($type, $text)
{
    $_SESSION['flash_message'] = ['type' => $type, 'text' => $text];
}

/**
 * Displays the flash message if one exists.
 */
function display_flash_message()
{
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        echo "<div class='alert alert-{$message['type']} alert-dismissible fade show' role='alert'>
                {$message['text']}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
        unset($_SESSION['flash_message']);
    }
}

/**
 * Checks if a user is logged in.
 * @return bool True if logged in, false otherwise.
 */
function is_logged_in()
{
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        set_flash_message('info', 'Your session has expired. Please log in again.');
        return false;
    }
    if (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time(); // Update last activity
        return true;
    }
    return false;
}

/**
 * Gets the current user's role.
 * @return string|null The user's role or null if not logged in.
 */
function get_current_user_role()
{
    return $_SESSION['role'] ?? null;
}

/**
 * Gets the current user's ID.
 * @return int|null The user's ID or null if not logged in.
 */
function get_current_user_id()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Checks if the current user has the required role(s) and is approved.
 * @param string|array $required_roles The role or roles required.
 * @param bool $redirect_on_fail Whether to redirect if authentication fails.
 * @return bool True if authorized, false otherwise.
 */
function check_auth($required_roles, $redirect_on_fail = true)
{
    if (!is_logged_in()) {
        if ($redirect_on_fail) {
            set_flash_message('warning', 'You need to be logged in to access that page.');
            redirect('login');
        }
        return false;
    }

    $user_role = get_current_user_role();
    $is_authorized = false;

    if (is_array($required_roles)) {
        $is_authorized = in_array($user_role, $required_roles);
    } else {
        $is_authorized = ($user_role === $required_roles);
    }

    if (!$is_authorized) {
        if ($redirect_on_fail) {
            set_flash_message('danger', 'You do not have permission to access that page.');
            redirect('dashboard');
        }
        return false;
    }

    // Check approval status for non-admin users
    if ($user_role !== 'admin' && ($_SESSION['approved'] ?? 0) != 1) {
        if ($redirect_on_fail) {
            set_flash_message('info', 'Your account is pending approval. Please contact an administrator.');
            redirect('dashboard');
        }
        return false;
    }

    return true;
}

/**
 * Calculates the next eligible donation date (3 months after last donation).
 * @param string|null $last_date The last donation date in Y-m-d format.
 * @return string The next eligible date in Y-m-d format.
 */
function get_next_eligible_date($last_date)
{
    if (empty($last_date)) {
        return date('Y-m-d'); // Eligible immediately if no prior donation
    }
    return date('Y-m-d', strtotime($last_date . ' +3 months'));
}

/**
 * Renders an icon from Bootstrap Icons.
 * @param string $icon_name The name of the icon.
 * @param string $extra_classes Additional CSS classes.
 * @return string HTML for the icon.
 */
function render_icon($icon_name, $extra_classes = '')
{
    return "<i class='bi bi-{$icon_name} {$extra_classes}'></i>";
}

/**
 * Handles file uploads securely.
 * @param array $file_input The $_FILES array for the input.
 * @param string $sub_dir The subdirectory within UPLOAD_DIR_BASE (e.g., 'stories/', 'news/').
 * @param array $allowed_extensions Allowed file extensions (e.g., ['jpg', 'png']).
 * @param int $max_size Max file size in bytes.
 * @return string|false The new file path relative to the web root (e.g., 'uploads/stories/filename.jpg') on success, false on failure.
 */
function handle_upload($file_input, $sub_dir, $allowed_extensions, $max_size = MAX_FILE_SIZE)
{
    if (!isset($file_input) || $file_input['error'] !== UPLOAD_ERR_OK) {
        if ($file_input['error'] === UPLOAD_ERR_NO_FILE) {
            return false; // No file uploaded, not an error for optional uploads
        }
        set_flash_message('danger', 'File upload error: ' . $file_input['error']);
        return false;
    }

    $file_name = $file_input['name'];
    $file_tmp_path = $file_input['tmp_name'];
    $file_size = $file_input['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_extensions)) {
        set_flash_message('danger', 'Invalid file type. Only ' . implode(', ', $allowed_extensions) . ' are allowed.');
        return false;
    }

    if ($file_size > $max_size) {
        set_flash_message('danger', 'File size exceeds limit (' . ($max_size / 1024 / 1024) . 'MB).');
        return false;
    }

    $upload_path = UPLOAD_DIR_BASE . $sub_dir;
    if (!is_dir($upload_path)) {
        if (!mkdir($upload_path, 0755, true)) {
            set_flash_message('danger', 'Failed to create upload directory: ' . $upload_path);
            return false;
        }
    }
    if (!is_writable($upload_path)) {
        set_flash_message('danger', 'Upload directory is not writable: ' . $upload_path);
        return false;
    }

    $new_file_name = uniqid('upload_', true) . '.' . $file_ext;
    $dest_path = $upload_path . $new_file_name;

    if (move_uploaded_file($file_tmp_path, $dest_path)) {
        return 'uploads/' . $sub_dir . $new_file_name; // Return path relative to web root
    } else {
        set_flash_message('danger', 'Failed to move uploaded file. Check server permissions.');
        return false;
    }
}

/**
 * Updates the total units for a blood group in the blood_stock table.
 * This function should be called after adding/removing/updating blood_inventory items.
 * @param PDO $db The database connection.
 * @param string $blood_group The blood group to update.
 */
function update_blood_stock_summary($db, $blood_group)
{
    $stmt = $db->prepare("
        INSERT INTO blood_stock (blood_group, units, last_updated)
        VALUES (?, (SELECT COUNT(*) FROM blood_inventory WHERE blood_group = ? AND status = 'available'), CURRENT_TIMESTAMP)
        ON CONFLICT(blood_group) DO UPDATE SET
            units = (SELECT COUNT(*) FROM blood_inventory WHERE blood_group = ? AND status = 'available'),
            last_updated = CURRENT_TIMESTAMP;
    ");
    $stmt->execute([$blood_group, $blood_group, $blood_group]);
}

// --- Initialize Database ---
init_db();

// --- Get DB Connection ---
$db = get_db();

// --- Determine Current Page ---
$page = sanitize($_GET['page'] ?? (is_logged_in() ? 'dashboard' : 'home'));

// --- Handle POST Requests (Actions) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // CSRF token validation for all POST actions
    if (!in_array($action, ['login', 'register']) && !validate_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect($page); // Redirect back to the current page with error message
    }

    try {
        switch ($action) {
            case 'login':
                $username = validate_input($_POST['username'], 'string');
                $password = $_POST['password'];

                if (!$username) {
                    set_flash_message('danger', 'Username or email cannot be empty.');
                    break;
                }

                $stmt = $db->prepare("SELECT id, username, password_hash, role, approved, first_login FROM users WHERE username = :username OR email = :username");
                $stmt->execute([':username' => $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['approved'] = $user['approved'];
                    $_SESSION['first_login'] = $user['first_login'];
                    $_SESSION['last_activity'] = time();

                    if ($user['first_login'] == 1 && $user['role'] == 'admin') {
                        set_flash_message('warning', 'Please change your default password immediately for security.');
                        redirect('profile');
                    } else {
                        redirect('dashboard');
                    }
                } else {
                    set_flash_message('danger', 'Invalid username/email or password.');
                }
                break;

            case 'register':
                $full_name = validate_input($_POST['full_name'], 'string', ['min_len' => 3, 'max_len' => 100]);
                $username = validate_input($_POST['username'], 'string', ['min_len' => 3, 'max_len' => 50]);
                $email = validate_input($_POST['email'], 'email');
                $password = $_POST['password'];
                $contact = validate_input($_POST['contact_number'], 'tel');
                $blood_group = validate_input($_POST['blood_group'], 'blood_group');
                $city = validate_input($_POST['city'], 'string', ['min_len' => 2, 'max_len' => 100]);

                $errors = [];
                if (!$full_name) $errors[] = "Full Name is required and must be at least 3 characters.";
                if (!$username) $errors[] = "Username is required and must be at least 3 characters.";
                if (!$email) $errors[] = "Valid Email Address is required.";
                if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters long.";
                if (!$contact) $errors[] = "Valid Contact Number (e.g., 03001234567) is required.";
                if (!$blood_group) $errors[] = "Blood Group is required.";
                if (!$city) $errors[] = "City is required and must be at least 2 characters.";

                if (!empty($errors)) {
                    set_flash_message('danger', implode('<br>', $errors));
                    break;
                }

                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    set_flash_message('warning', 'Username or email already exists. Please choose another.');
                    break;
                }

                $db->beginTransaction();
                $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, contact_number, role, approved) VALUES (?, ?, ?, ?, ?, 'donor', 0)");
                $stmt->execute([$username, $email, $hashed_pass, $full_name, $contact]);
                $user_id = $db->lastInsertId();

                $stmt = $db->prepare("INSERT INTO profiles (user_id, blood_group, city) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $blood_group, $city]);
                $db->commit();

                set_flash_message('success', 'Registration successful! Your account is now pending approval from an administrator.');
                redirect('login');
                break;

            case 'logout':
                session_unset();
                session_destroy();
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(
                        session_name(),
                        '',
                        time() - 42000,
                        $params["path"],
                        $params["domain"],
                        $params["secure"],
                        $params["httponly"]
                    );
                }
                set_flash_message('success', 'You have been logged out successfully.');
                redirect('login');
                break;

            case 'change_password':
                check_auth(['donor', 'admin']);
                $user_id = get_current_user_id();
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];

                if (strlen($new_password) < 8) {
                    set_flash_message('danger', 'New password is too short (minimum 8 characters).');
                    redirect('profile');
                }
                if ($new_password !== $confirm_password) {
                    set_flash_message('danger', 'New passwords do not match.');
                    redirect('profile');
                }

                $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                if ($user && password_verify($current_password, $user['password_hash'])) {
                    $new_hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);
                    $db->beginTransaction();
                    $stmt = $db->prepare("UPDATE users SET password_hash = ?, first_login = 0 WHERE id = ?");
                    $stmt->execute([$new_hashed_pass, $user_id]);
                    $db->commit();
                    $_SESSION['first_login'] = 0; // Mark first login as done
                    set_flash_message('success', 'Password changed successfully.');
                } else {
                    set_flash_message('danger', 'Incorrect current password.');
                }
                redirect('profile');
                break;
            case 'update_profile':
                check_auth(['donor', 'admin']);
                $user_id = get_current_user_id();

                $stmt_old_profile = $db->prepare("SELECT profile_image_url FROM profiles WHERE user_id = ?");
                $stmt_old_profile->execute([$user_id]);
                $old_image_path = $stmt_old_profile->fetchColumn();

                $new_image_path = null;
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $uploaded_file = handle_upload($_FILES['profile_image'], 'profiles/', ALLOWED_IMAGE_EXTENSIONS);
                    if ($uploaded_file) {
                        $new_image_path = $uploaded_file;
                        if ($old_image_path && file_exists(__DIR__ . '/' . $old_image_path)) {
                            unlink(__DIR__ . '/' . $old_image_path);
                        }
                    } else {
                        redirect('profile');
                    }
                }

                $full_name = validate_input($_POST['full_name'], 'string', ['min_len' => 3, 'max_len' => 100]);
                $email = validate_input($_POST['email'], 'email');
                $contact = validate_input($_POST['contact_number'], 'tel');
                $blood_group = validate_input($_POST['blood_group'], 'blood_group');
                $city = validate_input($_POST['city'], 'string', ['min_len' => 2, 'max_len' => 100]);
                $is_available = isset($_POST['is_available']) ? 1 : 0;
                $last_donation_date = empty($_POST['last_donation_date']) ? null : validate_input($_POST['last_donation_date'], 'date');

                $errors = [];
                if (!$full_name) $errors[] = "Full Name is required.";
                if (!$email) $errors[] = "Valid Email is required.";
                if (!$contact) $errors[] = "Valid Contact Number is required.";
                if (!$blood_group) $errors[] = "Blood Group is required.";
                if (!$city) $errors[] = "City is required.";
                if ($last_donation_date === false) $errors[] = "Invalid Last Donation Date format.";

                if (!empty($errors)) {
                    set_flash_message('danger', implode('<br>', $errors));
                    redirect('profile');
                }

                $db->beginTransaction();

                $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, contact_number = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $contact, $user_id]);

                $image_to_update = $new_image_path ?? $old_image_path;

                $stmt_update_profile = $db->prepare("
        UPDATE profiles SET
            blood_group = ?, city = ?, is_available = ?, last_donation_date = ?, updated_at = CURRENT_TIMESTAMP, profile_image_url = ?
        WHERE user_id = ?
    ");
                $stmt_update_profile->execute([$blood_group, $city, $is_available, $last_donation_date, $image_to_update, $user_id]);

                if ($stmt_update_profile->rowCount() == 0) {
                    $stmt_insert_profile = $db->prepare("
            INSERT INTO profiles (user_id, blood_group, city, is_available, last_donation_date, profile_image_url) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
                    $stmt_insert_profile->execute([$user_id, $blood_group, $city, $is_available, $last_donation_date, $image_to_update]);
                }

                $db->commit();

                $_SESSION['profile_image_url'] = $image_to_update;

                set_flash_message('success', 'Your profile has been updated successfully.');
                redirect('profile');
                break;
            case 'create_request':
                check_auth(['donor', 'admin']);
                $patient_name = validate_input($_POST['patient_name'], 'string', ['min_len' => 3, 'max_len' => 100]);
                $blood_group = validate_input($_POST['blood_group'], 'blood_group');
                $city = validate_input($_POST['city'], 'string', ['min_len' => 2, 'max_len' => 100]);
                $hospital_name = validate_input($_POST['hospital_name'], 'string', ['min_len' => 3, 'max_len' => 100]);
                $required_units = validate_input($_POST['required_units'], 'int');
                $urgency = validate_input($_POST['urgency'], 'urgency');
                $contact_person = validate_input($_POST['contact_person'], 'string', ['min_len' => 3, 'max_len' => 100]);
                $contact_number = validate_input($_POST['contact_number'], 'tel');
                $details = validate_input($_POST['details'], 'string', ['max_len' => 500]) ?: null;

                $errors = [];
                if (!$patient_name) $errors[] = "Patient Name is required.";
                if (!$blood_group) $errors[] = "Blood Group is required.";
                if (!$city) $errors[] = "City is required.";
                if (!$hospital_name) $errors[] = "Hospital Name is required.";
                if ($required_units === false || $required_units < 1) $errors[] = "Required Units must be a positive number.";
                if (!$urgency) $errors[] = "Urgency level is required.";
                if (!$contact_person) $errors[] = "Contact Person is required.";
                if (!$contact_number) $errors[] = "Valid Contact Number is required.";

                if (!empty($errors)) {
                    set_flash_message('danger', implode('<br>', $errors));
                    break;
                }

                $stmt = $db->prepare("INSERT INTO requests (patient_name, blood_group, city, hospital_name, required_units, urgency, contact_person, contact_number, details, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $patient_name,
                    $blood_group,
                    $city,
                    $hospital_name,
                    $required_units,
                    $urgency,
                    $contact_person,
                    $contact_number,
                    $details,
                    get_current_user_id()
                ]);
                set_flash_message('success', 'Blood request created successfully.');
                redirect('requests');
                break;

            case 'update_request_status':
                check_auth('admin');
                $request_id = validate_input($_POST['id'], 'int');
                $status = validate_input($_POST['status'], 'status'); // Uses generic status validation

                if (!$request_id || !in_array($status, $request_statuses)) {
                    set_flash_message('danger', 'Invalid request ID or status.');
                    break;
                }

                $db->beginTransaction();
                $stmt = $db->prepare("UPDATE requests SET status = ? WHERE id = ?");
                $stmt->execute([$status, $request_id]);

                // If request is fulfilled, decrement blood stock and log donation
                if ($status === 'fulfilled') {
                    $stmt_req = $db->prepare("SELECT blood_group, required_units FROM requests WHERE id = ?");
                    $stmt_req->execute([$request_id]);
                    $req_info = $stmt_req->fetch();

                    if ($req_info) {
                        // Mark blood bags as 'used' from inventory
                        $stmt_bags = $db->prepare("SELECT id FROM blood_inventory WHERE blood_group = ? AND status = 'available' ORDER BY expiry_date ASC LIMIT ?");
                        $stmt_bags->execute([$req_info['blood_group'], $req_info['required_units']]);
                        $bags_to_use = $stmt_bags->fetchAll(PDO::FETCH_COLUMN);

                        if (count($bags_to_use) < $req_info['required_units']) {
                            // Not enough available bags, revert transaction and warn
                            $db->rollBack();
                            set_flash_message('warning', "Not enough available blood bags of type {$req_info['blood_group']} to fulfill this request. Please add more inventory or fulfill partially.");
                            redirect('requests');
                        }

                        foreach ($bags_to_use as $bag_id) {
                            $db->prepare("UPDATE blood_inventory SET status = 'used', notes = 'Used for request {$request_id}' WHERE id = ?")
                                ->execute([$bag_id]);
                        }

                        // Update overall blood stock summary for the blood group
                        update_blood_stock_summary($db, $req_info['blood_group']);

                        // Log donation (as an internal fulfillment)
                        $db->prepare("INSERT INTO donations (user_id, donation_date, type, request_id, notes) VALUES (?, ?, ?, ?, ?)")
                            ->execute([get_current_user_id(), date('Y-m-d'), 'request', $request_id, 'Fulfilled request ' . $request_id . ' using inventory.']);
                    }
                }
                $db->commit();
                set_flash_message('success', "Request status updated to '{$status}'.");
                redirect('requests');
                break;

            case 'create_drive':
                check_auth('admin');
                $title = validate_input($_POST['title'], 'string', ['min_len' => 5, 'max_len' => 255]);
                $drive_date = validate_input($_POST['drive_date'], 'datetime');
                $location = validate_input($_POST['location'], 'string', ['min_len' => 5, 'max_len' => 255]);
                $location_url = validate_input($_POST['location_url'], 'url') ?: null;
                $organizer = validate_input($_POST['organizer'], 'string', ['min_len' => 3, 'max_len' => 255]);
                $description = validate_input($_POST['description'], 'string', ['max_len' => 1000]) ?: null;

                $errors = [];
                if (!$title) $errors[] = "Drive Title is required.";
                if (!$drive_date) $errors[] = "Valid Date & Time is required.";
                if (!$location) $errors[] = "Location Address is required.";
                if (!$organizer) $errors[] = "Organizer is required.";
                if ($location_url === false && !empty($_POST['location_url'])) $errors[] = "Invalid Location URL format.";

                if (!empty($errors)) {
                    set_flash_message('danger', implode('<br>', $errors));
                    break;
                }

                if (new DateTime($drive_date) < new DateTime()) {
                    set_flash_message('danger', 'Drive date cannot be in the past.');
                    break;
                }

                $stmt = $db->prepare("INSERT INTO drives (title, drive_date, location, location_url, organizer, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $drive_date, $location, $location_url, $organizer, $description, get_current_user_id()]);
                set_flash_message('success', 'Donation drive created successfully.');
                redirect('drives');
                break;

            case 'update_drive_status':
                check_auth('admin');
                $drive_id = validate_input($_POST['id'], 'int');
                $status = validate_input($_POST['status'], 'status'); // Uses generic status validation

                if (!$drive_id || !in_array($status, $drive_statuses)) {
                    set_flash_message('danger', 'Invalid drive ID or status.');
                    break;
                }

                $stmt = $db->prepare("UPDATE drives SET status = ? WHERE id = ?");
                $stmt->execute([$status, $drive_id]);
                set_flash_message('success', "Drive status updated to '{$status}'.");
                redirect('drives');
                break;

            case 'delete_drive':
                check_auth('admin');
                $drive_id = validate_input($_POST['id'], 'int');

                if (!$drive_id) {
                    set_flash_message('danger', 'Invalid drive ID.');
                    break;
                }

                $stmt = $db->prepare("DELETE FROM drives WHERE id = ?");
                $stmt->execute([$drive_id]);
                set_flash_message('success', 'Donation drive deleted.');
                redirect('drives');
                break;

            case 'add_donation':
                check_auth(['donor', 'admin']);
                $user_id = (get_current_user_role() === 'admin' && isset($_POST['user_id'])) ? validate_input($_POST['user_id'], 'int') : get_current_user_id();
                $donation_date = validate_input($_POST['donation_date'], 'date');
                $type = validate_input($_POST['type'], 'string'); // 'request', 'drive', 'voluntary'
                $request_id = validate_input($_POST['request_id'], 'int') ?: null;
                $drive_id = validate_input($_POST['drive_id'], 'int') ?: null;
                $notes = validate_input($_POST['notes'], 'string', ['max_len' => 255]) ?: null;

                $errors = [];
                if (!$user_id) $errors[] = "Donor is required.";
                if (!$donation_date) $errors[] = "Donation Date is required.";
                if (!in_array($type, $donation_types)) $errors[] = "Invalid Donation Type.";
                if (new DateTime($donation_date) > new DateTime()) $errors[] = "Donation date cannot be in the future.";

                if (!empty($errors)) {
                    set_flash_message('danger', implode('<br>', $errors));
                    redirect('donations');
                }

                $db->beginTransaction();
                $stmt = $db->prepare("INSERT INTO donations (user_id, donation_date, type, request_id, drive_id, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $donation_date, $type, $request_id, $drive_id, $notes]);

                // Update donor's profile: last donation date and total donations
                $stmt_profile = $db->prepare("UPDATE profiles SET last_donation_date = ?, total_donations = total_donations + 1, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
                $stmt_profile->execute([$donation_date, $user_id]);
                $db->commit();

                set_flash_message('success', 'Donation recorded successfully.');
                redirect('donations');
                break;

            case 'admin_update_user':
                check_auth('admin');
                $user_id = validate_input($_POST['user_id'], 'int');
                $role = validate_input($_POST['role'], 'role');
                $approved = isset($_POST['approved']) ? 1 : 0;

                if (!$user_id || !$role) {
                    set_flash_message('danger', 'Invalid user ID or role.');
                    break;
                }

                // Prevent demoting the last admin
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                $stmt->execute();
                $admin_count = $stmt->fetchColumn();

                $stmt_current_user_role = $db->prepare("SELECT role FROM users WHERE id = ?");
                $stmt_current_user_role->execute([$user_id]);
                $current_user_role = $stmt_current_user_role->fetchColumn();

                if ($admin_count <= 1 && $user_id === get_current_user_id() && $role !== 'admin') {
                    set_flash_message('danger', 'You cannot demote yourself if you are the last administrator.');
                } else if ($admin_count <= 1 && $current_user_role === 'admin' && $role !== 'admin') {
                    set_flash_message('danger', 'You cannot demote the last administrator.');
                } else {
                    $stmt = $db->prepare("UPDATE users SET role = ?, approved = ? WHERE id = ?");
                    $stmt->execute([$role, $approved, $user_id]);
                    set_flash_message('success', 'User details updated.');
                }
                redirect('admin_users');
                break;

            case 'admin_delete_user':
                check_auth('admin');
                $user_id = validate_input($_POST['id'], 'int');

                if (!$user_id) {
                    set_flash_message('danger', 'Invalid user ID.');
                    break;
                }

                if ($user_id === get_current_user_id()) {
                    set_flash_message('danger', 'You cannot delete your own account.');
                } else {
                    // Prevent deleting the last admin
                    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                    $stmt->execute();
                    $admin_count = $stmt->fetchColumn();

                    $stmt_user_role = $db->prepare("SELECT role FROM users WHERE id = ?");
                    $stmt_user_role->execute([$user_id]);
                    $user_to_delete_role = $stmt_user_role->fetchColumn();

                    if ($admin_count <= 1 && $user_to_delete_role === 'admin') {
                        set_flash_message('danger', 'You cannot delete the last administrator account.');
                    } else {
                        $db->beginTransaction();
                        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $db->commit();
                        set_flash_message('success', 'User has been deleted.');
                    }
                }
                redirect('admin_users');
                break;

            case 'admin_reset_password':
                check_auth('admin');
                $user_id = validate_input($_POST['user_id'], 'int');

                if (!$user_id) {
                    set_flash_message('danger', 'Invalid user ID.');
                    break;
                }

                $new_password = bin2hex(random_bytes(8)); // Generate a random 16-char hex string
                $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);

                $db->beginTransaction();
                $stmt = $db->prepare("UPDATE users SET password_hash = ?, first_login = 1 WHERE id = ?");
                $stmt->execute([$hashed_pass, $user_id]);
                $db->commit();

                set_flash_message('success', "Password has been reset. The new password is: <strong>{$new_password}</strong>. Please provide it to the user securely. User will be prompted to change it on next login.");
                redirect('admin_users');
                break;

            case 'add_announcement':
                check_auth('admin');
                $title = validate_input($_POST['title'], 'string', ['min_len' => 5, 'max_len' => 255]);
                $content = validate_input($_POST['content'], 'string', ['min_len' => 10, 'max_len' => 2000]);

                if (!$title || !$content) {
                    set_flash_message('danger', 'Title and content are required for an announcement.');
                    break;
                }

                $stmt = $db->prepare("INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)");
                $stmt->execute([$title, $content, get_current_user_id()]);
                set_flash_message('success', 'Announcement posted.');
                redirect('admin_announcements');
                break;

            case 'delete_announcement':
                check_auth('admin');
                $announcement_id = validate_input($_POST['id'], 'int');

                if (!$announcement_id) {
                    set_flash_message('danger', 'Invalid announcement ID.');
                    break;
                }

                $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?");
                $stmt->execute([$announcement_id]);
                set_flash_message('success', 'Announcement deleted.');
                redirect('admin_announcements');
                break;

            case 'add_story':
                check_auth('admin');
                $title = validate_input($_POST['title'], 'string', ['min_len' => 5, 'max_len' => 255]);
                $content = validate_input($_POST['content'], 'string', ['min_len' => 10, 'max_len' => 2000]);
                $image_url = null;

                if (!$title || !$content) {
                    set_flash_message('danger', 'Title and content are required for a story.');
                    break;
                }

                if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploaded_file_path = handle_upload($_FILES['image'], 'stories/', ALLOWED_IMAGE_EXTENSIONS);
                    if ($uploaded_file_path === false) {
                        // Error message already set by handle_upload
                        break;
                    }
                    $image_url = $uploaded_file_path;
                }

                $stmt = $db->prepare("INSERT INTO stories (title, content, created_by, image_url) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $content, get_current_user_id(), $image_url]);
                set_flash_message('success', 'Story added successfully.');
                redirect('admin_stories');
                break;

            case 'delete_story':
                check_auth('admin');
                $story_id = validate_input($_POST['id'], 'int');

                if (!$story_id) {
                    set_flash_message('danger', 'Invalid story ID.');
                    break;
                }

                $stmt = $db->prepare("SELECT image_url FROM stories WHERE id = ?");
                $stmt->execute([$story_id]);
                $story = $stmt->fetch();

                if ($story && $story['image_url'] && file_exists(__DIR__ . '/' . $story['image_url'])) {
                    unlink(__DIR__ . '/' . $story['image_url']); // Delete the actual file
                }

                $stmt = $db->prepare("DELETE FROM stories WHERE id = ?");
                $stmt->execute([$story_id]);
                set_flash_message('success', 'Story deleted.');
                redirect('admin_stories');
                break;

            case 'add_news':
                check_auth('admin');
                $title = validate_input($_POST['title'], 'string', ['min_len' => 5, 'max_len' => 255]);
                $content = validate_input($_POST['content'], 'string', ['min_len' => 10, 'max_len' => 2000]);
                $image_url = null;

                if (!$title || !$content) {
                    set_flash_message('danger', 'Title and content are required for a news article.');
                    break;
                }

                if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploaded_file_path = handle_upload($_FILES['image'], 'news/', ALLOWED_IMAGE_EXTENSIONS);
                    if ($uploaded_file_path === false) {
                        // Error message already set by handle_upload
                        break;
                    }
                    $image_url = $uploaded_file_path;
                }

                $stmt = $db->prepare("INSERT INTO news (title, content, created_by, image_url) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $content, get_current_user_id(), $image_url]);
                set_flash_message('success', 'News article added successfully.');
                redirect('admin_news');
                break;

            case 'delete_news':
                check_auth('admin');
                $news_id = validate_input($_POST['id'], 'int');

                if (!$news_id) {
                    set_flash_message('danger', 'Invalid news ID.');
                    break;
                }

                $stmt = $db->prepare("SELECT image_url FROM news WHERE id = ?");
                $stmt->execute([$news_id]);
                $news_item = $stmt->fetch();

                if ($news_item && $news_item['image_url'] && file_exists(__DIR__ . '/' . $news_item['image_url'])) {
                    unlink(__DIR__ . '/' . $news_item['image_url']); // Delete the actual file
                }

                $stmt = $db->prepare("DELETE FROM news WHERE id = ?");
                $stmt->execute([$news_id]);
                set_flash_message('success', 'News article deleted.');
                redirect('admin_news');
                break;

            case 'add_blood_bag':
                check_auth('admin');
                $donor_id = validate_input($_POST['donor_id'], 'int');
                $collection_date = validate_input($_POST['collection_date'], 'date');
                $custom_expiry_date = validate_input($_POST['expiry_date'] ?? '', 'date') ?: null;
                $notes = validate_input($_POST['notes'], 'string', ['max_len' => 255]) ?: null;

                $errors = [];
                if (!$donor_id) $errors[] = "Donor is required.";
                if (!$collection_date) $errors[] = "Collection Date is required.";
                if ($custom_expiry_date && (new DateTime($custom_expiry_date) < new DateTime($collection_date))) {
                    $errors[] = "Expiry date cannot be before the collection date.";
                }

                if (!empty($errors)) {
                    set_flash_message('danger', implode('<br>', $errors));
                    redirect('admin_blood_bank');
                }

                $stmt_donor = $db->prepare("SELECT blood_group FROM profiles WHERE user_id = ?");
                $stmt_donor->execute([$donor_id]);
                $donor_profile = $stmt_donor->fetch();

                if (!$donor_profile) {
                    set_flash_message('danger', 'Selected donor profile not found.');
                    redirect('admin_blood_bank');
                }

                $blood_group = $donor_profile['blood_group'];
                $expiry_date = $custom_expiry_date ?: date('Y-m-d', strtotime($collection_date . ' +42 days')); // Default 42 days (6 weeks)

                $bag_id = strtoupper('BAG-' . bin2hex(random_bytes(6))); // Generate unique bag ID

                $db->beginTransaction();
                $stmt = $db->prepare("INSERT INTO blood_inventory (bag_id, blood_group, donor_id, collection_date, expiry_date, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$bag_id, $blood_group, $donor_id, $collection_date, $expiry_date, $notes]);

                // Log this as a voluntary donation for the donor
                $stmt_donation = $db->prepare("INSERT INTO donations (user_id, donation_date, type, notes) VALUES (?, ?, 'voluntary', ?)");
                $stmt_donation->execute([$donor_id, $collection_date, "Donation for bag ID: {$bag_id}"]);

                // Update donor's profile: last donation date and total donations
                $stmt_profile = $db->prepare("UPDATE profiles SET last_donation_date = ?, total_donations = total_donations + 1, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
                $stmt_profile->execute([$collection_date, $donor_id]);

                // Update overall blood stock summary
                update_blood_stock_summary($db, $blood_group);

                $db->commit();
                set_flash_message('success', "Blood bag {$bag_id} added to inventory successfully.");
                redirect('admin_blood_bank');
                break;

            case 'update_bag_status':
                check_auth('admin');
                $inventory_id = validate_input($_POST['inventory_id'], 'int');
                $new_status = validate_input($_POST['status'], 'blood_bag_status');
                $expiry_date = validate_input($_POST['expiry_date'], 'date');

                $errors = [];
                if (!$inventory_id) $errors[] = "Bag ID is invalid.";
                if (!$new_status) $errors[] = "Status value is invalid.";
                if (!$expiry_date) $errors[] = "Expiry Date is invalid or not in YYYY-MM-DD format.";

                if (!empty($errors)) {
                    set_flash_message('danger', "Update failed: " . implode(" ", $errors));
                    redirect('admin_blood_bank');
                }

                $db->beginTransaction();

                // Get current bag info to determine if stock needs update
                $stmt_current_bag = $db->prepare("SELECT blood_group, status FROM blood_inventory WHERE id = ?");
                $stmt_current_bag->execute([$inventory_id]);
                $current_bag = $stmt_current_bag->fetch();

                if (!$current_bag) {
                    $db->rollBack();
                    set_flash_message('danger', 'Blood bag not found.');
                    redirect('admin_blood_bank');
                }

                $old_status = $current_bag['status'];
                $blood_group = $current_bag['blood_group'];

                $stmt = $db->prepare("UPDATE blood_inventory SET status = ?, expiry_date = ? WHERE id = ?");
                $stmt->execute([$new_status, $expiry_date, $inventory_id]);

                // Update overall blood stock summary if status changed availability
                if ($old_status === 'available' && $new_status !== 'available') {
                    update_blood_stock_summary($db, $blood_group);
                } elseif ($old_status !== 'available' && $new_status === 'available') {
                    update_blood_stock_summary($db, $blood_group);
                }

                $db->commit();
                set_flash_message('success', "Bag details updated successfully.");
                redirect('admin_blood_bank');
                break;

            case 'export_inventory_csv':
                check_auth('admin');
                // Clear any previous output buffering
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="lifeflow_inventory_' . date('Y-m-d_H-i-s') . '.csv"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');

                $inventory_data = $db->query("
                    SELECT i.bag_id, i.blood_group, u.full_name as donor_name, i.collection_date, i.expiry_date, i.status, i.notes, i.created_at
                    FROM blood_inventory i
                    LEFT JOIN users u ON i.donor_id = u.id
                    ORDER BY i.collection_date DESC
                ")->fetchAll(PDO::FETCH_ASSOC);

                $output = fopen('php://output', 'w');
                fputcsv($output, ['Bag ID', 'Blood Group', 'Donor Name', 'Collection Date', 'Expiry Date', 'Status', 'Notes', 'Record Created At']);
                foreach ($inventory_data as $row) {
                    fputcsv($output, $row);
                }
                fclose($output);
                exit; // Terminate script after file download

            case 'backup_db':
                check_auth('admin');
                // Clear any previous output buffering
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="lifeflow_backup_' . date('Y-m-d_H-i-s') . '.sqlite"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize(DB_FILE));

                readfile(DB_FILE);
                exit; // Terminate script after file download

            case 'restore_db':
                check_auth('admin');
                if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp_path = $_FILES['backup_file']['tmp_name'];
                    $file_name = $_FILES['backup_file']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    if ($file_ext === 'sqlite') {
                        // Close the current DB connection before overwriting the file
                        $db = null;

                        // Temporarily change journal mode to DELETE for safe overwrite
                        // This is a common practice for SQLite file replacement
                        $temp_db = new PDO('sqlite:' . DB_FILE);
                        $temp_db->exec("PRAGMA journal_mode = DELETE;");
                        $temp_db = null; // Close connection

                        if (move_uploaded_file($file_tmp_path, DB_FILE)) {
                            set_flash_message('success', 'Database restored successfully. You will be logged out for security.');
                            session_destroy(); // Destroy session after restore
                            redirect('login');
                        } else {
                            set_flash_message('danger', 'Failed to move uploaded file. Check server permissions.');
                        }
                    } else {
                        set_flash_message('danger', 'Invalid file type. Please upload a .sqlite file.');
                    }
                } else {
                    set_flash_message('danger', 'File upload error. Code: ' . ($_FILES['backup_file']['error'] ?? 'N/A'));
                }
                redirect('admin_backup');
                break;

            case 'send_message':
                $sender_id = get_current_user_id();
                $sender_name = validate_input($_POST['sender_name'], 'string', ['min_len' => 3, 'max_len' => 100]);
                $sender_email = validate_input($_POST['sender_email'], 'email');
                $subject = validate_input($_POST['subject'], 'string', ['min_len' => 5, 'max_len' => 255]);
                $message_content = validate_input($_POST['message'], 'string', ['min_len' => 10, 'max_len' => 2000]);

                $errors = [];
                if (!$sender_name) $errors[] = "Your Name is required.";
                if (!$sender_email) $errors[] = "Valid Email is required.";
                if (!$subject) $errors[] = "Subject is required.";
                if (!$message_content) $errors[] = "Message content is required.";

                if (!empty($errors)) {
                    set_flash_message('danger', implode('<br>', $errors));
                    redirect('contact');
                }

                $stmt = $db->prepare("INSERT INTO messages (sender_id, sender_name, sender_email, subject, message) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$sender_id, $sender_name, $sender_email, $subject, $message_content]);
                set_flash_message('success', 'Your message has been sent to the administrators. We will get back to you soon!');
                redirect('contact');
                break;

            case 'mark_message_read':
                check_auth('admin');
                $message_id = validate_input($_POST['message_id'], 'int');
                if ($message_id) {
                    $stmt = $db->prepare("UPDATE messages SET read_status = 1 WHERE id = ?");
                    $stmt->execute([$message_id]);
                    set_flash_message('success', 'Message marked as read.');
                } else {
                    set_flash_message('danger', 'Invalid message ID.');
                }
                redirect('admin_messages');
                break;

            case 'delete_message':
                check_auth('admin');
                $message_id = validate_input($_POST['message_id'], 'int');
                if ($message_id) {
                    $stmt = $db->prepare("DELETE FROM messages WHERE id = ?");
                    $stmt->execute([$message_id]);
                    set_flash_message('success', 'Message deleted.');
                } else {
                    set_flash_message('danger', 'Invalid message ID.');
                }
                redirect('admin_messages');
                break;

            default:
                set_flash_message('danger', 'Unknown action requested.');
                break;
        }
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("PDOException in action '{$action}': " . $e->getMessage());
        set_flash_message('danger', 'A database error occurred. Please try again or contact support.');
    } catch (Exception $e) {
        error_log("Exception in action '{$action}': " . $e->getMessage());
        set_flash_message('danger', 'An unexpected error occurred. Please try again.');
    }
}

// --- Page Access Control ---
$public_pages = ['home', 'login', 'register', 'announcements', 'news', 'stories', 'how_to_donate', 'contact', 'certificate'];

// Redirect to login if not logged in and trying to access a protected page
if (!is_logged_in() && !in_array($page, $public_pages)) {
    set_flash_message('warning', 'Please log in to view this page.');
    redirect('login');
}

// Redirect logged-in users from login/register/home to dashboard
if (is_logged_in() && in_array($page, ['login', 'register', 'home'])) {
    redirect('dashboard');
}

// Force admin to change password on first login
if (is_logged_in() && get_current_user_role() === 'admin' && ($_SESSION['first_login'] ?? 0) == 1 && $page !== 'profile') {
    set_flash_message('warning', 'For security, please change your default password immediately.');
    redirect('profile');
}

// --- Generate CSRF Token for Forms ---
$csrf_token = generate_csrf_token();

?>
<?php
$csrf_token = generate_csrf_token();
switch ($page) {
    case 'certificate':
        // This page is designed to be standalone for printing/downloading
        $user_id = validate_input($_GET['user_id'] ?? 0, 'int');
        $period_text = sanitize($_GET['period_text'] ?? 'their outstanding contributions');

        if (!$user_id) {
            die('Invalid User ID.');
        }

        $stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $donor = $stmt->fetch();

        if (!$donor) {
            die('Donor not found.');
        }
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <title>Certificate of Appreciation for <?= sanitize($donor['full_name']) ?></title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
            <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
            <!-- Styles are embedded in the head for self-contained certificate -->
            <style>
                @page {
                    size: A4 landscape;
                    margin: 0;
                }

                body {
                    margin: 0;
                    padding: 2cm;
                    font-family: 'Merriweather', serif;
                    color: #333;
                    background-color: #f0f0f0;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    box-sizing: border-box;
                }

                .certificate-container {
                    width: 25.7cm;
                    height: 17cm;
                    position: relative;
                    background-color: white;
                    border: 10px solid #006A4E;
                    box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
                    padding: 2cm;
                    box-sizing: border-box;
                }

                .certificate-border {
                    position: absolute;
                    top: 10px;
                    left: 10px;
                    right: 10px;
                    bottom: 10px;
                    border: 3px solid #FFC72C;
                }

                .content {
                    text-align: center;
                }

                .logo svg {
                    width: 48px;
                    height: 48px;
                    fill: #006A4E;
                }

                h1.certificate-title {
                    font-family: 'Great Vibes', cursive;
                    font-size: 3.5rem;
                    color: #006A4E;
                    margin: 20px 0 10px;
                    font-weight: normal;
                }

                h2.certificate-subtitle {
                    font-size: 1.5rem;
                    font-weight: normal;
                    margin-bottom: 20px;
                }

                .donor-name {
                    font-family: 'Great Vibes', cursive;
                    font-size: 4rem;
                    color: #006A4E;
                    border-bottom: 2px solid #FFC72C;
                    display: inline-block;
                    padding-bottom: 5px;
                    margin: 20px 0;
                    text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.2);
                }

                .main-text {
                    font-size: 1.1rem;
                    line-height: 1.6;
                    margin: 20px auto;
                    max-width: 80%;
                }

                .certificate-footer {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 50px;
                    position: absolute;
                    bottom: 6cm;
                    width: calc(100% - 4cm);
                }

                .signature {
                    border-top: 1px solid #333;
                    padding-top: 5px;
                    width: 200px;
                }

                .action-button {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 10px 20px;
                    font-size: 1rem;
                    background: #006A4E;
                    color: white;
                    border: none;
                    cursor: pointer;
                    border-radius: 5px;
                    z-index: 100;
                }

                .action-button:disabled {
                    background: #6c757d;
                    cursor: not-allowed;
                }

                .watermark-img {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    width: 290px;
                    height: 290px;
                    border-radius: 50%;
                    object-fit: cover;
                    opacity: 0.45;
                    z-index: -1;
                }

                @media print {
                    @page {
                        margin: 0;
                    }

                    body {
                        padding: 0;
                    }

                    .certificate-container {
                        box-shadow: none;
                        width: 29.7cm;
                        height: 21cm;
                        border: none;
                        padding: 0;
                    }

                    .certificate-border {
                        display: none;
                    }

                    .action-button {
                        display: none;
                    }

                    .certificate-footer {
                        bottom: 4cm;
                        /* Adjust for print layout */
                    }
                }
            </style>
        </head>

        <body>
            <button id="downloadBtn" class="action-button">Download Image</button>
            <div id="certificate" class="certificate-container">
                <div class="certificate-border"></div>
                <div class="content">
                    <div class="logo">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#006A4E" class="bi bi-award-fill" viewBox="0 0 16 16">
                            <defs>
                                <!-- Gradient for the blood effect -->
                                <linearGradient id="bloodGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" style="stop-color:#8B0000;stop-opacity:1" />
                                    <stop offset="50%" style="stop-color:#DC143C;stop-opacity:1" />
                                    <stop offset="100%" style="stop-color:#B22222;stop-opacity:1" />
                                </linearGradient>

                                <!-- Clipping path for the blood level (80% filled) -->
                                <clipPath id="bloodLevel">
                                    <rect x="0" y="3.2" width="16" height="11.2" />
                                </clipPath>
                            </defs>

                            <!-- Blood drop outline -->
                            <path d="M8 1C6.5 2.5 3 6 3 10c0 2.76 2.24 5 5 5s5-2.24 5-5c0-4-3.5-7.5-5-9z"
                                fill="none"
                                stroke="#8B0000"
                                stroke-width="0.3" />

                            <!-- Blood fill (80% filled) -->
                            <path d="M8 1C6.5 2.5 3 6 3 10c0 2.76 2.24 5 5 5s5-2.24 5-5c0-4-3.5-7.5-5-9z"
                                fill="url(#bloodGradient)"
                                clip-path="url(#bloodLevel)" />

                            <!-- Small highlight for realistic effect -->
                            <ellipse cx="6.5" cy="5" rx="1" ry="1.5" fill="#FF6B6B" opacity="0.6" />
                        </svg>
                    </div>
                    <h2 class="certificate-subtitle">Certificate of Appreciation</h2>
                    <h1 class="certificate-title">Proudly Presented To</h1>
                    <div id="donorName" class="donor-name"><?= sanitize($donor['full_name']) ?></div>
                    <p class="main-text">
                        In sincere recognition of your selfless and life-saving blood donations.
                        Your invaluable contribution is a beacon of hope and generosity in our community.
                    </p>
                    <p>This certificate is awarded for your donations for the period of<br><strong><?= $period_text ?></strong>.</p>
                    <div class="certificate-footer">
                        <div class="signature">
                            <strong>Yasin Ullah</strong><br>
                            <small>Project Director, <?= SITE_NAME ?></small>
                        </div>
                        <div class="signature">
                            <strong>Date</strong><br>
                            <small><?= date('F j, Y') ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                document.getElementById('downloadBtn').addEventListener('click', function() {
                    const button = this;
                    const certificateElement = document.getElementById('certificate');
                    const donorName = "<?= addslashes(sanitize($donor['full_name'])) ?>";
                    const fileName = `Certificate-${donorName.replace(/ /g, '-')}.png`;

                    button.disabled = true;
                    button.textContent = 'Processing...';

                    html2canvas(certificateElement, {
                        scale: 2, // Increase scale for better resolution
                        useCORS: true, // Enable CORS for images if any
                        backgroundColor: null // Keep background transparent if needed
                    }).then(canvas => {
                        const link = document.createElement('a');
                        link.download = fileName;
                        link.href = canvas.toDataURL('image/png');
                        link.click();

                        button.disabled = false;
                        button.textContent = 'Download Image';
                    }).catch(err => {
                        console.error('Error generating image:', err);
                        alert('Could not generate image. Please try again.');
                        button.disabled = false;
                        button.textContent = 'Download Image';
                    });
                });

                // Watermark functionality
                const donorNameElement = document.getElementById('donorName');
                donorNameElement.style.position = 'relative';
                donorNameElement.style.cursor = 'pointer';
                donorNameElement.style.zIndex = '1'; // Ensure it's above the watermark

                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.accept = 'image/png, image/jpeg';
                fileInput.style.display = 'none'; // Hide the input

                // Append file input to body (or any accessible element)
                document.body.appendChild(fileInput);

                donorNameElement.addEventListener('click', () => {
                    fileInput.click(); // Trigger file input click on name click
                });

                fileInput.addEventListener('change', function(event) {
                    const file = event.target.files[0];
                    if (!file) {
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Remove existing watermark if any
                        const existingWatermark = donorNameElement.querySelector('.watermark-img');
                        if (existingWatermark) {
                            existingWatermark.remove();
                        }

                        const watermarkImg = document.createElement('img');
                        watermarkImg.src = e.target.result;
                        watermarkImg.className = 'watermark-img'; // Apply CSS class for styling

                        donorNameElement.appendChild(watermarkImg);
                    };
                    reader.readAsDataURL(file); // Read file as data URL
                });
            </script>
        </body>

        </html>
    <?php
        exit; // Stop further rendering for certificate page
        break;

    case 'home':
        $stats = $db->query("
                                    SELECT
                                        (SELECT COUNT(*) FROM users WHERE role='donor' AND approved=1) as total_donors,
                                        (SELECT COUNT(*) FROM requests WHERE status='fulfilled') as lives_saved,
                                        (SELECT COUNT(*) FROM drives WHERE status='upcoming' AND drive_date > CURRENT_TIMESTAMP) as upcoming_drives,
                                        (SELECT COUNT(*) FROM blood_inventory WHERE status = 'available') as total_stock_units
                                ")->fetch();

        $latest_announcements = $db->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3")->fetchAll();
        $urgent_requests = $db->query("SELECT * FROM requests WHERE status = 'pending' AND (urgency = 'emergency' OR urgency = 'urgent') ORDER BY CASE urgency WHEN 'emergency' THEN 1 WHEN 'urgent' THEN 2 END, created_at DESC LIMIT 4")->fetchAll();
        $upcoming_drives = $db->query("SELECT * FROM drives WHERE status = 'upcoming' AND drive_date > CURRENT_TIMESTAMP ORDER BY drive_date ASC LIMIT 3")->fetchAll();
        $latest_stories = $db->query("SELECT * FROM stories ORDER BY created_at DESC LIMIT 2")->fetchAll();
        $top_donors = $db->query("
                                    SELECT u.full_name, p.blood_group, p.total_donations
                                    FROM profiles p
                                    JOIN users u ON p.user_id = u.id
                                    WHERE u.approved = 1 AND p.total_donations > 0
                                    ORDER BY p.total_donations DESC
                                    LIMIT 5
                                ")->fetchAll();
    ?>
        <div class="container my-5 py-5 hero-section">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold lh-1 mb-3">Become a Hero. <br><span class="text-primary">Donate Blood, Save Lives.</span></h1>
                    <p class="lead">Join our community of voluntary blood donors. Your single donation can save up to three lives. Find requests near you, get notified about donation drives, and be a beacon of hope.</p>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                        <a href="?page=register" class="btn btn-primary btn-lg px-4 me-md-2"><?= render_icon('person-plus-fill') ?> Register Now</a>
                        <a href="?page=login" class="btn btn-outline-secondary btn-lg px-4"><?= render_icon('box-arrow-in-right') ?> Login</a>
                    </div>
                </div>
                <div class="col-lg-6 text-center d-none d-lg-block">
                    <!-- Optional: Add an image or illustration here -->
                </div>
            </div>
        </div>

        <div class="container mb-5">
            <div class="row text-center my-5 py-4 bg-white rounded shadow-sm">
                <div class="col-md-3 col-6 mb-3 mb-md-0">
                    <h3><?= render_icon('people-fill', 'text-primary') ?> <?= sanitize($stats['total_donors']) ?>+</h3>
                    <p class="text-muted">Registered Donors</p>
                </div>
                <div class="col-md-3 col-6 mb-3 mb-md-0">
                    <h3><?= render_icon('heart-pulse-fill', 'text-danger') ?> <?= sanitize($stats['lives_saved']) ?>+</h3>
                    <p class="text-muted">Lives Saved</p>
                </div>
                <div class="col-md-3 col-6 mb-3 mb-md-0">
                    <h3><?= render_icon('hospital', 'text-info') ?> <?= sanitize($stats['upcoming_drives']) ?></h3>
                    <p class="text-muted">Upcoming Drives</p>
                </div>
                <div class="col-md-3 col-6 mb-3 mb-md-0">
                    <h3><?= render_icon('bank', 'text-success') ?> <?= sanitize($stats['total_stock_units']) ?>+</h3>
                    <p class="text-muted">Units in Stock</p>
                </div>
            </div>

            <h2 class="text-center mb-4 mt-5">Urgent Needs</h2>
            <div class="row">
                <?php if (!empty($urgent_requests)) : ?>
                    <?php foreach ($urgent_requests as $req) : ?>
                        <div class="col-md-6 col-lg-3 mb-4">
                            <div class="card h-100 shadow-sm urgency-<?= sanitize($req['urgency']) ?>">
                                <div class="card-body text-center">
                                    <h2 class="card-title text-danger fw-bold"><?= sanitize($req['blood_group']) ?></h2>
                                    <p class="card-text text-muted"><?= render_icon('hospital') ?> <?= sanitize($req['hospital_name']) ?>, <?= sanitize($req['city']) ?></p>
                                    <span class="badge bg-urgency-<?= sanitize($req['urgency']) ?> text-uppercase"><?= sanitize($req['urgency']) ?></span>
                                </div>
                                <div class="card-footer bg-white border-0 text-center">
                                    <a href="?page=login" class="btn btn-sm btn-outline-primary">Login to Help</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="col-12">
                        <div class="alert alert-success text-center">No urgent requests at the moment. Thank you to all our donors!</div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row mt-5">
                <div class="col-lg-8">
                    <h2 class="mb-4">Upcoming Donation Drives</h2>
                    <?php if (!empty($upcoming_drives)) : ?>
                        <?php foreach ($upcoming_drives as $drive) : ?>
                            <div class="card mb-3 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title text-primary"><?= sanitize($drive['title']) ?></h5>
                                    <p class="card-text mb-1"><small class="text-muted"><?= render_icon('calendar-event') ?> <?= date('F j, Y, g:i A', strtotime($drive['drive_date'])) ?></small></p>
                                    <p class="card-text"><small class="text-muted"><?= render_icon('geo-alt') ?> <?= sanitize($drive['location']) ?></small></p>
                                    <a href="?page=drives" class="stretched-link">More Info</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class="alert alert-info">No upcoming drives scheduled. Check back soon!</div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-4">
                    <h2 class="mb-4">Inspiring Stories</h2>
                    <?php if (!empty($latest_stories)) : ?>
                        <?php foreach ($latest_stories as $story) : ?>
                            <div class="card mb-3 shadow-sm">
                                <?php if ($story['image_url']) : ?>
                                    <img src="<?= sanitize($story['image_url']) ?>" class="card-img-top" alt="<?= sanitize($story['title']) ?>" style="height: 150px; object-fit: cover;">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h6 class="card-title"><?= sanitize($story['title']) ?></h6>
                                    <p class="card-text small text-muted"><?= nl2br(substr(sanitize($story['content']), 0, 80)) ?>...</p>
                                    <a href="?page=stories" class="stretched-link">Read Story</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row mt-5">
                <div class="col-12">
                    <h2 class="text-center mb-4"><?= render_icon('trophy-fill', 'text-warning') ?> Our Top Heroes</h2>
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <?php if (!empty($top_donors)) : ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover align-middle text-center">
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>Hero Name</th>
                                                <th>Blood Group</th>
                                                <th>Total Donations</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $rank = 1;
                                            foreach ($top_donors as $donor) : ?>
                                                <tr>
                                                    <td class="fw-bold fs-5"><?= $rank++ ?></td>
                                                    <td><?= sanitize($donor['full_name']) ?></td>
                                                    <td class="fw-bold text-danger"><?= sanitize($donor['blood_group']) ?></td>
                                                    <td><span class="badge bg-primary rounded-pill fs-6"><?= sanitize($donor['total_donations']) ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="?page=login" class="btn btn-outline-primary">Login to see the full Leaderboard</a>
                                </div>
                            <?php else : ?>
                                <div class="alert alert-info text-center mb-0">Our leaderboard is waiting for its first heroes!</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <h2 class="text-center mb-4 mt-5">Latest Announcements</h2>
            <div class="row">
                <?php if (!empty($latest_announcements)) : ?>
                    <?php foreach ($latest_announcements as $ann) : ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title text-primary"><?= sanitize($ann['title']) ?></h5>
                                    <p class="card-text text-muted small"><?= date('F j, Y', strtotime($ann['created_at'])) ?></p>
                                    <p class="card-text"><?= nl2br(substr(sanitize($ann['content']), 0, 120)) ?><?= strlen($ann['content']) > 120 ? '...' : '' ?></p>
                                    <a href="?page=announcements" class="stretched-link">Read more</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">No announcements available yet.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php
        break;

    case 'login':
    ?>
        <div class="row justify-content-center my-5">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <h2 class="card-title text-center mb-4 text-primary">Welcome Back</h2>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="login">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="username" name="username" placeholder="Username or Email" required aria-label="Username or Email">
                                <label for="username">Username or Email</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required aria-label="Password">
                                <label for="password">Password</label>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg"><?= render_icon('box-arrow-in-right') ?> Login</button>
                            </div>
                        </form>
                        <div class="text-center mt-4">
                            <p class="mb-0">Don't have an account? <a href="?page=register" class="text-primary fw-bold">Register here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php
        break;

    case 'register':
    ?>
        <div class="row justify-content-center my-5">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <h2 class="card-title text-center mb-4 text-primary">Join Our Lifesaving Community</h2>
                        <form method="post" action="" class="row g-3">
                            <input type="hidden" name="action" value="register">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <div class="col-md-12">
                                <label for="reg_full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="reg_full_name" name="full_name" required aria-label="Full Name">
                            </div>
                            <div class="col-md-6">
                                <label for="reg_username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="reg_username" name="username" required aria-label="Username">
                            </div>
                            <div class="col-md-6">
                                <label for="reg_email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="reg_email" name="email" required aria-label="Email Address">
                            </div>
                            <div class="col-md-12">
                                <label for="reg_password" class="form-label">Password (min 8 characters)</label>
                                <input type="password" class="form-control" id="reg_password" name="password" minlength="8" required aria-label="Password">
                            </div>
                            <div class="col-md-6">
                                <label for="reg_contact" class="form-label">Contact Number (e.g., 03001234567)</label>
                                <input type="tel" class="form-control" id="reg_contact" name="contact_number" pattern="^03\d{9}$" title="Enter a valid Pakistani mobile number (e.g., 03001234567)" required aria-label="Contact Number">
                            </div>
                            <div class="col-md-6">
                                <label for="reg_city" class="form-label">City</label>
                                <input type="text" class="form-control" id="reg_city" name="city" required aria-label="City">
                            </div>
                            <div class="col-md-12">
                                <label for="reg_blood_group" class="form-label">Blood Group</label>
                                <select id="reg_blood_group" name="blood_group" class="form-select" required aria-label="Blood Group">
                                    <option selected disabled value="">Choose...</option>
                                    <?php foreach ($blood_groups as $bg) : ?>
                                        <option value="<?= $bg ?>"><?= $bg ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-lg"><?= render_icon('person-plus-fill') ?> Create Account</button>
                            </div>
                        </form>
                        <div class="text-center mt-4">
                            <p class="mb-0">Already have an account? <a href="?page=login" class="text-primary fw-bold">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php
        break;

    case 'dashboard':
        check_auth(['donor', 'admin']);
        $user_id = get_current_user_id();

        $stats = $db->query("
                                    SELECT
                                        (SELECT COUNT(*) FROM users WHERE role='donor' AND approved=1) as total_donors,
                                        (SELECT COUNT(*) FROM requests WHERE status='pending') as pending_requests,
                                        (SELECT COUNT(*) FROM drives WHERE status='upcoming' AND drive_date > CURRENT_TIMESTAMP) as upcoming_drives,
                                        (SELECT COUNT(*) FROM blood_inventory WHERE status = 'available') as total_stock_units
                                ")->fetch();

        $user_profile_stmt = $db->prepare("SELECT p.*, u.full_name, u.email FROM profiles p JOIN users u ON u.id = p.user_id WHERE p.user_id = ?");
        $user_profile_stmt->execute([$user_id]);
        $profile = $user_profile_stmt->fetch();

        $my_requests_stmt = $db->prepare("SELECT * FROM requests WHERE created_by = ? ORDER BY created_at DESC LIMIT 5");
        $my_requests_stmt->execute([$user_id]);
        $my_requests = $my_requests_stmt->fetchAll();

        $matching_requests = [];
        if ($profile && $profile['blood_group']) {
            $matching_requests_stmt = $db->prepare("SELECT * FROM requests WHERE status = 'pending' AND blood_group = ? ORDER BY created_at DESC LIMIT 5");
            $matching_requests_stmt->execute([$profile['blood_group']]);
            $matching_requests = $matching_requests_stmt->fetchAll();
        }

        // Get live blood stock counts from inventory
        $dashboard_stock = array_fill_keys($blood_groups, ['units' => 0, 'last_updated' => 'N/A']);
        $live_stock_counts = $db->query("
                                    SELECT blood_group, COUNT(*) as units, MAX(created_at) as last_updated
                                    FROM blood_inventory
                                    WHERE status = 'available'
                                    GROUP BY blood_group
                                ")->fetchAll();

        foreach ($live_stock_counts as $stock) {
            if (isset($dashboard_stock[$stock['blood_group']])) {
                $dashboard_stock[$stock['blood_group']] = [
                    'units' => $stock['units'],
                    'last_updated' => $stock['last_updated']
                ];
            }
        }
    ?>
        <h1 class="mb-4">Dashboard</h1>
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-start border-primary border-4 h-100 shadow-sm">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Donors</div>
                                <div class="h5 mb-0 fw-bold text-gray-800"><?= sanitize($stats['total_donors']) ?></div>
                            </div>
                            <div class="col-auto">
                                <?= render_icon('people-fill', 'fs-2 text-primary opacity-50') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-start border-success border-4 h-100 shadow-sm">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs fw-bold text-success text-uppercase mb-1">Available Units</div>
                                <div class="h5 mb-0 fw-bold text-gray-800"><?= sanitize($stats['total_stock_units']) ?></div>
                            </div>
                            <div class="col-auto">
                                <?= render_icon('droplet-fill', 'fs-2 text-success opacity-50') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-start border-info border-4 h-100 shadow-sm">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs fw-bold text-info text-uppercase mb-1">Upcoming Drives</div>
                                <div class="h5 mb-0 fw-bold text-gray-800"><?= sanitize($stats['upcoming_drives']) ?></div>
                            </div>
                            <div class="col-auto">
                                <?= render_icon('hospital-fill', 'fs-2 text-info opacity-50') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-start border-warning border-4 h-100 shadow-sm">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs fw-bold text-warning text-uppercase mb-1">Pending Requests</div>
                                <div class="h5 mb-0 fw-bold text-gray-800"><?= sanitize($stats['pending_requests']) ?></div>
                            </div>
                            <div class="col-auto">
                                <?= render_icon('hourglass-split', 'fs-2 text-warning opacity-50') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Requests Matching Your Blood Group (<?= sanitize($profile['blood_group'] ?? 'N/A') ?>)</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($profile && !empty($matching_requests)) : ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($matching_requests as $req) : ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= sanitize($req['hospital_name']) ?></strong>, <?= sanitize($req['city']) ?>
                                            <span class="badge bg-urgency-<?= sanitize($req['urgency']) ?> ms-2 text-uppercase"><?= sanitize($req['urgency']) ?></span>
                                        </div>
                                        <a href="?page=requests" class="btn btn-sm btn-outline-primary">View Details</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="text-center text-muted py-3">No pending requests match your blood group right now. Keep up the great work!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Your Recent Requests</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($my_requests)) : ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($my_requests as $req) : ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        For <strong><?= sanitize($req['patient_name']) ?></strong> (<?= sanitize($req['blood_group']) ?>)
                                        <span class="badge bg-info"><?= ucfirst(sanitize($req['status'])) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="text-center text-muted py-3">You haven't created any requests yet. <a href="?page=requests">Create one now</a>.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Current Blood Stock Levels</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($dashboard_stock as $bg => $stock) : ?>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="card text-center h-100 <?= $stock['units'] < 10 ? 'border-danger' : ($stock['units'] < 20 ? 'border-warning' : 'border-success') ?>">
                                        <div class="card-body">
                                            <h5 class="card-title text-danger fw-bold"><?= sanitize($bg) ?></h5>
                                            <p class="card-text fs-3 fw-bold"><?= sanitize($stock['units']) ?> <small class="text-muted">units</small></p>
                                            <p class="card-text text-muted small">
                                                Last updated: <?= $stock['last_updated'] !== 'N/A' ? date('M j, Y', strtotime($stock['last_updated'])) : 'N/A' ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php
        break;
        exit;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Poor People Walfare: A community platform to connect blood donors with those in need, organize donation drives, share news and stories, and manage blood bank stock. Donate blood, save lives.">
    <meta name="keywords" content="blood donation, find blood donor, blood bank, save life, community health, Pakistan, blood drive, urgent blood, donor registration, blood inventory, volunteer">
    <meta name="author" content="Yasin Ullah">
    <meta name="robots" content="index, follow">
    <title><?= sanitize(ucfirst(str_replace('_', ' ', $page))) ?> | <?= SITE_NAME ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><text x='0' y='14' font-size='16'>&#x1F496;</text></svg>" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #dc3545;
            /* Red */
            --secondary-color: #6c757d;
            /* Gray */
            --info-color: #0dcaf0;
            /* Cyan */
            --success-color: #198754;
            /* Green */
            --warning-color: #ffc107;
            /* Yellow */
            --danger-color: #dc3545;
            /* Red */
            --light-bg: #f8f9fa;
            /* Light Gray */
            --dark-text: #212529;
            /* Dark Gray */
            --light-text: #f8f9fa;
            /* Light Gray */
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }

        .text-primary {
            color: var(--primary-color) !important;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: #fff;
        }

        .navbar {
            box-shadow: 0 2px 4px rgba(0, 0, 0, .05);
            background-color: #fff;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 280px;
            padding: 56px 0 0;
            box-shadow: 0 2px 5px 0 rgba(0, 0, 0, .05), 0 2px 10px 0 rgba(0, 0, 0, .05);
            z-index: 600;
            background-color: #fff;
            overflow-y: auto;
            transition: all 0.3s ease-in-out;
        }

        .main-content {
            padding-left: 280px;
            flex: 1;
        }

        .sidebar .nav-link {
            font-weight: 500;
            color: var(--dark-text);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: var(--primary-color);
            background-color: rgba(var(--primary-color-rgb), 0.1);
            border-left: 4px solid var(--primary-color);
            padding-left: calc(1.5rem - 4px);
        }

        .sidebar .nav-link.active {
            font-weight: 600;
        }

        .sidebar .nav-link .bi {
            margin-right: .8rem;
            font-size: 1.2rem;
            width: 24px;
        }

        .sidebar .list-group-item {
            border: none;
            border-radius: 0;
        }

        .card {
            border: none;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.03);
            border-radius: 0.75rem;
        }

        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #eee;
            padding: 1.25rem 1.5rem;
        }

        /* Urgency Colors for Request Cards/Rows */
        .urgency-emergency {
            border-left: 5px solid var(--danger-color);
        }

        .urgency-urgent {
            border-left: 5px solid var(--warning-color);
        }

        .urgency-normal {
            border-left: 5px solid var(--info-color);
        }

        /* Badges */
        .badge.bg-availability-available {
            background-color: var(--success-color) !important;
        }

        .badge.bg-availability-unavailable {
            background-color: var(--secondary-color) !important;
        }

        .badge.bg-urgency-emergency {
            background-color: var(--danger-color) !important;
        }

        .badge.bg-urgency-urgent {
            background-color: var(--warning-color) !important;
            color: #333 !important;
        }

        .badge.bg-urgency-normal {
            background-color: var(--info-color) !important;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.9)), url('https://source.unsplash.com/random/1200x800/?blood-donation,community') no-repeat center center;
            background-size: cover;
            padding: 80px 24px;
            border-radius: 1rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .hero-section .display-4 {
            color: var(--dark-text);
        }

        .hero-section .lead {
            color: var(--secondary-color);
        }

        /* Footer */
        footer {
            background-color: #f0f2f5;
            border-top: 1px solid #e0e2e5;
            margin-top: auto;
        }

        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .sidebar {
                left: -280px;
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                padding-left: 0;
            }

            .navbar-toggler {
                margin-left: 1rem;
            }
        }

        /* Certificate Specific Styles */
        .certificate-container {
            width: 25.7cm;
            height: 17cm;
            position: relative;
            background-color: white;
            border: 10px solid #006A4E;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            padding: 2cm;
            box-sizing: border-box;
        }

        .certificate-border {
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 3px solid #FFC72C;
        }

        .content {
            text-align: center;
        }

        .logo svg {
            width: 48px;
            height: 48px;
            fill: #006A4E;
        }

        h1.certificate-title {
            font-family: 'Great Vibes', cursive;
            font-size: 3.5rem;
            color: #006A4E;
            margin: 20px 0 10px;
            font-weight: normal;
        }

        h2.certificate-subtitle {
            font-size: 1.5rem;
            font-weight: normal;
            margin-bottom: 20px;
        }

        .donor-name {
            font-family: 'Great Vibes', cursive;
            font-size: 4rem;
            color: #d4af37;
            border-bottom: 2px solid #FFC72C;
            display: inline-block;
            padding-bottom: 5px;
            margin: 20px 0;
            color: #006A4E;
            /* Changed to match theme */
            text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.2);
            /* Subtle shadow */
        }

        .main-text {
            font-size: 1.1rem;
            line-height: 1.6;
            margin: 20px auto;
            max-width: 80%;
        }

        .certificate-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            position: absolute;
            bottom: 6cm;
            width: calc(100% - 4cm);
        }

        .signature {
            border-top: 1px solid #333;
            padding-top: 5px;
            width: 200px;
        }

        .action-button {
            position: fixed;
            top: 68px;
            right: 8px;
            padding: 10px 20px;
            font-size: 1rem;
            background: #006A4E;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            z-index: 100;
        }

        .action-button:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .watermark-img {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 290px;
            height: 290px;
            border-radius: 50%;
            object-fit: cover;
            opacity: 0.45;
            z-index: -1;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 0;
            }

            body {
                padding: 0;
            }

            .certificate-container {
                box-shadow: none;
                width: 29.7cm;
                height: 21cm;
                border: none;
                padding: 0;
            }

            .certificate-border {
                display: none;
            }

            .action-button {
                display: none;
            }

            .certificate-footer {
                bottom: 4cm;
                /* Adjust for print layout */
            }
        }
    </style>
</head>

<body>
    <?php if ($page !== 'certificate') : ?>
        <?php if (is_logged_in()) : ?>
            <header class="navbar navbar-expand-lg navbar-light bg-light fixed-top p-0">
                <div class="container-fluid">
                    <button class="navbar-toggler d-lg-none" type="button" id="sidebarToggle">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <a class="navbar-brand fw-bold text-primary ms-3" href="?page=dashboard"><?= render_icon('droplet-half') ?> <?= SITE_NAME ?></a>
                    <div class="ms-auto d-flex align-items-center">
                        <div class="dropdown">
                            <a href="#" class="nav-link dropdown-toggle text-secondary" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php if (!empty($_SESSION['profile_image_url'])) : ?>
                                    <img src="<?= sanitize($_SESSION['profile_image_url']) ?>" alt="Profile" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                <?php else : ?>
                                    <?= render_icon('person-circle', 'fs-4') ?>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><span class="dropdown-item-text">Signed in as <strong><?= sanitize($_SESSION['username']) ?></strong></span></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="?page=profile"><?= render_icon('person-vcard') ?> My Profile</a></li>
                                <li>
                                    <form method="post" action="">
                                        <input type="hidden" name="action" value="logout">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <button type="submit" class="dropdown-item text-danger"><?= render_icon('box-arrow-right') ?> Logout</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>
            <nav id="sidebarMenu" class="d-lg-block sidebar bg-white">
                <div class="position-sticky">
                    <div class="list-group list-group-flush mx-3 mt-4">
                        <a href="?page=dashboard" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'dashboard') ? 'active' : '' ?>">
                            <?= render_icon('speedometer2') ?> <span>Dashboard</span>
                        </a>
                        <a href="?page=donors" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'donors') ? 'active' : '' ?>">
                            <?= render_icon('people-fill') ?> <span>Find a Donor</span>
                        </a>
                        <a href="?page=requests" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'requests') ? 'active' : '' ?>">
                            <?= render_icon('hearts') ?> <span>Blood Requests</span>
                        </a>
                        <a href="?page=drives" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'drives') ? 'active' : '' ?>">
                            <?= render_icon('hospital') ?> <span>Donation Drives</span>
                        </a>
                        <a href="?page=donations" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'donations') ? 'active' : '' ?>">
                            <?= render_icon('droplet-fill') ?> <span>My Donations</span>
                        </a>
                        <a href="?page=announcements" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'announcements') ? 'active' : '' ?>">
                            <?= render_icon('megaphone') ?> <span>Announcements</span>
                        </a>
                        <a href="?page=news" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'news') ? 'active' : '' ?>">
                            <?= render_icon('newspaper') ?> <span>News</span>
                        </a>
                        <a href="?page=stories" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'stories') ? 'active' : '' ?>">
                            <?= render_icon('book') ?> <span>Stories</span>
                        </a>
                        <a href="?page=leaderboard" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'leaderboard') ? 'active' : '' ?>">
                            <?= render_icon('trophy') ?> <span>Leaderboard</span>
                        </a>
                        <a href="?page=how_to_donate" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'how_to_donate') ? 'active' : '' ?>">
                            <?= render_icon('question-circle') ?> <span>How to Donate</span>
                        </a>
                        <a href="?page=contact" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'contact') ? 'active' : '' ?>">
                            <?= render_icon('envelope') ?> <span>Contact Us</span>
                        </a>

                        <?php if (get_current_user_role() === 'admin') : ?>
                            <div class="pt-3">
                                <span class="text-muted small text-uppercase px-3">Admin Panel</span>
                                <a href="?page=admin_users" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'admin_users') ? 'active' : '' ?>">
                                    <?= render_icon('person-gear') ?> <span>User Management</span>
                                </a>
                                <a href="?page=admin_announcements" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'admin_announcements') ? 'active' : '' ?>">
                                    <?= render_icon('megaphone-fill') ?> <span>Manage Announcements</span>
                                </a>
                                <a href="?page=admin_drives" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'admin_drives') ? 'active' : '' ?>">
                                    <?= render_icon('hospital-fill') ?> <span>Manage Drives</span>
                                </a>
                                <a href="?page=admin_news" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'admin_news') ? 'active' : '' ?>">
                                    <?= render_icon('newspaper-fill') ?> <span>Manage News</span>
                                </a>
                                <a href="?page=admin_stories" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'admin_stories') ? 'active' : '' ?>">
                                    <?= render_icon('book-fill') ?> <span>Manage Stories</span>
                                </a>
                                <a href="?page=admin_blood_bank" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'admin_blood_bank') ? 'active' : '' ?>">
                                    <?= render_icon('bank') ?> <span>Blood Bank Stock</span>
                                </a>
                                <a href="?page=admin_messages" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'admin_messages') ? 'active' : '' ?>">
                                    <?= render_icon('chat-dots') ?> <span>User Messages</span>
                                    <?php
                                    $unread_messages_count = $db->query("SELECT COUNT(*) FROM messages WHERE read_status = 0")->fetchColumn();
                                    if ($unread_messages_count > 0) {
                                        echo "<span class='badge bg-danger rounded-pill ms-auto'>{$unread_messages_count}</span>";
                                    }
                                    ?>
                                </a>
                                <a href="finance.php" class="list-group-item list-group-item-action py-2 ripple">
                                    <?= render_icon('cash-coin') ?> <span>Financial Management</span>
                                </a>
                                <a href="?page=admin_backup" class="list-group-item list-group-item-action py-2 ripple <?= ($page == 'admin_backup') ? 'active' : '' ?>">
                                    <?= render_icon('database-down') ?> <span>Backup & Restore</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>
            <main class="main-content" style="padding-top: 58px;">
                <div class="container-fluid pt-4">
                    <?php display_flash_message(); ?>
                    <?php if (is_logged_in() && $_SESSION['approved'] == 0 && get_current_user_role() !== 'admin') : ?>
                        <div class='alert alert-warning border-0 shadow-sm'>Your account is pending approval by an administrator. Some features may be limited.</div>
                    <?php endif; ?>
                <?php else : // Public pages navigation 
                ?>
                    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
                        <div class="container">
                            <a class="navbar-brand fw-bold text-primary" href="?page=home"><?= render_icon('droplet-half') ?> <?= SITE_NAME ?></a>
                            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
                                <span class="navbar-toggler-icon"></span>
                            </button>
                            <div class="collapse navbar-collapse" id="publicNav">
                                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                                    <li class="nav-item"><a class="nav-link <?= ($page == 'home') ? 'active' : '' ?>" href="?page=home">Home</a></li>
                                    <li class="nav-item"><a class="nav-link <?= ($page == 'announcements') ? 'active' : '' ?>" href="?page=announcements">Announcements</a></li>
                                    <li class="nav-item"><a class="nav-link <?= ($page == 'news') ? 'active' : '' ?>" href="?page=news">News</a></li>
                                    <li class="nav-item"><a class="nav-link <?= ($page == 'stories') ? 'active' : '' ?>" href="?page=stories">Stories</a></li>
                                    <li class="nav-item"><a class="nav-link <?= ($page == 'how_to_donate') ? 'active' : '' ?>" href="?page=how_to_donate">How to Donate</a></li>
                                    <li class="nav-item"><a class="nav-link <?= ($page == 'contact') ? 'active' : '' ?>" href="?page=contact">Contact Us</a></li>
                                    <li class="nav-item"><a class="nav-link" href="?page=login">Login</a></li>
                                    <li class="nav-item"><a class="btn btn-primary ms-lg-2" href="?page=register">Register as Donor</a></li>
                                </ul>
                            </div>
                        </div>
                    </nav>
                    <main style="padding-top: 70px;">
                        <div class="container pt-4">
                            <?php display_flash_message(); ?>
                        <?php endif; ?>

                        <?php
                        // --- Page Content Rendering ---
                        switch ($page) {
                            case 'profile':
                                check_auth(['donor', 'admin']);
                                $user_id = get_current_user_id();
                                $stmt = $db->prepare("SELECT u.*, p.blood_group, p.city, p.last_donation_date, p.is_available, p.total_donations, p.profile_image_url
    FROM users u
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE u.id = ?");
                                $stmt->execute([$user_id]);
                                $user = $stmt->fetch();

                                $next_donation_date = get_next_eligible_date($user['last_donation_date']);
                                $is_eligible = (new DateTime() >= new DateTime($next_donation_date));
                        ?>
                                <h1 class="mb-4">My Profile</h1>
                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="card mb-4 shadow-sm">
                                            <div class="card-body text-center">
                                                <?php if (!empty($user['profile_image_url'])) : ?>
                                                    <img src="<?= sanitize($user['profile_image_url']) ?>" alt="<?= sanitize($user['full_name']) ?>" class="rounded-circle img-fluid" style="width: 150px; height: 150px; object-fit: cover;">
                                                <?php else : ?>
                                                    <?= render_icon('person-circle', 'display-1 text-secondary mb-3') ?>
                                                <?php endif; ?>
                                                <h5 class="my-3"><?= sanitize($user['full_name']) ?></h5>
                                                <p class="text-muted mb-1"><?= sanitize($user['city']) ?></p>
                                                <p class="text-muted mb-4">Role: <span class="badge bg-primary"><?= ucfirst(sanitize($user['role'])) ?></span></p>
                                                <div class="d-flex justify-content-center mb-2">
                                                    <span class="display-4 fw-bold text-danger"><?= sanitize($user['blood_group']) ?></span>
                                                </div>
                                                <p class="text-muted">Total Donations: <span class="fw-bold"><?= sanitize($user['total_donations']) ?></span></p>
                                            </div>
                                        </div>
                                        <div class="card mb-4 shadow-sm">
                                            <div class="card-body">
                                                <h5 class="card-title mb-3 text-primary">Donation Status</h5>
                                                <p><strong>Last Donation:</strong> <?= $user['last_donation_date'] ? date('F j, Y', strtotime($user['last_donation_date'])) : 'N/A' ?></p>
                                                <p><strong>Next Eligible On:</strong> <?= date('F j, Y', strtotime($next_donation_date)) ?></p>
                                                <p><strong>Eligibility:</strong>
                                                    <?php if ($is_eligible) : ?>
                                                        <span class="badge bg-success">Eligible to Donate</span>
                                                    <?php else : ?>
                                                        <span class="badge bg-warning text-dark">Not Eligible Yet</span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-8">
                                        <div class="card mb-4 shadow-sm">
                                            <div class="card-body">
                                                <h5 class="card-title mb-3 text-primary">Update Profile</h5>
                                                <form method="post" action="" enctype="multipart/form-data">
                                                    <input type="hidden" name="action" value="update_profile">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                                                    <div class="row mb-3">
                                                        <div class="col-sm-3"><label class="form-label">Profile Picture</label></div>
                                                        <div class="col-sm-9">
                                                            <input class="form-control" type="file" id="profile_image" name="profile_image" accept="image/*">
                                                            <div class="form-text">Optional. Max <?= MAX_FILE_SIZE / (1024 * 1024) ?>MB. Allowed: <?= implode(', ', ALLOWED_IMAGE_EXTENSIONS) ?>.</div>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-sm-3"><label class="form-label">Full Name</label></div>
                                                        <div class="col-sm-9"><input type="text" class="form-control" name="full_name" value="<?= sanitize($user['full_name']) ?>" required></div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-sm-3"><label class="form-label">Email</label></div>
                                                        <div class="col-sm-9"><input type="email" class="form-control" name="email" value="<?= sanitize($user['email']) ?>" required></div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-sm-3"><label class="form-label">Contact</label></div>
                                                        <div class="col-sm-9"><input type="tel" class="form-control" name="contact_number" value="<?= sanitize($user['contact_number']) ?>" pattern="^03\d{9}$" title="Enter a valid Pakistani mobile number (e.g., 03001234567)" required></div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-sm-3"><label class="form-label">City</label></div>
                                                        <div class="col-sm-9"><input type="text" class="form-control" name="city" value="<?= sanitize($user['city']) ?>" required></div>
                                                    </div>
                                                    <hr class="my-4">
                                                    <h5 class="card-title mb-3 text-primary">Donor Details</h5>
                                                    <div class="row mb-3">
                                                        <div class="col-sm-3"><label class="form-label">Blood Group</label></div>
                                                        <div class="col-sm-9">
                                                            <select class="form-select" name="blood_group" required>
                                                                <?php foreach ($blood_groups as $bg) : ?>
                                                                    <option value="<?= $bg ?>" <?= ($user['blood_group'] == $bg) ? 'selected' : '' ?>><?= $bg ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-sm-3"><label class="form-label">Last Donation Date</label></div>
                                                        <div class="col-sm-9"><input type="date" class="form-control" name="last_donation_date" value="<?= sanitize($user['last_donation_date']) ?>"></div>
                                                    </div>
                                                    <div class="row mb-3 align-items-center">
                                                        <div class="col-sm-3"><label class="form-label">Availability</label></div>
                                                        <div class="col-sm-9">
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" role="switch" id="is_available" name="is_available" <?= $user['is_available'] ? 'checked' : '' ?>>
                                                                <label class="form-check-label" for="is_available">Available to donate</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <button type="submit" class="btn btn-primary mt-3"><?= render_icon('save') ?> Save Changes</button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="card mb-4 shadow-sm">
                                            <div class="card-body">
                                                <h5 class="card-title mb-3 text-primary">Change Password</h5>
                                                <form method="post" action="">
                                                    <input type="hidden" name="action" value="change_password">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <div class="row mb-3">
                                                        <div class="col-sm-3"><label class="form-label">Current Password</label></div>
                                                        <div class="col-sm-9"><input type="password" name="current_password" class="form-control" required></div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-sm-3"><label class="form-label">New Password</label></div>
                                                        <div class="col-sm-9"><input type="password" name="new_password" class="form-control" minlength="8" required></div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-sm-3"><label class="form-label">Confirm New Password</label></div>
                                                        <div class="col-sm-9"><input type="password" name="confirm_password" class="form-control" required></div>
                                                    </div>
                                                    <button type="submit" class="btn btn-danger mt-3"><?= render_icon('key') ?> Change Password</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php
                                break;

                            case 'donors':
                                check_auth(['donor', 'admin']);
                                $search_bg = sanitize($_GET['bg'] ?? '');
                                $search_city = sanitize($_GET['city'] ?? '');
                                $search_avail = sanitize($_GET['avail'] ?? '1');

                                $sql = "SELECT u.full_name, u.contact_number, p.* FROM profiles p JOIN users u ON p.user_id = u.id WHERE u.approved = 1";
                                $params = [];

                                if ($search_bg) {
                                    $sql .= " AND p.blood_group = ?";
                                    $params[] = $search_bg;
                                }
                                if ($search_city) {
                                    $sql .= " AND p.city LIKE ?";
                                    $params[] = "%$search_city%";
                                }
                                if ($search_avail !== 'all') {
                                    $sql .= " AND p.is_available = ?";
                                    $params[] = (int)$search_avail;
                                }

                                $sql .= " ORDER BY p.updated_at DESC";
                                $stmt = $db->prepare($sql);
                                $stmt->execute($params);
                                $donors = $stmt->fetchAll();
                            ?>
                                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                                    <h1 class="h2">Find a Donor</h1>
                                </div>

                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <form method="get" class="row g-3 align-items-end">
                                            <input type="hidden" name="page" value="donors">
                                            <div class="col-md-3">
                                                <label class="form-label">Blood Group</label>
                                                <select name="bg" class="form-select" aria-label="Filter by Blood Group">
                                                    <option value="">Any</option>
                                                    <?php foreach ($blood_groups as $bg) : ?>
                                                        <option value="<?= $bg ?>" <?= ($search_bg == $bg) ? 'selected' : '' ?>><?= $bg ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">City</label>
                                                <input type="text" name="city" class="form-control" value="<?= $search_city ?>" placeholder="e.g., Karachi" aria-label="Filter by City">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Availability</label>
                                                <select name="avail" class="form-select" aria-label="Filter by Availability">
                                                    <option value="1" <?= ($search_avail == '1') ? 'selected' : '' ?>>Available Only</option>
                                                    <option value="0" <?= ($search_avail == '0') ? 'selected' : '' ?>>Not Available</option>
                                                    <option value="all" <?= ($search_avail == 'all') ? 'selected' : '' ?>>All</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <button type="submit" class="btn btn-primary w-100"><?= render_icon('search') ?> Search</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <div class="row">
                                    <?php if (count($donors) > 0) : foreach ($donors as $donor) : ?>
                                            <?php
                                            $next_donation_date = get_next_eligible_date($donor['last_donation_date']);
                                            $is_eligible = (new DateTime() >= new DateTime($next_donation_date));
                                            ?>
                                            <div class="col-md-6 col-lg-4 mb-4">
                                                <div class="card h-100 shadow-sm">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <h5 class="card-title mb-0"><?= sanitize($donor['full_name']) ?></h5>
                                                            <span class="display-5 fw-bold text-danger"><?= sanitize($donor['blood_group']) ?></span>
                                                        </div>
                                                        <p class="card-text text-muted"><?= render_icon('geo-alt-fill') ?> <?= sanitize($donor['city']) ?></p>
                                                        <hr>
                                                        <p class="card-text mb-2">
                                                            <?= render_icon('telephone-fill') ?> <span class="fw-bold"><?= sanitize($donor['contact_number']) ?></span>
                                                        </p>
                                                        <p class="card-text mb-2">
                                                            <?= render_icon('calendar-check') ?> Last Donated:
                                                            <span class="fw-bold"><?= $donor['last_donation_date'] ? date('M j, Y', strtotime($donor['last_donation_date'])) : 'N/A' ?></span>
                                                        </p>
                                                        <p class="mb-0">
                                                            <?php if ($donor['is_available']) : ?>
                                                                <span class="badge bg-availability-available me-1">Currently Available</span>
                                                            <?php else : ?>
                                                                <span class="badge bg-availability-unavailable me-1">Currently Unavailable</span>
                                                            <?php endif; ?>
                                                            <?php if ($is_eligible) : ?>
                                                                <span class="badge bg-success">Eligible</span>
                                                            <?php else : ?>
                                                                <span class="badge bg-warning text-dark">Not Eligible Yet</span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach;
                                    else : ?>
                                        <div class="col-12">
                                            <div class="alert alert-info text-center shadow-sm">No donors found matching your criteria.</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php
                                break;

                            case 'requests':
                                check_auth(['donor', 'admin']);

                                $filter_bg = sanitize($_GET['filter_bg'] ?? '');
                                $filter_city = sanitize($_GET['filter_city'] ?? '');
                                $filter_urgency = sanitize($_GET['filter_urgency'] ?? '');
                                $filter_status = sanitize($_GET['filter_status'] ?? 'pending');

                                $sql = "SELECT * FROM requests WHERE 1=1";
                                $params = [];

                                if ($filter_bg) {
                                    $sql .= " AND blood_group = ?";
                                    $params[] = $filter_bg;
                                }
                                if ($filter_city) {
                                    $sql .= " AND city LIKE ?";
                                    $params[] = "%$filter_city%";
                                }
                                if ($filter_urgency && $filter_urgency !== 'all') {
                                    $sql .= " AND urgency = ?";
                                    $params[] = $filter_urgency;
                                }
                                if ($filter_status && $filter_status !== 'all') {
                                    $sql .= " AND status = ?";
                                    $params[] = $filter_status;
                                }

                                $sql .= " ORDER BY CASE urgency WHEN 'emergency' THEN 1 WHEN 'urgent' THEN 2 WHEN 'normal' THEN 3 END, created_at DESC";
                                $stmt = $db->prepare($sql);
                                $stmt->execute($params);
                                $reqs = $stmt->fetchAll();
                            ?>
                                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                                    <h1 class="h2">Blood Requests</h1>
                                    <div class="btn-toolbar mb-2 mb-md-0">
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createRequestModal">
                                            <?= render_icon('plus-circle-fill') ?> Create New Request
                                        </button>
                                    </div>
                                </div>

                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <form method="get" class="row g-3 align-items-end">
                                            <input type="hidden" name="page" value="requests">
                                            <div class="col-md-3">
                                                <label class="form-label">Blood Group</label>
                                                <select name="filter_bg" class="form-select">
                                                    <option value="">Any</option>
                                                    <?php foreach ($blood_groups as $bg) : ?>
                                                        <option value="<?= $bg ?>" <?= ($filter_bg == $bg) ? 'selected' : '' ?>><?= $bg ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">City</label>
                                                <input type="text" name="filter_city" class="form-control" value="<?= $filter_city ?>" placeholder="e.g., Lahore">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Urgency</label>
                                                <select name="filter_urgency" class="form-select">
                                                    <option value="all">All</option>
                                                    <?php foreach ($urgencies as $urgency_option) : ?>
                                                        <option value="<?= $urgency_option ?>" <?= ($filter_urgency == $urgency_option) ? 'selected' : '' ?>><?= ucfirst($urgency_option) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Status</label>
                                                <select name="filter_status" class="form-select">
                                                    <option value="all" <?= ($filter_status == 'all') ? 'selected' : '' ?>>All</option>
                                                    <?php foreach ($request_statuses as $status_option) : ?>
                                                        <option value="<?= $status_option ?>" <?= ($filter_status == $status_option) ? 'selected' : '' ?>><?= ucfirst($status_option) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <button type="submit" class="btn btn-primary w-100"><?= render_icon('search') ?> Filter Requests</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <div class="card shadow-sm mb-4">
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>Urgency</th>
                                                        <th>Patient</th>
                                                        <th>Blood Group</th>
                                                        <th>Hospital & City</th>
                                                        <th>Units</th>
                                                        <th>Contact</th>
                                                        <th>Status</th>
                                                        <th>Posted On</th>
                                                        <?php if (get_current_user_role() === 'admin') : ?><th>Action</th><?php endif; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($reqs)) :
                                                        foreach ($reqs as $req) :
                                                    ?>
                                                            <tr class="urgency-<?= sanitize($req['urgency']) ?>">
                                                                <td><span class="badge bg-urgency-<?= sanitize($req['urgency']) ?> text-uppercase"><?= sanitize($req['urgency']) ?></span></td>
                                                                <td><?= sanitize($req['patient_name']) ?></td>
                                                                <td class="fw-bold text-danger"><?= sanitize($req['blood_group']) ?></td>
                                                                <td><?= sanitize($req['hospital_name']) ?>, <?= sanitize($req['city']) ?></td>
                                                                <td><?= sanitize($req['required_units']) ?></td>
                                                                <td><?= sanitize($req['contact_person']) ?> <br><small class="text-muted"><?= sanitize($req['contact_number']) ?></small></td>
                                                                <td><span class="badge bg-info"><?= ucfirst(sanitize($req['status'])) ?></span></td>
                                                                <td><?= date('M j, Y', strtotime($req['created_at'])) ?></td>
                                                                <?php if (get_current_user_role() === 'admin') : ?>
                                                                    <td>
                                                                        <form method="post" class="d-inline">
                                                                            <input type="hidden" name="action" value="update_request_status">
                                                                            <input type="hidden" name="id" value="<?= $req['id'] ?>">
                                                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Update request status">
                                                                                <?php foreach ($request_statuses as $status_option) : ?>
                                                                                    <option value="<?= $status_option ?>" <?= $req['status'] == $status_option ? 'selected' : '' ?>><?= ucfirst($status_option) ?></option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </form>
                                                                    </td>
                                                                <?php endif; ?>
                                                            </tr>
                                                        <?php endforeach;
                                                    else : ?>
                                                        <tr>
                                                            <td colspan="9" class="text-center text-muted py-4">No blood requests found.</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Create Request Modal -->
                                <div class="modal fade" id="createRequestModal" tabindex="-1" aria-labelledby="createRequestModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title" id="createRequestModalLabel"><?= render_icon('plus-circle-fill') ?> Create a New Blood Request</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="create_request">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label for="patient_name" class="form-label">Patient Name</label>
                                                            <input type="text" name="patient_name" id="patient_name" class="form-control" required aria-label="Patient Name">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="blood_group" class="form-label">Blood Group</label>
                                                            <select name="blood_group" id="blood_group" class="form-select" required aria-label="Blood Group">
                                                                <option selected disabled value="">Choose...</option>
                                                                <?php foreach ($blood_groups as $bg) echo "<option value='{$bg}'>{$bg}</option>"; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="hospital_name" class="form-label">Hospital Name</label>
                                                            <input type="text" name="hospital_name" id="hospital_name" class="form-control" required aria-label="Hospital Name">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="city" class="form-label">City</label>
                                                            <input type="text" name="city" id="city" class="form-control" required aria-label="City">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="contact_person" class="form-label">Contact Person</label>
                                                            <input type="text" name="contact_person" id="contact_person" class="form-control" required aria-label="Contact Person">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="contact_number" class="form-label">Contact Number (e.g., 03001234567)</label>
                                                            <input type="tel" name="contact_number" id="contact_number" class="form-control" pattern="^03\d{9}$" title="Enter a valid Pakistani mobile number (e.g., 03001234567)" required aria-label="Contact Number">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="urgency" class="form-label">Urgency</label>
                                                            <select name="urgency" id="urgency" class="form-select" required aria-label="Urgency">
                                                                <?php foreach ($urgencies as $urgency_option) echo "<option value='{$urgency_option}'>" . ucfirst($urgency_option) . "</option>"; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="required_units" class="form-label">Required Units</label>
                                                            <input type="number" name="required_units" id="required_units" class="form-control" value="1" min="1" required aria-label="Required Units">
                                                        </div>
                                                        <div class="col-12">
                                                            <label for="details" class="form-label">Additional Details</label>
                                                            <textarea name="details" id="details" class="form-control" rows="3" aria-label="Additional Details"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-primary">Submit Request</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php
                                break;

                            case 'drives':
                            case 'admin_drives':
                                $is_admin_page = ($page === 'admin_drives');
                                if ($is_admin_page) check_auth('admin');

                                $filter_status = sanitize($_GET['filter_status'] ?? 'upcoming');
                                $filter_date_start = sanitize($_GET['filter_date_start'] ?? '');
                                $filter_date_end = sanitize($_GET['filter_date_end'] ?? '');

                                $sql = "SELECT d.*, u.full_name as created_by_name FROM drives d JOIN users u ON d.created_by = u.id WHERE 1=1";
                                $params = [];

                                if ($filter_status && $filter_status !== 'all') {
                                    $sql .= " AND d.status = ?";
                                    $params[] = $filter_status;
                                }
                                if ($filter_date_start) {
                                    $sql .= " AND d.drive_date >= ?";
                                    $params[] = $filter_date_start . ' 00:00:00';
                                }
                                if ($filter_date_end) {
                                    $sql .= " AND d.drive_date <= ?";
                                    $params[] = $filter_date_end . ' 23:59:59';
                                }

                                $sql .= " ORDER BY drive_date DESC";
                                $stmt = $db->prepare($sql);
                                $stmt->execute($params);
                                $drives = $stmt->fetchAll();
                            ?>
                                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                                    <h1 class="h2">Donation Drives</h1>
                                    <?php if ($is_admin_page) : ?>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDriveModal"><?= render_icon('plus-circle') ?> New Drive</button>
                                    <?php endif; ?>
                                </div>

                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <form method="get" class="row g-3 align-items-end">
                                            <input type="hidden" name="page" value="<?= $page ?>">
                                            <div class="col-md-4">
                                                <label class="form-label">Status</label>
                                                <select name="filter_status" class="form-select">
                                                    <option value="all" <?= ($filter_status == 'all') ? 'selected' : '' ?>>All</option>
                                                    <?php foreach ($drive_statuses as $status_option) : ?>
                                                        <option value="<?= $status_option ?>" <?= ($filter_status == $status_option) ? 'selected' : '' ?>><?= ucfirst($status_option) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Start Date</label>
                                                <input type="date" name="filter_date_start" class="form-control" value="<?= $filter_date_start ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">End Date</label>
                                                <input type="date" name="filter_date_end" class="form-control" value="<?= $filter_date_end ?>">
                                            </div>
                                            <div class="col-12">
                                                <button type="submit" class="btn btn-primary w-100"><?= render_icon('search') ?> Filter Drives</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <?php if ($is_admin_page) : ?>
                                    <!-- Create Drive Modal -->
                                    <div class="modal fade" id="createDriveModal" tabindex="-1" aria-labelledby="createDriveModalLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title" id="createDriveModalLabel"><?= render_icon('plus-circle-fill') ?> Create New Donation Drive</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="post">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="create_drive">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <div class="row g-3">
                                                            <div class="col-md-12">
                                                                <label for="drive_title" class="form-label">Drive Title</label>
                                                                <input type="text" name="title" id="drive_title" class="form-control" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="drive_date" class="form-label">Date & Time</label>
                                                                <input type="datetime-local" name="drive_date" id="drive_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="drive_organizer" class="form-label">Organizer</label>
                                                                <input type="text" name="organizer" id="drive_organizer" class="form-control" required>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <label for="drive_location" class="form-label">Location Address</label>
                                                                <input type="text" name="location" id="drive_location" class="form-control" required>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <label for="drive_location_url" class="form-label">Location URL (Google Maps link, optional)</label>
                                                                <input type="url" name="location_url" id="drive_location_url" class="form-control">
                                                            </div>
                                                            <div class="col-12">
                                                                <label for="drive_description" class="form-label">Description</label>
                                                                <textarea name="description" id="drive_description" class="form-control" rows="4"></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary">Create Drive</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="row">
                                    <?php if (!empty($drives)) : foreach ($drives as $drive) : ?>
                                            <div class="col-md-6 col-lg-4 mb-4">
                                                <div class="card h-100 shadow-sm">
                                                    <div class="card-body d-flex flex-column">
                                                        <h5 class="card-title text-primary"><?= sanitize($drive['title']) ?></h5>
                                                        <p class="card-text text-muted small mb-2">
                                                            <?= render_icon('calendar-event') ?> <?= date('F j, Y, g:i A', strtotime($drive['drive_date'])) ?>
                                                        </p>
                                                        <p class="card-text mb-2">
                                                            <?= render_icon('pin-map') ?> <?= sanitize($drive['location']) ?>
                                                            <?php if ($drive['location_url']) : ?>
                                                                <a href="<?= sanitize($drive['location_url']) ?>" target="_blank" class="ms-2 small"><?= render_icon('box-arrow-up-right') ?> Map</a>
                                                            <?php endif; ?>
                                                        </p>
                                                        <p class="card-text mb-2">
                                                            <?= render_icon('person-badge') ?> Organizer: <span class="fw-bold"><?= sanitize($drive['organizer']) ?></span>
                                                        </p>
                                                        <p class="card-text flex-grow-1"><?= nl2br(substr(sanitize($drive['description']), 0, 150)) ?><?= strlen($drive['description']) > 150 ? '...' : '' ?></p>
                                                        <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top">
                                                            <span class="badge bg-<?= $drive['status'] == 'upcoming' ? 'info' : ($drive['status'] == 'completed' ? 'success' : 'secondary') ?> text-uppercase">
                                                                <?= sanitize($drive['status']) ?>
                                                            </span>
                                                            <?php if ($is_admin_page) : ?>
                                                                <div class="btn-group" role="group">
                                                                    <form method="post" class="d-inline">
                                                                        <input type="hidden" name="action" value="update_drive_status">
                                                                        <input type="hidden" name="id" value="<?= $drive['id'] ?>">
                                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Update drive status">
                                                                            <?php foreach ($drive_statuses as $status_option) : ?>
                                                                                <option value="<?= $status_option ?>" <?= $drive['status'] == $status_option ? 'selected' : '' ?>><?= ucfirst($status_option) ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </form>
                                                                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this drive?');" class="d-inline ms-2">
                                                                        <input type="hidden" name="action" value="delete_drive">
                                                                        <input type="hidden" name="id" value="<?= $drive['id'] ?>">
                                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Drive"><?= render_icon('trash') ?></button>
                                                                    </form>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach;
                                    else : ?>
                                        <div class="col-12">
                                            <div class="alert alert-info text-center shadow-sm">No donation drives scheduled or listed yet.</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php
                                break;

                            case 'donations':
                                check_auth(['donor', 'admin']);
                                $user_id = get_current_user_id();
                                $is_admin = get_current_user_role() === 'admin';

                                $sql = "SELECT d.*, u.full_name, p.blood_group, r.patient_name as request_patient, dr.title as drive_title
                                FROM donations d
                                JOIN users u ON d.user_id = u.id
                                LEFT JOIN profiles p ON u.id = p.user_id
                                LEFT JOIN requests r ON d.request_id = r.id
                                LEFT JOIN drives dr ON d.drive_id = dr.id";
                                $params = [];

                                if (!$is_admin) {
                                    $sql .= " WHERE d.user_id = ?";
                                    $params[] = $user_id;
                                }
                                $sql .= " ORDER BY d.donation_date DESC";
                                $stmt = $db->prepare($sql);
                                $stmt->execute($params);
                                $donations = $stmt->fetchAll();

                                $all_users = [];
                                if ($is_admin) {
                                    $all_users = $db->query("SELECT id, full_name, username FROM users ORDER BY full_name")->fetchAll();
                                }
                            ?>
                                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                                    <h1 class="h2">My Donations</h1>
                                    <?php if ($is_admin) : ?>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDonationModal"><?= render_icon('plus-circle') ?> Add Donation</button>
                                    <?php endif; ?>
                                </div>

                                <?php if ($is_admin) : ?>
                                    <!-- Add Donation Modal -->
                                    <div class="modal fade" id="addDonationModal" tabindex="-1" aria-labelledby="addDonationModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title" id="addDonationModalLabel"><?= render_icon('plus-circle-fill') ?> Add New Donation Record</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="post">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="add_donation">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <div class="mb-3">
                                                            <label for="donation_user_id" class="form-label">Donor</label>
                                                            <select name="user_id" id="donation_user_id" class="form-select" required>
                                                                <option value="" selected disabled>Choose a donor...</option>
                                                                <?php foreach ($all_users as $u) : ?>
                                                                    <option value="<?= sanitize($u['id']) ?>"><?= sanitize($u['full_name']) ?> (<?= sanitize($u['username']) ?>)</option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="donation_date" class="form-label">Donation Date</label>
                                                            <input type="date" name="donation_date" id="donation_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="donation_type" class="form-label">Type of Donation</label>
                                                            <select name="type" id="donation_type" class="form-select" required>
                                                                <?php foreach ($donation_types as $type_option) : ?>
                                                                    <option value="<?= $type_option ?>"><?= ucfirst($type_option) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="donation_request_id" class="form-label">Related Request ID (Optional)</label>
                                                            <input type="number" name="request_id" id="donation_request_id" class="form-control" placeholder="e.g., 123">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="donation_drive_id" class="form-label">Related Drive ID (Optional)</label>
                                                            <input type="number" name="drive_id" id="donation_drive_id" class="form-control" placeholder="e.g., 456">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="donation_notes" class="form-label">Notes (Optional)</label>
                                                            <textarea name="notes" id="donation_notes" class="form-control" rows="3"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Record Donation</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="card shadow-sm mb-4">
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle">
                                                <thead>
                                                    <tr>
                                                        <?php if ($is_admin) : ?><th>Donor</th><?php endif; ?>
                                                        <th>Date</th>
                                                        <th>Type</th>
                                                        <th>Details</th>
                                                        <th>Notes</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($donations)) : foreach ($donations as $donation) : ?>
                                                            <tr>
                                                                <?php if ($is_admin) : ?>
                                                                    <td><?= sanitize($donation['full_name']) ?> (<?= sanitize($donation['blood_group']) ?>)</td>
                                                                <?php endif; ?>
                                                                <td><?= date('F j, Y', strtotime($donation['donation_date'])) ?></td>
                                                                <td><span class="badge bg-secondary"><?= ucfirst(sanitize($donation['type'])) ?></span></td>
                                                                <td>
                                                                    <?php if ($donation['type'] === 'request' && $donation['request_patient']) : ?>
                                                                        For Request: <?= sanitize($donation['request_patient']) ?>
                                                                    <?php elseif ($donation['type'] === 'drive' && $donation['drive_title']) : ?>
                                                                        At Drive: <?= sanitize($donation['drive_title']) ?>
                                                                    <?php else : ?>
                                                                        Voluntary
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?= sanitize($donation['notes']) ?: 'N/A' ?></td>
                                                            </tr>
                                                        <?php endforeach;
                                                    else : ?>
                                                        <tr>
                                                            <td colspan="<?= $is_admin ? 5 : 4 ?>" class="text-center text-muted py-4">No donation records found.</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php
                                break;

                            case 'announcements':
                            case 'admin_announcements':
                                $is_admin_page = ($page === 'admin_announcements');
                                if ($is_admin_page) check_auth('admin');

                                $announcements = $db->query("SELECT a.*, u.full_name FROM announcements a JOIN users u ON a.created_by = u.id ORDER BY a.created_at DESC")->fetchAll();
                            ?>
                                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                                    <h1 class="h2">Announcements</h1>
                                    <?php if ($is_admin_page) : ?>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal"><?= render_icon('plus-circle') ?> New Announcement</button>
                                    <?php endif; ?>
                                </div>

                                <?php if ($is_admin_page) : ?>
                                    <!-- Add Announcement Modal -->
                                    <div class="modal fade" id="addAnnouncementModal" tabindex="-1" aria-labelledby="addAnnouncementModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="post">
                                                    <div class="modal-header bg-primary text-white">
                                                        <h5 class="modal-title" id="addAnnouncementModalLabel"><?= render_icon('plus-circle-fill') ?> New Announcement</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="add_announcement">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <div class="mb-3">
                                                            <label for="ann_title" class="form-label">Title</label>
                                                            <input type="text" name="title" id="ann_title" class="form-control" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="ann_content" class="form-label">Content</label>
                                                            <textarea name="content" id="ann_content" class="form-control" rows="5" required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Post Announcement</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="row">
                                    <?php if (!empty($announcements)) : foreach ($announcements as $ann) : ?>
                                            <div class="col-md-12 mb-4">
                                                <div class="card shadow-sm">
                                                    <div class="card-body">
                                                        <h4 class="card-title text-primary"><?= sanitize($ann['title']) ?></h4>
                                                        <p class="card-subtitle mb-2 text-muted">Posted on <?= date('F j, Y, g:i a', strtotime($ann['created_at'])) ?> by <span class="fw-bold"><?= sanitize($ann['full_name']) ?></span></p>
                                                        <p class="card-text mt-3"><?= nl2br(sanitize($ann['content'])) ?></p>
                                                        <?php if ($is_admin_page) : ?>
                                                            <hr>
                                                            <form method="post" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                                                <input type="hidden" name="action" value="delete_announcement">
                                                                <input type="hidden" name="id" value="<?= $ann['id'] ?>">
                                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger"><?= render_icon('trash') ?> Delete</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach;
                                    else : ?>
                                        <div class="col-12">
                                            <div class="alert alert-info text-center shadow-sm">No announcements available yet.</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php
                                break;

                            case 'news':
                            case 'admin_news':
                                $is_admin_page = ($page === 'admin_news');
                                if ($is_admin_page) check_auth('admin');

                                $news_items = $db->query("SELECT n.*, u.full_name FROM news n LEFT JOIN users u ON n.created_by = u.id ORDER BY published_at DESC")->fetchAll();
                            ?>
                                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                                    <h1 class="h2">Latest News</h1>
                                    <?php if ($is_admin_page) : ?>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNewsModal"><?= render_icon('plus-circle') ?> Add News</button>
                                    <?php endif; ?>
                                </div>

                                <?php if ($is_admin_page) : ?>
                                    <!-- Add News Modal -->
                                    <div class="modal fade" id="addNewsModal" tabindex="-1" aria-labelledby="addNewsModalLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="post" enctype="multipart/form-data">
                                                    <div class="modal-header bg-primary text-white">
                                                        <h5 class="modal-title" id="addNewsModalLabel"><?= render_icon('plus-circle-fill') ?> Add New News Article</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="add_news">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <div class="mb-3">
                                                            <label for="news_title" class="form-label">Title</label>
                                                            <input type="text" name="title" id="news_title" class="form-control" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="news_content" class="form-label">Content</label>
                                                            <textarea name="content" id="news_content" class="form-control" rows="7" required></textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="news_image" class="form-label">Feature Image (Optional)</label>
                                                            <input class="form-control" type="file" id="news_image" name="image" accept="image/*">
                                                            <div class="form-text">Max <?= MAX_FILE_SIZE / (1024 * 1024) ?>MB. Allowed: <?= implode(', ', ALLOWED_IMAGE_EXTENSIONS) ?>.</div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Publish News</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="row">
                                    <?php if (!empty($news_items)) : foreach ($news_items as $news) : ?>
                                            <div class="col-md-6 col-lg-4 mb-4">
                                                <div class="card h-100 shadow-sm">
                                                    <?php if ($news['image_url']) : ?>
                                                        <img src="<?= sanitize($news['image_url']) ?>" class="card-img-top" alt="<?= sanitize($news['title']) ?>" style="height: 200px; object-fit: cover;">
                                                    <?php endif; ?>
                                                    <div class="card-body d-flex flex-column">
                                                        <h5 class="card-title text-primary"><?= sanitize($news['title']) ?></h5>
                                                        <p class="card-subtitle mb-2 text-muted small">
                                                            <?= render_icon('calendar') ?> <?= date('F j, Y', strtotime($news['published_at'])) ?>
                                                            <?php if ($news['created_by']) : ?>
                                                                by <span class="fw-bold"><?= sanitize($news['full_name']) ?></span>
                                                            <?php endif; ?>
                                                        </p>
                                                        <p class="card-text flex-grow-1"><?= nl2br(substr(sanitize($news['content']), 0, 150)) ?><?= strlen($news['content']) > 150 ? '...' : '' ?></p>
                                                        <div class="mt-auto pt-3 border-top">
                                                            <?php if ($is_admin_page) : ?>
                                                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this news article?');">
                                                                    <input type="hidden" name="action" value="delete_news">
                                                                    <input type="hidden" name="id" value="<?= $news['id'] ?>">
                                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><?= render_icon('trash') ?> Delete</button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach;
                                    else : ?>
                                        <div class="col-12">
                                            <div class="alert alert-info text-center shadow-sm">No news articles published yet.</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php
                                break;

                            case 'stories':
                            case 'admin_stories':
                                $is_admin_page = ($page === 'admin_stories');
                                if ($is_admin_page) check_auth('admin');

                                $stories = $db->query("SELECT s.*, u.full_name FROM stories s LEFT JOIN users u ON s.created_by = u.id ORDER BY created_at DESC")->fetchAll();
                            ?>
                                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                                    <h1 class="h2">Inspiring Stories</h1>
                                    <?php if ($is_admin_page) : ?>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStoryModal"><?= render_icon('plus-circle') ?> Add Story</button>
                                    <?php endif; ?>
                                </div>

                                <?php if ($is_admin_page) : ?>
                                    <!-- Add Story Modal -->
                                    <div class="modal fade" id="addStoryModal" tabindex="-1" aria-labelledby="addStoryModalLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="post" enctype="multipart/form-data">
                                                    <div class="modal-header bg-primary text-white">
                                                        <h5 class="modal-title" id="addStoryModalLabel"><?= render_icon('plus-circle-fill') ?> Add New Story</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="add_story">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <div class="mb-3">
                                                            <label for="story_title" class="form-label">Title</label>
                                                            <input type="text" name="title" id="story_title" class="form-control" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="story_content" class="form-label">Content</label>
                                                            <textarea name="content" id="story_content" class="form-control" rows="7" required></textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="story_image" class="form-label">Feature Image (Optional)</label>
                                                            <input class="form-control" type="file" id="story_image" name="image" accept="image/*">
                                                            <div class="form-text">Max <?= MAX_FILE_SIZE / (1024 * 1024) ?>MB. Allowed: <?= implode(', ', ALLOWED_IMAGE_EXTENSIONS) ?>.</div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Publish Story</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="row">
                                    <?php if (!empty($stories)) : foreach ($stories as $story) : ?>
                                            <div class="col-md-6 col-lg-4 mb-4">
                                                <div class="card h-100 shadow-sm">
                                                    <?php if ($story['image_url']) : ?>
                                                        <img src="<?= sanitize($story['image_url']) ?>" class="card-img-top" alt="<?= sanitize($story['title']) ?>" style="height: 200px; object-fit: cover;">
                                                    <?php endif; ?>
                                                    <div class="card-body d-flex flex-column">
                                                        <h5 class="card-title text-primary"><?= sanitize($story['title']) ?></h5>
                                                        <p class="card-subtitle mb-2 text-muted small">
                                                            <?= render_icon('calendar') ?> <?= date('F j, Y', strtotime($story['created_at'])) ?>
                                                            <?php if ($story['created_by']) : ?>
                                                                by <span class="fw-bold"><?= sanitize($story['full_name']) ?></span>
                                                            <?php endif; ?>
                                                        </p>
                                                        <p class="card-text flex-grow-1"><?= nl2br(substr(sanitize($story['content']), 0, 150)) ?><?= strlen($story['content']) > 150 ? '...' : '' ?></p>
                                                        <div class="mt-auto pt-3 border-top">
                                                            <?php if ($is_admin_page) : ?>
                                                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this story?');">
                                                                    <input type="hidden" name="action" value="delete_story">
                                                                    <input type="hidden" name="id" value="<?= $story['id'] ?>">
                                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><?= render_icon('trash') ?></button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach;
                                    else : ?>
                                        <div class="col-12">
                                            <div class="alert alert-info text-center shadow-sm">No inspiring stories shared yet. Be the first to share yours!</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php
                                break;

                            case 'leaderboard':
                                check_auth(['donor', 'admin']);
                                $filter_period = $_GET['period'] ?? 'all';
                                $start_date = sanitize($_GET['start_date'] ?? '');
                                $end_date = sanitize($_GET['end_date'] ?? '');
                                $period_text = 'All Time';

                                $where_clauses = [];
                                $params = [];
                                $today = date('Y-m-d');

                                switch ($filter_period) {
                                    case 'today':
                                        $where_clauses[] = "d.donation_date = ?";
                                        $params[] = $today;
                                        $period_text = "Today (" . date('F j, Y') . ")";
                                        break;
                                    case 'week':
                                        $start_of_week = date('Y-m-d', strtotime('last monday')); // Start of current week (Monday)
                                        $where_clauses[] = "d.donation_date >= ?";
                                        $params[] = $start_of_week;
                                        $period_text = "This Week (Since " . date('M j', strtotime($start_of_week)) . ")";
                                        break;
                                    case 'month':
                                        $where_clauses[] = "strftime('%Y-%m', d.donation_date) = ?";
                                        $params[] = date('Y-m');
                                        $period_text = "This Month (" . date('F Y') . ")";
                                        break;
                                    case 'year':
                                        $where_clauses[] = "strftime('%Y', d.donation_date) = ?";
                                        $params[] = date('Y');
                                        $period_text = "This Year (" . date('Y') . ")";
                                        break;
                                    case 'custom':
                                        if (validate_input($start_date, 'date') && validate_input($end_date, 'date')) {
                                            $where_clauses[] = "d.donation_date BETWEEN ? AND ?";
                                            $params[] = $start_date;
                                            $params[] = $end_date;
                                            $period_text = "from " . date('M j, Y', strtotime($start_date)) . " to " . date('M j, Y', strtotime($end_date));
                                        } else {
                                            set_flash_message('warning', 'Invalid custom date range. Showing all time data.');
                                            $filter_period = 'all'; // Revert to all if custom is invalid
                                        }
                                        break;
                                }

                                $sql = "SELECT u.id as user_id, u.full_name, p.blood_group, COUNT(d.id) as donation_count
                                FROM donations d
                                JOIN users u ON d.user_id = u.id
                                JOIN profiles p ON u.id = p.user_id";

                                if (!empty($where_clauses)) {
                                    $sql .= " WHERE " . implode(' AND ', $where_clauses);
                                }

                                $sql .= " GROUP BY u.id, u.full_name, p.blood_group ORDER BY donation_count DESC, u.full_name ASC LIMIT 25";
                                $stmt = $db->prepare($sql);
                                $stmt->execute($params);
                                $top_donors = $stmt->fetchAll();
                            ?>
                                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                                    <h1 class="h2">Top Donors Leaderboard</h1>
                                </div>

                                <div class="card shadow-sm mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Filter Donations</h5>
                                    </div>
                                    <div class="card-body bg-light">
                                        <form method="get" class="row g-3 align-items-end">
                                            <input type="hidden" name="page" value="leaderboard">
                                            <div class="col-md-3">
                                                <label for="period" class="form-label">Time Period</label>
                                                <select name="period" id="period" class="form-select">
                                                    <option value="all" <?= ($filter_period == 'all') ? 'selected' : '' ?>>All Time</option>
                                                    <option value="year" <?= ($filter_period == 'year') ? 'selected' : '' ?>>This Year</option>
                                                    <option value="month" <?= ($filter_period == 'month') ? 'selected' : '' ?>>This Month</option>
                                                    <option value="week" <?= ($filter_period == 'week') ? 'selected' : '' ?>>This Week</option>
                                                    <option value="today" <?= ($filter_period == 'today') ? 'selected' : '' ?>>Today</option>
                                                    <option value="custom" <?= ($filter_period == 'custom') ? 'selected' : '' ?>>Custom Range</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3" id="start_date_col" style="display: none;">
                                                <label for="start_date" class="form-label">Start Date</label>
                                                <input type="date" name="start_date" id="start_date" class="form-control" value="<?= $start_date ?>">
                                            </div>
                                            <div class="col-md-3" id="end_date_col" style="display: none;">
                                                <label for="end_date" class="form-label">End Date</label>
                                                <input type="date" name="end_date" id="end_date" class="form-control" value="<?= $end_date ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <button type="submit" class="btn btn-primary w-100"><?= render_icon('search') ?> Filter</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <div class="card shadow-sm mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Showing Results for: <span class="text-primary"><?= sanitize($period_text) ?></span></h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Recognizing our heroes who have made the most blood donations during the selected period.</p>
                                        <?php if (!empty($top_donors)) : ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover align-middle">
                                                    <thead class="table-dark">
                                                        <tr>
                                                            <th scope="col">Rank</th>
                                                            <th scope="col">Donor Name</th>
                                                            <th scope="col">Blood Group</th>
                                                            <th scope="col">Donations</th>
                                                            <?php if (get_current_user_role() === 'admin') : ?>
                                                                <th scope="col">Action</th>
                                                            <?php endif; ?>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php $rank = 1;
                                                        foreach ($top_donors as $donor) : ?>
                                                            <tr>
                                                                <th scope="row" class="fs-4"><?= $rank++ ?></th>
                                                                <td><?= sanitize($donor['full_name']) ?></td>
                                                                <td class="fw-bold text-danger"><?= sanitize($donor['blood_group']) ?></td>
                                                                <td><span class="badge bg-primary rounded-pill fs-6"><?= sanitize($donor['donation_count']) ?></span></td>
                                                                <?php if (get_current_user_role() === 'admin') : ?>
                                                                    <td>
                                                                        <a href="?page=certificate&user_id=<?= $donor['user_id'] ?>&period_text=<?= urlencode($period_text) ?>" target="_blank" class="btn btn-sm btn-success">
                                                                            <?= render_icon('award-fill') ?> Certificate
                                                                        </a>
                                                                    </td>
                                                                <?php endif; ?>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else : ?>
                                            <div class="alert alert-info text-center">No donation data to display for the selected period.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const periodSelect = document.getElementById('period');
                                        const startDateCol = document.getElementById('start_date_col');
                                        const endDateCol = document.getElementById('end_date_col');

                                        function toggleDateInputs() {
                                            if (periodSelect.value === 'custom') {
                                                startDateCol.style.display = 'block';
                                                endDateCol.style.display = 'block';
                                            } else {
                                                startDateCol.style.display = 'none';
                                                endDateCol.style.display = 'none';
                                            }
                                        }
                                        periodSelect.addEventListener('change', toggleDateInputs);
                                        toggleDateInputs(); // Call on load to set initial state
                                    });
                                </script>
                            <?php
                                break;

                            case 'admin_users':
                                check_auth('admin');
                                $users = $db->query("SELECT u.*, p.blood_group FROM users u LEFT JOIN profiles p ON u.id = p.user_id ORDER BY u.created_at DESC")->fetchAll();
                            ?>
                                <h1 class="h2 mb-4">User Management</h1>
                                <div class="card shadow-sm">
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Username</th>
                                                        <th>Email</th>
                                                        <th>Role</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($users)) : foreach ($users as $user) : ?>
                                                            <tr>
                                                                <td><?= sanitize($user['full_name']) ?> <br><small class="text-muted"><?= sanitize($user['contact_number']) ?></small></td>
                                                                <td><?= sanitize($user['username']) ?></td>
                                                                <td><?= sanitize($user['email']) ?></td>
                                                                <td>
                                                                    <?php if ($user['id'] === get_current_user_id()) : ?>
                                                                        <span class="badge bg-primary"><?= ucfirst(sanitize($user['role'])) ?></span>
                                                                    <?php else : ?>
                                                                        <form method="post" class="d-inline">
                                                                            <input type="hidden" name="action" value="admin_update_user">
                                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                                            <input type="hidden" name="approved" value="<?= $user['approved'] ?>">
                                                                            <select name="role" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Change user role">
                                                                                <?php foreach ($roles as $role_option) : ?>
                                                                                    <option value="<?= $role_option ?>" <?= $user['role'] == $role_option ? 'selected' : '' ?>><?= ucfirst($role_option) ?></option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <form method="post">
                                                                        <input type="hidden" name="action" value="admin_update_user">
                                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                                        <input type="hidden" name="role" value="<?= $user['role'] ?>">
                                                                        <div class="form-check form-switch">
                                                                            <input class="form-check-input" type="checkbox" name="approved" <?= $user['approved'] ? 'checked' : '' ?> onchange="this.form.submit()" aria-label="Approve/Disapprove user">
                                                                            <label class="form-check-label"><?= $user['approved'] ? 'Approved' : 'Pending' ?></label>
                                                                        </div>
                                                                    </form>
                                                                </td>
                                                                <td>
                                                                    <div class="btn-group" role="group">
                                                                        <form method="post" onsubmit="return confirm('Are you sure? This will generate a new password and set first_login flag.');" class="d-inline">
                                                                            <input type="hidden" name="action" value="admin_reset_password">
                                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Reset Password"><?= render_icon('key') ?></button>
                                                                        </form>
                                                                        <?php if ($user['id'] !== get_current_user_id()) : ?>
                                                                            <form method="post" onsubmit="return confirm('WARNING: This will permanently delete the user and all associated data. Are you sure?');" class="d-inline ms-1">
                                                                                <input type="hidden" name="action" value="admin_delete_user">
                                                                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete User"><?= render_icon('trash') ?></button>
                                                                            </form>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach;
                                                    else : ?>
                                                        <tr>
                                                            <td colspan="6" class="text-center text-muted py-4">No users registered yet.</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php
                                break;

                            case 'admin_blood_bank':
                                check_auth('admin');
                                $today = date('Y-m-d');
                                $next_week = date('Y-m-d', strtotime('+7 days'));

                                $summary = $db->query("
                                    SELECT
                                        (SELECT COUNT(*) FROM blood_inventory WHERE status = 'available') as total_units,
                                        (SELECT COUNT(*) FROM blood_inventory WHERE status = 'available' AND expiry_date BETWEEN '$today' AND '$next_week') as expiring_soon,
                                        (SELECT COUNT(*) FROM blood_inventory WHERE status = 'expired') as total_expired
                                ")->fetch();

                                $low_stock_threshold = 10;
                                $blood_stock_by_type = $db->query("SELECT blood_group, COUNT(*) as count FROM blood_inventory WHERE status='available' GROUP BY blood_group")->fetchAll(PDO::FETCH_KEY_PAIR);
                                $low_stock_groups = [];
                                foreach ($blood_groups as $bg) {
                                    if (!isset($blood_stock_by_type[$bg]) || $blood_stock_by_type[$bg] < $low_stock_threshold) {
                                        $low_stock_groups[] = $bg;
                                    }
                                }

                                // Chart Data for last 30 days
                                $chart_data_stmt = $db->prepare("
                                    WITH RECURSIVE dates(date) AS (
                                      SELECT date('now', '-29 days')
                                      UNION ALL
                                      SELECT date(date, '+1 day')
                                      FROM dates
                                      WHERE date <= date('now')
                                    )
                                    SELECT
                                      d.date,
                                      IFNULL(donations.count, 0) as donated,
                                      IFNULL(usages.count, 0) as used
                                    FROM dates d
                                    LEFT JOIN (SELECT collection_date, COUNT(*) as count FROM blood_inventory GROUP BY collection_date) donations ON d.date = donations.collection_date
                                    LEFT JOIN (SELECT date(created_at) as usage_date, COUNT(*) as count FROM blood_inventory WHERE status = 'used' GROUP BY usage_date) usages ON d.date = usages.usage_date
                                    ORDER BY d.date ASC
                                ");
                                $chart_data_stmt->execute();
                                $chart_raw_data = $chart_data_stmt->fetchAll();

                                $chart_labels = json_encode(array_map(fn($d) => date('M d', strtotime($d['date'])), $chart_raw_data));
                                $chart_donations = json_encode(array_column($chart_raw_data, 'donated'));
                                $chart_usage = json_encode(array_column($chart_raw_data, 'used'));

                                // Inventory List Filtering
                                $filter_bg = sanitize($_GET['filter_bg'] ?? '');
                                $filter_status = sanitize($_GET['filter_status'] ?? 'available');

                                $inventory_sql = "SELECT i.*, u.full_name as donor_name
                                FROM blood_inventory i
                                LEFT JOIN users u ON i.donor_id = u.id";
                                $params = [];
                                $where_clauses = [];

                                if ($filter_bg) {
                                    $where_clauses[] = "i.blood_group = ?";
                                    $params[] = $filter_bg;
                                }
                                if ($filter_status && $filter_status !== 'all') {
                                    $where_clauses[] = "i.status = ?";
                                    $params[] = $filter_status;
                                }

                                if (!empty($where_clauses)) {
                                    $inventory_sql .= " WHERE " . implode(' AND ', $where_clauses);
                                }
                                $inventory_sql .= " ORDER BY i.expiry_date ASC";

                                $inventory_stmt = $db->prepare($inventory_sql);
                                $inventory_stmt->execute($params);
                                $inventory_list = $inventory_stmt->fetchAll();

                                // Donors for Add Bag Modal
                                $donors_for_modal = $db->query("SELECT u.id, u.full_name, u.username, p.blood_group FROM users u JOIN profiles p ON u.id = p.user_id WHERE u.role = 'donor' AND u.approved=1 ORDER BY full_name")->fetchAll();
                            ?>
                                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                                    <h1 class="h2">Blood Bank Management</h1>
                                    <div class="btn-toolbar mb-2 mb-md-0">
                                        <form method="post" action="" class="me-2">
                                            <input type="hidden" name="action" value="export_inventory_csv">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary"><?= render_icon('download') ?> Export to CSV</button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addBagModal">
                                            <?= render_icon('plus-circle-fill') ?> Add Blood Bag
                                        </button>
                                    </div>
                                </div>

                                <?php if (!empty($low_stock_groups)) : ?>
                                    <div class="alert alert-warning" role="alert">
                                        <?= render_icon('exclamation-triangle-fill') ?> <strong>Low Stock Alert:</strong> The following blood groups are below the threshold of <?= $low_stock_threshold ?> units:
                                        <strong><?= implode(', ', $low_stock_groups) ?></strong>
                                    </div>
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-4 mb-4">
                                        <div class="card border-start border-primary border-4 h-100 shadow-sm">
                                            <div class="card-body">
                                                <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Available Units</div>
                                                <div class="h5 mb-0 fw-bold text-gray-800"><?= sanitize($summary['total_units']) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-4">
                                        <div class="card border-start border-warning border-4 h-100 shadow-sm">
                                            <div class="card-body">
                                                <div class="text-xs fw-bold text-warning text-uppercase mb-1">Expiring in 7 Days</div>
                                                <div class="h5 mb-0 fw-bold text-gray-800"><?= sanitize($summary['expiring_soon']) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-4">
                                        <div class="card border-start border-danger border-4 h-100 shadow-sm">
                                            <div class="card-body">
                                                <div class="text-xs fw-bold text-danger text-uppercase mb-1">Total Expired Units</div>
                                                <div class="h5 mb-0 fw-bold text-gray-800"><?= sanitize($summary['total_expired']) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card shadow-sm mb-4">
                                    <div class="card-header">
                                        <h6 class="m-0 font-weight-bold text-primary"><?= render_icon('bar-chart-line-fill') ?> Donations vs. Usage (Last 30 Days)</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="inventoryChart"></canvas>
                                    </div>
                                </div>

                                <div class="card shadow-sm">
                                    <div class="card-header">
                                        <h6 class="m-0 font-weight-bold text-primary"><?= render_icon('inboxes-fill') ?> Detailed Inventory</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="get" class="row g-3 align-items-end mb-4 bg-light p-3 rounded">
                                            <input type="hidden" name="page" value="admin_blood_bank">
                                            <div class="col-md-4">
                                                <label class="form-label">Filter by Blood Group</label>
                                                <select name="filter_bg" class="form-select">
                                                    <option value="">Any</option>
                                                    <?php foreach ($blood_groups as $bg) : ?>
                                                        <option value="<?= $bg ?>" <?= ($filter_bg == $bg) ? 'selected' : '' ?>><?= $bg ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Filter by Status</label>
                                                <select name="filter_status" class="form-select">
                                                    <option value="all" <?= ($filter_status == 'all') ? 'selected' : '' ?>>All</option>
                                                    <?php foreach ($blood_bag_statuses as $status_option) : ?>
                                                        <option value="<?= $status_option ?>" <?= ($filter_status == $status_option) ? 'selected' : '' ?>><?= ucfirst($status_option) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <button type="submit" class="btn btn-primary w-100"><?= render_icon('funnel-fill') ?> Filter</button>
                                            </div>
                                        </form>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>Bag ID</th>
                                                        <th>Blood Group</th>
                                                        <th>Donor</th>
                                                        <th>Collection Date</th>
                                                        <th>Expiry Date</th>
                                                        <th>Status</th>
                                                        <th>Notes</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($inventory_list)) : foreach ($inventory_list as $item) :
                                                            $days_left = (strtotime($item['expiry_date']) - time()) / (60 * 60 * 24);
                                                            $row_class = '';
                                                            if ($item['status'] == 'available') {
                                                                if ($days_left < 0) $row_class = 'table-danger'; // Expired but still marked available
                                                                else if ($days_left < 7) $row_class = 'table-warning'; // Expiring soon
                                                            } else if ($item['status'] == 'expired') {
                                                                $row_class = 'table-danger opacity-75';
                                                            } else if ($item['status'] == 'used') {
                                                                $row_class = 'table-success opacity-75';
                                                            } else if ($item['status'] == 'quarantined') {
                                                                $row_class = 'table-secondary opacity-75';
                                                            }
                                                    ?>
                                                            <tr class="<?= $row_class ?>">
                                                                <td class="fw-bold"><?= sanitize($item['bag_id']) ?></td>
                                                                <td class="fw-bold text-danger fs-5"><?= sanitize($item['blood_group']) ?></td>
                                                                <td><?= sanitize($item['donor_name'] ?: 'N/A') ?></td>
                                                                <td><?= date('M j, Y', strtotime($item['collection_date'])) ?></td>
                                                                <td>
                                                                    <?= date('M j, Y', strtotime($item['expiry_date'])) ?>
                                                                    <?php if ($item['status'] == 'available') : ?>
                                                                        <small class="d-block text-muted">(<?= floor($days_left) ?> days left)</small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><span class="badge bg-secondary"><?= ucfirst(sanitize($item['status'])) ?></span></td>
                                                                <td><?= sanitize($item['notes']) ?: 'N/A' ?></td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal" data-bag-id="<?= sanitize($item['bag_id']) ?>" data-inventory-id="<?= sanitize($item['id']) ?>" data-current-status="<?= sanitize($item['status']) ?>" data-expiry-date="<?= sanitize($item['expiry_date']) ?>">
                                                                        <?= render_icon('pencil-square') ?> Update
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach;
                                                    else : ?>
                                                        <tr>
                                                            <td colspan="8" class="text-center text-muted py-4">No inventory found matching criteria.</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Add Blood Bag Modal -->
                                <div class="modal fade" id="addBagModal" tabindex="-1" aria-labelledby="addBagModalLabel" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title" id="addBagModalLabel"><?= render_icon('plus-circle-fill') ?> Add New Blood Bag</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="add_blood_bag">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <div class="mb-3">
                                                        <label for="donor_id" class="form-label">Select Donor</label>
                                                        <select name="donor_id" id="donor_id" class="form-select" required>
                                                            <option value="" selected disabled>Choose a donor...</option>
                                                            <?php foreach ($donors_for_modal as $d) : ?>
                                                                <option value="<?= $d['id'] ?>"><?= sanitize($d['full_name']) ?> (<?= sanitize($d['blood_group']) ?>)</option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="collection_date" class="form-label">Collection Date</label>
                                                        <input type="date" name="collection_date" id="collection_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="expiry_date" class="form-label">Expiry Date (Optional)</label>
                                                        <input type="date" name="expiry_date" id="expiry_date" class="form-control">
                                                        <div class="form-text">Leave blank to default to 42 days from collection date.</div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="notes" class="form-label">Notes (Optional)</label>
                                                        <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Add to Inventory</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Update Bag Status Modal -->
                                <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="updateStatusModalLabel">Update Bag: <span id="modalBagId" class="fw-bold"></span></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="update_bag_status">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="inventory_id" id="modal-inventory-id-input" value="">
                                                    <div class="mb-3">
                                                        <label for="modalStatus" class="form-label">Status</label>
                                                        <select name="status" id="modalStatus" class="form-select" required>
                                                            <?php foreach ($blood_bag_statuses as $status_option) : ?>
                                                                <option value="<?= $status_option ?>"><?= ucfirst($status_option) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="modalExpiryDate" class="form-label">Expiry Date</label>
                                                        <input type="date" name="expiry_date" id="modalExpiryDate" class="form-control" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Update Details</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const ctx = document.getElementById('inventoryChart')?.getContext('2d');
                                        if (ctx) {
                                            const inventoryChart = new Chart(ctx, {
                                                type: 'bar',
                                                data: {
                                                    labels: <?= $chart_labels ?>,
                                                    datasets: [{
                                                        label: 'Donations',
                                                        data: <?= $chart_donations ?>,
                                                        backgroundColor: 'rgba(25, 135, 84, 0.7)', // Success color
                                                        borderColor: 'rgba(25, 135, 84, 1)',
                                                        borderWidth: 1
                                                    }, {
                                                        label: 'Usage',
                                                        data: <?= $chart_usage ?>,
                                                        backgroundColor: 'rgba(220, 53, 69, 0.7)', // Danger color
                                                        borderColor: 'rgba(220, 53, 69, 1)',
                                                        borderWidth: 1
                                                    }]
                                                },
                                                options: {
                                                    scales: {
                                                        y: {
                                                            beginAtZero: true,
                                                            ticks: {
                                                                stepSize: 1
                                                            }
                                                        }
                                                    },
                                                    responsive: true,
                                                    maintainAspectRatio: false,
                                                    plugins: {
                                                        legend: {
                                                            position: 'top',
                                                        },
                                                        title: {
                                                            display: false,
                                                            text: 'Blood Donations vs. Usage'
                                                        }
                                                    }
                                                }
                                            });
                                        }

                                        const updateStatusModal = document.getElementById('updateStatusModal');
                                        if (updateStatusModal) {
                                            updateStatusModal.addEventListener('show.bs.modal', function(event) {
                                                const button = event.relatedTarget;
                                                const bagId = button.getAttribute('data-bag-id');
                                                const inventoryId = button.getAttribute('data-inventory-id');
                                                const currentStatus = button.getAttribute('data-current-status');
                                                const expiryDate = button.getAttribute('data-expiry-date');

                                                updateStatusModal.querySelector('#modalBagId').textContent = bagId;
                                                updateStatusModal.querySelector('#modal-inventory-id-input').value = inventoryId;
                                                updateStatusModal.querySelector('#modalStatus').value = currentStatus;
                                                updateStatusModal.querySelector('#modalExpiryDate').value = expiryDate;
                                            });
                                        }
                                    });
                                </script>
                            <?php
                                break;

                            case 'admin_messages':
                                check_auth('admin');
                                $messages = $db->query("SELECT * FROM messages ORDER BY created_at DESC")->fetchAll();
                            ?>
                                <h1 class="h2 mb-4">User Messages</h1>
                                <div class="card shadow-sm">
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>Sender</th>
                                                        <th>Email</th>
                                                        <th>Subject</th>
                                                        <th>Message</th>
                                                        <th>Sent On</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($messages)) : foreach ($messages as $msg) : ?>
                                                            <tr class="<?= $msg['read_status'] == 0 ? 'fw-bold table-light' : '' ?>">
                                                                <td><?= sanitize($msg['sender_name']) ?></td>
                                                                <td><?= sanitize($msg['sender_email']) ?></td>
                                                                <td><?= sanitize($msg['subject']) ?></td>
                                                                <td><?= nl2br(substr(sanitize($msg['message']), 0, 100)) ?><?= strlen($msg['message']) > 100 ? '...' : '' ?></td>
                                                                <td><?= date('M j, Y H:i', strtotime($msg['created_at'])) ?></td>
                                                                <td>
                                                                    <span class="badge bg-<?= $msg['read_status'] == 0 ? 'warning text-dark' : 'success' ?>">
                                                                        <?= $msg['read_status'] == 0 ? 'Unread' : 'Read' ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <div class="btn-group" role="group">
                                                                        <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#viewMessageModal" data-message-id="<?= $msg['id'] ?>" data-sender-name="<?= sanitize($msg['sender_name']) ?>" data-sender-email="<?= sanitize($msg['sender_email']) ?>" data-subject="<?= sanitize($msg['subject']) ?>" data-message-content="<?= sanitize($msg['message']) ?>" data-created-at="<?= date('M j, Y H:i', strtotime($msg['created_at'])) ?>">
                                                                            <?= render_icon('eye') ?> View
                                                                        </button>
                                                                        <?php if ($msg['read_status'] == 0) : ?>
                                                                            <form method="post" class="d-inline ms-1">
                                                                                <input type="hidden" name="action" value="mark_message_read">
                                                                                <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Mark as Read"><?= render_icon('check-circle') ?></button>
                                                                            </form>
                                                                        <?php endif; ?>
                                                                        <form method="post" onsubmit="return confirm('Are you sure you want to delete this message?');" class="d-inline ms-1">
                                                                            <input type="hidden" name="action" value="delete_message">
                                                                            <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Message"><?= render_icon('trash') ?></button>
                                                                        </form>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach;
                                                    else : ?>
                                                        <tr>
                                                            <td colspan="7" class="text-center text-muted py-4">No messages received yet.</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- View Message Modal -->
                                <div class="modal fade" id="viewMessageModal" tabindex="-1" aria-labelledby="viewMessageModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header bg-info text-white">
                                                <h5 class="modal-title" id="viewMessageModalLabel">Message Details</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p><strong>From:</strong> <span id="modalSenderName"></span> (<span id="modalSenderEmail"></span>)</p>
                                                <p><strong>Subject:</strong> <span id="modalSubject"></span></p>
                                                <p><strong>Sent On:</strong> <span id="modalCreatedAt"></span></p>
                                                <hr>
                                                <p><strong>Message:</strong></p>
                                                <p id="modalMessageContent" class="alert alert-light border"></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const viewMessageModal = document.getElementById('viewMessageModal');
                                        if (viewMessageModal) {
                                            viewMessageModal.addEventListener('show.bs.modal', function(event) {
                                                const button = event.relatedTarget;
                                                document.getElementById('modalSenderName').textContent = button.getAttribute('data-sender-name');
                                                document.getElementById('modalSenderEmail').textContent = button.getAttribute('data-sender-email');
                                                document.getElementById('modalSubject').textContent = button.getAttribute('data-subject');
                                                document.getElementById('modalMessageContent').textContent = button.getAttribute('data-message-content');
                                                document.getElementById('modalCreatedAt').textContent = button.getAttribute('data-created-at');

                                                // Optionally, mark as read via AJAX if not already read
                                                const messageId = button.getAttribute('data-message-id');
                                                const csrfToken = '<?= $csrf_token ?>'; // Get CSRF token from PHP

                                                // Find the row and update its class
                                                const row = button.closest('tr');
                                                if (row && row.classList.contains('fw-bold')) {
                                                    row.classList.remove('fw-bold', 'table-light');
                                                    const badge = row.querySelector('.badge');
                                                    if (badge) {
                                                        badge.classList.remove('bg-warning', 'text-dark');
                                                        badge.classList.add('bg-success');
                                                        badge.textContent = 'Read';
                                                    }
                                                    // Send AJAX request to mark as read
                                                    fetch(window.location.href, {
                                                        method: 'POST',
                                                        headers: {
                                                            'Content-Type': 'application/x-www-form-urlencoded',
                                                        },
                                                        body: new URLSearchParams({
                                                            action: 'mark_message_read',
                                                            message_id: messageId,
                                                            csrf_token: csrfToken
                                                        })
                                                    }).then(response => {
                                                        if (!response.ok) {
                                                            console.error('Failed to mark message as read via AJAX');
                                                        }
                                                    }).catch(error => {
                                                        console.error('AJAX error:', error);
                                                    });
                                                }
                                            });
                                        }
                                    });
                                </script>
                            <?php
                                break;

                            case 'how_to_donate':
                            ?>
                                <h1 class="h2 mb-4">How to Donate Blood</h1>
                                <div class="card shadow-sm mb-4">
                                    <div class="card-body">
                                        <h3 class="text-primary mb-3">Why Donate Blood?</h3>
                                        <p>Blood donation is a selfless act that saves millions of lives each year. It is a vital resource for planned medical procedures, accident victims, cancer patients, and those with chronic illnesses. Your single donation can save up to three lives!</p>
                                        <p>Beyond saving lives, donating blood also offers potential health benefits for the donor, such as reducing iron stores, which can lower the risk of heart disease.</p>

                                        <h3 class="text-primary mt-5 mb-3">Who Can Donate? (General Guidelines)</h3>
                                        <p>While specific criteria may vary, generally, you can donate blood if you meet the following:</p>
                                        <ul>
                                            <li><strong>Age:</strong> Usually between 18 and 65 years old.</li>
                                            <li><strong>Weight:</strong> At least 50 kg (110 lbs).</li>
                                            <li><strong>Health:</strong> In good general health, free from fever, flu, or other infections.</li>
                                            <li><strong>Hemoglobin Level:</strong> A minimum hemoglobin level is required (checked before donation).</li>
                                            <li><strong>Time Since Last Donation:</strong> At least 3 months (90 days) must have passed since your last whole blood donation.</li>
                                            <li><strong>No Risk Behaviors:</strong> No history of high-risk behaviors that could transmit blood-borne diseases.</li>
                                            <li><strong>No Recent Tattoos/Piercings:</strong> Usually a waiting period of 6-12 months after tattoos or piercings.</li>
                                            <li><strong>No Recent Major Surgery:</strong> A waiting period is typically required after major surgeries.</li>
                                        </ul>
                                        <p class="alert alert-info"><strong>Important:</strong> These are general guidelines. Always consult with the medical staff at the donation center for precise eligibility criteria based on your individual health history and local regulations.</p>

                                        <h3 class="text-primary mt-5 mb-3">The Donation Process: What to Expect</h3>
                                        <ol>
                                            <li><strong>Registration:</strong> You'll fill out a registration form with your personal details and medical history.</li>
                                            <li><strong>Mini-Physical:</strong> A healthcare professional will check your temperature, pulse, blood pressure, and hemoglobin level.</li>
                                            <li><strong>Confidential Interview:</strong> You'll have a private interview to discuss your health history and lifestyle to ensure your safety and the safety of the blood supply.</li>
                                            <li><strong>Donation:</strong> The actual blood collection takes about 10-15 minutes for whole blood. A sterile needle is inserted into a vein in your arm.</li>
                                            <li><strong>Refreshments:</strong> After donating, you'll be asked to rest for a short while and enjoy some refreshments to help your body recover.</li>
                                            <li><strong>Post-Donation Care:</strong> You'll receive instructions on what to do and avoid after donation.</li>
                                        </ol>

                                        <h3 class="text-primary mt-5 mb-3">Tips for a Successful Donation</h3>
                                        <ul>
                                            <li>Get a good night's sleep before donating.</li>
                                            <li>Eat a healthy meal before your donation.</li>
                                            <li>Drink plenty of fluids (water, juice) before and after donation.</li>
                                            <li>Avoid fatty foods before donation.</li>
                                            <li>Wear comfortable clothing with sleeves that can be rolled up.</li>
                                            <li>Bring a valid ID.</li>
                                        </ul>

                                        <p class="mt-4">Thank you for considering becoming a blood donor. Your contribution is invaluable!</p>
                                        <div class="text-center mt-5">
                                            <a href="?page=register" class="btn btn-primary btn-lg"><?= render_icon('person-plus-fill') ?> Register as a Donor Today</a>
                                        </div>
                                    </div>
                                </div>
                            <?php
                                break;

                            case 'contact':
                                $user_id = get_current_user_id();
                                $user_info = null;
                                if ($user_id) {
                                    $stmt = $db->prepare("SELECT full_name, email FROM users WHERE id = ?");
                                    $stmt->execute([$user_id]);
                                    $user_info = $stmt->fetch();
                                }
                            ?>
                                <h1 class="h2 mb-4">Contact Us</h1>
                                <div class="card shadow-sm mb-4">
                                    <div class="card-body">
                                        <p class="lead">Have a question, feedback, or need assistance? Please fill out the form below and we'll get back to you as soon as possible.</p>

                                        <form method="post" action="">
                                            <input type="hidden" name="action" value="send_message">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                                            <div class="mb-3">
                                                <label for="sender_name" class="form-label">Your Name</label>
                                                <input type="text" class="form-control" id="sender_name" name="sender_name" value="<?= sanitize($user_info['full_name'] ?? '') ?>" required <?= $user_info ? 'readonly' : '' ?>>
                                            </div>
                                            <div class="mb-3">
                                                <label for="sender_email" class="form-label">Your Email</label>
                                                <input type="email" class="form-control" id="sender_email" name="sender_email" value="<?= sanitize($user_info['email'] ?? '') ?>" required <?= $user_info ? 'readonly' : '' ?>>
                                            </div>
                                            <div class="mb-3">
                                                <label for="subject" class="form-label">Subject</label>
                                                <input type="text" class="form-control" id="subject" name="subject" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="message" class="form-label">Message</label>
                                                <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary"><?= render_icon('send') ?> Send Message</button>
                                        </form>
                                    </div>
                                </div>

                                <div class="card shadow-sm">
                                    <div class="card-body">
                                        <h3 class="text-primary mb-3">Other Ways to Reach Us</h3>
                                        <p><?= render_icon('geo-alt-fill') ?> <strong>Address:</strong> 123 Blood Donor Street, Welfare City, Pakistan</p>
                                        <p><?= render_icon('telephone-fill') ?> <strong>Phone:</strong> +92 300 1234567</p>
                                        <p><?= render_icon('envelope-fill') ?> <strong>Email:</strong> info@poorpeoplewalfare.org</p>
                                        <p class="text-muted small">Please note: For urgent blood requests, please use the "Blood Requests" section after logging in.</p>
                                    </div>
                                </div>
                            <?php
                                break;

                            case 'admin_backup':
                                check_auth('admin');
                            ?>
                                <h1 class="h2 mb-4">Backup & Restore</h1>
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <div class="card shadow-sm">
                                            <div class="card-body">
                                                <h5 class="card-title text-primary"><?= render_icon('database-down') ?> Create a Backup</h5>
                                                <p class="card-text">Download a complete backup of the application database (<code><?= basename(DB_FILE) ?></code>). Keep this file in a safe and secure place.</p>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="backup_db">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <button type="submit" class="btn btn-primary"><?= render_icon('download') ?> Download Backup Now</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <div class="card border-danger shadow-sm">
                                            <div class="card-header bg-danger text-white rounded-top">
                                                <h5 class="card-title mb-0"><?= render_icon('database-up') ?> Restore from Backup</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="alert alert-danger mb-3"><strong>WARNING:</strong> Restoring will completely overwrite the current database with the contents of the backup file. This action cannot be undone. All current data will be lost.</div>
                                                <form method="post" enctype="multipart/form-data" onsubmit="return confirm('Are you absolutely sure you want to overwrite the database? All current data will be lost and you will be logged out.');">
                                                    <input type="hidden" name="action" value="restore_db">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <div class="mb-3">
                                                        <label for="backup_file" class="form-label">Select .sqlite Backup File</label>
                                                        <input class="form-control" type="file" id="backup_file" name="backup_file" accept=".sqlite" required>
                                                    </div>
                                                    <button type="submit" class="btn btn-danger"><?= render_icon('upload') ?> Restore Database</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        <?php
                                break;

                            default:
                                echo "<div class='alert alert-danger text-center shadow-sm'>Page not found. Redirecting to home...</div>";
                                echo "<script>setTimeout(() => window.location.href = '?page=home', 2000);</script>";
                                break;
                        }
                        ?>
                        </div>
                    </main>
                    <footer class="mt-auto py-3 bg-light <?php if (is_logged_in()) echo 'main-content'; ?>">
                        <div class="container text-center">
                            <span class="text-muted">© <?= date('Y') ?> <?= SITE_NAME ?>. All Rights Reserved. Application by Yasin Ullah. Version <?= APP_VERSION ?></span>
                        </div>
                    </footer>
                    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
                    <script>
                        // Sidebar toggle for smaller screens
                        const sidebarToggle = document.getElementById('sidebarToggle');
                        const sidebarMenu = document.getElementById('sidebarMenu');
                        if (sidebarToggle && sidebarMenu) {
                            sidebarToggle.addEventListener('click', () => {
                                sidebarMenu.classList.toggle('active');
                            });
                            // Close sidebar when clicking outside on small screens
                            document.addEventListener('click', (event) => {
                                if (window.innerWidth < 992 && !sidebarMenu.contains(event.target) && !sidebarToggle.contains(event.target) && sidebarMenu.classList.contains('active')) {
                                    sidebarMenu.classList.remove('active');
                                }
                            });
                        }

                        // Initialize tooltips
                        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
                        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                            return new bootstrap.Tooltip(tooltipTriggerEl)
                        })

                        // Auto-dismiss alerts after 5 seconds
                        document.addEventListener('DOMContentLoaded', function() {
                            const alerts = document.querySelectorAll('.alert-dismissible');
                            alerts.forEach(alert => {
                                setTimeout(() => {
                                    const bsAlert = new bootstrap.Alert(alert);
                                    bsAlert.close();
                                }, 5000);
                            });
                        });
                    </script>
                <?php endif; ?>
</body>

</html>
<?php ob_end_flush(); ?>