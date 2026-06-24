<?php
// employee/dashboard.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance.php';
require_login(BASE_URL . '/index.php');

$pageTitle = 'My Dashboard';
$activeNav = 'dash';
$uid       = current_user_id();
$today     = get_today_record($uid);
$month     = date('Y-m');
$stats     = get_summary_stats($uid, $month);
$recent    = get_attendance_for_user($uid, date('Y-m-01'), date('Y-m-d'));
$user      = current_user();

include __DIR__ . '/../includes/header.php';
?>
<div class="content-inner">
  <!-- Greeting -->
  <div class="mb-4">
    <h2 class="fw-700 mb-0">
      Good <?= (date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening')) ?>,
      <?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?> 👋
    </h2>
    <p class="text-muted mb-0" style="font-size:.875rem"><?= date('l, F j, Y') ?></p>
  </div>

  <!-- Today's status -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="stat-card">
        <div class="stat-icon <?= $today ? ($today['status'] === 'on_time' ? 'green' : 'amber') : 'blue' ?>">
          <i class="bi bi-clock-fill"></i>
        </div>
        <div>
          <div class="stat-value" style="font-size:1.2rem">
            <?= $today ? date('h:i A', strtotime($today['check_in_time'])) : '—' ?>
          </div>
          <div class="stat-label">Today's Check-In</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card">
        <div class="stat-icon <?= ($today && $today['check_out_time']) ? 'red' : 'purple' ?>">
          <i class="bi bi-door-open-fill"></i>
        </div>
        <div>
          <div class="stat-value" style="font-size:1.2rem">
            <?= ($today && $today['check_out_time']) ? date('h:i A', strtotime($today['check_out_time'])) : '—' ?>
          </div>
          <div class="stat-label">Today's Check-Out</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card">
        <div class="stat-icon green">
          <i class="bi bi-hourglass-split"></i>
        </div>
        <div>
          <div class="stat-value" style="font-size:1.2rem">
            <?= ($today && $today['work_hours']) ? $today['work_hours'] . 'h' : '—' ?>
          </div>
          <div class="stat-label">Hours Today</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Monthly stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-calendar2-check"></i></div>
        <div>
          <div class="stat-value"><?= $stats['total_days'] ?? 0 ?></div>
          <div class="stat-label">Days Present</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
        <div>
          <div class="stat-value"><?= $stats['on_time'] ?? 0 ?></div>
          <div class="stat-label">On Time</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-icon amber"><i class="bi bi-clock-history"></i></div>
        <div>
          <div class="stat-value"><?= $stats['late'] ?? 0 ?></div>
          <div class="stat-label">Late Arrivals</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-stopwatch"></i></div>
        <div>
          <div class="stat-value"><?= $stats['total_hours'] ?? 0 ?>h</div>
          <div class="stat-label">Total Hours (<?= date('M') ?>)</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <!-- Donut chart -->
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header">Attendance Breakdown – <?= date('F') ?></div>
        <div class="card-body d-flex flex-column align-items-center">
          <canvas id="donutChart" width="180" height="180"></canvas>
        </div>
      </div>
    </div>

    <!-- Recent records -->
    <div class="col-md-8">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          Recent Attendance
          <a href="<?= BASE_URL ?>/employee/history.php" class="btn btn-sm btn-outline-primary">View all</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Check In</th>
                  <th>Check Out</th>
                  <th>Hours</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recent)): ?>
                  <tr><td colspan="5" class="text-center text-muted py-4">No records this month.</td></tr>
                <?php else: ?>
                  <?php foreach (array_slice($recent, 0, 7) as $r): ?>
                  <tr>
                    <td class="fw-600"><?= date('D, M j', strtotime($r['attendance_date'])) ?></td>
                    <td><?= $r['check_in_time'] ? date('h:i A', strtotime($r['check_in_time'])) : '—' ?></td>
                    <td><?= $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '—' ?></td>
                    <td><?= $r['work_hours'] ?? '—' ?></td>
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
  </div>
</div>

<?php
$on_time  = (int)($stats['on_time']   ?? 0);
$late     = (int)($stats['late']      ?? 0);
$absent   = (int)($stats['absent']    ?? 0);
$half     = (int)($stats['half_day']  ?? 0);
$extraJs  = "
buildDonut('donutChart',
  ['On Time','Late','Absent','Half Day'],
  [{$on_time},{$late},{$absent},{$half}],
  ['#10B981','#F59E0B','#EF4444','#7C3AED']
);
";
include __DIR__ . '/../includes/footer.php';
?>
