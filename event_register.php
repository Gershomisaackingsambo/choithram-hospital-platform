<?php
require_once 'db.php';
require_once 'functions.php';
require_login();

$uid = (int)$_SESSION['user_id'];
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    header("Location: events.php");
    exit;
}

$u = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
$u->execute([$uid]);
$user = $u->fetch(PDO::FETCH_ASSOC);

$r = $pdo->prepare("SELECT * FROM event_registrations WHERE event_id = ? AND user_id = ?");
$r->execute([$id, $uid]);
$reg = $r->fetch(PDO::FETCH_ASSOC);

$t = $pdo->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status IN ('registered','attended')");
$t->execute([$id]);
$taken = (int)$t->fetchColumn();

$left     = max(0, (int)$event['capacity'] - $taken);
$isPast   = strtotime($event['event_date']) < time();
$isActive = $reg && in_array($reg['status'], ['registered', 'attended'], true);
$errors   = [];
$notes    = $reg['notes'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $notes = trim($_POST['notes'] ?? '');
        if ($isPast)                      $errors[] = "This event has already taken place.";
        if ($isActive)                    $errors[] = "You are already registered for this event.";
        if (!$isPast && !$isActive && $left <= 0) $errors[] = "Sorry, this event is full.";
        if (empty($_POST['confirm']))     $errors[] = "Please tick the confirmation box.";
        if (mb_strlen($notes) > 255)      $errors[] = "Notes must be 255 characters or fewer.";

        if (!$errors) {
            $ins = $pdo->prepare(
                "INSERT INTO event_registrations (event_id, user_id, status, notes)
                 VALUES (?, ?, 'registered', ?)
                 ON DUPLICATE KEY UPDATE status = 'registered', notes = VALUES(notes), registered_at = NOW()"
            );
            $ins->execute([$id, $uid, $notes]);
            $_SESSION['flash'] = 'You are registered for "' . $event['title'] . '".';
            header("Location: my_events.php");
            exit;
        }
    } elseif ($action === 'cancel') {
        if ($reg && $reg['status'] === 'registered' && !$isPast) {
            $upd = $pdo->prepare("UPDATE event_registrations SET status = 'cancelled' WHERE event_id = ? AND user_id = ?");
            $upd->execute([$id, $uid]);
            $_SESSION['flash'] = 'Your registration for "' . $event['title'] . '" was cancelled.';
            header("Location: my_events.php?tab=cancelled");
            exit;
        }
        $errors[] = "This registration can no longer be cancelled.";
    }
}

require 'header.php';
?>
<div class="container">
  <div class="reg-wrap">

    <div class="reg-banner">
      <div class="reg-icons">
        <i class="bi bi-heart-fill" style="color:#e56b8f"></i>
        <i class="bi bi-heart-pulse-fill" style="color:#0B5ED7"></i>
        <i class="bi bi-heart-fill" style="color:#f2b84b"></i>
      </div>
      <div class="reg-contact">
        <img src="logo.png" alt="Choithram Memorial Hospital logo">
        Choithram Memorial Hospital &nbsp;|&nbsp; info@choithramhospital.sl &nbsp;|&nbsp; +232 76 000 000
        <div class="reg-banner-title">Event Registration Form</div>
      </div>
    </div>

    <div class="reg-body">
      <h2><?= e($event['title']) ?></h2>
      <p class="reg-sub">Please check your details and confirm your place. We will use them to manage attendance for this event.</p>

      <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger py-2"><?= e($err) ?></div>
      <?php endforeach; ?>

      <div class="reg-section">Attendee information</div>
      <div class="mb-3">
        <label class="reg-label">Full name</label>
        <span class="reg-hint">Taken from your profile</span>
        <input type="text" class="form-control" value="<?= e($user['full_name']) ?>" disabled>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="reg-label">Email</label>
          <span class="reg-hint">Taken from your profile</span>
          <input type="text" class="form-control" value="<?= e($user['email']) ?>" disabled>
        </div>
        <div class="col-md-6 mb-3">
          <label class="reg-label">Phone number</label>
          <span class="reg-hint">Update it in Edit profile if it has changed</span>
          <input type="text" class="form-control" value="<?= e($user['phone']) ?>" disabled>
        </div>
      </div>

      <div class="reg-section">Event details</div>
      <div class="reg-summary">
        <div><i class="bi bi-calendar-event me-2 text-azure"></i><strong><?= e(date('l, d F Y', strtotime($event['event_date']))) ?></strong> at <?= e(date('g:i A', strtotime($event['event_date']))) ?></div>
        <div class="mt-1"><i class="bi bi-geo-alt me-2 text-azure"></i><?= e($event['location']) ?></div>
        <div class="mt-1"><i class="bi bi-tag me-2 text-azure"></i><?= e($event['category']) ?></div>
        <div class="mt-1"><i class="bi bi-people me-2 text-azure"></i><?= $left ?> of <?= (int)$event['capacity'] ?> places left</div>
        <p class="mt-3 mb-0"><?= e($event['description']) ?></p>
      </div>

      <?php if ($isActive && $reg['status'] === 'registered' && !$isPast): ?>
        <div class="alert alert-success mt-4">You are registered for this event.</div>
        <form method="post" onsubmit="return confirm('Cancel your registration for this event?');">
          <input type="hidden" name="action" value="cancel">
          <button type="submit" class="btn btn-outline-danger w-100 py-2">Cancel my registration</button>
        </form>
      <?php elseif ($isActive): ?>
        <div class="alert alert-success mt-4">You attended or are registered for this event.</div>
      <?php elseif ($isPast): ?>
        <div class="alert alert-secondary mt-4">This event has already taken place.</div>
      <?php elseif ($left <= 0): ?>
        <div class="alert alert-warning mt-4">Sorry, this event is full.</div>
      <?php else: ?>
        <form method="post" novalidate>
          <input type="hidden" name="action" value="register">

          <div class="reg-section">Additional information</div>
          <div class="mb-3">
            <label class="reg-label" for="notes">Anything we should know?</label>
            <span class="reg-hint">Optional. For example access needs or a question for the speaker.</span>
            <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="255"><?= e($notes) ?></textarea>
          </div>

          <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" id="confirm" name="confirm" value="1">
            <label class="form-check-label" for="confirm">I confirm that my details are correct and that I plan to attend. <span class="text-danger">*</span></label>
          </div>

          <button type="submit" class="btn btn-azure w-100 py-2">Complete registration</button>
        </form>
      <?php endif; ?>

      <div class="text-center mt-4">
        <a href="events.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to events</a>
      </div>
    </div>
  </div>
</div>
<?php require 'footer.php'; ?>