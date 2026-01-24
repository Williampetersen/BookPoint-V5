import React from 'react';

export default function StepAgent({ agents, value, onChange, onBack, onNext }) {
  const canNext = !!value;

  return (
    <div className="bp-step">
      <div className="bp-grid">
        {agents.map((ag) => {
          const selected = String(value) === String(ag.id);
          return (
            <button
              key={ag.id}
              type="button"
              className={selected ? 'bp-tile active' : 'bp-tile'}
              onClick={() => onChange(ag.id)}
            >
              {ag.image_url ? <img className="bp-avatar" src={ag.image_url} alt="" /> : null}
              <div className="bp-tile-title">{ag.name || `#${ag.id}`}</div>
            </button>
          );
        })}
        {!agents.length ? <div className="bp-empty">No agents available.</div> : null}
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
