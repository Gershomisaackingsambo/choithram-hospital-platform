<?php require 'header.php'; ?>

<!-- Hero -->
<section class="hero py-5">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6 mb-5 mb-lg-0">
        <h1 class="hero-title">Quality Care for<br>Your Whole Family</h1>
        <p class="lead text-secondary mt-3">
          At Choithram Memorial Hospital, our caring doctors and nurses are here to keep you healthy,
          with check-ups, treatment and health advice you can trust.
        </p>
        <?php if (isset($_SESSION['user_id'])): ?>
          <a href="dashboard.php" class="btn btn-azure btn-lg mt-2">Go to Dashboard</a>
        <?php else: ?>
          <a href="signup.php" class="btn btn-azure btn-lg mt-2 me-2">Get Started</a>
          <a href="login.php" class="btn btn-outline-primary btn-lg mt-2">Login</a>
        <?php endif; ?>
        <p class="small text-muted mt-4 mb-0"><i class="bi bi-geo-alt-fill text-azure me-1"></i> Freetown, Sierra Leone</p>
      </div>

      <div class="col-lg-6">
        <div class="photo-cluster">
          <img src="images/doctor1.jpg" alt="Doctor smiling" class="photo photo-a">
          <img src="images/doctor2.jpg" alt="Doctor consulting a patient" class="photo photo-b">
          <img src="images/doctor3.jpg" alt="Hospital care" class="photo photo-c">
        </div>
      </div>
    </div>
  </div>
</section>

<!-- About -->
<section id="about" class="py-5 bg-white">
  <div class="container text-center">
    <h2 class="text-azure mb-3">About Us</h2>
    <p class="mx-auto" style="max-width: 700px;">
      Choithram Memorial Hospital is a hospital in Freetown focused on affordable, friendly and
      reliable healthcare. We believe good health starts with prevention, so we pair
      quality treatment with simple health education for every patient.
    </p>
  </div>
</section>

<!-- Services -->
<section id="services" class="py-5">
  <div class="container">
    <h2 class="text-azure text-center mb-4">Our Services</h2>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="card dash-card h-100 text-center p-3">
          <div class="icon-circle mx-auto"><i class="bi bi-heart-pulse"></i></div>
          <h5 class="mt-3">General Consultation</h5>
          <p class="text-muted mb-0">Check-ups and treatment for common illnesses.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card dash-card h-100 text-center p-3">
          <div class="icon-circle mx-auto"><i class="bi bi-shield-plus"></i></div>
          <h5 class="mt-3">Vaccination</h5>
          <p class="text-muted mb-0">Immunisation for children and adults.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card dash-card h-100 text-center p-3">
          <div class="icon-circle mx-auto"><i class="bi bi-eyedropper"></i></div>
          <h5 class="mt-3">Laboratory Tests</h5>
          <p class="text-muted mb-0">Fast and accurate diagnostic testing.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Contact -->
<section id="contact" class="py-5 bg-white">
  <div class="container text-center">
    <h2 class="text-azure mb-3">Contact Us</h2>
    <p class="mb-1"><i class="bi bi-geo-alt-fill text-azure me-2"></i>Freetown, Sierra Leone</p>
    <p class="mb-1"><i class="bi bi-telephone-fill text-azure me-2"></i>+232 76 000 000</p>
    <p class="mb-0"><i class="bi bi-envelope-fill text-azure me-2"></i>info@choithramhospital.sl</p>
  </div>
</section>

<?php require 'footer.php'; ?>