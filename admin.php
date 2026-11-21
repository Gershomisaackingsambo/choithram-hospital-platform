<?php
require_once 'db.php';
require_once 'functions.php';
require_admin();

$count = fn(string $sql): int => (int)$pdo->query($sql)->fetchColumn();
$pairs = fn(string $sql): array => $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);

$members     = $count("SELECT COUNT(*) FROM users WHERE role = 'member'");
$membersWeek = $count("SELECT COUNT(*) FROM users WHERE role = 'member' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$eventsUp    = $count("SELECT COUNT(*) FROM events WHERE event_date >= NOW()");
$regsWeek    = $count("SELECT COUNT(*) FROM event_registrations WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$resources   = $count("SELECT COUNT(*) FROM resources");
$doneWeek    = $count("SELECT COUNT(*) FROM resource_progress WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$topics      = $count("SELECT COUNT(*) FROM forum_topics");
$topicsWeek  = $count("SELECT COUNT(*) FROM forum_topics WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

$catEvents = $pairs("SELECT category, COUNT(*) FROM events WHERE event_date >= NOW() GROUP BY category");
$catRes    = $pairs("SELECT category, COUNT(*) FROM resources GROUP BY category");
$catTopics = $pairs("SELECT category, COUNT(*) FROM forum_topics GROUP BY category");

/* Last 30 days chart */
$to     = date('Y-m-d');
$from   = date('Y-m-d', strtotime('-29 days'));
$labels = date_range_labels($from, $to);
$shown  = array_map(fn($l) => date('d M', strtotime($l)), $labels);
$sSign  = series($labels, daily_counts($pdo, 'users', 'created_at', $from, $to));
$sReg   = series($labels, daily_counts($pdo, 'event_registrations', 'registered_at', $from, $to));
$sDone  = series($labels, daily_counts($pdo, 'resource_progress', 'completed_at', $from, $to));

$cc = $pdo->query(
    "SELECT r.category, COUNT(p.id) AS c
     FROM resources r LEFT JOIN resource_progress p ON p.resource_id = r.id
     GROUP BY r.category ORDER BY c DESC"
)->fetchAll(PDO::FETCH_ASSOC);

/* Recent activity */
$activity = [];
$add = function (string $kind, string $sql) use ($pdo, &$activity) {
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $r['kind'] = $kind;
        $activity[] = $r;
    }
};
$add('Joined',     "SELECT full_name AS who, 'Created an account' AS detail, created_at AS at FROM users ORDER BY created_at DESC LIMIT 8");
$add('Registered', "SELECT u.full_name AS who, e.title AS detail, r.registered_at AS at FROM event_registrations r JOIN users u ON u.id = r.user_id JOIN events e ON e.id = r.event_id ORDER BY r.registered_at DESC LIMIT 8");
$add('Completed',  "SELECT u.full_name AS who, rs.title AS detail, p.completed_at AS at FROM resource_progress p JOIN users u ON u.id = p.user_id JOIN resources rs ON rs.id = p.resource_id ORDER BY p.completed_at DESC LIMIT 8");
$add('Posted',     "SELECT u.full_name AS who, t.title AS detail, t.created_at AS at FROM forum_topics t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC LIMIT 8");
usort($activity, fn($a, $b) => strcmp($b['at'], $a['at']));
$activity = array_slice($activity, 0, 8);
$kindPill = ['Joined' => 'p-blue', 'Registered' => 'p-green', 'Completed' => 'p-purple', 'Posted' => 'p-orange'];

$adminPage   = 'overview';
$pageTitle   = 'Overview';
$pageSub     = 'Members, events, resources and community at a glance';
$pageActions = '<a class="ad-btn light" href="admin_reports.php"><i class="bi bi-bar-chart-line"></i> Reports</a>'
             . '<a class="ad-btn" href="admin_event_form.php"><i class="bi bi-plus-lg"></i> Add event</a>';
$needCharts  = true;
require 'admin_header.php';
?>

<div class="ad-stats">
  <div class="ad-stat"><div class="ico"><i class="bi bi-people"></i></div>
    <div><div class="num"><?= $members ?></div><div class="lbl">Members</div><div class="delta">+<?= $membersWeek ?> this week</div></div></div>
  <div class="ad-stat"><div class="ico"><i class="bi bi-calendar-event"></i></div>
    <div><div class="num"><?= $eventsUp ?></div><div class="lbl">Upcoming events</div><div class="delta">+<?= $regsWeek ?> registrations this week</div></div></div>
  <div class="ad-stat"><div class="ico"><i class="bi bi-journal-medical"></i></div>
    <div><div class="num"><?= $resources ?></div><div class="lbl">Learning resources</div><div class="delta">+<?= $doneWeek ?> completions this week</div></div></div>
  <div class="ad-stat"><div class="ico"><i class="bi bi-chat-dots"></i></div>
    <div><div class="num"><?= $topics ?></div><div class="lbl">Forum topics</div><div class="delta">+<?= $topicsWeek ?> this week</div></div></div>
</div>

<div class="ad-card">
  <h2>Categories <small>upcoming events, resources and forum topics</small></h2>
  <div class="ad-cats">
    <?php foreach (interests_list() as $c): [$c1, $c2, $icon] = category_style($c); ?>
      <div class="ad-cat">
        <div class="tile" style="background: linear-gradient(135deg, <?= e($c1) ?>, <?= e($c2) ?>);"><i class="bi <?= e($icon) ?>"></i></div>
        <div class="t"><?= e($c) ?></div>
        <div class="s">Events: <?= (int)($catEvents[$c] ?? 0) ?> &middot; Resources: <?= (int)($catRes[$c] ?? 0) ?></div>
        <div class="s2">Forum topics: <?= (int)($catTopics[$c] ?? 0) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="ad-grid-2">
  <div class="ad-card">
    <h2>Activity, last 30 days</h2>
    <div class="ad-chart"><canvas id="lineChart"></canvas></div>
  </div>
  <div class="ad-card">
    <h2>Resources completed by category</h2>
    <div class="ad-chart"><canvas id="barChart"></canvas></div>
  </div>
</div>

<div class="ad-card">
  <h2>Recent activity</h2>
  <?php if (!$activity): ?>
    <div class="ad-empty">No activity yet.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="ad-table">
        <thead><tr><th>Member</th><th>Action</th><th>Details</th><th>When</th></tr></thead>
        <tbody>
          <?php foreach ($activity as $a): ?>
            <tr>
              <td><div class="ad-person"><span class="ad-init"><?= e(initials($a['who'])) ?></span><?= e($a['who']) ?></div></td>
              <td><span class="ad-pill <?= $kindPill[$a['kind']] ?? 'p-grey' ?>"><?= e($a['kind']) ?></span></td>
              <td><?= e(mb_strimwidth($a['detail'], 0, 60, '...')) ?></td>
              <td class="sub"><?= e(time_ago($a['at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
new Chart(document.getElementById('lineChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($shown, JSON_HEX_TAG) ?>,
    datasets: [
      { label: 'Sign-ups', data: <?= json_encode($sSign) ?>, borderColor: '#0B5ED7', backgroundColor: 'rgba(11,94,215,.12)', fill: true, tension: .35 },
      { label: 'Event registrations', data: <?= json_encode($sReg) ?>, borderColor: '#2e8b57', tension: .35 },
      { label: 'Resources completed', data: <?= json_encode($sDone) ?>, borderColor: '#f59f00', tension: .35 }
    ]
  },
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { maxTicksLimit: 8 } } } }
});
new Chart(document.getElementById('barChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($cc, 'category'), JSON_HEX_TAG) ?>,
    datasets: [{ label: 'Completions', data: <?= json_encode(array_map('intval', array_column($cc, 'c'))) ?>, backgroundColor: '#0B5ED7', borderRadius: 6 }]
  },
  options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
    scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
});
</script>
<?php require 'admin_footer.php'; ?>