import React, { useEffect, useMemo, useState } from "react";
import Shell from "./layout/Shell";

// Screens
import DashboardScreen from "./screens/DashboardScreen";
import BookingsScreen from "./screens/BookingsScreen";
import BookingEditScreen from "./screens/BookingEditScreen";
import FormFieldsScreen from "./screens/FormFieldsScreen";
import ServicesScreen from "./screens/ServicesScreen";
import ServicesEditScreen from "./screens/ServicesEditScreen";
import CategoriesScreen from "./screens/CategoriesScreen";
import CategoriesEditScreen from "./screens/CategoriesEditScreen";
import ExtrasScreen from "./screens/ExtrasScreen";
import ExtrasEditScreen from "./screens/ExtrasEditScreen";
import PromoCodesScreen from "./screens/PromoCodesScreen";
import CalendarScreen from "./screens/CalendarScreen";
import ScheduleScreen from "./screens/ScheduleScreen";
import HolidaysScreen from "./screens/HolidaysScreen";
import LocationsScreen from "./screens/LocationsScreen";
import LocationsEditScreen from "./screens/LocationsEditScreen";
import LocationCategoryEditScreen from "./screens/LocationCategoryEditScreen";
import CustomersScreen from "./screens/CustomersScreen";
import CustomersEditScreen from "./screens/CustomersEditScreen";
import AgentsScreen from "./screens/AgentsScreen";
import AgentsEditScreen from "./screens/AgentsEditScreen";
import SettingsScreen from "./screens/SettingsScreen";
import AuditScreen from "./screens/AuditScreen";
import ToolsScreen from "./screens/ToolsScreen";
import NotificationsScreen from "./screens/NotificationsScreen";
import BookingFormDesignerScreen from "./screens/BookingFormDesignerScreen";
import HowToUseScreen from "./screens/HowToUseScreen";

function resolveScreen(page) {
  switch(page){
    case "pointlybooking_dashboard": return "dashboard";
    case "bp-bookings": return "bookings";
    case "pointlybooking_bookings": return "bookings";
    case "pointlybooking_bookings_edit": return "bookings-edit";
    case "bp-calendar": return "calendar";
    case "pointlybooking_calendar": return "calendar";
    case "bp-schedule": return "schedule";
    case "pointlybooking_schedule": return "schedule";
    case "bp-holidays": return "holidays";
    case "pointlybooking_holidays": return "holidays";

    case "bp-services": return "services";
    case "pointlybooking_services": return "services";
    case "pointlybooking_services_edit": return "services-edit";
    case "bp-categories": return "categories";
    case "pointlybooking_categories": return "categories";
    case "pointlybooking_categories_edit": return "categories-edit";
    case "bp-extras": return "extras";
    case "pointlybooking_extras": return "extras";
    case "pointlybooking_extras_edit": return "extras-edit";
    case "bp-locations": return "locations";
    case "pointlybooking_locations": return "locations";
    case "bp-locations-edit": return "locations-edit";
    case "pointlybooking_locations_edit": return "locations-edit";
    case "bp-location-categories-edit": return "location-categories-edit";
    case "pointlybooking_location_categories_edit": return "location-categories-edit";
    case "bp-promo-codes": return "promo";
    case "pointlybooking_promo_codes": return "promo";
    case "bp-form-fields": return "form-fields";
    case "pointlybooking_form_fields": return "form-fields";
    case "bp-customers": return "customers";
    case "pointlybooking_customers": return "customers";
    case "pointlybooking_customers_edit": return "customers-edit";
    case "bp-agents": return "agents";
    case "pointlybooking_agents": return "agents";
    case "pointlybooking_agents_edit": return "agents-edit";

    case "bp-settings": return "settings";
    case "pointlybooking_settings": return "settings";
    case "bp-notifications": return "notifications";
    case "pointlybooking_notifications": return "notifications";
    case "bp-audit-log": return "audit";
    case "pointlybooking_audit": return "audit";
    case "bp-tools": return "tools";
    case "pointlybooking_tools": return "tools";
    case "pointlybooking_design_form": return "design-form";
    case "pointlybooking_how_to_use": return "how-to";

    default: return "dashboard";
  }
}

export default function AdminApp() {
  const page = window.pointlybooking_ADMIN?.page || "pointlybooking_dashboard";
  const screen = useMemo(() => resolveScreen(page), [page]);

  const [theme, setTheme] = useState("light");

  useEffect(() => {
    setTheme("light");
    document.documentElement.classList.remove("bp-dark");
    localStorage.setItem("pointlybooking_theme", "light");
  }, []);

  function toggleTheme() {
    return;
  }

  return (
    <Shell
      theme={theme}
      onToggleTheme={toggleTheme}
      active={screen}
    >
      {screen === "dashboard" ? <DashboardScreen /> : null}
      {screen === "bookings" ? <BookingsScreen /> : null}
      {screen === "bookings-edit" ? <BookingEditScreen /> : null}
      {screen === "form-fields" ? <FormFieldsScreen /> : null}
      {screen === "services" ? <ServicesScreen /> : null}
      {screen === "services-edit" ? <ServicesEditScreen /> : null}
      {screen === "categories" ? <CategoriesScreen /> : null}
      {screen === "categories-edit" ? <CategoriesEditScreen /> : null}
      {screen === "extras" ? <ExtrasScreen /> : null}
      {screen === "extras-edit" ? <ExtrasEditScreen /> : null}
      {screen === "promo" ? <PromoCodesScreen /> : null}
      {screen === "locations" ? <LocationsScreen /> : null}
      {screen === "locations-edit" ? <LocationsEditScreen /> : null}
      {screen === "location-categories-edit" ? <LocationCategoryEditScreen /> : null}
      {screen === "customers" ? <CustomersScreen /> : null}
      {screen === "customers-edit" ? <CustomersEditScreen /> : null}
      {screen === "agents" ? <AgentsScreen /> : null}
      {screen === "agents-edit" ? <AgentsEditScreen /> : null}
      {screen === "settings" ? <SettingsScreen /> : null}
      {screen === "audit" ? <AuditScreen /> : null}
      {screen === "tools" ? <ToolsScreen /> : null}
      {screen === "notifications" ? <NotificationsScreen /> : null}
      {screen === "calendar" ? <CalendarScreen /> : null}
      {screen === "schedule" ? <ScheduleScreen /> : null}
      {screen === "holidays" ? <HolidaysScreen /> : null}
      {screen === "design-form" ? <BookingFormDesignerScreen /> : null}
      {screen === "how-to" ? <HowToUseScreen /> : null}
    </Shell>
  );
}

function ComingSoon({ title }){
  return (
    <div className="bp-card">
      <div className="bp-card-label">Page</div>
      <div className="bp-card-value" style={{fontSize:18, marginTop:6}}>
        {title} (UI shell active âœ…)
      </div>
      <div className="bp-muted" style={{marginTop:8}}>
        Next: we design and connect this page with Horizon UI layout.
      </div>
    </div>
  );
}
