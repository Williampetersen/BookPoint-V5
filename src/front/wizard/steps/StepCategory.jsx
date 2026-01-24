import React, { useMemo, useState } from 'react';

export default function StepCategory({ categories, value, onChange, onBack, onNext }) {
  const [q, setQ] = useState('');

  const filtered = useMemo(() => {
    const s = q.trim().toLowerCase();
    if (!s) return categories;
    return categories.filter((x) => (x.name || '').toLowerCase().includes(s));
  }, [q, categories]);

  function toggle(id) {
    const sid = String(id);
    const next = value.some((v) => String(v) === sid)
      ? value.filter((v) => String(v) !== sid)
      : [...value, id];
    onChange(next);
  }

  const canNext = value.length > 0;

  return (
    <div className="bp-step">
      <input
        value={q}
        onChange={(e) => setQ(e.target.value)}
        placeholder="Search..."
        className="bp-input"
      />

      <div className="bp-grid">
        {filtered.map((cat) => {
          const selected = value.some((v) => String(v) === String(cat.id));
          return (
            <button
              key={cat.id}
              type="button"
              className={selected ? 'bp-tile active' : 'bp-tile'}
              onClick={() => toggle(cat.id)}
            >
              <div className="bp-tile-title">{cat.name}</div>
            </button>
          );
        })}
        {!filtered.length ? <div className="bp-empty">No categories found.</div> : null}
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
