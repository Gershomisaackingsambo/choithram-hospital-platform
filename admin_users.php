<?php
require_once 'db.php';
require_once 'functions.php';
require_admin();

$me   = (int)$_SESSION['user_id'];
$back = 'admin_users.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['flash_err'] = "Your session expired. Please try again.";
        header("Location: $back");
        exit;
    }
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    $st = $pdo->prepare("SELECT id, full_name, role, status, is_mentor, profile_picture FROM users WHERE id = ?");
    $st->execute([$id]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        $_SESSION['flash_err'] = "That user no longer exists.";
    } elseif ($id === $me && in_array($action, ['toggle_status', 'toggle_admin', 'delete'], true)) {
        $_SESSION['flash_err'] = "You cannot suspend, demote or delete your own account.";
    } else {
        switch ($action) {
            case 'toggle_status':
                $new = $u['status'] === 'active' ? 'suspended' : 'active';
                $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new, $id]);
                $_SESSION['flash'] = $u['full_name'] . ($new === 'suspended' ? " was suspended." : " was reactivated.");
                break;
            case 'toggle_admin':
                $new = $u['role'] === 'admin' ? 'member' : 'admin';
                $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new, $id]);
                $_SESSION['flash'] = $u['full_name'] . ($new === 'admin' ? " is now an administrator." : " is no longer an administrator.");
                break;
            case 'toggle_mentor':
                $new = $u['is_mentor'] ? 0 : 1;
                $pdo->prepare("UPDATE users SET is_mentor = ? WHERE id = ?")->execute([$new, $id]);
                $_SESSION['flash'] = $u['full_name'] . ($new ? " is now a mentor." : " is no longer a mentor.");
                break;
            case 'delete':
                if ($u['profile_picture'] && strpos($u['profile_picture'], 'uploads/profiles/') === 0 && is_file(__DIR__ . '/' . $u['profile_picture'])) {
                    unlink(__DIR__ . '/' . $u['profile_picture']);
                }
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                $_SESSION['flash'] = $u['full_name'] . " was deleted.";
                break;
        }
    }
    header("Location: $back");
    exit;
}

$q      = trim($_GET['q'] ?? '');
$role   = $_GET['role'] ?? 'all';
$status = $_GET['status'] ?? 'all';

$where  = "1=1";
$params = [];
if ($q !== '') {
    $where .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($role === 'admin')       $where .= " AND u.role = 'admin'";
elseif ($role === 'member')  $where .= " AND u.role = 'member'";
elseif ($role === 'mentor')  $where .= " AND u.is_mentor = 1";
else $role = 'all';
if ($status === 'active' || $status === 'suspended') {
    $where .= " AND u.status = '$status'";
} else {
    $status = 'all';
}

$stmt = $pdo->prepare(
    "SELECT u.*,
       (SELECT COUNT(*) FROM event_registrations r WHERE r.user_id = u.id AND r.status <> 'cancelled') AS events_n
     FROM users u
     WHERE $where
     ORDER BY u.created_at DESC
     LIMIT 200"
);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totals = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(role = 'admin') AS admins,
            SUM(is_mentor = 1) AS mentors,
            SUM(status = 'suspended') AS suspended
     FROM users"
)->fetch(PDO::FETCH_ASSOC);

$adminPage = 'users';
$pageTitle = 'Users';
$pageSub   = 'Manage members, roles and access';
require 'admin_header.php';
?>

<div class="ad-stats">
  <div class="ad-stat"><div class="ico"><i class="bi bi-people"></i></div><div><div class="num"><?= (int)$totals['total'] ?></div><div class="lbl">All users</div></div></div>
  <div class="ad-stat"><div class="ico"><i class="bi bi-shield-lock"></i></div><div><div class="num"><?= (int)$totals['admins'] ?></div><div class="lbl">Administrators</div></div></div>
  <div class="ad-stat"><div class="ico"><i class="bi bi-award"></i></div><div><div class="num"><?= (int)$totals['mentors'] ?></div><div class="lbl">Mentors</div></div></div>
  <div class="ad-stat"><div class="ico"><i class="bi bi-slash-circle"></i></div><div><div class="num"><?= (int)$totals['suspended'] ?></div><div class="lbl">Suspended</div></div></div>
</div>

<form method="get" class="ad-filter">
  <input class="ad-input" type="text" name="q" placeholder="Search name or email" value="<?= e($q) ?>">
  <select class="ad-input" name="role" onchange="this.form.submit()">
    <option value="all"    <?= $role === 'all' ? 'selected' : '' ?>>All roles</option>
    <option value="member" <?= $role === 'member' ? 'selected' : '' ?>>Members</option>
    <option value="mentor" <?= $role === 'mentor' ? 'selected' : '' ?>>Mentors</option>
    <option value="admin"  <?= $role === 'admin' ? 'selected' : '' ?>>Administrators</option>
  </select>
  <select class="ad-input" name="status" onchange="this.form.submit()">
    <option value="all"       <?= $status === 'all' ? 'selected' : '' ?>>Any status</option>
    <option value="active"    <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
    <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
  </select>
  <button class="ad-btn" type="submit"><i class="bi bi-search"></i> Search</button>
</form>

<div class="ad-card">
  <?php if (!$users): ?>
    <div class="ad-empty">No users match your search.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="ad-table">
      <thead><tr><th>User</th><th>Contact</th><th>Role</th><th>Status</th><th>Events</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): $isMe = ((int)$u['id'] === $me); ?>
        <tr>
          <td>
            <div class="ad-person">
              <?php if ($u['profile_picture'] && is_file(__DIR__ . '/' . $u['profile_picture'])): ?>
                <img class="ad-av" src="<?= e($u['profile_picture']) ?>" alt="">
              <?php else: ?>
                <span class="ad-init"><?= e(initials($u['full_name'])) ?></span>
              <?php endif; ?>
              <div>
                <div class="fw-semibold"><?= e($u['full_name']) ?> <?php if ($isMe): ?><span class="ad-pill p-blue">You</span><?php endif; ?></div>
                <div class="sub"><?= e($u['email']) ?></div>
              </div>
            </div>
          </td>
          <td><?= e($u['phone']) ?></td>
          <td>
            <?php if ($u['role'] === 'admin'): ?><span class="ad-pill p-purple">Admin</span>
            <?php else: ?><span class="ad-pill p-grey">Member</span><?php endif; ?>
            <?php if ($u['is_mentor']): ?><span class="ad-pill p-orange">Mentor</span><?php endif; ?>
          </td>
          <td><span class="ad-pill <?= $u['status'] === 'active' ? 'p-green' : 'p-red' ?>"><?= e(ucfirst($u['status'])) ?></span></td>
          <td><?= (int)$u['events_n'] ?></td>
          <td class="sub"><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
          <td>
            <div class="ad-actions">
              <?= ad_btn_form('toggle_mentor', ['id' => $u['id']], $u['is_mentor'] ? 'bi-award-fill' : 'bi-award', $u['is_mentor'] ? 'Remove mentor' : 'Make mentor') ?>
              <?php if (!$isMe): ?>
                <?= ad_btn_form('toggle_admin', ['id' => $u['id']], $u['role'] === 'admin' ? 'bi-shield-x' : 'bi-shield-lock', $u['role'] === 'admin' ? 'Remove admin' : 'Make admin', $u['role'] === 'admin' ? 'Remove administrator rights from this user?' : 'Give this user administrator rights?') ?>
                <?= ad_btn_form('toggle_status', ['id' => $u['id']], $u['status'] === 'active' ? 'bi-slash-circle' : 'bi-check-circle', $u['status'] === 'active' ? 'Suspend' : 'Reactivate', $u['status'] === 'active' ? 'Suspend this user? They will not be able to log in.' : '') ?>
                <?= ad_btn_form('delete', ['id' => $u['id']], 'bi-trash', 'Delete', 'Delete this user and everything they posted? This cannot be undone.', 'danger') ?>
              <?php endif; ?>
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