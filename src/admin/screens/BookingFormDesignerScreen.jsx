import React, { useEffect, useMemo, useRef, useState } from "react";
import StepsReorderModal from "../components/designer/StepsReorderModal";
import WizardPreview from "../components/designer/WizardPreview";
import WpMediaPicker from "../components/designer/WpMediaPicker";
import FieldsLayoutPanel from "../components/designer/FieldsLayoutPanel";

const DESIGN_STEP_ORDER = [
  "location",
  "category",
  "service",
  "extras",
  "agents",
  "datetime",
  "customer",
  "payment",
  "review",
  "confirm",
];

const REQUIRED_STEP_KEYS = new Set([
  "service",
  "agents",
  "datetime",
  "customer",
  "review",
  "confirm",
]);

const STEP_DEFAULTS = {
  payment: {
    key: "payment",
    enabled: true,
    title: "Payment",
    subtitle: "Choose a payment method",
    image: "service-image.png",
    buttonBackLabel: "<- Back",
    buttonNextLabel: "Next ->",
    accentOverride: "",
    showLeftPanel: true,
    showHelpBox: true,
  },
};

function normalizeStepKey(key) {
  if (key === "agent") return "agents";
  if (key === "done") return "confirm";
  return key;
}

function normalizeDesignSteps(steps = []) {
  const map = new Map();
  (steps || []).forEach((s) => {
    const k = normalizeStepKey(s?.key);
    if (!k) return;
    if (!map.has(k)) map.set(k, { ...s, key: k });
  });

  if (!map.has("payment")) {
    map.set("payment", { ...STEP_DEFAULTS.payment });
  }

  // Some steps are required for creating a booking; keep them enabled.
  for (const k of REQUIRED_STEP_KEYS) {
    if (map.has(k)) {
      const step = map.get(k);
      map.set(k, { ...step, enabled: true });
    }
  }

  return DESIGN_STEP_ORDER.filter((k) => map.has(k)).map((k) => map.get(k));
}

function canonicalizeConfig(raw) {
  const cfg = raw && typeof raw === "object" ? raw : {};
  const normalizedSteps = normalizeDesignSteps(cfg.steps || []);
  return { ...cfg, steps: normalizedSteps };
}

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
    try {
      json = text ? JSON.parse(text) : null;
    } catch (e) {
      // ignore
    }
    if (!res.ok) throw new Error(json?.message || `Request failed (${res.status})`);
    return json;
  });
}

function safeJsonParse(raw) {
  try {
    return JSON.parse(String(raw || ""));
  } catch {
    return null;
  }
}

function downloadText(filename, text, type = "application/json;charset=utf-8") {
  const blob = new Blob([text], { type });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

export default function BookingFormDesignerScreen() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [toast, setToast] = useState("");

  const [config, setConfig] = useState(null);
  const [dirty, setDirty] = useState(false);
  const [fieldsAll, setFieldsAll] = useState(null);

  const [activeStepKey, setActiveStepKey] = useState("location");
  const [reorderOpen, setReorderOpen] = useState(false);
  const [device, setDevice] = useState("desktop"); // desktop|mobile

  const serverConfigRef = useRef(null);
  const draftTimerRef = useRef(null);

  const [draftInfo, setDraftInfo] = useState(null); // { savedAt, config }
  const [draftPrompt, setDraftPrompt] = useState(false);

  const storageKey = useMemo(() => {
    const host = window.location.hostname || "site";
    return `bp_design_form_draft_${host}`;
  }, []);

  const showToast = (msg) => {
    setToast(msg);
    window.setTimeout(() => setToast(""), 2500);
  };

  useEffect(() => {
    let mounted = true;

    async function load() {
      setLoading(true);
      setError("");

      try {
        const data = await wpApiFetch("/admin/booking-form-design");
        if (!mounted) return;

        const nextConfig = canonicalizeConfig(data.config || {});
        setConfig(nextConfig);
        serverConfigRef.current = nextConfig;

        const firstEnabled = (nextConfig.steps || []).find((s) => s.enabled)?.key;
        setActiveStepKey(firstEnabled || nextConfig.steps?.[0]?.key || "location");

        // Draft prompt (local-only)
        try {
          const raw = window.localStorage.getItem(storageKey);
          const parsed = safeJsonParse(raw);
          if (parsed && parsed.config) {
            const same = JSON.stringify(parsed.config) === JSON.stringify(nextConfig);
            setDraftInfo({ savedAt: parsed.savedAt || 0, config: parsed.config });
            setDraftPrompt(!same);
          } else {
            setDraftInfo(null);
            setDraftPrompt(false);
          }
        } catch (_) {
          setDraftInfo(null);
          setDraftPrompt(false);
        }

        try {
          const fieldsRes = await wpApiFetch("/admin/form-fields/all");
          if (!mounted) return;
          setFieldsAll(fieldsRes?.data || fieldsRes?.fields || null);
        } catch (e) {
          if (!mounted) return;
          setFieldsAll(null);
        }
      } catch (e) {
        if (!mounted) return;
        setError(e.message || "Failed to load");
      } finally {
        if (!mounted) return;
        setLoading(false);
      }
    }

    load();
    return () => {
      mounted = false;
    };
  }, [storageKey]);

  const steps = config?.steps || [];
  const activeStep = steps.find((s) => s.key === activeStepKey) || steps[0] || null;

  const patchConfig = (patchFn) => {
    setConfig((prev) => patchFn(prev));
    setDirty(true);
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

  const resetToServer = () => {
    const base = serverConfigRef.current;
    if (!base) {
      window.location.reload();
      return;
    }
    setConfig(base);
    const firstEnabled = (base.steps || []).find((s) => s.enabled)?.key;
    setActiveStepKey(firstEnabled || base.steps?.[0]?.key || "location");
    setDirty(false);
    setError("");
    try {
      window.localStorage.removeItem(storageKey);
    } catch (_) {
      // ignore
    }
    setDraftInfo(null);
    setDraftPrompt(false);
    showToast("Discarded local changes.");
  };

  const resetStepToServer = (key) => {
    const base = serverConfigRef.current;
    if (!base || !(base.steps || []).length) return;
    const src = (base.steps || []).find((s) => s.key === key);
    if (!src) return;
    patchConfig((prev) => ({
      ...prev,
      steps: (prev?.steps || []).map((s) => (s.key === key ? { ...src } : s)),
    }));
    showToast("Step reset.");
  };

  const publish = async () => {
    if (!config) return;
    setSaving(true);
    setError("");
    try {
      const data = await wpApiFetch("/admin/booking-form-design", {
        method: "POST",
        body: { config },
      });

      const nextConfig = canonicalizeConfig(data.config || {});
      setConfig(nextConfig);
      serverConfigRef.current = nextConfig;
      setDirty(false);

      try {
        window.localStorage.removeItem(storageKey);
      } catch (_) {
        // ignore
      }
      setDraftInfo(null);
      setDraftPrompt(false);
      showToast("Published.");
    } catch (e) {
      setError(e.message || "Publish failed");
    } finally {
      setSaving(false);
    }
  };

  // Autosave draft locally while editing (does not publish to server).
  useEffect(() => {
    if (!dirty || !config) return;
    if (draftTimerRef.current) window.clearTimeout(draftTimerRef.current);

    draftTimerRef.current = window.setTimeout(() => {
      try {
        const payload = { savedAt: Date.now(), config };
        window.localStorage.setItem(storageKey, JSON.stringify(payload));
        setDraftInfo(payload);
      } catch (_) {
        // ignore
      }
    }, 800);

    return () => {
      if (draftTimerRef.current) window.clearTimeout(draftTimerRef.current);
    };
  }, [dirty, config, storageKey]);

  if (loading) return <div className="bp-card bp-p-24">Loading...</div>;
  if (!config) return <div className="bp-card bp-p-24">No config. {error}</div>;

  return (
    <div className="myplugin-page bp-design-form">
      <main className="myplugin-content">
        <div className="bp-designer">
          {toast ? <div className="bp-toast bp-toast-success">{toast}</div> : null}

      <div className="bp-designer-head">
        <div>
          <div className="bp-h1">Booking Form Designer</div>
          <div className="bp-muted">Build the booking wizard step-by-step. Drafts auto-save locally until you publish.</div>
        </div>

        <div className="bp-designer-headActions">
          <label className="bp-btn">
            Import JSON
            <input
              type="file"
              accept="application/json"
              style={{ display: "none" }}
              onChange={(e) => {
                const f = e.target.files && e.target.files[0];
                if (!f) return;
                const reader = new FileReader();
                reader.onload = () => {
                  const parsed = safeJsonParse(reader.result);
                  if (!parsed || typeof parsed !== "object") {
                    setError("Invalid JSON file.");
                    return;
                  }
                  const nextConfig = canonicalizeConfig(parsed);
                  setConfig(nextConfig);
                  setDirty(true);
                  setError("");
                  showToast("Imported (not published). ");
                };
                reader.readAsText(f);
              }}
            />
          </label>

          <button
            type="button"
            className="bp-btn"
            onClick={() => {
              downloadText(
                `bookpoint-form-design-${new Date().toISOString().slice(0, 10)}.json`,
                JSON.stringify(config, null, 2)
              );
            }}
          >
            Export JSON
          </button>

          <button type="button" className="bp-btn" disabled={!dirty || saving} onClick={resetToServer}>
            Discard
          </button>

          <button
            type="button"
            className="bp-btn"
            onClick={async () => {
              if (!confirm("Reset design to defaults?")) return;
              const res = await wpApiFetch("/admin/booking-form-design-reset", { method: "POST" });
              const nextConfig = canonicalizeConfig(res.config || {});
              setConfig(nextConfig);
              serverConfigRef.current = nextConfig;
              setDirty(false);
              try {
                window.localStorage.removeItem(storageKey);
              } catch (_) {
                // ignore
              }
              setDraftInfo(null);
              setDraftPrompt(false);
              showToast("Reset to defaults.");
            }}
          >
            Reset
          </button>

          <button type="button" className="bp-btn bp-btn-primary" disabled={!dirty || saving} onClick={publish}>
            {saving ? "Publishing..." : "Publish"}
          </button>
        </div>
      </div>

      {draftPrompt && draftInfo ? (
        <div className="bp-alert bp-alert-warn bp-designer-draft">
          <div style={{ minWidth: 0 }}>
            Draft found from {draftInfo.savedAt ? new Date(draftInfo.savedAt).toLocaleString() : "earlier"}.
          </div>
          <div className="bp-designer-draftActions">
            <button
              type="button"
              className="bp-btn"
              onClick={() => {
                const nextConfig = canonicalizeConfig(draftInfo.config || {});
                setConfig(nextConfig);
                const firstEnabled = (nextConfig.steps || []).find((s) => s.enabled)?.key;
                setActiveStepKey(firstEnabled || nextConfig.steps?.[0]?.key || "location");
                setDirty(true);
                setDraftPrompt(false);
                showToast("Draft restored (not published). ");
              }}
            >
              Restore draft
            </button>
            <button
              type="button"
              className="bp-btn bp-btn-ghost"
              onClick={() => {
                try {
                  window.localStorage.removeItem(storageKey);
                } catch (_) {
                  // ignore
                }
                setDraftPrompt(false);
                setDraftInfo(null);
                showToast("Draft removed.");
              }}
            >
              Discard draft
            </button>
          </div>
        </div>
      ) : null}

      {error ? <div className="bp-alert bp-alert-error">{error}</div> : null}

      <div className="bp-designer-layout">
        <div className="bp-designer-steps">
          <div className="bp-card bp-designer-panel">
            <div className="bp-designer-panelHead">
              <div className="bp-section-title" style={{ margin: 0 }}>Steps</div>
              <button className="bp-btn bp-btn-ghost" type="button" onClick={() => setReorderOpen(true)}>
                Reorder
              </button>
            </div>
            <div className="bp-designer-stepsList">
              {(steps || []).map((s) => {
                const active = s.key === activeStepKey;
                const required = REQUIRED_STEP_KEYS.has(s.key);
                const checked = required ? true : !!s.enabled;
                return (
                  <button
                    key={s.key}
                    type="button"
                    className={`bp-designer-step ${active ? "is-active" : ""}`}
                    onClick={() => setActiveStepKey(s.key)}
                  >
                    <div className="bp-designer-stepMain">
                      <div className="bp-designer-stepTitle">{s.title || s.key}</div>
                      <div className="bp-muted bp-text-xs">{s.subtitle || s.key}</div>
                    </div>
                    <label className="bp-designer-stepToggle" onClick={(e) => e.stopPropagation()}>
                      <input
                        type="checkbox"
                        checked={checked}
                        disabled={required}
                        onChange={(e) => updateStep(s.key, { enabled: e.target.checked })}
                      />
                      <span className="bp-muted bp-text-xs">{checked ? "On" : "Off"}</span>
                    </label>
                  </button>
                );
              })}
            </div>
          </div>
        </div>

        <div className="bp-designer-preview">
          <div className="bp-card bp-designer-panel">
            <div className="bp-designer-panelHead">
              <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap" }}>
                <div className="bp-section-title" style={{ margin: 0 }}>Preview</div>
                {draftInfo?.savedAt && dirty ? (
                  <span className="bp-muted bp-text-xs">Draft saved {new Date(draftInfo.savedAt).toLocaleTimeString()}</span>
                ) : null}
              </div>
              <div className="bp-designer-previewActions">
                <button
                  type="button"
                  className={`bp-btn ${device === "desktop" ? "bp-btn-primary" : ""}`}
                  onClick={() => setDevice("desktop")}
                >
                  Desktop
                </button>
                <button
                  type="button"
                  className={`bp-btn ${device === "mobile" ? "bp-btn-primary" : ""}`}
                  onClick={() => setDevice("mobile")}
                >
                  Mobile
                </button>
              </div>
            </div>

            <div className={`bp-designer-previewBody ${device === "mobile" ? "is-mobile" : ""}`}>
              <WizardPreview config={config} activeStepKey={activeStepKey} device={device} />
            </div>
          </div>
        </div>

        <div className="bp-designer-props">
          <div className="bp-card bp-designer-panel">
            <div className="bp-designer-panelHead">
              <div className="bp-section-title" style={{ margin: 0 }}>Step Settings</div>
              <button className="bp-btn bp-btn-ghost" type="button" onClick={() => resetStepToServer(activeStepKey)}>
                Reset step
              </button>
            </div>

            <div className="bp-designer-panelBody">
              <div className="bp-flex bp-items-center bp-justify-between">
                <div className="bp-font-700">Enable this step</div>
                {REQUIRED_STEP_KEYS.has(activeStepKey) ? (
                  <span className="bp-muted bp-text-xs">Required</span>
                ) : null}
                <input
                  type="checkbox"
                  checked={REQUIRED_STEP_KEYS.has(activeStepKey) ? true : !!activeStep?.enabled}
                  disabled={REQUIRED_STEP_KEYS.has(activeStepKey)}
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
          </div>

          <div className="bp-card bp-designer-panel bp-mt-14">
            <div className="bp-designer-panelHead">
              <div className="bp-section-title" style={{ margin: 0 }}>Appearance</div>
              <div className="bp-muted bp-text-xs">Global theme</div>
            </div>

            <div className="bp-designer-panelBody">
              <div className="bp-mt-4">
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
          </div>

          <div className="bp-card bp-designer-panel bp-mt-14">
            <div className="bp-designer-panelHead">
              <div className="bp-section-title" style={{ margin: 0 }}>Texts</div>
              <div className="bp-muted bp-text-xs">Global help + buttons</div>
            </div>

            <div className="bp-designer-panelBody">
              <div className="bp-mt-4">
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
          </div>

          <div className="bp-card bp-designer-panel bp-mt-14">
            <div className="bp-designer-panelHead">
              <div className="bp-section-title" style={{ margin: 0 }}>Fields Layout</div>
              <div className="bp-muted bp-text-xs">Customer + booking fields</div>
            </div>
            <div className="bp-designer-panelBody">
              {fieldsAll ? (
                <FieldsLayoutPanel
                  fieldsByGroup={fieldsAll}
                  value={config.fieldsLayout}
                  onChange={(fieldsLayout) => {
                    patchConfig((prev) => ({ ...prev, fieldsLayout }));
                  }}
                />
              ) : (
                <div className="bp-text-sm bp-muted">Unable to load fields list.</div>
              )}
            </div>
          </div>
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
      </main>
    </div>
  );
}
