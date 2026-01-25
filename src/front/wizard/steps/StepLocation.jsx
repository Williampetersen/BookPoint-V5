import React from 'react';
import { imgOf } from '../ui';

export default function StepLocation({ locations, value, onChange, onNext }) {
  const filtered = locations || [];

  const canNext = !!value;

  return (
    <div className="bp-step">
      <div className="bp-list">
        {filtered.map((loc) => (
          <button
            key={loc.id}
            type="button"
            className={String(value) === String(loc.id) ? 'bp-card active' : 'bp-card'}
            onClick={() => onChange(loc.id)}
          >
            <div className="bp-card-row">
              <img className="bp-card-thumb" src={imgOf(loc, 'location-image.png')} alt="" />
              <div>
                <div className="bp-card-title">{loc.name}</div>
                <div className="bp-card-sub">{loc.address || ''}</div>
              </div>
            </div>
          </button>
        ))}
        {!filtered.length ? <div className="bp-empty">No locations found.</div> : null}
      </div>

      <div className="bp-step-footer">
        <div />
        <button
          type="button"
          className="bp-next"
          disabled={!canNext}
          onClick={() => onNext()}
        >
          Next ->
        </button>
      </div>
    </div>
  );
}
