import React from 'react';

export default function StepDateTime({ date, onDateChange, slots, value, onSlotChange, onBack, onNext }) {
  const canNext = !!date && !!value;

  return (
    <div className="bp-step">
      <div className="bp-field">
        <label className="bp-label">Date</label>
        <input
          type="date"
          className="bp-input"
          value={date || ''}
          onChange={(e) => onDateChange(e.target.value)}
        />
      </div>

      <div className="bp-field">
        <label className="bp-label">Time slots</label>
        <div className="bp-slot-grid">
          {slots.map((s) => {
            const active = value?.start_time === s.start_time;
            return (
              <button
                key={s.start_time}
                type="button"
                className={active ? 'bp-slot-btn active' : 'bp-slot-btn'}
                onClick={() => onSlotChange(s)}
              >
                {s.label || s.start_time}
              </button>
            );
          })}
          {!slots.length ? <div className="bp-empty">No available time slots.</div> : null}
        </div>
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
