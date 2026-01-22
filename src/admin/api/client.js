export async function bpFetch(path, opts = {}) {
  const base = window.BP_ADMIN?.restUrl || '/wp-json/bp/v1';
  const url = base.replace(/\/$/, '') + path;

  const headers = {
    'Content-Type': 'application/json',
    ...(opts.headers || {}),
  };

  const nonce = window.BP_ADMIN?.nonce;
  if (nonce) headers['X-WP-Nonce'] = nonce;

  const body = typeof opts.body === 'string' ? opts.body : (opts.body ? JSON.stringify(opts.body) : undefined);

  const res = await fetch(url, {
    method: opts.method || 'GET',
    credentials: 'same-origin',
    headers,
    body,
  });

  const json = await res.json().catch(() => ({}));
  if (!res.ok || json?.status === 'error') {
    const msg = json?.message || 'Request failed';
    const err = new Error(msg);
    err.status = res.status;
    err.payload = json;
    throw err;
  }
  return json;
}

export async function bpPost(path, body = {}) {
  return bpFetch(path, { method: 'POST', body });
}
