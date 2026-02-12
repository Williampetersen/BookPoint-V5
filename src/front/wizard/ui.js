export function imgOf(item, fallbackFile) {
  const imagesBase = (window.BP_FRONT?.images || '').replace(/\/$/, '') + '/';
  const url = item?.image_url || item?.image || '';

  // If the backend gives a full URL, use it as-is.
  if (url && (String(url).startsWith('http') || String(url).startsWith('/'))) return url;

  const file = String(url || fallbackFile || '').trim();
  if (!file) return '';

  const out = imagesBase + file;
  const v = String(window.BP_FRONT?.imagesBuild || '').trim();
  if (!v) return out;
  const sep = out.includes('?') ? '&' : '?';
  return `${out}${sep}v=${encodeURIComponent(v)}`;
}
