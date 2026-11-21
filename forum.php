<?php
require_once 'db.php';
require_once 'functions.php';
require_login();

$cat  = $_GET['cat'] ?? 'All';
$q    = trim($_GET['q'] ?? '');
$show = max(6, min(60, (int)($_GET['show'] ?? 6)));
if ($cat !== 'All' && !in_array($cat, interests_list(), true)) $cat = 'All';

$where  = "1=1";
$params = [];
if ($cat !== 'All') {
    $where   .= " AND t.category = ?";
    $params[] = $cat;
}
if ($q !== '') {
    $where   .= " AND (t.title LIKE ? OR t.body LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$cnt = $pdo->prepare("SELECT COUNT(*) FROM forum_topics t WHERE $where");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT t.*, u.full_name,
            (SELECT COUNT(*) FROM forum_replies r WHERE r.topic_id = t.id) AS replies
     FROM forum_topics t
     JOIN users u ON u.id = t.user_id
     WHERE $where
     ORDER BY t.created_at DESC, t.id DESC
     LIMIT $show"
);
$stmt->execute($params);
$topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

function fo_url(string $cat, string $q, int $show): string {
    $p = [];
    if ($cat !== 'All') $p['cat'] = $cat;
    if ($q !== '')      $p['q'] = $q;
    if ($show > 6)      $p['show'] = $show;
    return 'forum.php' . ($p ? '?' . http_build_query($p) : '');
}

require 'header.php';
?>
<section class="fo-stage">
  <div class="fo-wrap">

    <h1 class="fo-title"><span class="l1">Community</span><span class="l2">Forum</span></h1>

    <?php if ($flash): ?><div class="fo-flash"><?= e($flash) ?></div><?php endif; ?>

    <div class="fo-pills">
      <a class="fo-pill <?= $cat === 'All' ? 'active' : '' ?>" href="<?= e(fo_url('All', $q, 6)) ?>">All</a>
      <?php foreach (interests_list() as $c): [$c1] = category_style($c); ?>
        <a class="fo-pill <?= $cat === $c ? 'active' : '' ?>" href="<?= e(fo_url($c, $q, 6)) ?>">
          <i class="dot" style="background: <?= e($c1) ?>"></i><?= e($c) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="fo-tools">
      <form method="get" class="fo-search">
        <i class="bi bi-search"></i>
        <?php if ($cat !== 'All'): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
        <input type="text" name="q" placeholder="Search topics" value="<?= e($q) ?>">
      </form>
      <a href="forum_new.php" class="fo-new"><i class="bi bi-plus-lg"></i> New topic</a>
    </div>

    <?php if (!$topics): ?>
      <div class="fo-empty">No topics found. Be the first to start a conversation.</div>
    <?php endif; ?>

    <div class="fo-grid">
      <?php foreach ($topics as $t): [$c1, $c2, $icon] = category_style($t['category']); ?>
        <a class="fo-card" href="topic.php?id=<?= (int)$t['id'] ?>">
          <div class="fo-thumb" style="background: linear-gradient(135deg, <?= e($c1) ?>, <?= e($c2) ?>);">
            <i class="bi <?= e($icon) ?>"></i>
          </div>
          <div class="fo-body">
            <div class="fo-tags">
              <span class="fo-tag"><i class="dot" style="background: <?= e($c1) ?>"></i><?= e($t['category']) ?></span>
              <span class="fo-tag"><i class="bi bi-chat-dots"></i> <?= (int)$t['replies'] ?> repl<?= (int)$t['replies'] === 1 ? 'y' : 'ies' ?></span>
            </div>
            <h3><?= e($t['title']) ?></h3>
            <div class="fo-meta">by <?= e($t['full_name']) ?> &middot; <?= e(time_ago($t['created_at'])) ?></div>
            <span class="fo-join">JOIN CONVERSATION <i class="bi bi-arrow-up-right"></i></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($total > $show): ?>
      <div class="fo-more"><a href="<?= e(fo_url($cat, $q, $show + 6)) ?>">LOAD MORE</a></div>
    <?php endif; ?>

  </div>
</section>
<?php require 'footer.php'; ?>