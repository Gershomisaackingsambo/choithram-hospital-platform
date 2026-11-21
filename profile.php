<?php
require_once 'db.php';
require_once 'functions.php';
require_login();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$interests = parse_interests($user['interests']);

// Learning progress: completed resources per category
$prog = $pdo->prepare(
    "SELECT r.category, COUNT(r.id) AS total, COUNT(rp.id) AS done
     FROM resources r
     LEFT JOIN resource_progress rp ON rp.resource_id = r.id AND rp.user_id = ?
     GROUP BY r.category
     ORDER BY done DESC, total DESC
     LIMIT 5"
);
$prog->execute([$user['id']]);
$progress = $prog->fetchAll(PDO::FETCH_ASSOC);

// Upcoming events the member registered for
$ev = $pdo->prepare(
    "SELECT e.title, e.event_date, e.location
     FROM event_registrations er
     JOIN events e ON e.id = er.event_id
     WHERE er.user_id = ? AND er.status = 'registered' AND e.event_date >= NOW()
     ORDER BY e.event_date ASC
     LIMIT 3"
);
$ev->execute([$user['id']]);
$events = $ev->fetchAll(PDO::FETCH_ASSOC);

require 'header.php';
?>
<div class="container py-4">

  <?php if ($flash): ?>
    <div class="alert alert-success"><?= e($flash) ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Left profile card -->
    <div class="col-lg-4">
      <div class="card profile-side p-4 h-100">
        <div class="text-center mb-3">
          <img src="<?= e(avatar_url($user['profile_picture'])) ?>" alt="Profile picture" class="avatar-lg">
          <h4 class="mt-3 mb-1"><?= e($user['full_name']) ?></h4>
          <?php if ($user['role'] === 'admin'): ?>
            <span class="badge bg-dark">Administrator</span>
          <?php elseif ($user['is_mentor']): ?>
            <span class="badge bg-warning text-dark"><i class="bi bi-award-fill me-1"></i>Mentor</span>
          <?php else: ?>
            <span class="badge bg-primary">Member</span>
          <?php endif; ?>
        </div>

        <div class="detail-row"><span>Age</span><span><?= e(age_from_dob($user['date_of_birth'])) ?></span></div>
        <div class="detail-row"><span>Gender</span><span><?= e($user['gender']) ?></span></div>
        <div class="detail-row"><span>Phone</span><span><?= e($user['phone']) ?></span></div>
        <div class="detail-row"><span>Email</span><span><?= e($user['email']) ?></span></div>
        <div class="detail-row"><span>Member since</span><span><?= e(date('d M Y', strtotime($user['created_at']))) ?></span></div>

        <div class="tip-card p-3 mt-4">
          <strong><i class="bi bi-lightbulb-fill me-1"></i> Health tip of the day</strong>
          <p class="mb-0 mt-1 small"><?= e(tip_of_the_day()) ?></p>
        </div>

        <a href="profile_edit.php" class="btn btn-azure mt-4"><i class="bi bi-pencil-square me-1"></i> Edit profile</a>
      </div>
    </div>

    <!-- Right content -->
    <div class="col-lg-8">
      <div class="card dash-card p-4 mb-4">
        <h5 class="section-title">About</h5>
        <p class="mb-0"><?= $user['bio'] ? nl2br(e($user['bio'])) : '<span class="text-muted">No bio yet. Use Edit profile to tell the community about yourself.</span>' ?></p>
      </div>

      <div class="card dash-card p-4 mb-4">
        <h5 class="section-title">Interests</h5>
        <?php if ($interests): ?>
          <?php foreach ($interests as $i): ?><span class="chip-tag"><?= e($i) ?></span><?php endforeach; ?>
        <?php else: ?>
          <span class="text-muted">No interests selected yet. Add some in Edit profile.</span>
        <?php endif; ?>
      </div>

      <div class="row g-4">
        <div class="col-md-6">
          <div class="card dash-card p-4 h-100">
            <h5 class="section-title">Learning progress</h5>
            <?php if ($progress): ?>
              <?php foreach ($progress as $p): $pct = $p['total'] > 0 ? round(($p['done'] / $p['total']) * 100) : 0; ?>
                <div class="mb-3">
                  <div class="d-flex justify-content-between small">
                    <span><?= e($p['category']) ?></span>
                    <span><?= (int)$p['done'] ?>/<?= (int)$p['total'] ?></span>
                  </div>
                  <div class="progress"><div class="progress-bar" style="width: <?= $pct ?>%"></div></div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <span class="text-muted">Your progress will show here once learning resources are available.</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card dash-card p-4 h-100">
            <h5 class="section-title">My upcoming events</h5>
            <?php if ($events): ?>
              <?php foreach ($events as $x): ?>
                <div class="mb-3">
                  <strong><?= e($x['title']) ?></strong><br>
                  <small class="text-muted">
                    <i class="bi bi-calendar-event me-1"></i><?= e(date('d M Y, g:i A', strtotime($x['event_date']))) ?>
                    <br><i class="bi bi-geo-alt me-1"></i><?= e($x['location']) ?>
                  </small>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <span class="text-muted">You haven't registered for any upcoming events yet.</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require 'footer.php'; ?>