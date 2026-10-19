(function () {
  'use strict';

  var form = document.getElementById('contact-form');
  if (!form) { return; }

  var statusBox = document.getElementById('form-status');

  var RULES = [
    {
      id: 'name',
      test: function (v) { return v.length >= 2; },
      message: 'Please enter your full name (at least 2 characters).'
    },
    {
      id: 'email',
      test: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); },
      message: 'Enter a valid email address, for example name@example.com.'
    },
    {
      id: 'phone',
      optional: true,
      test: function (v) { return /^[0-9+\-\s]{7,18}$/.test(v); },
      message: 'Phone may only contain digits, spaces, + and -, 7 to 18 characters.'
    },
    {
      id: 'topic',
      test: function (v) { return v !== ''; },
      message: 'Please choose a topic.'
    },
    {
      id: 'password',
      test: function (v) { return v.length >= 8; },
      message: 'Password must be at least 8 characters long.'
    },
    {
      id: 'website',
      optional: true,
      test: function (v) { return /^https?:\/\/.+\..+/.test(v); },
      message: 'Website must start with http:// or https:// and include a domain.'
    },
    {
      id: 'message',
      test: function (v) { return v.length >= 20 && v.length <= 1000; },
      message: 'Message must be between 20 and 1000 characters.'
    },
    {
      id: 'consent',
      test: function () { return document.getElementById('consent').checked; },
      message: 'Please tick the consent box to continue.'
    }
  ];

  function fieldFor(id) {
    return document.getElementById(id);
  }

  function errorSlotFor(id) {
    return form.querySelector('[data-error-for="' + id + '"]');
  }

  function valueOf(element) {
    if (!element) { return ''; }
    if (element.type === 'checkbox') { return element.checked ? '1' : ''; }
    return (element.value || '').trim();
  }

  function showError(id, message) {
    var slot = errorSlotFor(id);
    var field = fieldFor(id);
    if (slot) { slot.textContent = message; }
    if (field) {
      field.setAttribute('aria-invalid', 'true');
      field.classList.add('field-error');
    }
  }

  function clearError(id) {
    var slot = errorSlotFor(id);
    var field = fieldFor(id);
    if (slot) { slot.textContent = ''; }
    if (field) {
      field.removeAttribute('aria-invalid');
      field.classList.remove('field-error');
    }
  }

  function validateRule(rule) {
    var field = fieldFor(rule.id);
    var value = valueOf(field);

    if (rule.optional && value === '') {
      clearError(rule.id);
      return true;
    }
    if (rule.test(value)) {
      clearError(rule.id);
      return true;
    }
    showError(rule.id, rule.message);
    return false;
  }

  function validateAll() {
    var valid = true;
    var firstBad = null;
    for (var i = 0; i < RULES.length; i++) {
      if (!validateRule(RULES[i])) {
        valid = false;
        if (!firstBad) { firstBad = fieldFor(RULES[i].id); }
      }
    }
    return { valid: valid, firstBad: firstBad };
  }

  function setStatus(text, kind) {
    if (!statusBox) { return; }
    statusBox.textContent = text;
    statusBox.className = 'alert alert-' + (kind || 'info');
    statusBox.hidden = false;
  }

  RULES.forEach(function (rule) {
    var field = fieldFor(rule.id);
    if (!field) { return; }
    ['blur', 'change', 'keyup'].forEach(function (evt) {
      field.addEventListener(evt, function () { validateRule(rule); });
    });
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    var result = validateAll();

    if (!result.valid) {
      setStatus('Please fix the highlighted fields and try again.', 'error');
      if (result.firstBad) { result.firstBad.focus(); }
      return;
    }

    var data = new FormData(form);
    var interests = data.getAll('interests[]');
    if (!interests.length) { interests = ['(none selected)']; }

    setStatus(
      'Message validated. Topics selected: ' + interests.join(', ') +
      '. This Task 1 form is a front-end demonstration, so nothing was transmitted.',
      'success'
    );
    form.reset();
  });

  form.addEventListener('reset', function () {
    for (var i = 0; i < RULES.length; i++) { clearError(RULES[i].id); }
    if (statusBox) { statusBox.hidden = true; }
  });
})();
