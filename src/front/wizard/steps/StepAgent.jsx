import React from 'react';
import { imgOf } from '../ui';

export default function StepAgent({ agents, value, onChange, onBack, onNext }) {
  const filtered = agents || [];

  const canNext = !!value;

  return (
    <div className="bp-step">
      <div className="bp-cardlist">
        {filtered.map((ag) => {
          const active = String(value) === String(ag.id);
          return (
            <button
              key={ag.id}
              type="button"
              className={active ? 'bp-pickcard active' : 'bp-pickcard'}
              onClick={() => onChange(ag.id)}
            >
              <div className="bp-pickcard-left">
                <img className="bp-avatar" src={imgOf(ag, 'default-avatar.jpg')} alt="" />
              </div>

              <div className="bp-pickcard-mid">
                <div className="bp-pickcard-title">{ag.name}</div>
                {!!ag.title && <div className="bp-pickcard-sub">{ag.title}</div>}
                {!!ag.email && <div className="bp-pickcard-sub small">{ag.email}</div>}
              </div>

              <div className="bp-pickcard-right">
                <div className={active ? 'bp-radio on' : 'bp-radio'} />
              </div>
            </button>
          );
        })}

        {!filtered.length ? <div className="bp-empty">No agents available for this service.</div> : null}
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
