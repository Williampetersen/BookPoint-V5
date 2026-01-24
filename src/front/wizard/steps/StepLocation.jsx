import React, { useMemo, useState } from 'react';

export default function StepLocation({ locations, value, onChange, onNext }) {
  const [q, setQ] = useState('');
  const filtered = useMemo(() => {
    const s = q.trim().toLowerCase();
    if (!s) return locations;
    return locations.filter((x) => (x.name || '').toLowerCase().includes(s));
  }, [q, locations]);

  const canNext = !!value;

  return (
    <div className="bp-step">
      <div className="bp-step-search">
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Search..."
          className="bp-input"
        />
      </div>

      <div className="bp-list">
        {filtered.map((loc) => (
          <button
            key={loc.id}
            type="button"
            className={String(value) === String(loc.id) ? 'bp-card active' : 'bp-card'}
            onClick={() => onChange(loc.id)}
          >
            <div className="bp-card-title">{loc.name}</div>
            <div className="bp-card-sub">{loc.address || ''}</div>
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
