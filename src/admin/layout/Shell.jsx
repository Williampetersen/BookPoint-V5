import React, { useEffect, useMemo, useState } from "react";
import { iconDataUri } from "../icons/iconData";

export default function Shell({ theme, onToggleTheme, active, children }) {
  const page = window.BP_ADMIN?.page || "bp_dashboard";
  const pluginUrl = window.BP_ADMIN?.pluginUrl || "";
  const logoBase = pluginUrl
    ? pluginUrl.replace(/\/$/, "") + "/public/images/logo.png"
    : `${window.location.origin}/wp-content/plugins/bookpoint-v5/public/images/logo.png`;
  const logoUrl = `${logoBase}?v=${encodeURIComponent(window.BP_ADMIN?.build || Date.now())}`;
  const wpLogoBase = pluginUrl
    ? pluginUrl.replace(/\/$/, "") + "/public/images/wordpress-logo.png"
    : `${window.location.origin}/wp-content/plugins/bookpoint-v5/public/images/wordpress-logo.png`;
  const wpLogoUrl = `${wpLogoBase}?v=${encodeURIComponent(window.BP_ADMIN?.build || Date.now())}`;
  const ICON = (name, isActive = false) => iconDataUri(name, { active: isActive, theme });

  // Keep sidebar "standard" (expanded) by default.
  const [collapsed, setCollapsed] = useState(false);
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const pickIcon = (name, isActive) => ICON(name, isActive);
  const is = (p) => {
    if (page === p) return true;
    if (p === "bp_locations" && (page === "bp_locations_edit" || page === "bp_location_categories_edit")) return true;
    if (p === "bp_bookings" && page === "bp_bookings_edit") return true;
    if (p === "bp_services" && page === "bp_services_edit") return true;
    if (p === "bp_categories" && page === "bp_categories_edit") return true;
    if (p === "bp_extras" && page === "bp_extras_edit") return true;
    if (p === "bp_agents" && page === "bp_agents_edit") return true;
    if (p === "bp_customers" && page === "bp_customers_edit") return true;
    return false;
  };

  useEffect(() => {
    try {
      window.localStorage.setItem("bp_sidebar_collapsed", collapsed ? "1" : "0");
    } catch (e) {
      // ignore storage failures
    }
  }, [collapsed]);

  useEffect(() => {
    if (window.innerWidth >= 640) {
      setCollapsed(false);
    }
    const onKey = (e) => {
      if (e.key === "Escape") setSidebarOpen(false);
    };
    const onResize = () => {
      if (window.innerWidth >= 640) {
        setSidebarOpen(false);
        setCollapsed(false);
      }
    };
    window.addEventListener("keydown", onKey);
    window.addEventListener("resize", onResize);
    return () => {
      window.removeEventListener("keydown", onKey);
      window.removeEventListener("resize", onResize);
    };
  }, []);

  const iconMenu = ICON("menu", false);
  const iconCalendar = pickIcon("calendar", is("bp_calendar"));
  const iconSettings = pickIcon("settings", is("bp_settings"));
  const iconAdmin = pickIcon("customers", is("bp_customers"));

  return (
    <div className="bp-app">
      <div className="bp-shell">
        <aside className={`bp-sidebar ${collapsed ? "is-collapsed" : ""} ${sidebarOpen ? "is-open" : ""}`}>
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
              className="bp-sidebar-close"
              onClick={() => setSidebarOpen(false)}
              aria-label="Close menu"
            >
              X
            </button>
            <button
              type="button"
              className="bp-sidebar-toggle"
              onClick={() => setCollapsed((v) => !v)}
              aria-label="Toggle sidebar"
            >
              <img src={iconMenu} alt="" />
            </button>
          </div>

          <div className="bp-nav-wrap">
            <nav className="bp-nav">
              <div className="bp-group-sep" aria-hidden="true" />
              <a className={`bp-nav-item ${is("bp_dashboard") ? "active" : ""}`} href="admin.php?page=bp_dashboard">
                <span className="bp-sidebar-item" title={collapsed ? "Dashboard" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("dashboard", is("bp_dashboard"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Dashboard</span>
                </span>
              </a>
              <a className={`bp-nav-item ${is("bp_bookings") ? "active" : ""}`} href="admin.php?page=bp_bookings">
                <span className="bp-sidebar-item" title={collapsed ? "Bookings" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("bookings", is("bp_bookings"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Bookings</span>
                </span>
              </a>
              <a className={`bp-nav-item ${is("bp_calendar") ? "active" : ""}`} href="admin.php?page=bp_calendar">
                <span className="bp-sidebar-item" title={collapsed ? "Calendar" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("calendar", is("bp_calendar"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Calendar</span>
                </span>
              </a>

              <div className="bp-group-sep" aria-hidden="true" />
              <a className={`bp-nav-item ${is("bp_services") ? "active" : ""}`} href="admin.php?page=bp_services">
                <span className="bp-sidebar-item" title={collapsed ? "Services" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("services", is("bp_services"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Services</span>
                </span>
              </a>
              <a className={`bp-nav-item ${is("bp_categories") ? "active" : ""}`} href="admin.php?page=bp_categories">
                <span className="bp-sidebar-item" title={collapsed ? "Categories" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("categories", is("bp_categories"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Categories</span>
                </span>
              </a>
              <a className={`bp-nav-item ${is("bp_extras") ? "active" : ""}`} href="admin.php?page=bp_extras">
                <span className="bp-sidebar-item" title={collapsed ? "Service Extras" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("service-extras", is("bp_extras"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Service Extras</span>
                </span>
              </a>

              <a className={`bp-nav-item ${is("bp_locations") ? "active" : ""}`} href="admin.php?page=bp_locations">
                <span className="bp-sidebar-item" title={collapsed ? "Locations" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("locations", is("bp_locations"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Locations</span>
                </span>
              </a>
              <a className={`bp-nav-item ${is("bp_agents") ? "active" : ""}`} href="admin.php?page=bp_agents">
                <span className="bp-sidebar-item" title={collapsed ? "Agents" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("agents", is("bp_agents"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Agents</span>
                </span>
              </a>
              <a className={`bp-nav-item ${is("bp_customers") ? "active" : ""}`} href="admin.php?page=bp_customers">
                <span className="bp-sidebar-item" title={collapsed ? "Customers" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("customers", is("bp_customers"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Customers</span>
                </span>
              </a>

              <div className="bp-group-sep" aria-hidden="true" />
              <a className={`bp-nav-item ${is("bp_settings") ? "active" : ""}`} href="admin.php?page=bp_settings">
                <span className="bp-sidebar-item" title={collapsed ? "Settings" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("settings", is("bp_settings"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Settings</span>
                </span>
              </a>
              <a className={`bp-nav-item ${is("bp_design_form") ? "active" : ""}`} href="admin.php?page=bp_design_form">
                <span className="bp-sidebar-item" title={collapsed ? "Booking Form Designer" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("designer", is("bp_design_form"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Booking Form Designer</span>
                </span>
              </a>
              <a className={`bp-nav-item ${is("bp_how_to_use") ? "active" : ""}`} href="admin.php?page=bp_how_to_use">
                <span className="bp-sidebar-item" title={collapsed ? "How to Use" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("settings", is("bp_how_to_use"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">How to Use</span>
                </span>
              </a>
            </nav>
          </div>

          <div className="bp-sidebar-footer">
            <a className="bp-wp-link" href="/wp-admin/index.php" aria-label="Back to WordPress">
              <img src={wpLogoUrl} alt="" aria-hidden="true" />
            </a>
          </div>
        </aside>

        <div
          className={`bp-sidebar-overlay ${sidebarOpen ? "is-open" : ""}`}
          onClick={() => setSidebarOpen(false)}
          aria-hidden={!sidebarOpen}
        />

        <main className="bp-main">
          <header className="bp-topbar">
            <div className="bp-topbar__left">
              <button
                type="button"
                className="bp-topbar__menu"
                onClick={() => {
                  if (window.innerWidth >= 640) {
                    setCollapsed((v) => !v);
                  } else {
                    setSidebarOpen((v) => !v);
                  }
                }}
                aria-label="Toggle menu"
              >
                <img src={iconMenu} alt="" />
              </button>
              <div className="bp-topbar__logo">
                <img src={logoUrl} alt="BookPoint" />
              </div>
            </div>

            {page !== "bp_services_edit" &&
            page !== "bp_categories_edit" &&
            page !== "bp_extras_edit" &&
            page !== "bp_locations_edit" &&
            page !== "bp_location_categories_edit" &&
            page !== "bp_agents_edit" &&
            page !== "bp_customers_edit" ? (
              <div className="bp-topbar__center">
                <div className="bp-search">
                  <input placeholder="Search..." />
                </div>
              </div>
            ) : (
              <div className="bp-topbar__center" aria-hidden="true" />
            )}

            <div className="bp-topbar__right">
              <div className="bp-topbar__dock">
                <a className="bp-icon-btn" href="admin.php?page=bp_calendar" aria-label="Calendar">
                  <img src={iconCalendar} alt="" />
                </a>
                <a className="bp-icon-btn" href="admin.php?page=bp_settings" aria-label="Settings">
                  <img src={iconSettings} alt="" />
                </a>
                <a className="bp-icon-btn" href="admin.php?page=bp_customers" aria-label="Admin">
                  <img src={iconAdmin} alt="" />
                </a>
              </div>

              <a className="bp-primary-btn bp-topbar__cta" href="admin.php?page=bp_bookings_edit">
                + Booking
              </a>
              <div className="bp-avatar">W</div>
            </div>
          </header>

          <div className="bp-content">{children}</div>
        </main>
      </div>
    </div>
  );
}
