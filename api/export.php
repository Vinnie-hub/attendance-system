<?php
// api/export.php – PDF (HTML-to-print) and Excel (CSV) export
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance.php';
require_login(BASE_URL . '/index.php');

$format = $_GET['format'] ?? 'pdf';
$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-t');
$search = $_GET['q']      ?? null;
$uid    = isset($_GET['uid']) ? (int)$_GET['uid'] : null;

// Employees can only export their own records
if (!is_admin() && $uid !== current_user_id()) {
    $uid = current_user_id();
}

$records = $uid
    ? get_attendance_for_user($uid, $from, $to)
    : get_all_attendance($from, $to, $search ?: null);

$title = 'Attendance Report ' . date('d M Y', strtotime($from)) . ' – ' . date('d M Y', strtotime($to));

// ── Excel / CSV ───────────────────────────────────────────────
if ($format === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Employee', 'Department', 'Date', 'Check In', 'Check Out', 'Work Hours', 'Status']);
    foreach ($records as $r) {
        fputcsv($out, [
            $r['full_name'],
            $r['department'] ?? '',
            $r['attendance_date'],
            $r['check_in_time']  ? date('h:i A', strtotime($r['check_in_time']))  : '',
            $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '',
            $r['work_hours']     ?? '',
            ucfirst(str_replace('_', ' ', $r['status'])),
        ]);
    }
    fclose($out);
    exit;
}

// ── PDF (printable HTML) ──────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title) ?></title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #111; padding: 24px; }
    h1   { font-size: 18px; margin-bottom: 4px; }
    p.sub{ font-size: 11px; color: #666; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #1e3a5f; color: #fff; padding: 7px 8px; text-align: left; font-size: 11px; }
    td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
    tr:nth-child(even) td { background: #f9fafb; }
    .badge { padding: 2px 7px; border-radius: 999px; font-size: 10px; font-weight: 700; }
    .on_time  { background: #D1FAE5; color: #065F46; }
    .late     { background: #FEF3C7; color: #92400E; }
    .absent   { background: #FEE2E2; color: #991B1B; }
    .half_day { background: #EDE9FE; color: #4C1D95; }
    .footer   { margin-top: 24px; font-size: 10px; color: #888; border-top: 1px solid #e5; padding-top: 8px; }
    @media print { .no-print { display: none; } }
  </style>
</head>
<body>
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px">
    <div>
      <h1><?= APP_NAME ?> – <?= htmlspecialchars($title) ?></h1>
      <p class="sub">Generated: <?= date('d M Y, h:i A') ?> | Records: <?= count($records) ?></p>
    </div>
    <button class="no-print" onclick="window.print()" style="padding:6px 16px;background:#2563EB;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px">
      🖨 Print / Save as PDF
    </button>
  </div>

  <table>
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
        <tr><td colspan="8" style="text-align:center;color:#888;padding:20px">No records found.</td></tr>
      <?php else: ?>
        <?php foreach ($records as $i => $r): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($r['full_name']) ?></td>
          <td><?= htmlspecialchars($r['department'] ?? '—') ?></td>
          <td><?= date('d M Y', strtotime($r['attendance_date'])) ?></td>
          <td><?= $r['check_in_time']  ? date('h:i A', strtotime($r['check_in_time']))  : '—' ?></td>
          <td><?= $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '—' ?></td>
          <td><?= $r['work_hours'] ?? '—' ?></td>
          <td><span class="badge <?= $r['status'] ?>"><?= ucfirst(str_replace('_', ' ', $r['status'])) ?></span></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="footer"><?= APP_NAME ?> v<?= APP_VERSION ?> &nbsp;|&nbsp; Exported by <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></div>
</body>
</html>
<?php exit; ?>
