import React from 'react';
import CalendarScreen from './screens/CalendarScreen';
import ScheduleScreen from './screens/ScheduleScreen';
import HolidaysScreen from './screens/HolidaysScreen';
import CatalogScreen from './screens/CatalogScreen';

export default function AdminApp({ route }) {
  if (route === 'calendar') return <CalendarScreen />;
  if (route === 'schedule') return <ScheduleScreen />;
  if (route === 'holidays') return <HolidaysScreen />;
  if (route === 'catalog') return <CatalogScreen />;

  return <div style={{ padding: 16 }}>Unknown route: {route}</div>;
}
