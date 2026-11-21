<?php
require_once 'db.php';
require_once 'functions.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . (($_SESSION['role'] ?? '') === 'admin' ? 'admin.php' : 'dashboard.php'));
    exit;
}

$errors = [];
$email = "";
$notice = isset($_GET['logged_out']) ? "You have been logged out successfully." : "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = "Please enter your email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name, password, role, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'suspended') {
                $errors[] = "This account has been suspended. Please contact the hospital.";
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];
                header("Location: " . ($user['role'] === 'admin' ? 'admin.php' : 'dashboard.php'));
                exit;
            }
        } else {
            $errors[] = "Invalid email or password.";
        }
    }
}

require 'header.php';
?>
<section class="auth-stage">
  <div class="auth-frame" style="background-image: url('images/doctor2.jpg');">

    <div class="auth-left">
      <h1>Welcome back to your health community</h1>
      <div class="auth-switch">
        New here?
        <a href="signup.php">Create an account <i class="bi bi-arrow-right-circle ms-1"></i></a>
      </div>
    </div>

    <div class="auth-panel">
      <div class="auth-brand">
        <img src="logo.png" alt="Choithram Memorial Hospital logo">
      </div>

      <div class="auth-form-wrap">
        <h3>Login</h3>

        <?php if ($notice): ?>
          <div class="alert alert-success py-2"><?= e($notice) ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger py-2"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" novalidate>
          <div class="form-floating mb-3">
            <input type="email" class="form-control" id="email" name="email" placeholder="Email" value="<?= e($email) ?>">
            <label for="email">Email</label>
          </div>
          <div class="form-floating mb-3">
            <input type="password" class="form-control" id="password" name="password" placeholder="Password">
            <label for="password">Password</label>
          </div>
          <button type="submit" class="btn-white-pill">Login</button>
        </form>
      </div>
    </div>

  </div>
</section>
<?php require 'footer.php'; ?>