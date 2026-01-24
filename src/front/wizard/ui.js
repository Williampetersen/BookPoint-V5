export function imgOf(item, fallbackFile) {
  const base = (window.BP_FRONT?.images || '').replace(/\/$/, '') + '/';
  const url = item?.image_url || item?.image || '';
  if (url) return url;
  return base + fallbackFile;
}
