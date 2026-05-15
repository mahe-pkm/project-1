/**
 * Main Portfolio Interactions
 */

document.addEventListener('DOMContentLoaded', () => {
  const header = document.querySelector('.header');
  const navToggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('.nav');
  const navLinks = document.querySelectorAll('.nav__list a');
  
  // Theme Toggle Elements
  const themeToggle = document.getElementById('theme-toggle');
  const body = document.documentElement; // Using html element for data-theme

  // 1. Theme Switching Logic
  const currentTheme = localStorage.getItem('theme') || 'dark';
  body.setAttribute('data-theme', currentTheme);

  themeToggle.addEventListener('click', () => {
    const newTheme = body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    body.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
  });

  // 2. Header Scroll & Scroll Progress Logic
  const scrollTopBtn = document.getElementById('scroll-top');
  const progressCircle = document.getElementById('scroll-progress');
  const pathLength = 113.1; // 2 * Math.PI * 18 (approx)

  if (progressCircle) {
    progressCircle.style.strokeDasharray = `${pathLength} ${pathLength}`;
    progressCircle.style.strokeDashoffset = pathLength;
  }

  const handleScroll = () => {
    // Header effect
    if (window.scrollY > 50) {
      header.classList.add('header--scrolled');
    } else {
      header.classList.remove('header--scrolled');
    }

    // Scroll progress & Back to top visibility
    const scrollTotal = document.documentElement.scrollHeight - window.innerHeight;
    const scrollProgress = window.scrollY / scrollTotal;
    
    if (window.scrollY > 300) {
      scrollTopBtn.classList.add('scroll-top--visible');
    } else {
      scrollTopBtn.classList.remove('scroll-top--visible');
    }

    if (progressCircle) {
      const offset = pathLength - (scrollProgress * pathLength);
      progressCircle.style.strokeDashoffset = offset;
    }
  };

  window.addEventListener('scroll', handleScroll);
  handleScroll();

  // 3. Back to Top Click
  scrollTopBtn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  // 4. Mobile Menu Toggle
  const toggleMenu = () => {
    navToggle.classList.toggle('nav-toggle--open');
    nav.classList.toggle('nav--open');
    document.body.style.overflow = nav.classList.contains('nav--open') ? 'hidden' : '';
  };

  navToggle.addEventListener('click', toggleMenu);

  navLinks.forEach(link => {
    link.addEventListener('click', () => {
      if (nav.classList.contains('nav--open')) {
        toggleMenu();
      }
    });
  });

  // 5. Reveal Animation
  const revealElements = document.querySelectorAll('.card, .section-title, .hero__content > *');
  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
        revealObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  revealElements.forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
    revealObserver.observe(el);
  });
});
