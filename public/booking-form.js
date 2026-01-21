(function(){
  let promo = { code: '', discount: 0, total: null, valid: false };
  function money(n){ return (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2); }

  async function api(url, opts){
    const r = await fetch(url, opts || {});
    return await r.json();
  }

  function getBase(){
    return window.location.origin;
  }

  async function loadCategories($wrap){
    const res = await api(getBase() + '/wp-json/bp/v1/categories');
    const list = res?.data || [];
    const $sel = $wrap.querySelector('.bp-category');
    $sel.innerHTML = `<option value="0">Select category</option>`;
    list.forEach(c => {
      $sel.insertAdjacentHTML('beforeend', `<option value="${c.id}">${c.name}</option>`);
    });
  }

  function renderField(field){
    const req = field.required ? 'required' : '';
    const name = `bp_field_${field.key}`;

    if (field.type === 'textarea') {
      return `<div class="bp-row">
        <label>${field.label}${field.required ? ' *' : ''}</label>
        <textarea class="bp-dyn" data-key="${field.key}" ${req} rows="3"></textarea>
      </div>`;
    }

    if (field.type === 'select' || field.type === 'radio') {
      const opts = (field.options || []).map(o => String(o));
      if (field.type === 'select') {
        return `<div class="bp-row">
          <label>${field.label}${field.required ? ' *' : ''}</label>
          <select class="bp-dyn" data-key="${field.key}" ${req}>
            <option value="">Select...</option>
            ${opts.map(o => `<option value="${o.replace(/"/g,'&quot;')}">${o}</option>`).join('')}
          </select>
        </div>`;
      }

      return `<div class="bp-row">
        <label>${field.label}${field.required ? ' *' : ''}</label>
        <div style="display:grid;gap:8px;">
          ${opts.map(o => `<label style="display:flex;gap:8px;align-items:center;">
            <input type="radio" name="${name}" class="bp-dyn-radio" data-key="${field.key}" value="${o.replace(/"/g,'&quot;')}" ${req}>
            <span>${o}</span>
          </label>`).join('')}
        </div>
      </div>`;
    }

    if (field.type === 'checkbox') {
      return `<div class="bp-row">
        <label style="display:flex;gap:10px;align-items:center;">
          <input type="checkbox" class="bp-dyn-checkbox" data-key="${field.key}">
          <span>${field.label}${field.required ? ' *' : ''}</span>
        </label>
      </div>`;
    }

    const inputType = (['text','email','tel','date'].includes(field.type)) ? field.type : 'text';
    return `<div class="bp-row">
      <label>${field.label}${field.required ? ' *' : ''}</label>
      <input type="${inputType}" class="bp-dyn" data-key="${field.key}" ${req}>
    </div>`;
  }

  async function loadDynamicFields($wrap, scope){
    const target = scope === 'customer'
      ? $wrap.querySelector('.bp-dynamic-customer')
      : $wrap.querySelector('.bp-dynamic-booking');

    if (!target) return [];
    target.innerHTML = 'Loading...';

    const res = await api(getBase() + `/wp-json/bp/v1/form-fields?scope=${encodeURIComponent(scope)}`);
    const fields = res?.data || [];

    target.innerHTML = fields.map(renderField).join('');
    return fields;
  }

  function collectDynamicFields($wrap, scope, fields){
    const data = {};

    fields.forEach(f => {
      const key = f.key;

      if (f.type === 'checkbox') {
        const el = $wrap.querySelector(`.bp-dyn-checkbox[data-key="${key}"]`);
        data[key] = el ? !!el.checked : false;
        return;
      }

      if (f.type === 'radio') {
        const checked = $wrap.querySelector(`input.bp-dyn-radio[data-key="${key}"]:checked`);
        data[key] = checked ? checked.value : '';
        return;
      }

      const el = $wrap.querySelector(`.bp-dyn[data-key="${key}"]`);
      data[key] = el ? (el.value ?? '') : '';
    });

    return data;
  }

  async function loadServices($wrap, categoryId){
    const $svc = $wrap.querySelector('.bp-service');
    $svc.disabled = true;
    $svc.innerHTML = `<option value="0">Select service</option>`;

    const url = getBase() + '/wp-json/bp/v1/services' + (categoryId > 0 ? `?category_id=${categoryId}` : '');
    const res = await api(url);
    const list = res?.data || [];

    list.forEach(s => {
      $svc.insertAdjacentHTML('beforeend', `<option value="${s.id}" data-price="${s.price}" data-img="${s.image||''}">${s.name}</option>`);
    });

    $svc.disabled = false;
  }

  async function loadExtras($wrap, serviceId){
    const $box = $wrap.querySelector('.bp-extras');
    $box.innerHTML = '';

    if (!serviceId) return;

    const res = await api(getBase() + `/wp-json/bp/v1/extras?service_id=${serviceId}`);
    const list = res?.data || [];

    if (!list.length) {
      $box.innerHTML = `<div style="color:#666;">No extras for this service.</div>`;
      return;
    }

    list.forEach(e => {
      $box.insertAdjacentHTML('beforeend', `
        <label style="border:1px solid #e5e5e5;border-radius:12px;padding:10px;display:flex;gap:10px;align-items:center;">
          <input type="checkbox" class="bp-extra" value="${e.id}" data-price="${e.price}">
          <span style="flex:1;">
            <div style="font-weight:600;">${e.name}</div>
            <div style="font-size:12px;color:#666;">+ ${money(e.price)}</div>
          </span>
        </label>
      `);
    });
  }

  async function loadAgents($wrap, serviceId){
    const $sel = $wrap.querySelector('.bp-agent');
    $sel.disabled = true;
    $sel.innerHTML = `<option value="0">Select agent</option>`;

    if (!serviceId) return;

    const res = await api(getBase() + `/wp-json/bp/v1/agents?service_id=${serviceId}`);
    const list = res?.data || [];
    list.forEach(a => {
      $sel.insertAdjacentHTML('beforeend', `<option value="${a.id}">${a.name}</option>`);
    });
    $sel.disabled = false;
  }

  function calcTotal($wrap){
    const svcOpt = $wrap.querySelector('.bp-service').selectedOptions[0];
    const servicePrice = svcOpt ? parseFloat(svcOpt.getAttribute('data-price') || '0') : 0;

    let extrasTotal = 0;
    $wrap.querySelectorAll('.bp-extra:checked').forEach(ch => {
      extrasTotal += parseFloat(ch.getAttribute('data-price') || '0');
    });

    const subtotal = servicePrice + extrasTotal;
    let discount = promo.valid ? (promo.discount || 0) : 0;
    if (discount > subtotal) discount = subtotal;

    const total = subtotal - discount;
    $wrap.querySelector('.bp-total').textContent = `Total: ${money(total)} (Discount: ${money(discount)})`;
    return { servicePrice, extrasTotal, subtotal, discount, total };
  }

  async function createBooking($wrap){
    const nonce = $wrap.getAttribute('data-nonce') || '';

    const category_id = parseInt($wrap.querySelector('.bp-category').value || '0', 10);
    const service_id  = parseInt($wrap.querySelector('.bp-service').value || '0', 10);
    const agent_id    = parseInt($wrap.querySelector('.bp-agent').value || '0', 10);
    const date        = $wrap.querySelector('.bp-date').value;
    const time        = $wrap.querySelector('.bp-time').value;
    const name        = $wrap.querySelector('.bp-customer-name').value.trim();
    const email       = $wrap.querySelector('.bp-customer-email').value.trim();

    const extras = [];
    $wrap.querySelectorAll('.bp-extra:checked').forEach(ch => {
      extras.push({
        id: parseInt(ch.value, 10),
        price: parseFloat(ch.getAttribute('data-price') || '0')
      });
    });

    const totals = calcTotal($wrap);

    const customer_fields = collectDynamicFields($wrap, 'customer', $wrap.__bp_customerFields || []);
    const booking_fields = collectDynamicFields($wrap, 'booking', $wrap.__bp_bookingFields || []);

    const payload = {
      category_id,
      service_id,
      agent_id,
      date,
      time,
      customer_name: name,
      customer_email: email,
      extras,
      total_price: totals.total,
      promo_code: promo.valid ? promo.code : '',
      customer_fields,
      booking_fields
    };

    const res = await api(getBase() + '/wp-json/bp/v1/booking/create', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce
      },
      body: JSON.stringify(payload)
    });

    return res;
  }

  document.addEventListener('DOMContentLoaded', async function(){
    document.querySelectorAll('.bp-booking').forEach(async function($wrap){

      await loadCategories($wrap);

      const customerFields = await loadDynamicFields($wrap, 'customer');
      const bookingFields = await loadDynamicFields($wrap, 'booking');
      $wrap.__bp_customerFields = customerFields;
      $wrap.__bp_bookingFields = bookingFields;

      $wrap.querySelector('.bp-category').addEventListener('change', async function(){
        const cid = parseInt(this.value || '0', 10);
        await loadServices($wrap, cid);
        $wrap.querySelector('.bp-extras').innerHTML = '';
        $wrap.querySelector('.bp-agent').innerHTML = `<option value="0">Select agent</option>`;
        calcTotal($wrap);
      });

      $wrap.querySelector('.bp-service').addEventListener('change', async function(){
        const sid = parseInt(this.value || '0', 10);

        const opt = this.selectedOptions[0];
        const img = opt ? (opt.getAttribute('data-img') || '') : '';
        $wrap.querySelector('.bp-service-preview').innerHTML = img ? `<img src="${img}" style="width:70px;height:70px;border-radius:12px;object-fit:cover;margin-top:10px;">` : '';

        await loadExtras($wrap, sid);
        await loadAgents($wrap, sid);

        calcTotal($wrap);

        $wrap.querySelectorAll('.bp-extra').forEach(x => x.addEventListener('change', () => calcTotal($wrap)));
      });

      const applyBtn = $wrap.querySelector('.bp-apply-promo');
      applyBtn && applyBtn.addEventListener('click', async function(){
        const code = ($wrap.querySelector('.bp-promo').value || '').trim().toUpperCase();
        const msg = $wrap.querySelector('.bp-promo-msg');
        promo = { code:'', discount:0, total:null, valid:false };

        if (!code) {
          msg.textContent = 'Enter a code.';
          calcTotal($wrap);
          return;
        }

        const base = (function(){
          const svcOpt = $wrap.querySelector('.bp-service').selectedOptions[0];
          const servicePrice = svcOpt ? parseFloat(svcOpt.getAttribute('data-price') || '0') : 0;
          let extrasTotal = 0;
          $wrap.querySelectorAll('.bp-extra:checked').forEach(ch => extrasTotal += parseFloat(ch.getAttribute('data-price') || '0'));
          return servicePrice + extrasTotal;
        })();

        msg.textContent = 'Checking...';

        try {
          const res = await api(getBase() + `/wp-json/bp/v1/promo/validate?code=${encodeURIComponent(code)}&subtotal=${encodeURIComponent(base)}`);
          if (res?.valid) {
            promo = { code, discount: parseFloat(res.discount||0), total: parseFloat(res.total||base), valid:true };
            msg.textContent = `✅ Applied: -${money(promo.discount)}`;
          } else {
            msg.textContent = `❌ ${res?.message || 'Invalid code'}`;
          }
          calcTotal($wrap);
        } catch(e) {
          msg.textContent = '❌ Could not validate code.';
          calcTotal($wrap);
        }
      });

      $wrap.querySelector('.bp-submit').addEventListener('click', async function(){
        const msg = $wrap.querySelector('.bp-msg');
        msg.textContent = 'Saving...';

        try {
          const res = await createBooking($wrap);
          if (res?.status === 'success') {
            msg.textContent = '✅ Booking created successfully.';
          } else {
            msg.textContent = '❌ ' + (res?.message || 'Could not create booking.');
          }
        } catch(e) {
          msg.textContent = '❌ Network error.';
        }
      });

    });
  });

})();
