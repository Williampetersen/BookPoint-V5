import React, { useState } from "react";

export default function StepsReorderModal({ open, onClose, steps, onChange }) {
  const [local, setLocal] = useState(steps || []);
  const [dragIndex, setDragIndex] = useState(null);

  React.useEffect(() => {
    if (open) setLocal(steps || []);
  }, [open, steps]);

  const move = (from, to) => {
    if (from === to) return;
    const copy = [...local];
    const [item] = copy.splice(from, 1);
    copy.splice(to, 0, item);
    setLocal(copy);
  };

  if (!open) return null;

  return (
    <div className="bp-modal-overlay">
      <div className="bp-modal bp-card bp-p-16 bp-w-720">
        <div className="bp-flex bp-justify-between bp-items-center">
          <div>
            <div className="bp-text-lg bp-font-700">Change Order</div>
            <div className="bp-text-sm bp-muted">Drag & drop steps</div>
          </div>
          <button className="bp-btn bp-btn-ghost" onClick={onClose}>Close</button>
        </div>

        <div className="bp-mt-14 bp-list">
          {local.map((s, idx) => (
            <div
              key={s.key}
              className="bp-reorder-item"
              draggable
              onDragStart={() => setDragIndex(idx)}
              onDragOver={(e) => e.preventDefault()}
              onDrop={() => {
                if (dragIndex === null) return;
                move(dragIndex, idx);
                setDragIndex(null);
              }}
            >
              <div className="bp-reorder-grip">⋮⋮</div>
              <div className="bp-flex bp-flex-col">
                <div className="bp-font-700">{s.title || s.key}</div>
                <div className="bp-text-sm bp-muted">{s.subtitle || ""}</div>
              </div>
              <div className="bp-chip">{s.enabled ? "Enabled" : "Disabled"}</div>
            </div>
          ))}
        </div>

        <div className="bp-flex bp-justify-end bp-gap-8 bp-mt-16">
          <button className="bp-btn bp-btn-ghost" onClick={onClose}>Cancel</button>
          <button
            className="bp-btn bp-btn-primary"
            onClick={() => {
              onChange(local);
              onClose();
            }}
          >
            Save Order
          </button>
        </div>
      </div>
    </div>
  );
}
