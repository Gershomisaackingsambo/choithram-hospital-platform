<?php
require_once 'db.php';
require_once 'functions.php';
require_login();

$uid   = (int)$_SESSION['user_id'];
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$stmt = $pdo->prepare(
    "SELECT e.id, e.title, e.location, e.event_date, e.category, r.status
     FROM event_registrations r
     JOIN events e ON e.id = r.event_id
     WHERE r.user_id = ?
     ORDER BY e.event_date ASC"
);
$stmt->execute([$uid]);
$all = $stmt->fetchAll(PDO::FETCH_ASSOC);

$now = time();
$upcoming = $past = $cancelled = [];
foreach ($all as $row) {
    $future = strtotime($row['event_date']) >= $now;
    if ($row['status'] === 'cancelled') $cancelled[] = $row;
    elseif ($future)                    $upcoming[] = $row;
    else                                $past[] = $row;
}
$attended = count(array_filter($all, fn($r) => $r['status'] === 'attended'));

$tab = $_GET['tab'] ?? 'upcoming';
if (!in_array($tab, ['upcoming', 'past', 'cancelled'], true)) $tab = 'upcoming';
$list = $tab === 'past' ? array_reverse($past) : ($tab === 'cancelled' ? $cancelled : $upcoming);

require 'header.php';
?>
<div class="container py-4" style="max-width: 900px;">

  <?php if ($flash): ?>
    <div class="alert alert-success"><?= e($flash) ?></div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="text-azure mb-0">My events</h3>
    <a href="events.php" class="btn btn-azure btn-sm"><i class="bi bi-plus-circle me-1"></i>Find events</a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-4"><div class="card dash-card text-center p-3"><div class="fs-3 text-azure fw-bold"><?= count($upcoming) ?></div><small class="text-muted">Upcoming</small></div></div>
    <div class="col-4"><div class="card dash-card text-center p-3"><div class="fs-3 text-azure fw-bold"><?= $attended ?></div><small class="text-muted">Attended</small></div></div>
    <div class="col-4"><div class="card dash-card text-center p-3"><div class="fs-3 text-azure fw-bold"><?= count($cancelled) ?></div><small class="text-muted">Cancelled</small></div></div>
  </div>

  <ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab === 'upcoming' ? 'active' : '' ?>" href="my_events.php?tab=upcoming">Upcoming</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'past' ? 'active' : '' ?>" href="my_events.php?tab=past">Past</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'cancelled' ? 'active' : '' ?>" href="my_events.php?tab=cancelled">Cancelled</a></li>
  </ul>

  <?php if (!$list): ?>
    <div class="card dash-card p-4 text-muted">Nothing here yet.</div>
  <?php endif; ?>

  <?php foreach ($list as $row):
      $ts = strtotime($row['event_date']);
      $badge = ['registered' => 'bg-primary', 'attended' => 'bg-success', 'cancelled' => 'bg-secondary'][$row['status']] ?? 'bg-secondary';
  ?>
    <div class="card dash-card p-3 mb-3">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="text-center" style="min-width:64px;">
          <div class="fw-bold text-azure"><?= e(strtoupper(date('M j', $ts))) ?></div>
          <small class="text-muted"><?= e(date('Y', $ts)) ?></small>
        </div>
        <div class="flex-grow-1">
          <div class="fw-bold"><?= e($row['title']) ?></div>
          <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($row['location']) ?> &middot; <?= e(date('g:i A', $ts)) ?></small>
        </div>
        <span class="badge <?= $badge ?>"><?= e(ucfirst($row['status'])) ?></span>
        <?php if ($tab === 'upcoming'): ?>
          <a href="event_register.php?id=<?= (int)$row['id'] ?>" class="btn btn-outline-primary btn-sm">Manage</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php require 'footer.php'; ?>