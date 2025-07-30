<?php
ob_start();
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep errors off in production
date_default_timezone_set('Asia/Karachi');

// --- Configuration and Global Variables ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'lifeflow_db');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('SITE_NAME', 'Poor People Walfare');
define('SESSION_LIFETIME', 7200);

$blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

// --- Core Functions ---

function get_db()
{
    static $db = null;
    if ($db === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $db = new PDO($dsn, DB_USER, DB_PASS);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            http_response_code(500);
            die("Database Connection Error.");
        }
    }
    return $db;
}

function sanitize($data)
{
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

function validate_input($input, $type)
{
    $input = trim($input ?? '');
    switch ($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT, ['flags' => FILTER_FLAG_ALLOW_OCTAL]);
        case 'string':
            return sanitize($input);
        case 'date':
            $d = DateTime::createFromFormat('Y-m-d', $input);
            return $d && $d->format('Y-m-d') === $input ? $input : false;
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
        set_flash_message('danger', 'Invalid security token.');
        return false;
    }
    return true;
}

function redirect($page, $params = [])
{
    $url = $page;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    header("Location: " . $url, true, 303);
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

function get_current_user_role()
{
    return $_SESSION['role'] ?? null;
}
function get_current_user_id()
{
    return $_SESSION['user_id'] ?? null;
}
function render_icon($icon_name, $extra_classes = '')
{
    return "<i class='bi bi-{$icon_name} {$extra_classes}'></i>";
}

function check_auth($required_role)
{
    if (!is_logged_in()) {
        set_flash_message('warning', 'Please log in to access that page.');
        header("Location: index.php?page=login");
        exit;
    }
    if (get_current_user_role() !== $required_role) {
        set_flash_message('danger', 'You do not have permission to access this page.');
        header("Location: index.php?page=dashboard");
        exit;
    }
}

function update_blood_stock_summary($db, $blood_group)
{
    $stmt = $db->prepare(
        "INSERT INTO blood_stock (blood_group, units, last_updated)
         VALUES (:bg, (SELECT COUNT(*) FROM blood_inventory WHERE blood_group = :bg1 AND status = 'available'), NOW())
         ON DUPLICATE KEY UPDATE
            units = (SELECT COUNT(*) FROM blood_inventory WHERE blood_group = :bg2 AND status = 'available'),
            last_updated = NOW();"
    );
    $stmt->execute([':bg' => $blood_group, ':bg1' => $blood_group, ':bg2' => $blood_group]);
}

// --- Page Logic ---
check_auth('admin');
$db = get_db();
$view = sanitize($_GET['view'] ?? 'list');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'issue_blood') {
    if (validate_csrf_token($_POST['csrf_token'])) {
        $inventory_id = validate_input($_POST['inventory_id'], 'int');
        $patient_name = validate_input($_POST['patient_name'], 'string');
        $patient_age = validate_input($_POST['patient_age'], 'int');
        $patient_gender = sanitize($_POST['patient_gender']);
        $hospital_name = validate_input($_POST['hospital_name'], 'string');
        $ward = validate_input($_POST['ward'], 'string');
        $doctor_name = validate_input($_POST['doctor_name'], 'string');
        $issue_date = validate_input($_POST['issue_date'], 'date');
        $request_id_to_fulfill = validate_input($_POST['request_id'], 'int');

        $stmt_bg = $db->prepare("SELECT blood_group FROM blood_inventory WHERE id = ? AND status = 'available'");
        $stmt_bg->execute([$inventory_id]);
        $blood_group = $stmt_bg->fetchColumn();

        if ($inventory_id && $patient_name && $issue_date && $blood_group) {
            try {
                $db->beginTransaction();
                $stmt_issue = $db->prepare("INSERT INTO blood_issues (inventory_id, patient_name, patient_age, patient_gender, hospital_name, ward, doctor_name, issue_date, issued_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_issue->execute([$inventory_id, $patient_name, $patient_age, $patient_gender, $hospital_name, $ward, $doctor_name, $issue_date, get_current_user_id()]);
                $issue_id = $db->lastInsertId();

                $stmt_update = $db->prepare("UPDATE blood_inventory SET status = 'used', notes = CONCAT(IFNULL(notes, ''), ' Issued via record #', ?) WHERE id = ?");
                $stmt_update->execute([$issue_id, $inventory_id]);

                if ($request_id_to_fulfill) {
                    $stmt_fulfill = $db->prepare("UPDATE requests SET status = 'fulfilled' WHERE id = ?");
                    $stmt_fulfill->execute([$request_id_to_fulfill]);
                }
                update_blood_stock_summary($db, $blood_group);
                $db->commit();
                set_flash_message('success', 'Life Saved! Blood bag has been successfully issued.');
                redirect('issueblood.php', ['view' => 'receipt', 'id' => $issue_id]);
            } catch (Exception $e) {
                $db->rollBack();
                set_flash_message('danger', 'Database error during issuance. Please try again.');
                redirect('issueblood.php', ['view' => 'list']);
            }
        } else {
            set_flash_message('danger', 'Issuance failed. Please ensure all required fields are correct and the selected bag is available.');
            redirect('issueblood.php', ['view' => 'list', 'request_id' => $request_id_to_fulfill]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Blood | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .receipt-container {
            background: #fff;
            border: 2px solid #006a4e;
            padding: 25px;
            max-width: 800px;
            margin: auto;
            font-family: 'Times New Roman', serif;
        }

        .editable {
            background-color: #f0fff0;
            cursor: pointer;
            border: 1px dashed #ccc;
            padding: 2px 5px;
        }

        .editable:hover {
            background-color: #e0f8e0;
            border: 1px dashed #006a4e;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body,
            .container-fluid {
                margin: 0;
                padding: 0;
            }

            .receipt-container {
                max-width: 100%;
                border: none;
                box-shadow: none;
                margin: 0;
            }

            .editable {
                border: none !important;
                background-color: transparent !important;
            }
        }
    </style>
</head>

<body>
    <header class="bg-dark text-white p-3 d-flex justify-content-between align-items-center no-print">
        <h4 class="mb-0"><?= render_icon('box-arrow-up-right') ?> Blood Issuance Module</h4>
        <a href="index.php?page=dashboard" class="btn btn-light"><?= render_icon('arrow-left-circle') ?> Back to Dashboard</a>
    </header>

    <main class="container-fluid my-4">
        <?php if ($view !== 'receipt'): ?>
            <ul class="nav nav-tabs mb-4 no-print">
                <li class="nav-item"><a class="nav-link <?= ($view === 'list') ? 'active' : '' ?>" href="?view=list">Issue Blood</a></li>
                <li class="nav-item"><a class="nav-link <?= ($view === 'reports') ? 'active' : '' ?>" href="?view=reports">Issuance Reports</a></li>
            </ul>
        <?php endif; ?>
        <?php display_flash_message(); ?>

        <?php
        switch ($view):
            case 'receipt':
                $issue_id = validate_input($_GET['id'] ?? 0, 'int');
                if (!$issue_id) die('Invalid Issue ID.');

                $stmt = $db->prepare(
                    "SELECT bi.*, inv.bag_id, inv.blood_group, inv.collection_date, inv.expiry_date,
                            issuer.full_name as issuer_name, donor.full_name as donor_name
                     FROM blood_issues bi 
                     JOIN blood_inventory inv ON bi.inventory_id = inv.id 
                     JOIN users issuer ON bi.issued_by = issuer.id 
                     LEFT JOIN users donor ON inv.donor_id = donor.id
                     WHERE bi.id = ?"
                );
                $stmt->execute([$issue_id]);
                $receipt = $stmt->fetch();
                if (!$receipt) die('Receipt not found.');
        ?>
                <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                    <h1 class="h2">Issuance Receipt</h1>
                    <div>
                        <a href="issueblood.php" class="btn btn-secondary"><?= render_icon('plus-circle') ?> New Issue</a>
                        <button id="downloadBtn" class="btn btn-primary"><?= render_icon('download') ?> Download Receipt</button>
                    </div>
                </div>
                <div id="receipt" class="receipt-container">
                    <div class="text-center border-bottom border-2 border-success pb-3 mb-4">
                        <h2><?= SITE_NAME ?> - Blood Bank</h2>
                        <p class="mb-0">Bannu, Khyber Pakhtunkhwa, Pakistan | Tel: +92 300 1234567</p>
                    </div>
                    <div class="text-center fs-5 fw-bold text-decoration-underline my-4">BLOOD ISSUE & CROSS-MATCH REPORT</div>
                    <table class="table table-bordered">
                        <tr class="table-light">
                            <td colspan="4" class="fw-bold text-center">Patient Details</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Patient Name:</td>
                            <td class="editable" contenteditable="true"><?= sanitize($receipt['patient_name']) ?></td>
                            <td class="fw-bold">Age/Gender:</td>
                            <td class="editable" contenteditable="true"><?= sanitize($receipt['patient_age'] ?: '') ?> / <?= sanitize($receipt['patient_gender'] ?: '') ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Hospital:</td>
                            <td class="editable" contenteditable="true"><?= sanitize($receipt['hospital_name']) ?></td>
                            <td class="fw-bold">Ward/Dept:</td>
                            <td class="editable" contenteditable="true"><?= sanitize($receipt['ward'] ?: '') ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Doctor's Name:</td>
                            <td class="editable" contenteditable="true" colspan="3"><?= sanitize($receipt['doctor_name'] ?: '') ?></td>
                        </tr>
                    </table>
                    <table class="table table-bordered mt-4">
                        <tr class="table-light">
                            <td colspan="4" class="fw-bold text-center">Blood Unit & Donor Details</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Bag ID:</td>
                            <td><?= sanitize($receipt['bag_id']) ?></td>
                            <td class="fw-bold">Blood Group:</td>
                            <td class="fw-bold text-danger fs-5"><?= sanitize($receipt['blood_group']) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Collection Date:</td>
                            <td><?= date('d-M-Y', strtotime($receipt['collection_date'])) ?></td>
                            <td class="fw-bold">Expiry Date:</td>
                            <td><?= date('d-M-Y', strtotime($receipt['expiry_date'])) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Donor Name:</td>
                            <td colspan="3"><?= sanitize($receipt['donor_name'] ?: 'Voluntary / Unregistered') ?></td>
                        </tr>
                    </table>
                    <table class="table table-bordered mt-4">
                        <tr class="table-light">
                            <td colspan="2" class="fw-bold text-center">Cross-Match & Screening Results</td>
                        </tr>
                        <tr>
                            <td class="fw-bold" style="width: 50%;">Compatibility</td>
                            <td class="editable" contenteditable="true">Compatible</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Saline</td>
                            <td class="editable" contenteditable="true">No Agglutination</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Albumin</td>
                            <td class="editable" contenteditable="true">No Agglutination</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Coombs Test (IAT)</td>
                            <td class="editable" contenteditable="true">Negative</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Screening (HBsAg, HCV, HIV)</td>
                            <td class="editable" contenteditable="true">Non-Reactive</td>
                        </tr>
                    </table>
                    <div class="mt-5 pt-4 d-flex justify-content-between">
                        <div class="text-center pt-2 border-top border-dark"><strong>Issued By:</strong><br><?= sanitize($receipt['issuer_name']) ?></div>
                        <div class="text-center pt-2 border-top border-dark" style="width:200px;">Technologist Signature</div>
                        <div class="text-center pt-2 border-top border-dark" style="width:200px;">Receiver Signature</div>
                    </div>
                </div>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
                <script>
                    document.getElementById('downloadBtn').addEventListener('click', function() {
                        html2canvas(document.getElementById('receipt'), {
                            scale: 2.5
                        }).then(canvas => {
                            const link = document.createElement('a');
                            link.download = 'Receipt-<?= sanitize($receipt['bag_id']) ?>-<?= date('Ymd') ?>.png';
                            link.href = canvas.toDataURL('image/png');
                            link.click();
                        });
                    });
                </script>
            <?php break;

            case 'reports':
                $start_date = sanitize($_GET['start_date'] ?? date('Y-m-01'));
                $end_date = sanitize($_GET['end_date'] ?? date('Y-m-d'));
                $filter_bg = sanitize($_GET['filter_bg'] ?? '');

                $sql = "SELECT bi.*, inv.bag_id, inv.blood_group, u.full_name as issuer_name FROM blood_issues bi JOIN blood_inventory inv ON bi.inventory_id = inv.id JOIN users u ON bi.issued_by = u.id WHERE bi.issue_date BETWEEN ? AND ?";
                $params = [$start_date, $end_date];
                if ($filter_bg) {
                    $sql .= " AND inv.blood_group = ?";
                    $params[] = $filter_bg;
                }
                $sql .= " ORDER BY bi.issue_date DESC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $report_data = $stmt->fetchAll();
            ?>
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Issuance Reports</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3 align-items-end p-3 bg-light rounded mb-4 no-print">
                            <input type="hidden" name="view" value="reports">
                            <div class="col-md-3"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control" value="<?= $start_date ?>"></div>
                            <div class="col-md-3"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control" value="<?= $end_date ?>"></div>
                            <div class="col-md-3"><label class="form-label">Blood Group</label><select name="filter_bg" class="form-select">
                                    <option value="">All</option><?php foreach ($blood_groups as $bg) echo "<option value='{$bg}' " . ($filter_bg == $bg ? 'selected' : '') . ">{$bg}</option>"; ?>
                                </select></div>
                            <div class="col-md-3"><button type="submit" class="btn btn-primary w-100"><?= render_icon('search') ?> Generate</button></div>
                        </form>
                        <div id="report-container" class="report-container">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4>Report: Blood Issued from <?= date('d M, Y', strtotime($start_date)) ?> to <?= date('d M, Y', strtotime($end_date)) ?></h4>
                                <button onclick="window.print()" class="btn btn-secondary no-print"><?= render_icon('printer') ?> Print Report</button>
                            </div>
                            <p><strong>Total Records:</strong> <?= count($report_data) ?></p>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Date</th>
                                            <th>Patient</th>
                                            <th>Hospital</th>
                                            <th>BG</th>
                                            <th>Bag ID</th>
                                            <th>Issued By</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($report_data)): foreach ($report_data as $row): ?>
                                                <tr>
                                                    <td><?= $row['id'] ?></td>
                                                    <td><?= date('d-m-Y', strtotime($row['issue_date'])) ?></td>
                                                    <td><?= sanitize($row['patient_name']) ?></td>
                                                    <td><?= sanitize($row['hospital_name']) ?></td>
                                                    <td class="fw-bold"><?= sanitize($row['blood_group']) ?></td>
                                                    <td><?= sanitize($row['bag_id']) ?></td>
                                                    <td><?= sanitize($row['issuer_name']) ?></td>
                                                    <td><a href="?view=receipt&id=<?= $row['id'] ?>" class="btn btn-sm btn-info" title="View Receipt"><?= render_icon('receipt') ?></a></td>
                                                </tr>
                                            <?php endforeach;
                                        else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center p-4">No records found for the selected criteria.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php break;

            case 'list':
            default:
                $available_bags = $db->query("SELECT id, bag_id, blood_group, expiry_date FROM blood_inventory WHERE status = 'available' ORDER BY expiry_date ASC")->fetchAll();
                $pending_requests = $db->query("SELECT id, patient_name, blood_group, hospital_name FROM requests WHERE status = 'pending' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

                $prefill_data = null;
                $request_id_from_url = validate_input($_GET['request_id'] ?? null, 'int');
                if ($request_id_from_url) {
                    $stmt = $db->prepare("SELECT id, patient_name, blood_group, hospital_name FROM requests WHERE id = ?");
                    $stmt->execute([$request_id_from_url]);
                    $prefill_data = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            ?>
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Issue Blood to Patient</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3" id="issue-form">
                            <input type="hidden" name="action" value="issue_blood"><input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>"><input type="hidden" name="request_id" id="request_id_field" value="<?= sanitize($prefill_data['id'] ?? '') ?>">
                            <div class="col-12"><label for="request_selector" class="form-label fw-bold">1. Fulfill Pending Request (Optional)</label><select id="request_selector" class="form-select">
                                    <option value="" data-patient-name="" data-hospital-name="" data-blood-group="">Enter New Patient Details Manually...</option>
                                    <?php foreach ($pending_requests as $req): ?><option value="<?= $req['id'] ?>" data-patient-name="<?= sanitize($req['patient_name']) ?>" data-hospital-name="<?= sanitize($req['hospital_name']) ?>" data-blood-group="<?= sanitize($req['blood_group']) ?>" <?= ($prefill_data && $prefill_data['id'] == $req['id']) ? 'selected' : '' ?>>Req #<?= $req['id'] ?>: <?= sanitize($req['patient_name']) ?> (<?= $req['blood_group'] ?>) at <?= sanitize($req['hospital_name']) ?></option><?php endforeach; ?>
                                </select></div>
                            <hr>
                            <div class="col-md-6"><label for="patient_name_field" class="form-label fw-bold">2. Patient Details</label><input type="text" name="patient_name" id="patient_name_field" class="form-control mb-2" placeholder="Patient Full Name" value="<?= sanitize($prefill_data['patient_name'] ?? '') ?>" required><input type="text" name="hospital_name" id="hospital_name_field" class="form-control" placeholder="Hospital Name" value="<?= sanitize($prefill_data['hospital_name'] ?? 'On-Site Clinic') ?>" required></div>
                            <div class="col-md-6"><label class="form-label">&nbsp;</label>
                                <div class="input-group mb-2"><input type="number" name="patient_age" class="form-control" placeholder="Patient Age"><select name="patient_gender" class="form-select">
                                        <option>Male</option>
                                        <option>Female</option>
                                        <option>Other</option>
                                    </select></div>
                                <div class="input-group"><input type="text" name="ward" class="form-control" placeholder="Ward / Department"><input type="text" name="doctor_name" class="form-control" placeholder="Referring Doctor"></div>
                            </div>
                            <div class="col-md-8"><label for="inventory_id" class="form-label fw-bold">3. Select Available Blood Bag</label><select name="inventory_id" id="inventory_id" class="form-select" required>
                                    <option value="" disabled selected>Choose a bag (<?= count($available_bags) ?> available)...</option>
                                    <?php foreach ($available_bags as $bag): ?><option value="<?= $bag['id'] ?>" data-blood-group="<?= $bag['blood_group'] ?>"><?= $bag['bag_id'] ?> (<?= $bag['blood_group'] ?>) - Expires: <?= date('d M, Y', strtotime($bag['expiry_date'])) ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="col-md-4"><label for="issue_date" class="form-label fw-bold">4. Issue Date</label><input type="date" name="issue_date" id="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                            <div class="col-12 mt-4"><button type="submit" class="btn btn-success w-100 fs-5 fw-bold"><?= render_icon('check2-circle') ?> FULFILL & SAVE LIFE</button></div>
                        </form>
                    </div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const requestSelector = document.getElementById('request_selector');
                        const inventorySelector = document.getElementById('inventory_id');
                        const inventoryOptions = Array.from(inventorySelector.options);

                        function filterInventory() {
                            const selectedRequest = requestSelector.options[requestSelector.selectedIndex];
                            const requiredBloodGroup = selectedRequest.getAttribute('data-blood-group');

                            // Pre-fill patient details
                            document.getElementById('request_id_field').value = requestSelector.value;
                            document.getElementById('patient_name_field').value = selectedRequest.getAttribute('data-patient-name');
                            document.getElementById('hospital_name_field').value = selectedRequest.getAttribute('data-hospital-name') || 'On-Site Clinic';

                            // Filter inventory dropdown
                            let hasSelectableOption = false;
                            inventorySelector.innerHTML = '';
                            inventorySelector.appendChild(inventoryOptions[0]); // Keep the "Choose a bag..." option
                            inventoryOptions[0].selected = true; // Reselect the placeholder

                            inventoryOptions.slice(1).forEach(option => {
                                if (!requiredBloodGroup || option.getAttribute('data-blood-group') === requiredBloodGroup) {
                                    inventorySelector.appendChild(option);
                                    if (!hasSelectableOption) {
                                        hasSelectableOption = true;
                                    }
                                }
                            });

                            // If filtering resulted in no options, add a disabled message
                            if (!hasSelectableOption && requiredBloodGroup) {
                                let noMatchOption = document.createElement('option');
                                noMatchOption.disabled = true;
                                noMatchOption.textContent = `No bags found for blood group ${requiredBloodGroup}`;
                                inventorySelector.appendChild(noMatchOption);
                            }
                        }

                        requestSelector.addEventListener('change', filterInventory);

                        // Run filter on page load in case a request is pre-selected via URL
                        if (requestSelector.value) {
                            filterInventory();
                        }
                    });
                </script>
        <?php break;
        endswitch; ?>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php ob_end_flush(); ?>