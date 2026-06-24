<?php
// admin/employees.php – Add / Edit / Deactivate employees
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin(BASE_URL . '/index.php');

$pageTitle = 'Employee Management';
$activeNav = 'emp';
$db        = DB::get();
$msg       = $err = '';

// ── Handle POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id         = (int)($_POST['id'] ?? 0);
        $full_name  = trim($_POST['full_name'] ?? '');
        $email      = strtolower(trim($_POST['email'] ?? ''));
        $role       = in_array($_POST['role'] ?? '', ['admin','employee']) ? $_POST['role'] : 'employee';
        $dept       = trim($_POST['department'] ?? '');
        $position   = trim($_POST['position'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $password   = $_POST['password'] ?? '';

        if (!$full_name || !$email) {
            $err = 'Name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Invalid email address.';
        } else {
            if ($action === 'add') {
                if (!$password) { $err = 'Password is required for new employees.'; }
                else {
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    try {
                        db_query(
                            'INSERT INTO users (full_name,email,password,role,department,position,phone) VALUES (?,?,?,?,?,?,?)',
                            [$full_name, $email, $hash, $role, $dept, $position, $phone]
                        );
                        $msg = 'Employee added successfully.';
                    } catch (PDOException $e) {
                        $err = 'Email already exists.';
                    }
                }
            } else { // edit
                $fields = 'full_name=?, email=?, role=?, department=?, position=?, phone=?';
                $params = [$full_name, $email, $role, $dept, $position, $phone];
                if ($password) {
                    $fields  .= ', password=?';
                    $params[] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                }
                $params[] = $id;
                db_query("UPDATE users SET $fields WHERE id=?", $params);
                $msg = 'Employee updated.';
            }
        }
    }

    if ($action === 'toggle') {
        $id     = (int)$_POST['id'];
        $active = (int)$_POST['is_active'];
        db_query('UPDATE users SET is_active=? WHERE id=?', [$active ? 0 : 1, $id]);
        $msg = 'Employee status updated.';
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        db_query('DELETE FROM users WHERE id=? AND role != \'admin\'', [$id]);
        $msg = 'Employee removed.';
    }

    if ($action === 'delete_all') {
        $count = db_query("SELECT COUNT(*) FROM users WHERE role='employee'")->fetchColumn();
        db_query("DELETE FROM users WHERE role='employee'");
        $msg = "All $count employee(s) have been removed.";
    }
}

// ── Fetch employees ────────────────────────────────────────────
$search    = trim($_GET['q'] ?? '');
$employees = db_query(
    "SELECT * FROM users WHERE role='employee' AND (full_name LIKE ? OR email LIKE ? OR department LIKE ?) ORDER BY full_name",
    ["%$search%", "%$search%", "%$search%"]
)->fetchAll();

// Editing?
$editing = null;
if (isset($_GET['edit'])) {
    $editing = db_query('SELECT * FROM users WHERE id=?', [(int)$_GET['edit']])->fetch();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="content-inner">

  <?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php elseif ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-x-circle me-2"></i><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <div class="row g-4">

    <!-- Form -->
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><?= $editing ? 'Edit Employee' : 'Add New Employee' ?></div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'add' ?>">
            <?php if ($editing): ?>
              <input type="hidden" name="id" value="<?= $editing['id'] ?>">
            <?php endif; ?>

            <div class="mb-3">
              <label class="form-label">Full Name *</label>
              <input type="text" name="full_name" class="form-control" required
                value="<?= htmlspecialchars($editing['full_name'] ?? '') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Email *</label>
              <input type="email" name="email" class="form-control" required
                value="<?= htmlspecialchars($editing['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Password <?= $editing ? '(leave blank to keep)' : '*' ?></label>
              <input type="password" name="password" class="form-control" placeholder="••••••••"
                <?= $editing ? '' : 'required' ?>>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                  <option value="employee" <?= ($editing['role'] ?? '') !== 'admin' ? 'selected' : '' ?>>Employee</option>
                  <option value="admin"    <?= ($editing['role'] ?? '') === 'admin'  ? 'selected' : '' ?>>Admin</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label">Phone</label>
                <input type="tel" name="phone" class="form-control"
                  value="<?= htmlspecialchars($editing['phone'] ?? '') ?>">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Department</label>
              <input type="text" name="department" class="form-control"
                value="<?= htmlspecialchars($editing['department'] ?? '') ?>">
            </div>
            <div class="mb-4">
              <label class="form-label">Position</label>
              <input type="text" name="position" class="form-control"
                value="<?= htmlspecialchars($editing['position'] ?? '') ?>">
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary flex-fill fw-600">
                <i class="bi bi-<?= $editing ? 'save' : 'person-plus' ?> me-1"></i>
                <?= $editing ? 'Save Changes' : 'Add Employee' ?>
              </button>
              <?php if ($editing): ?>
                <a href="<?= BASE_URL ?>/admin/employees.php" class="btn btn-outline-secondary">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- List -->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            Employees <span class="badge bg-secondary"><?= count($employees) ?></span>
            <?php if (!empty($employees)): ?>
              <form method="post" class="d-inline delete-all-form" onsubmit="return confirmDeleteAll()">
                <input type="hidden" name="action" value="delete_all">
                <button type="submit" class="btn btn-sm btn-outline-danger">
                  <i class="bi bi-trash3 me-1"></i>Delete All
                </button>
              </form>
            <?php endif; ?>
          </div>
          <form method="get" class="d-flex gap-2 ms-auto">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
              class="form-control form-control-sm" placeholder="Search…" style="min-width:140px">
            <button type="submit" class="btn btn-sm btn-outline-primary">Go</button>
          </form>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr>
                <th>Name</th><th>Dept</th><th>Role</th><th>Status</th><th>Actions</th>
              </tr></thead>
              <tbody>
                <?php if (empty($employees)): ?>
                  <tr><td colspan="5" class="text-center text-muted py-4">No employees found.</td></tr>
                <?php else: ?>
                <?php foreach ($employees as $e): ?>
                  <tr>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <div class="avatar-sm" style="background:var(--brand-primary)">
                          <?= strtoupper(substr($e['full_name'], 0, 1)) ?>
                        </div>
                        <div>
                          <div class="fw-600"><?= htmlspecialchars($e['full_name']) ?></div>
                          <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($e['email']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td><?= htmlspecialchars($e['department'] ?? '—') ?></td>
                    <td><span class="badge <?= $e['role']==='admin'?'bg-primary':'bg-light text-secondary' ?>"><?= ucfirst($e['role']) ?></span></td>
                    <td>
                      <span class="badge <?= $e['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $e['is_active'] ? 'Active' : 'Inactive' ?>
                      </span>
                    </td>
                    <td>
                      <div class="d-flex gap-1">
                        <a href="?edit=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                          <i class="bi bi-pencil"></i>
                        </a>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="action"    value="toggle">
                          <input type="hidden" name="id"        value="<?= $e['id'] ?>">
                          <input type="hidden" name="is_active" value="<?= $e['is_active'] ?>">
                          <button class="btn btn-sm <?= $e['is_active']?'btn-outline-warning':'btn-outline-success' ?> py-0 px-2">
                            <i class="bi bi-<?= $e['is_active']?'pause':'play' ?>"></i>
                          </button>
                        </form>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id"     value="<?= $e['id'] ?>">
                          <button class="btn btn-sm btn-outline-danger py-0 px-2"
                            data-confirm="Delete <?= htmlspecialchars($e['full_name']) ?>? This cannot be undone.">
                            <i class="bi bi-trash"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /row -->
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
