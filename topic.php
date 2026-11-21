<?php
require_once 'db.php';
require_once 'functions.php';
require_login();

$uid     = (int)$_SESSION['user_id'];
$isAdmin = (($_SESSION['role'] ?? '') === 'admin');
$id      = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT t.*, u.full_name, u.profile_picture, u.is_mentor
     FROM forum_topics t JOIN users u ON u.id = t.user_id
     WHERE t.id = ?"
);
$stmt->execute([$id]);
$topic = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$topic) {
    header("Location: forum.php");
    exit;
}

$errors = [];
$reply  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'reply') {
        $reply = trim($_POST['body'] ?? '');
        if (mb_strlen($reply) < 2)    $errors[] = "Please write a reply.";
        if (mb_strlen($reply) > 2000) $errors[] = "Replies must be 2000 characters or fewer.";
        if (!$errors) {
            $ins = $pdo->prepare("INSERT INTO forum_replies (topic_id, user_id, body) VALUES (?, ?, ?)");
            $ins->execute([$id, $uid, $reply]);
            $_SESSION['flash'] = "Your reply was posted.";
            header("Location: topic.php?id=$id#replies");
            exit;
        }
    } elseif ($action === 'delete_reply') {
        $rid = (int)($_POST['reply_id'] ?? 0);
        $r = $pdo->prepare("SELECT user_id FROM forum_replies WHERE id = ? AND topic_id = ?");
        $r->execute([$rid, $id]);
        $row = $r->fetch(PDO::FETCH_ASSOC);
        if ($row && ((int)$row['user_id'] === $uid || $isAdmin)) {
            $pdo->prepare("DELETE FROM forum_replies WHERE id = ?")->execute([$rid]);
            $_SESSION['flash'] = "Reply deleted.";
        }
        header("Location: topic.php?id=$id#replies");
        exit;
    } elseif ($action === 'delete_topic') {
        if ((int)$topic['user_id'] === $uid || $isAdmin) {
            $pdo->prepare("DELETE FROM forum_topics WHERE id = ?")->execute([$id]);
            $_SESSION['flash'] = "Topic deleted.";
            header("Location: forum.php");
            exit;
        }
    }
}

$rs = $pdo->prepare(
    "SELECT r.*, u.full_name, u.profile_picture, u.is_mentor
     FROM forum_replies r JOIN users u ON u.id = r.user_id
     WHERE r.topic_id = ?
     ORDER BY r.created_at ASC, r.id ASC"
);
$rs->execute([$id]);
$replies = $rs->fetchAll(PDO::FETCH_ASSOC);

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

[$c1, $c2, $icon] = category_style($topic['category']);

require 'header.php';
?>
<div class="tp-wrap">

  <a href="forum.php" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Back to forum</a>

  <div class="tp-label mt-3"><?= e($topic['category']) ?></div>
  <h1 class="tp-title"><?= e($topic['title']) ?></h1>

  <?php if ($flash): ?><div class="alert alert-success py-2"><?= e($flash) ?></div><?php endif; ?>

  <div class="tp-card">
    <div class="tp-head">
      <img class="avatar-sm" src="<?= e(avatar_url($topic['profile_picture'])) ?>" alt="">
      <div>
        <div class="tp-who">
          <?= e($topic['full_name']) ?>
          <?php if ($topic['is_mentor']): ?><span class="badge bg-warning text-dark ms-1">Mentor</span><?php endif; ?>
        </div>
        <div class="tp-when"><?= e(time_ago($topic['created_at'])) ?></div>
      </div>
      <span class="tp-icon ms-auto" style="color: <?= e($c1) ?>"><i class="bi <?= e($icon) ?>"></i></span>
    </div>
    <div class="tp-body"><?= nl2br(e($topic['body'])) ?></div>

    <?php if ((int)$topic['user_id'] === $uid || $isAdmin): ?>
      <form method="post" class="mt-3" onsubmit="return confirm('Delete this topic and all its replies?');">
        <input type="hidden" name="action" value="delete_topic">
        <button type="submit" class="tp-del"><i class="bi bi-trash me-1"></i>Delete topic</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="tp-replies-title" id="replies"><?= count($replies) ?> repl<?= count($replies) === 1 ? 'y' : 'ies' ?></div>

  <?php if (!$replies): ?>
    <div class="tp-card text-muted">No replies yet. Be the first to share your thoughts.</div>
  <?php endif; ?>

  <?php foreach ($replies as $r): ?>
    <div class="tp-card">
      <div class="tp-head mb-2">
        <img class="avatar-sm" src="<?= e(avatar_url($r['profile_picture'])) ?>" alt="">
        <div>
          <div class="tp-who">
            <?= e($r['full_name']) ?>
            <?php if ($r['is_mentor']): ?><span class="badge bg-warning text-dark ms-1">Mentor</span><?php endif; ?>
          </div>
          <div class="tp-when"><?= e(time_ago($r['created_at'])) ?></div>
        </div>
      </div>
      <div class="tp-body"><?= nl2br(e($r['body'])) ?></div>
      <?php if ((int)$r['user_id'] === $uid || $isAdmin): ?>
        <form method="post" class="mt-2" onsubmit="return confirm('Delete this reply?');">
          <input type="hidden" name="action" value="delete_reply">
          <input type="hidden" name="reply_id" value="<?= (int)$r['id'] ?>">
          <button type="submit" class="tp-del"><i class="bi bi-trash me-1"></i>Delete</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="tp-card">
    <h5 class="mb-3">Add your reply</h5>
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-danger py-2"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" novalidate>
      <input type="hidden" name="action" value="reply">
      <textarea class="form-control mb-3" name="body" rows="4" maxlength="2000" placeholder="Share your thoughts or experience..."><?= e($reply) ?></textarea>
      <button type="submit" class="btn btn-azure px-4">Post reply</button>
    </form>
  </div>

</div>
<?php require 'footer.php'; ?>