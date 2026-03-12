import React, { useEffect, useMemo, useState } from "react";
import { iconDataUri } from "../icons/iconData";

export default function Shell({ theme, onToggleTheme, active, children }) {
  const page = window.pointlybooking_ADMIN?.page || "pointlybooking_dashboard";
  const pluginUrl = String(window.pointlybooking_ADMIN?.pluginUrl || window.bpAdmin?.pluginUrl || "").replace(/\/$/, "");
  const publicImagesUrl = (
    window.pointlybooking_ADMIN?.publicImagesUrl ||
    window.bpAdmin?.publicImagesUrl ||
    (pluginUrl ? `${pluginUrl}/public/images` : "")
  ).replace(/\/$/, "");
  const build = window.pointlybooking_ADMIN?.build || Date.now();
  const logoBase = publicImagesUrl ? `${publicImagesUrl}/logo.png` : "";
  const logoUrl = logoBase ? `${logoBase}?v=${encodeURIComponent(build)}` : "";
  const wpLogoBase = publicImagesUrl ? `${publicImagesUrl}/wordpress-logo.png` : "";
  const wpLogoUrl = wpLogoBase ? `${wpLogoBase}?v=${encodeURIComponent(build)}` : "";
  const ICON = (name, isActive = false) => iconDataUri(name, { active: isActive, theme });

  // Keep sidebar "standard" (expanded) by default.
  const [collapsed, setCollapsed] = useState(false);
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const pickIcon = (name, isActive) => ICON(name, isActive);
  const is = (p) => {
    if (page === p) return true;
    if (p === "pointlybooking_locations" && (page === "pointlybooking_locations_edit" || page === "pointlybooking_location_categories_edit")) return true;
    if (p === "pointlybooking_bookings" && page === "pointlybooking_bookings_edit") return true;
    if (p === "pointlybooking_services" && page === "pointlybooking_services_edit") return true;
    if (p === "pointlybooking_categories" && page === "pointlybooking_categories_edit") return true;
    if (p === "pointlybooking_extras" && page === "pointlybooking_extras_edit") return true;
    if (p === "pointlybooking_agents" && page === "pointlybooking_agents_edit") return true;
    if (p === "pointlybooking_customers" && page === "pointlybooking_customers_edit") return true;
    return false;
  };

  useEffect(() => {
    try {
      window.localStorage.setItem("pointlybooking_sidebar_collapsed", collapsed ? "1" : "0");
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
  const iconCalendar = pickIcon("calendar", is("pointlybooking_calendar"));
  const iconSettings = pickIcon("settings", is("pointlybooking_settings"));
  const iconAdmin = pickIcon("customers", is("pointlybooking_customers"));

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
              <a className={`bp-nav-item ${is("pointlybooking_dashboard") ? "active" : ""}`} href="admin.php?page=pointlybooking_dashboard">
                <span className="bp-sidebar-item" title={collapsed ? "Dashboard" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("dashboard", is("pointlybooking_dashboard"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Dashboard</span>
                </span>
              </a>
              <a className={`bp-nav-item ${is("pointlybooking_bookings") ? "active" : ""}`} href="admin.php?page=pointlybooking_bookings">
                <span className="bp-sidebar-item" title={collapsed ? "Bookings" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("bookings", is("pointlybooking_bookings"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Bookings</span>
                </span>
              </a>
              <a className={`bp-nav-item ${is("pointlybooking_calendar") ? "active" : ""}`} href="admin.php?page=pointlybooking_calendar">
                <span className="bp-sidebar-item" title={collapsed ? "Calendar" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("calendar", is("pointlybooking_calendar"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Calendar</span>
                </span>
              </a>

              <div className="bp-group-sep" aria-hidden="true" />
              <a className={`bp-nav-item ${is("pointlybooking_services") ? "active" : ""}`} href="admin.php?page=pointlybooking_services">
                <span className="bp-sidebar-item" title={collapsed ? "Services" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("services", is("pointlybooking_services"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Services</span>
                </span>
              </a>
              <a className={`bp-nav-item ${is("pointlybooking_categories") ? "active" : ""}`} href="admin.php?page=pointlybooking_categories">
                <span className="bp-sidebar-item" title={collapsed ? "Categories" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("categories", is("pointlybooking_categories"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Categories</span>
                </span>
              </a>
              <a className={`bp-nav-item ${is("pointlybooking_extras") ? "active" : ""}`} href="admin.php?page=pointlybooking_extras">
                <span className="bp-sidebar-item" title={collapsed ? "Service Extras" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("service-extras", is("pointlybooking_extras"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Service Extras</span>
                </span>
              </a>

              <a className={`bp-nav-item ${is("pointlybooking_locations") ? "active" : ""}`} href="admin.php?page=pointlybooking_locations">
                <span className="bp-sidebar-item" title={collapsed ? "Locations" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("locations", is("pointlybooking_locations"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Locations</span>
                </span>
              </a>
              <a className={`bp-nav-item ${is("pointlybooking_agents") ? "active" : ""}`} href="admin.php?page=pointlybooking_agents">
                <span className="bp-sidebar-item" title={collapsed ? "Agents" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("agents", is("pointlybooking_agents"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Agents</span>
                </span>
              </a>
              <a className={`bp-nav-item ${is("pointlybooking_customers") ? "active" : ""}`} href="admin.php?page=pointlybooking_customers">
                <span className="bp-sidebar-item" title={collapsed ? "Customers" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("customers", is("pointlybooking_customers"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Customers</span>
                </span>
              </a>

              <div className="bp-group-sep" aria-hidden="true" />
              <a className={`bp-nav-item ${is("pointlybooking_settings") ? "active" : ""}`} href="admin.php?page=pointlybooking_settings">
                <span className="bp-sidebar-item" title={collapsed ? "Settings" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("settings", is("pointlybooking_settings"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Settings</span>
                </span>
              </a>
              <a className={`bp-nav-item ${is("pointlybooking_design_form") ? "active" : ""}`} href="admin.php?page=pointlybooking_design_form">
                <span className="bp-sidebar-item" title={collapsed ? "Booking Form Designer" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("designer", is("pointlybooking_design_form"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">Booking Form Designer</span>
                </span>
              </a>
              <a className={`bp-nav-item ${is("pointlybooking_how_to_use") ? "active" : ""}`} href="admin.php?page=pointlybooking_how_to_use">
                <span className="bp-sidebar-item" title={collapsed ? "How to Use" : ""}>
                  <img className="bp-sidebar-icon" src={pickIcon("settings", is("pointlybooking_how_to_use"))} alt="" aria-hidden="true" />
                  <span className="bp-sidebar-text">How to Use</span>
                </span>
              </a>

            </nav>
          </div>

          <div className="bp-sidebar-footer">
            <a className="bp-wp-link" href="index.php" aria-label="Back to WordPress">
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

            {page !== "pointlybooking_services_edit" &&
            page !== "pointlybooking_categories_edit" &&
            page !== "pointlybooking_extras_edit" &&
            page !== "pointlybooking_locations_edit" &&
            page !== "pointlybooking_location_categories_edit" &&
            page !== "pointlybooking_agents_edit" &&
            page !== "pointlybooking_customers_edit" ? (
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
                <a className="bp-icon-btn" href="admin.php?page=pointlybooking_calendar" aria-label="Calendar">
                  <img src={iconCalendar} alt="" />
                </a>
                <a className="bp-icon-btn" href="admin.php?page=pointlybooking_settings" aria-label="Settings">
                  <img src={iconSettings} alt="" />
                </a>
                <a className="bp-icon-btn" href="admin.php?page=pointlybooking_customers" aria-label="Admin">
                  <img src={iconAdmin} alt="" />
                </a>
              </div>

              <a className="bp-primary-btn bp-topbar__cta" href="admin.php?page=pointlybooking_bookings_edit&new=1">
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
