<?php
require_once 'db.php';
require_once 'functions.php';
require_admin();

/* ---------- Date range ---------- */
$today = date('Y-m-d');
$range = $_GET['range'] ?? '30d';
$from  = null;
$to    = null;

switch ($range) {
    case 'today':
        $from = $to = $today;
        break;
    case 'week':
        $from = date('Y-m-d', strtotime('monday this week'));
        $to   = $today;
        break;
    case 'month':
        $from = date('Y-m-01');
        $to   = $today;
        break;
    case 'last_month':
        $from = date('Y-m-01', strtotime('first day of last month'));
        $to   = date('Y-m-t', strtotime('first day of last month'));
        break;
    case 'quarter':
        $qs   = (int)(floor(((int)date('n') - 1) / 3) * 3 + 1);
        $from = date('Y') . '-' . sprintf('%02d', $qs) . '-01';
        $to   = $today;
        break;
    case 'custom':
        $f  = $_GET['from'] ?? '';
        $t  = $_GET['to'] ?? '';
        $df = DateTime::createFromFormat('Y-m-d', $f);
        $dt = DateTime::createFromFormat('Y-m-d', $t);
        if ($df && $dt && $df->format('Y-m-d') === $f && $dt->format('Y-m-d') === $t) {
            $from = $f;
            $to   = $t;
        } else {
            $range = '30d';
        }
        break;
    default:
        $range = '30d';
}
if ($from === null) {
    $from = date('Y-m-d', strtotime('-29 days'));
    $to   = $today;
}
if ($from > $to) [$from, $to] = [$to, $from];
if ((strtotime($to) - strtotime($from)) / 86400 > 365) {
    $from = date('Y-m-d', strtotime($to . ' -365 days'));
}
$fromDT = $from . ' 00:00:00';
$toDT   = $to . ' 23:59:59';

$cnt = function (string $sql, array $p = []) use ($pdo): int {
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return (int)$s->fetchColumn();
};
$pr = [$fromDT, $toDT];

/* ---------- Stats ---------- */
$newMembers = $cnt("SELECT COUNT(*) FROM users WHERE created_at BETWEEN ? AND ?", $pr);
$regs       = $cnt("SELECT COUNT(*) FROM event_registrations WHERE status <> 'cancelled' AND registered_at BETWEEN ? AND ?", $pr);
$completed  = $cnt("SELECT COUNT(*) FROM resource_progress WHERE completed_at BETWEEN ? AND ?", $pr);
$posts      = $cnt("SELECT COUNT(*) FROM forum_topics WHERE created_at BETWEEN ? AND ?", $pr)
            + $cnt("SELECT COUNT(*) FROM forum_replies WHERE created_at BETWEEN ? AND ?", $pr);
$mentorReq  = $cnt("SELECT COUNT(*) FROM mentorships WHERE requested_at BETWEEN ? AND ?", $pr);

/* ---------- Chart series ---------- */
$labels = date_range_labels($from, $to);
$step   = count($labels) > 45 ? 7 : 1;
$sSign  = series($labels, daily_counts($pdo, 'users', 'created_at', $from, $to));
$sReg   = series($labels, daily_counts($pdo, 'event_registrations', 'registered_at', $from, $to, "AND status <> 'cancelled'"));
$sDone  = series($labels, daily_counts($pdo, 'resource_progress', 'completed_at', $from, $to));
$sTop   = daily_counts($pdo, 'forum_topics', 'created_at', $from, $to);
$sRep   = daily_counts($pdo, 'forum_replies', 'created_at', $from, $to);
$sPosts = array_map(fn($l) => ($sTop[$l] ?? 0) + ($sRep[$l] ?? 0), $labels);
$shown  = array_map(fn($l) => date('d M', strtotime($l)), $labels);

/* ---------- Event participation (events held in the range) ---------- */
$ev = $pdo->prepare(
    "SELECT e.id, e.title, e.category, e.event_date, e.capacity,
       (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status IN ('registered','attended')) AS registered,
       (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status = 'attended') AS attended
     FROM events e
     WHERE e.event_date BETWEEN ? AND ?
     ORDER BY e.event_date ASC"
);
$ev->execute($pr);
$eventRows = $ev->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Member engagement ---------- */
$params = [];
for ($i = 0; $i < 5; $i++) { $params[] = $fromDT; $params[] = $toDT; }
$en = $pdo->prepare(
    "SELECT * FROM (
       SELECT u.id, u.full_name, u.email,
         (SELECT COUNT(*) FROM event_registrations r WHERE r.user_id = u.id AND r.status <> 'cancelled' AND r.registered_at BETWEEN ? AND ?) AS regs,
         (SELECT COUNT(*) FROM event_registrations r WHERE r.user_id = u.id AND r.status = 'attended' AND r.registered_at BETWEEN ? AND ?) AS att,
         (SELECT COUNT(*) FROM resource_progress p WHERE p.user_id = u.id AND p.completed_at BETWEEN ? AND ?) AS done,
         (SELECT COUNT(*) FROM forum_topics t WHERE t.user_id = u.id AND t.created_at BETWEEN ? AND ?)
         + (SELECT COUNT(*) FROM forum_replies fr WHERE fr.user_id = u.id AND fr.created_at BETWEEN ? AND ?) AS posts
       FROM users u WHERE u.role = 'member'
     ) x
     ORDER BY (regs + att + done + posts) DESC, full_name ASC
     LIMIT 25"
);
$en->execute($params);
$engage = $en->fetchAll(PDO::FETCH_ASSOC);

/* ---------- CSV export (must run before any output) ---------- */
if (isset($_GET['export'])) {
    $type = $_GET['export'] === 'events' ? 'events' : 'members';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $type . '_' . $from . '_to_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    if ($type === 'events') {
        fputcsv($out, ['Event', 'Category', 'Date', 'Capacity', 'Registered', 'Attended', 'Attendance rate %'], ',', '"', '\\');
        foreach ($eventRows as $r) {
            $rate = $r['registered'] > 0 ? round($r['attended'] / $r['registered'] * 100) : 0;
            fputcsv($out, [csv_safe($r['title']), csv_safe($r['category']), $r['event_date'], $r['capacity'], $r['registered'], $r['attended'], $rate], ',', '"', '\\');
        }
    } else {
        fputcsv($out, ['Member', 'Email', 'Events registered', 'Events attended', 'Resources completed', 'Forum posts'], ',', '"', '\\');
        foreach ($engage as $r) {
            fputcsv($out, [csv_safe($r['full_name']), csv_safe($r['email']), $r['regs'], $r['att'], $r['done'], $r['posts']], ',', '"', '\\');
        }
    }
    fclose($out);
    exit;
}

$qs = fn(array $extra) => http_build_query(array_merge(['range' => $range, 'from' => $from, 'to' => $to], $extra));

$adminPage   = 'reports';
$pageTitle   = 'Reports';
$pageSub     = 'Member engagement and participation: ' . date('d M Y', strtotime($from)) . ' to ' . date('d M Y', strtotime($to));
$pageActions = '<a class="ad-btn light" href="admin_reports.php?' . e($qs(['export' => 'members'])) . '"><i class="bi bi-download"></i> Export CSV</a>'
             . '<button class="ad-btn light" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>';
$needCharts  = true;
require 'admin_header.php';

$pills = ['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'last_month' => 'Last Month', '30d' => 'Last 30 Days', 'quarter' => 'This Quarter'];
?>

<form method="get" class="ad-card ad-noprint">
  <div class="ad-filter mb-0">
    <i class="bi bi-calendar3 text-azure"></i>
    <input type="hidden" name="range" value="custom">
    <input class="ad-input" type="date" name="from" value="<?= e($from) ?>">
    <span class="sub">to</span>
    <input class="ad-input" type="date" name="to" value="<?= e($to) ?>">
    <button class="ad-btn" type="submit">Apply</button>
    <div class="ad-chips ms-md-3">
      <?php foreach ($pills as $k => $label): ?>
        <a class="ad-chip <?= $range === $k ? 'active' : '' ?>" href="admin_reports.php?range=<?= e($k) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</form>

<div class="ad-stats">
  <div class="ad-stat"><div class="ico"><i class="bi bi-person-plus"></i></div><div><div class="num"><?= $newMembers ?></div><div class="lbl">New members</div></div></div>
  <div class="ad-stat"><div class="ico"><i class="bi bi-calendar-check"></i></div><div><div class="num"><?= $regs ?></div><div class="lbl">Event registrations</div></div></div>
  <div class="ad-stat"><div class="ico"><i class="bi bi-journal-check"></i></div><div><div class="num"><?= $completed ?></div><div class="lbl">Resources completed</div></div></div>
  <div class="ad-stat"><div class="ico"><i class="bi bi-chat-dots"></i></div><div><div class="num"><?= $posts ?></div><div class="lbl">Forum posts</div></div></div>
  <div class="ad-stat"><div class="ico"><i class="bi bi-people"></i></div><div><div class="num"><?= $mentorReq ?></div><div class="lbl">Mentorship requests</div></div></div>
</div>

<div class="ad-grid-2">
  <div class="ad-card">
    <h2>Daily activity</h2>
    <div class="ad-chart"><canvas id="lineChart"></canvas></div>
  </div>
  <div class="ad-card">
    <h2>Registered vs attended <small>events in this period</small></h2>
    <div class="ad-chart"><canvas id="barChart"></canvas></div>
  </div>
</div>

<div class="ad-card">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="mb-0">Event participation <small>events held in this period</small></h2>
    <a class="ad-btn light ad-noprint" href="admin_reports.php?<?= e($qs(['export' => 'events'])) ?>"><i class="bi bi-download"></i> Export CSV</a>
  </div>
  <?php if (!$eventRows): ?>
    <div class="ad-empty">No events took place in this period.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="ad-table">
      <thead><tr><th>Event</th><th>Date</th><th>Capacity</th><th>Registered</th><th>Attended</th><th>Attendance rate</th></tr></thead>
      <tbody>
      <?php foreach ($eventRows as $r): $rate = $r['registered'] > 0 ? round($r['attended'] / $r['registered'] * 100) : 0; ?>
        <tr>
          <td><div class="fw-semibold"><?= e($r['title']) ?></div><div class="sub"><?= e($r['category']) ?></div></td>
          <td><?= e(date('d M Y', strtotime($r['event_date']))) ?></td>
          <td><?= (int)$r['capacity'] ?></td>
          <td><?= (int)$r['registered'] ?></td>
          <td><?= (int)$r['attended'] ?></td>
          <td><?= $rate ?>%<div class="ad-bar"><span style="width: <?= $rate ?>%"></span></div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="ad-card">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="mb-0">Member engagement <small>top 25 most active members</small></h2>
    <a class="ad-btn light ad-noprint" href="admin_reports.php?<?= e($qs(['export' => 'members'])) ?>"><i class="bi bi-download"></i> Export CSV</a>
  </div>
  <div class="table-wrap">
    <table class="ad-table">
      <thead><tr><th>Member</th><th>Events registered</th><th>Events attended</th><th>Resources completed</th><th>Forum posts</th></tr></thead>
      <tbody>
      <?php foreach ($engage as $r): ?>
        <tr>
          <td><div class="ad-person"><span class="ad-init"><?= e(initials($r['full_name'])) ?></span>
            <div><div class="fw-semibold"><?= e($r['full_name']) ?></div><div class="sub"><?= e($r['email']) ?></div></div></div></td>
          <td><?= (int)$r['regs'] ?></td>
          <td><?= (int)$r['att'] ?></td>
          <td><?= (int)$r['done'] ?></td>
          <td><?= (int)$r['posts'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const step = <?= (int)$step ?>;
new Chart(document.getElementById('lineChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($shown, JSON_HEX_TAG) ?>,
    datasets: [
      { label: 'Sign-ups', data: <?= json_encode($sSign) ?>, borderColor: '#0B5ED7', backgroundColor: 'rgba(11,94,215,.12)', fill: true, tension: .35 },
      { label: 'Event registrations', data: <?= json_encode($sReg) ?>, borderColor: '#2e8b57', tension: .35 },
      { label: 'Resources completed', data: <?= json_encode($sDone) ?>, borderColor: '#f59f00', tension: .35 },
      { label: 'Forum posts', data: <?= json_encode($sPosts) ?>, borderColor: '#6f42c1', tension: .35 }
    ]
  },
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { maxTicksLimit: 8 } } } }
});
new Chart(document.getElementById('barChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_map(fn($r) => mb_strimwidth($r['title'], 0, 22, '...'), $eventRows), JSON_HEX_TAG) ?>,
    datasets: [
      { label: 'Registered', data: <?= json_encode(array_map(fn($r) => (int)$r['registered'], $eventRows)) ?>, backgroundColor: '#4db8ff', borderRadius: 6 },
      { label: 'Attended',   data: <?= json_encode(array_map(fn($r) => (int)$r['attended'], $eventRows)) ?>,   backgroundColor: '#0B5ED7', borderRadius: 6 }
    ]
  },
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});
</script>
<?php require 'admin_footer.php'; ?>