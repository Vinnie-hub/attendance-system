<?php
// admin/attendance.php – View & search all attendance records
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance.php';
require_admin(BASE_URL . '/index.php');

$pageTitle = 'Attendance Records';
$activeNav = 'att';

$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-t');
$search = trim($_GET['q'] ?? '');

$records = get_all_attendance($from, $to, $search ?: null);

$statusCounts = array_count_values(array_column($records, 'status'));

include __DIR__ . '/../includes/header.php';
?>
<div class="content-inner">

  <!-- Filter bar -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label">From</label>
          <input type="date" name="from" class="form-control" value="<?= $from ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">To</label>
          <input type="date" name="to" class="form-control" value="<?= $to ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Search by name or date</label>
          <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="e.g. Alice or 15/06/2025">
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>Search</button>
          <a href="?" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
        </div>
      </form>
    </div>
  </div>

  <!-- Summary pills -->
  <div class="d-flex gap-2 mb-4 flex-wrap">
    <span class="badge bg-light text-dark fs-6 border"><?= count($records) ?> Total Records</span>
    <span class="badge badge-on_time fs-6"><?= $statusCounts['on_time'] ?? 0 ?> On Time</span>
    <span class="badge badge-late fs-6"><?= $statusCounts['late'] ?? 0 ?> Late</span>
    <span class="badge badge-absent fs-6"><?= $statusCounts['absent'] ?? 0 ?> Absent</span>
    <span class="badge badge-half_day fs-6"><?= $statusCounts['half_day'] ?? 0 ?> Half Day</span>
    <div class="ms-auto d-flex gap-2">
      <a href="<?= BASE_URL ?>/api/export.php?from=<?= $from ?>&to=<?= $to ?>&q=<?= urlencode($search) ?>&format=excel"
         class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
      <a href="<?= BASE_URL ?>/api/export.php?from=<?= $from ?>&to=<?= $to ?>&q=<?= urlencode($search) ?>&format=pdf"
         class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf me-1"></i>PDF</a>
    </div>
  </div>

  <!-- Table -->
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 searchable-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Employee</th>
              <th>Department</th>
              <th>Date</th>
              <th>Check In</th>
              <th>Check Out</th>
              <th>Hours</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($records)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">No records found.</td></tr>
            <?php else: ?>
              <?php foreach ($records as $i => $r): ?>
              <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td>
                  <div class="fw-600"><?= htmlspecialchars($r['full_name']) ?></div>
                  <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($r['email']) ?></div>
                </td>
                <td><?= htmlspecialchars($r['department'] ?? '—') ?></td>
                <td><?= date('D, M j Y', strtotime($r['attendance_date'])) ?></td>
                <td><?= $r['check_in_time']  ? date('h:i A', strtotime($r['check_in_time']))  : '<span class="text-muted">—</span>' ?></td>
                <td><?= $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '<span class="text-muted">—</span>' ?></td>
                <td class="fw-600"><?= $r['work_hours'] ? $r['work_hours'] . 'h' : '—' ?></td>
                <td>
                  <span class="badge badge-<?= $r['status'] ?> rounded-pill px-2">
                    <?= ucfirst(str_replace('_', ' ', $r['status'])) ?>
                  </span>
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
<?php include __DIR__ . '/../includes/footer.php'; ?>
