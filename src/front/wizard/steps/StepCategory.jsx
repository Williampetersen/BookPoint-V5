import React from 'react';
import { imgOf } from '../ui';

export default function StepCategory({ categories, value, onChange, onBack, onNext, backLabel = '<- Back', nextLabel = 'Next ->', loading = false }) {
  const filtered = categories || [];

  function selectOne(id) {
    onChange([id]);
  }

  const canNext = (value || []).length > 0;

  return (
    <div className="bp-step">
      <div className="bp-list" role="radiogroup" aria-label="Category">
        {loading ? (
          <>
            <div className="bp-skel" />
            <div className="bp-skel" />
            <div className="bp-skel" />
          </>
        ) : (
          <>
            {filtered.map((cat) => {
              const selected = (value || []).some((v) => String(v) === String(cat.id));
              return (
                <button
                  key={cat.id}
                  type="button"
                  role="radio"
                  aria-checked={selected}
                  className={selected ? 'bp-card active' : 'bp-card'}
                  onClick={() => selectOne(cat.id)}
                >
                  <div className="bp-card-row">
                    <img
                      className="bp-card-thumb"
                      src={imgOf(cat, 'service-image.png')}
                      alt=""
                      onError={(e) => { e.currentTarget.src = imgOf({}, 'service-image.png'); }}
                    />
                    <div className="bp-card-title">{cat.name}</div>
                  </div>
                </button>
              );
            })}
            {!filtered.length ? <div className="bp-empty">No categories found.</div> : null}
          </>
        )}
      </div>

      <div className="bp-step-footer">
        <button type="button" className="bp-back" onClick={onBack}>{backLabel}</button>
        <button type="button" className="bp-next" disabled={!canNext} onClick={onNext}>
          {nextLabel}
        </button>
      </div>
    </div>
  );
}
