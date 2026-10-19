(function () {
  'use strict';

  function $(selector, scope) {
    return (scope || document).querySelector(selector);
  }

  function $all(selector, scope) {
    return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
  }

  /* ------------------------------------------------------- nav toggle --- */

  function initNav() {
    var toggle = $('.nav-toggle');
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

  /* --------------------------------------------- destructive confirmation --- */

  /**
   * Forms carrying data-confirm ask before submitting. The server checks the
   * CSRF token and the HTTP method regardless, so bypassing this dialog does
   * not make the request forgeable.
   */
  function initConfirmations() {
    $all('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!window.confirm(form.getAttribute('data-confirm'))) {
          event.preventDefault();
        }
      });
    });
  }

  /* ------------------------------------------------- password toggle --- */

  function initPasswordToggles() {
    $all('[data-toggle-password]').forEach(function (button) {
      var input = document.getElementById(button.getAttribute('data-toggle-password'));
      if (!input) { return; }

      button.addEventListener('click', function () {
        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.textContent = showing ? 'Show' : 'Hide';
        button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        input.focus();
      });
    });
  }

  /* ------------------------------------------- chart theme for light mode --- */

  /**
   * The chart colours are tuned for the dark palette. When the visitor is on a
   * light colour scheme, fall back to the darker grid and tick colours so the
   * chart stays readable instead of fading into the background.
   */
  function prefersLight() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
  }

  function init() {
    initNav();
    initConfirmations();
    initPasswordToggles();

    // Chart.js reads colours when the chart is created, so nothing to do here
    // beyond exposing the preference for the inline script to check.
    window.ApexShop = { prefersLight: prefersLight };
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
