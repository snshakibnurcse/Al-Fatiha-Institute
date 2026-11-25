<?php
require_once __DIR__ . '/includes/functions.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Banner -->
<section style="background: linear-gradient(135deg, var(--primary-deep) 0%, var(--primary-dark) 100%); color: #ffffff; padding: 4rem 0 3.5rem 0; text-align: center;">
  <div class="container">
    <span class="sub-badge gold"><i class="fas fa-graduation-cap"></i> Academic Excellence</span>
    <h1 style="color: #ffffff; font-size: 2.8rem; margin-top: 0.5rem;">Cambridge Curriculum & Syllabus</h1>
    <p style="color: rgba(255,255,255,0.85); font-size: 1.1rem; max-width: 650px; margin: 0.8rem auto 0 auto;">
      A blended dual-curriculum framework: International Cambridge Primary Stage 1-2 coupled with Quranic Tajweed, Arabic & Sunnah Ethics.
    </p>
  </div>
</section>

<!-- Curriculum Breakdown -->
<section class="section-padding">
  <div class="container">
    <div class="section-title-wrap">
      <span class="sub-badge"><i class="fas fa-book-open"></i> Grade-by-Grade Roadmap</span>
      <h2 class="section-title">Educational Journey (Play to Class 2)</h2>
    </div>

    <!-- Play Group -->
    <div id="play" style="background: #ffffff; padding: 2.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); margin-bottom: 2.5rem;">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 2px solid var(--bg-body); padding-bottom: 1rem;">
        <div>
          <span style="background: var(--sky-blue-light); color: var(--primary); font-weight: 800; padding: 0.35rem 0.9rem; border-radius: var(--radius-full); font-size: 0.8rem;">AGE: 2.5 - 3.5 YEARS</span>
          <h2 style="font-size: 1.8rem; color: var(--primary-dark); margin-top: 0.4rem;">Play Group (Early Sensory Phase)</h2>
        </div>
        <a href="admissions.php?class=1" class="btn btn-gold"><i class="fas fa-paper-plane"></i> Apply for Play</a>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <div>
          <h4 style="color: var(--primary); margin-bottom: 0.6rem;"><i class="fas fa-star"></i> Cambridge Early Learning Focus</h4>
          <ul style="list-style: none; padding: 0; color: var(--text-muted); font-size: 0.95rem;">
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> Synthetic Phonics & Alphabet sound recognition (Jolly Phonics)</li>
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> Fine motor skills: Playdough, coloring, object sorting</li>
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> Spoken English vocabulary for colors, animals, objects</li>
          </ul>
        </div>
        <div>
          <h4 style="color: var(--accent-gold-dark); margin-bottom: 0.6rem;"><i class="fas fa-moon"></i> Islamic & Arabic Integration</h4>
          <ul style="list-style: none; padding: 0; color: var(--text-muted); font-size: 0.95rem;">
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> Arabic Alphabet recognition (Alif to Ya) with nursery rhymes</li>
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> Memorization of Bismillah, Alhamdu Lillah, and Daily Duas</li>
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> Learning Islamic etiquette (Sharing, Salam, Respect)</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Nursery -->
    <div id="nursery" style="background: #ffffff; padding: 2.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); margin-bottom: 2.5rem;">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 2px solid var(--bg-body); padding-bottom: 1rem;">
        <div>
          <span style="background: var(--sky-blue-light); color: var(--primary); font-weight: 800; padding: 0.35rem 0.9rem; border-radius: var(--radius-full); font-size: 0.8rem;">AGE: 3.5 - 4.5 YEARS</span>
          <h2 style="font-size: 1.8rem; color: var(--primary-dark); margin-top: 0.4rem;">Nursery (Foundation Building)</h2>
        </div>
        <a href="admissions.php?class=2" class="btn btn-gold"><i class="fas fa-paper-plane"></i> Apply for Nursery</a>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <div>
          <h4 style="color: var(--primary); margin-bottom: 0.6rem;"><i class="fas fa-star"></i> Cambridge Early Learning Focus</h4>
          <ul style="list-style: none; padding: 0; color: var(--text-muted); font-size: 0.95rem;">
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> 2 and 3 letter CVC word blending & reading</li>
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> Number tracing 1-50, simple counting, basic shapes</li>
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> General Knowledge: Seasons, Body parts, Plants</li>
          </ul>
        </div>
        <div>
          <h4 style="color: var(--accent-gold-dark); margin-bottom: 0.6rem;"><i class="fas fa-moon"></i> Islamic & Arabic Integration</h4>
          <ul style="list-style: none; padding: 0; color: var(--text-muted); font-size: 0.95rem;">
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> Surah Al-Fatiha, Al-Ikhlas, An-Nas memorization</li>
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> Arabic words for family members, fruits, colors</li>
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> Stories of the Prophets (Adam, Nuh, Ibrahim AS)</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- KG -->
    <div id="kg" style="background: #ffffff; padding: 2.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); margin-bottom: 2.5rem;">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 2px solid var(--bg-body); padding-bottom: 1rem;">
        <div>
          <span style="background: var(--sky-blue-light); color: var(--primary); font-weight: 800; padding: 0.35rem 0.9rem; border-radius: var(--radius-full); font-size: 0.8rem;">AGE: 4.5 - 5.5 YEARS</span>
          <h2 style="font-size: 1.8rem; color: var(--primary-dark); margin-top: 0.4rem;">Kindergarten (KG)</h2>
        </div>
        <a href="admissions.php?class=3" class="btn btn-gold"><i class="fas fa-paper-plane"></i> Apply for KG</a>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <div>
          <h4 style="color: var(--primary); margin-bottom: 0.6rem;"><i class="fas fa-star"></i> Cambridge Early Learning Focus</h4>
          <ul style="list-style: none; padding: 0; color: var(--text-muted); font-size: 0.95rem;">
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> Sentence reading & simple story writing</li>
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> Math: Addition, Subtraction, Patterns, Money concept</li>
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> Science: Living & Non-living things, Weather</li>
          </ul>
        </div>
        <div>
          <h4 style="color: var(--accent-gold-dark); margin-bottom: 0.6rem;"><i class="fas fa-moon"></i> Islamic & Arabic Integration</h4>
          <ul style="list-style: none; padding: 0; color: var(--text-muted); font-size: 0.95rem;">
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> Arabic Letter Harakat (Fatha, Kasra, Damma) reading</li>
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> Memorization of 5 Short Surahs with basic meaning</li>
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> Daily Sunnah of Eating, Sleeping, Wudu steps</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Class 1 & Class 2 -->
    <div id="class1" style="background: #ffffff; padding: 2.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 2px solid var(--bg-body); padding-bottom: 1rem;">
        <div>
          <span style="background: var(--sky-blue-light); color: var(--primary); font-weight: 800; padding: 0.35rem 0.9rem; border-radius: var(--radius-full); font-size: 0.8rem;">AGE: 5.5 - 7.5 YEARS</span>
          <h2 style="font-size: 1.8rem; color: var(--primary-dark); margin-top: 0.4rem;">Class One & Class Two (Primary Stage 1-2)</h2>
        </div>
        <a href="admissions.php?class=4" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Apply for Primary Classes</a>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <div>
          <h4 style="color: var(--primary); margin-bottom: 0.6rem;"><i class="fas fa-star"></i> Cambridge Primary Curriculum</h4>
          <ul style="list-style: none; padding: 0; color: var(--text-muted); font-size: 0.95rem;">
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> <strong>English:</strong> Reading fluency, grammar, creative writing</li>
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> <strong>Mathematics:</strong> Number sense, geometry, measurement</li>
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> <strong>Science:</strong> Biology basics, forces, materials, space</li>
          </ul>
        </div>
        <div>
          <h4 style="color: var(--accent-gold-dark); margin-bottom: 0.6rem;"><i class="fas fa-moon"></i> Islamic & Quranic Mastery</h4>
          <ul style="list-style: none; padding: 0; color: var(--text-muted); font-size: 0.95rem;">
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> <strong>Tajweed:</strong> Madd, Gunnah, Ikhfa, Idgham rules</li>
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> <strong>Hifz:</strong> Systematic memorization of Juz 30</li>
            <li style="margin-bottom: 0.4rem;"><i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i> <strong>Islamic Ethics:</strong> Seerah of Prophet Muhammad (ﷺ)</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
