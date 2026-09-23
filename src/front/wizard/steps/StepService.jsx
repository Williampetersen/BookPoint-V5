import React from 'react';
import { imgOf } from '../ui';
import { formatMoney } from '../money';

export default function StepService({ services, value, onChange, onBack, onNext, settings, backLabel = '<- Back', nextLabel = 'Next ->', loading = false }) {
  const filtered = services || [];

  const canNext = !!value;

  if (loading) {
    return (
      <div className="bp-step">
        <div className="bp-cardlist">
          <div className="bp-skel" style={{ height: 84 }} />
          <div className="bp-skel" style={{ height: 84 }} />
          <div className="bp-skel" style={{ height: 84 }} />
        </div>
        <div className="bp-step-footer">
          <button type="button" className="bp-back" onClick={onBack}>{backLabel}</button>
          <button type="button" className="bp-next" disabled>{nextLabel}</button>
        </div>
      </div>
    );
  }

  return (
    <div className="bp-step">
      <div className="bp-cardlist" role="radiogroup" aria-label="Service">
        {filtered.map((svc) => {
          const active = String(value) === String(svc.id);
          return (
            <button
              key={svc.id}
              type="button"
              role="radio"
              aria-checked={active}
              className={active ? 'bp-pickcard active' : 'bp-pickcard'}
              onClick={() => onChange(svc.id)}
            >
              <div className="bp-pickcard-left">
                <img
                  className="bp-thumb"
                  src={imgOf(svc, 'service-image.png')}
                  alt=""
                  onError={(e) => { e.currentTarget.src = imgOf({}, 'service-image.png'); }}
                />
              </div>

              <div className="bp-pickcard-mid">
                <div className="bp-pickcard-title">{svc.name}</div>
                {!!svc.description && <div className="bp-pickcard-sub">{svc.description}</div>}

                <div className="bp-meta">
                  {!!svc.duration && (
                    <span className="bp-chip">{svc.duration} min</span>
                  )}
                  {!!svc.category_name && (
                    <span className="bp-chip ghost">{svc.category_name}</span>
                  )}
                </div>
              </div>

              <div className="bp-pickcard-right">
                <div className="bp-price">
                  {svc.price != null ? (
                    <span className="bp-price-line">
                      {formatMoney(svc.price, settings)}
                    </span>
                  ) : (
                    <span className="bp-price-cur">Select</span>
                  )}
                </div>
                <div className={active ? 'bp-radio on' : 'bp-radio'} />
              </div>
            </button>
          );
        })}

        {!filtered.length ? <div className="bp-empty">No services found.</div> : null}
      </div>

      <div className="bp-step-footer">
        <button type="button" className="bp-back" onClick={onBack}>
          {backLabel}
        </button>
        <button type="button" className="bp-next" disabled={!canNext} onClick={onNext}>
          {nextLabel}
        </button>
      </div>
    </div>
  );
}
