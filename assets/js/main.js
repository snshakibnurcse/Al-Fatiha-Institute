/* ==========================================================================
   AL FATIHA INSTITUTE - Frontend JavaScript
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
  // Mobile Navigation Toggle
  const menuToggle = document.querySelector('.menu-toggle');
  const navMenu = document.querySelector('.nav-menu');

  if (menuToggle && navMenu) {
    menuToggle.addEventListener('click', function () {
      navMenu.classList.toggle('active');
      const icon = menuToggle.querySelector('i');
      if (icon) {
        if (navMenu.classList.contains('active')) {
          icon.className = 'fas fa-times';
        } else {
          icon.className = 'fas fa-bars';
        }
      }
    });
  }

  // Header Scroll Effect
  const header = document.querySelector('.site-header');
  window.addEventListener('scroll', function () {
    if (window.scrollY > 50) {
      header?.classList.add('scrolled');
    } else {
      header?.classList.remove('scrolled');
    }
  });

  // Number Counter Animation
  const counters = document.querySelectorAll('.counter-number');
  if (counters.length > 0) {
    const animateCounters = () => {
      counters.forEach(counter => {
        const target = +counter.getAttribute('data-target');
        const count = +counter.innerText;
        const speed = 200;
        const inc = target / speed;

        if (count < target) {
          counter.innerText = Math.ceil(count + inc);
          setTimeout(animateCounters, 15);
        } else {
          counter.innerText = target;
        }
      });
    };

    // Trigger when section comes into view
    let counted = false;
    window.addEventListener('scroll', function () {
      const statsSec = document.querySelector('.stats-section');
      if (statsSec) {
        const pos = statsSec.getBoundingClientRect().top;
        if (pos < window.innerHeight && !counted) {
          animateCounters();
          counted = true;
        }
      }
    });
  }
});
