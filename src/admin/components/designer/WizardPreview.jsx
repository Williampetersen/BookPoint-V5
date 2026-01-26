import React from "react";

function imgUrl(filename) {
  const base =
    window.BP_ADMIN?.publicImagesUrl ||
    window.BP_ADMIN?.pluginUrl?.replace(/\/$/, "") + "/public/images" ||
    "/wp-content/plugins/bookpoint-v5/public/images";
  return `${base}/${filename}`;
}

export default function WizardPreview({ config, activeStepKey }) {
  const steps = config?.steps || [];
  const step =
    steps.find((s) => s.key === activeStepKey) ||
    steps.find((s) => s.enabled) ||
    steps[0];

  const globalPrimary = config?.appearance?.primaryColor || "#2563EB";
  const stepPrimary = step?.accentOverride ? step.accentOverride : globalPrimary;
  const rounded = (config?.appearance?.borderStyle || "rounded") === "rounded";

  const helpTitle = config?.texts?.helpTitle || "Need help?";
  const helpPhone = config?.texts?.helpPhone || "";
  const nextLabel = step?.buttonNextLabel || config?.texts?.nextLabel || "Next ->";
  const backLabel = step?.buttonBackLabel || config?.texts?.backLabel || "<- Back";

  const showLeft = step?.showLeftPanel !== false;
  const showHelp = step?.showHelpBox !== false;

  const fallback = imgUrl(step?.image || "location-image.png");
  const src = step?.imageUrl || fallback;

  return (
    <div className="bp-preview-wrap">
      <div className={`bp-preview ${rounded ? "bp-rounded" : "bp-flat"}`}>
        {showLeft && (
          <div className="bp-preview-left">
            <div className="bp-preview-icon">
              <img
                src={src}
                alt=""
                onError={(e) => { e.currentTarget.src = fallback; }}
              />
            </div>

            <div className="bp-preview-title">{step?.title || "Step Title"}</div>
            <div className="bp-preview-sub">{step?.subtitle || "Step description goes here..."}</div>

            {showHelp && (
              <div className="bp-preview-help">
                <div className="bp-preview-help-title">{helpTitle}</div>
                <div className="bp-preview-help-phone">{helpPhone}</div>
              </div>
            )}
          </div>
        )}

        <div className="bp-preview-right">
          <div className="bp-preview-head">
            <div className="bp-preview-head-title">{step?.title || ""}</div>
          </div>

          <div className="bp-preview-content">
            <div className="bp-input" />
            <div className="bp-card-lite bp-p-12 bp-mt-10">
              <div className="bp-line bp-w-60" />
              <div className="bp-line bp-w-30 bp-mt-8" />
            </div>
            <div className="bp-card-lite bp-p-12 bp-mt-10">
              <div className="bp-line bp-w-40" />
              <div className="bp-line bp-w-80 bp-mt-8" />
            </div>
          </div>

          <div className="bp-preview-footer">
            <button className="bp-btn bp-btn-ghost">{backLabel}</button>
            <button className="bp-btn bp-btn-primary" style={{ background: stepPrimary, borderColor: stepPrimary }}>
              {nextLabel}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
