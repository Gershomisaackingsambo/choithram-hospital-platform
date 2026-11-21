<?php require_once __DIR__ . '/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Choithram Memorial Hospital</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
  <link rel="stylesheet" href="events.css?v=<?= filemtime(__DIR__ . '/events.css') ?>">
  <link rel="stylesheet" href="community.css?v=<?= filemtime(__DIR__ . '/community.css') ?>">
  <link rel="stylesheet" href="dashboard.css?v=<?= filemtime(__DIR__ . '/dashboard.css') ?>">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark bg-azure">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="logo.png" alt="Choithram Memorial Hospital logo" height="50" class="me-2">
      <span class="fw-bold">Choithram Memorial Hospital</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <?php if (isset($_SESSION['user_id'])): ?>
          <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>

          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Events</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="events.php">Upcoming events</a></li>
              <li><a class="dropdown-item" href="my_events.php">My events</a></li>
            </ul>
          </li>

          <li class="nav-item"><a class="nav-link" href="resources.php">Resources</a></li>

          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Community</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="forum.php">Forum</a></li>
              <li><a class="dropdown-item" href="mentors.php">Find a mentor</a></li>
              <li><a class="dropdown-item" href="my_mentorship.php">My mentorship</a></li>
            </ul>
          </li>

          <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
          <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <li class="nav-item"><a class="nav-link" href="admin.php">Admin</a></li>
          <?php endif; ?>
          <li class="nav-item"><a class="btn btn-light ms-lg-3" href="logout.php">Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="index.php#about">About</a></li>
          <li class="nav-item"><a class="nav-link" href="index.php#services">Services</a></li>
          <li class="nav-item"><a class="nav-link" href="index.php#contact">Contact</a></li>
          <li class="nav-item"><a class="nav-link" href="signup.php">Sign Up</a></li>
          <li class="nav-item"><a class="btn btn-light ms-lg-3" href="login.php">Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<main class="flex-grow-1">