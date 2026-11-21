<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_admin();

$adminPage   = $adminPage   ?? '';
$pageTitle   = $pageTitle   ?? 'Admin';
$pageSub     = $pageSub     ?? '';
$pageActions = $pageActions ?? '';
$needCharts  = $needCharts  ?? false;

$adNav = [
    'overview'  => ['admin.php',           'bi-grid-1x2',        'Overview'],
    'users'     => ['admin_users.php',     'bi-people',          'Users'],
    'events'    => ['admin_events.php',    'bi-calendar-event',  'Events'],
    'resources' => ['admin_resources.php', 'bi-journal-medical', 'Resources'],
    'forum'     => ['admin_forum.php',     'bi-chat-dots',       'Forum'],
    'reports'   => ['admin_reports.php',   'bi-bar-chart-line',  'Reports'],
];

$meStmt = $pdo->prepare("SELECT full_name, profile_picture FROM users WHERE id = ?");
$meStmt->execute([$_SESSION['user_id']]);
$meRow = $meStmt->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => 'Admin', 'profile_picture' => null];

$flash    = $_SESSION['flash'] ?? '';
$flashErr = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_err']);

/* Small icon button that submits a POST form */
function ad_btn_form(string $action, array $hidden, string $icon, string $title, string $confirm = '', string $cls = ''): string {
    $h = csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '">';
    foreach ($hidden as $k => $v) {
        $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string)$v) . '">';
    }
    $on = $confirm !== '' ? ' onsubmit="return confirm(\'' . e(addslashes($confirm)) . '\');"' : '';
    return '<form method="post" class="d-inline"' . $on . '>' . $h
         . '<button type="submit" class="ad-ico ' . e($cls) . '" title="' . e($title) . '"><i class="bi ' . e($icon) . '"></i></button></form>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | Admin | Choithram Memorial Hospital</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
  <?php if ($needCharts): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
  <?php endif; ?>
</head>
<body class="ad-body">
<div class="ad-shell">

  <aside class="ad-side">
    <div class="ad-brand">
      <img src="logo.png" alt="Logo">
      <div>Choithram Memorial<small>Admin Panel</small></div>
    </div>

    <div class="ad-nav-label">Manage</div>
    <nav class="ad-nav">
      <?php foreach ($adNav as $key => [$href, $icon, $label]): ?>
        <a href="<?= e($href) ?>" class="<?= $adminPage === $key ? 'active' : '' ?>"><i class="bi <?= e($icon) ?>"></i><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="ad-user">
      <div class="ad-user-row">
        <img src="<?= e(avatar_url($meRow['profile_picture'])) ?>" alt="">
        <div>
          <div class="ad-user-name"><?= e($meRow['full_name']) ?></div>
          <div class="ad-user-role">Administrator</div>
        </div>
      </div>
      <div class="ad-user-links">
        <a href="dashboard.php"><i class="bi bi-box-arrow-up-right me-1"></i>View site</a>
        <a href="logout.php"><i class="bi bi-box-arrow-left me-1"></i>Sign out</a>
      </div>
    </div>
  </aside>

  <section class="ad-main">
    <div class="ad-top">
      <div>
        <h1><?= e($pageTitle) ?></h1>
        <?php if ($pageSub): ?><p><?= e($pageSub) ?></p><?php endif; ?>
      </div>
      <?php if ($pageActions): ?><div class="ad-top-actions ad-noprint"><?= $pageActions ?></div><?php endif; ?>
    </div>

    <?php if ($flash): ?><div class="ad-flash ok"><?= e($flash) ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="ad-flash err"><?= e($flashErr) ?></div><?php endif; ?>