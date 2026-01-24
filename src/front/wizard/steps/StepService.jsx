import React from 'react';

export default function StepService({ services, value, onChange, onBack, onNext }) {
  const canNext = !!value;

  return (
    <div className="bp-step">
      <div className="bp-grid">
        {services.map((svc) => {
          const selected = String(value) === String(svc.id);
          return (
            <button
              key={svc.id}
              type="button"
              className={selected ? 'bp-tile active' : 'bp-tile'}
              onClick={() => onChange(svc.id)}
            >
              {svc.image_url ? <img className="bp-tile-img" src={svc.image_url} alt="" /> : null}
              <div className="bp-tile-title">{svc.name}</div>
              <div className="bp-tile-sub">
                {Number(svc.price || 0).toFixed(2)} • {svc.duration || 0} min
              </div>
            </button>
          );
        })}
        {!services.length ? <div className="bp-empty">No services found.</div> : null}
      </div>

      <div className="bp-step-footer">
        <button type="button" className="bp-back" onClick={onBack}>&lt;- Back</button>
        <button type="button" className="bp-next" disabled={!canNext} onClick={onNext}>
          Next ->
        </button>
      </div>
    </div>
  );
}
