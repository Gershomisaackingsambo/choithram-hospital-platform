<?php
require_once 'db.php';
require_once 'functions.php';
require_login();

$uid = (int)$_SESSION['user_id'];
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ?");
$stmt->execute([$id]);
$res = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$res) {
    header("Location: resources.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'complete') {
        $pdo->prepare("INSERT IGNORE INTO resource_progress (resource_id, user_id) VALUES (?, ?)")->execute([$id, $uid]);
        $_SESSION['flash'] = "Marked as completed. Well done!";
    } elseif ($action === 'undo') {
        $pdo->prepare("DELETE FROM resource_progress WHERE resource_id = ? AND user_id = ?")->execute([$id, $uid]);
        $_SESSION['flash'] = "Completion removed.";
    }
    header("Location: resource.php?id=" . $id);
    exit;
}

$d = $pdo->prepare("SELECT completed_at FROM resource_progress WHERE resource_id = ? AND user_id = ?");
$d->execute([$id, $uid]);
$doneRow = $d->fetch(PDO::FETCH_ASSOC);

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

[$c1, $c2, $icon] = category_style($res['category']);

require 'header.php';
?>
<section class="res-hero" style="background: linear-gradient(135deg, <?= $c1 ?>, <?= $c2 ?>); padding-bottom: 60px;">
  <i class="bi <?= e($icon) ?>" style="font-size: 3rem;"></i>
  <h1 class="mt-2"><?= e($res['title']) ?></h1>
  <p><?= e($res['category']) ?> &middot; <?= e(ucfirst($res['type'])) ?></p>
</section>

<div class="container" style="max-width: 800px; margin-top: -30px; margin-bottom: 40px;">
  <div class="card dash-card p-4">
    <?php if ($flash): ?>
      <div class="alert alert-success py-2"><?= e($flash) ?></div>
    <?php endif; ?>

    <p class="lead"><?= e($res['description']) ?></p>
    <hr>
    <div class="res-body"><?= e($res['content'] ?? 'Content coming soon.') ?></div>

    <?php if (!empty($res['link'])): ?>
      <a href="<?= e($res['link']) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary mt-3 align-self-start">
        <i class="bi bi-box-arrow-up-right me-1"></i>Open linked material
      </a>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2 mt-4">
      <form method="post">
        <?php if ($doneRow): ?>
          <input type="hidden" name="action" value="undo">
          <button type="submit" class="btn btn-success"><i class="bi bi-check-circle-fill me-1"></i>Completed (click to undo)</button>
        <?php else: ?>
          <input type="hidden" name="action" value="complete">
          <button type="submit" class="btn btn-azure"><i class="bi bi-check-circle me-1"></i>Mark as completed</button>
        <?php endif; ?>
      </form>
      <a href="resources.php" class="btn btn-light">Back to resources</a>
    </div>
  </div>
</div>
<?php require 'footer.php'; ?>