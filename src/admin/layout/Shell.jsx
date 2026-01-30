import React, { useMemo, useState } from "react";

export default function Shell({ theme, onToggleTheme, active, children }) {
  const page = window.BP_ADMIN?.page || "bp_dashboard";
  const pluginUrl = window.BP_ADMIN?.pluginUrl || "";
  const logoBase = pluginUrl
    ? pluginUrl.replace(/\/$/, "") + "/public/images/logo.png"
    : `${window.location.origin}/wp-content/plugins/bookpoint-v5/public/images/logo.png`;
  const logoUrl = `${logoBase}?v=${encodeURIComponent(window.BP_ADMIN?.build || Date.now())}`;
  const iconBase = (window.bpAdmin?.iconsUrl || window.BP_ADMIN?.publicIconsUrl || "")
    .replace(/\/$/, "") || (pluginUrl
      ? pluginUrl.replace(/\/$/, "") + "/public/icons"
      : `${window.location.origin}/wp-content/plugins/bookpoint-v5/public/icons`);
  const ICON = (name) => `${iconBase}/${name}.svg`;
  const ICON_ACTIVE = (name) => `${iconBase}/${name}-active.svg`;
  const ICON_DARK = (name) => `${iconBase}/${name}-dark.svg`;
  const ICON_ACTIVE_DARK = (name) => `${iconBase}/${name}-active-dark.svg`;
  const [collapsed, setCollapsed] = useState(false);
  const isDark = theme === "dark";
  const pickIcon = (name, isActive) => {
    if (isDark) return isActive ? ICON_ACTIVE_DARK(name) : ICON_DARK(name);
    return isActive ? ICON_ACTIVE(name) : ICON(name);
  };
  const is = (p) => {
    if (page === p) return true;
    if (p === "bp_locations" && (page === "bp_locations_edit" || page === "bp_location_categories_edit")) return true;
    if (p === "bp_bookings" && page === "bp_bookings_edit") return true;
    return false;
  };

  return (
    <div className="bp-app">
      <div className="bp-shell">
        <aside className={`bp-sidebar ${collapsed ? "is-collapsed" : ""}`}>
          <div className="bp-brand">
            <div className="bp-logo">
              <img src={logoUrl} alt="BookPoint" />
            </div>
            <div>
              <div className="bp-title">BookPoint</div>
              <div className="bp-sub">Admin</div>
            </div>
            <button
              type="button"
              className="bp-sidebar-toggle"
              onClick={() => setCollapsed((v) => !v)}
              aria-label="Toggle sidebar"
            >
              ☰
            </button>
          </div>

          <nav className="bp-nav">
            {/* DAILY */}
            <div className="bp-group-title">OVERVIEW</div>
            <a className={`bp-nav-item ${is("bp_dashboard")?"active":""}`} href="admin.php?page=bp_dashboard">
              <span className="bp-sidebar-item" title={collapsed ? "Dashboard" : ""}>
                <img className="bp-sidebar-icon" src={pickIcon("dashboard", is("bp_dashboard"))} alt="" aria-hidden="true" />
                <span className="bp-sidebar-text">Dashboard</span>
              </span>
            </a>
            <a className={`bp-nav-item ${is("bp_bookings")?"active":""}`} href="admin.php?page=bp_bookings">
              <span className="bp-sidebar-item" title={collapsed ? "Bookings" : ""}>
                <img className="bp-sidebar-icon" src={pickIcon("bookings", is("bp_bookings"))} alt="" aria-hidden="true" />
                <span className="bp-sidebar-text">Bookings</span>
              </span>
            </a>
            <a className={`bp-nav-item ${is("bp_calendar")?"active":""}`} href="admin.php?page=bp_calendar">
              <span className="bp-sidebar-item" title={collapsed ? "Calendar" : ""}>
                <img className="bp-sidebar-icon" src={pickIcon("calendar", is("bp_calendar"))} alt="" aria-hidden="true" />
                <span className="bp-sidebar-text">Calendar</span>
              </span>
            </a>

            {/* RESOURCES */}
            <div className="bp-group-title">MANAGE</div>
            <a className={`bp-nav-item ${is("bp_services")?"active":""}`} href="admin.php?page=bp_services">
              <span className="bp-sidebar-item" title={collapsed ? "Services" : ""}>
                <img className="bp-sidebar-icon" src={pickIcon("services", is("bp_services"))} alt="" aria-hidden="true" />
                <span className="bp-sidebar-text">Services</span>
              </span>
            </a>
            <a className={`bp-nav-item ${is("bp_categories")?"active":""}`} href="admin.php?page=bp_categories">
              <span className="bp-sidebar-item" title={collapsed ? "Categories" : ""}>
                <img className="bp-sidebar-icon" src={pickIcon("categories", is("bp_categories"))} alt="" aria-hidden="true" />
                <span className="bp-sidebar-text">Categories</span>
              </span>
            </a>
            <a className={`bp-nav-item ${is("bp_extras")?"active":""}`} href="admin.php?page=bp_extras">
              <span className="bp-sidebar-item" title={collapsed ? "Service Extras" : ""}>
                <img className="bp-sidebar-icon" src={pickIcon("service-extras", is("bp_extras"))} alt="" aria-hidden="true" />
                <span className="bp-sidebar-text">Service Extras</span>
              </span>
            </a>

            <a className={`bp-nav-item ${is("bp_locations")?"active":""}`} href="admin.php?page=bp_locations">
              <span className="bp-sidebar-item" title={collapsed ? "Locations" : ""}>
                <img className="bp-sidebar-icon" src={pickIcon("locations", is("bp_locations"))} alt="" aria-hidden="true" />
                <span className="bp-sidebar-text">Locations</span>
              </span>
            </a>
            <a className={`bp-nav-item ${is("bp_agents")?"active":""}`} href="admin.php?page=bp_agents">
              <span className="bp-sidebar-item" title={collapsed ? "Agents" : ""}>
                <img className="bp-sidebar-icon" src={pickIcon("agents", is("bp_agents"))} alt="" aria-hidden="true" />
                <span className="bp-sidebar-text">Agents</span>
              </span>
            </a>
            <a className={`bp-nav-item ${is("bp_customers")?"active":""}`} href="admin.php?page=bp_customers">
              <span className="bp-sidebar-item" title={collapsed ? "Customers" : ""}>
                <img className="bp-sidebar-icon" src={pickIcon("customers", is("bp_customers"))} alt="" aria-hidden="true" />
                <span className="bp-sidebar-text">Customers</span>
              </span>
            </a>

            {/* SYSTEM */}
            <div className="bp-group-title">SYSTEM</div>
            <a className={`bp-nav-item ${is("bp_settings")?"active":""}`} href="admin.php?page=bp_settings">
              <span className="bp-sidebar-item" title={collapsed ? "Settings" : ""}>
                <img className="bp-sidebar-icon" src={pickIcon("settings", is("bp_settings"))} alt="" aria-hidden="true" />
                <span className="bp-sidebar-text">Settings</span>
              </span>
            </a>
            <a className={`bp-nav-item ${is("bp_design_form")?"active":""}`} href="admin.php?page=bp_design_form">
              <span className="bp-sidebar-item" title={collapsed ? "Booking Form Designer" : ""}>
                <img className="bp-sidebar-icon" src={pickIcon("designer", is("bp_design_form"))} alt="" aria-hidden="true" />
                <span className="bp-sidebar-text">Booking Form Designer</span>
              </span>
            </a>
          </nav>

          <div className="bp-sidebar-footer">
            <a className="bp-wp-link" href="/wp-admin/index.php" aria-label="Back to WordPress">
              <span className="dashicons dashicons-wordpress" aria-hidden="true" />
            </a>
            <a className="bp-top-btn" href="/wp-admin/index.php">
              Back to WordPress
            </a>
          </div>
        </aside>

        <main className="bp-main">
          <header className="bp-topbar">
            <div className="bp-search">
              <input placeholder="Search…" />
            </div>

            <div className="bp-top-actions">
              <button className="bp-top-btn" onClick={onToggleTheme}>
                {theme === "dark" ? "☀️ Light" : "🌙 Dark"}
              </button>
              <div className="bp-avatar">W</div>
            </div>
          </header>

          <div className="bp-content">{children}</div>
        </main>
      </div>
    </div>
  );
}

