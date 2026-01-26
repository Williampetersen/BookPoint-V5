import React, { useEffect, useState } from "react";
import StepsReorderModal from "../components/designer/StepsReorderModal";
import WizardPreview from "../components/designer/WizardPreview";
import WpMediaPicker from "../components/designer/WpMediaPicker";
import FieldsLayoutPanel from "../components/designer/FieldsLayoutPanel";

function wpApiFetch(path, opts = {}) {
  const admin = window.BP_ADMIN || window.bpAdmin || {};
  const url = admin.restUrl
    ? admin.restUrl.replace(/\/$/, "") + path
    : window.location.origin + "/wp-json" + path;

  const headers = { "Content-Type": "application/json", ...(opts.headers || {}) };
  const nonce = admin.nonce;
  if (nonce) headers["X-WP-Nonce"] = nonce;

  return fetch(url, {
    method: opts.method || "GET",
    credentials: "same-origin",
    headers,
    body: opts.body ? JSON.stringify(opts.body) : undefined,
  }).then(async (res) => {
    const text = await res.text();
    let json = null;
    try { json = text ? JSON.parse(text) : null; } catch (e) {}
    if (!res.ok) throw new Error(json?.message || `Request failed (${res.status})`);
    return json;
  });
}

export default function BookingFormDesignerScreen() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [config, setConfig] = useState(null);
  const [dirty, setDirty] = useState(false);
  const [fieldsAll, setFieldsAll] = useState(null);

  const [activeStepKey, setActiveStepKey] = useState("location");
  const [reorderOpen, setReorderOpen] = useState(false);

  useEffect(() => {
    let mounted = true;
    setLoading(true);
    wpApiFetch("/admin/booking-form-design")
      .then(async (data) => {
        if (!mounted) return;
        setConfig(data.config);
        const firstEnabled = data.config?.steps?.find((s) => s.enabled)?.key;
        setActiveStepKey(firstEnabled || data.config?.steps?.[0]?.key || "location");

        try {
          const fieldsRes = await wpApiFetch("/admin/form-fields/all");
          if (!mounted) return;
          setFieldsAll(fieldsRes?.data || fieldsRes?.fields || null);
        } catch (e) {
          if (!mounted) return;
          setFieldsAll(null);
        }
      })
      .catch((e) => mounted && setError(e.message))
      .finally(() => mounted && setLoading(false));
    return () => { mounted = false; };
  }, []);

  const steps = config?.steps || [];
  const activeStep = steps.find((s) => s.key === activeStepKey) || steps[0];

  const patchConfig = (patchFn) => {
    setConfig((prev) => patchFn(prev));
    setDirty(true);
  };

  const save = async () => {
    if (!config) return;
    setSaving(true);
    setError("");
    try {
      const data = await wpApiFetch("/admin/booking-form-design", {
        method: "POST",
        body: { config },
      });
      setConfig(data.config);
      setDirty(false);
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const setAppearance = (patch) => {
    patchConfig((prev) => ({
      ...prev,
      appearance: { ...(prev?.appearance || {}), ...patch },
    }));
  };

  const setTexts = (patch) => {
    patchConfig((prev) => ({
      ...prev,
      texts: { ...(prev?.texts || {}), ...patch },
    }));
  };

  const updateStep = (key, patch) => {
    patchConfig((prev) => ({
      ...prev,
      steps: (prev?.steps || []).map((s) => (s.key === key ? { ...s, ...patch } : s)),
    }));
  };

  if (loading) return <div className="bp-card bp-p-24">Loading...</div>;
  if (!config) return <div className="bp-card bp-p-24">No config. {error}</div>;

  return (
    <div className="bp-grid bp-grid-12 bp-gap-20">
      <div className="bp-col-8">
        <div className="bp-card bp-p-16">
          <div className="bp-flex bp-justify-between bp-items-center">
            <div>
              <div className="bp-text-lg bp-font-800">Booking Form Designer</div>
              <div className="bp-text-sm bp-muted">Visual editor for the shortcode wizard</div>
            </div>

            <div className="bp-flex bp-gap-8">
              <button
                className="bp-btn bp-btn-ghost"
                disabled={!dirty || saving}
                onClick={() => window.location.reload()}
              >
                Discard
              </button>
              <button
                className="bp-btn bp-btn-ghost"
                onClick={async () => {
                  if (!confirm("Reset design to defaults?")) return;
                  const res = await wpApiFetch("/admin/booking-form-design-reset", { method: "POST" });
                  setConfig(res.config);
                  setDirty(false);
                }}
              >
                Reset
              </button>
              <button
                className="bp-btn bp-btn-primary"
                disabled={!dirty || saving}
                onClick={save}
              >
                {saving ? "Saving..." : "Save Changes"}
              </button>
            </div>
          </div>

          {error ? <div className="bp-alert bp-alert-error bp-mt-12">{error}</div> : null}

          <div className="bp-mt-16">
            <WizardPreview config={config} activeStepKey={activeStepKey} />
          </div>
        </div>
      </div>

      <div className="bp-col-4">
        <div className="bp-card bp-p-16">
          <div className="bp-flex bp-justify-between bp-items-center">
            <div className="bp-font-800">Appearance</div>
            <div className="bp-chip">Live</div>
          </div>

          <div className="bp-mt-12">
            <label className="bp-label">Primary color</label>
            <input
              type="color"
              className="bp-color"
              value={config.appearance?.primaryColor || "#2563EB"}
              onChange={(e) => setAppearance({ primaryColor: e.target.value })}
            />
          </div>

          <div className="bp-mt-12">
            <label className="bp-label">Border style</label>
            <select
              className="bp-select"
              value={config.appearance?.borderStyle || "rounded"}
              onChange={(e) => setAppearance({ borderStyle: e.target.value })}
            >
              <option value="rounded">Rounded Corners</option>
              <option value="flat">Flat</option>
            </select>
          </div>

          <div className="bp-mt-12 bp-flex bp-items-center bp-justify-between">
            <div>
              <div className="bp-font-700">Dark mode default</div>
              <div className="bp-text-sm bp-muted">Wizard opens in dark mode</div>
            </div>
            <input
              type="checkbox"
              checked={!!config.appearance?.darkModeDefault}
              onChange={(e) => setAppearance({ darkModeDefault: e.target.checked })}
            />
          </div>
        </div>

        <div className="bp-card bp-p-16 bp-mt-14">
          <div className="bp-flex bp-justify-between bp-items-center">
            <div className="bp-font-800">Steps</div>
            <button className="bp-link" onClick={() => setReorderOpen(true)}>Change Order</button>
          </div>

          <div className="bp-mt-10">
            <select
              className="bp-select"
              value={activeStepKey}
              onChange={(e) => setActiveStepKey(e.target.value)}
            >
              {steps.map((s) => (
                <option key={s.key} value={s.key}>
                  {s.title || s.key}
                </option>
              ))}
            </select>
          </div>

          <div className="bp-mt-12 bp-flex bp-items-center bp-justify-between">
            <div className="bp-font-700">Enable this step</div>
            <input
              type="checkbox"
              checked={!!activeStep?.enabled}
              onChange={(e) => updateStep(activeStepKey, { enabled: e.target.checked })}
            />
          </div>

          <div className="bp-mt-12">
            <label className="bp-label">Step title</label>
            <input
              className="bp-input-field"
              value={activeStep?.title || ""}
              onChange={(e) => updateStep(activeStepKey, { title: e.target.value })}
            />
          </div>

          <div className="bp-mt-12">
            <label className="bp-label">Step subtitle</label>
            <textarea
              className="bp-textarea"
              value={activeStep?.subtitle || ""}
              onChange={(e) => updateStep(activeStepKey, { subtitle: e.target.value })}
            />
          </div>

          <WpMediaPicker
            label="Step image"
            valueId={activeStep?.imageId}
            valueUrl={activeStep?.imageUrl}
            onChange={({ imageId, imageUrl }) => updateStep(activeStepKey, { imageId, imageUrl })}
            help="This image appears on the left panel of the wizard."
          />

          <div className="bp-mt-12">
            <label className="bp-label">Back button label (this step)</label>
            <input
              className="bp-input-field"
              value={activeStep?.buttonBackLabel || ""}
              onChange={(e) => updateStep(activeStepKey, { buttonBackLabel: e.target.value })}
              placeholder="<- Back"
            />
          </div>

          <div className="bp-mt-12">
            <label className="bp-label">Next button label (this step)</label>
            <input
              className="bp-input-field"
              value={activeStep?.buttonNextLabel || ""}
              onChange={(e) => updateStep(activeStepKey, { buttonNextLabel: e.target.value })}
              placeholder="Next ->"
            />
          </div>

          <div className="bp-mt-12">
            <label className="bp-label">Step accent override (optional)</label>
            <div className="bp-flex bp-items-center bp-gap-10">
              <input
                type="color"
                className="bp-color"
                value={activeStep?.accentOverride || (config.appearance?.primaryColor || "#2563EB")}
                onChange={(e) => updateStep(activeStepKey, { accentOverride: e.target.value })}
              />
              <button
                className="bp-btn bp-btn-ghost"
                type="button"
                onClick={() => updateStep(activeStepKey, { accentOverride: "" })}
              >
                Use global color
              </button>
            </div>
            <div className="bp-text-xs bp-muted bp-mt-6">If set, this step uses its own highlight color.</div>
          </div>

          <div className="bp-mt-12 bp-flex bp-items-center bp-justify-between">
            <div>
              <div className="bp-font-700">Show left panel</div>
              <div className="bp-text-sm bp-muted">Left image + title panel</div>
            </div>
            <input
              type="checkbox"
              checked={activeStep?.showLeftPanel !== false}
              onChange={(e) => updateStep(activeStepKey, { showLeftPanel: e.target.checked })}
            />
          </div>

          <div className="bp-mt-12 bp-flex bp-items-center bp-justify-between">
            <div>
              <div className="bp-font-700">Show help box</div>
              <div className="bp-text-sm bp-muted">Help title + phone block</div>
            </div>
            <input
              type="checkbox"
              checked={activeStep?.showHelpBox !== false}
              onChange={(e) => updateStep(activeStepKey, { showHelpBox: e.target.checked })}
            />
          </div>
        </div>

        <div className="bp-card bp-p-16 bp-mt-14">
          <div className="bp-font-800">Texts</div>

          <div className="bp-mt-12">
            <label className="bp-label">Help title</label>
            <input
              className="bp-input-field"
              value={config.texts?.helpTitle || ""}
              onChange={(e) => setTexts({ helpTitle: e.target.value })}
            />
          </div>

          <div className="bp-mt-12">
            <label className="bp-label">Help phone</label>
            <input
              className="bp-input-field"
              value={config.texts?.helpPhone || ""}
              onChange={(e) => setTexts({ helpPhone: e.target.value })}
            />
          </div>

          <div className="bp-grid bp-grid-2 bp-gap-10 bp-mt-12">
            <div>
              <label className="bp-label">Back label</label>
              <input
                className="bp-input-field"
                value={config.texts?.backLabel || ""}
                onChange={(e) => setTexts({ backLabel: e.target.value })}
              />
            </div>
            <div>
              <label className="bp-label">Next label</label>
              <input
                className="bp-input-field"
                value={config.texts?.nextLabel || ""}
                onChange={(e) => setTexts({ nextLabel: e.target.value })}
              />
            </div>
          </div>
        </div>

        <div className="bp-card bp-p-16 bp-mt-14">
          {fieldsAll ? (
            <FieldsLayoutPanel
              fieldsByGroup={fieldsAll}
              value={config.fieldsLayout}
              onChange={(fieldsLayout) => {
                patchConfig((prev) => ({ ...prev, fieldsLayout }));
              }}
            />
          ) : (
            <div>
              <div className="bp-font-800">Fields Layout</div>
              <div className="bp-text-sm bp-muted">Unable to load fields list.</div>
            </div>
          )}
        </div>
      </div>

      <StepsReorderModal
        open={reorderOpen}
        onClose={() => setReorderOpen(false)}
        steps={steps}
        onChange={(newSteps) => {
          patchConfig((prev) => ({ ...prev, steps: newSteps }));
        }}
      />
    </div>
  );
}

