<?php
require_once 'db.php';
require_once 'functions.php';
require_admin();

$back = 'admin_resources.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['flash_err'] = "Your session expired. Please try again.";
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $t = $pdo->prepare("SELECT title FROM resources WHERE id = ?");
        $t->execute([$id]);
        $title = $t->fetchColumn();
        if ($title !== false) {
            $pdo->prepare("DELETE FROM resources WHERE id = ?")->execute([$id]);
            $_SESSION['flash'] = '"' . $title . '" was deleted.';
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
    $where .= " AND (r.title LIKE ? OR r.description LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($cat !== '' && in_array($cat, interests_list(), true)) {
    $where .= " AND r.category = ?";
    $params[] = $cat;
} else {
    $cat = '';
}

$stmt = $pdo->prepare(
    "SELECT r.*, (SELECT COUNT(*) FROM resource_progress p WHERE p.resource_id = r.id) AS done_n
     FROM resources r WHERE $where ORDER BY r.created_at DESC, r.id DESC"
);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$adminPage   = 'resources';
$pageTitle   = 'Resources';
$pageSub     = 'Guides, tutorials and learning materials for members';
$pageActions = '<a class="ad-btn" href="admin_resource_form.php"><i class="bi bi-plus-lg"></i> Add resource</a>';
require 'admin_header.php';
?>

<form method="get" class="ad-filter">
  <input class="ad-input" type="text" name="q" placeholder="Search resources" value="<?= e($q) ?>">
  <select class="ad-input" name="cat" onchange="this.form.submit()">
    <option value="">All categories</option>
    <?php foreach (interests_list() as $c): ?>
      <option value="<?= e($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="ad-btn light" type="submit"><i class="bi bi-search"></i> Search</button>
</form>

<div class="ad-card">
  <?php if (!$items): ?>
    <div class="ad-empty">No resources found. <a href="admin_resource_form.php">Add one</a>.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="ad-table">
      <thead><tr><th>Resource</th><th>Category</th><th>Format</th><th>Completed by</th><th>Added</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($items as $r): [$c1, $c2, $icon] = category_style($r['category']); ?>
        <tr>
          <td>
            <div class="ad-person">
              <span class="ad-init" style="background: linear-gradient(135deg, <?= e($c1) ?>, <?= e($c2) ?>);"><i class="bi <?= e($icon) ?>"></i></span>
              <div><div class="fw-semibold"><?= e($r['title']) ?></div><div class="sub"><?= e(mb_strimwidth($r['description'], 0, 70, '...')) ?></div></div>
            </div>
          </td>
          <td><?= e($r['category']) ?></td>
          <td><span class="ad-pill p-blue"><?= e(ucfirst($r['type'])) ?></span></td>
          <td><?= (int)$r['done_n'] ?> member<?= (int)$r['done_n'] === 1 ? '' : 's' ?></td>
          <td class="sub"><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
          <td>
            <div class="ad-actions">
              <a class="ad-ico" href="resource.php?id=<?= (int)$r['id'] ?>" title="View on site"><i class="bi bi-eye"></i></a>
              <a class="ad-ico" href="admin_resource_form.php?id=<?= (int)$r['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
              <?= ad_btn_form('delete', ['id' => $r['id']], 'bi-trash', 'Delete', 'Delete this resource? Members will lose their completion record for it.', 'danger') ?>
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