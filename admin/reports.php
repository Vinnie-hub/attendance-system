<?php
// admin/reports.php – analytics & report generation
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance.php';
require_admin(BASE_URL . '/index.php');

$pageTitle = 'Attendance Reports';
$activeNav = 'rep';
$db        = DB::get();

$month     = $_GET['month'] ?? date('Y-m');
$from      = $month . '-01';
$to        = date('Y-m-t', strtotime($from));
$monthName = date('F Y', strtotime($from));

// Per-employee summary for the month
$empStats = $db->query(
    "SELECT u.id, u.full_name, u.department,
        COUNT(a.id)                          AS days_present,
        SUM(a.status='on_time')              AS on_time,
        SUM(a.status='late')                 AS late,
        SUM(a.status='absent')               AS absent,
        SUM(a.status='half_day')             AS half_day,
        ROUND(AVG(a.work_hours),2)           AS avg_hours,
        ROUND(SUM(a.work_hours),2)           AS total_hours
     FROM users u
     LEFT JOIN attendance a ON a.user_id = u.id AND a.attendance_date BETWEEN '$from' AND '$to'
     WHERE u.role='employee' AND u.is_active=1
     GROUP BY u.id
     ORDER BY u.full_name"
)->fetchAll();

// Daily totals for bar chart
$dailyRows = $db->query(
    "SELECT attendance_date, COUNT(*) AS cnt,
        SUM(status='on_time') AS on_time, SUM(status='late') AS late
     FROM attendance WHERE attendance_date BETWEEN '$from' AND '$to'
     GROUP BY attendance_date ORDER BY attendance_date"
)->fetchAll();

$chartLabels = $chartPresent = $chartLate = [];
foreach ($dailyRows as $d) {
    $chartLabels[]  = date('d', strtotime($d['attendance_date']));
    $chartPresent[] = $d['on_time'];
    $chartLate[]    = $d['late'];
}

include __DIR__ . '/../includes/header.php';
?>
<div class="content-inner">

  <!-- Month picker + export -->
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <form method="get" class="d-flex align-items-center gap-2">
      <label class="form-label mb-0 fw-600">Month:</label>
      <input type="month" name="month" class="form-control" value="<?= $month ?>" style="width:160px">
      <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-right"></i></button>
    </form>
    <div class="d-flex gap-2">
      <a href="<?= BASE_URL ?>/api/export.php?from=<?= $from ?>&to=<?= $to ?>&format=excel"
         class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
      <a href="<?= BASE_URL ?>/api/export.php?from=<?= $from ?>&to=<?= $to ?>&format=pdf"
         class="btn btn-outline-danger btn-sm"><i class="bi bi-file-pdf me-1"></i>Export PDF</a>
    </div>
  </div>

  <h5 class="fw-700 mb-3"><?= $monthName ?> Report</h5>

  <!-- Daily chart -->
  <div class="card mb-4">
    <div class="card-header">Daily Attendance – <?= $monthName ?></div>
    <div class="card-body">
      <canvas id="dailyBar" height="160"></canvas>
    </div>
  </div>

  <!-- Per-employee table -->
  <div class="card">
    <div class="card-header">Employee Summary</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Dept</th>
              <th>Present</th>
              <th>On Time</th>
              <th>Late</th>
              <th>Absent</th>
              <th>Half Day</th>
              <th>Avg Hrs</th>
              <th>Total Hrs</th>
              <th>Rate</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($empStats)): ?>
              <tr><td colspan="10" class="text-center text-muted py-4">No data for this month.</td></tr>
            <?php else: ?>
              <?php foreach ($empStats as $e):
                $workDays  = 22; // approximate working days in a month
                $rate      = $e['days_present'] > 0 ? round(($e['days_present']/$workDays)*100, 0) : 0;
                $rateColor = $rate >= 90 ? 'success' : ($rate >= 75 ? 'warning' : 'danger');
              ?>
              <tr>
                <td class="fw-600"><?= htmlspecialchars($e['full_name']) ?></td>
                <td><?= htmlspecialchars($e['department'] ?? '—') ?></td>
                <td><?= $e['days_present'] ?></td>
                <td class="text-success fw-600"><?= $e['on_time'] ?? 0 ?></td>
                <td class="text-warning fw-600"><?= $e['late'] ?? 0 ?></td>
                <td class="text-danger fw-600"><?= $e['absent'] ?? 0 ?></td>
                <td><?= $e['half_day'] ?? 0 ?></td>
                <td><?= $e['avg_hours'] ?? '—' ?></td>
                <td><?= $e['total_hours'] ?? '—' ?></td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-fill" style="height:6px;width:70px">
                      <div class="progress-bar bg-<?= $rateColor ?>" style="width:<?= $rate ?>%"></div>
                    </div>
                    <span class="text-<?= $rateColor ?> fw-600" style="font-size:.8rem"><?= $rate ?>%</span>
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

<?php
$labelsJs  = json_encode($chartLabels);
$presentJs = json_encode($chartPresent);
$lateJs    = json_encode($chartLate);
$extraJs   = "
buildBar('dailyBar', $labelsJs, [
  { label:'On Time', data:$presentJs, backgroundColor:'#10B981', borderRadius:4 },
  { label:'Late',    data:$lateJs,    backgroundColor:'#F59E0B', borderRadius:4 }
]);
";
include __DIR__ . '/../includes/footer.php';
?>
