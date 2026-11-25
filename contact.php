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
            $success_msg = "Thank you! Your message has been sent successfully. We will get back to you shortly.";
        } catch (PDOException $e) {
            $error_msg = "Failed to send inquiry: " . $e->getMessage();
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<!-- Banner -->
<section style="background: linear-gradient(135deg, var(--primary-deep) 0%, var(--primary-dark) 100%); color: #ffffff; padding: 4rem 0 3.5rem 0; text-align: center;">
  <div class="container">
    <span class="sub-badge gold"><i class="fas fa-envelope-open-text"></i> Get In Touch</span>
    <h1 style="color: #ffffff; font-size: 2.8rem; margin-top: 0.5rem;">Contact AL FATIHA INSTITUTE</h1>
    <p style="color: rgba(255,255,255,0.85); font-size: 1.1rem; max-width: 650px; margin: 0.8rem auto 0 auto;">
      We welcome your queries regarding admissions, curriculum, or school campus visits.
    </p>
  </div>
</section>

<!-- Contact Form & Info Grid -->
<section class="section-padding">
  <div class="container">
    <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 3.5rem; align-items: flex-start;">
      
      <!-- Contact Cards -->
      <div>
        <span class="sub-badge"><i class="fas fa-map-marked-alt"></i> Information</span>
        <h2 style="font-size: 2rem; color: var(--primary-dark); margin-bottom: 1.5rem;">Cox's Bazar Campus</h2>

        <div style="background: #ffffff; padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); margin-bottom: 1.2rem; display: flex; gap: 1rem; align-items: flex-start;">
          <div style="width: 48px; height: 48px; background: var(--primary-light); color: var(--primary); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;">
            <i class="fas fa-map-marker-alt"></i>
          </div>
          <div>
            <h4 style="color: var(--primary-dark); margin-bottom: 0.2rem;">Address</h4>
            <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0;">Engg Edris building, Kalur Dokan, Tekpara main road, Cox's Bazar 4700.</p>
          </div>
        </div>

        <div style="background: #ffffff; padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); margin-bottom: 1.2rem; display: flex; gap: 1rem; align-items: flex-start;">
          <div style="width: 48px; height: 48px; background: var(--accent-gold-light); color: var(--accent-gold-dark); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;">
            <i class="fas fa-phone-alt"></i>
          </div>
          <div>
            <h4 style="color: var(--primary-dark); margin-bottom: 0.2rem;">Phone & WhatsApp</h4>
            <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0;">01611477441 / 01632550045</p>
          </div>
        </div>

        <div style="background: #ffffff; padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); display: flex; gap: 1rem; align-items: flex-start;">
          <div style="width: 48px; height: 48px; background: var(--sky-blue-light); color: var(--sky-blue); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;">
            <i class="fas fa-envelope"></i>
          </div>
          <div>
            <h4 style="color: var(--primary-dark); margin-bottom: 0.2rem;">Email</h4>
            <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0;">alfatihacxb@gmail.com</p>
          </div>
        </div>
      </div>

      <!-- Send Message Form -->
      <div style="background: #ffffff; border-radius: var(--radius-lg); border: 1px solid var(--border-color); padding: 2.5rem; box-shadow: var(--shadow-md);">
        <h3 style="color: var(--primary-dark); font-size: 1.6rem; margin-bottom: 0.4rem;">Send Us a Message</h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">Have questions about admission or curriculum? Fill in the form below.</p>

        <?php if ($success_msg): ?>
          <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
            <i class="fas fa-check-circle"></i> <?= $success_msg ?>
          </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
          <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
            <i class="fas fa-exclamation-triangle"></i> <?= $error_msg ?>
          </div>
        <?php endif; ?>

        <form method="POST">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label>Your Name *</label>
              <input type="text" name="name" class="form-control" placeholder="Full Name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>

            <div class="form-group">
              <label>Phone Number *</label>
              <input type="text" name="phone" class="form-control" placeholder="01800000000" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" name="email" class="form-control" placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
              <label>Subject *</label>
              <input type="text" name="subject" class="form-control" placeholder="e.g. Playgroup Admission Query" required value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Message *</label>
            <textarea name="message" class="form-control" rows="4" placeholder="Write your query details here..." required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
          </div>

          <button type="submit" name="submit_inquiry" class="btn btn-primary btn-block" style="margin-top: 1rem;">
            <i class="fas fa-paper-plane"></i> Send Inquiry
          </button>
        </form>
      </div>

    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
