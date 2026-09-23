import React from 'react';
import { imgOf } from '../ui';

export default function StepLocation({ locations, value, onChange, onNext, nextLabel = 'Next ->', loading = false }) {
  const filtered = locations || [];

  const canNext = !!value;

  return (
    <div className="bp-step">
      <div className="bp-list" role="radiogroup" aria-label="Location">
        {loading ? (
          <>
            <div className="bp-skel" />
            <div className="bp-skel" />
            <div className="bp-skel" />
          </>
        ) : (
          <>
            {filtered.map((loc) => {
              const selected = String(value) === String(loc.id);
              return (
                <button
                  key={loc.id}
                  type="button"
                  role="radio"
                  aria-checked={selected}
                  className={selected ? 'bp-card active' : 'bp-card'}
                  onClick={() => onChange(loc.id)}
                >
                  <div className="bp-card-row">
                    <img
                      className="bp-card-thumb"
                      src={imgOf(loc, 'location-image.png')}
                      alt=""
                      onError={(e) => { e.currentTarget.src = imgOf({}, 'location-image.png'); }}
                    />
                    <div>
                      <div className="bp-card-title">{loc.name}</div>
                      <div className="bp-card-sub">{loc.address || ''}</div>
                    </div>
                  </div>
                </button>
              );
            })}
            {!filtered.length ? <div className="bp-empty">No locations found.</div> : null}
          </>
        )}
      </div>

      <div className="bp-step-footer">
        <div />
        <button
          type="button"
          className="bp-next"
          disabled={!canNext}
          onClick={() => onNext()}
        >
          {nextLabel}
        </button>
      </div>
    </div>
  );
}
