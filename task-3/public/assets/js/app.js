(function () {
  'use strict';

  var THEME_KEY = 'apex-task3-motion';

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

  /* ------------------------------- destructive action confirmation --- */

  /**
   * Any form carrying data-confirm asks before submitting. The message is read
   * from the attribute and shown with the browser's native dialog, which is
   * keyboard and screen-reader accessible and cannot be styled away.
   *
   * The server checks the CSRF token and the request method regardless, so a
   * user who bypasses this dialog still cannot forge the request.
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

  /* --------------------------------------------- live image preview --- */

  function initAvatarPreview() {
    var input = document.getElementById('avatar');
    var avatar = $('.avatar-sm');
    if (!input || !avatar) { return; }

    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file) { return; }

      // Client-side preview only. The authoritative checks (size and real MIME
      // type) all happen on the server.
      if (file.size > 2 * 1024 * 1024) {
        return;
      }
      var reader = new FileReader();
      reader.onload = function (event) {
        avatar.src = event.target.result;
      };
      reader.readAsDataURL(file);
    });
  }

  /* -------------------------------------------------- character count --- */

  function initCounters() {
    $all('textarea[maxlength]').forEach(function (area) {
      var max = parseInt(area.getAttribute('maxlength'), 10);
      var id = area.id;
      if (!id) { return; }

      var hint = document.querySelector('[data-counter-for="' + id + '"]');
      if (!hint) { return; }

      function update() { hint.textContent = area.value.length + ' / ' + max; }
      area.addEventListener('input', update);
      update();
    });
  }

  function init() {
    initNav();
    initConfirmations();
    initPasswordToggles();
    initAvatarPreview();
    initCounters();
  }

  window.ApexTask3 = { storageKey: THEME_KEY };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
