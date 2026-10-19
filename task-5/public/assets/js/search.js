(function () {
  'use strict';

  var form = document.getElementById('course-search');
  var grid = document.getElementById('course-grid');
  var count = document.getElementById('result-count');
  var clear = document.getElementById('clear-filters');
  if (!form || !grid) { return; }

  var DEBOUNCE_MS = 280;
  var controller = null;

  function debounce(fn, wait) {
    var t = null;
    return function () {
      var args = arguments, self = this;
      window.clearTimeout(t);
      t = window.setTimeout(function () { fn.apply(self, args); }, wait);
    };
  }

  function params() {
    var data = new FormData(form);
    var out = {};
    data.forEach(function (v, k) { if (v !== '') { out[k] = v; } });
    return new URLSearchParams(out).toString();
  }

  function label(n) {
    return n + ' course' + (n === 1 ? '' : 's');
  }

  function setBusy(busy) {
    grid.setAttribute('aria-busy', busy ? 'true' : 'false');
    grid.style.opacity = busy ? '0.55' : '1';
  }

  function run() {
    if (controller) { controller.abort(); }
    controller = new AbortController();
    setBusy(true);

    fetch('api/search-courses.php?' + params(), {
      signal: controller.signal,
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    })
      .then(function (r) {
        if (!r.ok) { throw new Error('HTTP ' + r.status); }
        return r.json();
      })
      .then(function (data) {
        // The server sends markup that is already escaped, so inserting it is
        // safe. It is never built from raw user text on this side.
        grid.innerHTML = data.html;
        if (count) { count.textContent = label(data.total); }
        if (data.url) {
          window.history.replaceState({}, '', data.url);
        }
      })
      .catch(function (err) {
        if (err.name === 'AbortError') { return; }
        grid.insertAdjacentHTML(
          'afterbegin',
          '<div class="card"><p class="error-text">Live search is unavailable. ' +
          'Press Search to reload the page.</p></div>'
        );
      })
      .then(function () { setBusy(false); });
  }

  var debouncedRun = debounce(run, DEBOUNCE_MS);

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    run();
  });

  form.addEventListener('input', debouncedRun);
  form.addEventListener('change', run);

  if (clear) {
    clear.addEventListener('click', function (event) {
      event.preventDefault();
      form.reset();
      run();
    });
  }
})();
