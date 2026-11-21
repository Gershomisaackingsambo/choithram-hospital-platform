<?php
require_once 'db.php';
require_once 'functions.php';
require_login();

$uid = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$errors = [];
$tab = 'details';
$form = [
    'full_name' => $user['full_name'],
    'phone'     => $user['phone'],
    'gender'    => $user['gender'],
    'dob'       => $user['date_of_birth'] ?? '',
    'bio'       => $user['bio'] ?? '',
    'interests' => parse_interests($user['interests']),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- Update details ----------
    if ($action === 'details') {
        $tab = 'details';
        $form['full_name'] = trim($_POST['full_name'] ?? '');
        $form['phone']     = trim($_POST['phone'] ?? '');
        $form['gender']    = $_POST['gender'] ?? '';
        $form['dob']       = $_POST['dob'] ?? '';
        $form['bio']       = trim($_POST['bio'] ?? '');
        $form['interests'] = array_values(array_intersect(interests_list(), (array)($_POST['interests'] ?? [])));

        if ($form['full_name'] === '') $errors[] = "Full name is required.";
        if (!preg_match('/^\+?[0-9]{8,15}$/', $form['phone'])) $errors[] = "Phone number must be 8 to 15 digits (a leading + is allowed).";
        if (!in_array($form['gender'], ['Male', 'Female', 'Other'], true)) $errors[] = "Please select a valid gender.";
        if ($form['dob'] === '' || $form['dob'] > date('Y-m-d')) $errors[] = "Please enter a valid date of birth.";
        if (mb_strlen($form['bio']) > 500) $errors[] = "Bio must be 500 characters or fewer.";
        if (!$form['interests']) $errors[] = "Please choose at least one interest.";

        if (!$errors) {
            $upd = $pdo->prepare(
                "UPDATE users SET full_name = ?, phone = ?, gender = ?, date_of_birth = ?, bio = ?, interests = ? WHERE id = ?"
            );
            $upd->execute([
                $form['full_name'], $form['phone'], $form['gender'], $form['dob'],
                $form['bio'], implode(', ', $form['interests']), $uid
            ]);
            $_SESSION['full_name'] = $form['full_name'];
            $_SESSION['flash'] = "Profile updated successfully.";
            header("Location: profile.php");
            exit;
        }
    }

    // ---------- Upload picture ----------
    elseif ($action === 'picture') {
        $tab = 'picture';
        $file = $_FILES['picture'] ?? null;

        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = "Please choose an image first.";
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "The upload failed. Please try again with a smaller image.";
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = "The image must be 2 MB or smaller.";
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

            if (!isset($allowed[$mime])) {
                $errors[] = "Only JPG and PNG images are allowed.";
            } else {
                $dir = __DIR__ . '/uploads/profiles/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);

                $name = 'u' . $uid . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
                if (move_uploaded_file($file['tmp_name'], $dir . $name)) {
                    // Remove the old picture
                    $old = $user['profile_picture'];
                    if ($old && strpos($old, 'uploads/profiles/') === 0 && is_file(__DIR__ . '/' . $old)) {
                        unlink(__DIR__ . '/' . $old);
                    }
                    $upd = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $upd->execute(['uploads/profiles/' . $name, $uid]);
                    $_SESSION['flash'] = "Profile picture updated.";
                    header("Location: profile.php");
                    exit;
                } else {
                    $errors[] = "Could not save the image. Check that the uploads/profiles folder exists.";
                }
            }
        }
    }

    // ---------- Change password ----------
    elseif ($action === 'password') {
        $tab = 'password';
        $current = $_POST['current'] ?? '';
        $new     = $_POST['new'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            $errors[] = "Please fill in all three password fields.";
        } else {
            if (!password_verify($current, $user['password'])) $errors[] = "Your current password is incorrect.";
            if (strlen($new) < 8) $errors[] = "The new password must be at least 8 characters.";
            if ($new !== $confirm) $errors[] = "The new passwords do not match.";
        }

        if (!$errors) {
            $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            $_SESSION['flash'] = "Password changed successfully.";
            header("Location: profile.php");
            exit;
        }
    }
}

require 'header.php';
?>
<div class="container py-4" style="max-width: 800px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="text-azure mb-0">Edit profile</h3>
    <a href="profile.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back to profile</a>
  </div>

  <div class="card dash-card p-4">
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-danger py-2"><?= e($err) ?></div>
    <?php endforeach; ?>

    <ul class="nav nav-tabs mb-4">
      <li class="nav-item"><button class="nav-link <?= $tab === 'details' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-details" type="button">Details</button></li>
      <li class="nav-item"><button class="nav-link <?= $tab === 'picture' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-picture" type="button">Profile picture</button></li>
      <li class="nav-item"><button class="nav-link <?= $tab === 'password' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-password" type="button">Password</button></li>
    </ul>

    <div class="tab-content">

      <!-- Details -->
      <div class="tab-pane fade <?= $tab === 'details' ? 'show active' : '' ?>" id="tab-details">
        <form method="post" novalidate>
          <input type="hidden" name="action" value="details">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Full name</label>
              <input type="text" class="form-control" name="full_name" value="<?= e($form['full_name']) ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Email (used to log in)</label>
              <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Phone</label>
              <input type="tel" class="form-control" name="phone" value="<?= e($form['phone']) ?>">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Gender</label>
              <select class="form-select" name="gender">
                <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                  <option value="<?= e($g) ?>" <?= $form['gender'] === $g ? 'selected' : '' ?>><?= e($g) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Date of birth</label>
              <input type="date" class="form-control" name="dob" max="<?= date('Y-m-d') ?>" value="<?= e($form['dob']) ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Bio <small class="text-muted">(max 500 characters)</small></label>
            <textarea class="form-control" name="bio" rows="4"><?= e($form['bio']) ?></textarea>
          </div>
          <label class="form-label">Interests</label>
          <div class="mb-3">
            <?php foreach (interests_list() as $i): ?>
              <label class="chip">
                <input type="checkbox" name="interests[]" value="<?= e($i) ?>" <?= in_array($i, $form['interests'], true) ? 'checked' : '' ?>>
                <span><?= e($i) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="btn btn-azure">Save changes</button>
        </form>
      </div>

      <!-- Picture -->
      <div class="tab-pane fade <?= $tab === 'picture' ? 'show active' : '' ?>" id="tab-picture">
        <div class="text-center mb-3">
          <img src="<?= e(avatar_url($user['profile_picture'])) ?>" alt="Current picture" class="avatar-lg">
        </div>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="picture">
          <div class="mb-3">
            <label class="form-label">Choose a JPG or PNG image (max 2 MB)</label>
            <input type="file" class="form-control" name="picture" accept="image/png, image/jpeg">
          </div>
          <button type="submit" class="btn btn-azure">Upload picture</button>
        </form>
      </div>

      <!-- Password -->
      <div class="tab-pane fade <?= $tab === 'password' ? 'show active' : '' ?>" id="tab-password">
        <form method="post" novalidate>
          <input type="hidden" name="action" value="password">
          <div class="mb-3">
            <label class="form-label">Current password</label>
            <input type="password" class="form-control" name="current">
          </div>
          <div class="mb-3">
            <label class="form-label">New password</label>
            <input type="password" class="form-control" name="new">
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm new password</label>
            <input type="password" class="form-control" name="confirm">
          </div>
          <button type="submit" class="btn btn-azure">Change password</button>
        </form>
      </div>

    </div>
  </div>
</div>
<?php require 'footer.php'; ?>