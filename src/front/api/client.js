export async function bpPublicFetch(path) {
  const base = window.BP_FRONT?.restUrl || '/wp-json/bp/v1';
  const url = base.replace(/\/$/, '') + path;

  const res = await fetch(url, { method: 'GET', credentials: 'same-origin' });
  const json = await res.json().catch(() => ({}));
  if (!res.ok || json?.status === 'error') {
    throw new Error(json?.message || `Request failed (${res.status})`);
  }
  return json;
}
