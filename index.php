<?php
require_once __DIR__ . '/includes/functions.php';
$db = get_db_connection();

// Fetch latest notices for ticker & widget
$noticeStmt = $db->query("SELECT * FROM notices ORDER BY published_date DESC LIMIT 5");
$notices = $noticeStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
  <div class="container hero-grid">
    <div class="hero-content">
      <h2><i class="fas fa-star" style="color: var(--accent-gold);"></i> Premier Kindergarten & Early Primary</h2>
      <h1>Nurturing Young Minds with <span>Cambridge Excellence</span> & Islamic Values</h1>
      <p>
        At <strong>AL FATIHA INSTITUTE</strong>, we provide a world-class Cambridge International curriculum foundation combined with authentic Islamic Tarbiyah, Quranic Tajweed, and Arabic language immersion for children in <strong>Play, Nursery, KG, Class 1, and Class 2</strong>.
      </p>
      <div class="hero-buttons">
        <a href="admissions.php" class="btn btn-gold"><i class="fas fa-user-plus"></i> Online Admission 2027</a>
        <a href="academics.php" class="btn btn-outline" style="color: #ffffff; border-color: rgba(255,255,255,0.4);"><i class="fas fa-book-open"></i> Explore Curriculum</a>
      </div>

      <div class="hero-badges">
        <div class="hero-badge-item">
          <i class="fas fa-graduation-cap"></i>
          <div>
            <strong>Cambridge Primary</strong>
            <span>Global Standard Phonics & Numeracy</span>
          </div>
        </div>
        <div class="hero-badge-item">
          <i class="fas fa-quran"></i>
          <div>
            <strong>Arabic & Tajweed</strong>
            <span>Daily Hifz & Sunnah Integration</span>
          </div>
        </div>
      </div>
    </div>

    <div class="hero-card-preview">
      <div class="hero-image-wrap">
        <img src="assets/images/logo.png?v=2" alt="AL FATIHA INSTITUTE Logo" class="logo-hero">
        <h3 style="color: #ffffff; font-size: 1.4rem; margin-bottom: 0.5rem;">AL FATIHA INSTITUTE</h3>
        <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem; margin-bottom: 1rem;">Cox's Bazar • Established 2025</p>
        
        <div class="floating-pill">
          <i class="fas fa-award"></i>
          <span style="text-align: left;">
            <strong style="display: block; font-size: 0.9rem;">Admissions Open 2027</strong>
            <small style="color: var(--text-muted); font-size: 0.75rem;">Play, Nursery, KG, Class 1 & Class 2</small>
          </span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Notice Ticker Bar -->
<div class="ticker-bar">
  <div class="container ticker-wrap">
    <span class="ticker-label"><i class="fas fa-bullhorn"></i> Latest Notice</span>
    <div class="ticker-content">
      <?php if (!empty($notices)): ?>
        <?php foreach ($notices as $n): ?>
          <span style="margin-right: 3rem;">
            <strong>[<?= date('d M', strtotime($n['published_date'])) ?>]</strong> <?= htmlspecialchars($n['title']) ?> &nbsp;•&nbsp;
          </span>
        <?php endforeach; ?>
      <?php else: ?>
        <span>Welcome to AL FATIHA INSTITUTE - Admission open for 2027 academic session!</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Four Core Pillars Section -->
<section class="section-padding">
  <div class="container">
    <div class="section-title-wrap">
      <span class="sub-badge"><i class="fas fa-compass"></i> Why Choose Us</span>
      <h2 class="section-title">Foundations of Holistic Education</h2>
      <p class="section-desc">We combine academic brilliance with pure moral and spiritual growth to empower your child's golden early learning years.</p>
    </div>

    <div class="pillars-grid">
      <div class="pillar-card">
        <div class="pillar-icon"><i class="fas fa-book-reader"></i></div>
        <h3>Cambridge Curriculum</h3>
        <p>British Early Years Foundation Stage (EYFS) and Cambridge Primary standards focusing on analytical thinking, English fluency, and mathematical problem-solving.</p>
      </div>

      <div class="pillar-card">
        <div class="pillar-icon"><i class="fas fa-quran"></i></div>
        <h3>Islamic Ethos & Tarbiyah</h3>
        <p>Comprehensive Islamic character development focusing on Akhlaq, Daily Sunnah adab, Quranic stories, and basic Aqeedah designed for young minds.</p>
      </div>

      <div class="pillar-card">
        <div class="pillar-icon"><i class="fas fa-language"></i></div>
        <h3>Arabic & Tajweed Hifz</h3>
        <p>Interactive Arabic language vocabulary acquisition, proper Makharij, Tajweed rules, and age-appropriate Surah memorization with daily practice.</p>
      </div>

      <div class="pillar-card">
        <div class="pillar-icon"><i class="fas fa-shield-alt"></i></div>
        <h3>Child-Centric Campus</h3>
        <p>Safe, CCTV-monitored, air-conditioned activity rooms, hygienic play zones, and caring female & male educators maintaining a 1:15 teacher-student ratio.</p>
      </div>
    </div>
  </div>
</section>

<!-- Academic Programs Section -->
<section class="section-padding programs-section">
  <div class="container">
    <div class="section-title-wrap">
      <span class="sub-badge gold"><i class="fas fa-layer-group"></i> Academic Structure</span>
      <h2 class="section-title">Our Kindergarten & Early Primary Classes</h2>
      <p class="section-desc">Tailored developmental stages from toddler playgroup to primary Class Two.</p>
    </div>

    <div class="programs-grid">
      <!-- Play Group -->
      <div class="program-card">
        <span class="program-badge">Age 2.5 - 3.5 Yrs</span>
        <div class="program-header">
          <div class="program-icon"><i class="fas fa-shapes"></i></div>
          <h3>Play Group</h3>
          <p class="program-age">Early Childhood Sensory Learning</p>
        </div>
        <ul class="program-features">
          <li><i class="fas fa-check-circle"></i> Phonics & Alphabet Sounds</li>
          <li><i class="fas fa-check-circle"></i> Basic Arabic Letters & Duas</li>
          <li><i class="fas fa-check-circle"></i> Motor Skills & Creative Play</li>
          <li><i class="fas fa-check-circle"></i> Social Habit Building</li>
        </ul>
        <a href="admissions.php?class=1" class="btn btn-outline btn-block"><i class="fas fa-paper-plane"></i> Apply for Play</a>
      </div>

      <!-- Nursery -->
      <div class="program-card">
        <span class="program-badge">Age 3.5 - 4.5 Yrs</span>
        <div class="program-header">
          <div class="program-icon"><i class="fas fa-child"></i></div>
          <h3>Nursery</h3>
          <p class="program-age">Foundation Literacy & Numeracy</p>
        </div>
        <ul class="program-features">
          <li><i class="fas fa-check-circle"></i> English CVC Words & Reading</li>
          <li><i class="fas fa-check-circle"></i> Counting 1-100 & Shapes</li>
          <li><i class="fas fa-check-circle"></i> Short Surahs & Daily Sunnah</li>
          <li><i class="fas fa-check-circle"></i> Art & Environmental Exploration</li>
        </ul>
        <a href="admissions.php?class=2" class="btn btn-outline btn-block"><i class="fas fa-paper-plane"></i> Apply for Nursery</a>
      </div>

      <!-- KG -->
      <div class="program-card">
        <span class="program-badge">Age 4.5 - 5.5 Yrs</span>
        <div class="program-header">
          <div class="program-icon"><i class="fas fa-school"></i></div>
          <h3>Kindergarten (KG)</h3>
          <p class="program-age">Active Learner Preparation</p>
        </div>
        <ul class="program-features">
          <li><i class="fas fa-check-circle"></i> Sentence Construction & Vocabulary</li>
          <li><i class="fas fa-check-circle"></i> Basic Addition & Subtraction</li>
          <li><i class="fas fa-check-circle"></i> Arabic Reading with Harakat</li>
          <li><i class="fas fa-check-circle"></i> Islamic History Stories for Kids</li>
        </ul>
        <a href="admissions.php?class=3" class="btn btn-outline btn-block"><i class="fas fa-paper-plane"></i> Apply for KG</a>
      </div>

      <!-- Class One -->
      <div class="program-card">
        <span class="program-badge">Age 5.5 - 6.5 Yrs</span>
        <div class="program-header">
          <div class="program-icon"><i class="fas fa-book-reader"></i></div>
          <h3>Class One</h3>
          <p class="program-age">Cambridge Primary Stage 1</p>
        </div>
        <ul class="program-features">
          <li><i class="fas fa-check-circle"></i> Cambridge Primary English & Math</li>
          <li><i class="fas fa-check-circle"></i> Cambridge Primary Science</li>
          <li><i class="fas fa-check-circle"></i> Quran Recitation with Tajweed</li>
          <li><i class="fas fa-check-circle"></i> Basic Conversational Arabic</li>
        </ul>
        <a href="admissions.php?class=4" class="btn btn-primary btn-block"><i class="fas fa-paper-plane"></i> Apply for Class 1</a>
      </div>

      <!-- Class Two -->
      <div class="program-card">
        <span class="program-badge">Age 6.5 - 7.5 Yrs</span>
        <div class="program-header">
          <div class="program-icon"><i class="fas fa-graduation-cap"></i></div>
          <h3>Class Two</h3>
          <p class="program-age">Cambridge Primary Stage 2</p>
        </div>
        <ul class="program-features">
          <li><i class="fas fa-check-circle"></i> Advanced Reading & Comprehension</li>
          <li><i class="fas fa-check-circle"></i> Science Experiments & Logic</li>
          <li><i class="fas fa-check-circle"></i> Juz Amma Hifz Progress</li>
          <li><i class="fas fa-check-circle"></i> Islamic Ethics & Leadership</li>
        </ul>
        <a href="admissions.php?class=5" class="btn btn-primary btn-block"><i class="fas fa-paper-plane"></i> Apply for Class 2</a>
      </div>
    </div>
  </div>
</section>

<!-- Islamic Values Feature Showcase -->
<section class="section-padding islamic-banner-section">
  <div class="container islamic-grid">
    <div class="islamic-content">
      <span class="sub-badge gold"><i class="fas fa-mosque"></i> Islamic Priority</span>
      <h2 class="arabic-header arabic-text">بِسْمِ اللهِ الرَّحْمٰنِ الرَّحِيْمِ</h2>
      <h2>Arabic & Islamic Ethos in Daily Learning</h2>
      <p style="color: rgba(255,255,255,0.85); font-size: 1.05rem; line-height: 1.7;">
        We believe early childhood is the most crucial phase to instill love for Allah (SWT), the Prophet (ﷺ), and noble character. Along with Cambridge English subjects, every child receives:
      </p>

      <ul class="islamic-list">
        <li>
          <i class="fas fa-quran"></i>
          <div>
            <strong>Tajweed & Hifz Program</strong>
            <span>Proper pronunciation of Arabic letters (Makharij) and gradual memorization of Juz Amma.</span>
          </div>
        </li>
        <li>
          <i class="fas fa-hands-praying"></i>
          <div>
            <strong>Daily Sunnah & Duas</strong>
            <span>Practical routines for eating, greeting (Salam), entering classroom, and age-appropriate Salah practice.</span>
          </div>
        </li>
        <li>
          <i class="fas fa-comments"></i>
          <div>
            <strong>Arabic Language Vocabulary</strong>
            <span>Learning everyday Arabic words for animals, fruits, family, and simple conversational sentences.</span>
          </div>
        </li>
      </ul>
    </div>

    <div class="islamic-card-box">
      <i class="fas fa-quote-left" style="font-size: 2.5rem; color: var(--accent-gold); margin-bottom: 1rem;"></i>
      <div class="quran-quote arabic-text">"رَبِّ زِدْنِي عِلْمًا"</div>
      <div class="translation-quote">
        "O my Sustainer! Increase me in knowledge." <br>
        <small style="color: var(--accent-gold); font-size: 0.85rem; font-style: normal;">(Surah Taha 20:114)</small>
      </div>

      <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.15);">
        <h4 style="color: #ffffff; margin-bottom: 0.5rem; font-size: 1.1rem;">Nurturing Morals Alongside Cambridge Standards</h4>
        <p style="color: rgba(255,255,255,0.75); font-size: 0.88rem;">Our teachers lead by example, cultivating honesty, kindness, respect for parents, and love for knowledge.</p>
      </div>
    </div>
  </div>
</section>

<!-- Live Stats Counter -->
<section class="stats-section">
  <div class="container stats-grid">
    <div class="stat-item">
      <h3 class="counter-number" data-target="250">0</h3>
      <p>Happy Students</p>
    </div>
    <div class="stat-item">
      <h3 class="counter-number" data-target="18">0</h3>
      <p>Dedicated Educators</p>
    </div>
    <div class="stat-item">
      <h3 class="counter-number" data-target="5">0</h3>
      <p>Academic Classes</p>
    </div>
    <div class="stat-item">
      <h3>100%</h3>
      <p>Cambridge & Islamic Care</p>
    </div>
  </div>
</section>

<!-- Notices & Events Dual Section -->
<section class="section-padding">
  <div class="container dual-section-grid">
    <!-- Public Notice Board Box -->
    <div class="notice-box">
      <div class="box-header">
        <h3><i class="fas fa-bullhorn"></i> Notice Board</h3>
        <a href="notices.php" style="color: var(--primary); font-weight: 700; font-size: 0.88rem;">View All <i class="fas fa-arrow-right"></i></a>
      </div>

      <?php if (!empty($notices)): ?>
        <?php foreach (array_slice($notices, 0, 4) as $item): ?>
          <div class="notice-item">
            <div class="notice-date">
              <strong><?= date('d', strtotime($item['published_date'])) ?></strong>
              <span><?= date('M', strtotime($item['published_date'])) ?></span>
            </div>
            <div class="notice-info">
              <h4><a href="notices.php#notice-<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a></h4>
              <p><?= htmlspecialchars(substr($item['content'], 0, 90)) ?>...</p>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="color: var(--text-muted); font-size: 0.9rem;">No active notices published yet.</p>
      <?php endif; ?>
    </div>

    <!-- School Events & Highlights -->
    <div class="event-box">
      <div class="box-header">
        <h3><i class="fas fa-calendar-alt"></i> Upcoming Activities</h3>
        <a href="gallery.php" style="color: var(--primary); font-weight: 700; font-size: 0.88rem;">Campus Life <i class="fas fa-arrow-right"></i></a>
      </div>

      <div class="notice-item">
        <div class="notice-date" style="background: var(--accent-gold-light); color: var(--accent-gold-dark);">
          <strong>15</strong>
          <span>OCT</span>
        </div>
        <div class="notice-info">
          <h4>Quran Recitation & Tajweed Exhibition</h4>
          <p>Children will showcase their Quran recitation fluency and Arabic poem recitation for parents.</p>
        </div>
      </div>

      <div class="notice-item">
        <div class="notice-date" style="background: var(--sky-blue-light); color: var(--sky-blue);">
          <strong>28</strong>
          <span>OCT</span>
        </div>
        <div class="notice-info">
          <h4>Cambridge Phonics & Science Fun Fair</h4>
          <p>Interactive sensory experiments, shape building, and word puzzles organized for Play, Nursery & KG.</p>
        </div>
      </div>

      <div class="notice-item">
        <div class="notice-date" style="background: var(--primary-light); color: var(--primary-dark);">
          <strong>10</strong>
          <span>NOV</span>
        </div>
        <div class="notice-info">
          <h4>Parents-Teacher Progress Meeting</h4>
          <p>First term academic report cards and developmental discussion with class teachers.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Call to Action Banner -->
<section style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: #ffffff; padding: 4rem 0; text-align: center;">
  <div class="container">
    <h2 style="color: #ffffff; font-size: 2.2rem; margin-bottom: 0.8rem;">Give Your Child the Best Start in Life</h2>
    <p style="color: rgba(255,255,255,0.85); font-size: 1.1rem; max-width: 650px; margin: 0 auto 2rem auto;">
      Apply online today for Play Group, Nursery, KG, Class 1, or Class 2 admissions at AL FATIHA INSTITUTE, Cox's Bazar.
    </p>
    <a href="admissions.php" class="btn btn-gold" style="font-size: 1.1rem; padding: 1rem 2.2rem;">
      <i class="fas fa-edit"></i> Submit Admission Application
    </a>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
