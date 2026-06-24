<?php
// employee/history.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance.php';
require_login(BASE_URL . '/index.php');

$pageTitle = 'Attendance History';
$activeNav = 'hist';
$uid       = current_user_id();

$from  = $_GET['from']  ?? date('Y-m-01');
$to    = $_GET['to']    ?? date('Y-m-t');
$month = substr($from, 0, 7);

$records = get_attendance_for_user($uid, $from, $to);
$stats   = get_summary_stats($uid, $month);

include __DIR__ . '/../includes/header.php';
?>
<div class="content-inner">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="fw-700 mb-0">Attendance History</h3>
    <a href="<?= BASE_URL ?>/api/export.php?uid=<?= $uid ?>&from=<?= $from ?>&to=<?= $to ?>&format=pdf"
       class="btn btn-outline-danger btn-sm">
      <i class="bi bi-file-pdf me-1"></i> Export PDF
    </a>
  </div>

  <!-- Filter -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label">From</label>
          <input type="date" name="from" class="form-control" value="<?= $from ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">To</label>
          <input type="date" name="to" class="form-control" value="<?= $to ?>">
        </div>
        <div class="col-md-4">
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-funnel me-1"></i> Filter
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Stats row -->
  <div class="row g-3 mb-4">
    <?php foreach ([
      ['total_days','Days Logged','blue','calendar2-check'],
      ['on_time','On Time','green','check-circle'],
      ['late','Late','amber','clock-history'],
      ['absent','Absent','red','x-circle'],
      ['total_hours','Total Hours','purple','hourglass-split'],
    ] as [$key,$label,$color,$icon]): ?>
    <div class="col-6 col-md">
      <div class="stat-card">
        <div class="stat-icon <?= $color ?>"><i class="bi bi-<?= $icon ?>"></i></div>
        <div>
          <div class="stat-value"><?= $stats[$key] ?? 0 ?><?= $key==='total_hours'?'h':'' ?></div>
          <div class="stat-label"><?= $label ?></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Records table -->
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      Records
      <span class="badge bg-secondary"><?= count($records) ?> entries</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Date</th>
              <th>Check In</th>
              <th>Check Out</th>
              <th>Work Hours</th>
              <th>Status</th>
              <th>Location</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($records)): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">No records found for the selected range.</td></tr>
            <?php else: ?>
              <?php foreach ($records as $i => $r): ?>
              <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td class="fw-600"><?= date('D, M j Y', strtotime($r['attendance_date'])) ?></td>
                <td><?= $r['check_in_time'] ? date('h:i A', strtotime($r['check_in_time'])) : '<span class="text-muted">—</span>' ?></td>
                <td><?= $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '<span class="text-muted">—</span>' ?></td>
                <td>
                  <?php if ($r['work_hours']): ?>
                    <span class="fw-600 <?= $r['work_hours'] >= 8 ? 'text-success' : 'text-warning' ?>">
                      <?= $r['work_hours'] ?>h
                    </span>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                  <span class="badge badge-<?= $r['status'] ?> rounded-pill px-2 py-1">
                    <?= ucfirst(str_replace('_', ' ', $r['status'])) ?>
                  </span>
                </td>
                <td>
                  <?php if ($r['check_in_lat']): ?>
                    <a href="https://maps.google.com/?q=<?= $r['check_in_lat'] ?>,<?= $r['check_in_lng'] ?>"
                       target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-1" style="font-size:.7rem">
                      <i class="bi bi-geo-alt"></i>
                    </a>
                  <?php else: ?>—<?php endif; ?>
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
