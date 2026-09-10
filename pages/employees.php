<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle = 'Employees';
$currentPage = 'employees';
$pdo = getDB();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_employee') {
        try {
            $pdo->beginTransaction();
            
            // Generate next code
            $lastCode = $pdo->query("SELECT code FROM employees ORDER BY id DESC LIMIT 1")->fetchColumn();
            $nextNum = 1;
            if ($lastCode && preg_match('/EMP-\d{4}-(\d+)/', $lastCode, $matches)) {
                $nextNum = intval($matches[1]) + 1;
            }
            $code = 'EMP-' . date('Y') . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
            $id = 'emp-' . uniqid();
            
            $stmt = $pdo->prepare("
                INSERT INTO employees 
                (id, code, first_name, middle_name, last_name, suffix, email, mobile, birth_date, gender, department, position, employment_type, hire_date, basic_salary, status, sss, philhealth, pagibig, tin, ewallet_provider, ewallet_account, ewallet_name) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $id,
                $code,
                $_POST['first_name'],
                $_POST['middle_name'] ?: null,
                $_POST['last_name'],
                $_POST['suffix'] ?: null,
                $_POST['email'] ?: null,
                $_POST['mobile'] ?: null,
                $_POST['birth_date'] ?: null,
                $_POST['gender'] ?: null,
                $_POST['department'],
                $_POST['position'],
                $_POST['employment_type'],
                $_POST['hire_date'],
                $_POST['basic_salary'] ?: 0,
                $_POST['status'],
                $_POST['sss'] ?: null,
                $_POST['philhealth'] ?: null,
                $_POST['pagibig'] ?: null,
                $_POST['tin'] ?: null,
                $_POST['ewallet_provider'] ?: null,
                $_POST['ewallet_account'] ?: null,
                $_POST['ewallet_name'] ?: null
            ]);
            
            // Insert initial salary history
            $stmtHist = $pdo->prepare("INSERT INTO salary_history (employee_id, basic_salary, effective_date, reason) VALUES (?, ?, ?, ?)");
            $stmtHist->execute([$id, $_POST['basic_salary'] ?: 0, $_POST['hire_date'], 'Initial salary on hire']);
            
            auditLog('Employee Created', "Created employee {$code} - {$_POST['first_name']} {$_POST['last_name']}");
            $pdo->commit();
            header('Location: ' . BASE_URL . '/index.php?page=employees&success=created');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error creating employee: " . $e->getMessage();
        }
    } elseif ($action === 'delete_employee') {
        try {
            $empId = $_POST['employee_id'];
            $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
            $stmt->execute([$empId]);
            auditLog('Employee Deleted', "Deleted employee ID {$empId}");
            header('Location: ' . BASE_URL . '/index.php?page=employees&success=deleted');
            exit;
        } catch (Exception $e) {
            $error = "Error deleting employee: " . $e->getMessage();
        }
    }
}

// Fetch stats
$statTotal = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$statActive = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn();
$statLeave = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'On Leave'")->fetchColumn();
$statResigned = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'Resigned'")->fetchColumn();

// Fetch departments for filter
$departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// Filter params
$search = $_GET['search'] ?? '';
$dept_filter = $_GET['dept_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

// Fetch employees
$query = "SELECT * FROM employees WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($dept_filter) {
    $query .= " AND department = ?";
    $params[] = $dept_filter;
}
if ($status_filter) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}
$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-main">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  
  <div class="page-content">
    <div class="page-header">
      <div>
        <h1>Employees</h1>
        <p>Manage employee records and profiles</p>
      </div>
      <button class="btn btn-primary" onclick="openModal('modalCreate')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        Add Employee
      </button>
    </div>

    <?php if(isset($error)): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if(isset($_GET['success'])): ?>
        <script>document.addEventListener('DOMContentLoaded', () => showToast('Operation successful'));</script>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Employees</div>
        <div class="stat-value"><?= number_format($statTotal) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Active</div>
        <div class="stat-value text-main"><?= number_format($statActive) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">On Leave</div>
        <div class="stat-value" style="color:var(--warning)"><?= number_format($statLeave) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Resigned</div>
        <div class="stat-value" style="color:var(--danger)"><?= number_format($statResigned) ?></div>
      </div>
    </div>

    <!-- Filters -->
    <form class="filters-bar" method="GET">
      <input type="hidden" name="page" value="employees">
      <input type="text" name="search" class="form-control" placeholder="Search employees..." value="<?= htmlspecialchars($search) ?>" style="width:250px;">
      <select name="dept_filter" class="form-control" onchange="this.form.submit()">
        <option value="">All Departments</option>
        <?php foreach($departments as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>" <?= $dept_filter === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status_filter" class="form-control" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>Active</option>
        <option value="On Leave" <?= $status_filter === 'On Leave' ? 'selected' : '' ?>>On Leave</option>
        <option value="Resigned" <?= $status_filter === 'Resigned' ? 'selected' : '' ?>>Resigned</option>
        <option value="Terminated" <?= $status_filter === 'Terminated' ? 'selected' : '' ?>>Terminated</option>
      </select>
      <noscript><button type="submit" class="btn btn-secondary">Filter</button></noscript>
    </form>

    <!-- Employees Table -->
    <div class="card">
      <div class="table-wrap">
        <table id="empTable">
          <thead>
            <tr>
              <th>Code</th>
              <th>Name</th>
              <th>Department</th>
              <th>Position</th>
              <th>Basic Salary</th>
              <th>E-Wallet</th>
              <th>Status</th>
              <th style="text-align:right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $emp): ?>
            <tr>
              <td class="font-semibold"><?= htmlspecialchars($emp['code']) ?></td>
              <td>
                <div class="text-main font-semibold"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($emp['email']) ?></div>
              </td>
              <td><?= htmlspecialchars($emp['department']) ?></td>
              <td><?= htmlspecialchars($emp['position']) ?></td>
              <td><?= formatCurrency($emp['basic_salary']) ?></td>
              <td>
                  <?php if($emp['ewallet_provider']): ?>
                    <span class="badge badge-info"><?= htmlspecialchars($emp['ewallet_provider']) ?></span>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
              </td>
              <td><span class="badge <?= getStatusBadgeClass($emp['status']) ?>"><?= htmlspecialchars($emp['status']) ?></span></td>
              <td style="text-align:right">
                <button class="btn btn-ghost btn-sm" onclick="viewEmployee(<?= htmlspecialchars(json_encode($emp)) ?>)">View</button>
                <button class="btn btn-ghost btn-sm" style="color:var(--danger)" onclick="deleteEmployee('<?= htmlspecialchars($emp['id']) ?>', '<?= htmlspecialchars(addslashes($emp['first_name'] . ' ' . $emp['last_name'])) ?>')">Delete</button>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(!$employees): ?>
            <tr><td colspan="8" class="empty-state">No employees found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- Modal: Create Employee -->
<div id="modalCreate" class="modal-backdrop" style="display:none;">
  <div class="modal-box modal-lg">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="create_employee">
      
      <div class="modal-header">
        <h3>Add New Employee</h3>
        <button type="button" class="modal-close" onclick="closeModal('modalCreate')">&times;</button>
      </div>
      
      <div class="modal-body">
        <h4 style="margin-bottom:12px;font-size:13px;color:var(--text-main)">Personal Information</h4>
        <div class="form-row">
            <div class="form-group"><label class="form-label required">First Name</label><input type="text" name="first_name" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-control"></div>
            <div class="form-group"><label class="form-label required">Last Name</label><input type="text" name="last_name" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Suffix</label><input type="text" name="suffix" class="form-control" placeholder="Jr., Sr., III"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Email Address</label><input type="email" name="email" class="form-control"></div>
            <div class="form-group"><label class="form-label">Mobile Number</label><input type="text" name="mobile" class="form-control"></div>
            <div class="form-group"><label class="form-label">Birth Date</label><input type="date" name="birth_date" class="form-control"></div>
            <div class="form-group">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-control">
                    <option value="">Select Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>

        <hr class="divider">
        <h4 style="margin-bottom:12px;font-size:13px;color:var(--text-main)">Employment Information</h4>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required">Department</label>
                <select name="department" class="form-control" required>
                    <option value="">Select Department</option>
                    <option value="IT Department">IT Department</option>
                    <option value="HR Department">HR Department</option>
                    <option value="Finance Department">Finance Department</option>
                    <option value="Operations">Operations</option>
                    <option value="Marketing">Marketing</option>
                </select>
            </div>
            <div class="form-group"><label class="form-label required">Position</label><input type="text" name="position" class="form-control" required></div>
            <div class="form-group">
                <label class="form-label required">Employment Type</label>
                <select name="employment_type" class="form-control" required>
                    <option value="Regular">Regular</option>
                    <option value="Probationary">Probationary</option>
                    <option value="Contractual">Contractual</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label required">Hire Date</label><input type="date" name="hire_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="form-group">
                <label class="form-label required">Status</label>
                <select name="status" class="form-control" required>
                    <option value="Active">Active</option>
                    <option value="On Leave">On Leave</option>
                    <option value="Resigned">Resigned</option>
                    <option value="Terminated">Terminated</option>
                </select>
            </div>
        </div>

        <hr class="divider">
        <h4 style="margin-bottom:12px;font-size:13px;color:var(--text-main)">Compensation</h4>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required">Monthly Basic Salary</label>
                <input type="number" step="0.01" id="newBasicSal" name="basic_salary" class="form-control" required oninput="computeRates('newBasicSal', 'newDailyRate', 'newHourlyRate')">
            </div>
            <div class="form-group">
                <label class="form-label">Daily Rate (Est.)</label>
                <input type="text" id="newDailyRate" class="form-control" readonly style="background:var(--surface-alt)">
            </div>
            <div class="form-group">
                <label class="form-label">Hourly Rate (Est.)</label>
                <input type="text" id="newHourlyRate" class="form-control" readonly style="background:var(--surface-alt)">
            </div>
        </div>

        <hr class="divider">
        <h4 style="margin-bottom:12px;font-size:13px;color:var(--text-main)">Government IDs</h4>
        <div class="form-row">
            <div class="form-group"><label class="form-label">SSS Number</label><input type="text" name="sss" class="form-control"></div>
            <div class="form-group"><label class="form-label">PhilHealth Number</label><input type="text" name="philhealth" class="form-control"></div>
            <div class="form-group"><label class="form-label">Pag-IBIG Number</label><input type="text" name="pagibig" class="form-control"></div>
            <div class="form-group"><label class="form-label">TIN Number</label><input type="text" name="tin" class="form-control"></div>
        </div>

        <hr class="divider">
        <h4 style="margin-bottom:12px;font-size:13px;color:var(--text-main)">E-Wallet & Payment Details</h4>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Provider</label>
                <select id="provSelect" name="ewallet_provider" class="form-control" onchange="onProviderChange(this, document.getElementById('accInput'), document.getElementById('accFeed'))">
                    <option value="">Select Provider</option>
                    <option value="GCash">GCash</option>
                    <option value="Maya">Maya</option>
                    <option value="PayMaya">PayMaya</option>
                    <option value="Bank">Bank</option>
                    <option value="Company Bank">Company Bank</option>
                    <option value="Cash">Cash</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Account Number</label>
                <input type="text" id="accInput" name="ewallet_account" class="form-control" oninput="onAccountInput(document.getElementById('provSelect'), this, document.getElementById('accFeed'))">
                <div id="accFeed" style="font-size:11px;margin-top:4px;"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Account Name</label>
                <input type="text" name="ewallet_name" class="form-control">
            </div>
        </div>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalCreate')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Employee</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: View Employee -->
<div id="modalView" class="modal-backdrop" style="display:none;">
  <div class="modal-box modal-lg">
    <div class="modal-header">
      <h3 id="viewTitle">Employee Details</h3>
      <button type="button" class="modal-close" onclick="closeModal('modalView')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="grid-2" id="viewGrid" style="font-size:13px;">
          <!-- Populated by JS -->
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modalView')">Close</button>
    </div>
  </div>
</div>

<!-- Modal: Delete -->
<div id="modalDelete" class="modal-backdrop" style="display:none;">
  <div class="modal-box">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="delete_employee">
      <input type="hidden" name="employee_id" id="delEmpId">
      
      <div class="modal-header">
        <h3 style="color:var(--danger)">Delete Employee</h3>
        <button type="button" class="modal-close" onclick="closeModal('modalDelete')">&times;</button>
      </div>
      <div class="modal-body text-center">
        <svg width="48" height="48" fill="none" stroke="var(--danger)" stroke-width="2" viewBox="0 0 24 24" style="margin:0 auto 16px;"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <p>Are you sure you want to delete <strong id="delEmpName"></strong>?</p>
        <p class="text-muted mt-4">This action cannot be undone and will cascade delete associated records.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalDelete')">Cancel</button>
        <button type="submit" class="btn btn-danger">Delete Employee</button>
      </div>
    </form>
  </div>
</div>

<script>
function deleteEmployee(id, name) {
    document.getElementById('delEmpId').value = id;
    document.getElementById('delEmpName').textContent = name;
    openModal('modalDelete');
}
function viewEmployee(emp) {
    document.getElementById('viewTitle').textContent = emp.first_name + ' ' + emp.last_name + ' (' + emp.code + ')';
    const grid = document.getElementById('viewGrid');
    
    const fields = [
        { label: 'Full Name', val: `${emp.first_name} ${emp.middle_name || ''} ${emp.last_name} ${emp.suffix || ''}`.trim() },
        { label: 'Code', val: emp.code },
        { label: 'Department', val: emp.department },
        { label: 'Position', val: emp.position },
        { label: 'Email', val: emp.email || '-' },
        { label: 'Mobile', val: emp.mobile || '-' },
        { label: 'Gender', val: emp.gender || '-' },
        { label: 'Birth Date', val: emp.birth_date || '-' },
        { label: 'Employment Type', val: emp.employment_type },
        { label: 'Hire Date', val: emp.hire_date },
        { label: 'Status', val: emp.status },
        { label: 'Basic Salary', val: formatPHP(emp.basic_salary) },
        { label: 'SSS', val: emp.sss || '-' },
        { label: 'PhilHealth', val: emp.philhealth || '-' },
        { label: 'Pag-IBIG', val: emp.pagibig || '-' },
        { label: 'TIN', val: emp.tin || '-' },
        { label: 'E-Wallet Provider', val: emp.ewallet_provider || '-' },
        { label: 'E-Wallet Account', val: emp.ewallet_account || '-' },
    ];
    
    grid.innerHTML = fields.map(f => `
        <div style="margin-bottom:12px;">
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:2px;text-transform:uppercase;font-weight:600;">${f.label}</div>
            <div style="color:var(--text-main);font-weight:500;">${f.val}</div>
        </div>
    `).join('');
    
    openModal('modalView');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
