/* Reveal halus saat elemen masuk layar, dengan jeda bertahap 70ms.
   Dimatikan otomatis kalau pengguna meminta kurangi gerak. */
(function () {
  'use strict';

  var items = document.querySelectorAll('.rv');
  if (!items.length) return;

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduced || !('IntersectionObserver' in window)) {
    items.forEach(function (el) { el.classList.add('in'); });
    return;
  }

  var obs = new IntersectionObserver(function (entries) {
    var i = 0;
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      var el = entry.target;
      setTimeout(function () { el.classList.add('in'); }, i * 70);
      i++;
      obs.unobserve(el);
    });
  }, { rootMargin: '0px 0px -10% 0px', threshold: 0.1 });

  items.forEach(function (el) { obs.observe(el); });
})();
