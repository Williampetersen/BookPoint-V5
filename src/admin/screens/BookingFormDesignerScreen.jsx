import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";
import AppearancePanel from "../components/designer/AppearancePanel";
import StepEditorPanel from "../components/designer/StepEditorPanel";
import StepsReorderModal from "../components/designer/StepsReorderModal";
import WizardPreview from "../components/designer/WizardPreview";

export default function BookingFormDesignerScreen() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [design, setDesign] = useState(null);
  const [selectedStepKey, setSelectedStepKey] = useState("location");
  const [reorderOpen, setReorderOpen] = useState(false);
  const [dirty, setDirty] = useState(false);

  useEffect(() => {
    (async () => {
      setLoading(true);
      try {
        const data = await bpFetch("/admin/booking-form-design", { method: "GET" });
        setDesign(data);
        setSelectedStepKey(data?.steps?.[0]?.key || "location");
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  const selectedStep = useMemo(() => {
    if (!design?.steps) return null;
    return design.steps.find((s) => s.key === selectedStepKey) || design.steps[0];
  }, [design, selectedStepKey]);

  const onChange = (next) => {
    setDesign(next);
    setDirty(true);
  };

  const onSave = async () => {
    if (!design) return;
    setSaving(true);
    try {
      await bpFetch("/admin/booking-form-design", {
        method: "POST",
        body: design,
      });
      setDirty(false);
    } finally {
      setSaving(false);
    }
  };

  const onDiscard = async () => {
    setLoading(true);
    try {
      const data = await bpFetch("/admin/booking-form-design", { method: "GET" });
      setDesign(data);
      setDirty(false);
      setSelectedStepKey(data?.steps?.[0]?.key || "location");
    } finally {
      setLoading(false);
    }
  };

  if (loading || !design) {
    return (
      <div className="bp-card" style={{ padding: 24 }}>
        <div className="bp-h1">Booking Form Designer</div>
        <div className="bp-muted" style={{ marginTop: 8 }}>Loading...</div>
      </div>
    );
  }

  return (
    <div className="bp-grid" style={{ gridTemplateColumns: "repeat(12, minmax(0, 1fr))", gap: 16 }}>
      <div className="bp-col" style={{ gridColumn: "span 8" }}>
        <div className="bp-card" style={{ padding: 16 }}>
          <div className="bp-flex" style={{ alignItems: "center", justifyContent: "space-between", marginBottom: 12 }}>
            <div>
              <div className="bp-h1">Booking Form Designer</div>
              <div className="bp-muted">Visual editor for the shortcode wizard</div>
            </div>
            <div className="bp-flex" style={{ gap: 8 }}>
              <button className="bp-btn" onClick={onDiscard} disabled={!dirty || saving}>Discard</button>
              <button className="bp-btn bp-btn-primary" onClick={onSave} disabled={!dirty || saving}>
                {saving ? "Saving..." : "Save changes"}
              </button>
            </div>
          </div>

          <WizardPreview design={design} />
        </div>
      </div>

      <div className="bp-col" style={{ gridColumn: "span 4" }}>
        <div className="bp-card" style={{ padding: 16, position: "sticky", top: 12 }}>
          <AppearancePanel
            value={design.appearance}
            onChange={(appearance) => onChange({ ...design, appearance })}
          />

          <div className="bp-divider" style={{ margin: "16px 0" }} />

          <div className="bp-flex" style={{ alignItems: "center", justifyContent: "space-between", marginBottom: 8 }}>
            <div style={{ fontWeight: 800 }}>Steps</div>
            <button className="bp-btn-sm" onClick={() => setReorderOpen(true)}>Change Order</button>
          </div>

          <select
            className="bp-input"
            value={selectedStepKey}
            onChange={(e) => setSelectedStepKey(e.target.value)}
          >
            {design.steps.map((s) => (
              <option key={s.key} value={s.key}>
                {s.title || s.key}
              </option>
            ))}
          </select>

          <div style={{ marginTop: 12 }}>
            <StepEditorPanel
              step={selectedStep}
              design={design}
              onChange={onChange}
            />
          </div>
        </div>

        <StepsReorderModal
          open={reorderOpen}
          onClose={() => setReorderOpen(false)}
          steps={design.steps}
          onChange={(steps) => onChange({ ...design, steps })}
        />
      </div>
    </div>
  );
}
