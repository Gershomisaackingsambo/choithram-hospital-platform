<?php
require_once 'db.php';
require_once 'functions.php';
require_admin();

$id      = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$event   = null;
$taken   = 0;

if ($editing) {
    $st = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $st->execute([$id]);
    $event = $st->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        $_SESSION['flash_err'] = "That event no longer exists.";
        header("Location: admin_events.php");
        exit;
    }
    $t = $pdo->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status IN ('registered','attended')");
    $t->execute([$id]);
    $taken = (int)$t->fetchColumn();
}

$form = [
    'title'       => $event['title'] ?? '',
    'category'    => $event['category'] ?? '',
    'location'    => $event['location'] ?? '',
    'event_date'  => $event ? date('Y-m-d\TH:i', strtotime($event['event_date'])) : '',
    'capacity'    => $event['capacity'] ?? 50,
    'description' => $event['description'] ?? '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = "Your session expired. Please try again.";
    }
    $form['title']       = trim($_POST['title'] ?? '');
    $form['category']    = $_POST['category'] ?? '';
    $form['location']    = trim($_POST['location'] ?? '');
    $form['event_date']  = trim($_POST['event_date'] ?? '');
    $form['capacity']    = (int)($_POST['capacity'] ?? 0);
    $form['description'] = trim($_POST['description'] ?? '');

    if (mb_strlen($form['title']) < 5 || mb_strlen($form['title']) > 150)     $errors[] = "The title must be 5 to 150 characters.";
    if (!in_array($form['category'], interests_list(), true))                  $errors[] = "Please choose a category.";
    if (mb_strlen($form['location']) < 3 || mb_strlen($form['location']) > 150) $errors[] = "Please enter a location (3 to 150 characters).";

    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $form['event_date']);
    if (!$dt || $dt->format('Y-m-d\TH:i') !== $form['event_date'])             $errors[] = "Please choose a valid date and time.";

    if ($form['capacity'] < 1 || $form['capacity'] > 1000)                     $errors[] = "Capacity must be between 1 and 1000.";
    elseif ($editing && $form['capacity'] < $taken)                            $errors[] = "Capacity cannot be lower than the $taken people already registered.";

    if (mb_strlen($form['description']) < 10 || mb_strlen($form['description']) > 2000) $errors[] = "The description must be 10 to 2000 characters.";

    if (!$errors) {
        $when = $dt->format('Y-m-d H:i:s');
        if ($editing) {
            $pdo->prepare("UPDATE events SET title = ?, description = ?, category = ?, location = ?, event_date = ?, capacity = ? WHERE id = ?")
                ->execute([$form['title'], $form['description'], $form['category'], $form['location'], $when, $form['capacity'], $id]);
            $_SESSION['flash'] = "Event updated.";
        } else {
            $pdo->prepare("INSERT INTO events (title, description, category, location, event_date, capacity, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$form['title'], $form['description'], $form['category'], $form['location'], $when, $form['capacity'], $_SESSION['user_id']]);
            $_SESSION['flash'] = "Event added.";
        }
        header("Location: admin_events.php");
        exit;
    }
}

$adminPage   = 'events';
$pageTitle   = $editing ? 'Edit event' : 'Add event';
$pageSub     = $editing ? 'Update the details of this event' : 'Create a new workshop or awareness session';
$pageActions = '<a class="ad-btn light" href="admin_events.php"><i class="bi bi-arrow-left"></i> Back to events</a>';
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
        <label for="title">Event title</label>
        <input type="text" class="form-control" id="title" name="title" maxlength="150" value="<?= e($form['title']) ?>" placeholder="e.g. Malaria Prevention Workshop">
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
        <label for="location">Location</label>
        <input type="text" class="form-control" id="location" name="location" maxlength="150" value="<?= e($form['location']) ?>" placeholder="e.g. Main Hall, Freetown">
      </div>
    </div>

    <div class="ad-section"><i class="bi bi-calendar-event"></i> Date and capacity</div>
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <label for="event_date">Date and time</label>
        <input type="datetime-local" class="form-control" id="event_date" name="event_date" value="<?= e($form['event_date']) ?>">
        <div class="hint">You can use a past date to record an event that already took place.</div>
      </div>
      <div class="col-md-6">
        <label for="capacity">Capacity (places)</label>
        <input type="number" class="form-control" id="capacity" name="capacity" min="1" max="1000" value="<?= (int)$form['capacity'] ?>">
        <?php if ($editing): ?><div class="hint"><?= $taken ?> already registered.</div><?php endif; ?>
      </div>
    </div>

    <div class="ad-section"><i class="bi bi-card-text"></i> Description</div>
    <div class="mb-4">
      <textarea class="form-control" name="description" rows="5" maxlength="2000" placeholder="What will members learn or do at this event?"><?= e($form['description']) ?></textarea>
    </div>

    <button type="submit" class="ad-btn"><i class="bi bi-check2-circle"></i> <?= $editing ? 'Save changes' : 'Add event' ?></button>
    <a href="admin_events.php" class="ad-btn light ms-2">Cancel</a>
  </form>
</div>
<?php require 'admin_footer.php'; ?>