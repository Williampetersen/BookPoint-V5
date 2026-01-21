import React from 'react';
import { createRoot } from 'react-dom/client';
import AdminApp from './app';

document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('bp-admin-app');
  if (!el) return;

  const route = el.getAttribute('data-route') || (window.BP_ADMIN?.route || 'calendar');
  const root = createRoot(el);
  root.render(<AdminApp route={route} />);
});
