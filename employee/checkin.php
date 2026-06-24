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

// SINGLE SOURCE OF TRUTH for office fence — same data the API uses.
$office = get_office_location();

// Generate a daily QR token
$qrToken  = hash('sha256', 'qr_' . date('Y-m-d') . '_' . $uid . '_' . session_id());
$qrUrlIn  = BASE_URL . '/api/qr_scan.php?token=' . $qrToken . '&action=check_in';
$qrUrlOut = BASE_URL . '/api/qr_scan.php?token=' . $qrToken . '&action=check_out';

include __DIR__ . '/../includes/header.php';
?>
<div class="content-inner">

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

<?php $extraJs = "
// ── GPS detection ─────────────────────────────────────────────
// Office values are echoed from PHP get_office_location() — the SAME
// source the API uses, so the UI and the server can never disagree.
const OFFICE_LAT   = " . (float)$office['latitude']  . ";
const OFFICE_LNG   = " . (float)$office['longitude'] . ";
const OFFICE_RADIUS_M = " . (float)$office['radius_m'] . ";

// Server-side gate is radius + min(accuracy, 100m). Keep these in sync
// with includes/attendance.php::gps_gate().
const MAX_ACCURACY_BUFFER_M = 100;
const MAX_ACCEPTABLE_ACCURACY_M = 200;

let bestFix = null; // { lat, lng, accuracy, ts }

function haversineMeters(lat1, lng1, lat2, lng2) {
  const R = 6371000;
  const toRad = d => d * Math.PI / 180;
  const dLat = toRad(lat2 - lat1);
  const dLng = toRad(lng2 - lng1);
  const a = Math.sin(dLat/2)**2 +
            Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
            Math.sin(dLng/2)**2;
  return 2 * R * Math.asin(Math.sqrt(a));
}

function getOnePosition(opts) {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) return reject(new Error('Geolocation not supported by this browser.'));
    navigator.geolocation.getCurrentPosition(resolve, reject, opts);
  });
}

/**
 * Take up to N samples and return the one with the smallest accuracy circle.
 * Short-circuits as soon as we get a fix accurate to <= 30 m.
 */
async function getBestPosition({ samples = 4, perSampleTimeout = 8000 } = {}) {
  let best = null;
  for (let i = 0; i < samples; i++) {
    try {
      const pos = await getOnePosition({
        enableHighAccuracy: true,
        timeout: perSampleTimeout,
        maximumAge: 0,
      });
      const acc = pos.coords.accuracy || 9999;
      if (!best || acc < best.coords.accuracy) best = pos;
      if (acc <= 30) break; // good enough, stop polling
    } catch (e) {
      if (!best && i === samples - 1) throw e;
    }
  }
  if (!best) throw new Error('Could not obtain a GPS fix.');
  return best;
}

function renderPill(fix) {
  const pill   = document.getElementById('gpsPill');
  const distEl = document.getElementById('gpsDistance');
  const dist   = haversineMeters(fix.lat, fix.lng, OFFICE_LAT, OFFICE_LNG);
  const buffer = Math.min(fix.accuracy || 0, MAX_ACCURACY_BUFFER_M);
  const allowed = OFFICE_RADIUS_M + buffer;
  distEl.style.display = 'block';

  if ((fix.accuracy || 0) > MAX_ACCEPTABLE_ACCURACY_M) {
    pill.className = 'location-pill warn';
    pill.innerHTML = '<i class=\"bi bi-exclamation-triangle-fill\"></i> Weak GPS signal';
    distEl.textContent = 'GPS accuracy is ±' + Math.round(fix.accuracy) + ' m — too imprecise to verify. Move outside or near a window and retry.';
    distEl.style.color = '#92400E';
    return { dist, allowed, ok: false };
  }

  if (dist <= allowed) {
    pill.className = 'location-pill';
    pill.innerHTML = '<i class=\"bi bi-geo-alt-fill\"></i> At office location';
    distEl.textContent = '✓ ' + Math.round(dist) + ' m from office (±' + Math.round(fix.accuracy || 0) + ' m accuracy, allowed ' + Math.round(allowed) + ' m)';
    distEl.style.color = '#065F46';
    return { dist, allowed, ok: true };
  }

  pill.className = 'location-pill warn';
  pill.innerHTML = '<i class=\"bi bi-exclamation-triangle-fill\"></i> Outside office area';
  const distKm = (dist / 1000).toFixed(2);
  distEl.textContent = 'You are ' + distKm + ' km away — allowed ' + Math.round(allowed) + ' m (radius ' + OFFICE_RADIUS_M + ' m + ±' + Math.round(buffer) + ' m). Check-in will be rejected.';
  distEl.style.color = '#92400E';
  return { dist, allowed, ok: false };
}

function showGpsError(err) {
  const pill = document.getElementById('gpsPill');
  const distEl = document.getElementById('gpsDistance');
  pill.className = 'location-pill err';
  pill.innerHTML = '<i class=\"bi bi-exclamation-triangle-fill\"></i> Location unavailable';
  distEl.style.display = 'block';
  let msg = 'Enable location services to check in.';
  if (err && err.code === 1) msg = 'Location permission denied. Allow it in your browser settings and reload.';
  else if (err && err.code === 2) msg = 'GPS signal unavailable. Move outside or near a window and retry.';
  else if (err && err.code === 3) msg = 'Could not get GPS in time. Move outside or near a window and retry.';
  distEl.textContent = msg;
  distEl.style.color = '#991B1B';
}

async function detectGPS() {
  try {
    const pos = await getBestPosition({ samples: 3 });
    bestFix = {
      lat: pos.coords.latitude,
      lng: pos.coords.longitude,
      accuracy: pos.coords.accuracy,
      ts: Date.now(),
    };
    renderPill(bestFix);
  } catch (e) {
    bestFix = null;
    showGpsError(e);
  }
}
detectGPS();

async function doAction(action) {
  const btn = document.getElementById(action === 'check_in' ? 'btnCheckIn' : 'btnCheckOut');
  if (btn) { btn.disabled = true; }
  try {
    // Always re-acquire at click time so we send the freshest, most accurate fix.
    let fix;
    try {
      const pos = await getBestPosition({ samples: 4 });
      fix = {
        lat: pos.coords.latitude,
        lng: pos.coords.longitude,
        accuracy: pos.coords.accuracy,
      };
      bestFix = { ...fix, ts: Date.now() };
      renderPill(bestFix);
    } catch (geoErr) {
      showGpsError(geoErr);
      showToast('Could not get your GPS location. Please allow location access and try again.', 'danger');
      return;
    }

    if ((fix.accuracy || 0) > MAX_ACCEPTABLE_ACCURACY_M) {
      showToast('GPS too imprecise (±' + Math.round(fix.accuracy) + ' m). Move outside or near a window and retry.', 'danger');
      return;
    }

    const res = await fetch('" . BASE_URL . "/api/attendance.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        action,
        lat: fix.lat,
        lng: fix.lng,
        accuracy: fix.accuracy,
      })
    });
    const data = await res.json();
    if (data.ok) {
      const msg = action === 'check_in'
        ? 'Checked in successfully! (' + data.status + ')'
        : 'Checked out! ' + (data.hours || '') + 'h worked.';
      showToast(msg, 'success');
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast(data.msg, 'danger');
    }
  } finally {
    if (btn) { btn.disabled = false; }
  }
}

document.getElementById('btnCheckIn')?.addEventListener('click', () => doAction('check_in'));
document.getElementById('btnCheckOut')?.addEventListener('click', () => doAction('check_out'));

// QR toggle
let qrGenerated = false;
document.getElementById('toggleQR').addEventListener('click', function() {
  const sec = document.getElementById('qrSection');
  const showing = sec.style.display !== 'none';
  const next = showing ? 'none' : 'block';
  sec.style.setProperty('display', next, 'important');
  this.textContent = showing ? 'Show QR' : 'Hide QR';
  if (!showing && !qrGenerated) {
    generateQR('qrIn',  '" . $qrUrlIn . "');
    generateQR('qrOut', '" . $qrUrlOut . "');
    qrGenerated = true;
  }
});
"; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
