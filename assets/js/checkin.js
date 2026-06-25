/* assets/js/checkin.js – GPS check-in/out logic */
'use strict';

// CFG is injected by the PHP view (employee/checkin.php)

// ── State ──────────────────────────────────────────────────────
let gpsWatchId = null;
let currentLat = null;
let currentLng = null;
let currentAccuracy = null;
let locationFixed = false;
let pendingAction = null;   // 'check_in' | 'check_out' | null
let processing = false;

// ── DOM refs ───────────────────────────────────────────────────
const $ = (id) => document.getElementById(id);
const btnCheckIn   = $('btnCheckIn');
const btnCheckOut  = $('btnCheckOut');
const gpsPill      = $('gpsPill');
const gpsDistance  = $('gpsDistance');
const officeName   = $('officeName');

// ── Start GPS acquisition ─────────────────────────────────────
function startGPS() {
  if (!navigator.geolocation) {
    updateGPSSpill('err', 'GPS not supported');
    return;
  }

  updateGPSSpill('warn', 'Detecting location\u2026');
  gpsWatchId = navigator.geolocation.watchPosition(
    onGPSPosition,
    onGPSError,
    { enableHighAccuracy: true, timeout: CFG.gpsTimeoutPhase1Ms, maximumAge: 5000 }
  );
}

function onGPSPosition(pos) {
  currentLat      = pos.coords.latitude;
  currentLng      = pos.coords.longitude;
  currentAccuracy = pos.coords.accuracy;
  locationFixed   = true;

  const dist = getDistanceFromOffice(currentLat, currentLng);

  // Update pill
  if (dist <= CFG.officeRadiusM + Math.min(currentAccuracy || 0, CFG.maxAccuracyBufferM)) {
    updateGPSSpill('ok', getAccuracyText(currentAccuracy));
  } else {
    updateGPSSpill('warn', getAccuracyText(currentAccuracy) + ' — ' + dist.toFixed(0) + 'm away');
  }

  // Show distance
  gpsDistance.style.display = 'block';
  gpsDistance.innerHTML = `<span class="text-muted">${dist.toFixed(0)}m from office  ·  ±${currentAccuracy ? Math.round(currentAccuracy) + 'm' : '?'}</span>`;
  gpsDistance.style.display = '';

  // Enable buttons
  enableButtons(true);

  // If we had a pending action queued before GPS fixed, fire it now
  if (pendingAction) {
    const action = pendingAction;
    pendingAction = null;
    performAction(action);
  }
}

function onGPSError(err) {
  console.warn('GPS error:', err.message);

  if (CFG.wifiFallbackEnabled) {
    // Try lower-accuracy / wifi fallback via phase 2
    if (gpsWatchId !== null) {
      navigator.geolocation.clearWatch(gpsWatchId);
      gpsWatchId = null;
    }

    updateGPSSpill('warn', 'GPS weak, trying Wi-Fi\u2026');

    // Fallback attempt with relaxed accuracy
    navigator.geolocation.getCurrentPosition(
      onGPSPosition,
      () => {
        // Fallback failed too — enable manual entry if allowed
        updateGPSSpill('warn', 'Location unavailable');
        const msg = CFG.adminApprovalRequired
          ? 'Location unavailable. Contact admin for an approval code.'
          : 'Location unavailable. Try enabling Wi-Fi or moving near a window.';
        gpsDistance.textContent = msg;
        gpsDistance.style.display = '';

        // Enable buttons anyway for WiFi/manual fallback
        enableButtons(true);

        // If a pending action exists, proceed with null coordinates
        if (pendingAction) {
          const action = pendingAction;
          pendingAction = null;
          performAction(action);
        }
      },
      { enableHighAccuracy: false, timeout: CFG.gpsTimeoutPhase2Ms, maximumAge: 30000 }
    );
  } else {
    updateGPSSpill('err', 'GPS unavailable');
    gpsDistance.textContent = 'Please enable location services and refresh.';
    gpsDistance.style.display = '';
    enableButtons(false);
  }
}

function getAccuracyText(accuracy) {
  if (accuracy === null || accuracy === undefined) return '±?m';
  return '±' + Math.round(accuracy) + 'm';
}

function updateGPSSpill(type, text) {
  if (!gpsPill) return;
  gpsPill.className = 'location-pill ' + type;
  gpsPill.innerHTML = '<i class="bi bi-geo-alt-fill"></i> ' + text;
}

function enableButtons(enabled) {
  if (btnCheckIn)  btnCheckIn.disabled  = !enabled || processing;
  if (btnCheckOut) btnCheckOut.disabled = !enabled || processing;
}

// ── Haversine (duplicated from includes/attendance.php for client-side) ──
function haversine(lat1, lng1, lat2, lng2) {
  const R = 6371000;
  const toRad = (deg) => deg * Math.PI / 180;
  const dLat = toRad(lat2 - lat1);
  const dLng = toRad(lng2 - lng1);
  const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng/2)**2;
  return R * 2 * Math.asin(Math.sqrt(a));
}

function getDistanceFromOffice(lat, lng) {
  return haversine(lat, lng, CFG.officeLat, CFG.officeLng);
}

// ── Check-in / Check-out API call ─────────────────────────────
function performAction(action) {
  if (processing) return;
  processing = true;
  enableButtons(false);

  // Determine method
  let method = 'gps';
  if (currentLat === null || currentLng === null) {
    method = CFG.wifiFallbackEnabled ? 'wifi' : 'manual';
  }

  // Check if admin approval is needed for non-GPS methods
  function doSubmit(token) {
    const payload = {
      action: action,
      lat: currentLat,
      lng: currentLng,
      accuracy: currentAccuracy,
      method: method,
    };
    if (token) payload.admin_override_token = token;

    // Show loading state
    const btn = action === 'check_in' ? btnCheckIn : btnCheckOut;
    if (btn) {
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Processing\u2026';
      btn.disabled = true;
    }

    fetch(CFG.baseUrl + '/api/attendance.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
      .then(r => r.json())
      .then(data => {
        processing = false;
        enableButtons(true);

        if (data.ok) {
          showToast(action === 'check_in' ? '✅ Checked in successfully!' : '✅ Checked out successfully!', 'success');
          // Reload after short delay to show updated state
          setTimeout(() => location.reload(), 1500);
        } else if (data.requires_admin_approval) {
          // Prompt for admin token
          promptForAdminToken(action);
        } else {
          showToast('❌ ' + (data.msg || 'Action failed.'), 'danger');
        }
      })
      .catch(err => {
        processing = false;
        enableButtons(true);
        showToast('❌ Network error: ' + err.message, 'danger');
      });
  }

  // If admin approval is required and we're using fallback method
  if (CFG.adminApprovalRequired && (method === 'wifi' || method === 'manual' || currentLat === null)) {
    // First check if there's already a pending token
    fetch(CFG.baseUrl + '/api/check_approval_status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: action }),
    })
      .then(r => r.json())
      .then(status => {
        if (status.has_pending) {
          // Token already generated by admin, use it
          // We need to get the token - we'll call the API without it first
          // and get rejected, which will prompt the admin flow. But better:
          // query what token is pending isn't possible, so let user know
          showToast('Admin approval pending. Enter the code provided by your admin.', 'info');
          promptForAdminToken(action);
        } else {
          // No token pending, try submitting - will get requires_admin_approval response
          doSubmit(null);
        }
      })
      .catch(() => doSubmit(null)); // Fall through to normal flow
  } else {
    doSubmit(null);
  }
}

// ── Admin token modal ─────────────────────────────────────────
function promptForAdminToken(action) {
  const token = prompt('Enter the admin approval code:');
  if (token && token.trim()) {
    performActionWithToken(action, token.trim());
  } else {
    processing = false;
    enableButtons(true);
    showToast('Admin approval code required. Please contact your admin.', 'warning');
    // Reset button text
    const btn = action === 'check_in' ? btnCheckIn : btnCheckOut;
    if (btn) {
      btn.innerHTML = action === 'check_in'
        ? '<i class="bi bi-check-circle me-2"></i> Check In Now'
        : '<i class="bi bi-box-arrow-right me-2"></i> Check Out Now';
      btn.disabled = false;
    }
  }
}

function performActionWithToken(action, token) {
  if (processing) return;

  const method = (currentLat === null || currentLng === null)
    ? (CFG.wifiFallbackEnabled ? 'wifi' : 'manual')
    : 'gps';

  const payload = {
    action: action,
    lat: currentLat,
    lng: currentLng,
    accuracy: currentAccuracy,
    method: method,
    admin_override_token: token,
  };

  const btn = action === 'check_in' ? btnCheckIn : btnCheckOut;
  if (btn) {
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Processing\u2026';
    btn.disabled = true;
  }

  fetch(CFG.baseUrl + '/api/attendance.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
    .then(r => r.json())
    .then(data => {
      processing = false;
      enableButtons(true);

      if (data.ok) {
        showToast(action === 'check_in' ? '✅ Checked in successfully!' : '✅ Checked out successfully!', 'success');
        setTimeout(() => location.reload(), 1500);
      } else {
        showToast('❌ ' + (data.msg || 'Action failed.'), 'danger');
      }
    })
    .catch(err => {
      processing = false;
      enableButtons(true);
      showToast('❌ Network error: ' + err.message, 'danger');
    });
}

// ── Button click handlers ─────────────────────────────────────
if (btnCheckIn) {
  btnCheckIn.addEventListener('click', () => {
    if (processing) return;
    if (!locationFixed && currentLat === null) {
      // GPS hasn't fixed yet, we'll queue the action
      updateGPSSpill('warn', 'Acquiring GPS\u2026');
      pendingAction = 'check_in';
      showToast('Acquiring location\u2026 please wait.', 'info');
      // If GPS watch isn't running, start it
      if (gpsWatchId === null) startGPS();
      return;
    }
    performAction('check_in');
  });
}

if (btnCheckOut) {
  btnCheckOut.addEventListener('click', () => {
    if (processing) return;
    if (!locationFixed && currentLat === null) {
      updateGPSSpill('warn', 'Acquiring GPS\u2026');
      pendingAction = 'check_out';
      showToast('Acquiring location\u2026 please wait.', 'info');
      if (gpsWatchId === null) startGPS();
      return;
    }
    performAction('check_out');
  });
}

// ── QR Code toggle ────────────────────────────────────────────
const toggleQR = $('toggleQR');
const qrSection = $('qrSection');

if (toggleQR && qrSection) {
  toggleQR.addEventListener('click', () => {
    const hidden = qrSection.style.display === 'none' || qrSection.style.display === '';
    qrSection.style.display = hidden ? 'block' : 'none';
    toggleQR.textContent = hidden ? 'Hide QR' : 'Show QR';

    if (hidden) {
      // Generate QR codes when first shown
      generateQR('qrIn', CFG.qrUrlIn);
      generateQR('qrOut', CFG.qrUrlOut);
    }
  });
}

// ── Initialize ─────────────────────────────────────────────────
(function init() {
  // Start GPS acquisition
  startGPS();
})();