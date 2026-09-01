/* Vemovent — main.js */
(function () {
  'use strict';

  /* ---------- Sticky header ---------- */
  var header = document.querySelector('.header');
  function onScroll() {
    if (header) header.classList.toggle('is-scrolled', window.scrollY > 8);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- Mobile nav ---------- */
  var burger = document.querySelector('.burger');
  var nav = document.querySelector('.nav');
  if (burger && nav) {
    burger.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      burger.classList.toggle('is-open', open);
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) {
        nav.classList.remove('is-open');
        burger.classList.remove('is-open');
        burger.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ---------- Reveal on scroll ---------- */
  var reveals = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && reveals.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-in');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add('is-in'); });
  }

  /* ---------- Footer year ---------- */
  var yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  /* ---------- Contact form ---------- */
  var form = document.getElementById('contact-form');
  if (form) {
    var status = document.getElementById('form-status');

    function show(kind, msg) {
      if (!status) return;
      status.className = 'form-status ' + (kind === 'ok' ? 'is-ok' : 'is-err');
      status.textContent = msg;
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      var submitBtn = form.querySelector('[type="submit"]');
      var original = submitBtn ? submitBtn.innerHTML : '';

      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Sender …'; }

      var data = new FormData(form);

      fetch(form.getAttribute('action'), { method: 'POST', body: data })
        .then(function (res) { return res.json().catch(function () { throw new Error('bad-response'); }); })
        .then(function (json) {
          if (json && json.ok) {
            show('ok', 'Takk! Meldingen din er sendt. Vi tar kontakt så snart som mulig – vanligvis samme virkedag.');
            form.reset();
          } else {
            show('err', (json && json.message) || 'Beklager, noe gikk galt ved sending. Ring oss i stedet på +47 45 65 52 92.');
          }
        })
        .catch(function () {
          show('err', 'Kunne ikke sende skjemaet. Vennligst ring oss på +47 45 65 52 92 eller send e-post til post@vemovent.no.');
        })
        .finally(function () {
          if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = original; }
        });
    });
  }
})();
