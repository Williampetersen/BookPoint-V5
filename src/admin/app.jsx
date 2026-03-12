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
  const wpPage = window.pointlybooking_ADMIN?.page || getWpAdminPageFallback() || 'bp';

  if (wpPage === 'bp' || wpPage === 'pointlybooking_dashboard') return <DashboardScreen />;
  if (wpPage === 'pointlybooking_calendar') return <CalendarScreen />;
  if (wpPage === 'pointlybooking_schedule') return <ScheduleScreen />;
  if (wpPage === 'pointlybooking_holidays') return <HolidaysScreen />;
  if (wpPage === 'pointlybooking_catalog') return <CatalogScreen />;
  if (wpPage === 'bp-form-fields' || wpPage === 'pointlybooking_form_fields') return <FormFieldsScreen />;

  return <div style={{ padding: 16 }}>Unknown page: {wpPage}</div>;
}
