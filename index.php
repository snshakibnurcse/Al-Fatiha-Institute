<?php
require_once __DIR__ . '/includes/functions.php';
$db = get_db_connection();

// Fetch latest notices for strip & notice board
$noticeStmt = $db->query("SELECT * FROM notices ORDER BY published_date DESC LIMIT 5");
$notices = $noticeStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
  <div class="container hero-grid">
    <div class="hero-content">
      <span class="hero-eyebrow">Cambridge Early Years &amp; Islamic Ethos · Cox's Bazar</span>
      <h1>Nurturing young minds with Cambridge clarity and Islamic character</h1>
      <p>
        At AL FATIHA INSTITUTE, children from Play Group through Class 2 build strong foundations in Cambridge English, mathematics, and science alongside Quranic Tajweed and daily Arabic.
      </p>
      <div class="hero-buttons">
        <a href="admissions.php" class="btn btn-gold">Apply for 2027 Session</a>
        <a href="academics.php" class="btn btn-outline-dark">Explore Curriculum</a>
      </div>

      <div class="hero-meta-strip">
        <div class="hero-meta-item">
          <strong>Cambridge Early Years &amp; Primary</strong>
          <span>Structured phonics, numeracy, and inquiry-based science</span>
        </div>
        <div class="hero-meta-item">
          <strong>Tajweed &amp; Spoken Arabic</strong>
          <span>Daily Makharij, short Surahs, and practical Sunnah habits</span>
        </div>
      </div>
    </div>

    <!-- Hero Moment: 2027 Prospectus Snapshot -->
    <div class="prospectus-card">
      <div class="prospectus-head">
        <div class="prospectus-brand">
          <img src="assets/images/logo.png?v=2" alt="AL FATIHA INSTITUTE Crest">
          <div>
            <strong>Academic Prospectus 2027</strong>
            <span>Play Group – Class Two</span>
          </div>
        </div>
        <span class="status-tag">Admissions open</span>
      </div>

      <div class="prospectus-rows">
        <a href="academics.php#play" class="prospectus-row">
          <div class="prospectus-row-left">
            <span class="prospectus-idx">01</span>
            <span class="prospectus-row-title">Play Group · Early Sensory</span>
          </div>
          <span class="prospectus-row-age">2.5–3.5 yrs</span>
        </a>
        <a href="academics.php#nursery" class="prospectus-row">
          <div class="prospectus-row-left">
            <span class="prospectus-idx">02</span>
            <span class="prospectus-row-title">Nursery · Foundation Literacy</span>
          </div>
          <span class="prospectus-row-age">3.5–4.5 yrs</span>
        </a>
        <a href="academics.php#kg" class="prospectus-row">
          <div class="prospectus-row-left">
            <span class="prospectus-idx">03</span>
            <span class="prospectus-row-title">Kindergarten · Active Reading</span>
          </div>
          <span class="prospectus-row-age">4.5–5.5 yrs</span>
        </a>
        <a href="academics.php#class1" class="prospectus-row">
          <div class="prospectus-row-left">
            <span class="prospectus-idx">04</span>
            <span class="prospectus-row-title">Class One · Cambridge Stage 1</span>
          </div>
          <span class="prospectus-row-age">5.5–6.5 yrs</span>
        </a>
        <a href="academics.php#class1" class="prospectus-row">
          <div class="prospectus-row-left">
            <span class="prospectus-idx">05</span>
            <span class="prospectus-row-title">Class Two · Cambridge Stage 2</span>
          </div>
          <span class="prospectus-row-age">6.5–7.5 yrs</span>
        </a>
      </div>

      <div class="prospectus-foot">
        <span>Tekpara Campus · 1:15 ratio</span>
        <a href="admissions.php#apply-form">Start application &rarr;</a>
      </div>
    </div>
  </div>
</section>

<!-- Notice Strip -->
<div class="notice-strip">
  <div class="container notice-strip-wrap">
    <div class="notice-strip-left">
      <span class="notice-strip-tag">Latest notice</span>
      <?php if (!empty($notices)): $latest = $notices[0]; ?>
        <div class="notice-strip-text">
          <span class="notice-strip-date"><?= date('d M Y', strtotime($latest['published_date'])) ?></span>
          <strong><?= htmlspecialchars($latest['title']) ?></strong>
        </div>
      <?php else: ?>
        <div class="notice-strip-text">Admissions open for the 2027 academic session (Play Group to Class 2)</div>
      <?php endif; ?>
    </div>
    <a href="notices.php" class="notice-strip-link">All notices &rarr;</a>
  </div>
</div>

<!-- Four Core Pillars -->
<section class="section-padding">
  <div class="container">
    <div class="section-title-wrap">
      <span class="eyebrow">01 · Educational Framework</span>
      <h2 class="section-title">Foundations of balanced early schooling</h2>
      <p class="section-desc">Academic rigor and moral formation taught side by side in calm, structured classrooms.</p>
    </div>

    <div class="pillars-grid">
      <div class="pillar-card">
        <div class="pillar-top">
          <span class="pillar-num">01</span>
          <span class="pillar-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
          </span>
        </div>
        <h3>Cambridge Curriculum</h3>
        <p>EYFS and Cambridge Primary standards focused on English phonics, reading comprehension, and mathematical reasoning.</p>
      </div>

      <div class="pillar-card">
        <div class="pillar-top">
          <span class="pillar-num">02</span>
          <span class="pillar-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
          </span>
        </div>
        <h3>Islamic Ethos &amp; Tarbiyah</h3>
        <p>Character formation rooted in Akhlaq, daily Sunnah adab, Prophetic stories, and clear foundational Aqeedah.</p>
      </div>

      <div class="pillar-card">
        <div class="pillar-top">
          <span class="pillar-num">03</span>
          <span class="pillar-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/></svg>
          </span>
        </div>
        <h3>Arabic &amp; Tajweed Hifz</h3>
        <p>Accurate letter articulation (Makharij), applied Tajweed rules, practical Arabic vocabulary, and paced Surah memorization.</p>
      </div>

      <div class="pillar-card">
        <div class="pillar-top">
          <span class="pillar-num">04</span>
          <span class="pillar-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </span>
        </div>
        <h3>Child-Centric Campus</h3>
        <p>Air-conditioned classrooms, continuous CCTV monitoring, and attentive educators maintaining a 1:15 ratio.</p>
      </div>
    </div>
  </div>
</section>

<!-- Academic Programs -->
<section class="section-padding section-surface">
  <div class="container">
    <div class="section-title-wrap">
      <span class="eyebrow">02 · Academic Structure</span>
      <h2 class="section-title">Kindergarten and early primary classes</h2>
      <p class="section-desc">Age-appropriate stages guiding children from sensory discovery to Cambridge Primary Stage 2.</p>
    </div>

    <div class="programs-grid">
      <div class="program-card">
        <div>
          <div class="program-header">
            <span class="program-age-tag">Age 2.5–3.5 yrs</span>
            <h3>Play Group</h3>
            <p class="program-sub">Early childhood sensory learning</p>
          </div>
          <ul class="program-features">
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Phonics &amp; alphabet sounds</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Arabic letters &amp; daily Duas</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Fine motor &amp; creative play</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Classroom etiquette &amp; sharing</li>
          </ul>
        </div>
        <a href="admissions.php?class=1" class="btn btn-outline btn-block">Apply for Play</a>
      </div>

      <div class="program-card">
        <div>
          <div class="program-header">
            <span class="program-age-tag">Age 3.5–4.5 yrs</span>
            <h3>Nursery</h3>
            <p class="program-sub">Foundation literacy &amp; numeracy</p>
          </div>
          <ul class="program-features">
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> English CVC words &amp; reading</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Counting 1–100 &amp; shapes</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Short Surahs &amp; daily Sunnah</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Art &amp; nature observation</li>
          </ul>
        </div>
        <a href="admissions.php?class=2" class="btn btn-outline btn-block">Apply for Nursery</a>
      </div>

      <div class="program-card">
        <div>
          <div class="program-header">
            <span class="program-age-tag">Age 4.5–5.5 yrs</span>
            <h3>Kindergarten</h3>
            <p class="program-sub">Active learner preparation</p>
          </div>
          <ul class="program-features">
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Sentence building &amp; vocabulary</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Addition &amp; subtraction</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Arabic reading with Harakat</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Stories of the Prophets</li>
          </ul>
        </div>
        <a href="admissions.php?class=3" class="btn btn-outline btn-block">Apply for KG</a>
      </div>

      <div class="program-card">
        <div>
          <div class="program-header">
            <span class="program-age-tag">Age 5.5–6.5 yrs</span>
            <h3>Class One</h3>
            <p class="program-sub">Cambridge Primary Stage 1</p>
          </div>
          <ul class="program-features">
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Cambridge English &amp; Math</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Cambridge Primary Science</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Quran recitation with Tajweed</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Basic conversational Arabic</li>
          </ul>
        </div>
        <a href="admissions.php?class=4" class="btn btn-primary btn-block">Apply for Class 1</a>
      </div>

      <div class="program-card">
        <div>
          <div class="program-header">
            <span class="program-age-tag">Age 6.5–7.5 yrs</span>
            <h3>Class Two</h3>
            <p class="program-sub">Cambridge Primary Stage 2</p>
          </div>
          <ul class="program-features">
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Reading &amp; comprehension</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Science experiments &amp; logic</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Juz Amma Hifz progression</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg> Islamic ethics &amp; leadership</li>
          </ul>
        </div>
        <a href="admissions.php?class=5" class="btn btn-primary btn-block">Apply for Class 2</a>
      </div>
    </div>
  </div>
</section>

<!-- Islamic Values Feature Showcase -->
<section class="section-padding islamic-banner-section">
  <div class="container islamic-grid">
    <div class="islamic-content">
      <span class="eyebrow eyebrow-light">03 · Islamic Ethos</span>
      <div class="arabic-header arabic-text" lang="ar" dir="rtl">بِسْمِ اللهِ الرَّحْمٰنِ الرَّحِيْمِ</div>
      <h2>Arabic and Islamic values in daily learning</h2>
      <p class="islamic-lead">
        Early childhood is the natural time to cultivate love for Allah, respect for parents, and principled conduct. Alongside Cambridge subjects, every student practices:
      </p>

      <ul class="islamic-list">
        <li>
          <span class="islamic-list-num">01</span>
          <div>
            <strong>Tajweed &amp; Hifz Program</strong>
            <span>Clear Arabic articulation (Makharij) and step-by-step memorization of Juz Amma.</span>
          </div>
        </li>
        <li>
          <span class="islamic-list-num">02</span>
          <div>
            <strong>Daily Sunnah &amp; Duas</strong>
            <span>Practical routines for greeting with Salam, mealtime etiquette, and guided Salah preparation.</span>
          </div>
        </li>
        <li>
          <span class="islamic-list-num">03</span>
          <div>
            <strong>Spoken Arabic Vocabulary</strong>
            <span>Everyday Arabic words for family, nature, numbers, and polite classroom phrases.</span>
          </div>
        </li>
      </ul>
    </div>

    <div class="islamic-card-box">
      <div class="quran-quote arabic-text" lang="ar" dir="rtl">"رَبِّ زِدْنِي عِلْمًا"</div>
      <div class="translation-quote">
        "O my Sustainer! Increase me in knowledge."
        <span class="translation-source">Surah Taha 20:114</span>
      </div>

      <div class="islamic-card-divider">
        <h4>Character alongside academic clarity</h4>
        <p>Educators model honesty, patience, kindness, and curiosity across every lesson.</p>
      </div>
    </div>
  </div>
</section>

<!-- Key Figures Strip -->
<section class="stats-section">
  <div class="container stats-grid">
    <div class="stat-item">
      <h3 class="counter-number" data-target="250">250</h3>
      <p>Enrolled students</p>
    </div>
    <div class="stat-item">
      <h3 class="counter-number" data-target="18">18</h3>
      <p>Dedicated educators</p>
    </div>
    <div class="stat-item">
      <h3 class="counter-number" data-target="5">5</h3>
      <p>Academic stages (Play–Class 2)</p>
    </div>
    <div class="stat-item">
      <h3>1:15</h3>
      <p>Teacher-to-student ratio</p>
    </div>
  </div>
</section>

<!-- Notices & Upcoming Activities -->
<section class="section-padding">
  <div class="container dual-section-grid">
    <div class="notice-box">
      <div class="box-header">
        <h3>Notice Board</h3>
        <a href="notices.php">View all &rarr;</a>
      </div>

      <?php if (!empty($notices)): ?>
        <?php foreach (array_slice($notices, 0, 4) as $item): ?>
          <div class="notice-item">
            <div class="notice-date">
              <strong><?= date('d', strtotime($item['published_date'])) ?></strong>
              <span><?= strtoupper(date('M', strtotime($item['published_date']))) ?></span>
            </div>
            <div class="notice-info">
              <h4><a href="notices.php#notice-<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a></h4>
              <p><?= htmlspecialchars(substr($item['content'], 0, 90)) ?>...</p>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="section-desc">No active notices published yet.</p>
      <?php endif; ?>
    </div>

    <div class="event-box">
      <div class="box-header">
        <h3>Upcoming Activities</h3>
        <a href="gallery.php">Campus gallery &rarr;</a>
      </div>

      <div class="notice-item">
        <div class="notice-date">
          <strong>15</strong>
          <span>OCT</span>
        </div>
        <div class="notice-info">
          <h4>Quran Recitation &amp; Tajweed Exhibition</h4>
          <p>Students present Quran recitation fluency and Arabic poems for parents.</p>
        </div>
      </div>

      <div class="notice-item">
        <div class="notice-date">
          <strong>28</strong>
          <span>OCT</span>
        </div>
        <div class="notice-info">
          <h4>Cambridge Phonics &amp; Science Fair</h4>
          <p>Sensory experiments, shape building, and reading stations for Play through KG.</p>
        </div>
      </div>

      <div class="notice-item">
        <div class="notice-date">
          <strong>10</strong>
          <span>NOV</span>
        </div>
        <div class="notice-info">
          <h4>Parent–Teacher Progress Review</h4>
          <p>First-term academic reports and developmental conversations with class teachers.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Bottom Call to Action -->
<section class="cta-section">
  <div class="container">
    <h2>Begin your child's learning journey</h2>
    <p>Online applications are open for Play Group, Nursery, KG, Class 1, and Class 2 at our Cox's Bazar campus.</p>
    <a href="admissions.php" class="btn btn-gold">Submit Admission Application</a>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
