<?php

/**
 * Poor People Walfare - Financial Management Module
 *
 * A single-file application for managing income, expenses, and generating financial reports.
 * This module is an extension of the LifeFlowConnect platform and uses the same database.
 * Access is restricted to administrators only.
 *
 * @author Yasin Ullah (Pakistani)
 * @version 1.0.0
 * @package LifeFlowConnectFinance
 */
session_start();
ob_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Karachi');

define('DB_FILE', __DIR__ . '/_data/lifeflow_connect.sqlite');
define('SITE_NAME', 'Poor People Walfare - Finance');
define('APP_VERSION', '1.0.0');
define('SESSION_LIFETIME', 7200);

function get_db()
{
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO('sqlite:' . DB_FILE);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $db->exec("PRAGMA foreign_keys = ON;");
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            http_response_code(500);
            die("Database connection failed. Ensure the main application is set up correctly.");
        }
    }
    return $db;
}

function init_finance_db()
{
    $db = get_db();
    $db->exec("
        CREATE TABLE IF NOT EXISTS income (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            source TEXT NOT NULL,
            amount REAL NOT NULL,
            income_date DATE NOT NULL,
            category TEXT,
            description TEXT,
            created_by INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        );
        CREATE TABLE IF NOT EXISTS expenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            payee TEXT NOT NULL,
            amount REAL NOT NULL,
            expense_date DATE NOT NULL,
            category TEXT,
            description TEXT,
            receipt_url TEXT,
            created_by INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        );
        CREATE INDEX IF NOT EXISTS idx_income_date ON income (income_date);
        CREATE INDEX IF NOT EXISTS idx_income_category ON income (category);
        CREATE INDEX IF NOT EXISTS idx_expenses_date ON expenses (expense_date);
        CREATE INDEX IF NOT EXISTS idx_expenses_category ON expenses (category);
    ");
}

function sanitize($data)
{
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

function validate_input($input, $type, $options = [])
{
    $input = trim($input);
    switch ($type) {
        case 'string':
            $min_len = $options['min_len'] ?? 1;
            $max_len = $options['max_len'] ?? 255;
            if (mb_strlen($input) < $min_len || mb_strlen($input) > $max_len) return false;
            return sanitize($input);
        case 'text':
            return sanitize($input);
        case 'date':
            $d = DateTime::createFromFormat('Y-m-d', $input);
            return $d && $d->format('Y-m-d') === $input ? $input : false;
        case 'amount':
            return filter_var($input, FILTER_VALIDATE_FLOAT);
        default:
            return false;
    }
}

function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token)
{
    if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        set_flash_message('danger', 'Invalid CSRF token. Please try again.');
        return false;
    }
    return true;
}

function redirect($page = 'summary', $params = [], $status_code = 303)
{
    $url = $_SERVER['PHP_SELF'];
    $params['page'] = $page;
    $url .= '?' . http_build_query($params);
    header("Location: " . $url, true, $status_code);
    exit;
}

function set_flash_message($type, $text)
{
    $_SESSION['flash_message'] = ['type' => $type, 'text' => $text];
}

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

function is_logged_in()
{
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        return false;
    }
    if (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

function get_current_user_id()
{
    return $_SESSION['user_id'] ?? null;
}

function check_auth($required_role)
{
    if (!is_logged_in() || ($_SESSION['role'] ?? '') !== $required_role) {
        set_flash_message('danger', 'Access Denied. You must be an administrator to view this page.');
        header('Location: index.php?page=login');
        exit;
    }
}

function render_icon($icon_name, $extra_classes = '')
{
    return "<i class='bi bi-{$icon_name} {$extra_classes}'></i>";
}

function handle_upload($file_input, $upload_dir, $allowed_extensions, $max_size = 2097152)
{
    if (!isset($file_input) || $file_input['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    $file_ext = strtolower(pathinfo($file_input['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_extensions)) {
        set_flash_message('danger', 'Invalid file type.');
        return false;
    }
    if ($file_input['size'] > $max_size) {
        set_flash_message('danger', 'File size exceeds 2MB limit.');
        return false;
    }
    $new_file_name = uniqid('receipt_', true) . '.' . $file_ext;
    $dest_path = $upload_dir . $new_file_name;
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    if (move_uploaded_file($file_input['tmp_name'], $dest_path)) {
        return $new_file_name;
    }
    set_flash_message('danger', 'Failed to upload receipt.');
    return false;
}

check_auth('admin');
init_finance_db();

$action = $_POST['action'] ?? null;
$page = sanitize($_GET['page'] ?? 'summary');
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect($page);
    }
    try {
        switch ($action) {
            case 'add_income':
                $source = validate_input($_POST['source'], 'string');
                $amount = validate_input($_POST['amount'], 'amount');
                $income_date = validate_input($_POST['income_date'], 'date');
                $category = validate_input($_POST['category'], 'string');
                $description = validate_input($_POST['description'], 'text');
                if (!$source || !$amount || !$income_date || !$category) {
                    set_flash_message('danger', 'Invalid or missing fields for income.');
                    break;
                }
                $stmt = $db->prepare("INSERT INTO income (source, amount, income_date, category, description, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$source, $amount, $income_date, $category, $description, get_current_user_id()]);
                set_flash_message('success', 'Income record added successfully.');
                redirect('income');
                break;

            case 'add_expense':
                $payee = validate_input($_POST['payee'], 'string');
                $amount = validate_input($_POST['amount'], 'amount');
                $expense_date = validate_input($_POST['expense_date'], 'date');
                $category = validate_input($_POST['category'], 'string');
                $description = validate_input($_POST['description'], 'text');
                $receipt_url = null;
                if (!$payee || !$amount || !$expense_date || !$category) {
                    set_flash_message('danger', 'Invalid or missing fields for expense.');
                    break;
                }
                if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/uploads/receipts/';
                    $receipt_file = handle_upload($_FILES['receipt'], $upload_dir, ['jpg', 'jpeg', 'png', 'pdf']);
                    if ($receipt_file) {
                        $receipt_url = 'uploads/receipts/' . $receipt_file;
                    } else {
                        break;
                    }
                }
                $stmt = $db->prepare("INSERT INTO expenses (payee, amount, expense_date, category, description, receipt_url, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$payee, $amount, $expense_date, $category, $description, $receipt_url, get_current_user_id()]);
                set_flash_message('success', 'Expense record added successfully.');
                redirect('expenses');
                break;
            case 'delete_transaction':
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $type = validate_input($_POST['type'], 'string');
                if (!$id || !in_array($type, ['income', 'expense'])) {
                    set_flash_message('danger', 'Invalid transaction details.');
                    redirect($page);
                }
                if ($type === 'expense') {
                    $stmt = $db->prepare("SELECT receipt_url FROM expenses WHERE id = ?");
                    $stmt->execute([$id]);
                    $receipt = $stmt->fetchColumn();
                    if ($receipt && file_exists(__DIR__ . '/' . $receipt)) {
                        unlink(__DIR__ . '/' . $receipt);
                    }
                }
                $table = ($type === 'income') ? 'income' : 'expenses';
                $stmt = $db->prepare("DELETE FROM $table WHERE id = ?");
                $stmt->execute([$id]);
                set_flash_message('success', 'Transaction deleted successfully.');
                redirect($page);
                break;
        }
    } catch (Exception $e) {
        error_log("Finance Action Error: " . $e->getMessage());
        set_flash_message('danger', 'An error occurred. Please check logs.');
    }
}
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize(ucfirst(str_replace('_', ' ', $page))) ?> | <?= SITE_NAME ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><text x='0' y='14' font-size='16'></text></svg>" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #198754;
            --secondary-color: #6c757d;
            --danger-color: #dc3545;
        }

        body {
            background-color: #f8f9fa;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 280px;
            padding: 56px 0 0;
            background-color: #fff;
            box-shadow: 0 2px 5px 0 rgba(0, 0, 0, .05);
        }

        .main-content {
            padding-left: 280px;
        }

        .sidebar .nav-link {
            color: #333;
            font-weight: 500;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: var(--primary-color);
            background-color: rgba(25, 135, 84, 0.1);
        }

        .sidebar .nav-link .bi {
            margin-right: .8rem;
            font-size: 1.2rem;
        }

        .card {
            border: none;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.05);
        }

        .stat-card-income {
            border-left: 5px solid var(--primary-color);
        }

        .stat-card-expense {
            border-left: 5px solid var(--danger-color);
        }

        .stat-card-net {
            border-left: 5px solid var(--secondary-color);
        }

        @media (max-width: 991.98px) {
            .sidebar {
                left: -280px;
                z-index: 1040;
                transition: all 0.3s;
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                padding-left: 0;
            }
        }
    </style>
</head>

<body>
    <header class="navbar navbar-expand-lg navbar-light bg-light fixed-top p-0">
        <div class="container-fluid">
            <button class="navbar-toggler d-lg-none" type="button" id="sidebarToggle">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand fw-bold text-success ms-3" href="?page=summary"><?= render_icon('cash-coin') ?> <?= SITE_NAME ?></a>
            <div class="ms-auto d-flex align-items-center">
                <a href="index.php" class="btn btn-sm btn-outline-secondary me-2"><?= render_icon('arrow-left-circle') ?> Back to Main App</a>
                <div class="dropdown">
                    <a href="#" class="nav-link dropdown-toggle text-secondary" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= render_icon('person-circle', 'fs-4') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><span class="dropdown-item-text">Signed in as <strong><?= sanitize($_SESSION['username']) ?></strong></span></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <nav id="sidebarMenu" class="sidebar">
        <div class="position-sticky pt-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="?page=summary" class="nav-link <?= $page == 'summary' ? 'active' : '' ?>"><?= render_icon('clipboard-data') ?> Summary</a>
                </li>
                <li class="nav-item">
                    <a href="?page=income" class="nav-link <?= $page == 'income' ? 'active' : '' ?>"><?= render_icon('box-arrow-in-down') ?> Income</a>
                </li>
                <li class="nav-item">
                    <a href="?page=expenses" class="nav-link <?= $page == 'expenses' ? 'active' : '' ?>"><?= render_icon('box-arrow-up') ?> Expenses</a>
                </li>
                <li class="nav-item">
                    <a href="?page=reports" class="nav-link <?= $page == 'reports' ? 'active' : '' ?>"><?= render_icon('file-earmark-bar-graph') ?> Reports</a>
                </li>
            </ul>
        </div>
    </nav>

    <main class="main-content" style="padding-top: 58px;">
        <div class="container-fluid pt-4">
            <?php display_flash_message(); ?>
            <?php
            switch ($page) {
                case 'summary':
                    $stats = $db->query("
                        SELECT
                            (SELECT IFNULL(SUM(amount), 0) FROM income) as total_income,
                            (SELECT IFNULL(SUM(amount), 0) FROM expenses) as total_expenses
                    ")->fetch();
                    $net_balance = $stats['total_income'] - $stats['total_expenses'];

                    $recent_transactions = $db->query("
                        SELECT 'income' as type, source as party, amount, income_date as date, category FROM income
                        UNION ALL
                        SELECT 'expense' as type, payee as party, -amount as amount, expense_date as date, category FROM expenses
                        ORDER BY date DESC LIMIT 10
                    ")->fetchAll();
            ?>
                    <h1 class="h2 mb-4">Financial Summary</h1>
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="card stat-card-income">
                                <div class="card-body">
                                    <h6 class="text-uppercase text-muted">Total Income</h6>
                                    <span class="h3 fw-bold">PKR <?= number_format($stats['total_income'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card stat-card-expense">
                                <div class="card-body">
                                    <h6 class="text-uppercase text-muted">Total Expenses</h6>
                                    <span class="h3 fw-bold">PKR <?= number_format($stats['total_expenses'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card stat-card-net">
                                <div class="card-body">
                                    <h6 class="text-uppercase text-muted">Net Balance</h6>
                                    <span class="h3 fw-bold <?= $net_balance >= 0 ? 'text-success' : 'text-danger' ?>">PKR <?= number_format($net_balance, 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><?= render_icon('arrow-down-up') ?> Recent Transactions</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Party/Source</th>
                                            <th>Category</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recent_transactions)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted p-4">No transactions recorded yet.</td>
                                            </tr>
                                            <?php else: foreach ($recent_transactions as $tx): ?>
                                                <tr>
                                                    <td><?= date('M j, Y', strtotime($tx['date'])) ?></td>
                                                    <td><?= sanitize($tx['party']) ?></td>
                                                    <td><span class="badge bg-secondary"><?= sanitize($tx['category']) ?></span></td>
                                                    <td class="fw-bold <?= $tx['amount'] > 0 ? 'text-success' : 'text-danger' ?>">PKR <?= number_format(abs($tx['amount']), 2) ?></td>
                                                </tr>
                                        <?php endforeach;
                                        endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php
                    break;

                case 'income':
                case 'expenses':
                    $is_income = ($page === 'income');
                    $title = $is_income ? "Income" : "Expenses";
                    $table = $is_income ? "income" : "expenses";
                    $date_col = $is_income ? "income_date" : "expense_date";
                    $party_col = $is_income ? "source" : "payee";

                    $transactions = $db->query("SELECT * FROM $table ORDER BY $date_col DESC")->fetchAll();
                    $categories = $is_income
                        ? ['Donation', 'Grant', 'Sponsorship', 'Other']
                        : ['Office Supplies', 'Rent', 'Utilities', 'Event Costs', 'Bank Charges', 'Other'];
                ?>
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2">Manage <?= $title ?></h1>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal"><?= render_icon('plus-circle') ?> Add New <?= $title ?></button>
                    </div>

                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th><?= $is_income ? "Source" : "Payee" ?></th>
                                            <th>Category</th>
                                            <th>Amount</th>
                                            <th>Description</th><?php if (!$is_income) echo "<th>Receipt</th>"; ?><th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($transactions)): ?>
                                            <tr>
                                                <td colspan="<?= $is_income ? 6 : 7 ?>" class="text-center text-muted p-4">No <?= strtolower($title) ?> recorded.</td>
                                            </tr>
                                            <?php else: foreach ($transactions as $tx): ?>
                                                <tr>
                                                    <td><?= date('M j, Y', strtotime($tx[$date_col])) ?></td>
                                                    <td><?= sanitize($tx[$party_col]) ?></td>
                                                    <td><span class="badge bg-secondary"><?= sanitize($tx['category']) ?></span></td>
                                                    <td class="fw-bold">PKR <?= number_format($tx['amount'], 2) ?></td>
                                                    <td><?= sanitize($tx['description']) ?: 'N/A' ?></td>
                                                    <?php if (!$is_income): ?>
                                                        <td>
                                                            <?php if ($tx['receipt_url']): ?>
                                                                <a href="<?= sanitize($tx['receipt_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><?= render_icon('file-earmark-text') ?> View</a>
                                                            <?php else: ?>
                                                                N/A
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endif; ?>
                                                    <td>
                                                        <form method="post" onsubmit="return confirm('Are you sure you want to delete this transaction?');">
                                                            <input type="hidden" name="action" value="delete_transaction">
                                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                            <input type="hidden" name="id" value="<?= $tx['id'] ?>">
                                                            <input type="hidden" name="type" value="<?= $page === 'income' ? 'income' : 'expense' ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><?= render_icon('trash') ?></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                        <?php endforeach;
                                        endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="addTransactionModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="post" enctype="multipart/form-data">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add New <?= $title ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="<?= $is_income ? 'add_income' : 'add_expense' ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <div class="mb-3">
                                            <label class="form-label"><?= $is_income ? "Source" : "Payee" ?></label>
                                            <input type="text" name="<?= $party_col ?>" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Amount (PKR)</label>
                                            <input type="number" name="amount" class="form-control" step="0.01" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Date</label>
                                            <input type="date" name="<?= $date_col ?>" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Category</label>
                                            <select name="category" class="form-select" required>
                                                <option value="" disabled selected>Select a category...</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= $cat ?>"><?= $cat ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Description (Optional)</label>
                                            <textarea name="description" class="form-control" rows="2"></textarea>
                                        </div>
                                        <?php if (!$is_income): ?>
                                            <div class="mb-3">
                                                <label class="form-label">Receipt (Optional, max 2MB)</label>
                                                <input type="file" name="receipt" class="form-control">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-primary">Save <?= $title ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php
                    break;
                case 'reports':
                    $filter_type = $_GET['type'] ?? 'all';
                    $filter_category = $_GET['category'] ?? '';
                    $start_date = $_GET['start_date'] ?? date('Y-m-01');
                    $end_date = $_GET['end_date'] ?? date('Y-m-t');

                    $income_where = "WHERE income_date BETWEEN ? AND ?";
                    $expense_where = "WHERE expense_date BETWEEN ? AND ?";
                    $income_params = [$start_date, $end_date];
                    $expense_params = [$start_date, $end_date];

                    if ($filter_category) {
                        $income_where .= " AND category = ?";
                        $expense_where .= " AND category = ?";
                        $income_params[] = $filter_category;
                        $expense_params[] = $filter_category;
                    }

                    $sql = "";
                    $params = [];

                    if ($filter_type === 'income') {
                        $sql = "SELECT 'income' as type, id, income_date as date, source as party, category, amount, description, NULL as receipt_url FROM income $income_where";
                        $params = $income_params;
                    } elseif ($filter_type === 'expense') {
                        $sql = "SELECT 'expense' as type, id, expense_date as date, payee as party, category, amount, description, receipt_url FROM expenses $expense_where";
                        $params = $expense_params;
                    } else { // 'all'
                        $sql = "SELECT 'income' as type, id, income_date as date, source as party, category, amount, description, NULL as receipt_url FROM income $income_where
                                UNION ALL
                                SELECT 'expense' as type, id, expense_date as date, payee as party, category, -amount as amount, description, receipt_url FROM expenses $expense_where";
                        $params = array_merge($income_params, $expense_params);
                    }
                    $sql .= " ORDER BY date DESC";

                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $results = $stmt->fetchAll();

                    $total_income = 0;
                    $total_expenses = 0;

                    $summary_params = [$start_date, $end_date];
                    $cat_where = "";
                    if ($filter_category) {
                        $cat_where = " AND category = ?";
                        $summary_params[] = $filter_category;
                    }

                    $total_income = $db->prepare("SELECT IFNULL(SUM(amount), 0) FROM income WHERE income_date BETWEEN ? AND ? $cat_where")->execute($summary_params) ? $db->prepare("SELECT IFNULL(SUM(amount), 0) FROM income WHERE income_date BETWEEN ? AND ? $cat_where")->execute($summary_params) && ($stmt_income = $db->prepare("SELECT IFNULL(SUM(amount), 0) FROM income WHERE income_date BETWEEN ? AND ? $cat_where")) && $stmt_income->execute($summary_params) ? $stmt_income->fetchColumn() : 0 : 0;
                    $total_expenses = $db->prepare("SELECT IFNULL(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ? $cat_where")->execute($summary_params) ? $db->prepare("SELECT IFNULL(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ? $cat_where")->execute($summary_params) && ($stmt_expenses = $db->prepare("SELECT IFNULL(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ? $cat_where")) && $stmt_expenses->execute($summary_params) ? $stmt_expenses->fetchColumn() : 0 : 0;

                    if ($filter_type === 'income') $total_expenses = 0;
                    if ($filter_type === 'expense') $total_income = 0;

                    $net_total = $total_income - $total_expenses;

                    $all_categories = $db->query("SELECT DISTINCT category FROM income UNION SELECT DISTINCT category FROM expenses")->fetchAll(PDO::FETCH_COLUMN);
                ?>
                    <h1 class="h2 mb-4">Financial Reports</h1>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Filter Report</h5>
                        </div>
                        <div class="card-body bg-light">
                            <form method="get">
                                <input type="hidden" name="page" value="reports">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-3"><label>Start Date</label><input type="date" name="start_date" class="form-control" value="<?= sanitize($start_date) ?>"></div>
                                    <div class="col-md-3"><label>End Date</label><input type="date" name="end_date" class="form-control" value="<?= sanitize($end_date) ?>"></div>
                                    <div class="col-md-2"><label>Type</label><select name="type" class="form-select">
                                            <option value="all" <?= $filter_type == 'all' ? 'selected' : '' ?>>All</option>
                                            <option value="income" <?= $filter_type == 'income' ? 'selected' : '' ?>>Income</option>
                                            <option value="expense" <?= $filter_type == 'expense' ? 'selected' : '' ?>>Expenses</option>
                                        </select></div>
                                    <div class="col-md-2"><label>Category</label><select name="category" class="form-select">
                                            <option value="">All Categories</option>
                                            <?php foreach ($all_categories as $cat): if (!$cat) continue; ?>
                                                <option value="<?= sanitize($cat) ?>" <?= $filter_category == $cat ? 'selected' : '' ?>><?= sanitize($cat) ?></option>
                                            <?php endforeach; ?>
                                        </select></div>
                                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><?= render_icon('search') ?> Generate</button></div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Report for <?= date('M j, Y', strtotime($start_date)) ?> to <?= date('M j, Y', strtotime($end_date)) ?></h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Party/Source</th>
                                            <th>Category</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($results)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted p-4">No results for the selected criteria.</td>
                                            </tr>
                                            <?php else: foreach ($results as $res): ?>
                                                <tr>
                                                    <td><?= date('M j, Y', strtotime($res['date'])) ?></td>
                                                    <td><span class="badge bg-<?= $res['type'] === 'income' ? 'success' : 'danger' ?>"><?= $res['type'] ?></span></td>
                                                    <td><?= sanitize($res['party']) ?></td>
                                                    <td><span class="badge bg-secondary"><?= sanitize($res['category']) ?></span></td>
                                                    <td class="fw-bold <?= ($filter_type === 'all' && $res['amount'] < 0) || $res['type'] === 'expense' ? 'text-danger' : 'text-success' ?>">
                                                        PKR <?= number_format(abs($res['amount']), 2) ?>
                                                    </td>
                                                </tr>
                                        <?php endforeach;
                                        endif; ?>
                                    </tbody>
                                    <tfoot class="table-group-divider fw-bold">
                                        <tr>
                                            <td colspan="3"></td>
                                            <td>Total Income:</td>
                                            <td class="text-success">PKR <?= number_format($total_income, 2) ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3"></td>
                                            <td>Total Expenses:</td>
                                            <td class="text-danger">PKR <?= number_format($total_expenses, 2) ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3"></td>
                                            <td>Net Total:</td>
                                            <td class="<?= $net_total >= 0 ? 'text-success' : 'text-danger' ?>">PKR <?= number_format($net_total, 2) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

            <?php

                    break;

                default:
                    echo "<div class='alert alert-danger'>Page not found.</div>";
                    break;
            }
            ?>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', () => {
            document.getElementById('sidebarMenu')?.classList.toggle('active');
        });
    </script>
</body>

</html>
<?php ob_end_flush(); ?>