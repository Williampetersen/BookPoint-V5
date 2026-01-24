import React from 'react';

export default function StepExtras({ extras, value, onChange, onBack, onNext }) {
  function toggle(id) {
    const sid = String(id);
    const next = value.some((v) => String(v) === sid)
      ? value.filter((v) => String(v) !== sid)
      : [...value, id];
    onChange(next);
  }

  return (
    <div className="bp-step">
      <div className="bp-grid">
        {extras.map((ex) => {
          const selected = value.some((v) => String(v) === String(ex.id));
          return (
            <button
              key={ex.id}
              type="button"
              className={selected ? 'bp-tile active' : 'bp-tile'}
              onClick={() => toggle(ex.id)}
            >
              {ex.image_url ? <img className="bp-tile-img" src={ex.image_url} alt="" /> : null}
              <div className="bp-tile-title">{ex.name}</div>
              <div className="bp-tile-sub">+ {Number(ex.price || 0).toFixed(2)}</div>
            </button>
          );
        })}
        {!extras.length ? <div className="bp-empty">No extras for this service.</div> : null}
      </div>

      <div className="bp-step-footer">
        <button type="button" className="bp-back" onClick={onBack}>&lt;- Back</button>
        <button type="button" className="bp-next" onClick={onNext}>
          Next ->
        </button>
      </div>
    </div>
  );
}
