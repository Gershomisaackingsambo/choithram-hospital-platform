<?php
require_once 'db.php';
require_once 'functions.php';
require_login();

$uid    = (int)$_SESSION['user_id'];
$errors = [];
$form   = ['title' => '', 'category' => '', 'body' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['title']    = trim($_POST['title'] ?? '');
    $form['category'] = $_POST['category'] ?? '';
    $form['body']     = trim($_POST['body'] ?? '');

    if ($form['title'] === '' || mb_strlen($form['title']) < 5)  $errors[] = "Please enter a title of at least 5 characters.";
    if (mb_strlen($form['title']) > 150)                          $errors[] = "The title must be 150 characters or fewer.";
    if (!in_array($form['category'], interests_list(), true))     $errors[] = "Please choose a category.";
    if (mb_strlen($form['body']) < 10)                            $errors[] = "Please write at least 10 characters in your message.";
    if (mb_strlen($form['body']) > 3000)                          $errors[] = "Your message must be 3000 characters or fewer.";

    if (!$errors) {
        $ins = $pdo->prepare("INSERT INTO forum_topics (user_id, title, body, category) VALUES (?, ?, ?, ?)");
        $ins->execute([$uid, $form['title'], $form['body'], $form['category']]);
        $_SESSION['flash'] = "Your topic was posted.";
        header("Location: topic.php?id=" . (int)$pdo->lastInsertId());
        exit;
    }
}

require 'header.php';
?>
<div class="tp-wrap">
  <div class="tp-label">Community forum</div>
  <h1 class="tp-title">Start a new topic</h1>

  <div class="tp-card">
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-danger py-2"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
      <div class="mb-3">
        <label class="form-label fw-bold" for="title">Title</label>
        <input type="text" class="form-control" id="title" name="title" maxlength="150" value="<?= e($form['title']) ?>" placeholder="What would you like to talk about?">
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold" for="category">Category</label>
        <select class="form-select" id="category" name="category">
          <option value="">Choose a category</option>
          <?php foreach (interests_list() as $c): ?>
            <option value="<?= e($c) ?>" <?= $form['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold" for="body">Message</label>
        <textarea class="form-control" id="body" name="body" rows="6" maxlength="3000"><?= e($form['body']) ?></textarea>
        <div class="form-text">Please be kind and respectful. Do not share personal medical records or other people's private details. For emergencies, go to the hospital.</div>
      </div>
      <button type="submit" class="btn btn-azure px-4">Post topic</button>
      <a href="forum.php" class="btn btn-light ms-2">Cancel</a>
    </form>
  </div>
</div>
<?php require 'footer.php'; ?>