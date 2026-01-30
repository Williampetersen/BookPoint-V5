import React from "react";

export default function Shell({ theme, onToggleTheme, active, children }) {
  const page = window.BP_ADMIN?.page || "bp_dashboard";
  const pluginUrl = window.BP_ADMIN?.pluginUrl || "";
  const logoBase = pluginUrl
    ? pluginUrl.replace(/\/$/, "") + "/public/images/logo.png"
    : `${window.location.origin}/wp-content/plugins/bookpoint-v5/public/images/logo.png`;
  const logoUrl = `${logoBase}?v=${encodeURIComponent(window.BP_ADMIN?.build || Date.now())}`;
  const is = (p) => {
    if (page === p) return true;
    if (p === "bp_locations" && (page === "bp_locations_edit" || page === "bp_location_categories_edit")) return true;
    if (p === "bp_bookings" && page === "bp_bookings_edit") return true;
    return false;
  };

  return (
    <div className="bp-app">
      <div className="bp-shell">
        <aside className="bp-sidebar">
          <div className="bp-brand">
            <div className="bp-logo">
              <img src={logoUrl} alt="BookPoint" />
            </div>
            <div>
              <div className="bp-title">BookPoint</div>
              <div className="bp-sub">Admin</div>
            </div>
          </div>

          <nav className="bp-nav">
            {/* DAILY */}
            <div className="bp-group-title">OVERVIEW</div>
            <a className={`bp-nav-item ${is("bp_dashboard")?"active":""}`} href="admin.php?page=bp_dashboard">Dashboard</a>
            <a className={`bp-nav-item ${is("bp_bookings")?"active":""}`} href="admin.php?page=bp_bookings">Bookings</a>
            <a className={`bp-nav-item ${is("bp_calendar")?"active":""}`} href="admin.php?page=bp_calendar">Calendar</a>

            {/* RESOURCES */}
            <div className="bp-group-title">MANAGE</div>
            <div className="bp-sidegroup">
              <div className="bp-subnav">
                <a className={`bp-sub-item ${is("bp_services")?"active":""}`} href="admin.php?page=bp_services">Services</a>
                <a className={`bp-sub-item ${is("bp_categories")?"active":""}`} href="admin.php?page=bp_categories">Categories</a>
                <a className={`bp-sub-item ${is("bp_extras")?"active":""}`} href="admin.php?page=bp_extras">Service Extras</a>
              </div>
            </div>

            <a className={`bp-nav-item ${is("bp_locations")?"active":""}`} href="admin.php?page=bp_locations">Locations</a>
            <a className={`bp-nav-item ${is("bp_agents")?"active":""}`} href="admin.php?page=bp_agents">Agents</a>
            <a className={`bp-nav-item ${is("bp_customers")?"active":""}`} href="admin.php?page=bp_customers">Customers</a>

            {/* SYSTEM */}
            <div className="bp-group-title">SYSTEM</div>
            <a className={`bp-nav-item ${is("bp_settings")?"active":""}`} href="admin.php?page=bp_settings">Settings</a>
            <a className={`bp-nav-item ${is("bp_design_form")?"active":""}`} href="admin.php?page=bp_design_form">Booking Form Designer</a>
          </nav>

          <div className="bp-sidebar-footer">
            <a className="bp-top-btn" href="/wp-admin/index.php">
              ← Back to WordPress
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
