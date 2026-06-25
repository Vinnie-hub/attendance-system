<?php
// employee/checkin.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance.php';
require_login(BASE_URL . '/index.php');

$pageTitle = 'Check In / Out';
$activeNav = 'checkin';
$uid       = current_user_id();
$today     = get_today_record($uid);
$office    = get_office_location();

// Generate a daily QR token (session-based, expires at midnight)
$qrToken  = hash('sha256', 'qr_' . date('Y-m-d') . '_' . $uid . '_' . session_id());
$qrUrlIn  = BASE_URL . '/api/qr_scan.php?token=' . $qrToken . '&action=check_in';
$qrUrlOut = BASE_URL . '/api/qr_scan.php?token=' . $qrToken . '&action=check_out';

// QR scan result (redirected back here from api/qr_scan.php)
$qrStatus = $_GET['qr']  ?? '';
$qrMsg    = $_GET['msg'] ?? '';

// Determine the Google Geolocation API endpoint for the frontend
$googleGeoApiUrl = BASE_URL . '/api/geo_location.php';

// PHP → JS config values (safe JSON encoding avoids any escaping issues)
$jsConfig = json_encode([
    'baseUrl'                => BASE_URL,
    'officeLat'              => (float)$office['latitude'],
    'officeLng'              => (float)$office['longitude'],
    'officeRadiusM'          => (float)$office['radius_m'],
    'maxAccuracyBufferM'     => (int)MAX_ACCURACY_BUFFER_M,
    'maxAcceptableAccuracyM' => (int)MAX_ACCEPTABLE_ACCURACY_M,
    'gpsTimeoutPhase1Ms'     => (int)GPS_TIMEOUT_PHASE1_MS,
    'gpsTimeoutPhase2Ms'     => (int)GPS_TIMEOUT_PHASE2_MS,
    'gpsRetryAttempts'       => (int)GPS_RETRY_ATTEMPTS,
    'wifiFallbackEnabled'    => (bool)WIFI_FALLBACK_ENABLED,
    'adminApprovalRequired'  => (bool)ADMIN_APPROVAL_REQUIRED_FOR_FALLBACK,
    'qrUrlIn'                => $qrUrlIn,
    'qrUrlOut'               => $qrUrlOut,
    // Google Geolocation API config
    'googleGeoApiUrl'        => $googleGeoApiUrl,
    'googleApiEnabled'       => defined('GOOGLE_GEO_API_ENABLED') && GOOGLE_GEO_API_ENABLED && defined('GOOGLE_API_KEY') && GOOGLE_API_KEY,
]);

include __DIR__ . '/../includes/header.php';
?>

<div class="content-inner">

  <?php if ($qrStatus === 'ok'): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
      <i class="bi bi-check-circle-fill"></i>
      <?= htmlspecialchars($qrMsg ?: 'QR attendance recorded successfully!') ?>
    </div>
  <?php elseif ($qrStatus === 'err'): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
      <i class="bi bi-x-circle-fill"></i>
      <?= htmlspecialchars($qrMsg ?: 'QR scan failed. Please try again.') ?>
    </div>
  <?php endif; ?>

  <!-- Big clock -->
  <div class="text-center mb-5 mt-2">
    <div class="clock-display" id="bigClock">00:00:00</div>
    <p class="text-muted mt-1 mb-0"><?= date('l, F j, Y') ?></p>
  </div>

  <!-- GPS status pill -->
  <div class="text-center mb-4">
    <span class="location-pill warn" id="gpsPill">
      <i class="bi bi-geo-alt-fill"></i> Detecting location…
    </span>
    <span class="text-muted d-block mt-1" style="font-size:.72rem" id="officeName">
      Office: <?= htmlspecialchars($office['name']) ?>
    </span>
    <div id="gpsDistance" class="text-muted mt-1" style="font-size:.72rem;display:none"></div>
  </div>

  <div class="row g-4 justify-content-center">

    <!-- Check In card -->
    <div class="col-md-5">
      <div class="check-action-card">
        <div class="mb-3">
          <div class="stat-icon green mx-auto mb-3" style="width:60px;height:60px;font-size:1.8rem">
            <i class="bi bi-box-arrow-in-right"></i>
          </div>
          <h4 class="fw-700 mb-1">Check In</h4>
          <?php if ($today): ?>
            <p class="text-muted mb-3">Checked in at <strong><?= date('h:i A', strtotime($today['check_in_time'])) ?></strong></p>
            <span class="badge badge-<?= $today['status'] ?> rounded-pill px-3 py-2 fs-6">
              <?= ucfirst(str_replace('_', ' ', $today['status'])) ?>
            </span>
          <?php else: ?>
            <p class="text-muted mb-3">Work starts at <strong><?= WORK_START ?></strong> — grace period: <?= GRACE_MINUTES ?> min</p>
            <button class="btn btn-success btn-lg px-4 fw-600" id="btnCheckIn">
              <i class="bi bi-check-circle me-2"></i> Check In Now
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Check Out card -->
    <div class="col-md-5">
      <div class="check-action-card">
        <div class="mb-3">
          <div class="stat-icon red mx-auto mb-3" style="width:60px;height:60px;font-size:1.8rem">
            <i class="bi bi-box-arrow-right"></i>
          </div>
          <h4 class="fw-700 mb-1">Check Out</h4>
          <?php if ($today && $today['check_out_time']): ?>
            <p class="text-muted mb-3">Checked out at <strong><?= date('h:i A', strtotime($today['check_out_time'])) ?></strong></p>
            <p class="fw-600 text-success">
              <i class="bi bi-hourglass-split me-1"></i>
              <?= $today['work_hours'] ?> hours worked today
            </p>
          <?php elseif ($today): ?>
            <p class="text-muted mb-3">Remember to check out when you leave.</p>
            <button class="btn btn-danger btn-lg px-4 fw-600" id="btnCheckOut">
              <i class="bi bi-box-arrow-right me-2"></i> Check Out Now
            </button>
          <?php else: ?>
            <p class="text-muted mb-3">Please check in first.</p>
            <button class="btn btn-secondary btn-lg px-4 fw-600" disabled>Check Out</button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- QR Code option -->
    <div class="col-md-10">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-qr-code me-2"></i>QR Code Attendance</span>
          <button class="btn btn-sm btn-outline-secondary" id="toggleQR">Show QR</button>
        </div>
        <div class="card-body text-center" id="qrSection" style="display:none!important">
          <p class="text-muted mb-3" style="font-size:.875rem">Scan the QR code with the admin scanner or a mobile device.</p>
          <div class="row justify-content-center g-4">
            <div class="col-auto">
              <p class="fw-600 mb-2 text-success"><i class="bi bi-check-circle me-1"></i>Check In QR</p>
              <div id="qrIn"></div>
            </div>
            <div class="col-auto">
              <p class="fw-600 mb-2 text-danger"><i class="bi bi-x-circle me-1"></i>Check Out QR</p>
              <div id="qrOut"></div>
            </div>
          </div>
          <p class="text-muted mt-3" style="font-size:.75rem">QR codes expire daily at midnight.</p>
        </div>
      </div>
    </div>

  </div><!-- /row -->
</div>

<?php
// Load the independent geo-location service module
$extraScripts = [BASE_URL . '/assets/js/geo-location-service.js'];

// Pass all PHP config to JS via a single safe JSON object — no manual escaping needed
$extraJs = 'const CFG = ' . $jsConfig . ';' . file_get_contents(__DIR__ . '/../assets/js/checkin.js');
include __DIR__ . '/../includes/footer.php';
?>
