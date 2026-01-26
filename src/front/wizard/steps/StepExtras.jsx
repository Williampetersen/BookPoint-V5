import React from 'react';
import { imgOf } from '../ui';

export default function StepExtras({ extras, value, onChange, onBack, onNext, backLabel = '<- Back', nextLabel = 'Next ->' }) {
  const filtered = extras || [];

  function toggle(id) {
    const sid = String(id);
    const next = (value || []).some((v) => String(v) === sid)
      ? (value || []).filter((v) => String(v) !== sid)
      : [...(value || []), id];
    onChange(next);
  }

  const canNext = true;

  return (
    <div className="bp-step">
      <div className="bp-cardlist">
        {filtered.map((ex) => {
          const selected = (value || []).some((v) => String(v) === String(ex.id));
          return (
            <button
              key={ex.id}
              type="button"
              className={selected ? 'bp-pickcard active' : 'bp-pickcard'}
              onClick={() => toggle(ex.id)}
            >
              <div className="bp-pickcard-left">
                <img className="bp-thumb" src={imgOf(ex, 'service-image.png')} alt="" />
              </div>

              <div className="bp-pickcard-mid">
                <div className="bp-pickcard-title">{ex.name}</div>
                {(ex.description || ex.desc || ex.summary) && (
                  <div className="bp-pickcard-sub">
                    {ex.description || ex.desc || ex.summary}
                  </div>
                )}
              </div>

              <div className="bp-pickcard-right">
                <div className="bp-price">
                  {ex.price != null ? (
                    <span className="bp-price-line">
                      {Number(ex.price).toFixed(0)} Kr
                    </span>
                  ) : (
                    <span className="bp-price-cur">Extra</span>
                  )}
                </div>
                <div className={selected ? 'bp-check on' : 'bp-check'} />
              </div>
            </button>
          );
        })}

        {!filtered.length ? <div className="bp-empty">No extras for this service.</div> : null}
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
