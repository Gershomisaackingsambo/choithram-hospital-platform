<?php
require_once 'db.php';
require_once 'functions.php';
require_admin();

$back = 'admin_forum.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['flash_err'] = "Your session expired. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int)($_POST['id'] ?? 0);
        if ($action === 'delete_topic') {
            $pdo->prepare("DELETE FROM forum_topics WHERE id = ?")->execute([$id]);
            $_SESSION['flash'] = "Topic and its replies deleted.";
        } elseif ($action === 'delete_reply') {
            $pdo->prepare("DELETE FROM forum_replies WHERE id = ?")->execute([$id]);
            $_SESSION['flash'] = "Reply deleted.";
        }
    }
    header("Location: $back");
    exit;
}

$q   = trim($_GET['q'] ?? '');
$cat = $_GET['cat'] ?? '';

$where  = "1=1";
$params = [];
if ($q !== '') {
    $where .= " AND (t.title LIKE ? OR t.body LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($cat !== '' && in_array($cat, interests_list(), true)) {
    $where .= " AND t.category = ?";
    $params[] = $cat;
} else {
    $cat = '';
}

$stmt = $pdo->prepare(
    "SELECT t.id, t.title, t.category, t.created_at, u.full_name,
            (SELECT COUNT(*) FROM forum_replies r WHERE r.topic_id = t.id) AS replies
     FROM forum_topics t JOIN users u ON u.id = t.user_id
     WHERE $where ORDER BY t.created_at DESC LIMIT 100"
);
$stmt->execute($params);
$topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

$latest = $pdo->query(
    "SELECT r.id, r.body, r.created_at, r.topic_id, u.full_name, t.title
     FROM forum_replies r JOIN users u ON u.id = r.user_id JOIN forum_topics t ON t.id = r.topic_id
     ORDER BY r.created_at DESC LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);

$adminPage = 'forum';
$pageTitle = 'Forum';
$pageSub   = 'Review and moderate community discussions';
require 'admin_header.php';
?>

<form method="get" class="ad-filter">
  <input class="ad-input" type="text" name="q" placeholder="Search topics" value="<?= e($q) ?>">
  <select class="ad-input" name="cat" onchange="this.form.submit()">
    <option value="">All categories</option>
    <?php foreach (interests_list() as $c): ?>
      <option value="<?= e($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="ad-btn light" type="submit"><i class="bi bi-search"></i> Search</button>
</form>

<div class="ad-card">
  <h2>Topics</h2>
  <?php if (!$topics): ?>
    <div class="ad-empty">No topics found.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="ad-table">
      <thead><tr><th>Topic</th><th>Category</th><th>Author</th><th>Replies</th><th>Posted</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($topics as $t): ?>
        <tr>
          <td class="fw-semibold"><?= e(mb_strimwidth($t['title'], 0, 70, '...')) ?></td>
          <td><?= e($t['category']) ?></td>
          <td><?= e($t['full_name']) ?></td>
          <td><?= (int)$t['replies'] ?></td>
          <td class="sub"><?= e(time_ago($t['created_at'])) ?></td>
          <td>
            <div class="ad-actions">
              <a class="ad-ico" href="topic.php?id=<?= (int)$t['id'] ?>" title="View on site"><i class="bi bi-eye"></i></a>
              <?= ad_btn_form('delete_topic', ['id' => $t['id']], 'bi-trash', 'Delete topic', 'Delete this topic and all its replies?', 'danger') ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="ad-card">
  <h2>Latest replies</h2>
  <?php if (!$latest): ?>
    <div class="ad-empty">No replies yet.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="ad-table">
      <thead><tr><th>Reply</th><th>Author</th><th>In topic</th><th>Posted</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($latest as $r): ?>
        <tr>
          <td><?= e(mb_strimwidth($r['body'], 0, 80, '...')) ?></td>
          <td><?= e($r['full_name']) ?></td>
          <td><a href="topic.php?id=<?= (int)$r['topic_id'] ?>"><?= e(mb_strimwidth($r['title'], 0, 40, '...')) ?></a></td>
          <td class="sub"><?= e(time_ago($r['created_at'])) ?></td>
          <td><?= ad_btn_form('delete_reply', ['id' => $r['id']], 'bi-trash', 'Delete reply', 'Delete this reply?', 'danger') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php require 'admin_footer.php'; ?>