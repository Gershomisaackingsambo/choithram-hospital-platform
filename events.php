<?php
require_once 'db.php';
require_once 'functions.php';
require_login();

$uid     = (int)$_SESSION['user_id'];
$perPage = 5;
$cat     = $_GET['cat'] ?? 'All';
$page    = max(1, (int)($_GET['page'] ?? 1));

$cats = $pdo->query("SELECT DISTINCT category FROM events WHERE event_date >= NOW() ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
if ($cat !== 'All' && !in_array($cat, $cats, true)) $cat = 'All';

$where  = "e.event_date >= NOW()";
$params = [];
if ($cat !== 'All') {
    $where   .= " AND e.category = ?";
    $params[] = $cat;
}

$cnt = $pdo->prepare("SELECT COUNT(*) FROM events e WHERE $where");
$cnt->execute($params);
$total  = (int)$cnt->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT e.*,
          (SELECT COUNT(*) FROM event_registrations r
            WHERE r.event_id = e.id AND r.status IN ('registered','attended')) AS taken,
          (SELECT r2.status FROM event_registrations r2
            WHERE r2.event_id = e.id AND r2.user_id = ?) AS my_status
        FROM events e
        WHERE $where
        ORDER BY e.event_date ASC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$uid], $params));
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

require 'header.php';
?>
<section class="ev-stage">
  <div class="ev-card">

    <div class="ev-photo">
      <img src="images/doctor1.jpg" alt="Hospital staff">
    </div>

    <div class="ev-panel">
      <div class="ev-head">
        <div class="ev-badge">CMH</div>
        <div>
          <div class="ev-title-1">upcoming</div>
          <div class="ev-title-2">events</div>
        </div>
        <a href="my_events.php" class="ev-mine"><i class="bi bi-calendar-check me-1"></i>My events</a>
      </div>

      <div class="ev-tabs">
        <a href="events.php" class="<?= $cat === 'All' ? 'active' : '' ?>">All</a>
        <?php foreach ($cats as $c): ?>
          <a href="events.php?cat=<?= urlencode($c) ?>" class="<?= $cat === $c ? 'active' : '' ?>"><?= e($c) ?></a>
        <?php endforeach; ?>
      </div>

      <?php if (!$events): ?>
        <div class="ev-empty">No upcoming events in this category right now. Please check back soon.</div>
      <?php endif; ?>

      <?php foreach ($events as $ev):
          $ts   = strtotime($ev['event_date']);
          $full = (int)$ev['taken'] >= (int)$ev['capacity'];
          $mine = in_array($ev['my_status'], ['registered', 'attended'], true);
      ?>
        <div class="ev-row">
          <div class="ev-date">
            <strong><?= e(strtoupper(date('M j', $ts))) ?></strong>
            <span><?= e(date('Y', $ts)) ?></span>
          </div>
          <div class="ev-info">
            <strong><?= e(strtoupper($ev['title'])) ?></strong>
            <span><?= e($ev['location']) ?> &middot; <?= e(date('g:i A', $ts)) ?></span>
          </div>
          <div class="ev-action">
            <?php if ($mine): ?>
              <a class="ev-btn ev-btn-done" href="event_register.php?id=<?= (int)$ev['id'] ?>">REGISTERED</a>
            <?php elseif ($full): ?>
              <span class="ev-soldout">FULL</span>
            <?php else: ?>
              <a class="ev-btn" href="event_register.php?id=<?= (int)$ev['id'] ?>">REGISTER</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if ($pages > 1): ?>
        <div class="ev-pages">
          <?php for ($p = 1; $p <= $pages; $p++): ?>
            <a href="events.php?cat=<?= urlencode($cat) ?>&amp;page=<?= $p ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</section>
<?php require 'footer.php'; ?>