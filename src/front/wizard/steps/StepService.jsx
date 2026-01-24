import React, { useMemo, useState } from 'react';
import { imgOf } from '../ui';

export default function StepService({ services, value, onChange, onBack, onNext }) {
  const [q, setQ] = useState('');

  const filtered = useMemo(() => {
    const s = q.trim().toLowerCase();
    if (!s) return services || [];
    return (services || []).filter((x) => (x.name || '').toLowerCase().includes(s));
  }, [q, services]);

  const canNext = !!value;

  return (
    <div className="bp-step">
      <input
        value={q}
        onChange={(e) => setQ(e.target.value)}
        placeholder="Search service..."
        className="bp-input"
      />

      <div className="bp-cardlist">
        {filtered.map((svc) => {
          const active = String(value) === String(svc.id);
          return (
            <button
              key={svc.id}
              type="button"
              className={active ? 'bp-pickcard active' : 'bp-pickcard'}
              onClick={() => onChange(svc.id)}
            >
              <div className="bp-pickcard-left">
                <img className="bp-thumb" src={imgOf(svc, 'service-image.png')} alt="" />
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
                    <>
                      <span className="bp-price-num">{Number(svc.price).toFixed(0)}</span>
                      <span className="bp-price-cur">Kr</span>
                    </>
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
          &lt;- Back
        </button>
        <button type="button" className="bp-next" disabled={!canNext} onClick={onNext}>
          Next -&gt;
        </button>
      </div>
    </div>
  );
}
