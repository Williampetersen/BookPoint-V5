import React, { useEffect, useMemo, useRef, useState } from "react";
import { bpFetch } from "../api/client";
import UpgradeToPro from "../components/UpgradeToPro";

function clamp(n, min, max) {
  const v = Number(n);
  if (Number.isNaN(v)) return min;
  return Math.max(min, Math.min(max, v));
}

function fmtMoney(v) {
  const n = Number(v) || 0;
  return `$${n.toFixed(2)}`;
}

function toDatetimeLocal(value) {
  const s = String(value || "").trim();
  if (!s) return "";
  // Handle MySQL DATETIME: "YYYY-MM-DD HH:MM:SS" -> "YYYY-MM-DDTHH:MM"
  const t = s.replace(" ", "T").replace(/Z$/, "");
  const m = t.match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/);
  return m ? m[1] : "";
}

function fromDatetimeLocal(value) {
  const s = String(value || "").trim();
  if (!s) return "";
  // Handle <input type="datetime-local">: "YYYY-MM-DDTHH:MM" -> "YYYY-MM-DD HH:MM:00"
  const m = s.match(/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/);
  return m ? `${m[1]} ${m[2]}:00` : "";
}

function parseDate(value) {
  const s = String(value || "").trim();
  if (!s) return null;
  const normalized = s.includes(" ") ? s.replace(" ", "T") : s;
  const t = Date.parse(normalized);
  if (!Number.isFinite(t)) return null;
  return new Date(t);
}

function statusFor(row, now = new Date()) {
  const isActive = row?.is_active !== undefined ? !!Number(row.is_active) : true;
  if (!isActive) return "disabled";

  const starts = parseDate(row?.starts_at);
  const ends = parseDate(row?.ends_at);

  if (starts && starts.getTime() > now.getTime()) return "scheduled";
  if (ends && ends.getTime() < now.getTime()) return "expired";
  return "active";
}

function genCode() {
  const alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  let out = "";
  for (let i = 0; i < 10; i++) out += alphabet[Math.floor(Math.random() * alphabet.length)];
  return out;
}

async function copyText(text) {
  const v = String(text || "");
  try {
    await navigator.clipboard.writeText(v);
    return true;
  } catch {
    try {
      const ta = document.createElement("textarea");
      ta.value = v;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand("copy");
      ta.remove();
      return true;
    } catch {
      return false;
    }
  }
}

function PromoDrawer({ open, promo, onClose, onSaved, onDeleted }) {
  const [draft, setDraft] = useState(promo || null);
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState("");

  const [testTotal, setTestTotal] = useState(100);

  useEffect(() => {
    if (!promo) {
      setDraft(null);
    } else {
      setDraft({
        ...promo,
        starts_at: toDatetimeLocal(promo.starts_at),
        ends_at: toDatetimeLocal(promo.ends_at),
      });
    }
    setErr("");
    setSaving(false);
  }, [promo]);

  if (!open) return null;

  const isNew = !draft?.id;
  const type = draft?.type || "percent";
  const amount = Number(draft?.amount) || 0;

  const minTotal = draft?.min_total === null || draft?.min_total === undefined || draft?.min_total === ""
    ? null
    : Number(draft?.min_total) || 0;

  const maxUses = draft?.max_uses === null || draft?.max_uses === undefined || draft?.max_uses === ""
    ? null
    : Number(draft?.max_uses);

  const uses = Number(draft?.uses_count) || 0;

  const qualifies = minTotal === null ? true : (Number(testTotal) || 0) >= minTotal;
  const rawDiscount = type === "percent" ? ((Number(testTotal) || 0) * (amount / 100)) : amount;
  const appliedDiscount = qualifies ? Math.max(0, rawDiscount) : 0;

  const save = async () => {
    setSaving(true);
    setErr("");
    try {
      const payload = {
        code: String(draft?.code || "").toUpperCase().trim(),
        type: draft?.type || "percent",
        amount: Number(draft?.amount) || 0,
        starts_at: fromDatetimeLocal(draft?.starts_at || ""),
        ends_at: fromDatetimeLocal(draft?.ends_at || ""),
        max_uses: draft?.max_uses === "" ? null : (draft?.max_uses === null ? null : draft?.max_uses),
        min_total: draft?.min_total === "" ? null : (draft?.min_total === null ? null : draft?.min_total),
        is_active: !!Number(draft?.is_active ?? 1),
      };

      if (!payload.code) {
        setErr("Code is required.");
        return;
      }

      if (isNew) {
        const res = await bpFetch("/admin/promo-codes", { method: "POST", body: payload });
        onSaved?.(res?.data || null);
      } else {
        const res = await bpFetch(`/admin/promo-codes/${draft.id}`, { method: "PATCH", body: payload });
        onSaved?.(res?.data || null);
      }

      onClose?.();
    } catch (e) {
      setErr(e.message || "Save failed");
    } finally {
      setSaving(false);
    }
  };

  const del = async () => {
    if (!draft?.id) return;
    if (!confirm(`Delete promo code ${draft.code || ""}? This cannot be undone.`)) return;
    setSaving(true);
    setErr("");
    try {
      await bpFetch(`/admin/promo-codes/${draft.id}`, { method: "DELETE" });
      onDeleted?.(draft.id);
      onClose?.();
    } catch (e) {
      setErr(e.message || "Delete failed");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div
      className="bp-drawer-wrap"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onClose?.();
      }}
    >
      <div className="bp-drawer" style={{ width: "min(980px, 100%)" }}>
        <div className="bp-drawer-head">
          <div>
            <div className="bp-drawer-title">{isNew ? "New Promo Code" : (draft?.code || `Promo #${draft?.id}`)}</div>
            <div className="bp-muted">Configure discount rules and usage.</div>
          </div>
          <button className="bp-btn" type="button" onClick={onClose}>Close</button>
        </div>

        <div style={{ padding: 14 }}>
          {err ? <div className="bp-alert bp-alert-error" style={{ marginBottom: 12 }}>{err}</div> : null}

          <div className="bp-card" style={{ marginBottom: 12 }}>
            <div className="bp-section-title">Basics</div>

            <div className="bp-grid" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 12, marginTop: 10 }}>
              <div>
                <label className="bp-label">Code</label>
                <div style={{ display: "flex", gap: 10 }}>
                  <input
                    className="bp-input"
                    value={draft?.code || ""}
                    onChange={(e) => setDraft({ ...(draft || {}), code: e.target.value.toUpperCase() })}
                    placeholder="WELCOME10"
                  />
                  <button className="bp-btn" type="button" onClick={() => setDraft({ ...(draft || {}), code: genCode() })}>Generate</button>
                  <button className="bp-btn" type="button" onClick={async () => { await copyText(draft?.code || ""); }}>Copy</button>
                </div>
              </div>

              <div>
                <label className="bp-label">Status</label>
                <select
                  className="bp-input"
                  value={String(draft?.is_active ?? 1)}
                  onChange={(e) => setDraft({ ...(draft || {}), is_active: Number(e.target.value) })}
                >
                  <option value="1">Active</option>
                  <option value="0">Disabled</option>
                </select>
              </div>

              <div>
                <label className="bp-label">Discount type</label>
                <select className="bp-input" value={draft?.type || "percent"} onChange={(e) => setDraft({ ...(draft || {}), type: e.target.value })}>
                  <option value="percent">Percent</option>
                  <option value="fixed">Fixed amount</option>
                </select>
              </div>

              <div>
                <label className="bp-label">Amount</label>
                <input
                  className="bp-input"
                  type="number"
                  step="0.01"
                  value={draft?.amount ?? 0}
                  onChange={(e) => setDraft({ ...(draft || {}), amount: e.target.value })}
                />
              </div>
            </div>
          </div>

          <div className="bp-card" style={{ marginBottom: 12 }}>
            <div className="bp-section-title">Rules</div>

            <div className="bp-grid" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 12, marginTop: 10 }}>
              <div>
                <label className="bp-label">Starts at</label>
                <input className="bp-input" type="datetime-local" value={draft?.starts_at || ""} onChange={(e) => setDraft({ ...(draft || {}), starts_at: e.target.value })} />
                <div className="bp-muted" style={{ fontSize: 12, marginTop: 6 }}>Leave blank for immediate.</div>
              </div>
              <div>
                <label className="bp-label">Ends at</label>
                <input className="bp-input" type="datetime-local" value={draft?.ends_at || ""} onChange={(e) => setDraft({ ...(draft || {}), ends_at: e.target.value })} />
                <div className="bp-muted" style={{ fontSize: 12, marginTop: 6 }}>Leave blank for no expiry.</div>
              </div>
              <div>
                <label className="bp-label">Max uses</label>
                <input
                  className="bp-input"
                  type="number"
                  value={draft?.max_uses ?? ""}
                  onChange={(e) => setDraft({ ...(draft || {}), max_uses: e.target.value === "" ? "" : clamp(e.target.value, 0, 100000) })}
                  placeholder="Unlimited"
                />
              </div>
              <div>
                <label className="bp-label">Minimum total</label>
                <input
                  className="bp-input"
                  type="number"
                  step="0.01"
                  value={draft?.min_total ?? ""}
                  onChange={(e) => setDraft({ ...(draft || {}), min_total: e.target.value === "" ? "" : e.target.value })}
                  placeholder="0.00"
                />
              </div>
            </div>

            {!isNew ? (
              <div className="bp-muted" style={{ marginTop: 10 }}>
                Uses: <strong>{uses}</strong> {maxUses !== null ? ` / ${maxUses}` : " / ∞"}
              </div>
            ) : null}
          </div>

          <div className="bp-card" style={{ marginBottom: 12 }}>
            <div className="bp-section-title">Test Promo</div>
            <div className="bp-grid" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 12, marginTop: 10 }}>
              <div>
                <label className="bp-label">Order total</label>
                <input className="bp-input" type="number" step="0.01" value={testTotal} onChange={(e) => setTestTotal(e.target.value)} />
              </div>
              <div>
                <label className="bp-label">Result</label>
                <div className="bp-card-lite" style={{ padding: 12, borderRadius: 14 }}>
                  <div className="bp-muted" style={{ fontSize: 12 }}>Qualifies: <strong>{qualifies ? "Yes" : "No"}</strong></div>
                  <div style={{ marginTop: 6, fontWeight: 900 }}>
                    Discount: {type === "percent" ? `${amount}%` : fmtMoney(amount)}
                    {" "}
                    → {fmtMoney(appliedDiscount)}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div style={{ display: "flex", gap: 10, justifyContent: "flex-end", flexWrap: "wrap" }}>
            {!isNew ? (
              <button className="bp-btn bp-btn-danger" type="button" onClick={del} disabled={saving}>Delete</button>
            ) : null}
            <button className="bp-btn" type="button" onClick={onClose} disabled={saving}>Cancel</button>
            <button className="bp-btn bp-btn-primary" type="button" onClick={save} disabled={saving}>
              {saving ? "Saving..." : "Save"}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function PromoCodesScreen() {
  const isPro = Boolean(Number(window.BP_ADMIN?.isPro || 0));
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [toast, setToast] = useState("");

  const [q, setQ] = useState("");
  const [status, setStatus] = useState("all");
  const [type, setType] = useState("all");

  const [drawerOpen, setDrawerOpen] = useState(false);
  const [selected, setSelected] = useState(null);
  const [busyId, setBusyId] = useState(null);

  const toastTimer = useRef(null);
  const showToast = (msg) => {
    setToast(msg);
    if (toastTimer.current) clearTimeout(toastTimer.current);
    toastTimer.current = setTimeout(() => setToast(""), 2500);
  };

  async function load() {
    setLoading(true);
    setErr("");
    try {
      const resp = await bpFetch(`/admin/promo-codes?q=${encodeURIComponent(q || "")}`);
      setRows(resp?.data || []);
    } catch (e) {
      setRows([]);
      setErr(e.message || "Failed to load promo codes");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (!isPro) {
      setLoading(false);
      setRows([]);
      return;
    }
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isPro]);

  const filtered = useMemo(() => {
    const now = new Date();
    const needle = String(q || "").trim().toLowerCase();
    let list = [...(rows || [])];

    if (needle) {
      list = list.filter((r) => String(r.code || "").toLowerCase().includes(needle));
    }

    if (type !== "all") {
      list = list.filter((r) => String(r.type || "percent") === type);
    }

    if (status !== "all") {
      list = list.filter((r) => statusFor(r, now) === status);
    }

    return list;
  }, [rows, q, status, type]);

  const kpis = useMemo(() => {
    const now = new Date();
    const out = { total: rows.length, active: 0, disabled: 0, scheduled: 0, expired: 0, uses: 0 };
    for (const r of rows || []) {
      out.uses += Number(r.uses_count) || 0;
      const s = statusFor(r, now);
      out[s] += 1;
    }
    return out;
  }, [rows]);

  const openNew = () => {
    setSelected({
      code: genCode(),
      type: "percent",
      amount: 10,
      starts_at: "",
      ends_at: "",
      max_uses: "",
      min_total: "",
      is_active: 1,
    });
    setDrawerOpen(true);
  };

  const openEdit = (row) => {
    setSelected({
      ...row,
      max_uses: row.max_uses === null ? "" : row.max_uses,
      min_total: row.min_total === null ? "" : row.min_total,
    });
    setDrawerOpen(true);
  };

  const onSaved = (saved) => {
    if (!saved) return;
    setRows((prev) => {
      const list = [...(prev || [])];
      const idx = list.findIndex((r) => Number(r.id) === Number(saved.id));
      if (idx >= 0) list[idx] = saved;
      else list.unshift(saved);
      return list;
    });
    showToast("Saved.");
  };

  const onDeleted = (id) => {
    setRows((prev) => (prev || []).filter((r) => Number(r.id) !== Number(id)));
    showToast("Deleted.");
  };

  async function duplicate(row) {
    setBusyId(row.id);
    setErr("");
    try {
      const resp = await bpFetch(`/admin/promo-codes/${row.id}/duplicate`, { method: "POST", body: {} });
      if (resp?.data) {
        setRows((prev) => [resp.data, ...(prev || [])]);
        showToast("Duplicated.");
      }
    } catch (e) {
      setErr(e.message || "Duplicate failed");
    } finally {
      setBusyId(null);
    }
  }

  async function toggleActive(row) {
    setBusyId(row.id);
    setErr("");
    try {
      const next = row.is_active ? 0 : 1;
      const resp = await bpFetch(`/admin/promo-codes/${row.id}`, { method: "PATCH", body: { is_active: next } });
      if (resp?.data) {
        setRows((prev) => prev.map((r) => (r.id === row.id ? resp.data : r)));
      }
      showToast(next ? "Enabled." : "Disabled.");
    } catch (e) {
      setErr(e.message || "Update failed");
    } finally {
      setBusyId(null);
    }
  }

  async function delRow(row) {
    if (!confirm(`Delete promo code ${row.code}? This cannot be undone.`)) return;
    setBusyId(row.id);
    setErr("");
    try {
      await bpFetch(`/admin/promo-codes/${row.id}`, { method: "DELETE" });
      setRows((prev) => prev.filter((r) => r.id !== row.id));
      showToast("Deleted.");
    } catch (e) {
      setErr(e.message || "Delete failed");
    } finally {
      setBusyId(null);
    }
  }

  if (!isPro) {
    return <UpgradeToPro feature="Promo Codes" />;
  }

  return (
    <div className="bp-content">
      {toast ? <div className="bp-toast bp-toast-success">{toast}</div> : null}

      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Promo Codes</div>
          <div className="bp-muted">Create discounts and track usage.</div>
        </div>
        <div className="bp-head-actions">
          <button className="bp-btn" type="button" onClick={load} disabled={loading}>Refresh</button>
          <button className="bp-primary-btn" type="button" onClick={openNew}>+ New promo code</button>
        </div>
      </div>

      {err ? <div className="bp-alert bp-alert-error">{err}</div> : null}

      <div className="bp-card" style={{ padding: 14, marginBottom: 14 }}>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(160px, 1fr))", gap: 10 }}>
          <div className="bp-card-lite" style={{ padding: 12 }}><div className="bp-muted" style={{ fontSize: 12 }}>Active</div><div style={{ fontWeight: 950, fontSize: 16 }}>{kpis.active}</div></div>
          <div className="bp-card-lite" style={{ padding: 12 }}><div className="bp-muted" style={{ fontSize: 12 }}>Scheduled</div><div style={{ fontWeight: 950, fontSize: 16 }}>{kpis.scheduled}</div></div>
          <div className="bp-card-lite" style={{ padding: 12 }}><div className="bp-muted" style={{ fontSize: 12 }}>Expired</div><div style={{ fontWeight: 950, fontSize: 16 }}>{kpis.expired}</div></div>
          <div className="bp-card-lite" style={{ padding: 12 }}><div className="bp-muted" style={{ fontSize: 12 }}>Disabled</div><div style={{ fontWeight: 950, fontSize: 16 }}>{kpis.disabled}</div></div>
          <div className="bp-card-lite" style={{ padding: 12 }}><div className="bp-muted" style={{ fontSize: 12 }}>Total uses</div><div style={{ fontWeight: 950, fontSize: 16 }}>{kpis.uses}</div></div>
        </div>
      </div>

      <div className="bp-card" style={{ padding: 14, marginBottom: 14 }}>
        <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
          <input className="bp-input" placeholder="Search code..." value={q} onChange={(e) => setQ(e.target.value)} style={{ flex: "1 1 220px" }} />
          <select className="bp-input" value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="all">Status: All</option>
            <option value="active">Active</option>
            <option value="scheduled">Scheduled</option>
            <option value="expired">Expired</option>
            <option value="disabled">Disabled</option>
          </select>
          <select className="bp-input" value={type} onChange={(e) => setType(e.target.value)}>
            <option value="all">Type: All</option>
            <option value="percent">Percent</option>
            <option value="fixed">Fixed</option>
          </select>
          <div className="bp-muted" style={{ marginLeft: "auto" }}>{loading ? "Loading..." : `${filtered.length} shown`}</div>
        </div>
      </div>

      {loading ? (
        <div className="bp-card">Loading...</div>
      ) : filtered.length === 0 ? (
        <div className="bp-card">No promo codes found.</div>
      ) : (
        <div className="bp-card" style={{ padding: 0, overflow: "hidden" }}>
          <div style={{ padding: 12, borderBottom: "1px solid rgba(15,23,42,.06)", display: "flex", gap: 12, fontSize: 12, fontWeight: 900, color: "#64748b" }}>
            <div style={{ flex: "0 0 190px" }}>Code</div>
            <div style={{ flex: "0 0 120px" }}>Discount</div>
            <div style={{ flex: "0 0 120px" }}>Status</div>
            <div style={{ flex: "1 1 220px" }}>Rules</div>
            <div style={{ flex: "0 0 110px", textAlign: "right" }}>Usage</div>
            <div style={{ flex: "0 0 280px", textAlign: "right" }}>Actions</div>
          </div>

          {filtered.map((r) => {
            const s = statusFor(r);
            const discount = (r.type || "percent") === "percent" ? `${Number(r.amount) || 0}%` : fmtMoney(r.amount);
            const usage = `${Number(r.uses_count) || 0} / ${r.max_uses === null || r.max_uses === undefined ? "∞" : r.max_uses}`;
            const rules = [
              r.min_total !== null && r.min_total !== undefined ? `Min: ${fmtMoney(r.min_total)}` : null,
              r.starts_at ? `From: ${r.starts_at}` : null,
              r.ends_at ? `To: ${r.ends_at}` : null,
            ].filter(Boolean).join(" · ") || "—";

            return (
              <div key={r.id} style={{ display: "flex", gap: 12, alignItems: "center", padding: 12, borderTop: "1px solid rgba(15,23,42,.06)", flexWrap: "wrap" }}>
                <div style={{ flex: "0 0 190px", minWidth: 0, display: "flex", gap: 8, alignItems: "center" }}>
                  <code style={{ fontWeight: 900, background: "#f1f5f9", padding: "6px 10px", borderRadius: 10, overflow: "hidden", textOverflow: "ellipsis" }}>{r.code}</code>
                  <button className="bp-btn-sm" type="button" onClick={async () => { await copyText(r.code); showToast("Copied."); }}>Copy</button>
                </div>
                <div style={{ flex: "0 0 120px", fontWeight: 900 }}>{discount}</div>
                <div style={{ flex: "0 0 120px" }}>
                  <span className={`bp-ff-pill ${s === "active" ? "bp-ff-pill--req" : s === "disabled" ? "bp-ff-pill--off" : "bp-ff-pill--muted"}`}>
                    {s}
                  </span>
                </div>
                <div style={{ flex: "1 1 220px" }} className="bp-muted">{rules}</div>
                <div style={{ flex: "0 0 110px", textAlign: "right", fontWeight: 900 }}>{usage}</div>
                <div style={{ flex: "0 0 280px", textAlign: "right", display: "flex", justifyContent: "flex-end", gap: 8, flexWrap: "wrap" }}>
                  <button className="bp-btn-sm" type="button" onClick={() => openEdit(r)}>Edit</button>
                  <button className="bp-btn-sm" type="button" disabled={busyId === r.id} onClick={() => duplicate(r)}>
                    {busyId === r.id ? "..." : "Duplicate"}
                  </button>
                  <button className={`bp-btn-sm ${r.is_active ? "" : "bp-btn-danger"}`} type="button" disabled={busyId === r.id} onClick={() => toggleActive(r)}>
                    {r.is_active ? "Disable" : "Enable"}
                  </button>
                  <button className="bp-btn-sm bp-btn-danger" type="button" disabled={busyId === r.id} onClick={() => delRow(r)}>Delete</button>
                </div>
              </div>
            );
          })}
        </div>
      )}

      <PromoDrawer
        open={drawerOpen}
        promo={selected}
        onClose={() => setDrawerOpen(false)}
        onSaved={onSaved}
        onDeleted={onDeleted}
      />
    </div>
  );
}
