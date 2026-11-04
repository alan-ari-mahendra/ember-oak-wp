/* Ember & Oak — UI glue. WooCommerce stays the source of truth for cart/product data. */
(function ($) {
  'use strict';
  var toast = document.getElementById('eo-toast'), tt;
  function showToast(name) {
    if (!toast) return;
    document.getElementById('eo-toast-name').textContent = name + ' ' + (window.EO ? EO.i18n.added : 'added to cart');
    toast.classList.add('show'); clearTimeout(tt); tt = setTimeout(function () { toast.classList.remove('show'); }, 2600);
  }
  // Toast after WooCommerce AJAX add to cart (cart count refreshes via WC fragments).
  $(document.body).on('added_to_cart', function (e, frag, hash, $btn) {
    showToast(($btn && $btn.data('product_name')) || 'Item');
  });

  // Sticky header: announcement bar height drives the negative sticky offset
  function annH() { var a = document.querySelector('.announce'); if (a) document.documentElement.style.setProperty('--eo-announce-h', a.offsetHeight + 'px'); }
  annH(); window.addEventListener('resize', annH);

  // Search panel (header dropdown)
  (function () {
    var btn = document.getElementById('eo-search-toggle'), panel = document.getElementById('eo-search');
    if (!btn || !panel) return;
    function set(open) {
      panel.hidden = !open; btn.setAttribute('aria-expanded', open); document.querySelector('.eo-header').classList.toggle('search-open', open);
      if (open) { var i = panel.querySelector('input[type=search]'); i && i.focus(); } else { btn.focus(); }
    }
    btn.addEventListener('click', function () { set(panel.hidden); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) set(false); });
    document.addEventListener('click', function (e) { if (!panel.hidden && !panel.contains(e.target) && !btn.contains(e.target)) { panel.hidden = true; btn.setAttribute('aria-expanded', 'false'); } });
  })();

  // Mobile menu
  $(document).on('click', '.burger', function () {
    var open = $('.eo-header').toggleClass('menu-open').hasClass('menu-open');
    $(this).attr({ 'aria-expanded': open, 'aria-label': open ? 'Close menu' : 'Open menu' });
  });
  $(document).on('keydown', function (e) { if (e.key === 'Escape') $('.eo-header').removeClass('menu-open').find('.burger').attr('aria-expanded', 'false'); });

  // Roast filter (pressed state + hide non-matching; products come from WooCommerce)
  $(document).on('click', '.chip', function () {
    var f = this.dataset.f, $g = $('#pgrid');
    $('.chip').attr('aria-pressed', 'false'); $(this).attr('aria-pressed', 'true');
    $g.find('.pcard').each(function () { var m = f === 'all' || this.dataset.c.split(' ').indexOf(f) > -1; this.hidden = !m; });
  });

  // Subscription cadence preview (homepage)
  $(document).on('change', '.cadence input[name=cad]', function () {
    var $c = $(this).closest('.cadence'); $c.find('[data-next]').text('Next delivery in ' + this.value + ' week' + (this.value > 1 ? 's' : ''));
  });

  // Product form
  var f = document.getElementById('pform');
  if (f) {
    var vars = JSON.parse(f.dataset.variations || '[]'), disc = parseFloat(f.dataset.disc),
        $f = $(f), money = function (n) { return '$' + n.toFixed(2); };
    function cur() {
      var s = f.querySelector('[name=attribute_size]:checked'), g = f.querySelector('[name=attribute_grind]:checked');
      return vars.find(function (v) { return v.attributes.attribute_size === s.value && v.attributes.attribute_grind === g.value; });
    }
    function calc() {
      var v = cur(), s = f.querySelector('[name=attribute_size]:checked'), sub = (f.querySelector('[name=eo_mode]:checked') || {}).value === 'sub',
          q = +f.querySelector('#qty').value;
      vars.forEach(function (x) { if (x.attributes.attribute_grind === f.querySelector('[name=attribute_grind]:checked').value) { var el = f.querySelector('[data-size-price="' + x.attributes.attribute_size + '"]'); if (el) el.textContent = money(x.display_price); } });
      if (!v) return;
      f.querySelector('[name=variation_id]').value = v.variation_id;
      var base = v.display_regular_price || v.display_price, unit = sub ? base * (1 - disc) : base;
      var ps = document.getElementById('pSub'), po = document.getElementById('pOnce');
      if (ps) ps.innerHTML = money(base * (1 - disc)) + '<s>' + money(base) + '</s>';
      if (po) po.textContent = money(base);
      document.getElementById('ctaPrice').textContent = money(unit * q);
      document.getElementById('stickyPrice').textContent = money(unit * q);
      document.getElementById('stickyVar').textContent = s.value;
      var cad = document.getElementById('cad'); if (cad) cad.classList.toggle('show', sub);
      document.querySelectorAll('#purchase > label').forEach(function (l) { l.classList.toggle('on', l.querySelector('input').checked); });
    }
    f.addEventListener('change', calc);
    var qty = f.querySelector('#qty');
    document.getElementById('qinc').onclick = function () { qty.value = +qty.value + 1; calc(); };
    document.getElementById('qdec').onclick = function () { qty.value = Math.max(1, +qty.value - 1); calc(); };
    calc();

    // AJAX submit using WooCommerce's own endpoint
    function submit(e) {
      if (e) e.preventDefault();
      var data = $f.serializeArray().filter(function (x) { return x.name !== 'add-to-cart'; });
      data.push({ name: 'product_id', value: f.dataset.product_id });
      var btn = $('#eo-atc').addClass('loading');
      $.post(wc_add_to_cart_params.wc_ajax_url.replace('%%endpoint%%', 'eo_add_to_cart'), data).done(function (r) {
        if (r && r.error && r.product_url) { window.location = r.product_url; return; }
        $(document.body).trigger('wc_fragment_refresh'); showToast(f.dataset.name);
      }).always(function () { btn.removeClass('loading'); });
    }
    f.addEventListener('submit', submit);
    document.getElementById('stickyAtc').addEventListener('click', submit);

    // gallery thumbs
    $(document).on('click', '.thumb', function () {
      var i = this.dataset.i; $('.thumb').attr('aria-pressed', 'false'); $(this).attr('aria-pressed', 'true');
      $('.gimg').removeClass('on').filter('[data-i=' + i + ']').addClass('on');
    });

    // Sticky bar: only < 720px, only while the form CTA is out of view and footer not reached
    var bar = document.getElementById('stickybar'), mq = window.matchMedia('(max-width:719.98px)');
    if ('IntersectionObserver' in window) {
      var hide = { cta: false, foot: false };
      function upd() { var on = mq.matches && !hide.cta && !hide.foot; bar.classList.toggle('show', on); bar.setAttribute('aria-hidden', on ? 'false' : 'true'); $('#stickyAtc').attr('tabindex', on ? 0 : -1); }
      var io = new IntersectionObserver(function (es) { es.forEach(function (en) { hide[en.target.dataset.k] = en.isIntersecting; }); upd(); });
      var cta = f.querySelector('.cta'); cta.dataset.k = 'cta'; io.observe(cta);
      var ft = document.querySelector('.eo-footer'); if (ft) { ft.dataset.k = 'foot'; io.observe(ft); }
      mq.addEventListener('change', upd); upd();
    }
  }
})(jQuery);
