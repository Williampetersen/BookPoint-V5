import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import WizardModal from './wizard/WizardModal';
import './front.css';

function BookPointWidget({ label, mountEl }) {
  const [open, setOpen] = useState(false);
  const hideButton = mountEl?.getAttribute('data-bp-fallback') === '1';

  const imagesBase = (window.pointlybooking_FRONT?.images || '').replace(/\/$/, '') + '/';
  const iconsBase = (window.pointlybooking_FRONT?.icons || '').replace(/\/$/, '') + '/';
  const brand = useMemo(() => ({
    imagesBase,
    iconsBase,
  }), [imagesBase, iconsBase]);

  const presetServiceId = Number(mountEl?.getAttribute('data-bp-service-id') || 0) || null;
  const presetDate = mountEl?.getAttribute('data-bp-default-date') || null;
  const hideNotes = mountEl?.getAttribute('data-bp-hide-notes') === '1';
  const requirePhone = mountEl?.getAttribute('data-bp-require-phone') === '1';
  const compact = mountEl?.getAttribute('data-bp-compact') === '1';

  useEffect(() => attachOpenHandler(mountEl, setOpen), [mountEl]);

  return (
    <>
      {!hideButton && (
        <button
          className="bp-book-btn"
          type="button"
          onClick={() => setOpen(true)}
        >
          {label || 'Book Now'}
        </button>
      )}

      <WizardModal
        open={open}
        onClose={() => setOpen(false)}
        brand={brand}
        presetServiceId={presetServiceId}
        presetDate={presetDate}
        hideNotes={hideNotes}
        requirePhone={requirePhone}
        compact={compact}
      />
    </>
  );
}

function attachOpenHandler(mountEl, setOpen) {
  if (!mountEl) return () => {};
  mountEl.__bpOpen = () => setOpen(true);
  return () => {
    if (mountEl.__bpOpen) delete mountEl.__bpOpen;
  };
}

function getBrand() {
  const imagesBase = (window.pointlybooking_FRONT?.images || '').replace(/\/$/, '') + '/';
  const iconsBase = (window.pointlybooking_FRONT?.icons || '').replace(/\/$/, '') + '/';
  return {
    imagesBase,
    iconsBase,
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
    root.render(
      <BookPointWidget
        label={label}
        mountEl={el}
      />
    );
  });

  document.addEventListener('click', (e) => {
    const target = e.target;
    if (!(target instanceof Element)) return;
    const trigger = target.closest('.bp-fallback-btn, [data-bp-open="wizard"]');
    if (!trigger) return;

    const candidate = trigger.nextElementSibling;
    const mount = candidate && candidate.classList.contains('bp-front-root')
      ? candidate
      : trigger.closest('.bp-front-root') || document.querySelector('.bp-front-root');

    if (mount && typeof mount.__bpOpen === 'function') {
      e.preventDefault();
      mount.__bpOpen();
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
