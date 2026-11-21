<?php
require_once 'db.php';
require_once 'functions.php';
require_login();

$uid = (int)$_SESSION['user_id'];
$cat = $_GET['cat'] ?? 'All';
if ($cat !== 'All' && !in_array($cat, interests_list(), true)) $cat = 'All';

/* ---------- Send a mentorship request ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request') {
    $mid = (int)($_POST['mentor_id'] ?? 0);

    $m = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ? AND is_mentor = 1 AND status = 'active'");
    $m->execute([$mid]);
    $mentor = $m->fetch(PDO::FETCH_ASSOC);

    if (!$mentor || $mid === $uid) {
        $_SESSION['flash_err'] = "You cannot send a request to that mentor.";
    } else {
        $c = $pdo->prepare("SELECT COUNT(*) FROM mentorships WHERE mentor_id = ? AND status = 'active'");
        $c->execute([$mid]);
        $activeCount = (int)$c->fetchColumn();

        $x = $pdo->prepare("SELECT * FROM mentorships WHERE mentor_id = ? AND mentee_id = ?");
        $x->execute([$mid, $uid]);
        $existing = $x->fetch(PDO::FETCH_ASSOC);

        if ($existing && in_array($existing['status'], ['pending', 'active'], true)) {
            $_SESSION['flash_err'] = "You already have a request or mentorship with " . $mentor['full_name'] . ".";
        } elseif ($activeCount >= MAX_MENTEES) {
            $_SESSION['flash_err'] = $mentor['full_name'] . " has no free places right now.";
        } elseif ($existing) {
            $pdo->prepare("UPDATE mentorships SET status = 'pending', requested_at = NOW() WHERE id = ?")->execute([$existing['id']]);
            $_SESSION['flash'] = "Request sent to " . $mentor['full_name'] . ".";
        } else {
            $pdo->prepare("INSERT INTO mentorships (mentor_id, mentee_id, status) VALUES (?, ?, 'pending')")->execute([$mid, $uid]);
            $_SESSION['flash'] = "Request sent to " . $mentor['full_name'] . ".";
        }
    }
    header("Location: mentors.php" . ($cat !== 'All' ? '?cat=' . urlencode($cat) : ''));
    exit;
}

/* ---------- List mentors ---------- */
$where  = "u.is_mentor = 1 AND u.status = 'active' AND u.id <> ?";
$params = [$uid];
if ($cat !== 'All') {
    $where   .= " AND u.interests LIKE ?";
    $params[] = "%$cat%";
}

$sql = "SELECT u.id, u.full_name, u.interests, u.bio, u.profile_picture,
          (SELECT COUNT(*) FROM mentorships m WHERE m.mentor_id = u.id AND m.status = 'active') AS active_mentees,
          (SELECT m2.status FROM mentorships m2 WHERE m2.mentor_id = u.id AND m2.mentee_id = ?) AS my_status
        FROM users u
        WHERE $where
        ORDER BY u.full_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$uid], $params));
$mentors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flash = $_SESSION['flash'] ?? '';
$flashErr = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_err']);

$palette = [['#0B5ED7', '#4db8ff'], ['#c2417a', '#f78fb3'], ['#2e8b57', '#8fd694'], ['#6f42c1', '#b79df0']];

require 'header.php';
?>
<section class="mt-stage">
  <div class="mt-wrap">

    <h1 class="mt-title">Meet our health mentors</h1>
    <p class="mt-sub">Experienced health workers and community members who are here to guide you. Send a request and start learning together.</p>

    <?php if ($flash): ?><div class="mt-flash ok"><?= e($flash) ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="mt-flash err"><?= e($flashErr) ?></div><?php endif; ?>

    <div class="mt-pills">
      <a class="mt-pill <?= $cat === 'All' ? 'active' : '' ?>" href="mentors.php">All</a>
      <?php foreach (interests_list() as $c): ?>
        <a class="mt-pill <?= $cat === $c ? 'active' : '' ?>" href="mentors.php?cat=<?= urlencode($c) ?>"><?= e($c) ?></a>
      <?php endforeach; ?>
    </div>

    <?php if (!$mentors): ?>
      <div class="mt-empty">No mentors available in this category yet.</div>
    <?php endif; ?>

    <div class="mt-grid">
      <?php foreach ($mentors as $m):
          $pal   = $palette[$m['id'] % count($palette)];
          $hasPic = $m['profile_picture'] && is_file(__DIR__ . '/' . $m['profile_picture']);
          $full  = (int)$m['active_mentees'] >= MAX_MENTEES;
          $specs = array_slice(parse_interests($m['interests']), 0, 2);
          $bg    = $hasPic
              ? "background-image: url('" . e($m['profile_picture']) . "');"
              : "background: linear-gradient(160deg, {$pal[0]}, {$pal[1]});";
      ?>
        <div class="mt-card" style="<?= $bg ?>">
          <?php if (!$hasPic): ?><div class="mt-initials"><?= e(initials($m['full_name'])) ?></div><?php endif; ?>
          <div class="mt-shade"></div>
          <span class="mt-badge <?= $full ? 'full' : '' ?>"><?= $full ? 'Full' : 'Available' ?></span>
          <div class="mt-info">
            <h3><?= e($m['full_name']) ?></h3>
            <div class="mt-role"><?= e(implode(' · ', $specs)) ?></div>
            <?php if ($m['bio']): ?><div class="mt-bio"><?= e(mb_strimwidth($m['bio'], 0, 80, '...')) ?></div><?php endif; ?>

            <?php if ($m['my_status'] === 'active'): ?>
              <a class="mt-btn" href="my_mentorship.php">Your mentor</a>
            <?php elseif ($m['my_status'] === 'pending'): ?>
              <span class="mt-btn off">Request pending</span>
            <?php elseif ($full): ?>
              <span class="mt-btn off">No places left</span>
            <?php else: ?>
              <form method="post">
                <input type="hidden" name="action" value="request">
                <input type="hidden" name="mentor_id" value="<?= (int)$m['id'] ?>">
                <button type="submit" class="mt-btn">Request mentor</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="mt-how-title">How mentorship works</div>
    <div class="mt-how">
      <div class="mt-feature">
        <div class="ico"><i class="bi bi-search"></i></div>
        <h4>Choose a mentor</h4>
        <p>Browse mentors by topic and pick someone whose experience matches what you want to learn.</p>
      </div>
      <div class="mt-feature">
        <div class="ico"><i class="bi bi-send"></i></div>
        <h4>Send a request</h4>
        <p>The mentor reviews your request and accepts if they have a free place. Mentors can support up to <?= MAX_MENTEES ?> members at a time.</p>
      </div>
      <div class="mt-feature">
        <div class="ico"><i class="bi bi-chat-heart"></i></div>
        <h4>Learn together</h4>
        <p>Once accepted, you can see each other's contact details on the My mentorship page.</p>
      </div>
    </div>

  </div>
</section>
<?php require 'footer.php'; ?>