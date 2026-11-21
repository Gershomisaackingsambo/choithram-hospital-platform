<?php
require_once 'db.php';
require_once 'functions.php';
require_admin();

$id      = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$res     = null;

if ($editing) {
    $st = $pdo->prepare("SELECT * FROM resources WHERE id = ?");
    $st->execute([$id]);
    $res = $st->fetch(PDO::FETCH_ASSOC);
    if (!$res) {
        $_SESSION['flash_err'] = "That resource no longer exists.";
        header("Location: admin_resources.php");
        exit;
    }
}

$form = [
    'title'       => $res['title'] ?? '',
    'category'    => $res['category'] ?? '',
    'type'        => $res['type'] ?? 'guide',
    'description' => $res['description'] ?? '',
    'content'     => $res['content'] ?? '',
    'link'        => $res['link'] ?? '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) $errors[] = "Your session expired. Please try again.";

    $form['title']       = trim($_POST['title'] ?? '');
    $form['category']    = $_POST['category'] ?? '';
    $form['type']        = $_POST['type'] ?? '';
    $form['description'] = trim($_POST['description'] ?? '');
    $form['content']     = trim($_POST['content'] ?? '');
    $form['link']        = trim($_POST['link'] ?? '');

    if (mb_strlen($form['title']) < 5 || mb_strlen($form['title']) > 150)       $errors[] = "The title must be 5 to 150 characters.";
    if (!in_array($form['category'], interests_list(), true))                    $errors[] = "Please choose a category.";
    if (!in_array($form['type'], resource_types(), true))                        $errors[] = "Please choose a format.";
    if (mb_strlen($form['description']) < 10 || mb_strlen($form['description']) > 500) $errors[] = "The short description must be 10 to 500 characters.";
    if (mb_strlen($form['content']) > 10000)                                     $errors[] = "The content must be 10000 characters or fewer.";
    if ($form['link'] !== '') {
        $okLink = filter_var($form['link'], FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $form['link']);
        if (!$okLink)                                                            $errors[] = "The link must be a full web address starting with http:// or https://.";
        if (mb_strlen($form['link']) > 255)                                      $errors[] = "The link must be 255 characters or fewer.";
    }
    if ($form['content'] === '' && $form['link'] === '')                         $errors[] = "Please add the content, a link, or both.";

    if (!$errors) {
        $link = $form['link'] !== '' ? $form['link'] : null;
        if ($editing) {
            $pdo->prepare("UPDATE resources SET title = ?, description = ?, category = ?, type = ?, link = ?, content = ? WHERE id = ?")
                ->execute([$form['title'], $form['description'], $form['category'], $form['type'], $link, $form['content'], $id]);
            $_SESSION['flash'] = "Resource updated.";
        } else {
            $pdo->prepare("INSERT INTO resources (title, description, category, type, link, content, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$form['title'], $form['description'], $form['category'], $form['type'], $link, $form['content'], $_SESSION['user_id']]);
            $_SESSION['flash'] = "Resource added.";
        }
        header("Location: admin_resources.php");
        exit;
    }
}

$adminPage   = 'resources';
$pageTitle   = $editing ? 'Edit resource' : 'Add resource';
$pageSub     = $editing ? 'Update this learning material' : 'Publish a new guide, tutorial or checklist';
$pageActions = '<a class="ad-btn light" href="admin_resources.php"><i class="bi bi-arrow-left"></i> Back to resources</a>';
require 'admin_header.php';
?>
<div class="ad-card ad-form" style="max-width: 860px;">
  <?php foreach ($errors as $err): ?>
    <div class="ad-flash err"><?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="post" novalidate>
    <?= csrf_field() ?>

    <div class="ad-section"><i class="bi bi-info-circle"></i> Basic information</div>
    <div class="row g-3 mb-4">
      <div class="col-12">
        <label for="title">Title</label>
        <input type="text" class="form-control" id="title" name="title" maxlength="150" value="<?= e($form['title']) ?>" placeholder="e.g. Preventing Malaria at Home">
      </div>
      <div class="col-md-6">
        <label for="category">Category</label>
        <select class="form-select" id="category" name="category">
          <option value="">Choose a category</option>
          <?php foreach (interests_list() as $c): ?>
            <option value="<?= e($c) ?>" <?= $form['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label for="type">Format</label>
        <select class="form-select" id="type" name="type">
          <?php foreach (resource_types() as $t): ?>
            <option value="<?= e($t) ?>" <?= $form['type'] === $t ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label for="description">Short description</label>
        <input type="text" class="form-control" id="description" name="description" maxlength="500" value="<?= e($form['description']) ?>" placeholder="One sentence shown on the resource card">
      </div>
    </div>

    <div class="ad-section"><i class="bi bi-file-text"></i> Content</div>
    <div class="mb-3">
      <label for="content">Full text</label>
      <textarea class="form-control" id="content" name="content" rows="10" maxlength="10000" placeholder="Write the guide here. Line breaks are kept."><?= e($form['content']) ?></textarea>
    </div>
    <div class="mb-4">
      <label for="link">Link (optional)</label>
      <input type="url" class="form-control" id="link" name="link" maxlength="255" value="<?= e($form['link']) ?>" placeholder="https://...">
      <div class="hint">Use this for a video, PDF or website. Members see an "Open linked material" button.</div>
    </div>

    <button type="submit" class="ad-btn"><i class="bi bi-check2-circle"></i> <?= $editing ? 'Save changes' : 'Add resource' ?></button>
    <a href="admin_resources.php" class="ad-btn light ms-2">Cancel</a>
  </form>
</div>
<?php require 'admin_footer.php'; ?>