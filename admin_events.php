<?php
require_once 'db.php';
require_once 'functions.php';
require_admin();

$back = 'admin_events.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['flash_err'] = "Your session expired. Please try again.";
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $t = $pdo->prepare("SELECT title FROM events WHERE id = ?");
        $t->execute([$id]);
        $title = $t->fetchColumn();
        if ($title !== false) {
            $pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$id]);
            $_SESSION['flash'] = '"' . $title . '" and its registrations were deleted.';
        }
    }
    header("Location: $back");
    exit;
}

$q    = trim($_GET['q'] ?? '');
$when = $_GET['when'] ?? 'all';
$cat  = $_GET['cat'] ?? '';

$where  = "1=1";
$params = [];
if ($q !== '') {
    $where .= " AND (e.title LIKE ? OR e.location LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($when === 'upcoming')  $where .= " AND e.event_date >= NOW()";
elseif ($when === 'past')  $where .= " AND e.event_date < NOW()";
else $when = 'all';
if ($cat !== '' && in_array($cat, interests_list(), true)) {
    $where .= " AND e.category = ?";
    $params[] = $cat;
} else {
    $cat = '';
}

$stmt = $pdo->prepare(
    "SELECT e.*,
       (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status IN ('registered','attended')) AS taken,
       (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status = 'attended') AS attended
     FROM events e
     WHERE $where
     ORDER BY e.event_date DESC"
);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$adminPage   = 'events';
$pageTitle   = 'Events';
$pageSub     = 'Workshops, screenings and awareness sessions';
$pageActions = '<a class="ad-btn" href="admin_event_form.php"><i class="bi bi-plus-lg"></i> Add event</a>';
require 'admin_header.php';
?>

<form method="get" class="ad-filter">
  <input class="ad-input" type="text" name="q" placeholder="Search events" value="<?= e($q) ?>">
  <div class="ad-chips">
    <a class="ad-chip <?= $when === 'all' ? 'active' : '' ?>" href="?when=all">All</a>
    <a class="ad-chip <?= $when === 'upcoming' ? 'active' : '' ?>" href="?when=upcoming">Upcoming</a>
    <a class="ad-chip <?= $when === 'past' ? 'active' : '' ?>" href="?when=past">Past</a>
  </div>
  <input type="hidden" name="when" value="<?= e($when) ?>">
  <select class="ad-input" name="cat" onchange="this.form.submit()">
    <option value="">All categories</option>
    <?php foreach (interests_list() as $c): ?>
      <option value="<?= e($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="ad-btn light" type="submit"><i class="bi bi-search"></i> Search</button>
</form>

<div class="ad-card">
  <?php if (!$events): ?>
    <div class="ad-empty">No events found. <a href="admin_event_form.php">Add one</a>.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="ad-table">
      <thead><tr><th>Event</th><th>Date</th><th>Location</th><th>Registered</th><th>Attended</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($events as $ev):
          $isPast = strtotime($ev['event_date']) < time();
          $pct = $ev['capacity'] > 0 ? min(100, round(($ev['taken'] / $ev['capacity']) * 100)) : 0;
          [$c1] = category_style($ev['category']);
      ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= e($ev['title']) ?></div>
            <div class="sub"><i class="bi bi-circle-fill" style="color: <?= e($c1) ?>; font-size: .5rem;"></i> <?= e($ev['category']) ?></div>
          </td>
          <td><?= e(date('d M Y', strtotime($ev['event_date']))) ?><div class="sub"><?= e(date('g:i A', strtotime($ev['event_date']))) ?></div></td>
          <td><?= e($ev['location']) ?></td>
          <td><?= (int)$ev['taken'] ?> / <?= (int)$ev['capacity'] ?><div class="ad-bar"><span style="width: <?= $pct ?>%"></span></div></td>
          <td><?= (int)$ev['attended'] ?></td>
          <td><span class="ad-pill <?= $isPast ? 'p-grey' : 'p-green' ?>"><?= $isPast ? 'Past' : 'Upcoming' ?></span></td>
          <td>
            <div class="ad-actions">
              <a class="ad-ico" href="admin_event_attendees.php?id=<?= (int)$ev['id'] ?>" title="Attendees"><i class="bi bi-people"></i></a>
              <a class="ad-ico" href="admin_event_form.php?id=<?= (int)$ev['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
              <?= ad_btn_form('delete', ['id' => $ev['id']], 'bi-trash', 'Delete', 'Delete this event and all its registrations? This cannot be undone.', 'danger') ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php require 'admin_footer.php'; ?>