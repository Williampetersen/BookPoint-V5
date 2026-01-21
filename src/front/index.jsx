import React from 'react';
import { createRoot } from 'react-dom/client';
import WizardModal from './wizard/WizardModal';

function boot() {
  const mount = document.getElementById('bp-front-root');
  if (!mount) return;

  const root = createRoot(mount);

  const open = () => {
    mount.style.display = 'block';
    root.render(
      <WizardModal
        onClose={() => {
          root.render(null);
          mount.style.display = 'none';
        }}
      />
    );
  };

  document.addEventListener('click', (e) => {
    const target = e.target;
    if (!(target instanceof Element)) return;
    if (target.closest('.bp-open-wizard')) {
      e.preventDefault();
      open();
    }
  });
}

document.addEventListener('DOMContentLoaded', boot);
