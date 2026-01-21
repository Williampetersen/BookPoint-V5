import React from 'react';
import CalendarScreen from './screens/CalendarScreen';
import DashboardScreen from './screens/DashboardScreen';
import ScheduleScreen from './screens/ScheduleScreen';
import HolidaysScreen from './screens/HolidaysScreen';
import CatalogScreen from './screens/CatalogScreen';
import FormFieldsScreen from './screens/FormFieldsScreen';

function getWpAdminPageFallback() {
  try {
    return new URLSearchParams(window.location.search).get('page') || null;
  } catch {
    return null;
  }
}

export default function AdminApp() {
  const wpPage = window.BP_ADMIN?.page || getWpAdminPageFallback() || 'bp';

  if (wpPage === 'bp' || wpPage === 'bp_dashboard') return <DashboardScreen />;
  if (wpPage === 'bp_calendar') return <CalendarScreen />;
  if (wpPage === 'bp_schedule') return <ScheduleScreen />;
  if (wpPage === 'bp_holidays') return <HolidaysScreen />;
  if (wpPage === 'bp_catalog') return <CatalogScreen />;
  if (wpPage === 'bp-form-fields') return <FormFieldsScreen />;

  return <div style={{ padding: 16 }}>Unknown page: {wpPage}</div>;
}
