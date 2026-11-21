<?php
require_once 'db.php';
require_once 'functions.php';
require_login();

$uid = (int)$_SESSION['user_id'];

$me = $pdo->prepare("SELECT is_mentor FROM users WHERE id = ?");
$me->execute([$uid]);
$isMentor = (bool)$me->fetchColumn();

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $mid    = (int)($_POST['id'] ?? 0);

    $s = $pdo->prepare("SELECT * FROM mentorships WHERE id = ? AND (mentor_id = ? OR mentee_id = ?)");
    $s->execute([$mid, $uid, $uid]);
    $row = $s->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $asMentor = ((int)$row['mentor_id'] === $uid);

        if ($action === 'accept' && $asMentor && $row['status'] === 'pending') {
            $c = $pdo->prepare("SELECT COUNT(*) FROM mentorships WHERE mentor_id = ? AND status = 'active'");
            $c->execute([$uid]);
            if ((int)$c->fetchColumn() >= MAX_MENTEES) {
                $_SESSION['flash_err'] = "You already have " . MAX_MENTEES . " active mentees.";
            } else {
                $pdo->prepare("UPDATE mentorships SET status = 'active' WHERE id = ?")->execute([$mid]);
                $_SESSION['flash'] = "Mentorship accepted.";
            }
        } elseif ($action === 'decline' && $asMentor && $row['status'] === 'pending') {
            $pdo->prepare("UPDATE mentorships SET status = 'declined' WHERE id = ?")->execute([$mid]);
            $_SESSION['flash'] = "Request declined.";
        } elseif ($action === 'cancel' && !$asMentor && $row['status'] === 'pending') {
            $pdo->prepare("DELETE FROM mentorships WHERE id = ?")->execute([$mid]);
            $_SESSION['flash'] = "Request cancelled.";
        } elseif ($action === 'end' && $row['status'] === 'active') {
            $pdo->prepare("UPDATE mentorships SET status = 'ended' WHERE id = ?")->execute([$mid]);
            $_SESSION['flash'] = "Mentorship ended.";
        }
    }
    header("Location: my_mentorship.php");
    exit;
}

/* ---------- My mentors (I am the mentee) ---------- */
$a = $pdo->prepare(
    "SELECT m.id, m.status, m.requested_at, u.full_name, u.email, u.phone, u.interests, u.profile_picture
     FROM mentorships m JOIN users u ON u.id = m.mentor_id
     WHERE m.mentee_id = ?
     ORDER BY FIELD(m.status, 'active', 'pending', 'declined', 'ended'), m.requested_at DESC"
);
$a->execute([$uid]);
$myMentors = $a->fetchAll(PDO::FETCH_ASSOC);

/* ---------- My mentees (I am the mentor) ---------- */
$myMentees = [];
if ($isMentor) {
    $b = $pdo->prepare(
        "SELECT m.id, m.status, m.requested_at, u.full_name, u.email, u.phone, u.interests, u.profile_picture
         FROM mentorships m JOIN users u ON u.id = m.mentee_id
         WHERE m.mentor_id = ?
         ORDER BY FIELD(m.status, 'pending', 'active', 'declined', 'ended'), m.requested_at DESC"
    );
    $b->execute([$uid]);
    $myMentees = $b->fetchAll(PDO::FETCH_ASSOC);
}

$flash = $_SESSION['flash'] ?? '';
$flashErr = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_err']);

$badges = ['active' => 'bg-success', 'pending' => 'bg-warning text-dark', 'declined' => 'bg-secondary', 'ended' => 'bg-secondary'];

function person_card(array $p, string $actions): void {
    $hasPic = $p['profile_picture'] && is_file(__DIR__ . '/' . $p['profile_picture']);
    ?>
    <div class="mm-card">
      <?php if ($hasPic): ?>
        <img class="mm-avatar" src="<?= e($p['profile_picture']) ?>" alt="">
      <?php else: ?>
        <div class="mm-initials"><?= e(initials($p['full_name'])) ?></div>
      <?php endif; ?>
      <div class="mm-main">
        <div class="mm-name"><?= e($p['full_name']) ?></div>
        <div class="mm-sub"><?= e($p['interests'] ?? '') ?></div>
        <?php if ($p['status'] === 'active'): ?>
          <div class="mm-contact">
            <i class="bi bi-envelope me-1"></i><?= e($p['email']) ?> &nbsp;
            <i class="bi bi-telephone me-1"></i><?= e($p['phone']) ?>
          </div>
        <?php endif; ?>
      </div>
      <?= $actions ?>
    </div>
    <?php
}

require 'header.php';
?>
<div class="tp-wrap">
  <div class="tp-label">Community</div>
  <h1 class="tp-title">My mentorship</h1>

  <?php if ($flash): ?><div class="alert alert-success py-2"><?= e($flash) ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="alert alert-danger py-2"><?= e($flashErr) ?></div><?php endif; ?>

  <?php if ($isMentor): ?>
    <div class="mm-section-title">Requests and mentees</div>
    <?php if (!$myMentees): ?>
      <div class="tp-card text-muted">No mentorship requests yet. Members can find you on the <a href="mentors.php">mentors page</a>.</div>
    <?php endif; ?>
    <?php foreach ($myMentees as $p):
        ob_start(); ?>
        <span class="badge <?= $badges[$p['status']] ?? 'bg-secondary' ?>"><?= e(ucfirst($p['status'])) ?></span>
        <?php if ($p['status'] === 'pending'): ?>
          <form method="post" class="d-inline"><input type="hidden" name="action" value="accept"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-azure btn-sm">Accept</button></form>
          <form method="post" class="d-inline"><input type="hidden" name="action" value="decline"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-outline-secondary btn-sm">Decline</button></form>
        <?php elseif ($p['status'] === 'active'): ?>
          <form method="post" class="d-inline" onsubmit="return confirm('End this mentorship?');"><input type="hidden" name="action" value="end"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-outline-danger btn-sm">End</button></form>
        <?php endif;
        person_card($p, ob_get_clean());
    endforeach; ?>
  <?php endif; ?>

  <div class="mm-section-title">My mentors</div>
  <?php if (!$myMentors): ?>
    <div class="tp-card text-muted">You have not requested a mentor yet. <a href="mentors.php">Find a mentor</a>.</div>
  <?php endif; ?>
  <?php foreach ($myMentors as $p):
      ob_start(); ?>
      <span class="badge <?= $badges[$p['status']] ?? 'bg-secondary' ?>"><?= e(ucfirst($p['status'])) ?></span>
      <?php if ($p['status'] === 'pending'): ?>
        <form method="post" class="d-inline"><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-outline-secondary btn-sm">Cancel request</button></form>
      <?php elseif ($p['status'] === 'active'): ?>
        <form method="post" class="d-inline" onsubmit="return confirm('End this mentorship?');"><input type="hidden" name="action" value="end"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-outline-danger btn-sm">End</button></form>
      <?php endif;
      person_card($p, ob_get_clean());
  endforeach; ?>

  <a href="mentors.php" class="btn btn-azure mt-3"><i class="bi bi-search me-1"></i>Find a mentor</a>
</div>
<?php require 'footer.php'; ?>