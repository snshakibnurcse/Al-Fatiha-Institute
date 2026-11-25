<?php
require_once __DIR__ . '/includes/functions.php';
$db = get_db_connection();

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inquiry'])) {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if (empty($name) || empty($phone) || empty($subject) || empty($message)) {
        $error_msg = "Please fill in all required fields.";
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO inquiries (name, email, phone, subject, message, status) VALUES (?, ?, ?, ?, ?, 'Unread')");
            $stmt->execute([$name, $email, $phone, $subject, $message]);
            $success_msg = "Thank you! Your message has been sent. Our office will follow up shortly.";
        } catch (PDOException $e) {
            $error_msg = "Failed to send inquiry: " . $e->getMessage();
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<!-- Banner -->
<section class="page-banner centered">
  <div class="container">
    <span class="eyebrow eyebrow-light">Get In Touch · Cox's Bazar</span>
    <h1>Contact AL FATIHA INSTITUTE</h1>
    <p>
      Reach our admissions desk for curriculum questions, fee details, or to schedule a campus visit.
    </p>
  </div>
</section>

<!-- Contact Form & Info Grid -->
<section class="section-padding">
  <div class="container contact-layout">

    <!-- Contact Cards -->
    <div>
      <span class="eyebrow">Campus Directory</span>
      <h2 class="section-title" style="font-size: 28px; margin-bottom: 20px;">Cox's Bazar Campus</h2>

      <div class="contact-cards-stack">
        <div class="contact-detail-card">
          <div class="contact-icon-box" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          </div>
          <div>
            <h4 style="font-size: 15px; color: var(--navy); margin-bottom: 4px;">Address</h4>
            <p style="color: var(--text-secondary); font-size: 14px;">Engg Edris Building, Kalur Dokan, Tekpara Main Road, Cox's Bazar 4700</p>
          </div>
        </div>

        <div class="contact-detail-card">
          <div class="contact-icon-box" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          </div>
          <div>
            <h4 style="font-size: 15px; color: var(--navy); margin-bottom: 4px;">Phone &amp; WhatsApp</h4>
            <p style="color: var(--text-secondary); font-size: 14px;">01611477441 / 01632550045</p>
          </div>
        </div>

        <div class="contact-detail-card">
          <div class="contact-icon-box" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </div>
          <div>
            <h4 style="font-size: 15px; color: var(--navy); margin-bottom: 4px;">Email</h4>
            <p style="color: var(--text-secondary); font-size: 14px;">alfatihacxb@gmail.com</p>
          </div>
        </div>

        <div class="contact-detail-card">
          <div class="contact-icon-box" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <div>
            <h4 style="font-size: 15px; color: var(--navy); margin-bottom: 4px;">Office Hours</h4>
            <p style="color: var(--text-secondary); font-size: 14px;">Sunday – Thursday · 8:00 AM – 2:00 PM</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Send Message Form -->
    <div class="content-card elevated" style="padding: 32px;">
      <h3 style="font-family: 'Newsreader', Georgia, serif; color: var(--navy); font-size: 26px; margin-bottom: 4px;">Send Us a Message</h3>
      <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 24px;">Questions about admission or classes? Fill in the fields below.</p>

      <?php if ($success_msg): ?>
        <div class="alert-box alert-success">
          <span><?= $success_msg ?></span>
        </div>
      <?php endif; ?>

      <?php if ($error_msg): ?>
        <div class="alert-box alert-error">
          <span><?= $error_msg ?></span>
        </div>
      <?php endif; ?>

      <form method="POST">
        <div class="two-col-grid">
          <div class="form-group">
            <label>Your Name *</label>
            <input type="text" name="name" class="form-control" placeholder="Full name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label>Phone Number *</label>
            <input type="text" name="phone" class="form-control" placeholder="01800000000" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          </div>
        </div>

        <div class="two-col-grid">
          <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label>Subject *</label>
            <input type="text" name="subject" class="form-control" placeholder="Admission inquiry" required value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label>Message *</label>
          <textarea name="message" class="form-control" rows="4" placeholder="Write your message" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
        </div>

        <button type="submit" name="submit_inquiry" class="btn btn-primary btn-block" style="margin-top: 8px;">
          Send Inquiry
        </button>
      </form>
    </div>

  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
