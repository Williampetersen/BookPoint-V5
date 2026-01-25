import React from "react";

export default function StepEditorPanel({ step, design, onChange }) {
  if (!step) return null;

  function updateStep(patch) {
    const nextSteps = (design.steps || []).map((s) => (
      s.key === step.key ? { ...s, ...patch } : s
    ));
    onChange({ ...design, steps: nextSteps });
  }

  return (
    <div>
      <div style={{ fontWeight: 800, marginBottom: 8 }}>Step Settings</div>

      <label className="bp-label">Enabled</label>
      <label className="bp-checkbox" style={{ marginBottom: 10 }}>
        <input
          type="checkbox"
          checked={!!step.enabled}
          onChange={(e) => updateStep({ enabled: e.target.checked })}
        />
        <span>Show this step</span>
      </label>

      <label className="bp-label">Title</label>
      <input
        className="bp-input"
        value={step.title || ""}
        onChange={(e) => updateStep({ title: e.target.value })}
      />

      <label className="bp-label" style={{ marginTop: 10 }}>Subtitle</label>
      <input
        className="bp-input"
        value={step.subtitle || ""}
        onChange={(e) => updateStep({ subtitle: e.target.value })}
      />

      <label className="bp-label" style={{ marginTop: 10 }}>Image / Icon</label>
      <input
        className="bp-input"
        value={step.image || ""}
        onChange={(e) => updateStep({ image: e.target.value })}
        placeholder="location-image.png"
      />

      {step.key === "service" && (
        <div style={{ marginTop: 12 }}>
          <div style={{ fontWeight: 800, marginBottom: 6 }}>Service Options</div>
          <label className="bp-checkbox" style={{ marginBottom: 6 }}>
            <input
              type="checkbox"
              checked={!!step.options?.showServiceCategories}
              onChange={(e) => updateStep({
                options: { ...step.options, showServiceCategories: e.target.checked },
              })}
            />
            <span>Show service categories</span>
          </label>
          <label className="bp-checkbox">
            <input
              type="checkbox"
              checked={!!step.options?.showServiceCount}
              onChange={(e) => updateStep({
                options: { ...step.options, showServiceCount: e.target.checked },
              })}
            />
            <span>Show service count</span>
          </label>
        </div>
      )}

      {step.key === "datetime" && (
        <div style={{ marginTop: 12 }}>
          <div style={{ fontWeight: 800, marginBottom: 6 }}>Date & Time Options</div>
          <label className="bp-label">Time slot style</label>
          <select
            className="bp-input"
            value={step.options?.timeSlotsAs || "boxes"}
            onChange={(e) => updateStep({
              options: { ...step.options, timeSlotsAs: e.target.value },
            })}
          >
            <option value="boxes">Boxes</option>
            <option value="list">List</option>
          </select>

          <label className="bp-label" style={{ marginTop: 10 }}>Time format</label>
          <input
            className="bp-input"
            value={step.options?.timeFormat || "HH:mm"}
            onChange={(e) => updateStep({
              options: { ...step.options, timeFormat: e.target.value },
            })}
          />

          <label className="bp-checkbox" style={{ marginTop: 8 }}>
            <input
              type="checkbox"
              checked={!!step.options?.hideUnavailable}
              onChange={(e) => updateStep({
                options: { ...step.options, hideUnavailable: e.target.checked },
              })}
            />
            <span>Hide unavailable slots</span>
          </label>

          <label className="bp-checkbox" style={{ marginTop: 6 }}>
            <input
              type="checkbox"
              checked={!!step.options?.disableAutoFirstSlot}
              onChange={(e) => updateStep({
                options: { ...step.options, disableAutoFirstSlot: e.target.checked },
              })}
            />
            <span>Disable auto-select first slot</span>
          </label>
        </div>
      )}
    </div>
  );
}
