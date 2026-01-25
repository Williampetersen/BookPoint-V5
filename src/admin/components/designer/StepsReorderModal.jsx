import React from "react";

export default function StepsReorderModal({ open, onClose, steps, onChange }) {
  if (!open) return null;

  function move(idx, dir) {
    const next = [...steps];
    const target = idx + dir;
    if (target < 0 || target >= next.length) return;
    const tmp = next[idx];
    next[idx] = next[target];
    next[target] = tmp;
    onChange(next);
  }

  return (
    <div className="bp-modal-overlay" style={{ zIndex: 1000000 }}>
      <div className="bp-modal" style={{ width: 520, height: "auto" }}>
        <div style={{ padding: 18 }}>
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
            <div style={{ fontWeight: 900, fontSize: 18 }}>Reorder Steps</div>
            <button className="bp-btn" onClick={onClose}>Close</button>
          </div>

          <div style={{ marginTop: 12, display: "flex", flexDirection: "column", gap: 8 }}>
            {steps.map((s, idx) => (
              <div key={s.key} style={{ display: "flex", alignItems: "center", gap: 8, border: "1px solid #e5e7eb", borderRadius: 12, padding: "10px 12px" }}>
                <div style={{ fontWeight: 800 }}>{s.title || s.key}</div>
                <div style={{ marginLeft: "auto", display: "flex", gap: 6 }}>
                  <button className="bp-btn-sm" onClick={() => move(idx, -1)}>Up</button>
                  <button className="bp-btn-sm" onClick={() => move(idx, 1)}>Down</button>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
