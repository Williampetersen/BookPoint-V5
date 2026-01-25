import React, { useMemo } from "react";

export default function WizardPreview({ design }) {
  const appearance = design?.appearance || {};
  const layout = design?.layout || {};
  const accent = appearance.accent || "#2563EB";

  const steps = useMemo(() => {
    return (design?.steps || []).filter((s) => s.enabled !== false);
  }, [design]);

  const activeStep = steps[0] || { title: "Step Title", subtitle: "Step subtitle", image: "" };
  const borderRadius = appearance.borderStyle === "flat" ? 6 : 16;

  const showSummary = layout.showSummary === true || layout.showSummary === "auto";
  const columns = [
    ...(layout.leftPanel ? ["220px"] : []),
    "1fr",
    ...(showSummary ? ["220px"] : []),
  ].join(" ");

  return (
    <div
      className="bp-card"
      style={{
        borderRadius,
        padding: 14,
        background: "#f9fafb",
        border: "1px solid #eef2f7",
      }}
    >
      <div style={{ fontWeight: 800, marginBottom: 10 }}>Preview</div>

      <div
        style={{
          display: "grid",
          gridTemplateColumns: columns,
          gap: 12,
        }}
      >
        {layout.leftPanel ? (
          <div
            style={{
              background: "#fff",
              border: "1px solid #eef2f7",
              borderRadius,
              padding: 12,
              minHeight: 180,
              display: "flex",
              flexDirection: "column",
              gap: 8,
            }}
          >
            <div
              style={{
                width: 64,
                height: 64,
                borderRadius: 12,
                background: "#eef2ff",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                fontSize: 11,
                color: "#6b7280",
                textAlign: "center",
                padding: 4,
              }}
            >
              {activeStep.image ? activeStep.image : "icon"}
            </div>
            <div style={{ fontWeight: 800 }}>{activeStep.title || "Step title"}</div>
            <div style={{ color: "#6b7280", fontSize: 12 }}>
              {activeStep.subtitle || "Step subtitle text"}
            </div>
            {layout.helpPhone ? (
              <div style={{ marginTop: "auto", fontSize: 12, color: "#6b7280" }}>
                Help: <strong style={{ color: "#111827" }}>{layout.helpPhone}</strong>
              </div>
            ) : null}
          </div>
        ) : null}

        <div
          style={{
            background: "#fff",
            border: "1px solid #eef2f7",
            borderRadius,
            padding: 12,
            minHeight: 180,
            display: "flex",
            flexDirection: "column",
            gap: 10,
          }}
        >
          <div style={{ fontWeight: 800 }}>{activeStep.title || "Step title"}</div>
          <div style={{ color: "#6b7280", fontSize: 12 }}>
            {activeStep.subtitle || "This text comes from the designer settings."}
          </div>

          <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
            {steps.slice(0, 9).map((s) => (
              <span
                key={s.key}
                style={{
                  padding: "4px 8px",
                  borderRadius: 999,
                  fontSize: 11,
                  border: "1px solid #eef2f7",
                  background: s.key === activeStep.key ? accent : "#fff",
                  color: s.key === activeStep.key ? "#fff" : "#374151",
                  fontWeight: 700,
                }}
              >
                {s.title || s.key}
              </span>
            ))}
          </div>

          <div style={{ marginTop: "auto", display: "flex", justifyContent: "space-between" }}>
            <button className="bp-btn">Back</button>
            <button
              className="bp-btn"
              style={{ background: accent, color: "#fff", borderColor: accent }}
            >
              Next
            </button>
          </div>
        </div>

        {showSummary ? (
          <div
            style={{
              background: "#fff",
              border: "1px solid #eef2f7",
              borderRadius,
              padding: 12,
              minHeight: 180,
            }}
          >
            <div style={{ fontWeight: 800, marginBottom: 8 }}>Summary</div>
            {["Location", "Category", "Service", "Agent", "Date", "Time"].map((k) => (
              <div
                key={k}
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  padding: "6px 0",
                  borderBottom: "1px solid #f3f4f6",
                  fontSize: 12,
                }}
              >
                <span style={{ color: "#6b7280" }}>{k}</span>
                <span style={{ fontWeight: 700 }}>—</span>
              </div>
            ))}
          </div>
        ) : null}
      </div>
    </div>
  );
}
