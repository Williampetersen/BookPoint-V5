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

function boot() {
  document.querySelectorAll(".bp-front-root[data-bp-widget='wizard']").forEach((el) => {
    const root = createRoot(el);
    const label = el.getAttribute('data-bp-label') || 'Book Now';
    root.render(<BookPointWidget label={label} />);
  });
}

document.addEventListener('DOMContentLoaded', boot);
