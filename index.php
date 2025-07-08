<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Karachi');
define('DB_FILE', './community_blood_donation.sqlite');
define('SITE_TITLE', 'Community Blood Donation Register');
define('DEFAULT_LANG', 'en');

$LANGUAGES = [
    'en' => [
        'site_title' => SITE_TITLE,
        'toggle_nav' => 'Toggle navigation',
        'home' => 'Home',
        'donors' => 'Donors',
        'drives' => 'Drives',
        'requests' => 'Requests',
        'notes' => 'Notes',
        'users' => 'Users',
        'settings' => 'Settings',
        'login' => 'Login',
        'logout' => 'Logout',
        'register' => 'Register',
        'dashboard' => 'Dashboard',
        'welcome' => 'Welcome',
        'language' => 'Language',
        'english' => 'English',
        'urdu' => 'Urdu',
        'username' => 'Username',
        'password' => 'Password',
        'role' => 'Role',
        'name' => 'Name',
        'age' => 'Age',
        'gender' => 'Gender',
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
        'blood_group' => 'Blood Group',
        'contact' => 'Contact',
        'city' => 'City',
        'last_donation' => 'Last Donation Date',
        'actions' => 'Actions',
        'search' => 'Search',
        'submit' => 'Submit',
        'cancel' => 'Cancel',
        'add_new' => 'Add New',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'approve' => 'Approve',
        'approved' => 'Approved',
        'pending' => 'Pending',
        'not_approved' => 'Not Approved',
        'status' => 'Status',
        'details' => 'Details',
        'title' => 'Title',
        'date' => 'Date',
        'location' => 'Location',
        'organizer' => 'Organizer',
        'description' => 'Description',
        'participants' => 'Participants',
        'created_by' => 'Created By',
        'created_at' => 'Created At',
        'content' => 'Content',
        'key' => 'Key',
        'value' => 'Value',
        'update' => 'Update',
        'search_donors' => 'Search Donors',
        'register_donor' => 'Register as Donor/Recipient',
        'update_profile' => 'Update Profile',
        'add_drive' => 'Add Donation Drive',
        'manage_drives' => 'Manage Donation Drives',
        'submit_request' => 'Submit Blood Request',
        'view_requests' => 'View Blood Requests',
        'manage_users' => 'Manage Users',
        'approve_users' => 'Approve Users',
        'post_note' => 'Post Religious Note',
        'manage_notes' => 'Manage Notes',
        'site_settings' => 'Site Settings',
        'total_donors' => 'Total Donors',
        'total_drives' => 'Total Drives',
        'pending_requests' => 'Pending Requests',
        'pending_users' => 'Pending Users',
        'recent_activity' => 'Recent Activity',
        'latest_donors' => 'Latest Donors',
        'upcoming_drives' => 'Upcoming Drives',
        'recent_requests' => 'Recent Requests',
        'public' => 'Public',
        'user' => 'User',
        'ulama' => 'Ulama',
        'admin' => 'Admin',
        'login_required' => 'Login Required',
        'access_denied' => 'Access Denied',
        'registration_success' => 'Registration successful. Please wait for approval.',
        'login_invalid' => 'Invalid username or password.',
        'logged_out' => 'You have been logged out.',
        'profile_updated' => 'Profile updated successfully.',
        'donor_added' => 'Donor added successfully.',
        'donor_deleted' => 'Donor deleted successfully.',
        'drive_added' => 'Drive added successfully.',
        'drive_updated' => 'Drive updated successfully.',
        'drive_deleted' => 'Drive deleted successfully.',
        'request_submitted' => 'Blood request submitted successfully.',
        'request_updated' => 'Request status updated.',
        'request_deleted' => 'Request deleted successfully.',
        'user_approved' => 'User approved successfully.',
        'user_updated' => 'User updated successfully.',
        'user_deleted' => 'User deleted successfully.',
        'note_added' => 'Note added successfully.',
        'note_updated' => 'Note updated successfully.',
        'note_deleted' => 'Note deleted successfully.',
        'settings_updated' => 'Settings updated successfully.',
        'confirm_delete' => 'Are you sure you want to delete this item?',
        'error_occurred' => 'An error occurred. Please try again.',
        'is_recipient' => 'Registering as Recipient?',
        'yes' => 'Yes',
        'no' => 'No',
         'register_info' => 'If you are already a registered user, please login to manage your donor profile.',
         'request_info' => 'Your request will be visible to registered donors with matching blood group.',
         'view_matching_requests' => 'View Matching Requests',
         'no_records' => 'No records found.',
         'my_profile' => 'My Profile',
    ],
    'ur' => [
        'site_title' => 'کمیونٹی بلڈ ڈونیشن رجسٹر',
        'toggle_nav' => 'نیویگیشن ٹوگل کریں',
        'home' => 'مرکزی صفحہ',
        'donors' => 'خون دینے والے',
        'drives' => 'ڈونیشن مہم',
        'requests' => 'خون کی درخواستیں',
        'notes' => 'نوٹس',
        'users' => 'صارفین',
        'settings' => 'ترتیبات',
        'login' => 'لاگ ان',
        'logout' => 'لاگ آؤٹ',
        'register' => 'رجسٹر کریں',
        'dashboard' => 'ڈیش بورڈ',
        'welcome' => 'خوش آمدید',
        'language' => 'زبان',
        'english' => 'English',
        'urdu' => 'اردو',
        'username' => 'صارف نام',
        'password' => 'پاس ورڈ',
        'role' => 'کردار',
        'name' => 'نام',
        'age' => 'عمر',
        'gender' => 'جنس',
        'male' => 'مرد',
        'female' => 'عورت',
        'other' => 'دیگر',
        'blood_group' => 'بلڈ گروپ',
        'contact' => 'رابطہ نمبر',
        'city' => 'شہر',
        'last_donation' => 'آخری عطیہ کی تاریخ',
        'actions' => 'اعمال',
        'search' => 'تلاش کریں',
        'submit' => 'جمع کرائیں',
        'cancel' => 'منسوخ کریں',
        'add_new' => 'نیا شامل کریں',
        'edit' => 'ترمیم',
        'delete' => 'حذف کریں',
        'approve' => 'منظور کریں',
        'approved' => 'منظور شدہ',
        'pending' => 'زیر التواء',
        'not_approved' => 'منظور نہیں',
        'status' => 'حیثیت',
        'details' => 'تفصیلات',
        'title' => 'عنوان',
        'date' => 'تاریخ',
        'location' => 'مقام',
        'organizer' => 'منتظم',
        'description' => 'تفصیل',
        'participants' => 'شرکاء',
        'created_by' => 'بنایا گیا از',
        'created_at' => 'بنانے کی تاریخ',
        'content' => 'مواد',
        'key' => 'کلید',
        'value' => 'قدر',
        'update' => 'اپ ڈیٹ',
        'search_donors' => 'عطیہ دہندگان تلاش کریں',
        'register_donor' => 'بطور ڈونر/وصول کنندہ رجسٹر ہوں',
        'update_profile' => 'پروفائل اپ ڈیٹ کریں',
        'add_drive' => 'ڈونیشن مہم شامل کریں',
        'manage_drives' => 'ڈونیشن مہم کا نظم کریں',
        'submit_request' => 'خون کی درخواست جمع کرائیں',
        'view_requests' => 'خون کی درخواستیں دیکھیں',
        'manage_users' => 'صارفین کا نظم کریں',
        'approve_users' => 'صارفین کو منظور کریں',
        'post_note' => 'مذہبی نوٹ پوسٹ کریں',
        'manage_notes' => 'نوٹس کا نظم کریں',
        'site_settings' => 'سائٹ کی ترتیبات',
        'total_donors' => 'کل عطیہ دہندگان',
        'total_drives' => 'کل مہمات',
        'pending_requests' => 'زیر التواء درخواستیں',
        'pending_users' => 'زیر التواء صارفین',
        'recent_activity' => 'حالیہ سرگرمی',
        'latest_donors' => 'تازہ ترین عطیہ دہندگان',
        'upcoming_drives' => 'آنے والی مہمات',
        'recent_requests' => 'حالیہ درخواستیں',
        'public' => 'عوام',
        'user' => 'صارف',
        'ulama' => 'علماء',
        'admin' => 'ایڈمن',
        'login_required' => 'لاگ ان درکار ہے',
        'access_denied' => 'رسائی ممنوع ہے',
        'registration_success' => 'رجسٹریشن کامیاب۔ براہ کرم منظوری کا انتظار کریں۔',
        'login_invalid' => 'غلط صارف نام یا پاس ورڈ۔',
        'logged_out' => 'آپ لاگ آؤٹ ہو چکے ہیں۔',
        'profile_updated' => 'پروفائل کامیابی سے اپ ڈیٹ ہو گیا۔',
        'donor_added' => 'عطیہ دہندہ کامیابی سے شامل ہو گیا۔',
        'donor_deleted' => 'عطیہ دہندہ کامیابی سے حذف ہو گیا۔',
        'drive_added' => 'مہم کامیابی سے شامل ہو گئی۔',
        'drive_updated' => 'مہم کامیابی سے اپ ڈیٹ ہو گئی۔',
        'drive_deleted' => 'مہم کامیابی سے حذف ہو گئی۔',
        'request_submitted' => 'خون کی درخواست کامیابی سے جمع ہو گئی۔',
        'request_updated' => 'درخواست کی حیثیت اپ ڈیٹ ہو گئی۔',
        'request_deleted' => 'درخواست کامیابی سے حذف ہو گئی۔',
        'user_approved' => 'صارف کامیابی سے منظور ہو گیا۔',
        'user_updated' => 'صارف کامیابی سے اپ ڈیٹ ہو گیا۔',
        'user_deleted' => 'صارف کامیابی سے حذف ہو گیا۔',
        'note_added' => 'نوٹ کامیابی سے شامل ہو گیا۔',
        'note_updated' => 'نوٹ کامیابی سے اپ ڈیٹ ہو گیا۔',
        'note_deleted' => 'نوٹ کامیابی سے حذف ہو گیا۔',
        'settings_updated' => 'ترتیبات کامیابی سے اپ ڈیٹ ہو گئیں۔',
        'confirm_delete' => 'کیا آپ واقعی اس آئٹم کو حذف کرنا چاہتے ہیں؟',
        'error_occurred' => 'ایک خرابی پیش آگئی۔ براہ کرم دوبارہ کوشش کریں.',
        'is_recipient' => 'کیا آپ وصول کنندہ کے طور پر رجسٹر ہو رہے ہیں؟',
        'yes' => 'ہاں',
        'no' => 'نہیں',
        'register_info' => 'اگر آپ پہلے سے رجسٹرڈ صارف ہیں، تو براہ کرم اپنے ڈونر پروفائل کا نظم کرنے کے لیے لاگ ان کریں۔',
        'request_info' => 'آپ کی درخواست مماثل بلڈ گروپ والے رجسٹرڈ ڈونرز کو نظر آئے گی۔',
        'view_matching_requests' => 'مطابقت پذیر درخواستیں دیکھیں',
        'no_records' => 'کوئی ریکارڈ نہیں ملا۔',
        'my_profile' => 'میرا پروفائل',
    ]
];

$current_lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : (isset($_GET['lang']) ? $_GET['lang'] : DEFAULT_LANG);
if (!isset($LANGUAGES[$current_lang])) {
    $current_lang = DEFAULT_LANG;
}
$_SESSION['lang'] = $current_lang;

function t($key) {
    global $LANGUAGES, $current_lang;
    return $LANGUAGES[$current_lang][$key] ?? $LANGUAGES[DEFAULT_LANG][$key] ?? $key;
}

function get_db() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO('sqlite:' . DB_FILE);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $db->exec("PRAGMA foreign_keys = ON;");
        } catch (PDOException $e) {
            die("DB Connection Error: " . $e->getMessage());
        }
    }
    return $db;
}

function init_db() {
    $db = get_db();
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'user' CHECK(role IN ('public', 'user', 'ulama', 'admin')),
        approved INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS donors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        name TEXT NOT NULL,
        age INTEGER,
        gender TEXT,
        blood_group TEXT NOT NULL,
        contact TEXT NOT NULL,
        city TEXT,
        last_donation_date DATE,
        is_recipient INTEGER DEFAULT 0,
        registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS drives (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        drive_date DATETIME NOT NULL,
        location TEXT NOT NULL,
        organizer TEXT,
        description TEXT,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        blood_group TEXT NOT NULL,
        city TEXT NOT NULL,
        contact TEXT NOT NULL,
        details TEXT,
        requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'fulfilled', 'cancelled')),
        requested_by_public INTEGER DEFAULT 1
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        created_by INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )");
     $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");

    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $admin_user = 'admin';
        $admin_pass = 'password';
        $hashed_pass = password_hash($admin_pass, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, role, approved) VALUES (?, ?, 'admin', 1)");
        $stmt->execute([$admin_user, $hashed_pass]);
        echo "<div class='alert alert-info'>Admin user created: username '{$admin_user}', password '{$admin_pass}'. Please change the password immediately.</div>";
    }
    $stmt = $db->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
    $stmt->execute(['site_title', SITE_TITLE]);
}

init_db();

function get_setting($key) {
    $db = get_db();
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetchColumn();
    return $result !== false ? $result : null;
}

function update_setting($key, $value) {
    $db = get_db();
    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
    return $stmt->execute([$key, $value]);
}

function redirect($page = '', $params = []) {
    $url = $_SERVER['PHP_SELF'];
    if ($page) {
        $params['page'] = $page;
    }
     if (!isset($params['lang']) && isset($_SESSION['lang'])) {
        $params['lang'] = $_SESSION['lang'];
    }
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    header("Location: " . $url);
    exit;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_current_user_role() {
    return $_SESSION['role'] ?? 'public';
}

function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}
function get_current_username() {
    return $_SESSION['username'] ?? null;
}

function check_auth($required_role = null) {
    if (!is_logged_in()) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => t('login_required')];
        redirect('login');
    }
    if ($required_role) {
        $user_role = get_current_user_role();
        $allowed = false;
        if (is_array($required_role)) {
            $allowed = in_array($user_role, $required_role);
        } else {
            $allowed = $user_role === $required_role;
        }
        if ($user_role !== 'admin' && !$allowed) {
             $_SESSION['message'] = ['type' => 'danger', 'text' => t('access_denied')];
             redirect('dashboard');
        }
    }
     if (get_current_user_role() !== 'admin' && $_SESSION['approved'] == 0 && $_GET['page'] !== 'logout') {
         $_SESSION['message'] = ['type' => 'warning', 'text' => t('not_approved') . ' Please contact admin or ulama.'];
         redirect('dashboard');
     }
}

function check_permission($action_roles) {
    if (!is_logged_in()) return false;
    $user_role = get_current_user_role();
    if ($user_role === 'admin') return true;
    if (is_array($action_roles)) {
        return in_array($user_role, $action_roles);
    } else {
        return $user_role === $action_roles;
    }
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

$action = $_POST['action'] ?? null;
$page = sanitize($_GET['page'] ?? 'dashboard');
$db = get_db();
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

// POST Actions Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    try {
        switch ($action) {
            case 'login':
                $username = sanitize($_POST['username']);
                $password = $_POST['password'];
                $stmt = $db->prepare("SELECT id, username, password_hash, role, approved FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['approved'] = $user['approved'];
                    redirect('dashboard');
                } else {
                    $message = ['type' => 'danger', 'text' => t('login_invalid')];
                }
                break;

            case 'register':
                $username = sanitize($_POST['username']);
                $password = $_POST['password'];
                $role = 'user';
                 if (empty($username) || empty($password)) {
                     $message = ['type' => 'danger', 'text' => 'Username and password are required.'];
                     break;
                 }
                 $stmt_check = $db->prepare("SELECT id FROM users WHERE username = ?");
                 $stmt_check->execute([$username]);
                 if($stmt_check->fetch()) {
                      $message = ['type' => 'danger', 'text' => 'Username already exists.'];
                      break;
                 }

                $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password_hash, role, approved) VALUES (?, ?, ?, 0)");
                if ($stmt->execute([$username, $hashed_pass, $role])) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => t('registration_success')];
                    redirect('login');
                } else {
                    $message = ['type' => 'danger', 'text' => t('error_occurred')];
                }
                break;

             case 'add_donor':
             case 'update_donor':
                check_auth(['user', 'ulama', 'admin']);
                $user_id = is_logged_in() ? get_current_user_id() : null;
                $name = sanitize($_POST['name']);
                $age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
                $gender = sanitize($_POST['gender']);
                $blood_group = sanitize($_POST['blood_group']);
                $contact = sanitize($_POST['contact']);
                $city = sanitize($_POST['city']);
                $last_donation_date = sanitize($_POST['last_donation_date']) ?: null;
                $is_recipient = isset($_POST['is_recipient']) ? 1 : 0;
                $donor_id = filter_input(INPUT_POST, 'donor_id', FILTER_VALIDATE_INT);

                 if (empty($name) || empty($blood_group) || empty($contact)) {
                      $message = ['type' => 'danger', 'text' => 'Name, Blood Group, and Contact are required.'];
                      break;
                 }

                if ($action === 'add_donor') {
                    // Check if user already has a donor profile if logged in
                    if($user_id) {
                         $stmt_check = $db->prepare("SELECT id FROM donors WHERE user_id = ?");
                         $stmt_check->execute([$user_id]);
                         if ($stmt_check->fetch()) {
                              $_SESSION['message'] = ['type' => 'warning', 'text' => 'You already have a donor profile. You can edit it from your profile page.'];
                              redirect('profile');
                              break;
                         }
                    }
                    $sql = "INSERT INTO donors (user_id, name, age, gender, blood_group, contact, city, last_donation_date, is_recipient) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [$user_id, $name, $age, $gender, $blood_group, $contact, $city, $last_donation_date, $is_recipient];
                     $success_msg = t('donor_added');
                } else { // update_donor
                     $target_user_id = null;
                     if ($donor_id) { // Find user_id associated with donor_id if exists
                         $stmt_get_user = $db->prepare("SELECT user_id FROM donors WHERE id = ?");
                         $stmt_get_user->execute([$donor_id]);
                         $target_user_id = $stmt_get_user->fetchColumn();
                     }

                     // Allow update if admin, or if user owns the profile
                     if (!check_permission('admin') && $target_user_id != get_current_user_id()) {
                         $_SESSION['message'] = ['type' => 'danger', 'text' => t('access_denied')];
                         redirect('donors');
                         break;
                     }

                    $sql = "UPDATE donors SET name=?, age=?, gender=?, blood_group=?, contact=?, city=?, last_donation_date=?, is_recipient=? WHERE id=?";
                    $params = [$name, $age, $gender, $blood_group, $contact, $city, $last_donation_date, $is_recipient, $donor_id];
                    $success_msg = t('profile_updated');
                }

                $stmt = $db->prepare($sql);
                if ($stmt->execute($params)) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => $success_msg];
                    if (check_permission('user') && $action === 'update_donor' && $target_user_id == get_current_user_id()) {
                        redirect('profile');
                    } else {
                         redirect('donors');
                    }
                } else {
                    $message = ['type' => 'danger', 'text' => t('error_occurred') . ' ' . $stmt->errorInfo()[2]];
                }
                break;

             case 'delete_donor':
                 check_auth(['admin']);
                 $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                 if ($id) {
                     $stmt = $db->prepare("DELETE FROM donors WHERE id = ?");
                     if ($stmt->execute([$id])) {
                          $_SESSION['message'] = ['type' => 'success', 'text' => t('donor_deleted')];
                     } else {
                          $_SESSION['message'] = ['type' => 'danger', 'text' => t('error_occurred')];
                     }
                 }
                 redirect('donors');
                 break;

            case 'add_drive':
            case 'update_drive':
                check_auth(['ulama', 'admin']);
                $title = sanitize($_POST['title']);
                $drive_date = sanitize($_POST['drive_date']);
                $location = sanitize($_POST['location']);
                $organizer = sanitize($_POST['organizer']);
                $description = sanitize($_POST['description']);
                $created_by = get_current_user_id();
                $drive_id = filter_input(INPUT_POST, 'drive_id', FILTER_VALIDATE_INT);

                 if (empty($title) || empty($drive_date) || empty($location)) {
                     $message = ['type' => 'danger', 'text' => 'Title, Date, and Location are required.'];
                     break;
                 }


                if ($action === 'add_drive') {
                    $sql = "INSERT INTO drives (title, drive_date, location, organizer, description, created_by) VALUES (?, ?, ?, ?, ?, ?)";
                    $params = [$title, $drive_date, $location, $organizer, $description, $created_by];
                    $success_msg = t('drive_added');
                } else {
                    $sql = "UPDATE drives SET title=?, drive_date=?, location=?, organizer=?, description=? WHERE id=?";
                    $params = [$title, $drive_date, $location, $organizer, $description, $drive_id];
                    $success_msg = t('drive_updated');
                }
                $stmt = $db->prepare($sql);
                if ($stmt->execute($params)) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => $success_msg];
                } else {
                     $_SESSION['message'] = ['type' => 'danger', 'text' => t('error_occurred')];
                }
                 redirect('drives');
                break;

             case 'delete_drive':
                 check_auth(['ulama', 'admin']);
                 $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                 if ($id) {
                     $stmt = $db->prepare("DELETE FROM drives WHERE id = ?");
                      if ($stmt->execute([$id])) {
                           $_SESSION['message'] = ['type' => 'success', 'text' => t('drive_deleted')];
                      } else {
                           $_SESSION['message'] = ['type' => 'danger', 'text' => t('error_occurred')];
                      }
                 }
                 redirect('drives');
                 break;

            case 'submit_request':
                $name = sanitize($_POST['name']);
                $blood_group = sanitize($_POST['blood_group']);
                $city = sanitize($_POST['city']);
                $contact = sanitize($_POST['contact']);
                $details = sanitize($_POST['details']);
                $requested_by_public = is_logged_in() ? 0 : 1;

                if (empty($name) || empty($blood_group) || empty($city) || empty($contact)) {
                     $message = ['type' => 'danger', 'text' => 'Name, Blood Group, City, and Contact are required.'];
                     break;
                 }

                $stmt = $db->prepare("INSERT INTO requests (name, blood_group, city, contact, details, requested_by_public) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $blood_group, $city, $contact, $details, $requested_by_public])) {
                     $_SESSION['message'] = ['type' => 'success', 'text' => t('request_submitted')];
                } else {
                     $_SESSION['message'] = ['type' => 'danger', 'text' => t('error_occurred')];
                }
                 redirect('requests');
                break;

            case 'update_request_status':
                 check_auth(['user', 'ulama', 'admin']);
                 $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                 $status = sanitize($_POST['status']);
                 if ($id && in_array($status, ['pending', 'fulfilled', 'cancelled'])) {
                     $stmt = $db->prepare("UPDATE requests SET status = ? WHERE id = ?");
                     if ($stmt->execute([$status, $id])) {
                          $_SESSION['message'] = ['type' => 'success', 'text' => t('request_updated')];
                     } else {
                          $_SESSION['message'] = ['type' => 'danger', 'text' => t('error_occurred')];
                     }
                 }
                  redirect('requests');
                 break;

             case 'delete_request':
                 check_auth(['admin']);
                 $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                  if ($id) {
                     $stmt = $db->prepare("DELETE FROM requests WHERE id = ?");
                     if ($stmt->execute([$id])) {
                          $_SESSION['message'] = ['type' => 'success', 'text' => t('request_deleted')];
                     } else {
                          $_SESSION['message'] = ['type' => 'danger', 'text' => t('error_occurred')];
                     }
                 }
                 redirect('requests');
                 break;

            case 'approve_user':
                check_auth(['ulama', 'admin']);
                 $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                 if ($user_id) {
                     $stmt = $db->prepare("UPDATE users SET approved = 1 WHERE id = ?");
                      if ($stmt->execute([$user_id])) {
                           $_SESSION['message'] = ['type' => 'success', 'text' => t('user_approved')];
                      } else {
                           $_SESSION['message'] = ['type' => 'danger', 'text' => t('error_occurred')];
                      }
                 }
                 redirect('users', ['view' => 'pending']);
                break;

            case 'update_user':
                 check_auth(['admin']);
                 $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                 $role = sanitize($_POST['role']);
                 $approved = isset($_POST['approved']) ? 1 : 0;
                  if ($user_id && in_array($role, ['user', 'ulama', 'admin'])) {
                     // Prevent changing the last admin's role or unapproving them
                      $stmt_check_admin = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                      $stmt_check_admin->execute();
                      $admin_count = $stmt_check_admin->fetchColumn();

                      $stmt_get_user = $db->prepare("SELECT role, approved FROM users WHERE id = ?");
                      $stmt_get_user->execute([$user_id]);
                      $current_user_data = $stmt_get_user->fetch();

                      if ($current_user_data['role'] === 'admin' && $admin_count <= 1 && ($role !== 'admin' || $approved == 0)) {
                          $_SESSION['message'] = ['type' => 'danger', 'text' => 'Cannot modify the last admin user.'];
                      } else {
                          $stmt = $db->prepare("UPDATE users SET role = ?, approved = ? WHERE id = ?");
                          if ($stmt->execute([$role, $approved, $user_id])) {
                               $_SESSION['message'] = ['type' => 'success', 'text' => t('user_updated')];
                          } else {
                               $_SESSION['message'] = ['type' => 'danger', 'text' => t('error_occurred')];
                          }
                      }
                 }
                  redirect('users');
                 break;

             case 'delete_user':
                  check_auth(['admin']);
                  $user_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                  if ($user_id) {
                      // Prevent deleting the last admin
                      $stmt_check_admin = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                      $stmt_check_admin->execute();
                      $admin_count = $stmt_check_admin->fetchColumn();

                       $stmt_get_user = $db->prepare("SELECT role FROM users WHERE id = ?");
                       $stmt_get_user->execute([$user_id]);
                       $user_role = $stmt_get_user->fetchColumn();

                       if ($user_role === 'admin' && $admin_count <= 1) {
                           $_SESSION['message'] = ['type' => 'danger', 'text' => 'Cannot delete the last admin user.'];
                       } else {
                            // Optionally: Handle related data (e.g., set donors.user_id to NULL, drives.created_by to NULL?)
                            // Foreign key constraints handle notes deletion (ON DELETE CASCADE) and setting NULL for others.
                            $stmt_del = $db->prepare("DELETE FROM users WHERE id = ?");
                            if ($stmt_del->execute([$user_id])) {
                                 $_SESSION['message'] = ['type' => 'success', 'text' => t('user_deleted')];
                             } else {
                                 $_SESSION['message'] = ['type' => 'danger', 'text' => t('error_occurred')];
                             }
                       }
                  }
                  redirect('users');
                 break;

            case 'add_note':
            case 'update_note':
                 check_auth(['ulama', 'admin']);
                 $title = sanitize($_POST['title']);
                 $content = sanitize($_POST['content']); // Consider allowing some HTML later if needed, with purification
                 $created_by = get_current_user_id();
                 $note_id = filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT);

                  if (empty($title) || empty($content)) {
                     $message = ['type' => 'danger', 'text' => 'Title and Content are required.'];
                     break;
                 }

                 if ($action === 'add_note') {
                     $sql = "INSERT INTO notes (title, content, created_by) VALUES (?, ?, ?)";
                     $params = [$title, $content, $created_by];
                     $success_msg = t('note_added');
                 } else {
                     $sql = "UPDATE notes SET title=?, content=? WHERE id=?";
                     $params = [$title, $content, $note_id];
                     // Ensure only owner or admin can edit
                     if(!check_permission('admin')) {
                         $stmt_check = $db->prepare("SELECT created_by FROM notes WHERE id = ?");
                         $stmt_check->execute([$note_id]);
                         if($stmt_check->fetchColumn() != $created_by) {
                             $_SESSION['message'] = ['type' => 'danger', 'text' => t('access_denied')];
                             redirect('notes');
                             break;
                         }
                     }
                     $success_msg = t('note_updated');
                 }
                 $stmt = $db->prepare($sql);
                 if ($stmt->execute($params)) {
                      $_SESSION['message'] = ['type' => 'success', 'text' => $success_msg];
                 } else {
                     $_SESSION['message'] = ['type' => 'danger', 'text' => t('error_occurred')];
                 }
                 redirect('notes');
                 break;

             case 'delete_note':
                 check_auth(['ulama', 'admin']);
                 $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                 if ($id) {
                     // Ensure only owner or admin can delete
                     if(!check_permission('admin')) {
                         $stmt_check = $db->prepare("SELECT created_by FROM notes WHERE id = ?");
                         $stmt_check->execute([$id]);
                         if($stmt_check->fetchColumn() != get_current_user_id()) {
                             $_SESSION['message'] = ['type' => 'danger', 'text' => t('access_denied')];
                             redirect('notes');
                             break;
                         }
                     }
                     $stmt = $db->prepare("DELETE FROM notes WHERE id = ?");
                     if ($stmt->execute([$id])) {
                          $_SESSION['message'] = ['type' => 'success', 'text' => t('note_deleted')];
                     } else {
                          $_SESSION['message'] = ['type' => 'danger', 'text' => t('error_occurred')];
                     }
                 }
                 redirect('notes');
                 break;

            case 'update_settings':
                 check_auth(['admin']);
                 $site_title = sanitize($_POST['site_title']);
                 if (update_setting('site_title', $site_title)) {
                      $_SESSION['message'] = ['type' => 'success', 'text' => t('settings_updated')];
                 } else {
                     $_SESSION['message'] = ['type' => 'danger', 'text' => t('error_occurred')];
                 }
                 redirect('settings');
                 break;

             case 'logout':
                session_destroy();
                $_SESSION['message'] = ['type' => 'success', 'text' => t('logged_out')];
                redirect('login');
                break;
        }
    } catch (PDOException $e) {
        $message = ['type' => 'danger', 'text' => t('error_occurred') . ': ' . $e->getMessage()];
    } catch (Exception $e) {
         $message = ['type' => 'danger', 'text' => t('error_occurred') . ': ' . $e->getMessage()];
    }
}

// Redirect logged-in users from login/register pages
if (is_logged_in() && ($page === 'login' || $page === 'register')) {
    redirect('dashboard');
}
// Redirect non-logged-in users trying to access protected pages
if (!is_logged_in() && !in_array($page, ['login', 'register', 'donors', 'drives', 'requests', 'submit_request'])) {
     if ($page !== 'dashboard') { // Allow viewing dashboard elements for public? No, redirect.
         $_SESSION['message'] = ['type' => 'danger', 'text' => t('login_required')];
         redirect('login', ['ref' => $page]);
     } else {
         // Allow public view of specific dashboard sections if needed, otherwise redirect
          redirect('login');
     }
}

// Fetch site title from DB
$site_title_db = get_setting('site_title');
$site_title = $site_title_db ? $site_title_db : SITE_TITLE;

// Role definitions
$roles = ['public', 'user', 'ulama', 'admin'];
$blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= ($current_lang == 'ur') ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize(t($site_title)) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
     <?php if ($current_lang == 'ur'): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.rtl.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400..700&display=swap" rel="stylesheet">
    <style>body, h1, h2, h3, h4, h5, h6, .btn, .form-label, .nav-link, .card-title, .card-text, th, td, p, label, input, select, textarea { font-family: 'Noto Nastaliq Urdu', serif; } .en-font{font-family: sans-serif !important;}</style>
     <?php else: ?>
     <style>body{font-family: sans-serif;}</style>
     <?php endif; ?>
    <style>.form-label{margin-bottom: 0.1rem;} .form-group{margin-bottom: 0.8rem;} .card{margin-bottom:1rem;} body{padding-top: 70px;}</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-danger fixed-top">
    <div class="container">
        <a class="navbar-brand" href="?page=dashboard&lang=<?= $current_lang ?>"><?= sanitize(t($site_title)) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="<?= t('toggle_nav') ?>">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (is_logged_in() || check_permission('public')): ?>
                    <li class="nav-item"><a class="nav-link <?= ($page=='dashboard')?'active':'' ?>" href="?page=dashboard&lang=<?= $current_lang ?>"><?= t('dashboard') ?></a></li>
                    <li class="nav-item"><a class="nav-link <?= ($page=='donors')?'active':'' ?>" href="?page=donors&lang=<?= $current_lang ?>"><?= t('donors') ?></a></li>
                    <li class="nav-item"><a class="nav-link <?= ($page=='drives')?'active':'' ?>" href="?page=drives&lang=<?= $current_lang ?>"><?= t('drives') ?></a></li>
                    <li class="nav-item"><a class="nav-link <?= ($page=='requests'||$page=='submit_request')?'active':'' ?>" href="?page=requests&lang=<?= $current_lang ?>"><?= t('requests') ?></a></li>
                <?php endif; ?>
                 <?php if (check_permission(['ulama', 'admin'])): ?>
                     <li class="nav-item"><a class="nav-link <?= ($page=='notes')?'active':'' ?>" href="?page=notes&lang=<?= $current_lang ?>"><?= t('notes') ?></a></li>
                <?php endif; ?>
                <?php if (check_permission(['ulama', 'admin'])): ?>
                    <li class="nav-item"><a class="nav-link <?= ($page=='users')?'active':'' ?>" href="?page=users&lang=<?= $current_lang ?>"><?= t('users') ?></a></li>
                 <?php endif; ?>
                 <?php if (check_permission('admin')): ?>
                    <li class="nav-item"><a class="nav-link <?= ($page=='settings')?'active':'' ?>" href="?page=settings&lang=<?= $current_lang ?>"><?= t('settings') ?></a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                 <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= t('language') ?> (<?= $current_lang == 'en' ? t('english') : t('urdu') ?>)
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="languageDropdown">
                        <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['lang' => 'en'])) ?>"><?= t('english') ?></a></li>
                        <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['lang' => 'ur'])) ?>"><?= t('urdu') ?></a></li>
                    </ul>
                </li>
                <?php if (is_logged_in()): ?>
                     <li class="nav-item dropdown">
                         <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                             <?= t('welcome') ?>, <?= sanitize(get_current_username()) ?> (<?= t(get_current_user_role()) ?>)
                         </a>
                         <ul class="dropdown-menu" aria-labelledby="userDropdown">
                             <?php if(check_permission('user')): ?>
                             <li><a class="dropdown-item" href="?page=profile&lang=<?= $current_lang ?>"><?= t('my_profile') ?></a></li>
                             <?php endif; ?>
                             <li><hr class="dropdown-divider"></li>
                             <li>
                                 <form method="post" action="" style="display:inline;">
                                     <input type="hidden" name="action" value="logout">
                                     <button type="submit" class="dropdown-item"><?= t('logout') ?></button>
                                 </form>
                             </li>
                         </ul>
                     </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link <?= ($page=='login')?'active':'' ?>" href="?page=login&lang=<?= $current_lang ?>"><?= t('login') ?></a></li>
                    <li class="nav-item"><a class="nav-link <?= ($page=='register')?'active':'' ?>" href="?page=register&lang=<?= $current_lang ?>"><?= t('register') ?></a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <?php if ($message): ?>
        <div class="alert alert-<?= sanitize($message['type']) ?> alert-dismissible fade show" role="alert">
            <?= sanitize($message['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php
    switch ($page) {
        case 'login':
            ?>
            <h2><?= t('login') ?></h2>
            <form method="post" action="">
                <input type="hidden" name="action" value="login">
                <div class="form-group mb-3">
                    <label for="username" class="form-label"><?= t('username') ?></label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="form-group mb-3">
                    <label for="password" class="form-label"><?= t('password') ?></label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary"><?= t('login') ?></button>
                 <a href="?page=register&lang=<?= $current_lang ?>" class="btn btn-secondary"><?= t('register') ?></a>
            </form>
            <?php
            break;

        case 'register':
            ?>
            <h2><?= t('register') ?></h2>
            <form method="post" action="">
                <input type="hidden" name="action" value="register">
                 <div class="form-group mb-3">
                    <label for="reg_username" class="form-label"><?= t('username') ?></label>
                    <input type="text" class="form-control" id="reg_username" name="username" required>
                </div>
                <div class="form-group mb-3">
                    <label for="reg_password" class="form-label"><?= t('password') ?></label>
                    <input type="password" class="form-control" id="reg_password" name="password" required>
                </div>
                 <p><?= t('register_info') ?></p>
                <button type="submit" class="btn btn-primary"><?= t('register') ?></button>
                 <a href="?page=login&lang=<?= $current_lang ?>" class="btn btn-secondary"><?= t('login') ?></a>
            </form>
            <?php
            break;

        case 'dashboard':
             // Stats
             $stmt_donors = $db->query("SELECT COUNT(*) FROM donors");
             $total_donors = $stmt_donors->fetchColumn();
             $stmt_drives = $db->query("SELECT COUNT(*) FROM drives");
             $total_drives = $stmt_drives->fetchColumn();
             $stmt_requests = $db->query("SELECT COUNT(*) FROM requests WHERE status = 'pending'");
             $pending_requests = $stmt_requests->fetchColumn();
             $stmt_users = $db->query("SELECT COUNT(*) FROM users WHERE approved = 0 AND role = 'user'");
             $pending_users = $stmt_users->fetchColumn();

            // Recent Activity
             $latest_donors = $db->query("SELECT id, name, blood_group, city FROM donors ORDER BY registered_at DESC LIMIT 5")->fetchAll();
             $upcoming_drives = $db->query("SELECT id, title, drive_date, location FROM drives WHERE drive_date >= date('now') ORDER BY drive_date ASC LIMIT 5")->fetchAll();
             $recent_requests = $db->query("SELECT id, name, blood_group, city, status FROM requests ORDER BY requested_at DESC LIMIT 5")->fetchAll();
            ?>
            <h2><?= t('dashboard') ?></h2>
            <?php if (is_logged_in() && $_SESSION['approved'] == 0 && get_current_user_role() != 'admin'): ?>
                 <div class="alert alert-warning"><?= t('not_approved') ?> <?= t('Please contact admin or ulama.') ?></div>
            <?php endif; ?>
            <div class="row">
                 <div class="col-md-3">
                     <div class="card text-white bg-primary">
                         <div class="card-body">
                             <h5 class="card-title en-font"><?= $total_donors ?></h5>
                             <p class="card-text"><?= t('total_donors') ?></p>
                         </div>
                     </div>
                 </div>
                 <div class="col-md-3">
                     <div class="card text-white bg-success">
                         <div class="card-body">
                             <h5 class="card-title en-font"><?= $total_drives ?></h5>
                             <p class="card-text"><?= t('total_drives') ?></p>
                         </div>
                     </div>
                 </div>
                 <div class="col-md-3">
                     <div class="card text-white bg-warning">
                         <div class="card-body">
                             <h5 class="card-title en-font"><?= $pending_requests ?></h5>
                             <p class="card-text"><?= t('pending_requests') ?></p>
                         </div>
                     </div>
                 </div>
                  <?php if (check_permission(['ulama', 'admin'])): ?>
                 <div class="col-md-3">
                     <div class="card text-white bg-info">
                         <div class="card-body">
                             <h5 class="card-title en-font"><?= $pending_users ?></h5>
                             <p class="card-text"><?= t('pending_users') ?></p>
                         </div>
                     </div>
                 </div>
                 <?php endif; ?>
            </div>

            <h4><?= t('recent_activity') ?></h4>
            <div class="row">
                <div class="col-md-4">
                    <h5><?= t('latest_donors') ?></h5>
                    <?php if ($latest_donors): ?>
                    <ul class="list-group">
                        <?php foreach ($latest_donors as $donor): ?>
                            <li class="list-group-item"><?= sanitize($donor['name']) ?> (<?= sanitize($donor['blood_group']) ?>) - <?= sanitize($donor['city']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: echo "<p>".t('no_records')."</p>"; endif; ?>
                </div>
                <div class="col-md-4">
                    <h5><?= t('upcoming_drives') ?></h5>
                     <?php if ($upcoming_drives): ?>
                    <ul class="list-group">
                        <?php foreach ($upcoming_drives as $drive): ?>
                            <li class="list-group-item"><?= sanitize($drive['title']) ?> - <?= date('d M Y', strtotime($drive['drive_date'])) ?> (<?= sanitize($drive['location']) ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                     <?php else: echo "<p>".t('no_records')."</p>"; endif; ?>
                </div>
                <div class="col-md-4">
                    <h5><?= t('recent_requests') ?></h5>
                     <?php if ($recent_requests): ?>
                    <ul class="list-group">
                        <?php foreach ($recent_requests as $request): ?>
                            <li class="list-group-item"><?= sanitize($request['name']) ?> (<?= sanitize($request['blood_group']) ?>) - <?= sanitize($request['city']) ?> [<?= t($request['status']) ?>]</li>
                        <?php endforeach; ?>
                    </ul>
                     <?php else: echo "<p>".t('no_records')."</p>"; endif; ?>
                </div>
            </div>
            <?php
            break;

         case 'donors':
             $search_term = sanitize($_GET['q'] ?? '');
             $search_bg = sanitize($_GET['bg'] ?? '');
             $search_city = sanitize($_GET['city'] ?? '');

             $sql = "SELECT d.*, u.username FROM donors d LEFT JOIN users u ON d.user_id = u.id WHERE 1=1";
             $params = [];
             if ($search_term) { $sql .= " AND (d.name LIKE ? OR d.contact LIKE ?)"; $params[] = "%$search_term%"; $params[] = "%$search_term%"; }
             if ($search_bg) { $sql .= " AND d.blood_group = ?"; $params[] = $search_bg; }
             if ($search_city) { $sql .= " AND d.city LIKE ?"; $params[] = "%$search_city%"; }
             $sql .= " ORDER BY d.registered_at DESC";

             $stmt = $db->prepare($sql);
             $stmt->execute($params);
             $donors = $stmt->fetchAll();
            ?>
            <h2><?= t('donors') ?></h2>
             <form method="get" action="" class="row g-3 mb-3">
                 <input type="hidden" name="page" value="donors">
                 <input type="hidden" name="lang" value="<?= $current_lang ?>">
                 <div class="col-md-3">
                     <input type="text" name="q" class="form-control" placeholder="<?= t('search') ?> <?= t('name') ?>/<?= t('contact') ?>" value="<?= $search_term ?>">
                 </div>
                 <div class="col-md-3">
                      <select name="bg" class="form-select">
                         <option value=""><?= t('blood_group') ?> (All)</option>
                          <?php foreach($blood_groups as $bg): ?>
                          <option value="<?= $bg ?>" <?= ($search_bg == $bg) ? 'selected' : '' ?>><?= $bg ?></option>
                          <?php endforeach; ?>
                      </select>
                 </div>
                 <div class="col-md-3">
                     <input type="text" name="city" class="form-control" placeholder="<?= t('city') ?>" value="<?= $search_city ?>">
                 </div>
                 <div class="col-md-3">
                     <button type="submit" class="btn btn-primary"><?= t('search') ?></button>
                      <?php if (is_logged_in() && check_permission('user')):
                          $stmt_check_profile = $db->prepare("SELECT id FROM donors WHERE user_id = ?");
                          $stmt_check_profile->execute([get_current_user_id()]);
                          if(!$stmt_check_profile->fetch()): ?>
                          <a href="?page=add_donor&lang=<?= $current_lang ?>" class="btn btn-success"><?= t('register_donor') ?></a>
                          <?php else: ?>
                          <a href="?page=profile&lang=<?= $current_lang ?>" class="btn btn-info"><?= t('my_profile') ?></a>
                          <?php endif; ?>
                      <?php elseif (!is_logged_in()): ?>
                          <a href="?page=add_donor&lang=<?= $current_lang ?>" class="btn btn-success"><?= t('register_donor') ?></a>
                      <?php elseif (check_permission(['ulama', 'admin'])): ?>
                           <a href="?page=add_donor&lang=<?= $current_lang ?>" class="btn btn-success"><?= t('add_new') ?></a>
                      <?php endif; ?>
                 </div>
             </form>

             <div class="table-responsive">
             <table class="table table-striped table-bordered">
                 <thead>
                     <tr>
                         <th><?= t('name') ?></th>
                         <th><?= t('age') ?></th>
                         <th><?= t('gender') ?></th>
                         <th><?= t('blood_group') ?></th>
                         <th><?= t('contact') ?></th>
                         <th><?= t('city') ?></th>
                         <th><?= t('last_donation') ?></th>
                         <th><?= t('role') ?></th>
                         <?php if (check_permission(['admin'])): ?><th><?= t('actions') ?></th><?php endif; ?>
                     </tr>
                 </thead>
                 <tbody>
                 <?php if ($donors): ?>
                     <?php foreach ($donors as $donor): ?>
                     <tr>
                         <td><?= sanitize($donor['name']) ?> <?= $donor['is_recipient'] ? '<span class="badge bg-info">'.t('recipient').'</span>' : '' ?></td>
                         <td><?= sanitize($donor['age']) ?></td>
                         <td><?= t(strtolower($donor['gender'] ?? '')) ?: sanitize($donor['gender']) ?></td>
                         <td><?= sanitize($donor['blood_group']) ?></td>
                         <td><?= sanitize($donor['contact']) ?></td>
                         <td><?= sanitize($donor['city']) ?></td>
                         <td><?= $donor['last_donation_date'] ? date('d M Y', strtotime($donor['last_donation_date'])) : '-' ?></td>
                         <td><?= $donor['user_id'] ? t('user') : t('public') ?></td>
                         <?php if (check_permission(['admin'])): ?>
                         <td>
                              <a href="?page=edit_donor&id=<?= $donor['id'] ?>&lang=<?= $current_lang ?>" class="btn btn-sm btn-warning"><?= t('edit') ?></a>
                              <form method="post" action="" style="display:inline;" onsubmit="return confirm('<?= t('confirm_delete') ?>');">
                                 <input type="hidden" name="action" value="delete_donor">
                                 <input type="hidden" name="id" value="<?= $donor['id'] ?>">
                                 <button type="submit" class="btn btn-sm btn-danger"><?= t('delete') ?></button>
                              </form>
                         </td>
                         <?php endif; ?>
                     </tr>
                     <?php endforeach; ?>
                 <?php else: ?>
                     <tr><td colspan="<?= check_permission(['admin']) ? 9 : 8 ?>" class="text-center"><?= t('no_records') ?></td></tr>
                 <?php endif; ?>
                 </tbody>
             </table>
             </div>
            <?php
             break;

        case 'add_donor':
        case 'edit_donor':
        case 'profile': // User's own profile editing
             $is_editing = ($page === 'edit_donor' || $page === 'profile');
             $donor_data = null;
             $donor_id = null;

             if ($page === 'profile') {
                 check_auth('user');
                 $user_id = get_current_user_id();
                 $stmt = $db->prepare("SELECT * FROM donors WHERE user_id = ?");
                 $stmt->execute([$user_id]);
                 $donor_data = $stmt->fetch();
                 if (!$donor_data) {
                      // If user has no profile yet, redirect to add donor page
                      redirect('add_donor');
                 }
                 $donor_id = $donor_data['id'];
                 $page_title = t('update_profile');
             } elseif ($page === 'edit_donor') {
                 check_auth('admin'); // Only admin can edit arbitrary donor profiles via this page
                 $donor_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                 if (!$donor_id) redirect('donors');
                 $stmt = $db->prepare("SELECT * FROM donors WHERE id = ?");
                 $stmt->execute([$donor_id]);
                 $donor_data = $stmt->fetch();
                  if (!$donor_data) redirect('donors');
                 $page_title = t('edit') . " " . t('donors');
             } else { // add_donor
                 // No check_auth here initially, public can access form but submission logic checks if logged in.
                 $page_title = t('register_donor');
             }

             $form_action = $is_editing ? 'update_donor' : 'add_donor';
            ?>
             <h2><?= $page_title ?></h2>
             <?php if ($page === 'add_donor' && !is_logged_in()): ?>
                <div class="alert alert-info"><?= t('If you are a registered user, please login first to link this profile to your account.') ?></div>
             <?php endif; ?>
            <form method="post" action="">
                <input type="hidden" name="action" value="<?= $form_action ?>">
                 <?php if ($is_editing && $donor_id): ?>
                 <input type="hidden" name="donor_id" value="<?= $donor_id ?>">
                 <?php endif; ?>

                 <div class="row">
                     <div class="col-md-6 form-group">
                         <label for="name" class="form-label"><?= t('name') ?> <span class="text-danger">*</span></label>
                         <input type="text" class="form-control" id="name" name="name" value="<?= sanitize($donor_data['name'] ?? '') ?>" required>
                     </div>
                      <div class="col-md-6 form-group">
                         <label for="age" class="form-label"><?= t('age') ?></label>
                         <input type="number" class="form-control" id="age" name="age" value="<?= sanitize($donor_data['age'] ?? '') ?>">
                     </div>
                 </div>
                 <div class="row">
                    <div class="col-md-6 form-group">
                         <label for="gender" class="form-label"><?= t('gender') ?></label>
                         <select class="form-select" id="gender" name="gender">
                             <option value=""><?= t('select') ?>...</option>
                             <option value="Male" <?= ($donor_data['gender'] ?? '') == 'Male' ? 'selected' : '' ?>><?= t('male') ?></option>
                             <option value="Female" <?= ($donor_data['gender'] ?? '') == 'Female' ? 'selected' : '' ?>><?= t('female') ?></option>
                             <option value="Other" <?= ($donor_data['gender'] ?? '') == 'Other' ? 'selected' : '' ?>><?= t('other') ?></option>
                         </select>
                     </div>
                     <div class="col-md-6 form-group">
                         <label for="blood_group" class="form-label"><?= t('blood_group') ?> <span class="text-danger">*</span></label>
                         <select class="form-select" id="blood_group" name="blood_group" required>
                              <option value=""><?= t('select') ?>...</option>
                             <?php foreach($blood_groups as $bg): ?>
                             <option value="<?= $bg ?>" <?= ($donor_data['blood_group'] ?? '') == $bg ? 'selected' : '' ?>><?= $bg ?></option>
                             <?php endforeach; ?>
                         </select>
                     </div>
                 </div>
                  <div class="row">
                     <div class="col-md-6 form-group">
                         <label for="contact" class="form-label"><?= t('contact') ?> <span class="text-danger">*</span></label>
                         <input type="text" class="form-control" id="contact" name="contact" value="<?= sanitize($donor_data['contact'] ?? '') ?>" required>
                     </div>
                      <div class="col-md-6 form-group">
                         <label for="city" class="form-label"><?= t('city') ?></label>
                         <input type="text" class="form-control" id="city" name="city" value="<?= sanitize($donor_data['city'] ?? '') ?>">
                     </div>
                 </div>
                 <div class="row">
                      <div class="col-md-6 form-group">
                         <label for="last_donation_date" class="form-label"><?= t('last_donation') ?></label>
                         <input type="date" class="form-control" id="last_donation_date" name="last_donation_date" value="<?= sanitize($donor_data['last_donation_date'] ?? '') ?>">
                     </div>
                     <div class="col-md-6 form-group form-check form-switch pt-4">
                         <input class="form-check-input" type="checkbox" role="switch" id="is_recipient" name="is_recipient" value="1" <?= ($donor_data['is_recipient'] ?? 0) == 1 ? 'checked' : '' ?>>
                         <label class="form-check-label" for="is_recipient"><?= t('is_recipient') ?></label>
                     </div>
                 </div>

                <button type="submit" class="btn btn-primary"><?= $is_editing ? t('update') : t('submit') ?></button>
                <a href="?page=<?= ($page=='profile')?'dashboard':'donors' ?>&lang=<?= $current_lang ?>" class="btn btn-secondary"><?= t('cancel') ?></a>
            </form>
            <?php
            break;

        case 'drives':
            $can_manage = check_permission(['ulama', 'admin']);
             $sql = "SELECT d.*, u.username as creator_username FROM drives d LEFT JOIN users u ON d.created_by = u.id ORDER BY d.drive_date DESC";
             $stmt = $db->query($sql);
             $drives = $stmt->fetchAll();
            ?>
            <h2><?= t('drives') ?></h2>
             <?php if ($can_manage): ?>
             <a href="?page=add_drive&lang=<?= $current_lang ?>" class="btn btn-success mb-3"><?= t('add_drive') ?></a>
             <?php endif; ?>
             <div class="row">
                 <?php if ($drives): ?>
                     <?php foreach ($drives as $drive): ?>
                     <div class="col-md-6 col-lg-4">
                         <div class="card">
                             <div class="card-body">
                                 <h5 class="card-title"><?= sanitize($drive['title']) ?></h5>
                                 <h6 class="card-subtitle mb-2 text-muted"><?= date('D, d M Y, h:i A', strtotime($drive['drive_date'])) ?></h6>
                                 <p class="card-text"><strong><?= t('location') ?>:</strong> <?= sanitize($drive['location']) ?><br>
                                     <?php if($drive['organizer']): ?><strong><?= t('organizer') ?>:</strong> <?= sanitize($drive['organizer']) ?><br><?php endif; ?>
                                     <?php if($drive['description']): ?><small><?= nl2br(sanitize($drive['description'])) ?></small><br><?php endif; ?>
                                     <?php if($drive['creator_username']): ?><small><em><?= t('created_by') ?>: <?= sanitize($drive['creator_username']) ?></em></small><?php endif; ?>
                                 </p>
                                  <?php if ($can_manage): ?>
                                      <a href="?page=edit_drive&id=<?= $drive['id'] ?>&lang=<?= $current_lang ?>" class="btn btn-sm btn-warning"><?= t('edit') ?></a>
                                      <form method="post" action="" style="display:inline;" onsubmit="return confirm('<?= t('confirm_delete') ?>');">
                                         <input type="hidden" name="action" value="delete_drive">
                                         <input type="hidden" name="id" value="<?= $drive['id'] ?>">
                                         <button type="submit" class="btn btn-sm btn-danger"><?= t('delete') ?></button>
                                      </form>
                                 <?php endif; ?>
                             </div>
                         </div>
                     </div>
                     <?php endforeach; ?>
                 <?php else: ?>
                     <p><?= t('no_records') ?></p>
                 <?php endif; ?>
             </div>
            <?php
            break;

         case 'add_drive':
         case 'edit_drive':
             check_auth(['ulama', 'admin']);
             $is_editing = ($page === 'edit_drive');
             $drive_data = null;
             $drive_id = null;

             if ($is_editing) {
                  $drive_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                  if (!$drive_id) redirect('drives');
                  $stmt = $db->prepare("SELECT * FROM drives WHERE id = ?");
                  $stmt->execute([$drive_id]);
                  $drive_data = $stmt->fetch();
                   if (!$drive_data) redirect('drives');
                   $page_title = t('edit') . " " . t('drives');
             } else {
                   $page_title = t('add_drive');
             }
             $form_action = $is_editing ? 'update_drive' : 'add_drive';
            ?>
             <h2><?= $page_title ?></h2>
             <form method="post" action="">
                 <input type="hidden" name="action" value="<?= $form_action ?>">
                 <?php if ($is_editing): ?>
                 <input type="hidden" name="drive_id" value="<?= $drive_id ?>">
                 <?php endif; ?>
                 <div class="form-group mb-3">
                     <label for="title" class="form-label"><?= t('title') ?> <span class="text-danger">*</span></label>
                     <input type="text" class="form-control" id="title" name="title" value="<?= sanitize($drive_data['title'] ?? '') ?>" required>
                 </div>
                  <div class="form-group mb-3">
                     <label for="drive_date" class="form-label"><?= t('date') ?> & Time <span class="text-danger">*</span></label>
                     <input type="datetime-local" class="form-control" id="drive_date" name="drive_date" value="<?= $drive_data['drive_date'] ? date('Y-m-d\TH:i', strtotime($drive_data['drive_date'])) : '' ?>" required>
                 </div>
                  <div class="form-group mb-3">
                     <label for="location" class="form-label"><?= t('location') ?> <span class="text-danger">*</span></label>
                     <input type="text" class="form-control" id="location" name="location" value="<?= sanitize($drive_data['location'] ?? '') ?>" required>
                 </div>
                 <div class="form-group mb-3">
                     <label for="organizer" class="form-label"><?= t('organizer') ?></label>
                     <input type="text" class="form-control" id="organizer" name="organizer" value="<?= sanitize($drive_data['organizer'] ?? '') ?>">
                 </div>
                 <div class="form-group mb-3">
                     <label for="description" class="form-label"><?= t('description') ?></label>
                     <textarea class="form-control" id="description" name="description" rows="3"><?= sanitize($drive_data['description'] ?? '') ?></textarea>
                 </div>
                 <button type="submit" class="btn btn-primary"><?= $is_editing ? t('update') : t('submit') ?></button>
                 <a href="?page=drives&lang=<?= $current_lang ?>" class="btn btn-secondary"><?= t('cancel') ?></a>
             </form>
            <?php
            break;

        case 'requests':
             $can_manage = check_permission(['ulama', 'admin']);
             $can_respond = check_permission(['user', 'ulama', 'admin']);
             $is_user = check_permission('user');
             $user_blood_group = null;

              if ($is_user) {
                  // Fetch user's blood group if they have a donor profile
                  $stmt_bg = $db->prepare("SELECT blood_group FROM donors WHERE user_id = ?");
                  $stmt_bg->execute([get_current_user_id()]);
                  $user_blood_group = $stmt_bg->fetchColumn();
              }

             $filter_status = sanitize($_GET['status'] ?? 'pending');
             $filter_blood_group = sanitize($_GET['bg'] ?? ($is_user ? $user_blood_group : '')); // Default to user's BG if user

             $sql = "SELECT * FROM requests WHERE 1=1";
             $params = [];
             if ($filter_status && $filter_status !== 'all') {
                 $sql .= " AND status = ?";
                 $params[] = $filter_status;
             }
              if ($filter_blood_group) {
                  $sql .= " AND blood_group = ?";
                  $params[] = $filter_blood_group;
              }
             $sql .= " ORDER BY requested_at DESC";

             $stmt = $db->prepare($sql);
             $stmt->execute($params);
             $requests = $stmt->fetchAll();
            ?>
            <h2><?= t('requests') ?></h2>
             <div class="mb-3 d-flex justify-content-between align-items-center">
                  <form method="get" action="" class="row g-2 align-items-center">
                       <input type="hidden" name="page" value="requests">
                       <input type="hidden" name="lang" value="<?= $current_lang ?>">
                       <div class="col-auto">
                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                 <option value="pending" <?= ($filter_status == 'pending') ? 'selected' : '' ?>><?= t('pending') ?></option>
                                 <option value="fulfilled" <?= ($filter_status == 'fulfilled') ? 'selected' : '' ?>><?= t('fulfilled') ?></option>
                                 <option value="cancelled" <?= ($filter_status == 'cancelled') ? 'selected' : '' ?>><?= t('cancelled') ?></option>
                                 <option value="all" <?= ($filter_status == 'all') ? 'selected' : '' ?>><?= t('all') ?></option>
                            </select>
                       </div>
                       <div class="col-auto">
                            <select name="bg" class="form-select form-select-sm" onchange="this.form.submit()">
                                 <option value=""><?= t('blood_group') ?> (All)</option>
                                  <?php foreach($blood_groups as $bg): ?>
                                  <option value="<?= $bg ?>" <?= ($filter_blood_group == $bg) ? 'selected' : '' ?>><?= $bg ?></option>
                                  <?php endforeach; ?>
                            </select>
                       </div>
                       <div class="col-auto">
                           <button type="submit" class="btn btn-primary btn-sm"><?= t('filter') ?></button>
                       </div>
                  </form>
                  <div>
                     <?php if (!is_logged_in() || check_permission('user')): // Allow public and users to submit ?>
                     <a href="?page=submit_request&lang=<?= $current_lang ?>" class="btn btn-success"><?= t('submit_request') ?></a>
                     <?php endif; ?>
                     <?php if ($is_user && $user_blood_group): ?>
                     <a href="?page=requests&lang=<?= $current_lang ?>&bg=<?= urlencode($user_blood_group) ?>&status=pending" class="btn btn-info"><?= t('view_matching_requests') ?> (<?= $user_blood_group ?>)</a>
                     <?php endif; ?>
                  </div>
             </div>

             <div class="table-responsive">
             <table class="table table-striped table-bordered">
                 <thead>
                     <tr>
                         <th><?= t('name') ?></th>
                         <th><?= t('blood_group') ?></th>
                         <th><?= t('city') ?></th>
                         <th><?= t('contact') ?></th>
                         <th><?= t('details') ?></th>
                         <th><?= t('date') ?></th>
                         <th><?= t('status') ?></th>
                         <?php if ($can_respond || $can_manage): ?><th><?= t('actions') ?></th><?php endif; ?>
                     </tr>
                 </thead>
                 <tbody>
                 <?php if ($requests): ?>
                     <?php foreach ($requests as $request): ?>
                     <?php
                         // Users should only see matching blood group requests by default unless they clear the filter
                          $show_request = true;
                          if ($is_user && $user_blood_group && !$filter_blood_group && $request['blood_group'] !== $user_blood_group) {
                             // If user and no BG filter applied, hide non-matching requests (unless they explicitly choose 'All' or a different group)
                             // $show_request = false; // Decided to show all by default, user can filter
                          }
                          if (!$show_request) continue;
                     ?>
                     <tr>
                         <td><?= sanitize($request['name']) ?></td>
                         <td><?= sanitize($request['blood_group']) ?></td>
                         <td><?= sanitize($request['city']) ?></td>
                         <td><?= sanitize($request['contact']) ?></td>
                         <td><?= nl2br(sanitize($request['details'])) ?></td>
                         <td><?= date('d M Y, h:i A', strtotime($request['requested_at'])) ?></td>
                         <td><?= t($request['status']) ?></td>
                         <?php if ($can_respond || $can_manage): ?>
                         <td>
                              <?php if ($can_respond && $request['status'] == 'pending'): ?>
                                 <form method="post" action="" style="display:inline;" class="me-1">
                                      <input type="hidden" name="action" value="update_request_status">
                                      <input type="hidden" name="id" value="<?= $request['id'] ?>">
                                      <input type="hidden" name="status" value="fulfilled">
                                      <button type="submit" class="btn btn-sm btn-success"><?= t('fulfill') ?></button>
                                 </form>
                                 <form method="post" action="" style="display:inline;" class="me-1">
                                      <input type="hidden" name="action" value="update_request_status">
                                      <input type="hidden" name="id" value="<?= $request['id'] ?>">
                                      <input type="hidden" name="status" value="cancelled">
                                      <button type="submit" class="btn btn-sm btn-warning"><?= t('cancel') ?></button>
                                 </form>
                               <?php endif; ?>
                               <?php if ($can_manage): ?>
                                 <form method="post" action="" style="display:inline;" onsubmit="return confirm('<?= t('confirm_delete') ?>');">
                                     <input type="hidden" name="action" value="delete_request">
                                     <input type="hidden" name="id" value="<?= $request['id'] ?>">
                                     <button type="submit" class="btn btn-sm btn-danger"><?= t('delete') ?></button>
                                 </form>
                              <?php endif; ?>
                         </td>
                          <?php endif; ?>
                     </tr>
                     <?php endforeach; ?>
                 <?php else: ?>
                     <tr><td colspan="<?= ($can_respond || $can_manage) ? 8 : 7 ?>" class="text-center"><?= t('no_records') ?></td></tr>
                 <?php endif; ?>
                 </tbody>
             </table>
             </div>
            <?php
            break;

        case 'submit_request':
             ?>
             <h2><?= t('submit_request') ?></h2>
             <p><?= t('request_info') ?></p>
             <form method="post" action="">
                 <input type="hidden" name="action" value="submit_request">
                  <div class="row">
                     <div class="col-md-6 form-group">
                         <label for="req_name" class="form-label"><?= t('name') ?> <span class="text-danger">*</span></label>
                         <input type="text" class="form-control" id="req_name" name="name" required>
                     </div>
                     <div class="col-md-6 form-group">
                         <label for="req_blood_group" class="form-label"><?= t('blood_group') ?> <span class="text-danger">*</span></label>
                         <select class="form-select" id="req_blood_group" name="blood_group" required>
                              <option value=""><?= t('select') ?>...</option>
                             <?php foreach($blood_groups as $bg): ?>
                             <option value="<?= $bg ?>"><?= $bg ?></option>
                             <?php endforeach; ?>
                         </select>
                     </div>
                 </div>
                 <div class="row">
                     <div class="col-md-6 form-group">
                         <label for="req_city" class="form-label"><?= t('city') ?> <span class="text-danger">*</span></label>
                         <input type="text" class="form-control" id="req_city" name="city" required>
                     </div>
                     <div class="col-md-6 form-group">
                          <label for="req_contact" class="form-label"><?= t('contact') ?> <span class="text-danger">*</span></label>
                          <input type="text" class="form-control" id="req_contact" name="contact" required>
                     </div>
                 </div>
                 <div class="form-group mb-3">
                     <label for="details" class="form-label"><?= t('details') ?> (e.g., Hospital, Urgency)</label>
                     <textarea class="form-control" id="details" name="details" rows="3"></textarea>
                 </div>
                 <button type="submit" class="btn btn-primary"><?= t('submit') ?></button>
                 <a href="?page=requests&lang=<?= $current_lang ?>" class="btn btn-secondary"><?= t('cancel') ?></a>
             </form>
            <?php
            break;

        case 'users':
             check_auth(['ulama', 'admin']);
             $can_manage = check_permission('admin');
             $view = sanitize($_GET['view'] ?? 'all'); // 'all' or 'pending'

             $sql = "SELECT id, username, role, approved, created_at FROM users WHERE 1=1";
             $params = [];
             if ($view === 'pending') {
                  $sql .= " AND approved = 0 AND role = 'user'";
                  $page_title = t('approve_users');
             } else {
                  check_auth('admin'); // Only admin can see all users
                  $page_title = t('manage_users');
             }
             $sql .= " ORDER BY created_at DESC";

             $stmt = $db->prepare($sql);
             $stmt->execute($params);
             $users = $stmt->fetchAll();
            ?>
            <h2><?= $page_title ?></h2>
             <?php if ($can_manage): ?>
             <div class="mb-2">
                 <a href="?page=users&view=all&lang=<?= $current_lang ?>" class="btn btn-sm <?= ($view=='all')?'btn-primary':'btn-secondary' ?>"><?= t('all') ?> <?= t('users') ?></a>
                 <a href="?page=users&view=pending&lang=<?= $current_lang ?>" class="btn btn-sm <?= ($view=='pending')?'btn-primary':'btn-secondary' ?>"><?= t('pending_users') ?></a>
             </div>
             <?php endif; ?>

             <div class="table-responsive">
             <table class="table table-striped table-bordered">
                 <thead>
                     <tr>
                         <th><?= t('username') ?></th>
                         <th><?= t('role') ?></th>
                         <th><?= t('status') ?></th>
                         <th><?= t('created_at') ?></th>
                         <th><?= t('actions') ?></th>
                     </tr>
                 </thead>
                 <tbody>
                 <?php if ($users): ?>
                     <?php foreach ($users as $user): ?>
                     <tr>
                         <td><?= sanitize($user['username']) ?></td>
                         <td>
                              <?php if ($can_manage && $user['role'] !== 'admin'): ?>
                                <form method="post" action="" style="display:inline;">
                                     <input type="hidden" name="action" value="update_user">
                                     <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                     <select name="role" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                         <option value="user" <?= ($user['role'] == 'user') ? 'selected' : '' ?>><?= t('user') ?></option>
                                         <option value="ulama" <?= ($user['role'] == 'ulama') ? 'selected' : '' ?>><?= t('ulama') ?></option>
                                          <?php /* <option value="admin" <?= ($user['role'] == 'admin') ? 'selected' : '' ?>><?= t('admin') ?></option> */ ?>
                                     </select>
                                     <input type="checkbox" name="approved" value="1" <?= ($user['approved'] == 1) ? 'checked' : '' ?> onchange="this.form.submit()" class="form-check-input ms-2"> <?= t('approved') ?>
                                 </form>
                              <?php else: ?>
                                 <?= t($user['role']) ?>
                              <?php endif; ?>
                         </td>
                         <td><?= $user['approved'] ? '<span class="badge bg-success">'.t('approved').'</span>' : '<span class="badge bg-warning">'.t('pending').'</span>' ?></td>
                         <td><?= date('d M Y, h:i A', strtotime($user['created_at'])) ?></td>
                         <td>
                              <?php if (!$user['approved'] && check_permission(['ulama', 'admin'])): ?>
                                <form method="post" action="" style="display:inline;" class="me-1">
                                     <input type="hidden" name="action" value="approve_user">
                                     <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                     <button type="submit" class="btn btn-sm btn-success"><?= t('approve') ?></button>
                                </form>
                              <?php endif; ?>
                               <?php if ($can_manage && $_SESSION['user_id'] != $user['id']): // Admin can delete anyone except themselves ?>
                                 <form method="post" action="" style="display:inline;" onsubmit="return confirm('<?= t('confirm_delete') ?>');">
                                     <input type="hidden" name="action" value="delete_user">
                                     <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                     <button type="submit" class="btn btn-sm btn-danger"><?= t('delete') ?></button>
                                 </form>
                              <?php endif; ?>
                         </td>
                     </tr>
                     <?php endforeach; ?>
                 <?php else: ?>
                     <tr><td colspan="5" class="text-center"><?= t('no_records') ?></td></tr>
                 <?php endif; ?>
                 </tbody>
             </table>
             </div>
            <?php
            break;

        case 'notes':
             check_auth(['ulama', 'admin']);
             $edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
             $note_to_edit = null;
             if ($edit_id) {
                 $stmt = $db->prepare("SELECT * FROM notes WHERE id = ?");
                 $stmt->execute([$edit_id]);
                 $note_to_edit = $stmt->fetch();
                  // Security check: only owner or admin can edit
                 if ($note_to_edit && !check_permission('admin') && $note_to_edit['created_by'] != get_current_user_id()) {
                     $note_to_edit = null; // Deny access
                      $message = ['type' => 'danger', 'text' => t('access_denied')];
                 }
             }

             $sql = "SELECT n.*, u.username as creator_username FROM notes n JOIN users u ON n.created_by = u.id ORDER BY n.created_at DESC";
             $stmt = $db->query($sql);
             $notes = $stmt->fetchAll();
            ?>
            <h2><?= t('notes') ?></h2>
             <div class="card mb-4">
                 <div class="card-header"><?= $note_to_edit ? t('edit') : t('post_note') ?></div>
                 <div class="card-body">
                      <form method="post" action="">
                         <input type="hidden" name="action" value="<?= $note_to_edit ? 'update_note' : 'add_note' ?>">
                         <?php if ($note_to_edit): ?>
                         <input type="hidden" name="note_id" value="<?= $note_to_edit['id'] ?>">
                         <?php endif; ?>
                         <div class="form-group mb-2">
                             <label for="note_title" class="form-label"><?= t('title') ?> <span class="text-danger">*</span></label>
                             <input type="text" class="form-control" id="note_title" name="title" value="<?= sanitize($note_to_edit['title'] ?? '') ?>" required>
                         </div>
                         <div class="form-group mb-2">
                             <label for="note_content" class="form-label"><?= t('content') ?> <span class="text-danger">*</span></label>
                             <textarea class="form-control" id="note_content" name="content" rows="4" required><?= sanitize($note_to_edit['content'] ?? '') ?></textarea>
                         </div>
                         <button type="submit" class="btn btn-primary"><?= $note_to_edit ? t('update') : t('submit') ?></button>
                          <?php if ($note_to_edit): ?>
                             <a href="?page=notes&lang=<?= $current_lang ?>" class="btn btn-secondary"><?= t('cancel') ?></a>
                          <?php endif; ?>
                     </form>
                 </div>
             </div>

             <h4><?= t('manage_notes') ?></h4>
              <div class="list-group">
                 <?php if ($notes): ?>
                     <?php foreach ($notes as $note): ?>
                      <div class="list-group-item list-group-item-action flex-column align-items-start">
                         <div class="d-flex w-100 justify-content-between">
                           <h5 class="mb-1"><?= sanitize($note['title']) ?></h5>
                           <small><?= date('d M Y, h:i A', strtotime($note['created_at'])) ?></small>
                         </div>
                         <p class="mb-1"><?= nl2br(sanitize($note['content'])) ?></p>
                         <small><em><?= t('created_by') ?>: <?= sanitize($note['creator_username']) ?></em></small>
                         <div class="mt-2">
                              <?php if (check_permission('admin') || $note['created_by'] == get_current_user_id()): ?>
                              <a href="?page=notes&edit=<?= $note['id'] ?>&lang=<?= $current_lang ?>" class="btn btn-sm btn-warning"><?= t('edit') ?></a>
                               <form method="post" action="" style="display:inline;" onsubmit="return confirm('<?= t('confirm_delete') ?>');">
                                 <input type="hidden" name="action" value="delete_note">
                                 <input type="hidden" name="id" value="<?= $note['id'] ?>">
                                 <button type="submit" class="btn btn-sm btn-danger"><?= t('delete') ?></button>
                              </form>
                              <?php endif; ?>
                         </div>
                       </div>
                     <?php endforeach; ?>
                 <?php else: ?>
                     <p><?= t('no_records') ?></p>
                 <?php endif; ?>
             </div>
            <?php
            break;

        case 'settings':
             check_auth('admin');
             $current_site_title = get_setting('site_title');
            ?>
            <h2><?= t('site_settings') ?></h2>
             <form method="post" action="">
                 <input type="hidden" name="action" value="update_settings">
                 <div class="form-group mb-3">
                     <label for="site_title" class="form-label"><?= t('site_title') ?></label>
                     <input type="text" class="form-control" id="site_title" name="site_title" value="<?= sanitize($current_site_title) ?>" required>
                 </div>
                 <button type="submit" class="btn btn-primary"><?= t('update') ?></button>
             </form>
            <?php
            break;

        default:
            redirect('dashboard'); // Redirect unknown pages to dashboard
            break;
    }
    ?>

</div>

<footer class="footer mt-auto py-3 bg-light">
    <div class="container text-center">
        <span class="text-muted">&copy; <?= date('Y') ?> <?= sanitize(t($site_title)) ?></span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
