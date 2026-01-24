import React, { useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import WizardModal from './wizard/WizardModal';
import './front.css';

function BookPointWidget({ label }) {
  const [open, setOpen] = useState(false);

  const imagesBase = (window.BP_FRONT?.images || '').replace(/\/$/, '') + '/';
  const brand = useMemo(() => ({
    imagesBase,
    helpPhone: '+45 91 67 14 52',
  }), [imagesBase]);

  return (
    <>
      <button
        className="bp-book-btn"
        type="button"
        onClick={() => setOpen(true)}
      >
        {label || 'Book Now'}
      </button>

      <WizardModal
        open={open}
        onClose={() => setOpen(false)}
        brand={brand}
      />
    </>
  );
}

function getBrand() {
  const imagesBase = (window.BP_FRONT?.images || '').replace(/\/$/, '') + '/';
  return {
    imagesBase,
    helpPhone: '+45 91 67 14 52',
  };
}

function bootLegacy() {
  const mount = document.getElementById('bp-front-root');
  if (!mount) return;

  const root = createRoot(mount);
  const brand = getBrand();

  const open = () => {
    mount.style.display = 'block';
    root.render(
      <WizardModal
        open={true}
        onClose={() => {
          root.render(null);
          mount.style.display = 'none';
        }}
        brand={brand}
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

function boot() {
  const widgets = document.querySelectorAll(".bp-front-root[data-bp-widget='wizard']");
  if (!widgets.length) {
    bootLegacy();
    return;
  }

  widgets.forEach((el) => {
    const root = createRoot(el);
    const label = el.getAttribute('data-bp-label') || 'Book Now';
    root.render(<BookPointWidget label={label} />);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
