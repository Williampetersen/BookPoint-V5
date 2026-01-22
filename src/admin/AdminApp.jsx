import React, { useEffect, useMemo, useState } from "react";
import Shell from "./layout/Shell";

// Your screens (add gradually)
import DashboardScreen from "./screens/DashboardScreen";
import BookingsScreen from "./screens/BookingsScreen";
import FormFieldsScreen from "./screens/FormFieldsScreen";

function resolveScreen(page) {
  switch(page){
    case "bp_dashboard": return "dashboard";
    case "bp-bookings": return "bookings";
    case "bp_bookings": return "bookings";
    case "bp-calendar": return "calendar";
    case "bp_calendar": return "calendar";
    case "bp-schedule": return "schedule";
    case "bp_schedule": return "schedule";
    case "bp-holidays": return "holidays";
    case "bp_holidays": return "holidays";

    case "bp-services": return "services";
    case "bp_services": return "services";
    case "bp-categories": return "categories";
    case "bp_categories": return "categories";
    case "bp-extras": return "extras";
    case "bp_extras": return "extras";
    case "bp-promo-codes": return "promo";
    case "bp_promo_codes": return "promo";
    case "bp-form-fields": return "form-fields";
    case "bp-customers": return "customers";
    case "bp_customers": return "customers";
    case "bp-agents": return "agents";
    case "bp_agents": return "agents";

    case "bp-settings": return "settings";
    case "bp_settings": return "settings";
    case "bp-audit-log": return "audit";
    case "bp_audit": return "audit";
    case "bp-tools": return "tools";
    case "bp_tools": return "tools";

    default: return "dashboard";
  }
}

export default function AdminApp() {
  const page = window.BP_ADMIN?.page || "bp_dashboard";
  const screen = useMemo(() => resolveScreen(page), [page]);

  const [theme, setTheme] = useState("light");

  useEffect(() => {
    const saved = localStorage.getItem("bp_theme");
    const t = saved === "dark" ? "dark" : "light";
    setTheme(t);
    document.documentElement.classList.toggle("bp-dark", t === "dark");
  }, []);

  function toggleTheme() {
    const next = theme === "dark" ? "light" : "dark";
    setTheme(next);
    localStorage.setItem("bp_theme", next);
    document.documentElement.classList.toggle("bp-dark", next === "dark");
  }

  return (
    <Shell
      theme={theme}
      onToggleTheme={toggleTheme}
      active={screen}
    >
      {screen === "dashboard" ? <DashboardScreen /> : null}
      {screen === "bookings" ? <BookingsScreen /> : null}
      {screen === "form-fields" ? <FormFieldsScreen /> : null}
      {["calendar","schedule","holidays","services","categories","extras","promo","customers","agents","settings","audit","tools"].includes(screen)
        ? <ComingSoon title={screen} />
        : null}
    </Shell>
  );
}

function ComingSoon({ title }){
  return (
    <div className="bp-card">
      <div className="bp-card-label">Page</div>
      <div className="bp-card-value" style={{fontSize:18, marginTop:6}}>
        {title} (UI shell active ✅)
      </div>
      <div className="bp-muted" style={{marginTop:8}}>
        Next: we design and connect this page with Horizon UI layout.
      </div>
    </div>
  );
}
