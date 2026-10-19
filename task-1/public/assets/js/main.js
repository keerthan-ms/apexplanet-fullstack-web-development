(function () {
  'use strict';

  var THEME_KEY = 'apex-task1-prefers-motion';

  var skills = ['PHP', 'MySQL', 'JavaScript', 'CSS', 'Linux', 'Git'];
  var shown = [];

  function greet(name) {
    return 'Hello, ' + name + '!';
  }

  function addSkill(skill) {
    if (skills.indexOf(skill) === -1) {
      skills.push(skill);
    }
    shown.push(skill);
  }

  for (var i = 0; i < skills.length; i++) {
    addSkill(skills[i]);
  }

  function initNav() {
    var toggle = document.querySelector('.nav-toggle');
    var list = document.getElementById('navlist');
    if (!toggle || !list) { return; }

    toggle.addEventListener('click', function () {
      var open = list.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', function (event) {
      if (!list.contains(event.target) && !toggle.contains(event.target)) {
        list.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  function initReveal() {
    var items = document.querySelectorAll('.reveal');
    if (!items.length) { return; }

    if (!('IntersectionObserver' in window)) {
      for (var j = 0; j < items.length; j++) { items[j].classList.add('visible'); }
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15 });

    for (var k = 0; k < items.length; k++) {
      observer.observe(items[k]);
    }
  }

  function initPasswordToggle() {
    var pw = document.getElementById('password');
    var toggle = document.getElementById('toggle-pw');
    if (!pw || !toggle) { return; }

    toggle.addEventListener('change', function () {
      pw.type = toggle.checked ? 'text' : 'password';
    });
  }

  function initCounter() {
    var counter = document.getElementById('char-count');
    var message = document.getElementById('message');
    if (!counter || !message) { return; }

    message.addEventListener('keyup', function () {
      counter.textContent = String(message.value.length);
    });
    message.addEventListener('input', function () {
      counter.textContent = String(message.value.length);
    });
  }

  function initYear() {
    var slots = document.querySelectorAll('[data-year]');
    var year = new Date().getFullYear();
    for (var y = 0; y < slots.length; y++) {
      slots[y].textContent = String(year);
    }
  }

  function init() {
    initNav();
    initReveal();
    initPasswordToggle();
    initCounter();
    initYear();
  }

  window.ApexPortfolio = {
    greet: greet,
    skills: skills,
    shown: shown,
    storageKey: THEME_KEY
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
