(function(){
  function qs(sel, root){ return (root||document).querySelector(sel); }

  function setMsg(root, text){
    const el = qs('.bp-r-msg', root);
    if (el) el.textContent = text || '';
  }

  async function loadSlots(root){
    const serviceId = parseInt(root.dataset.serviceId || '0', 10);
    const agentId = parseInt(root.dataset.agentId || '0', 10);
    const dateEl = qs('.bp-r-date', root);
    const timeSel = qs('.bp-r-time', root);

    if (!timeSel || !dateEl) return;

    const date = dateEl.value;
    timeSel.innerHTML = '<option value="">Select a time</option>';
    setMsg(root, '');

    if(!serviceId || !date) return;

    const excludeId = root.dataset.excludeBookingId || '';
    const url = `/wp-json/bp/v1/manage/slots?service_id=${serviceId}&agent_id=${agentId}&date=${encodeURIComponent(date)}&exclude_booking_id=${encodeURIComponent(excludeId)}`;

    const res = await fetch(url).then(r => r.json()).catch(() => null);
    const slots = (res && res.data) ? res.data : [];

    slots.forEach(s => {
      const opt = document.createElement('option');
      opt.value = (s.start || '') + '|' + (s.end || '');
      opt.textContent = s.label || s.start || '';
      timeSel.appendChild(opt);
    });

    if(!slots.length) setMsg(root, 'No available times for this date.');
  }

  document.addEventListener('DOMContentLoaded', function(){
    const root = qs('.bp-reschedule');
    if(!root) return;

    const dateEl = qs('.bp-r-date', root);
    const timeSel = qs('.bp-r-time', root);
    const startEl = qs('.bp-new-start', root);
    const endEl = qs('.bp-new-end', root);

    if (dateEl) {
      dateEl.addEventListener('change', function(){
        loadSlots(root);
      });
    }

    if (timeSel) {
      timeSel.addEventListener('change', function(){
        const val = this.value || '';
        const parts = val.split('|');
        if (startEl) startEl.value = parts[0] || '';
        if (endEl) endEl.value = parts[1] || '';
      });
    }
  });
})();
