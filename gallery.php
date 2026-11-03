<?php
require_once __DIR__ . '/includes/functions.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Banner -->
<section style="background: linear-gradient(135deg, var(--primary-deep) 0%, var(--primary-dark) 100%); color: #ffffff; padding: 4rem 0 3.5rem 0; text-align: center;">
  <div class="container">
    <span class="sub-badge gold"><i class="fas fa-images"></i> Campus Life</span>
    <h1 style="color: #ffffff; font-size: 2.8rem; margin-top: 0.5rem;">Photo & Event Gallery</h1>
    <p style="color: rgba(255,255,255,0.85); font-size: 1.1rem; max-width: 650px; margin: 0.8rem auto 0 auto;">
      Highlights from classroom sensory activities, Quran recitation exhibitions, Cambridge science days, and celebrations.
    </p>
  </div>
</section>

<!-- Gallery Showcase -->
<section class="section-padding">
  <div class="container">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.8rem;">
      
      <div style="background: #ffffff; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <div style="height: 200px; background: linear-gradient(135deg, #0e73b9, #032d4e); display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 3rem;">
          <i class="fas fa-quran"></i>
        </div>
        <div style="padding: 1.2rem;">
          <span style="font-size: 0.75rem; color: var(--accent-gold-dark); font-weight: 800; text-transform: uppercase;">ISLAMIC EVENTS</span>
          <h4 style="color: var(--primary-dark); margin-top: 0.2rem;">Annual Tajweed & Recitation Contest</h4>
        </div>
      </div>

      <div style="background: #ffffff; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <div style="height: 200px; background: linear-gradient(135deg, #f3b527, #cd8707); display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 3rem;">
          <i class="fas fa-shapes"></i>
        </div>
        <div style="padding: 1.2rem;">
          <span style="font-size: 0.75rem; color: var(--primary); font-weight: 800; text-transform: uppercase;">CLASSROOM</span>
          <h4 style="color: var(--primary-dark); margin-top: 0.2rem;">Playgroup Phonics & Sensory Activity</h4>
        </div>
      </div>

      <div style="background: #ffffff; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <div style="height: 200px; background: linear-gradient(135deg, #3ac0f6, #0e73b9); display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 3rem;">
          <i class="fas fa-atom"></i>
        </div>
        <div style="padding: 1.2rem;">
          <span style="font-size: 0.75rem; color: var(--sky-blue); font-weight: 800; text-transform: uppercase;">CAMBRIDGE ACADEMICS</span>
          <h4 style="color: var(--primary-dark); margin-top: 0.2rem;">Year 1 & 2 Primary Science Fair</h4>
        </div>
      </div>

      <div style="background: #ffffff; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <div style="height: 200px; background: linear-gradient(135deg, #10b981, #064878); display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 3rem;">
          <i class="fas fa-child"></i>
        </div>
        <div style="padding: 1.2rem;">
          <span style="font-size: 0.75rem; color: #10b981; font-weight: 800; text-transform: uppercase;">CO-CURRICULAR</span>
          <h4 style="color: var(--primary-dark); margin-top: 0.2rem;">Little Sunnah Sports & Physical Day</h4>
        </div>
      </div>

    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
