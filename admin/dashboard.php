<?php
// admin/dashboard.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance.php';
require_admin(BASE_URL . '/index.php');

$pageTitle = 'Admin Dashboard';
$activeNav = 'dash';

$db = DB::get();

// KPI counts
$totalEmp     = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='employee' AND is_active=1")->fetchColumn();
$presentToday = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE attendance_date=CURDATE()")->fetchColumn();
$onTimeToday  = (int)$db->query("SELECT COUNT(*) FROM attendance WHERE attendance_date=CURDATE() AND status='on_time'")->fetchColumn();
$lateToday    = (int)$db->query("SELECT COUNT(*) FROM attendance WHERE attendance_date=CURDATE() AND status='late'")->fetchColumn();
$absentToday  = $totalEmp - $presentToday;

// Last 7 days chart
$days   = [];
$counts = [];
for ($i = 6; $i >= 0; $i--) {
    $dt       = date('Y-m-d', strtotime("-$i days"));
    $days[]   = date('D', strtotime($dt));
    $stmt     = db_query("SELECT COUNT(*) FROM attendance WHERE attendance_date = ?", [$dt]);
    $counts[] = (int)$stmt->fetchColumn();
}

// Today's log
$todayRecords = $db->query(
    "SELECT a.*, u.full_name, u.department FROM attendance a
     JOIN users u ON u.id = a.user_id
     WHERE a.attendance_date = CURDATE()
     ORDER BY a.check_in_time DESC"
)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="content-inner">

  <!-- KPIs -->
  <div class="row g-3 mb-4">
    <?php foreach ([
      [$totalEmp,    'Total Employees', 'blue',   'people-fill'],
      [$presentToday,'Present Today',   'green',  'check-circle-fill'],
      [$lateToday,   'Late Today',      'amber',  'clock-history'],
      [$absentToday, 'Absent Today',    'red',    'person-x-fill'],
    ] as [$val,$lbl,$clr,$ic]): ?>
    <div class="col-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-icon <?= $clr ?>"><i class="bi bi-<?= $ic ?>"></i></div>
        <div>
          <div class="stat-value"><?= $val ?></div>
          <div class="stat-label"><?= $lbl ?></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="row g-3 mb-4">
    <!-- Bar chart: last 7 days -->
    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-header">Attendance – Last 7 Days</div>
        <div class="card-body">
          <canvas id="barChart" height="200"></canvas>
        </div>
      </div>
    </div>

    <!-- Today donut -->
    <div class="col-lg-5">
      <div class="card h-100">
        <div class="card-header">Today's Breakdown</div>
        <div class="card-body d-flex align-items-center justify-content-center">
          <canvas id="todayDonut" width="180" height="180"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Today's live attendance -->
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      Today's Attendance Log
      <a href="<?= BASE_URL ?>/admin/attendance.php" class="btn btn-sm btn-outline-primary">Full Report</a>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Department</th>
              <th>Check In</th>
              <th>Check Out</th>
              <th>Hours</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($todayRecords)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No attendance recorded yet today.</td></tr>
            <?php else: ?>
              <?php foreach ($todayRecords as $r): ?>
              <tr>
                <td class="fw-600"><?= htmlspecialchars($r['full_name']) ?></td>
                <td><span class="badge bg-light text-secondary"><?= htmlspecialchars($r['department'] ?? '—') ?></span></td>
                <td><?= $r['check_in_time'] ? date('h:i A', strtotime($r['check_in_time'])) : '—' ?></td>
                <td><?= $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '<span class="text-muted">Still in</span>' ?></td>
                <td><?= $r['work_hours'] ? $r['work_hours'] . 'h' : '—' ?></td>
                <td>
                  <span class="badge badge-<?= $r['status'] ?> rounded-pill px-2 py-1">
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

<?php
$daysJs   = json_encode($days);
$countsJs = json_encode($counts);
$extraJs  = "
buildBar('barChart', $daysJs, [{
  label: 'Present',
  data: $countsJs,
  backgroundColor: '#2563EB',
  borderRadius: 6
}]);
buildDonut('todayDonut',
  ['Present','Late','Absent'],
  [$presentToday, $lateToday, $absentToday],
  ['#10B981','#F59E0B','#EF4444']
);
";
include __DIR__ . '/../includes/footer.php';
?>
