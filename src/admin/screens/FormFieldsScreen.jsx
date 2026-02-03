import React, { useEffect, useMemo, useRef, useState } from "react";
import { bpFetch } from "../api/client";
import { iconDataUri } from "../icons/iconData";

const SCOPES = [
  { key: "form", label: "Form", icon: "designer" },
  { key: "customer", label: "Customer", icon: "customers" },
  { key: "booking", label: "Booking", icon: "bookings" },
];

const TYPES = [
  { key: "text", label: "Text" },
  { key: "email", label: "Email" },
  { key: "tel", label: "Phone" },
  { key: "textarea", label: "Textarea" },
  { key: "number", label: "Number" },
  { key: "date", label: "Date" },
  { key: "select", label: "Select" },
  { key: "checkbox", label: "Checkbox" },
];

const STEPS = [
  { key: "details", label: "Details" },
  { key: "payment", label: "Payment" },
  { key: "summary", label: "Summary" },
];

function sanitizeKey(input) {
  return String(input || "")
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9_]/g, "_")
    .replace(/_+/g, "_")
    .replace(/^_+|_+$/g, "");
}

function agentSafeJson(v, fallback) {
  try {
    return JSON.parse(JSON.stringify(v));
  } catch (e) {
    return fallback;
  }
}

function normalizeOptions(raw) {
  if (!raw) return [];
  if (Array.isArray(raw)) {
    return raw
      .map((o) => {
        if (typeof o === "string") return { label: o, value: sanitizeKey(o) || o };
        if (o && typeof o === "object") return { label: String(o.label ?? o.value ?? ""), value: String(o.value ?? sanitizeKey(o.label) ?? "") };
        return null;
      })
      .filter(Boolean)
      .filter((o) => o.label || o.value);
  }
  return [];
}

function typeLabel(t) {
  return TYPES.find((x) => x.key === t)?.label || t || "—";
}

function stepLabel(s) {
  return STEPS.find((x) => x.key === s)?.label || s || "—";
}

function makeEmpty(scope) {
  return {
    id: 0,
    field_key: "",
    label: "",
    type: "text",
    scope: scope || "customer",
    step_key: "details",
    placeholder: "",
    options: [],
    is_required: 0,
    is_enabled: 1,
    show_in_wizard: 1,
    sort_order: 0,
  };
}

function Preview({ field }) {
  const f = field || makeEmpty("customer");
  const label = f.label || "Field label";
  const required = Number(f.is_required) === 1;
  const placeholder = f.placeholder || "";
  const opts = normalizeOptions(f.options);

  return (
    <div className="bp-ff-preview">
      <div className="bp-ff-preview__label">
        {label} {required ? <span className="bp-ff-preview__req">*</span> : null}
      </div>

      {f.type === "textarea" ? (
        <textarea className="bp-textarea" placeholder={placeholder} rows={4} readOnly />
      ) : f.type === "select" ? (
        <select className="bp-select" disabled>
          <option>{placeholder || "Select..."}</option>
          {opts.map((o, i) => (
            <option key={`${o.value}-${i}`}>{o.label || o.value}</option>
          ))}
        </select>
      ) : f.type === "checkbox" ? (
        <label className="bp-ff-check">
          <input type="checkbox" disabled />
          <span>{placeholder || "Checkbox"}</span>
        </label>
      ) : (
        <input
          className="bp-input-field"
          type={f.type === "tel" ? "tel" : f.type === "email" ? "email" : f.type === "number" ? "number" : f.type === "date" ? "date" : "text"}
          placeholder={placeholder}
          readOnly
        />
      )}

      <div className="bp-muted bp-text-xs" style={{ marginTop: 8 }}>
        Preview only.
      </div>
    </div>
  );
}

function EditorDrawer({ open, title, children, onClose }) {
  if (!open) return null;
  return (
    <div className="bp-ff-drawer__overlay" onMouseDown={onClose} role="dialog" aria-modal="true">
      <div className="bp-ff-drawer" onMouseDown={(e) => e.stopPropagation()}>
        <div className="bp-ff-drawer__head">
          <div style={{ minWidth: 0 }}>
            <div className="bp-section-title" style={{ margin: 0 }}>{title}</div>
            <div className="bp-muted bp-text-xs">Edit and save changes.</div>
          </div>
          <button className="bp-btn" type="button" onClick={onClose}>Close</button>
        </div>
        <div className="bp-ff-drawer__body">{children}</div>
      </div>
    </div>
  );
}

export default function FormFieldsScreen({ embedded = false }) {
  const [scope, setScope] = useState("customer");
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(false);
  const [toast, setToast] = useState("");
  const [err, setErr] = useState("");

  const [q, setQ] = useState("");
  const [statusFilter, setStatusFilter] = useState("all"); // all|enabled|disabled
  const [sort, setSort] = useState("order"); // order|label|type

  const [selectedId, setSelectedId] = useState(0);
  const [draft, setDraft] = useState(makeEmpty(scope));
  const [saving, setSaving] = useState(false);

  const [reorderMode, setReorderMode] = useState(false);
  const [dirtyOrder, setDirtyOrder] = useState(false);
  const dragIdRef = useRef(null);

  const [drawerOpen, setDrawerOpen] = useState(false);

  const showToast = (msg) => {
    setToast(msg);
    setTimeout(() => setToast(""), 2500);
  };

  const theme = document.documentElement.classList.contains("bp-dark") ? "dark" : "light";

  async function load() {
    setLoading(true);
    setErr("");
    try {
      const res = await bpFetch(`/admin/form-fields?scope=${encodeURIComponent(scope)}`);
      setRows(res?.data || []);
    } catch (e) {
      setRows([]);
      setErr(e.message || "Failed to load");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [scope]);

  useEffect(() => {
    // reset selection when scope changes
    setSelectedId(0);
    setDraft(makeEmpty(scope));
    setReorderMode(false);
    setDirtyOrder(false);
  }, [scope]);

  useEffect(() => {
    const isMobile = window.innerWidth < 1024;
    setDrawerOpen(isMobile && selectedId !== 0);
  }, [selectedId]);

  const existingKeys = useMemo(() => {
    return new Set((rows || []).map((r) => String(r.field_key || r.name_key || "").trim()).filter(Boolean));
  }, [rows]);

  const filtered = useMemo(() => {
    const needle = String(q || "").trim().toLowerCase();
    let list = [...(rows || [])];

    if (statusFilter !== "all") {
      const wantEnabled = statusFilter === "enabled";
      list = list.filter((r) => (Number(r.is_enabled) === 1) === wantEnabled);
    }

    if (needle) {
      list = list.filter((r) => {
        const key = String(r.field_key || r.name_key || "").toLowerCase();
        const label = String(r.label || "").toLowerCase();
        return key.includes(needle) || label.includes(needle);
      });
    }

    if (!reorderMode || sort !== "order") {
      if (sort === "label") {
        list.sort((a, b) => String(a.label || "").localeCompare(String(b.label || "")));
      } else if (sort === "type") {
        list.sort((a, b) => String(a.type || "").localeCompare(String(b.type || "")));
      } else {
        list.sort((a, b) => (Number(a.sort_order) || 0) - (Number(b.sort_order) || 0) || (Number(a.id) || 0) - (Number(b.id) || 0));
      }
    }

    return list;
  }, [rows, q, statusFilter, sort, reorderMode]);

  const selected = useMemo(() => {
    if (!selectedId) return null;
    return (rows || []).find((r) => Number(r.id) === Number(selectedId)) || null;
  }, [rows, selectedId]);

  const invalid = useMemo(() => {
    const errors = {};
    const key = sanitizeKey(draft.field_key);
    if (!draft.id) {
      if (!key) errors.field_key = "Key is required.";
      if (key && existingKeys.has(key)) errors.field_key = "Key already exists.";
    }
    if (!String(draft.label || "").trim()) errors.label = "Label is required.";
    if (draft.type === "select") {
      const opts = normalizeOptions(draft.options);
      if (!opts.length) errors.options = "Select fields need at least one option.";
      if (opts.some((o) => !o.label || !o.value)) errors.options = "All options need label and value.";
    }
    return errors;
  }, [draft, existingKeys]);

  const canSave = useMemo(() => Object.keys(invalid).length === 0 && !saving, [invalid, saving]);

  const openCreate = () => {
    setSelectedId(-1);
    setDraft(makeEmpty(scope));
    setDrawerOpen(window.innerWidth < 1024);
  };

  const openEdit = (row) => {
    setSelectedId(Number(row.id));
    setDraft({
      id: Number(row.id),
      field_key: String(row.field_key || row.name_key || ""),
      label: row.label || "",
      type: row.type || "text",
      scope: row.scope || scope,
      step_key: row.step_key || "details",
      placeholder: row.placeholder || "",
      options: agentSafeJson(normalizeOptions(row.options), []),
      is_required: Number(row.is_required) ? 1 : 0,
      is_enabled: Number(row.is_enabled) ? 1 : 0,
      show_in_wizard: Number(row.show_in_wizard) ? 1 : 0,
      sort_order: Number(row.sort_order) || 0,
    });
    setDrawerOpen(window.innerWidth < 1024);
  };

  const closeEditor = () => {
    setSelectedId(0);
    setDraft(makeEmpty(scope));
    setDrawerOpen(false);
  };

  const save = async () => {
    setSaving(true);
    setErr("");
    try {
      const payload = {
        field_key: sanitizeKey(draft.field_key),
        label: String(draft.label || "").trim(),
        type: draft.type,
        scope: scope,
        step_key: draft.step_key,
        placeholder: String(draft.placeholder || "").trim(),
        options: draft.type === "select" ? normalizeOptions(draft.options) : null,
        is_required: !!Number(draft.is_required),
        is_enabled: !!Number(draft.is_enabled),
        show_in_wizard: !!Number(draft.show_in_wizard),
        sort_order: Number(draft.sort_order) || 0,
      };

      if (!draft.id || draft.id === -1) {
        await bpFetch("/admin/form-fields", { method: "POST", body: payload });
        showToast("Field created.");
        await load();
        closeEditor();
      } else {
        await bpFetch(`/admin/form-fields/${draft.id}`, { method: "PATCH", body: payload });
        showToast("Saved.");
        await load();
      }
    } catch (e) {
      setErr(e.message || "Save failed");
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id) => {
    if (!confirm("Delete this field?")) return;
    setErr("");
    try {
      await bpFetch(`/admin/form-fields/${id}`, { method: "DELETE" });
      showToast("Deleted.");
      await load();
      if (Number(selectedId) === Number(id)) closeEditor();
    } catch (e) {
      setErr(e.message || "Delete failed");
    }
  };

  const toggleEnabled = async (row) => {
    try {
      const next = Number(row.is_enabled) ? 0 : 1;
      await bpFetch(`/admin/form-fields/${row.id}`, { method: "PATCH", body: { is_enabled: !!next } });
      setRows((prev) => prev.map((r) => (r.id === row.id ? { ...r, is_enabled: next } : r)));
      showToast(next ? "Enabled." : "Disabled.");
    } catch (e) {
      setErr(e.message || "Update failed");
    }
  };

  const duplicate = async (row) => {
    const baseKey = sanitizeKey(`${row.field_key || row.name_key || "field"}_copy`);
    let key = baseKey;
    let i = 2;
    while (existingKeys.has(key)) {
      key = sanitizeKey(`${baseKey}_${i}`);
      i += 1;
    }
    try {
      const payload = {
        field_key: key,
        label: `${row.label || "Field"} (copy)`,
        type: row.type || "text",
        scope: scope,
        step_key: row.step_key || "details",
        placeholder: row.placeholder || "",
        options: row.type === "select" ? normalizeOptions(row.options) : null,
        is_required: !!Number(row.is_required),
        is_enabled: true,
        show_in_wizard: !!Number(row.show_in_wizard),
        sort_order: Number(row.sort_order) || 0,
      };
      await bpFetch("/admin/form-fields", { method: "POST", body: payload });
      showToast("Duplicated.");
      await load();
    } catch (e) {
      setErr(e.message || "Duplicate failed");
    }
  };

  const startReorder = () => {
    setReorderMode(true);
    setDirtyOrder(false);
    setSort("order");
  };

  const cancelReorder = async () => {
    setReorderMode(false);
    setDirtyOrder(false);
    await load();
  };

  const onDragStart = (e, id) => {
    // HTML5 DnD: some browsers (Safari/Firefox) won't start dragging unless setData is called.
    dragIdRef.current = id;
    try {
      e.dataTransfer.effectAllowed = "move";
      e.dataTransfer.setData("text/plain", String(id));
    } catch (_) {
      // ignore
    }
  };

  const onDragEnd = () => {
    dragIdRef.current = null;
  };

  const moveRow = (id, delta) => {
    const rowId = Number(id) || 0;
    const d = Number(delta) || 0;
    if (!rowId || !d) return;

    setRows((prev) => {
      const list = [...(prev || [])];
      const from = list.findIndex((r) => Number(r.id) === rowId);
      if (from < 0) return prev;
      const to = Math.max(0, Math.min(list.length - 1, from + d));
      if (to === from) return prev;
      const [moved] = list.splice(from, 1);
      list.splice(to, 0, moved);
      return list;
    });
    setDirtyOrder(true);
  };

  const onDrop = (e, targetId) => {
    e.preventDefault();
    const sourceId = Number(dragIdRef.current) || 0;
    dragIdRef.current = null;
    const tid = Number(targetId) || 0;
    if (!sourceId || !tid || sourceId === tid) return;
    setRows((prev) => {
      const list = [...prev];
      const from = list.findIndex((r) => Number(r.id) === sourceId);
      const to = list.findIndex((r) => Number(r.id) === tid);
      if (from < 0 || to < 0) return prev;
      const [moved] = list.splice(from, 1);
      list.splice(to, 0, moved);
      return list;
    });
    setDirtyOrder(true);
  };

  const saveOrder = async () => {
    setErr("");
    try {
      const ids = (rows || []).map((r) => Number(r.id)).filter(Boolean);
      await bpFetch("/admin/form-fields/reorder", { method: "POST", body: { ids } });
      showToast("Order saved.");
      setDirtyOrder(false);
      setReorderMode(false);
      await load();
    } catch (e) {
      setErr(e.message || "Save order failed");
    }
  };

  const wrapClass = embedded ? "bp-ff bp-ff--embedded" : "bp-content bp-ff";
  const panelTitle = !draft.id || draft.id === -1 ? "Add field" : "Edit field";

  const EditorPanel = (
    <div className="bp-ff-editor">
      {err ? <div className="bp-alert bp-alert-error">{err}</div> : null}

      <div className="bp-card bp-ff-editorCard">
        <div className="bp-card-head bp-ff-editorHead">
          <div style={{ minWidth: 0 }}>
            <div className="bp-section-title" style={{ margin: 0 }}>{panelTitle}</div>
            <div className="bp-muted bp-text-xs">Scope: {scope}</div>
          </div>
          {draft.id && draft.id !== -1 ? (
            <div className="bp-hol-tag">#{draft.id}</div>
          ) : null}
        </div>

        <div className="bp-ff-editorBody">
          <div className="bp-ff-section">
            <div className="bp-ff-sectionTitle">Basics</div>
            <div className="bp-ff-grid2">
              <div>
                <label className="bp-label">Label</label>
                <input
                  className={`bp-input-field ${invalid.label ? "bp-field-error" : ""}`}
                  value={draft.label}
                  onChange={(e) => setDraft({ ...draft, label: e.target.value })}
                />
              </div>
              <div>
                <label className="bp-label">Key {draft.id && draft.id !== -1 ? <span className="bp-muted">(locked)</span> : null}</label>
                <input
                  className={`bp-input-field ${invalid.field_key ? "bp-field-error" : ""}`}
                  value={draft.field_key}
                  onChange={(e) => setDraft({ ...draft, field_key: sanitizeKey(e.target.value) })}
                  disabled={Boolean(draft.id && draft.id !== -1)}
                  placeholder="e.g. customer_phone"
                />
                {invalid.field_key ? <div className="bp-muted bp-text-xs" style={{ color: "#991b1b", marginTop: 6 }}>{invalid.field_key}</div> : null}
              </div>
            </div>

            <div className="bp-ff-grid2">
              <div>
                <label className="bp-label">Type</label>
                <select className="bp-select" value={draft.type} onChange={(e) => setDraft({ ...draft, type: e.target.value })}>
                  {TYPES.map((t) => (
                    <option key={t.key} value={t.key}>{t.label}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="bp-label">Step</label>
                <select className="bp-select" value={draft.step_key} onChange={(e) => setDraft({ ...draft, step_key: e.target.value })}>
                  {STEPS.map((s) => (
                    <option key={s.key} value={s.key}>{s.label}</option>
                  ))}
                </select>
              </div>
            </div>

            <div>
              <label className="bp-label">Placeholder / Help text</label>
              <input className="bp-input-field" value={draft.placeholder} onChange={(e) => setDraft({ ...draft, placeholder: e.target.value })} />
            </div>
          </div>

          <div className="bp-ff-section">
            <div className="bp-ff-sectionTitle">Rules</div>
            <div className="bp-ff-grid2">
              <div className="bp-ff-switchRow">
                <div>
                  <div className="bp-label">Required</div>
                  <div className="bp-muted bp-text-xs">User must fill this field.</div>
                </div>
                <label className="bp-switch">
                  <input type="checkbox" checked={!!Number(draft.is_required)} onChange={(e) => setDraft({ ...draft, is_required: e.target.checked ? 1 : 0 })} />
                  <span className="bp-slider" />
                </label>
              </div>

              <div className="bp-ff-switchRow">
                <div>
                  <div className="bp-label">Enabled</div>
                  <div className="bp-muted bp-text-xs">Show this field in admin and wizard.</div>
                </div>
                <label className="bp-switch">
                  <input type="checkbox" checked={!!Number(draft.is_enabled)} onChange={(e) => setDraft({ ...draft, is_enabled: e.target.checked ? 1 : 0 })} />
                  <span className="bp-slider" />
                </label>
              </div>

              <div className="bp-ff-switchRow">
                <div>
                  <div className="bp-label">Show in wizard</div>
                  <div className="bp-muted bp-text-xs">Visible during booking flow.</div>
                </div>
                <label className="bp-switch">
                  <input type="checkbox" checked={!!Number(draft.show_in_wizard)} onChange={(e) => setDraft({ ...draft, show_in_wizard: e.target.checked ? 1 : 0 })} />
                  <span className="bp-slider" />
                </label>
              </div>
            </div>
          </div>

          {draft.type === "select" ? (
            <div className="bp-ff-section">
              <div className="bp-ff-sectionTitle">Options</div>
              {invalid.options ? <div className="bp-alert bp-alert-error" style={{ marginBottom: 10 }}>{invalid.options}</div> : null}
              <div className="bp-ff-options">
                {normalizeOptions(draft.options).map((o, i) => (
                  <div key={i} className="bp-ff-optionRow">
                    <input
                      className="bp-input-field"
                      value={o.label}
                      onChange={(e) => {
                        const next = normalizeOptions(draft.options);
                        next[i] = { ...next[i], label: e.target.value, value: sanitizeKey(e.target.value) || next[i].value };
                        setDraft({ ...draft, options: next });
                      }}
                      placeholder="Label"
                    />
                    <input
                      className="bp-input-field"
                      value={o.value}
                      onChange={(e) => {
                        const next = normalizeOptions(draft.options);
                        next[i] = { ...next[i], value: sanitizeKey(e.target.value) || e.target.value };
                        setDraft({ ...draft, options: next });
                      }}
                      placeholder="Value"
                    />
                    <button
                      type="button"
                      className="bp-btn-sm bp-btn-danger"
                      onClick={() => {
                        const next = normalizeOptions(draft.options);
                        next.splice(i, 1);
                        setDraft({ ...draft, options: next });
                      }}
                    >
                      Remove
                    </button>
                  </div>
                ))}
                <button
                  type="button"
                  className="bp-btn-sm"
                  onClick={() => setDraft({ ...draft, options: [...normalizeOptions(draft.options), { label: "", value: "" }] })}
                >
                  + Add option
                </button>
              </div>
            </div>
          ) : null}

          <div className="bp-ff-section">
            <div className="bp-ff-sectionTitle">Preview</div>
            <Preview field={draft} />
          </div>
        </div>

        <div className="bp-ff-savebar">
          <button type="button" className="bp-btn bp-btn-ghost" onClick={closeEditor}>Cancel</button>
          {draft.id && draft.id !== -1 ? (
            <button type="button" className="bp-btn bp-btn-danger" onClick={() => handleDelete(draft.id)}>Delete</button>
          ) : null}
          <button type="button" className="bp-btn bp-btn-primary" disabled={!canSave} onClick={save}>
            {saving ? "Saving..." : "Save"}
          </button>
        </div>
      </div>
    </div>
  );

  return (
    <div className={wrapClass}>
      {toast ? <div className="bp-toast bp-toast-success">{toast}</div> : null}

      {!embedded ? (
        <div className="bp-page-head">
          <div>
            <div className="bp-h1">Form Fields</div>
            <div className="bp-muted">Manage custom fields for the booking wizard.</div>
          </div>
        </div>
      ) : null}

      <div className="bp-ff-layout">
        <div className="bp-card bp-ff-list">
          <div className="bp-card-head bp-ff-listHead">
            <div className="bp-ff-tabs">
              {SCOPES.map((s) => (
                <button
                  key={s.key}
                  type="button"
                  className={`bp-chip-btn ${scope === s.key ? "is-active" : ""}`}
                  onClick={() => setScope(s.key)}
                >
                  <img
                    className="bp-ff-scopeIcon"
                    src={iconDataUri(s.icon, { active: scope === s.key, theme })}
                    alt=""
                    aria-hidden="true"
                  />
                  {s.label}
                </button>
              ))}
            </div>
            <div className="bp-ff-listActions">
              {!reorderMode ? (
                <button type="button" className="bp-btn-sm" onClick={startReorder}>Reorder</button>
              ) : (
                <button type="button" className="bp-btn-sm" onClick={cancelReorder}>Done</button>
              )}
              <button type="button" className="bp-btn bp-btn-primary" onClick={openCreate}>+ Add field</button>
            </div>
          </div>

          <div className="bp-ff-filters">
            <div className="bp-ff-filter">
              <label className="bp-label">Search</label>
              <input className="bp-input-field" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search by label or key..." />
            </div>

            <div className="bp-ff-filterRow">
              <div className="bp-ff-chips" role="group" aria-label="Status filter">
                <button type="button" className={`bp-chip-btn ${statusFilter === "all" ? "is-active" : ""}`} onClick={() => setStatusFilter("all")}>All</button>
                <button type="button" className={`bp-chip-btn ${statusFilter === "enabled" ? "is-active" : ""}`} onClick={() => setStatusFilter("enabled")}>Enabled</button>
                <button type="button" className={`bp-chip-btn ${statusFilter === "disabled" ? "is-active" : ""}`} onClick={() => setStatusFilter("disabled")}>Disabled</button>
              </div>

              <div className="bp-ff-sort">
                <label className="bp-label">Sort</label>
                <select className="bp-select" value={sort} onChange={(e) => setSort(e.target.value)}>
                  <option value="order">Order</option>
                  <option value="label">Label</option>
                  <option value="type">Type</option>
                </select>
              </div>
            </div>
          </div>

          {loading ? <div className="bp-muted" style={{ padding: 14 }}>Loading...</div> : null}
          {!loading && err ? <div className="bp-alert bp-alert-error" style={{ margin: 14 }}>{err}</div> : null}
          {!loading && !err && filtered.length === 0 ? (
            <div className="bp-muted" style={{ padding: 14 }}>
              No fields yet. Create one to get started.
            </div>
          ) : null}

          {!loading && filtered.length > 0 ? (
            <div className="bp-ff-items">
              {filtered.map((r) => {
                const key = r.field_key || r.name_key || "";
                const enabled = Number(r.is_enabled) === 1;
                const required = Number(r.is_required) === 1;
                const show = Number(r.show_in_wizard) === 1;
                const active = Number(selectedId) === Number(r.id);
                const draggable = reorderMode && sort === "order";

                return (
                  <div
                    key={r.id}
                    className={`bp-ff-item ${active ? "is-active" : ""} ${draggable ? "is-draggable" : ""}`}
                    draggable={draggable}
                    onDragStart={(e) => draggable && onDragStart(e, r.id)}
                    onDragEnd={onDragEnd}
                    onDragOver={(e) => {
                      if (!draggable) return;
                      e.preventDefault();
                      try { e.dataTransfer.dropEffect = "move"; } catch (_) {}
                    }}
                    onDrop={(e) => draggable && onDrop(e, r.id)}
                    onClick={() => (!reorderMode ? openEdit(r) : null)}
                    role="button"
                    tabIndex={reorderMode ? -1 : 0}
                    onKeyDown={(e) => (!reorderMode && (e.key === "Enter" || e.key === " ")) && openEdit(r)}
                  >
                    <div className="bp-ff-itemMain">
                      <div className="bp-ff-itemTop">
                        <div className="bp-ff-itemTitle">
                          <span className="bp-ff-itemLabel">{r.label || "—"}</span>
                          <span className="bp-ff-pill">{typeLabel(r.type)}</span>
                          {!enabled ? <span className="bp-ff-pill bp-ff-pill--off">Disabled</span> : null}
                          {required ? <span className="bp-ff-pill bp-ff-pill--req">Required</span> : null}
                          {show ? null : <span className="bp-ff-pill bp-ff-pill--muted">Hidden</span>}
                        </div>
                        {reorderMode ? (
                          <div style={{ display: "flex", alignItems: "center", gap: 8 }} onClick={(e) => e.stopPropagation()}>
                            <button type="button" className="bp-btn-sm" onClick={() => moveRow(r.id, -1)}>Up</button>
                            <button type="button" className="bp-btn-sm" onClick={() => moveRow(r.id, 1)}>Down</button>
                            <span className="bp-ff-drag" aria-hidden="true">⋮⋮</span>
                          </div>
                        ) : null}
                      </div>
                      <div className="bp-ff-itemMeta">
                        <code className="bp-ff-code">{key}</code>
                        <span className="bp-ff-dot">•</span>
                        <span className="bp-muted">{stepLabel(r.step_key)}</span>
                      </div>
                    </div>

                    {!reorderMode ? (
                      <div className="bp-ff-itemActions" onClick={(e) => e.stopPropagation()}>
                        <button type="button" className="bp-btn-sm" onClick={() => toggleEnabled(r)}>{enabled ? "Disable" : "Enable"}</button>
                        <button type="button" className="bp-btn-sm" onClick={() => duplicate(r)}>Duplicate</button>
                        <button type="button" className="bp-btn-sm bp-btn-danger" onClick={() => handleDelete(r.id)}>Delete</button>
                      </div>
                    ) : null}
                  </div>
                );
              })}
            </div>
          ) : null}

          {reorderMode ? (
            <div className="bp-ff-orderbar">
              <div className="bp-muted bp-text-sm" style={{ minWidth: 0 }}>
                Drag items to reorder. Save to apply order.
              </div>
              <div className="bp-ff-orderbar__actions">
                <button type="button" className="bp-btn bp-btn-ghost" onClick={cancelReorder}>Cancel</button>
                <button type="button" className="bp-btn bp-btn-primary" disabled={!dirtyOrder} onClick={saveOrder}>Save order</button>
              </div>
            </div>
          ) : null}
        </div>

        <div className="bp-ff-right">
          {selectedId === 0 ? (
            <div className="bp-card bp-ff-empty">
              <div className="bp-section-title">Select a field</div>
              <div className="bp-muted bp-text-sm" style={{ marginTop: 6 }}>
                Pick a field from the left, or create a new one.
              </div>
              <div style={{ marginTop: 12 }}>
                <button className="bp-btn bp-btn-primary" type="button" onClick={openCreate}>+ Add field</button>
              </div>
            </div>
          ) : (
            EditorPanel
          )}
        </div>
      </div>

      <EditorDrawer open={drawerOpen} title={panelTitle} onClose={closeEditor}>
        {EditorPanel}
      </EditorDrawer>
    </div>
  );
}
