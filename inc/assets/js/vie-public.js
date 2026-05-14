(function () {
  'use strict';

  window.Vie = window.Vie || {};

  Vie.ApiError = class extends Error {
    constructor(errors, status) {
      super(errors?.[0]?.message || 'Lỗi không xác định');
      this.errors = errors || [];
      this.status = status;
    }
  };

  Vie.api = {
    async fetch(path, opts) {
      opts = opts || {};
      const headers = {
        'Content-Type': 'application/json',
        'X-WP-Nonce': (window.VieRest && window.VieRest.nonce) || '',
      };
      if (opts.headers) Object.assign(headers, opts.headers);
      if (opts.idempotencyKey) headers['X-Idempotency-Key'] = opts.idempotencyKey;
      const root = (window.VieRest && window.VieRest.root) || '/wp-json/vie/v1/';
      const url = root + path.replace(/^\//, '');
      const resp = await fetch(url, {
        method: opts.method || 'GET',
        headers: headers,
        body: opts.body,
      });
      let json;
      try { json = await resp.json(); } catch (e) {
        throw new Vie.ApiError([{ message: 'Phản hồi không hợp lệ' }], resp.status);
      }
      if (!json.success) throw new Vie.ApiError(json.errors, resp.status);
      return json.data;
    },
    get(path, query) {
      let qs = '';
      if (query) qs = '?' + new URLSearchParams(query).toString();
      return Vie.api.fetch(path + qs, { method: 'GET' });
    },
    post(path, body, opts) {
      opts = opts || {};
      return Vie.api.fetch(path, {
        method: 'POST',
        body: JSON.stringify(body || {}),
        idempotencyKey: opts.idempotencyKey,
      });
    },
  };

  Vie.format = {
    vnd(n) {
      return new Intl.NumberFormat('vi-VN').format(Math.round(Number(n) || 0)) + 'đ';
    },
    date(s) {
      if (!s) return '';
      try {
        const d = new Date(s);
        if (isNaN(d.getTime())) return s;
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        return `${dd}/${mm}/${d.getFullYear()}`;
      } catch (e) { return s; }
    },
    uuid() {
      const b = new Uint8Array(16);
      if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
        crypto.getRandomValues(b);
      } else {
        for (let i = 0; i < 16; i++) b[i] = Math.floor(Math.random() * 256);
      }
      b[6] = (b[6] & 0x0f) | 0x40;
      b[8] = (b[8] & 0x3f) | 0x80;
      const h = Array.from(b, (x) => x.toString(16).padStart(2, '0')).join('');
      return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
    },
  };

  function debounce(fn, ms) {
    let t;
    return function () {
      const args = arguments;
      clearTimeout(t);
      t = setTimeout(() => fn.apply(null, args), ms);
    };
  }

  function showError(root, message) {
    const el = root.querySelector('[data-error]');
    if (el) {
      el.textContent = message || '';
      el.style.display = message ? 'block' : 'none';
    }
  }

  function readQuery() {
    const q = {};
    const sp = new URLSearchParams(window.location.search);
    sp.forEach((v, k) => (q[k] = v));
    return q;
  }

  // ========== Checkout ==========
  function initCheckout(root) {
    const idemKey = Vie.format.uuid();
    const form = root.querySelector('form');
    if (!form) return;

    const summary = root.querySelector('[data-vie-summary]');
    const summaryLines = summary && summary.querySelector('[data-summary-lines]');
    const summaryTotal = summary && summary.querySelector('[data-summary-total]');
    const summaryMessages = summary && summary.querySelector('[data-summary-messages]');
    const submitBtn = form.querySelector('[type=submit]');
    const couponInput = form.querySelector('[name=coupon_code]');
    const couponApplyBtn = root.querySelector('[data-coupon-apply]');
    const couponStatus = root.querySelector('[data-coupon-status]');

    let currentQuote = null;
    let appliedCoupon = null;

    const q = readQuery();
    if (q.room_id) form.elements['room_id'] && (form.elements['room_id'].value = q.room_id);
    if (q.checkin) form.elements['checkin'] && (form.elements['checkin'].value = q.checkin);
    if (q.checkout) form.elements['checkout'] && (form.elements['checkout'].value = q.checkout);

    function buildItem() {
      const fd = new FormData(form);
      const roomId = parseInt(fd.get('room_id') || '0', 10);
      const checkin = String(fd.get('checkin') || '');
      const checkout = String(fd.get('checkout') || '');
      const adults = parseInt(fd.get('adults') || '1', 10);
      const bookingType = String(fd.get('booking_type') || 'room');
      const childAgesRaw = String(fd.get('child_ages') || '').trim();
      const childAges = childAgesRaw
        ? childAgesRaw.split(',').map((s) => parseInt(s.trim(), 10)).filter((n) => !isNaN(n))
        : [];
      if (!roomId || !checkin || !checkout) return null;
      return { room_id: roomId, booking_type: bookingType, checkin, checkout, adults, child_ages: childAges };
    }

    async function refreshQuote() {
      const item = buildItem();
      if (!item) {
        if (summaryLines) summaryLines.innerHTML = '<p class="vie-public__muted">Nhập đủ thông tin chuyến để xem giá.</p>';
        if (summaryTotal) summaryTotal.textContent = '—';
        currentQuote = null;
        return;
      }
      try {
        const data = await Vie.api.post('quote', item);
        currentQuote = data;
        renderSummary(data);
      } catch (e) {
        currentQuote = null;
        if (summaryLines) {
          summaryLines.innerHTML = '<p class="vie-public__error-inline">' + (e.errors?.[0]?.message || 'Không tính được giá') + '</p>';
        }
        if (summaryTotal) summaryTotal.textContent = '—';
      }
    }

    function renderSummary(quote) {
      if (!summaryLines) return;
      const lines = [];
      const nights = quote.nights || 0;
      const numRooms = quote.num_rooms || 1;
      if (quote.requires_quote) {
        lines.push('<p class="vie-public__muted">Số khách vượt sức chứa thông thường — vui lòng liên hệ để được báo giá.</p>');
      } else {
        lines.push(`<div class="vie-public__sum-row"><span>${numRooms} phòng × ${nights} đêm</span><span>${Vie.format.vnd(quote.room_subtotal || 0)}</span></div>`);
        if (quote.extra_adult_subtotal) lines.push(`<div class="vie-public__sum-row"><span>Phụ thu người lớn</span><span>${Vie.format.vnd(quote.extra_adult_subtotal)}</span></div>`);
        if (quote.child_surcharge_total) lines.push(`<div class="vie-public__sum-row"><span>Phụ thu trẻ em</span><span>${Vie.format.vnd(quote.child_surcharge_total)}</span></div>`);
        if (quote.ticket_subtotal) lines.push(`<div class="vie-public__sum-row"><span>Vé xe limousine</span><span>${Vie.format.vnd(quote.ticket_subtotal)}</span></div>`);
        if (quote.discount) lines.push(`<div class="vie-public__sum-row vie-public__sum-row--discount"><span>Giảm giá</span><span>−${Vie.format.vnd(quote.discount)}</span></div>`);
      }
      summaryLines.innerHTML = lines.join('') || '<p class="vie-public__muted">—</p>';
      const displayTotal = quote.requires_quote ? 'Liên hệ' : Vie.format.vnd(quote.total || 0);
      if (summaryTotal) summaryTotal.textContent = displayTotal;
      if (summaryMessages) {
        const msgs = quote.messages || [];
        if (quote.unavailable_date) msgs.push('Hết phòng đêm ' + quote.unavailable_date);
        summaryMessages.innerHTML = msgs.length
          ? '<ul class="vie-public__notes">' + msgs.map((m) => `<li>${escapeHtml(m)}</li>`).join('') + '</ul>'
          : '';
      }
    }

    async function applyCoupon() {
      const code = (couponInput?.value || '').trim();
      if (!code) {
        appliedCoupon = null;
        if (couponStatus) couponStatus.textContent = '';
        return refreshQuote();
      }
      const item = buildItem();
      if (!item) {
        if (couponStatus) { couponStatus.textContent = 'Chọn phòng + ngày trước khi áp mã.'; couponStatus.className = 'vie-public__coupon-status vie-public__coupon-status--err'; }
        return;
      }
      if (!currentQuote || !currentQuote.subtotal) {
        if (couponStatus) { couponStatus.textContent = 'Đợi tính giá xong rồi mới áp mã.'; couponStatus.className = 'vie-public__coupon-status vie-public__coupon-status--err'; }
        return;
      }
      try {
        const data = await Vie.api.post('coupons/validate', {
          code,
          order_subtotal: currentQuote.subtotal,
          room_id: item.room_id,
          booking_type: item.booking_type,
        });
        appliedCoupon = code;
        if (couponStatus) {
          couponStatus.textContent = 'Đã áp dụng mã: −' + Vie.format.vnd(data.discount || 0);
          couponStatus.className = 'vie-public__coupon-status vie-public__coupon-status--ok';
        }
      } catch (e) {
        appliedCoupon = null;
        if (couponStatus) {
          couponStatus.textContent = e.errors?.[0]?.message || 'Mã không hợp lệ';
          couponStatus.className = 'vie-public__coupon-status vie-public__coupon-status--err';
        }
      }
    }

    async function submit(ev) {
      ev.preventDefault();
      showError(root, '');
      const item = buildItem();
      if (!item) { showError(root, 'Vui lòng chọn phòng và ngày.'); return; }
      const fd = new FormData(form);
      const body = {
        customer: {
          phone: String(fd.get('phone') || '').trim(),
          name: String(fd.get('name') || '').trim(),
          email: String(fd.get('email') || '').trim() || null,
        },
        items: [item],
        customer_note: String(fd.get('customer_note') || '').trim() || null,
      };
      if (appliedCoupon) body.coupon_code = appliedCoupon;

      if (!body.customer.phone || !body.customer.name) {
        showError(root, 'Vui lòng nhập họ tên và số điện thoại.');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Đang xử lý...';
      try {
        const data = await Vie.api.post('public/orders', body, { idempotencyKey: idemKey });
        if (data.redirect_url) {
          window.location.href = data.redirect_url;
        } else {
          const successUrl = (window.VieRest && window.VieRest.successUrl) || '/dat-phong-thanh-cong/';
          window.location.href = successUrl + '?' + new URLSearchParams({
            code: data.code,
            phone: body.customer.phone,
          }).toString();
        }
      } catch (e) {
        const msg = (e.errors || []).map((er) => er.message).join('. ') || 'Đặt phòng thất bại';
        showError(root, msg);
        submitBtn.disabled = false;
        submitBtn.textContent = 'Đặt phòng';
      }
    }

    const onChange = debounce(refreshQuote, 350);
    form.addEventListener('input', (e) => {
      const target = e.target;
      if (!target.name) return;
      if (['room_id','checkin','checkout','adults','child_ages','booking_type'].indexOf(target.name) >= 0) {
        onChange();
      }
    });
    form.addEventListener('change', (e) => {
      if (e.target.name === 'booking_type' || e.target.name === 'room_id') onChange();
    });
    form.addEventListener('submit', submit);
    if (couponApplyBtn) couponApplyBtn.addEventListener('click', (e) => { e.preventDefault(); applyCoupon(); });

    refreshQuote();
  }

  // ========== Success ==========
  async function initSuccess(root) {
    const q = readQuery();
    const code = q.code || '';
    const phone = q.phone || '';
    const wrap = root.querySelector('[data-vie-success-body]');
    if (!wrap) return;

    if (!code || !phone) {
      wrap.innerHTML = '<p class="vie-public__error-inline">Thiếu mã đơn hoặc số điện thoại. Vui lòng kiểm tra email.</p>';
      return;
    }

    try {
      const data = await Vie.api.get('orders/lookup', { code, phone });
      wrap.innerHTML = renderSuccessSummary(data);
    } catch (e) {
      wrap.innerHTML = '<p class="vie-public__error-inline">' + (e.errors?.[0]?.message || 'Không tìm thấy đơn') + '</p>';
    }
  }

  function renderSuccessSummary(order) {
    const status = String(order.status || '');
    const statusLabel = {
      pending: 'Chờ thanh toán',
      confirmed: 'Đã xác nhận',
      paid: 'Đã thanh toán',
      cancelled: 'Đã hủy',
      completed: 'Hoàn thành',
    }[status] || status;
    const items = (order.items || []).map((it) => `
      <li>
        <strong>Phòng:</strong> ${escapeHtml(it.room_name || '')}${it.hotel_name ? ' — ' + escapeHtml(it.hotel_name) : ''}<br>
        <strong>Nhận phòng:</strong> ${Vie.format.date(it.checkin)} —
        <strong>Trả phòng:</strong> ${Vie.format.date(it.checkout)}<br>
        <strong>Khách:</strong> ${it.adults || 0} người lớn${(it.children || 0) > 0 ? ', ' + it.children + ' trẻ em' : ''}
      </li>`).join('');
    return `
      <div class="vie-public__success-card">
        <h2>Cảm ơn ${escapeHtml(order.customer_name || 'Quý khách')}!</h2>
        <p class="vie-public__muted">Mã đơn của bạn: <strong>${escapeHtml(order.code || '')}</strong></p>
        <p>Trạng thái: <strong>${escapeHtml(statusLabel)}</strong></p>
        <ul class="vie-public__order-items">${items}</ul>
        <p class="vie-public__total-line">Tổng cộng: <strong>${Vie.format.vnd(order.total || 0)}</strong></p>
        <p class="vie-public__muted">Bạn sẽ nhận email xác nhận trong giây lát. Vui lòng kiểm tra hộp thư (cả Spam).</p>
      </div>`;
  }

  // ========== Search form ==========
  function initSearch(root) {
    const form = root.querySelector('form');
    if (!form) return;
    form.addEventListener('submit', (ev) => {
      ev.preventDefault();
      const fd = new FormData(form);
      const detail = {
        hotel_id: parseInt(root.getAttribute('data-hotel-id') || '0', 10),
        checkin: fd.get('checkin') || '',
        checkout: fd.get('checkout') || '',
        adults: parseInt(fd.get('adults') || '2', 10),
        children: parseInt(fd.get('children') || '0', 10),
      };
      document.dispatchEvent(new CustomEvent('vie:search:apply', { detail }));
    });
  }

  // ========== Room cards ==========
  // Rooms are server-rendered by [vie_hotel_rooms]; JS only enriches book links
  // with selected checkin/checkout when a search form is submitted.
  function initRooms(root) {
    const checkoutUrl = (window.VieRest && window.VieRest.checkoutUrl) || '/dat-phong/';
    document.addEventListener('vie:search:apply', (ev) => {
      const detail = ev.detail || {};
      root.querySelectorAll('.vie-public__room-card a.vie-public__btn').forEach((a) => {
        const url = new URL(a.getAttribute('href'), window.location.origin);
        const params = new URLSearchParams(url.search);
        if (detail.checkin) params.set('checkin', detail.checkin);
        if (detail.checkout) params.set('checkout', detail.checkout);
        url.search = params.toString();
        a.setAttribute('href', url.pathname + (url.search ? url.search : ''));
      });
    });
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-vie-checkout]').forEach(initCheckout);
    document.querySelectorAll('[data-vie-success]').forEach(initSuccess);
    document.querySelectorAll('[data-vie-search]').forEach(initSearch);
    document.querySelectorAll('[data-vie-rooms]').forEach(initRooms);
  });
})();
