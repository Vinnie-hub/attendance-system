<?php
// includes/header.php  – shared HTML head + navbar
// Expected vars: $pageTitle (string), $activeNav (string)
require_once __DIR__ . '/auth.php';
$user = current_user();
$role = $user['role'] ?? 'employee';
$fullName = $user['full_name'] ?? 'User';
$nameParts = array_filter(explode(' ', $fullName));
$initials = !empty($nameParts)
    ? implode('', array_map(fn($w) => strtoupper(mb_substr($w, 0, 1)), array_slice($nameParts, 0, 2)))
    : 'U';
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> – <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/app.css" rel="stylesheet">
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <span class="brand-icon"><i class="bi bi-clock-fill"></i></span>
    <span class="brand-text"><?= APP_NAME ?></span>
  </div>

  <nav class="sidebar-nav">
    <?php if ($role === 'admin'): ?>
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-link <?= ($activeNav??'')==='dash'?'active':'' ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
      <a href="<?= BASE_URL ?>/admin/employees.php" class="nav-link <?= ($activeNav??'')==='emp'?'active':'' ?>">
        <i class="bi bi-people-fill"></i> Employees
      </a>
      <a href="<?= BASE_URL ?>/admin/attendance.php" class="nav-link <?= ($activeNav??'')==='att'?'active':'' ?>">
        <i class="bi bi-calendar-check-fill"></i> Attendance
      </a>
      <a href="<?= BASE_URL ?>/admin/reports.php" class="nav-link <?= ($activeNav??'')==='rep'?'active':'' ?>">
        <i class="bi bi-bar-chart-fill"></i> Reports
      </a>
      <div class="nav-divider"></div>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/employee/dashboard.php" class="nav-link <?= ($activeNav??'')==='dash'?'active':'' ?>">
        <i class="bi bi-house-fill"></i> Dashboard
      </a>
      <a href="<?= BASE_URL ?>/employee/checkin.php" class="nav-link <?= ($activeNav??'')==='checkin'?'active':'' ?>">
        <i class="bi bi-check-circle-fill"></i> Check In / Out
      </a>
      <a href="<?= BASE_URL ?>/employee/history.php" class="nav-link <?= ($activeNav??'')==='hist'?'active':'' ?>">
        <i class="bi bi-clock-history"></i> My History
      </a>
      <div class="nav-divider"></div>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/api/logout.php" class="nav-link nav-link-danger">
      <i class="bi bi-box-arrow-left"></i> Sign Out
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="avatar-sm"><?= $initials ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($user['full_name'] ?? '') ?></div>
        <div class="user-role"><?= ucfirst($role) ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Top bar -->
<div class="topbar">
  <button class="sidebar-toggle" id="sidebarToggle">
    <i class="bi bi-list"></i>
  </button>
  <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? '') ?></div>
  <div class="topbar-right">
    <span class="live-clock" id="liveClock"></span>
  </div>
</div>

<!-- Main content wrapper -->
<main class="main-content">
