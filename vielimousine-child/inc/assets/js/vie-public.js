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

    async function fetchOnce() {
      try {
        const data = await Vie.api.get('orders/lookup', { code, phone });
        wrap.innerHTML = renderSuccessSummary(data, code, phone);
        return data;
      } catch (e) {
        wrap.innerHTML = '<p class="vie-public__error-inline">' + (e.errors?.[0]?.message || 'Không tìm thấy đơn') + '</p>';
        return null;
      }
    }

    let order = await fetchOnce();
    // Poll while pending (max 2 minutes, every 8s) so SePay webhook updates appear quickly.
    if (order && (order.payment_status === 'pending' || order.status === 'pending')) {
      let pollCount = 0;
      const maxPolls = 15;
      const timer = setInterval(async () => {
        pollCount++;
        const next = await fetchOnce();
        if (!next || pollCount >= maxPolls) { clearInterval(timer); return; }
        if (next.payment_status !== 'pending' && next.status !== 'pending') {
          clearInterval(timer);
        }
      }, 8000);
    }

    // Manual retry button (delegate)
    wrap.addEventListener('click', async (e) => {
      const t = e.target;
      if (!(t instanceof HTMLElement)) return;
      if (t.matches('[data-vie-refresh]')) {
        t.disabled = true; t.textContent = 'Đang tải…';
        await fetchOnce();
      }
    });
  }

  function renderSuccessSummary(order, code, phone) {
    const status = String(order.status || '');
    const paymentStatus = String(order.payment_status || '');
    const statusLabel = {
      pending: 'Chờ xác nhận',
      confirmed: 'Đã xác nhận',
      paid: 'Đã thanh toán',
      cancelled: 'Đã hủy',
      completed: 'Hoàn thành',
      no_show: 'Không đến',
    }[status] || status;
    const paymentLabel = {
      pending: 'Chờ thanh toán',
      partial: 'Thanh toán 1 phần',
      paid: 'Đã thanh toán',
      refunded: 'Đã hoàn tiền',
    }[paymentStatus] || paymentStatus;

    const isPaid = paymentStatus === 'paid';
    const isPending = paymentStatus === 'pending';
    const isCancelled = status === 'cancelled';

    const items = (order.items || []).map((it) => `
      <li class="vie-public__order-item">
        <div><strong>${escapeHtml(it.room_name || it.name || '')}</strong>${it.hotel_name ? ' — ' + escapeHtml(it.hotel_name) : ''}</div>
        <div class="vie-public__muted">
          ${Vie.format.date(it.checkin)} → ${Vie.format.date(it.checkout)}
          · ${it.adults || 0} người lớn${(it.children || 0) > 0 ? ', ' + it.children + ' trẻ em' : ''}
        </div>
        <div class="vie-public__muted">${Vie.format.vnd(it.line_total || 0)}</div>
      </li>`).join('');

    const banner = isPaid
      ? '<div class="vh-success-banner vh-success-banner-ok">✓ Thanh toán thành công. Cảm ơn ' + escapeHtml(order.customer_name || 'Quý khách') + '!</div>'
      : isCancelled
      ? '<div class="vh-success-banner vh-success-banner-err">Đơn đã bị hủy.</div>'
      : isPending
      ? '<div class="vh-success-banner vh-success-banner-warn">⏳ Đang chờ thanh toán. Trang này sẽ tự cập nhật.</div>'
      : '<div class="vh-success-banner vh-success-banner-ok">✓ Đặt phòng thành công!</div>';

    const refreshLink = isPending
      ? `<button type="button" class="vh-btn" data-vie-refresh>Kiểm tra trạng thái</button>`
      : '';

    const paid = order.paid_amount || 0;
    const total = order.total || 0;
    const remaining = Math.max(0, total - paid);

    return `
      ${banner}
      <div class="vh-success-card">
        <div class="vh-success-head">
          <div>
            <div class="vie-public__muted">Mã đơn</div>
            <h2 class="vh-success-code">${escapeHtml(order.code || code)}</h2>
          </div>
          <div class="vh-success-status">
            <span class="vh-tag">${escapeHtml(statusLabel)}</span>
            <span class="vh-tag ${isPaid ? 'vh-tag-ok' : isPending ? 'vh-tag-warn' : ''}">${escapeHtml(paymentLabel)}</span>
          </div>
        </div>

        <ul class="vie-public__order-items">${items}</ul>

        <div class="vh-success-totals">
          <div><span>Tổng cộng</span><strong>${Vie.format.vnd(total)}</strong></div>
          <div><span>Đã thanh toán</span><strong>${Vie.format.vnd(paid)}</strong></div>
          ${remaining > 0 ? `<div class="vh-line-warn"><span>Còn lại</span><strong>${Vie.format.vnd(remaining)}</strong></div>` : ''}
        </div>

        <p class="vie-public__muted">Email xác nhận đã được gửi đến ${order.customer_email ? '<strong>' + escapeHtml(order.customer_email) + '</strong>' : 'số điện thoại của bạn'}. Vui lòng kiểm tra hộp thư (cả Spam).</p>

        ${refreshLink}
      </div>
    `;
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

  // ========== Hotel Detail (single-hotel.php — inline booking flow) ==========
  function initHotelDetail(root) {
    const hotelId = parseInt(root.getAttribute('data-hotel-id') || '0', 10);
    const hotelName = root.getAttribute('data-hotel-name') || '';

    const searchForm = root.querySelector('[data-vie-search] form');
    const roomsWrap = root.querySelector('[data-vie-rooms]');
    const roomEls = Array.from(roomsWrap?.querySelectorAll('.vh-room[data-room-id]') || []);
    const checkoutSection = root.querySelector('[data-vie-inline-checkout]');
    const checkoutForm = checkoutSection?.querySelector('.vh-checkout-form');
    const pickedRoomEl = checkoutSection?.querySelector('[data-picked-room]');
    const pickedDatesEl = checkoutSection?.querySelector('[data-picked-dates]');
    const changeRoomBtn = checkoutSection?.querySelector('[data-change-room]');

    const widget = root.querySelector('[data-vie-widget]');
    const widgetEmpty = widget?.querySelector('[data-widget-empty]');
    const widgetBody = widget?.querySelector('[data-widget-body]');
    const widgetRoom = widget?.querySelector('[data-widget-room]');
    const widgetMeta = widget?.querySelector('[data-widget-meta]');
    const widgetLines = widget?.querySelector('[data-widget-lines]');
    const widgetTotal = widget?.querySelector('[data-widget-total]');
    const widgetMessages = widget?.querySelector('[data-widget-messages]');

    const mobileCta = root.querySelector('[data-mobile-cta]');
    const mobileRoom = mobileCta?.querySelector('[data-mobile-room]');
    const mobileTotal = mobileCta?.querySelector('[data-mobile-total]');

    const couponInput = checkoutForm?.querySelector('[name=coupon_code]');
    const couponApplyBtn = checkoutSection?.querySelector('[data-coupon-apply]');
    const couponStatus = checkoutSection?.querySelector('[data-coupon-status]');
    const errEl = checkoutSection?.querySelector('[data-error]');
    const submitBtn = checkoutForm?.querySelector('[type=submit]');

    let selectedRoomId = null;       // currently picked room
    const quotes = new Map();        // room_id → latest quote object (or {error})
    const inflight = new Map();      // room_id → AbortController
    let appliedCoupon = null;
    const idemKey = Vie.format.uuid();

    function readSearch() {
      if (!searchForm) return null;
      const fd = new FormData(searchForm);
      const checkin = String(fd.get('checkin') || '');
      const checkout = String(fd.get('checkout') || '');
      const adults = parseInt(fd.get('adults') || '1', 10);
      const bookingType = String(fd.get('booking_type') || 'room');
      const childAgesRaw = String(fd.get('child_ages') || '').trim();
      const childAges = childAgesRaw
        ? childAgesRaw.split(',').map((s) => parseInt(s.trim(), 10)).filter((n) => !isNaN(n))
        : [];
      if (!checkin || !checkout) return null;
      return { checkin, checkout, adults, booking_type: bookingType, child_ages: childAges };
    }

    function setRoomCardState(roomEl, state, payload) {
      const priceEl = roomEl.querySelector('[data-price]');
      const msgEl = roomEl.querySelector('[data-messages]');
      const btn = roomEl.querySelector('[data-pick-room]');
      const basePrice = parseInt(roomEl.getAttribute('data-base-price') || '0', 10);

      roomEl.dataset.state = state;
      if (msgEl) msgEl.innerHTML = '';

      if (state === 'loading') {
        if (priceEl) priceEl.innerHTML = '<span class="vh-price-amount">Đang tính…</span>';
        if (btn) { btn.disabled = true; btn.textContent = 'Đang tính giá…'; }
      } else if (state === 'quote' && payload) {
        const quote = payload;
        const total = quote.total || 0;
        const nights = quote.nights || 1;
        const perNight = nights > 0 ? Math.round(total / nights) : total;
        if (priceEl) {
          priceEl.innerHTML = '<span class="vh-price-from">' + (quote.num_rooms > 1 ? quote.num_rooms + ' phòng × ' : '') +
            '</span><span class="vh-price-amount">' + Vie.format.vnd(total) + '</span>' +
            '<span class="vh-price-suffix">' + nights + ' đêm</span>' +
            '<span class="vh-price-per">' + Vie.format.vnd(perNight) + '/đêm</span>';
        }
        if (msgEl && Array.isArray(quote.messages) && quote.messages.length) {
          msgEl.innerHTML = '<ul class="vh-msg-list">' +
            quote.messages.map((m) => '<li>' + escapeHtml(m) + '</li>').join('') + '</ul>';
        }
        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Chọn phòng này';
        }
      } else if (state === 'unavailable' && payload) {
        if (priceEl) priceEl.innerHTML = '<span class="vh-price-amount vh-price-na">Không khả dụng</span>';
        if (msgEl && payload.message) {
          msgEl.innerHTML = '<p class="vh-msg-err">' + escapeHtml(payload.message) + '</p>';
        }
        if (btn) { btn.disabled = true; btn.textContent = 'Hết phòng'; }
      } else if (state === 'requires-quote' && payload) {
        if (priceEl) priceEl.innerHTML = '<span class="vh-price-amount vh-price-contact">Liên hệ báo giá</span>';
        if (msgEl && Array.isArray(payload.messages)) {
          msgEl.innerHTML = '<ul class="vh-msg-list">' + payload.messages.map((m) => '<li>' + escapeHtml(m) + '</li>').join('') + '</ul>';
        }
        if (btn) { btn.disabled = false; btn.textContent = 'Yêu cầu báo giá'; }
      } else if (state === 'error' && payload) {
        if (priceEl) priceEl.innerHTML = '<span class="vh-price-amount">' + Vie.format.vnd(basePrice) + '</span><span class="vh-price-suffix">/đêm</span>';
        if (msgEl) msgEl.innerHTML = '<p class="vh-msg-err">' + escapeHtml(payload.message || 'Không tính được giá') + '</p>';
        if (btn) { btn.disabled = false; btn.textContent = 'Chọn phòng này'; }
      }
    }

    async function fetchQuoteForRoom(roomEl, common) {
      const roomId = parseInt(roomEl.getAttribute('data-room-id') || '0', 10);
      if (!roomId) return;

      // Cancel previous inflight for this room
      const prev = inflight.get(roomId);
      if (prev) prev.abort();
      const ac = new AbortController();
      inflight.set(roomId, ac);

      setRoomCardState(roomEl, 'loading');

      const body = Object.assign({ room_id: roomId }, common);
      try {
        const url = ((window.VieRest && window.VieRest.root) || '/wp-json/vie/v1/') + 'quote';
        const resp = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': (window.VieRest && window.VieRest.nonce) || '',
          },
          body: JSON.stringify(body),
          signal: ac.signal,
        });
        const json = await resp.json();
        if (!json.success) {
          const msg = json.errors?.[0]?.message || 'Không tính được giá';
          quotes.set(roomId, { error: msg });
          setRoomCardState(roomEl, 'error', { message: msg });
          return;
        }
        const quote = json.data;
        quotes.set(roomId, quote);
        if (quote.unavailable_date) {
          setRoomCardState(roomEl, 'unavailable', { message: 'Hết phòng đêm ' + quote.unavailable_date });
        } else if (quote.requires_quote) {
          setRoomCardState(roomEl, 'requires-quote', { messages: quote.messages || [] });
        } else {
          setRoomCardState(roomEl, 'quote', quote);
        }

        // If this is the currently picked room, refresh widget
        if (selectedRoomId === roomId) renderWidget(quote, roomEl);
      } catch (e) {
        if (e.name === 'AbortError') return;
        quotes.set(roomId, { error: e.message });
        setRoomCardState(roomEl, 'error', { message: 'Lỗi kết nối' });
      }
    }

    function refreshAllQuotes() {
      const common = readSearch();
      if (!common) return;
      roomEls.forEach((el) => fetchQuoteForRoom(el, common));
    }

    function findRoomElById(roomId) {
      return roomEls.find((el) => parseInt(el.getAttribute('data-room-id') || '0', 10) === roomId) || null;
    }

    function renderWidget(quote, roomEl) {
      if (!widget) return;
      if (widgetEmpty) widgetEmpty.hidden = true;
      if (widgetBody) widgetBody.hidden = false;

      const roomName = roomEl?.getAttribute('data-room-name') || '';
      const common = readSearch() || {};
      if (widgetRoom) widgetRoom.textContent = roomName;
      if (widgetMeta) {
        const childCount = (common.child_ages || []).length;
        widgetMeta.innerHTML =
          '<span>' + Vie.format.date(common.checkin) + ' → ' + Vie.format.date(common.checkout) + '</span>' +
          ' · <span>' + (quote.nights || 0) + ' đêm · ' + (quote.num_rooms || 1) + ' phòng</span>' +
          ' · <span>' + (common.adults || 0) + ' NL' + (childCount > 0 ? ', ' + childCount + ' trẻ' : '') + '</span>';
      }

      if (widgetLines) {
        const lines = [];
        if (quote.requires_quote) {
          lines.push('<p class="vh-muted">Vượt sức chứa thông thường — gửi yêu cầu để được báo giá.</p>');
        } else {
          lines.push(line(quote.num_rooms + ' phòng × ' + quote.nights + ' đêm', Vie.format.vnd(quote.room_subtotal || 0)));
          if (quote.extra_adult_subtotal) lines.push(line('Phụ thu người lớn', Vie.format.vnd(quote.extra_adult_subtotal)));
          if (quote.child_surcharge_total) lines.push(line('Phụ thu trẻ em', Vie.format.vnd(quote.child_surcharge_total)));
          if (quote.ticket_subtotal) lines.push(line('Vé xe', Vie.format.vnd(quote.ticket_subtotal)));
          if (quote.discount) lines.push('<div class="vh-line vh-line-discount"><span>Giảm giá</span><span>−' + Vie.format.vnd(quote.discount) + '</span></div>');
        }
        widgetLines.innerHTML = lines.join('');
      }

      const totalText = quote.requires_quote ? 'Liên hệ' : Vie.format.vnd(quote.total || 0);
      if (widgetTotal) widgetTotal.textContent = totalText;

      if (widgetMessages) {
        const msgs = Array.isArray(quote.messages) ? quote.messages.slice() : [];
        if (quote.unavailable_date) msgs.push('Hết phòng đêm ' + quote.unavailable_date);
        widgetMessages.innerHTML = msgs.length
          ? '<ul class="vh-msg-list">' + msgs.map((m) => '<li>' + escapeHtml(m) + '</li>').join('') + '</ul>'
          : '';
      }

      // Mobile sticky CTA
      if (mobileCta) {
        mobileCta.hidden = false;
        if (mobileRoom) mobileRoom.textContent = roomName;
        if (mobileTotal) mobileTotal.textContent = totalText;
      }
    }

    function line(label, value) {
      return '<div class="vh-line"><span>' + escapeHtml(label) + '</span><span>' + escapeHtml(value) + '</span></div>';
    }

    function pickRoom(roomEl) {
      const roomId = parseInt(roomEl.getAttribute('data-room-id') || '0', 10);
      const roomName = roomEl.getAttribute('data-room-name') || '';
      selectedRoomId = roomId;

      // Highlight selected card
      roomEls.forEach((el) => el.classList.toggle('vh-room-picked', el === roomEl));

      // Sync widget + checkout section
      const quote = quotes.get(roomId);
      if (quote && !quote.error) renderWidget(quote, roomEl);

      if (checkoutSection) {
        checkoutSection.hidden = false;
        if (pickedRoomEl) pickedRoomEl.textContent = roomName;
        if (pickedDatesEl) {
          const common = readSearch() || {};
          pickedDatesEl.textContent = Vie.format.date(common.checkin) + ' → ' + Vie.format.date(common.checkout) +
            ' · ' + (common.adults || 0) + ' NL' +
            (common.child_ages?.length ? ', ' + common.child_ages.length + ' trẻ' : '');
        }
        checkoutSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    function unpickRoom() {
      selectedRoomId = null;
      roomEls.forEach((el) => el.classList.remove('vh-room-picked'));
      if (checkoutSection) checkoutSection.hidden = true;
      if (widget) {
        if (widgetEmpty) widgetEmpty.hidden = false;
        if (widgetBody) widgetBody.hidden = true;
      }
      if (mobileCta) mobileCta.hidden = true;
      roomsWrap?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function applyCoupon() {
      if (!couponStatus) return;
      const code = (couponInput?.value || '').trim();
      if (!code) {
        appliedCoupon = null;
        couponStatus.textContent = '';
        couponStatus.className = 'vh-coupon-status';
        return;
      }
      if (!selectedRoomId) {
        couponStatus.textContent = 'Chọn phòng trước khi áp mã.';
        couponStatus.className = 'vh-coupon-status vh-coupon-err';
        return;
      }
      const quote = quotes.get(selectedRoomId);
      if (!quote || quote.error) {
        couponStatus.textContent = 'Chờ tính giá xong rồi áp mã.';
        couponStatus.className = 'vh-coupon-status vh-coupon-err';
        return;
      }
      try {
        const data = await Vie.api.post('coupons/validate', {
          code,
          order_subtotal: quote.subtotal,
          room_id: selectedRoomId,
          booking_type: (readSearch() || {}).booking_type || 'room',
        });
        appliedCoupon = code;
        couponStatus.textContent = 'Áp dụng thành công: −' + Vie.format.vnd(data.discount || 0);
        couponStatus.className = 'vh-coupon-status vh-coupon-ok';
      } catch (e) {
        appliedCoupon = null;
        couponStatus.textContent = e.errors?.[0]?.message || 'Mã không hợp lệ';
        couponStatus.className = 'vh-coupon-status vh-coupon-err';
      }
    }

    function showErr(msg) {
      if (!errEl) return;
      errEl.textContent = msg || '';
      errEl.hidden = !msg;
    }

    async function submitOrder(ev) {
      ev.preventDefault();
      showErr('');
      if (!selectedRoomId) { showErr('Vui lòng chọn phòng.'); return; }

      const common = readSearch();
      if (!common) { showErr('Vui lòng nhập ngày nhận / trả phòng.'); return; }

      const fd = new FormData(checkoutForm);
      const phone = String(fd.get('phone') || '').trim();
      const name = String(fd.get('name') || '').trim();
      if (!name || !phone) { showErr('Vui lòng nhập họ tên và số điện thoại.'); return; }

      const body = {
        customer: {
          phone,
          name,
          email: String(fd.get('email') || '').trim() || null,
        },
        items: [Object.assign({ room_id: selectedRoomId }, common)],
        customer_note: String(fd.get('customer_note') || '').trim() || null,
        payment_method: String(fd.get('payment_method') || 'sepay'),
      };
      if (appliedCoupon) body.coupon_code = appliedCoupon;

      submitBtn.disabled = true;
      const oldLabel = submitBtn.textContent;
      submitBtn.textContent = 'Đang xử lý…';
      try {
        const data = await Vie.api.post('public/orders', body, { idempotencyKey: idemKey });
        if (data.redirect_url) {
          window.location.href = data.redirect_url;
        } else {
          const successUrl = (window.VieRest && window.VieRest.successUrl) || '/dat-phong-thanh-cong/';
          window.location.href = successUrl + '?' + new URLSearchParams({ code: data.code, phone }).toString();
        }
      } catch (e) {
        const msg = (e.errors || []).map((er) => er.message).join('. ') || 'Đặt phòng thất bại';
        showErr(msg);
        submitBtn.disabled = false;
        submitBtn.textContent = oldLabel;
      }
    }

    // --- Wire events ---
    const onSearchChange = debounce(refreshAllQuotes, 500);
    if (searchForm) {
      searchForm.addEventListener('input', onSearchChange);
      searchForm.addEventListener('change', onSearchChange);
      searchForm.addEventListener('submit', (e) => { e.preventDefault(); refreshAllQuotes(); });
    }

    roomEls.forEach((el) => {
      const btn = el.querySelector('[data-pick-room]');
      if (btn) btn.addEventListener('click', () => pickRoom(el));
    });

    if (changeRoomBtn) changeRoomBtn.addEventListener('click', unpickRoom);

    if (couponApplyBtn) couponApplyBtn.addEventListener('click', (e) => { e.preventDefault(); applyCoupon(); });
    if (checkoutForm) checkoutForm.addEventListener('submit', submitOrder);

    // Jump to checkout buttons (sidebar + mobile)
    root.querySelectorAll('[data-jump-checkout]').forEach((btn) => {
      btn.addEventListener('click', () => {
        if (!selectedRoomId) {
          roomsWrap?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
          checkoutSection?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });

    // Initial fetch
    refreshAllQuotes();

    // Prefill from query params (room_id, checkin, checkout)
    const q = readQuery();
    if (q.checkin && searchForm) searchForm.elements['checkin'] && (searchForm.elements['checkin'].value = q.checkin);
    if (q.checkout && searchForm) searchForm.elements['checkout'] && (searchForm.elements['checkout'].value = q.checkout);
    if (q.room_id) {
      // Auto-pick after first quote settle
      setTimeout(() => {
        const target = findRoomElById(parseInt(q.room_id, 10));
        if (target) pickRoom(target);
      }, 700);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-vie-hotel]').forEach(initHotelDetail);
    document.querySelectorAll('[data-vie-checkout]').forEach(initCheckout);
    document.querySelectorAll('[data-vie-success]').forEach(initSuccess);
    document.querySelectorAll('[data-vie-search]:not([data-vie-hotel] *)').forEach(initSearch);
    document.querySelectorAll('[data-vie-rooms]:not([data-vie-hotel] *)').forEach(initRooms);
  });
})();
