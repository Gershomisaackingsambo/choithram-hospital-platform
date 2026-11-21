<?php
require_once 'db.php';
require_once 'functions.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$st->execute([$id]);
$event = $st->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    $_SESSION['flash_err'] = "That event no longer exists.";
    header("Location: admin_events.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['flash_err'] = "Your session expired. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'set_status') {
            $rid = (int)($_POST['rid'] ?? 0);
            $new = $_POST['status'] ?? '';
            if (in_array($new, ['attended', 'registered'], true)) {
                $pdo->prepare("UPDATE event_registrations SET status = ? WHERE id = ? AND event_id = ? AND status <> 'cancelled'")
                    ->execute([$new, $rid, $id]);
                $_SESSION['flash'] = $new === 'attended' ? "Marked as attended." : "Attendance removed.";
            }
        } elseif ($action === 'mark_all') {
            $n = $pdo->prepare("UPDATE event_registrations SET status = 'attended' WHERE event_id = ? AND status = 'registered'");
            $n->execute([$id]);
            $_SESSION['flash'] = $n->rowCount() . " people marked as attended.";
        }
    }
    header("Location: admin_event_attendees.php?id=$id");
    exit;
}

$rs = $pdo->prepare(
    "SELECT r.id AS rid, r.status, r.registered_at, r.notes, u.full_name, u.email, u.phone
     FROM event_registrations r JOIN users u ON u.id = r.user_id
     WHERE r.event_id = ?
     ORDER BY FIELD(r.status, 'attended', 'registered', 'cancelled'), u.full_name"
);
$rs->execute([$id]);
$rows = $rs->fetchAll(PDO::FETCH_ASSOC);

$nReg = $nAtt = $nCan = 0;
foreach ($rows as $r) {
    if ($r['status'] === 'attended')   $nAtt++;
    elseif ($r['status'] === 'registered') $nReg++;
    else $nCan++;
}
$active = $nReg + $nAtt;
$left   = max(0, (int)$event['capacity'] - $active);
$pill   = ['attended' => 'p-green', 'registered' => 'p-blue', 'cancelled' => 'p-grey'];

$adminPage   = 'events';
$pageTitle   = 'Attendees';
$pageSub     = $event['title'] . ' - ' . date('d M Y, g:i A', strtotime($event['event_date']));
$pageActions = '<a class="ad-btn light" href="admin_events.php"><i class="bi bi-arrow-left"></i> Back to events</a>'
             . '<button class="ad-btn light" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>';
require 'admin_header.php';
?>

<div class="ad-stats">
  <div class="ad-stat"><div class="ico"><i class="bi bi-person-check"></i></div><div><div class="num"><?= $active ?></div><div class="lbl">Registered</div></div></div>
  <div class="ad-stat"><div class="ico"><i class="bi bi-check2-all"></i></div><div><div class="num"><?= $nAtt ?></div><div class="lbl">Attended</div></div></div>
  <div class="ad-stat"><div class="ico"><i class="bi bi-x-circle"></i></div><div><div class="num"><?= $nCan ?></div><div class="lbl">Cancelled</div></div></div>
  <div class="ad-stat"><div class="ico"><i class="bi bi-people"></i></div><div><div class="num"><?= $left ?></div><div class="lbl">Places left of <?= (int)$event['capacity'] ?></div></div></div>
</div>

<div class="ad-card">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="mb-0">Registration list</h2>
    <?php if ($nReg > 0): ?>
      <form method="post" class="ad-noprint" onsubmit="return confirm('Mark everyone who is still only registered as attended?');">
        <?= csrf_field() ?><input type="hidden" name="action" value="mark_all">
        <button class="ad-btn" type="submit"><i class="bi bi-check2-all"></i> Mark all as attended</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!$rows): ?>
    <div class="ad-empty">Nobody has registered for this event yet.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="ad-table">
      <thead><tr><th>Member</th><th>Phone</th><th>Registered</th><th>Notes</th><th>Status</th><th class="ad-noprint">Action</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td>
            <div class="ad-person"><span class="ad-init"><?= e(initials($r['full_name'])) ?></span>
              <div><div class="fw-semibold"><?= e($r['full_name']) ?></div><div class="sub"><?= e($r['email']) ?></div></div></div>
          </td>
          <td><?= e($r['phone']) ?></td>
          <td class="sub"><?= e(date('d M Y', strtotime($r['registered_at']))) ?></td>
          <td class="sub"><?= e($r['notes'] ?? '') ?></td>
          <td><span class="ad-pill <?= $pill[$r['status']] ?? 'p-grey' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
          <td class="ad-noprint">
            <?php if ($r['status'] === 'registered'): ?>
              <?= ad_btn_form('set_status', ['rid' => $r['rid'], 'status' => 'attended'], 'bi-check2-circle', 'Mark attended') ?>
            <?php elseif ($r['status'] === 'attended'): ?>
              <?= ad_btn_form('set_status', ['rid' => $r['rid'], 'status' => 'registered'], 'bi-arrow-counterclockwise', 'Undo attendance') ?>
            <?php else: ?>
              <span class="sub">-</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php require 'admin_footer.php'; ?>