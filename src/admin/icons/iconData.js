const KNOWN_ICON_NAMES = new Set([
  "agents",
  "bookings",
  "calendar",
  "categories",
  "customers",
  "dashboard",
  "designer",
  "locations",
  "menu",
  "service-extras",
  "services",
  "settings"
]);

const baseIconsUrl = () => {
  const admin = window.BP_ADMIN || {};
  const legacy = window.bpAdmin || {};
  const base =
    admin.publicIconsUrl ||
    legacy.iconsUrl ||
    (admin.pluginUrl ? `${admin.pluginUrl}public/icons` : "");
  return (base || "").replace(/\/$/, "");
};

export function iconDataUri(name, { active = false, theme = "light" } = {}) {
  const base = baseIconsUrl();
  if (!base) return "";
  const build =
    (window.BP_ADMIN && window.BP_ADMIN.iconsBuild) ||
    (window.bpAdmin && window.bpAdmin.iconsBuild) ||
    "";

  const key = String(name || "").trim();
  if (!key) return "";

  if (key === "menu") {
    const url = `${base}/menu.svg`;
    if (!build) return url;
    const sep = url.includes("?") ? "&" : "?";
    return `${url}${sep}v=${encodeURIComponent(build)}`;
  }

  const isDark = theme === "dark";
  let file = key;
  if (active) file += "-active";
  if (isDark) file += "-dark";

  if (!KNOWN_ICON_NAMES.has(key)) {
    const url = `${base}/${key}.svg`;
    if (!build) return url;
    const sep = url.includes("?") ? "&" : "?";
    return `${url}${sep}v=${encodeURIComponent(build)}`;
  }

  const url = `${base}/${file}.svg`;
  if (!build) return url;
  const sep = url.includes("?") ? "&" : "?";
  return `${url}${sep}v=${encodeURIComponent(build)}`;
}
