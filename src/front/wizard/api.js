async function api(path, opts = {}) {
  const base = window.BP_FRONT?.restUrl || '/wp-json/bp/v1';
  const nonce = window.BP_FRONT?.nonce;

  const res = await fetch(`${base}${path}`, {
    ...opts,
    headers: {
      'Content-Type': 'application/json',
      ...(nonce ? { 'X-WP-Nonce': nonce } : {}),
      ...(opts.headers || {}),
    },
  });

  const json = await res.json().catch(() => ({}));
  if (!res.ok || json?.status === 'error') {
    throw new Error(json?.message || 'Request failed');
  }
  return json?.data ?? json;
}

export const fetchLocations = () => api('/front/locations');
export const fetchCategories = () => api('/front/categories');
export const fetchServices = ({ category_ids = [], location_id = null }) =>
  api('/front/services', {
    method: 'POST',
    body: JSON.stringify({ category_ids, location_id }),
  });

export const fetchExtras = ({ service_id }) =>
  api('/front/extras', {
    method: 'POST',
    body: JSON.stringify({ service_id }),
  });

export const fetchAgents = ({ service_id, location_id }) =>
  api('/front/agents', {
    method: 'POST',
    body: JSON.stringify({ service_id, location_id }),
  });

export const fetchSlots = ({ service_id, agent_id, date, location_id = null }) =>
  api('/front/slots', {
    method: 'POST',
    body: JSON.stringify({ service_id, agent_id, date, location_id }),
  });

export const fetchAvailabilityMonth = ({ month, service_id, agent_id, location_id = null }) =>
  api(`/front/availability?month=${encodeURIComponent(month)}&service_id=${encodeURIComponent(service_id)}&agent_id=${encodeURIComponent(agent_id)}&location_id=${encodeURIComponent(location_id || '')}`);

export const fetchFormFields = async () => {
  const list = await api('/front/form-fields');
  const form = [];
  const customer = [];
  const booking = [];

  (Array.isArray(list) ? list : []).forEach((f) => {
    const scope = f.scope || 'customer';
    if (scope === 'booking') booking.push(f);
    else if (scope === 'form') form.push(f);
    else customer.push(f);
  });

  return { form, customer, booking };
};

export const createBooking = (payload) =>
  api('/front/bookings', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
