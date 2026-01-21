(function(){
  const money = (n) => (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2);
  const base = () => window.location.origin;

  async function api(url, opts){
    const r = await fetch(url, opts || {});
    return await r.json();
  }

  function setMsg(wrap, text, show=true){
    const el = wrap.querySelector('.bp-msg');
    if (!el) return;
    el.textContent = text || '';
    el.style.display = show ? 'block' : 'none';
  }

  function state(wrap){
    if (!wrap.__bp) {
      wrap.__bp = {
        category: null,
        service: null,
        agent: null,
        extras: [],
        promo: { valid:false, code:'', discount:0 },
        lists: { categories:[], services:[], extras:[], agents:[] },
        customerFields: [],
        bookingFields: []
      };
    }
    return wrap.__bp;
  }

  function updateSummary(wrap){
    const st = state(wrap);
    const cat = st.category?.name || '—';
    const svc = st.service?.name || '—';
    const ag  = st.agent?.name || '—';

    const extrasNames = st.extras.map(x => x.name).slice(0,2).join(', ');
    const extrasLabel = st.extras.length ? (st.extras.length <= 2 ? extrasNames : `${extrasNames} +${st.extras.length-2}`) : '—';

    const servicePrice = st.service ? (parseFloat(st.service.price||0) || 0) : 0;
    const extrasTotal = st.extras.reduce((a,x)=>a+(parseFloat(x.price||0)||0), 0);
    const subtotal = servicePrice + extrasTotal;

    let discount = st.promo.valid ? (parseFloat(st.promo.discount||0)||0) : 0;
    if (discount > subtotal) discount = subtotal;
    const total = subtotal - discount;

    wrap.querySelector('.bp-sum-category').textContent = cat;
    wrap.querySelector('.bp-sum-service').textContent = svc;
    wrap.querySelector('.bp-sum-agent').textContent = ag;
    wrap.querySelector('.bp-sum-extras').textContent = extrasLabel;

    wrap.querySelector('.bp-sum-subtotal').textContent = money(subtotal);
    wrap.querySelector('.bp-sum-discount').textContent = money(discount);
    wrap.querySelector('.bp-sum-total').textContent = money(total);

    return { subtotal, discount, total };
  }

  function cardHTML(item, metaParts){
    const img = item.image ? `<img class="bp-thumb" src="${item.image}" alt="">` : `<div class="bp-thumb"></div>`;
    const meta = metaParts?.length ? `<div class="bp-meta">${metaParts.map(m=>`<span>${m}</span>`).join('')}</div>` : '';
    return `
      <div class="bp-card" data-id="${item.id}">
        ${img}
        <div style="min-width:0;">
          <h4 title="${item.name}">${item.name}</h4>
          ${meta}
        </div>
      </div>
    `;
  }

  function renderCards(container, items, selectedId, metaBuilder){
    container.innerHTML = items.map(it => {
      const meta = metaBuilder ? metaBuilder(it) : [];
      return cardHTML(it, meta);
    }).join('');

    container.querySelectorAll('.bp-card').forEach(card => {
      const id = parseInt(card.getAttribute('data-id') || '0', 10);
      if (selectedId && id === selectedId) card.classList.add('is-selected');
    });
  }

  async function loadCategories(wrap){
    const res = await api(base() + '/wp-json/bp/v1/categories');
    const list = res?.data || [];
    state(wrap).lists.categories = list;

    const box = wrap.querySelector('.bp-categories');
    renderCards(box, list, state(wrap).category?.id, () => []);
  }

  async function loadServices(wrap, categoryId){
    const res = await api(base() + `/wp-json/bp/v1/services?category_id=${encodeURIComponent(categoryId)}`);
    const list = res?.data || [];
    state(wrap).lists.services = list;

    const box = wrap.querySelector('.bp-services');
    renderCards(box, list, state(wrap).service?.id, (s) => {
      const meta = [];
      if (s.price != null) meta.push(`€ ${money(parseFloat(s.price||0))}`);
      if (s.duration) meta.push(`${parseInt(s.duration,10)} min`);
      return meta;
    });
  }

  async function loadExtras(wrap, serviceId){
    const res = await api(base() + `/wp-json/bp/v1/extras?service_id=${encodeURIComponent(serviceId)}`);
    const list = res?.data || [];
    state(wrap).lists.extras = list;

    const box = wrap.querySelector('.bp-extras');
    const selectedIds = new Set(state(wrap).extras.map(x=>x.id));
    box.innerHTML = list.map(e => cardHTML(e, [`+ € ${money(parseFloat(e.price||0))}`])).join('');
    box.querySelectorAll('.bp-card').forEach(card => {
      const id = parseInt(card.getAttribute('data-id') || '0', 10);
      if (selectedIds.has(id)) card.classList.add('is-selected');
    });
  }

  async function loadAgents(wrap, serviceId){
    const res = await api(base() + `/wp-json/bp/v1/agents?service_id=${encodeURIComponent(serviceId)}`);
    const list = res?.data || [];
    state(wrap).lists.agents = list;

    const box = wrap.querySelector('.bp-agents');
    renderCards(box, list, state(wrap).agent?.id, () => []);
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

  async function loadDynamicFields(wrap, scope){
    const target = scope === 'customer'
      ? wrap.querySelector('.bp-dynamic-customer')
      : wrap.querySelector('.bp-dynamic-booking');

    if (!target) return [];
    target.innerHTML = 'Loading...';

    const res = await api(base() + `/wp-json/bp/v1/form-fields?scope=${encodeURIComponent(scope)}`);
    const fields = res?.data || [];

    target.innerHTML = fields.map(renderField).join('');
    return fields;
  }

  function collectDynamicFields(wrap, fields){
    const data = {};

    fields.forEach(f => {
      const key = f.key;

      if (f.type === 'checkbox') {
        const el = wrap.querySelector(`.bp-dyn-checkbox[data-key="${key}"]`);
        data[key] = el ? !!el.checked : false;
        return;
      }

      if (f.type === 'radio') {
        const checked = wrap.querySelector(`input.bp-dyn-radio[data-key="${key}"]:checked`);
        data[key] = checked ? checked.value : '';
        return;
      }

      const el = wrap.querySelector(`.bp-dyn[data-key="${key}"]`);
      data[key] = el ? (el.value ?? '') : '';
    });

    return data;
  }

  function attachCardClicks(wrap){
    wrap.querySelector('.bp-categories').addEventListener('click', async (e) => {
      const card = e.target.closest('.bp-card');
      if (!card) return;

      const id = parseInt(card.getAttribute('data-id') || '0', 10);
      const st = state(wrap);
      st.category = st.lists.categories.find(x=>x.id===id) || null;

      st.service = null;
      st.agent = null;
      st.extras = [];
      st.promo = { valid:false, code:'', discount:0 };
      wrap.querySelector('.bp-promo-msg').textContent = '';
      wrap.querySelector('.bp-promo').value = '';

      wrap.querySelectorAll('.bp-categories .bp-card').forEach(c=>c.classList.remove('is-selected'));
      card.classList.add('is-selected');

      wrap.querySelector('.bp-services').innerHTML = '';
      wrap.querySelector('.bp-extras').innerHTML = '';
      wrap.querySelector('.bp-agents').innerHTML = '';
      setMsg(wrap, '', false);

      await loadServices(wrap, id);
      updateSummary(wrap);
    });

    wrap.querySelector('.bp-services').addEventListener('click', async (e) => {
      const card = e.target.closest('.bp-card');
      if (!card) return;
      const id = parseInt(card.getAttribute('data-id') || '0', 10);
      const st = state(wrap);

      st.service = st.lists.services.find(x=>x.id===id) || null;

      st.agent = null;
      st.extras = [];
      st.promo = { valid:false, code:'', discount:0 };
      wrap.querySelector('.bp-promo-msg').textContent = '';
      wrap.querySelector('.bp-promo').value = '';

      wrap.querySelectorAll('.bp-services .bp-card').forEach(c=>c.classList.remove('is-selected'));
      card.classList.add('is-selected');

      wrap.querySelector('.bp-extras').innerHTML = '';
      wrap.querySelector('.bp-agents').innerHTML = '';

      await loadExtras(wrap, id);
      await loadAgents(wrap, id);
      updateSummary(wrap);
    });

    wrap.querySelector('.bp-extras').addEventListener('click', (e) => {
      const card = e.target.closest('.bp-card');
      if (!card) return;

      const id = parseInt(card.getAttribute('data-id') || '0', 10);
      const st = state(wrap);
      const item = st.lists.extras.find(x=>x.id===id);
      if (!item) return;

      const idx = st.extras.findIndex(x=>x.id===id);
      if (idx >= 0) st.extras.splice(idx, 1);
      else st.extras.push(item);

      st.promo = { valid:false, code:'', discount:0 };
      wrap.querySelector('.bp-promo-msg').textContent = '';
      wrap.querySelector('.bp-promo').value = '';

      card.classList.toggle('is-selected');
      updateSummary(wrap);
    });

    wrap.querySelector('.bp-agents').addEventListener('click', (e) => {
      const card = e.target.closest('.bp-card');
      if (!card) return;
      const id = parseInt(card.getAttribute('data-id') || '0', 10);
      const st = state(wrap);
      st.agent = st.lists.agents.find(x=>x.id===id) || null;

      wrap.querySelectorAll('.bp-agents .bp-card').forEach(c=>c.classList.remove('is-selected'));
      card.classList.add('is-selected');

      updateSummary(wrap);
    });
  }

  async function applyPromo(wrap){
    const st = state(wrap);
    const code = (wrap.querySelector('.bp-promo').value || '').trim().toUpperCase();
    const msg = wrap.querySelector('.bp-promo-msg');

    st.promo = { valid:false, code:'', discount:0 };

    if (!code) {
      msg.textContent = 'Enter a promo code.';
      updateSummary(wrap);
      return;
    }

    const totals = updateSummary(wrap);
    msg.textContent = 'Checking...';

    try {
      const res = await api(base() + `/wp-json/bp/v1/promo/validate?code=${encodeURIComponent(code)}&subtotal=${encodeURIComponent(totals.subtotal)}`);
      if (res?.valid) {
        st.promo = { valid:true, code, discount: parseFloat(res.discount||0) || 0 };
        msg.textContent = `✅ Applied: -€ ${money(st.promo.discount)}`;
      } else {
        msg.textContent = `❌ ${res?.message || 'Invalid code'}`;
      }
    } catch(e) {
      msg.textContent = '❌ Could not validate code.';
    }

    updateSummary(wrap);
  }

  async function submitBooking(wrap){
    const st = state(wrap);
    setMsg(wrap, 'Saving...', true);

    if (!st.service?.id) { setMsg(wrap, 'Please select a service.', true); return; }
    if (!st.category?.id) { setMsg(wrap, 'Please select a category.', true); return; }

    const date = wrap.querySelector('.bp-date').value;
    const time = wrap.querySelector('.bp-time').value;
    const name = (wrap.querySelector('.bp-customer-name').value || '').trim();
    const email = (wrap.querySelector('.bp-customer-email').value || '').trim();

    if (!date || !time) { setMsg(wrap, 'Please choose date and time.', true); return; }
    if (!name || !email) { setMsg(wrap, 'Please enter your name and email.', true); return; }

    const totals = updateSummary(wrap);
    const nonce = wrap.getAttribute('data-nonce') || '';

    const customer_fields = collectDynamicFields(wrap, st.customerFields || []);
    const booking_fields = collectDynamicFields(wrap, st.bookingFields || []);

    const payload = {
      category_id: st.category.id,
      service_id: st.service.id,
      agent_id: st.agent?.id || 0,
      date,
      time,
      customer_name: name,
      customer_email: email,
      extras: st.extras.map(x => ({ id: x.id })),
      promo_code: st.promo.valid ? st.promo.code : '',
      total_price: totals.total,
      customer_fields,
      booking_fields
    };

    try {
      const res = await api(base() + '/wp-json/bp/v1/booking/create', {
        method:'POST',
        headers:{
          'Content-Type':'application/json',
          'X-WP-Nonce': nonce
        },
        body: JSON.stringify(payload)
      });

      if (res?.status === 'success') {
        setMsg(wrap, '✅ Booking created successfully.', true);
      } else {
        setMsg(wrap, '❌ ' + (res?.message || 'Could not create booking.'), true);
      }
    } catch(e) {
      setMsg(wrap, '❌ Network error.', true);
    }
  }

  document.addEventListener('DOMContentLoaded', async function(){
    document.querySelectorAll('.bp-booking').forEach(async function(wrap){
      state(wrap);
      attachCardClicks(wrap);

      await loadCategories(wrap);
      updateSummary(wrap);

      const customerFields = await loadDynamicFields(wrap, 'customer');
      const bookingFields = await loadDynamicFields(wrap, 'booking');
      state(wrap).customerFields = customerFields;
      state(wrap).bookingFields = bookingFields;

      wrap.querySelector('.bp-apply-promo')?.addEventListener('click', ()=>applyPromo(wrap));
      wrap.querySelector('.bp-submit')?.addEventListener('click', ()=>submitBooking(wrap));
    });
  });
})();
