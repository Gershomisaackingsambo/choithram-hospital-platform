<?php
require_once 'db.php';
require_once 'functions.php';
require_login();

$uid  = (int)$_SESSION['user_id'];
$q    = trim($_GET['q'] ?? '');
$cat  = $_GET['category'] ?? '';
$type = $_GET['type'] ?? '';

$where  = "1=1";
$params = [];
if ($q !== '') {
    $where   .= " AND (r.title LIKE ? OR r.description LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($cat !== '' && in_array($cat, interests_list(), true)) {
    $where   .= " AND r.category = ?";
    $params[] = $cat;
} else {
    $cat = '';
}
if ($type !== '') {
    $where   .= " AND r.type = ?";
    $params[] = $type;
}

$sql = "SELECT r.*,
          (SELECT COUNT(*) FROM resource_progress p WHERE p.resource_id = r.id AND p.user_id = ?) AS done
        FROM resources r
        WHERE $where
        ORDER BY r.created_at DESC, r.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$uid], $params));
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$types = $pdo->query("SELECT DISTINCT type FROM resources ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
if ($type !== '' && !in_array($type, $types, true)) $type = '';

$doneCount  = count(array_filter($items, fn($i) => (int)$i['done'] > 0));

require 'header.php';
?>
<section class="res-hero">
  <h1>Resources</h1>
  <p>Trusted health guides, tutorials and checklists for every member.</p>
</section>

<form method="get" class="res-search">
  <label class="find"><i class="bi bi-search"></i>
    <input type="text" name="q" placeholder="Find" value="<?= e($q) ?>">
  </label>
  <select name="category" class="res-pill" onchange="this.form.submit()">
    <option value="">Category</option>
    <?php foreach (interests_list() as $c): ?>
      <option value="<?= e($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="type" class="res-pill" onchange="this.form.submit()">
    <option value="">Format</option>
    <?php foreach ($types as $t): ?>
      <option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="res-go">Search</button>
</form>

<div class="res-count"><?= count($items) ?> resource<?= count($items) === 1 ? '' : 's' ?> &middot; you have completed <?= $doneCount ?></div>

<?php if (!$items): ?>
  <div class="container text-center text-muted py-5">No resources match your search.</div>
<?php endif; ?>

<div class="res-grid">
  <?php foreach ($items as $it):
      [$c1, $c2, $icon] = category_style($it['category']);
  ?>
    <a class="res-card" href="resource.php?id=<?= (int)$it['id'] ?>">
      <div class="res-cover" style="background: linear-gradient(160deg, <?= $c1 ?>, <?= $c2 ?>);">
        <?php if ((int)$it['done'] > 0): ?><span class="res-done"><i class="bi bi-check-circle-fill me-1"></i>Completed</span><?php endif; ?>
        <span class="fmt"><?= e($it['type']) ?></span>
        <i class="bi <?= e($icon) ?> big"></i>
        <div class="cat"><?= e($it['category']) ?></div>
      </div>
      <div class="res-caption"><?= e($it['title']) ?></div>
    </a>
  <?php endforeach; ?>
</div>
<?php require 'footer.php'; ?>