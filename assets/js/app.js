/* assets/js/app.js */
'use strict';

// ── Live clock ───────────────────────────────────────────────
(function liveClock() {
  const el = document.getElementById('liveClock');
  if (!el) return;
  const tick = () => {
    el.textContent = new Date().toLocaleTimeString('en-KE', {
      hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
  };
  tick();
  setInterval(tick, 1000);
})();

// Also update any .live-clock-big elements (check-in page)
(function bigClock() {
  const el = document.getElementById('bigClock');
  if (!el) return;
  const tick = () => {
    el.textContent = new Date().toLocaleTimeString('en-KE', {
      hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
  };
  tick();
  setInterval(tick, 1000);
})();

// ── Sidebar toggle ───────────────────────────────────────────
(function sidebarSetup() {
  const sidebar = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('sidebarToggle');
  if (!sidebar) return;

  // Create backdrop element
  const backdrop = document.createElement('div');
  backdrop.className = 'sidebar-backdrop';
  backdrop.id = 'sidebarBackdrop';
  document.body.appendChild(backdrop);

  // Toggle sidebar + backdrop
  function toggleSidebar() {
    sidebar.classList.toggle('open');
    backdrop.classList.toggle('show');
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    backdrop.classList.remove('show');
  }

  toggleBtn?.addEventListener('click', toggleSidebar);

  // Click backdrop closes sidebar
  backdrop.addEventListener('click', closeSidebar);

  // Click outside sidebar on mobile closes it (backup)
  document.addEventListener('click', (e) => {
    if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && !toggleBtn?.contains(e.target)) {
      closeSidebar();
    }
  });
})();

// ── Toast notifications ──────────────────────────────────────
window.showToast = function(msg, type = 'success') {
  const container = document.getElementById('toastContainer') || (() => {
    const c = document.createElement('div');
    c.id = 'toastContainer';
    c.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(c);
    return c;
  })();

  const icons = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
  const icon  = icons[type] || icons.info;

  const el = document.createElement('div');
  el.className = `toast align-items-center text-bg-${type} border-0 show`;
  el.setAttribute('role', 'alert');
  el.innerHTML = `
    <div class="d-flex">
      <div class="toast-body d-flex align-items-center gap-2">
        <i class="bi ${icon}"></i> ${msg}
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>`;
  container.appendChild(el);
  setTimeout(() => el.remove(), 4000);
};

// ── GPS helpers ──────────────────────────────────────────────
window.getPosition = function() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) return reject(new Error('Geolocation not supported'));
    navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 10000 });
  });
};

// ── QR code generator ────────────────────────────────────────
window.generateQR = function(targetId, text, size = 180) {
  const el = document.getElementById(targetId);
  if (!el || !text) return;
  el.innerHTML = '';
  // eslint-disable-next-line no-undef
  new QRCode(el, { text, width: size, height: size, correctLevel: QRCode.CorrectLevel.H });
};

// ── Chart helpers ────────────────────────────────────────────
window.buildDonut = function(canvasId, labels, data, colors) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  return new Chart(ctx, {
    type: 'doughnut',
    data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
    options: {
      cutout: '72%',
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 12 } } } }
    }
  });
};

window.buildBar = function(canvasId, labels, datasets) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  return new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true,
      scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, grid: { color: '#F1F5F9' } }
      },
      plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 12 } } } }
    }
  });
};

// ── Table search ─────────────────────────────────────────────
const searchInput = document.getElementById('tableSearch');
if (searchInput) {
  searchInput.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.searchable-table tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ── Delete confirm ───────────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(btn => {
  btn.addEventListener('click', (e) => {
    if (!confirm(btn.dataset.confirm || 'Are you sure?')) e.preventDefault();
  });
});

// ── Delete All confirm ───────────────────────────────────────
window.confirmDeleteAll = function() {
  return confirm('⚠️ Are you sure you want to delete ALL employees? This cannot be undone.');
};
