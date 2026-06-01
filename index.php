<?php
include_once 'includes/config.php';

/* Landing page stylesheet */
$page_css   = '<link rel="stylesheet" href="assets/landing.css?v=4">';
$body_class = 'landing';

include 'includes/header.php';
?>

<div class="wrap fade-in">
  <!-- HERO SECTION -->
  <section class="hero card">
    <div class="hero-icon">
      <img src="assets/lostfound-logo.jpg" alt="Lost &amp; Found Icon" width="500" height="400" />
    </div>
    <h1 class="title gradient-text">Lost &amp; Found </h1>
    <p class="subtitle">Effortlessly report, search, or reclaim lost items.<br>
      Get quick help from our smart assistant when you log in!</p>
    <div class="cta-row">
      <a class="btn primary" href="login.php" title="Login to your account">Login</a>
      <a class="btn success" href="register.php" title="Create a new account">Register</a>
    </div>
    <div class="crowd">
      <img src="assets/bg-overlay.png" alt="Users" class="crowd-img" width="100" height="50"/>
      <span class="community">Trusted by <strong>2,500+</strong> students &amp; staff</span>
    </div>
  </section>

  <!-- Preview/Features Section -->
  <section class="features card">
    <div class="features-row">
      <div class="feat">
        <div class="feat-icon">
          <img src="assets/icon-search.png" alt="AI Search" width="80" height="50">
        </div>
        <h3>AI-powered Search</h3>
        <p>Describe your lost item or what you&rsquo;ve found &mdash; our assistant helps you match instantly.</p>
      </div>
      <div class="feat">
        <div class="feat-icon">
          <img src="assets/icon-notify.png" alt="Notifications" width="50" height="50">
        </div>
        <h3>Status Updates</h3>
        <p>Stay in the loop as your reports, claims or matches progress. Get notified quickly.</p>
      </div>
      <div class="feat">
        <div class="feat-icon">
          <img src="assets/icon-safe.png" alt="Safe Exchange" width="60" height="40">
        </div>
        <h3>Safe Handover</h3>
        <p>Arrange pickups securely on campus. Protect privacy for both parties at all times.</p>
      </div>
    </div>
  </section>

  <!-- AI PREVIEW (Optional) -->
  <section class="chat-preview card">
    <div class="preview-head">
      <h2>Try our Chatbot Assistant</h2>
      <span class="hint">Instantly available after you log in</span>
    </div>
    <div class="preview-box">
      <p><strong>Chatbot:</strong> Hi! What did you lose or find today?</p>
      <p class="bubble"><strong>User:</strong> I lost a set of keys with a blue tag near the library.</p>
      <p class="bubble bot"><strong>Chatbot:</strong> Searching matches... <br><em>2 possible matches found.</em></p>
    </div>
  </section>
</div>

<footer class="footer-tip">
  <div>
    <span>💡</span> <strong>Tip:</strong> Your data is private and only visible to verified users.
  </div>

</footer>

<?php // Optionally include footer.php if available ?>