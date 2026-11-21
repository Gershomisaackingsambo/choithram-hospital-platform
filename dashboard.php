<?php
require_once 'db.php';
require_once 'functions.php';
require_login();

$uid = (int)$_SESSION['user_id'];

$st = $pdo->prepare("SELECT id, full_name, interests, is_mentor, role, profile_picture FROM users WHERE id = ?");
$st->execute([$uid]);
$user = $st->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$interests = parse_interests($user['interests']);
$isMatch   = fn(string $cat): bool => in_array($cat, $interests, true);
$firstName = explode(' ', trim($user['full_name']))[0];

/* ---------- Resources and learning progress ---------- */
$rs = $pdo->prepare(
    "SELECT r.id, r.title, r.category, r.type,
       (SELECT COUNT(*) FROM resource_progress p WHERE p.resource_id = r.id AND p.user_id = ?) AS done
     FROM resources r
     ORDER BY r.created_at DESC, r.id DESC
     LIMIT 300"
);
$rs->execute([$uid]);
$allRes = $rs->fetchAll(PDO::FETCH_ASSOC);

$totalRes = count($allRes);
$doneRes  = count(array_filter($allRes, fn($r) => (int)$r['done'] > 0));
$pct      = $totalRes > 0 ? (int)round($doneRes / $totalRes * 100) : 0;

$byCat = [];
foreach ($allRes as $r) {
    $c = $r['category'];
    if (!isset($byCat[$c])) $byCat[$c] = ['t' => 0, 'd' => 0];
    $byCat[$c]['t']++;
    if ((int)$r['done'] > 0) $byCat[$c]['d']++;
}

$list = $allRes;
usort($list, function ($a, $b) use ($isMatch) {
    $sa = ((int)$a['done'] > 0 ? 2 : 0) + ($isMatch($a['category']) ? 0 : 1);
    $sb = ((int)$b['done'] > 0 ? 2 : 0) + ($isMatch($b['category']) ? 0 : 1);
    return $sa <=> $sb;
});
$list = array_slice($list, 0, 6);
$next = null;
foreach ($list as $r) {
    if ((int)$r['done'] === 0) { $next = $r; break; }
}

$cardCats = $interests ? array_slice($interests, 0, 5) : array_slice(interests_list(), 0, 5);

/* ---------- Events ---------- */
$ev = $pdo->prepare(
    "SELECT e.id, e.title, e.category, e.location, e.event_date, e.capacity,
       (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status IN ('registered','attended')) AS taken,
       (SELECT r2.status FROM event_registrations r2 WHERE r2.event_id = e.id AND r2.user_id = ?) AS my_status
     FROM events e
     WHERE e.event_date >= NOW()
     ORDER BY e.event_date ASC
     LIMIT 40"
);
$ev->execute([$uid]);
$events = $ev->fetchAll(PDO::FETCH_ASSOC);
usort($events, function ($a, $b) use ($isMatch) {
    $ra = in_array($a['my_status'], ['registered', 'attended'], true);
    $rb = in_array($b['my_status'], ['registered', 'attended'], true);
    $sa = $ra ? 0 : ($isMatch($a['category']) ? 1 : 2);
    $sb = $rb ? 0 : ($isMatch($b['category']) ? 1 : 2);
    $c = $sa <=> $sb;
    return $c !== 0 ? $c : strcmp($a['event_date'], $b['event_date']);
});
$events = array_slice($events, 0, 4);

$q = $pdo->prepare(
    "SELECT COUNT(*) FROM event_registrations r JOIN events e ON e.id = r.event_id
     WHERE r.user_id = ? AND r.status = 'registered' AND e.event_date >= NOW()"
);
$q->execute([$uid]);
$myUpcoming = (int)$q->fetchColumn();

/* ---------- Week strip ---------- */
$monday    = strtotime('monday this week');
$weekStart = date('Y-m-d 00:00:00', $monday);
$weekEnd   = date('Y-m-d 23:59:59', strtotime('+6 days', $monday));
$wk = $pdo->prepare(
    "SELECT DISTINCT DATE(e.event_date) FROM event_registrations r JOIN events e ON e.id = r.event_id
     WHERE r.user_id = ? AND r.status IN ('registered','attended') AND e.event_date BETWEEN ? AND ?"
);
$wk->execute([$uid, $weekStart, $weekEnd]);
$eventDays = array_flip($wk->fetchAll(PDO::FETCH_COLUMN));

/* ---------- Mentors ---------- */
$mn = $pdo->prepare(
    "SELECT u.id, u.full_name, u.interests, u.profile_picture,
       (SELECT COUNT(*) FROM mentorships m WHERE m.mentor_id = u.id AND m.status = 'active') AS active_n,
       (SELECT m2.status FROM mentorships m2 WHERE m2.mentor_id = u.id AND m2.mentee_id = ?) AS my_status
     FROM users u
     WHERE u.is_mentor = 1 AND u.status = 'active' AND u.id <> ?
     ORDER BY u.full_name ASC
     LIMIT 40"
);
$mn->execute([$uid, $uid]);
$mentors = $mn->fetchAll(PDO::FETCH_ASSOC);
usort($mentors, function ($a, $b) use ($interests) {
    $ma = count(array_intersect(parse_interests($a['interests']), $interests));
    $mb = count(array_intersect(parse_interests($b['interests']), $interests));
    return $mb <=> $ma;
});
$mentors = array_slice($mentors, 0, 3);

$pendingReq = 0;
if ($user['is_mentor']) {
    $p = $pdo->prepare("SELECT COUNT(*) FROM mentorships WHERE mentor_id = ? AND status = 'pending'");
    $p->execute([$uid]);
    $pendingReq = (int)$p->fetchColumn();
}

/* ---------- Greeting ---------- */
$hour     = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$left     = $totalRes - $doneRes;
$summary  = "You have $myUpcoming upcoming event" . ($myUpcoming === 1 ? '' : 's')
          . " and $left resource" . ($left === 1 ? '' : 's') . " left to complete.";
if ($pendingReq > 0) {
    $summary .= " $pendingReq mentorship request" . ($pendingReq === 1 ? ' is' : 's are') . " waiting for you.";
}
$roleLabel = $user['role'] === 'admin' ? 'Administrator' : ($user['is_mentor'] ? 'Mentor' : 'Member');

$dotFor = function (array $r) use ($isMatch): array {
    if ((int)$r['done'] > 0)        return ['dot-done', 'Completed'];
    if ($isMatch($r['category']))   return ['dot-rec', 'Recommended'];
    return ['dot-new', 'Not started'];
};

require 'header.php';
?>
<div class="db-stage">
  <div class="db-shell">

    <!-- Left menu -->
    <aside class="db-nav">
      <div class="db-brand"><img src="logo.png" alt=""><div>My Health<small>Member area</small></div></div>
      <a class="active" href="dashboard.php"><i class="bi bi-grid-1x2"></i>Dashboard</a>
      <a href="events.php"><i class="bi bi-calendar-event"></i>Events</a>
      <a href="my_events.php"><i class="bi bi-calendar-check"></i>My events</a>
      <a href="resources.php"><i class="bi bi-journal-medical"></i>Resources</a>
      <a href="forum.php"><i class="bi bi-chat-dots"></i>Forum</a>
      <a href="mentors.php"><i class="bi bi-people"></i>Mentors</a>
      <a href="my_mentorship.php"><i class="bi bi-person-hearts"></i>My mentorship</a>
      <a href="profile.php"><i class="bi bi-person-circle"></i>Profile</a>
      <?php if ($user['role'] === 'admin'): ?><a href="admin.php"><i class="bi bi-shield-lock"></i>Admin panel</a><?php endif; ?>
      <div class="spacer"></div>
      <a href="logout.php"><i class="bi bi-box-arrow-left"></i>Logout</a>
    </aside>

    <!-- Centre -->
    <section class="db-main">
      <div class="db-top">
        <form class="db-search" method="get" action="resources.php">
          <i class="bi bi-search"></i>
          <input type="text" name="q" placeholder="Search health resources">
        </form>
        <a class="db-btn" href="events.php">Browse events <i class="bi bi-chevron-down"></i></a>
      </div>

      <div class="db-banner">
        <div>
          <h2><?= e($greeting) ?>, <?= e($firstName) ?>!</h2>
          <p><?= e($summary) ?></p>
          <div class="db-tip"><i class="bi bi-lightbulb-fill me-1" style="color:#ffd54f"></i><strong>Health tip:</strong> <?= e(tip_of_the_day()) ?></div>
          <a class="db-btn light small" href="<?= $next ? 'resource.php?id=' . (int)$next['id'] : 'resources.php' ?>">Continue learning</a>
        </div>
        <img class="db-hero" src="images/doctor1.jpg" alt="">
      </div>

      <div class="db-head">
        <h3>Your health interests</h3>
        <a class="db-btn small" href="profile_edit.php">Edit</a>
      </div>
      <div class="db-cards">
        <?php foreach ($cardCats as $c):
            [$c1, $c2, $icon] = category_style($c);
            $t = $byCat[$c]['t'] ?? 0;
            $d = $byCat[$c]['d'] ?? 0;
        ?>
          <a class="db-card" href="resources.php?category=<?= urlencode($c) ?>">
            <span class="db-tile" style="background: linear-gradient(135deg, <?= e($c1) ?>, <?= e($c2) ?>);"><i class="bi <?= e($icon) ?>"></i></span>
            <div class="t"><?= e($c) ?></div>
            <div class="s"><?= $d ?> of <?= $t ?> completed</div>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="db-head">
        <h3>Learning progress</h3>
        <a class="db-btn small" href="resources.php">View all</a>
      </div>
      <div class="db-panel">
        <div class="db-ringrow">
          <div class="db-ring" style="--p: <?= $pct ?>"><span><?= $pct ?>%</span></div>
          <div>
            <div class="big"><?= $doneRes ?> of <?= $totalRes ?> resources completed</div>
            <div class="sub">Resources in your interests are shown first.</div>
          </div>
        </div>

        <?php if (!$list): ?>
          <div class="db-empty">No resources yet. Check back soon.</div>
        <?php else: ?>
        <table class="db-table">
          <thead><tr><th>Resource</th><th>Category</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($list as $r): [$cls, $label] = $dotFor($r); ?>
            <tr class="<?= ($next && $next['id'] === $r['id']) ? 'next' : '' ?>" onclick="location.href='resource.php?id=<?= (int)$r['id'] ?>'">
              <td><a href="resource.php?id=<?= (int)$r['id'] ?>"><?= e(mb_strimwidth($r['title'], 0, 38, '...')) ?></a></td>
              <td><?= e($r['category']) ?></td>
              <td><span class="db-dot <?= $cls ?>"></span><?= e($label) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </section>

    <!-- Right column -->
    <aside class="db-right">
      <a class="db-user" href="profile.php">
        <div><div class="n"><?= e($user['full_name']) ?></div><div class="r"><?= e($roleLabel) ?></div></div>
        <img src="<?= e(avatar_url($user['profile_picture'])) ?>" alt="">
      </a>

      <div class="db-sec">Your week <span class="db-month"><?= e(date('F Y')) ?></span></div>
      <div class="db-week">
        <?php for ($i = 0; $i < 7; $i++):
            $ts  = strtotime("+$i days", $monday);
            $key = date('Y-m-d', $ts);
        ?>
          <div class="db-day <?= $key === date('Y-m-d') ? 'today' : '' ?> <?= isset($eventDays[$key]) ? 'has' : '' ?>">
            <span class="w"><?= e(date('D', $ts)) ?></span><span class="d"><?= e(date('j', $ts)) ?></span>
          </div>
        <?php endfor; ?>
      </div>
      <div class="db-legend"><i class="bi bi-circle-fill"></i> A day you have an event</div>

      <div class="db-sec">Upcoming events <a href="events.php">View all</a></div>
      <div class="db-list">
        <?php if (!$events): ?><div class="db-empty">No upcoming events right now.</div><?php endif; ?>
        <?php foreach ($events as $x):
            [$c1, $c2, $icon] = category_style($x['category']);
            $mine = in_array($x['my_status'], ['registered', 'attended'], true);
            $ts   = strtotime($x['event_date']);
        ?>
          <div class="db-item">
            <span class="db-ic" style="background: linear-gradient(135deg, <?= e($c1) ?>, <?= e($c2) ?>);"><i class="bi <?= e($icon) ?>"></i></span>
            <div class="i">
              <div class="a"><a href="event_register.php?id=<?= (int)$x['id'] ?>"><?= e(mb_strimwidth($x['title'], 0, 34, '...')) ?></a></div>
              <div class="b"><?= e(date('D j M, g:i A', $ts)) ?></div>
            </div>
            <a class="db-go <?= $mine ? 'ok' : '' ?>" href="event_register.php?id=<?= (int)$x['id'] ?>" title="<?= $mine ? 'You are registered' : 'Register' ?>">
              <i class="bi <?= $mine ? 'bi-check-lg' : 'bi-arrow-right' ?>"></i>
            </a>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="db-sec">Mentors for you <a href="mentors.php">View all</a></div>
      <?php if (!$mentors): ?>
        <div class="db-empty">No mentors available yet.</div>
      <?php else: ?>
      <div class="db-mentors">
        <?php foreach ($mentors as $m):
            $hasPic = $m['profile_picture'] && is_file(__DIR__ . '/' . $m['profile_picture']);
            $full   = (int)$m['active_n'] >= MAX_MENTEES;
            $spec   = parse_interests($m['interests'])[0] ?? 'Mentor';
            if ($m['my_status'] === 'active')       { $btn = 'Your mentor'; $off = true; }
            elseif ($m['my_status'] === 'pending')  { $btn = 'Pending';     $off = true; }
            elseif ($full)                          { $btn = 'Full';        $off = true; }
            else                                    { $btn = 'Request';     $off = false; }
        ?>
          <a class="db-mentor" href="mentors.php">
            <?php if ($hasPic): ?>
              <img class="av" src="<?= e($m['profile_picture']) ?>" alt="">
            <?php else: ?>
              <div class="av"><?= e(initials($m['full_name'])) ?></div>
            <?php endif; ?>
            <div class="n"><?= e(explode(' ', $m['full_name'])[0]) ?></div>
            <div class="s"><?= e(mb_strimwidth($spec, 0, 14, '..')) ?></div>
            <span class="b <?= $off ? 'off' : '' ?>"><?= e($btn) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </aside>

  </div>
</div>
<?php require 'footer.php'; ?>