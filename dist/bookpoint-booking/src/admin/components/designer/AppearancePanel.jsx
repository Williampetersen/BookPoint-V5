import React from "react";

export default function AppearancePanel({ value, onChange }) {
  const appearance = value || {};

  function setField(key, val) {
    onChange({ ...appearance, [key]: val });
  }

  return (
    <div>
      <div style={{ fontWeight: 800, marginBottom: 8 }}>Appearance</div>

      <div style={{ marginBottom: 10 }}>
        <label className="bp-label">Theme</label>
        <select
          className="bp-input"
          value={appearance.theme || "light"}
          onChange={(e) => setField("theme", e.target.value)}
        >
          <option value="light">Light</option>
          <option value="dark">Dark</option>
        </select>
      </div>

      <div style={{ marginBottom: 10 }}>
        <label className="bp-label">Accent color</label>
        <input
          className="bp-input"
          type="text"
          value={appearance.accent || "#2563EB"}
          onChange={(e) => setField("accent", e.target.value)}
        />
      </div>

      <div style={{ marginBottom: 10 }}>
        <label className="bp-label">Border style</label>
        <select
          className="bp-input"
          value={appearance.borderStyle || "rounded"}
          onChange={(e) => setField("borderStyle", e.target.value)}
        >
          <option value="rounded">Rounded</option>
          <option value="flat">Flat</option>
        </select>
      </div>

      <div style={{ marginBottom: 10 }}>
        <label className="bp-label">Button style</label>
        <select
          className="bp-input"
          value={appearance.buttonStyle || "filled"}
          onChange={(e) => setField("buttonStyle", e.target.value)}
        >
          <option value="filled">Filled</option>
          <option value="outline">Outline</option>
        </select>
      </div>

      <div>
        <label className="bp-label">Font scale</label>
        <select
          className="bp-input"
          value={appearance.fontScale || "md"}
          onChange={(e) => setField("fontScale", e.target.value)}
        >
          <option value="sm">Small</option>
          <option value="md">Medium</option>
          <option value="lg">Large</option>
        </select>
      </div>
    </div>
  );
}
