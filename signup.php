<?php
require_once 'db.php';
require_once 'functions.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$errors = [];
$success = false;
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'gender' => '', 'dob' => '', 'interests' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['email']     = trim($_POST['email'] ?? '');
    $old['phone']     = trim($_POST['phone'] ?? '');
    $old['gender']    = $_POST['gender'] ?? '';
    $old['dob']       = $_POST['dob'] ?? '';
    $old['interests'] = array_values(array_intersect(interests_list(), (array)($_POST['interests'] ?? [])));
    $password         = $_POST['password'] ?? '';
    $confirm          = $_POST['confirm'] ?? '';

    if ($old['full_name'] === '') $errors[] = "Full name is required.";
    if ($old['email'] === '')     $errors[] = "Email is required.";
    if ($old['phone'] === '')     $errors[] = "Phone number is required.";
    if ($old['gender'] === '')    $errors[] = "Please select your gender.";
    if ($old['dob'] === '')       $errors[] = "Date of birth is required.";
    if (!$old['interests'])       $errors[] = "Please choose at least one interest.";
    if ($password === '')         $errors[] = "Please enter your password again.";

    if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    if ($old['phone'] !== '' && !preg_match('/^\+?[0-9]{8,15}$/', $old['phone'])) {
        $errors[] = "Phone number must be 8 to 15 digits (a leading + is allowed).";
    }
    if ($old['gender'] !== '' && !in_array($old['gender'], ['Male', 'Female', 'Other'], true)) {
        $errors[] = "Please select a valid gender.";
    }
    if ($old['dob'] !== '' && $old['dob'] > date('Y-m-d')) {
        $errors[] = "Date of birth cannot be in the future.";
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    if ($old['email'] !== '' && filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$old['email']]);
        if ($check->fetch()) {
            $errors[] = "That email is already registered.";
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $insert = $pdo->prepare(
            "INSERT INTO users (full_name, email, phone, gender, date_of_birth, interests, password)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $insert->execute([
            $old['full_name'], $old['email'], $old['phone'], $old['gender'],
            $old['dob'], implode(', ', $old['interests']), $hash
        ]);
        $success = true;
        $old = ['full_name' => '', 'email' => '', 'phone' => '', 'gender' => '', 'dob' => '', 'interests' => []];
    }
}

require 'header.php';
?>
<section class="auth-stage">
  <div class="auth-frame" style="background-image: url('images/doctor2.jpg');">

    <div class="auth-left">
      <h1>It's time to take charge of your health</h1>
      <div class="auth-switch">
        Already a member?
        <a href="login.php">Sign in <i class="bi bi-arrow-right-circle ms-1"></i></a>
      </div>
    </div>

    <div class="auth-panel">
      <div class="auth-brand">
        <img src="logo.png" alt="Choithram Memorial Hospital logo">
      </div>

      <div class="auth-form-wrap">
        <h3>Sign up</h3>

        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger py-2"><?= e($err) ?></div>
        <?php endforeach; ?>
        <?php if ($success): ?>
          <div class="alert alert-success py-2">Account created! You can now <a href="login.php">log in</a>.</div>
        <?php endif; ?>

        <form method="post" novalidate>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="full_name" name="full_name" placeholder="Full name" value="<?= e($old['full_name']) ?>">
            <label for="full_name">Full name</label>
          </div>
          <div class="form-floating mb-3">
            <input type="email" class="form-control" id="email" name="email" placeholder="Email" value="<?= e($old['email']) ?>">
            <label for="email">Email</label>
          </div>
          <div class="form-floating mb-3">
            <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone" value="<?= e($old['phone']) ?>">
            <label for="phone">Phone (e.g. +23276123456)</label>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <div class="form-floating">
                <select class="form-select" id="gender" name="gender">
                  <option value=""></option>
                  <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                    <option value="<?= e($g) ?>" <?= $old['gender'] === $g ? 'selected' : '' ?>><?= e($g) ?></option>
                  <?php endforeach; ?>
                </select>
                <label for="gender">Gender</label>
              </div>
            </div>
            <div class="col-6">
              <div class="form-floating">
                <input type="date" class="form-control" id="dob" name="dob" placeholder="Date of birth" max="<?= date('Y-m-d') ?>" value="<?= e($old['dob']) ?>">
                <label for="dob">Date of birth</label>
              </div>
            </div>
          </div>

          <span class="auth-label">Your health interests</span>
          <div class="mb-2">
            <?php foreach (interests_list() as $i): ?>
              <label class="chip">
                <input type="checkbox" name="interests[]" value="<?= e($i) ?>" <?= in_array($i, $old['interests'], true) ? 'checked' : '' ?>>
                <span><?= e($i) ?></span>
              </label>
            <?php endforeach; ?>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <div class="form-floating">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password">
                <label for="password">Password</label>
              </div>
            </div>
            <div class="col-6">
              <div class="form-floating">
                <input type="password" class="form-control" id="confirm" name="confirm" placeholder="Confirm password">
                <label for="confirm">Confirm password</label>
              </div>
            </div>
          </div>

          <button type="submit" class="btn-white-pill">Create account</button>
        </form>
      </div>
    </div>

  </div>
</section>
<?php require 'footer.php'; ?>