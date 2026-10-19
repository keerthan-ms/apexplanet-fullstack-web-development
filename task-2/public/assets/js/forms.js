/* ---------------------------------------------------------------------
   Task 2 — form behaviour.

   Three things the brief asks for:
     1. validate the login and registration forms
     2. confirm the registration password matches
     3. toggle password visibility

   Plus the AJAX availability check, so the page never reloads while the
   user is typing.
   --------------------------------------------------------------------- */

(function () {
  'use strict';

  var AJAX_ENDPOINT = 'api/check-availability.php';
  var DEBOUNCE_MS = 350;

  /* --------------------------------------------------------- helpers --- */

  function $(selector, scope) {
    return (scope || document).querySelector(selector);
  }

  function $all(selector, scope) {
    return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
  }

  function debounce(fn, wait) {
    var timer = null;
    return function () {
      var args = arguments, self = this;
      window.clearTimeout(timer);
      timer = window.setTimeout(function () { fn.apply(self, args); }, wait);
    };
  }

  /** Validation rules keyed by field id. */
  var RULES = {
    email: {
      test: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); },
      message: 'Enter a valid email address.'
    },
    username: {
      test: function (v) { return /^[A-Za-z0-9_.]{3,32}$/.test(v); },
      message: 'Use 3 to 32 characters: letters, digits, dot or underscore.'
    },
    'reset-email': {
      test: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); },
      message: 'Enter a valid email address.'
    }
  };

  /* -------------------------------------------------- password rules --- */

  function passwordProblems(value) {
    var problems = [];
    if (value.length < 8) { problems.push('at least 8 characters'); }
    if (!/[A-Za-z]/.test(value)) { problems.push('a letter'); }
    if (!/\d/.test(value)) { problems.push('a digit'); }
    return problems;
  }

  function passwordStrength(value) {
    if (!value) { return 0; }
    var score = 0;
    if (value.length >= 8) { score += 25; }
    if (value.length >= 12) { score += 15; }
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) { score += 20; }
    if (/\d/.test(value)) { score += 20; }
    if (/[^A-Za-z0-9]/.test(value)) { score += 20; }
    return Math.min(score, 100);
  }

  var STRENGTH_COLOURS = [
    { max: 25,  colour: '#e03131', label: 'Weak' },
    { max: 50,  colour: '#f59f00', label: 'Fair' },
    { max: 75,  colour: '#0ca678', label: 'Good' },
    { max: 100, colour: '#2f9e44', label: 'Strong' }
  ];

  /* ------------------------------------------------------ messaging --- */

  function setFieldState(input, ok, message) {
    if (!input) { return; }
    input.classList.toggle('is-invalid', !ok);
    input.classList.toggle('is-valid', ok);
    input.setAttribute('aria-invalid', ok ? 'false' : 'true');

    var slot = document.getElementById(input.id + '-msg');
    if (slot) { slot.textContent = ok ? '' : message; }
  }

  function setMessage(input, text, kind) {
    var slot = document.querySelector('[data-message-for="' + input.id + '"]');
    if (!slot) { return; }
    slot.textContent = text || '';
    slot.className = 'field-message' + (kind ? ' ' + kind : '');
  }

  /* ------------------------------------------------- password toggle --- */

  function initPasswordToggles() {
    $all('[data-toggle-password]').forEach(function (button) {
      var input = document.getElementById(button.getAttribute('data-toggle-password'));
      if (!input) { return; }

      var icon = button.querySelector('i');

      button.addEventListener('click', function () {
        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        button.setAttribute('title', showing ? 'Show password' : 'Hide password');
        if (icon) { icon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash'; }
        input.focus();
      });
    });
  }

  /* ----------------------------------------------------- strength bar --- */

  function initStrengthBar() {
    var password = document.getElementById('password');
    var fill = document.getElementById('strength-fill');
    var hint = document.getElementById('password-hint');
    if (!password || !fill) { return; }

    password.addEventListener('input', function () {
      var value = password.value;
      var score = passwordStrength(value);
      var band = STRENGTH_COLOURS.filter(function (c) { return score <= c.max; })[0] || STRENGTH_COLOURS[3];

      fill.style.width = score + '%';
      fill.style.background = band.colour;

      if (!value) {
        hint.textContent = 'Use at least 8 characters with a letter and a digit.';
        hint.className = 'field-message';
      } else {
        var problems = passwordProblems(value);
        hint.textContent = problems.length
          ? 'Needs ' + problems.join(', ') + '.'
          : band.label + ' password.';
        hint.className = 'field-message ' + (problems.length ? 'error' : 'ok');
      }
    });
  }

  /* ------------------------------------------------- password match --- */

  function initPasswordMatch() {
    var password = document.getElementById('password');
    var confirm = document.getElementById('confirm_password');
    if (!password || !confirm) { return; }

    function compare() {
      if (!confirm.value) {
        setMessage(confirm, '', '');
        confirm.classList.remove('is-invalid', 'is-valid');
        return;
      }
      var match = confirm.value === password.value;
      setMessage(
        confirm,
        match ? 'Passwords match.' : 'The two passwords do not match.',
        match ? 'ok' : 'error'
      );
      setFieldState(confirm, match, 'The two passwords do not match.');
    }

    confirm.addEventListener('input', compare);
    password.addEventListener('input', compare);
  }

  /* ------------------------------------------------------ AJAX check --- */

  function initAjaxChecks() {
    $all('[data-ajax-check]').forEach(function (input) {
      var field = input.getAttribute('data-ajax-check');
      var spinner = document.getElementById(field + '-spinner');

      var run = debounce(function () {
        var value = input.value.trim();
        setMessage(input, '', '');

        if (!value) { return; }

        if (spinner) { spinner.classList.remove('d-none'); }

        var url = AJAX_ENDPOINT + '?field=' + encodeURIComponent(field) +
                  '&value=' + encodeURIComponent(value);

        fetch(url, {
          method: 'GET',
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin'
        })
          .then(function (response) {
            return response.json().then(function (data) {
              return { ok: response.ok, data: data };
            });
          })
          .then(function (result) {
            var data = result.data || {};
            if (typeof data.available === 'boolean') {
              setMessage(input, data.message || '', data.available ? 'ok' : 'error');
            } else {
              setMessage(input, data.error || 'Could not check availability.', 'error');
            }
          })
          .catch(function () {
            setMessage(input, 'Availability check is unavailable offline.', 'error');
          })
          .then(function () {
            if (spinner) { spinner.classList.add('d-none'); }
          });
      }, DEBOUNCE_MS);

      input.addEventListener('input', run);
      input.addEventListener('blur', run);
    });
  }

  /* ------------------------------------------------------ validation --- */

  function validateField(input) {
    var value = (input.value || '').trim();
    var id = input.id;

    if (input.type === 'checkbox') {
      var okCheck = input.checked;
      setFieldState(input, okCheck, 'You must accept the terms to continue.');
      setMessage(input, okCheck ? '' : 'You must accept the terms to continue.', okCheck ? '' : 'error');
      return okCheck;
    }

    if (input.hasAttribute('minlength') && value && value.length < parseInt(input.getAttribute('minlength'), 10)) {
      setFieldState(input, false, 'Must be at least ' + input.getAttribute('minlength') + ' characters.');
      return false;
    }

    if (input.hasAttribute('pattern') && value && !new RegExp('^(?:' + input.getAttribute('pattern') + ')$').test(value)) {
      setFieldState(input, false, 'That format is not allowed.');
      return false;
    }

    var rule = RULES[id];
    if (rule) {
      var ok = value.length > 0 && rule.test(value);
      setFieldState(input, ok, rule.message);
      return ok;
    }

    if (input.required && !value) {
      setFieldState(input, false, 'This field is required.');
      return false;
    }

    setFieldState(input, value.length > 0, '');
    return true;
  }

  function initValidation() {
    $all('form[data-validate]').forEach(function (form) {
      var inputs = $all('input, select, textarea', form).filter(function (el) {
        return el.type !== 'hidden';
      });

      inputs.forEach(function (input) {
        ['blur', 'change'].forEach(function (evt) {
          input.addEventListener(evt, function () { validateField(input); });
        });
        input.addEventListener('input', function () {
          if (input.classList.contains('is-invalid')) { validateField(input); }
        });
      });

      form.addEventListener('submit', function (event) {
        var allValid = true;
        var firstBad = null;

        inputs.forEach(function (input) {
          if (!validateField(input)) {
            allValid = false;
            if (!firstBad) { firstBad = input; }
          }
        });

        if (!allValid) {
          event.preventDefault();
          if (firstBad) { firstBad.focus(); }
          return;
        }

        // Task 2 stops here on purpose: the server decides the real outcome.
        event.preventDefault();
        var notice = document.createElement('div');
        notice.className = 'alert alert-success d-flex align-items-center gap-2';
        notice.setAttribute('role', 'status');
        notice.innerHTML = '<i class="bi bi-check-circle-fill"></i>' +
          '<span>Form validated in the browser. Task 2 stops at the front end; ' +
          'server-side authentication is implemented in Task 3.</span>';
        form.parentNode.insertBefore(notice, form);
        window.setTimeout(function () { notice.remove(); }, 8000);
      });
    });
  }

  /* ----------------------------------------------------------- boot --- */

  function init() {
    initPasswordToggles();
    initStrengthBar();
    initPasswordMatch();
    initAjaxChecks();
    initValidation();
  }

  window.ApexTask2 = {
    passwordStrength: passwordStrength,
    passwordProblems: passwordProblems,
    validateField: validateField,
    debounce: debounce
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
