import React from "react";

export default function UpgradeToPro({ feature = "" }) {
  const pricingUrl = "https://wpbookpoint.com/pricing/";

  const subtitle = feature
    ? `${feature} is a BookPoint Pro feature. Install the Pro version to unlock it.`
    : "Locations, Service Extras, Promo Codes, Holidays, and Payments are Pro features. Install the Pro version to unlock them.";

  return (
    <div className="bp-content">
      <div className="bp-license">
        <div className="bp-card bp-license-proBanner is-invalid">
          <div className="bp-license-proBannerBody">
            <div className="bp-license-proDot is-invalid" aria-hidden="true" />
            <div style={{ minWidth: 0 }}>
              <div className="bp-section-title" style={{ margin: 0 }}>Upgrade to BookPoint Pro</div>
              <div className="bp-muted bp-text-xs" style={{ marginTop: 6 }}>
                {subtitle}
              </div>
            </div>
            <div className="bp-license-proBannerActions">
              <a className="bp-btn bp-btn-primary" href={pricingUrl} target="_blank" rel="noreferrer noopener">
                View plans & pricing
              </a>
            </div>
          </div>
        </div>

        <div className="bp-card">
          <div className="bp-card-head" style={{ padding: 14, borderBottom: "1px solid rgba(15,23,42,.06)" }}>
            <div>
              <div className="bp-section-title" style={{ margin: 0 }}>How to upgrade</div>
              <div className="bp-muted bp-text-xs">Install Pro, then activate your license key.</div>
            </div>
          </div>
          <div style={{ padding: 14, display: "grid", gap: 10 }}>
            <div className="bp-muted bp-text-xs">
              1) Purchase a plan from{" "}
              <a href={pricingUrl} target="_blank" rel="noreferrer noopener">wpbookpoint.com</a>.
            </div>
            <div className="bp-muted bp-text-xs">
              2) Download the BookPoint Pro ZIP from your account/download link.
            </div>
            <div className="bp-muted bp-text-xs">
              3) In WordPress: Plugins -> Add New -> Upload Plugin -> choose the ZIP -> Install Now -> Activate.
            </div>
            <div className="bp-muted bp-text-xs">
              4) Come back to Settings -> License to paste and activate your license key.
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
