import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";
import UpgradeToPro from "../components/UpgradeToPro";

const monthName = (yyyyMmDd) => {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(yyyyMmDd || "").slice(0, 10));
  if (!m) return "Unknown";
  const month = parseInt(m[2], 10);
  const names = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December",
  ];
  return names[Math.max(0, Math.min(11, month - 1))] || "Unknown";
};

const toIso = (d) => {
  try {
    return new Date(d).toISOString().slice(0, 10);
  } catch (e) {
    return "";
  }
};

const todayIso = () => toIso(new Date());

function formatRange(start, end) {
  const s = String(start || "").slice(0, 10);
  const e = String(end || "").slice(0, 10);
  return s && e && s !== e ? `${s} - ${e}` : s || e || "—";
}

function agentLabel(a) {
  return a?.name || `${a?.first_name || ""} ${a?.last_name || ""}`.trim() || (a?.id ? `#${a.id}` : "Agent");
}

function HolidayModal({ agents, row, onClose, onSaved }) {
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [form, setForm] = useState(() => ({
    title: row?.title || "Holiday",
    start_date: String(row?.start_date || "").slice(0, 10) || todayIso(),
    end_date: String(row?.end_date || "").slice(0, 10) || todayIso(),
    scope: row?.agent_id ? "agent" : "global",
    agent_id: row?.agent_id ? Number(row.agent_id) : 0,
    is_recurring_yearly: Boolean(Number(row?.is_recurring_yearly) || row?.is_recurring_yearly),
    is_enabled: row?.is_enabled === undefined ? true : Boolean(Number(row.is_enabled)),
  }));

  const canSave = form.start_date && form.end_date && (!form.agent_id || form.scope === "agent") && !saving;

  const save = async () => {
    setSaving(true);
    setError("");
    try {
      const payload = {
        title: form.title || "Holiday",
        start_date: form.start_date,
        end_date: form.end_date,
        agent_id: form.scope === "agent" ? (form.agent_id || 0) : 0,
        is_recurring_yearly: !!form.is_recurring_yearly,
        is_enabled: !!form.is_enabled,
      };
      await bpFetch(`/admin/holidays/${row.id}`, { method: "PATCH", body: payload });
      onSaved();
      onClose();
    } catch (e) {
      setError(e?.message || "Save failed");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="bp-modal-overlay" role="dialog" aria-modal="true">
      <div className="bp-modal bp-w-720 bp-card bp-holidays-modal" onMouseDown={(e) => e.stopPropagation()}>
        <div className="bp-card-head" style={{ padding: 14, borderBottom: "1px solid rgba(15,23,42,.08)" }}>
          <div style={{ minWidth: 0 }}>
            <div className="bp-section-title" style={{ margin: 0 }}>Edit holiday</div>
            <div className="bp-muted bp-text-xs">{formatRange(row.start_date, row.end_date)}</div>
          </div>
          <button type="button" className="bp-btn" onClick={onClose}>Close</button>
        </div>

        <div style={{ padding: 14, display: "grid", gap: 12 }}>
          {error ? <div className="bp-alert bp-alert-error">{error}</div> : null}

          <div className="bp-grid bp-grid-2 bp-gap-10">
            <div>
              <label className="bp-label">Title</label>
              <input className="bp-input-field" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
            </div>
            <div className="bp-grid bp-grid-2 bp-gap-10">
              <div>
                <label className="bp-label">From</label>
                <input type="date" className="bp-input-field" value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} />
              </div>
              <div>
                <label className="bp-label">To</label>
                <input type="date" className="bp-input-field" value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} />
              </div>
            </div>
          </div>

          <div className="bp-grid bp-grid-2 bp-gap-10">
            <div>
              <label className="bp-label">Apply to</label>
              <div className="bp-pay-seg">
                <button type="button" className={`bp-pay-segbtn ${form.scope === "global" ? "is-active" : ""}`} onClick={() => setForm({ ...form, scope: "global" })}>
                  Global
                </button>
                <button type="button" className={`bp-pay-segbtn ${form.scope === "agent" ? "is-active" : ""}`} onClick={() => setForm({ ...form, scope: "agent" })}>
                  Agent
                </button>
              </div>
              {form.scope === "agent" ? (
                <div className="bp-mt-10">
                  <select className="bp-select" value={form.agent_id || 0} onChange={(e) => setForm({ ...form, agent_id: parseInt(e.target.value, 10) || 0 })}>
                    <option value={0}>Select agent...</option>
                    {agents.map((a) => (
                      <option key={a.id} value={a.id}>{agentLabel(a)}</option>
                    ))}
                  </select>
                </div>
              ) : null}
            </div>

            <div className="bp-grid bp-grid-2 bp-gap-10">
              <div>
                <label className="bp-label">Repeat yearly</label>
                <label className="bp-switch">
                  <input type="checkbox" checked={!!form.is_recurring_yearly} onChange={(e) => setForm({ ...form, is_recurring_yearly: e.target.checked })} />
                  <span className="bp-slider" />
                </label>
              </div>
              <div>
                <label className="bp-label">Enabled</label>
                <label className="bp-switch">
                  <input type="checkbox" checked={!!form.is_enabled} onChange={(e) => setForm({ ...form, is_enabled: e.target.checked })} />
                  <span className="bp-slider" />
                </label>
              </div>
            </div>
          </div>
        </div>

        <div className="bp-payments-bar" style={{ borderRadius: 0, borderLeft: 0, borderRight: 0, borderBottom: 0 }}>
          <button type="button" className="bp-btn bp-btn-ghost" onClick={onClose}>Cancel</button>
          <button type="button" className="bp-btn bp-btn-primary" disabled={!canSave} onClick={save}>
            {saving ? "Saving..." : "Save changes"}
          </button>
        </div>
      </div>
    </div>
  );
}

export default function HolidaysScreen({ embedded = false }) {
  const isPro = Boolean(Number(window.BP_ADMIN?.isPro || 0));
  const [rows, setRows] = useState([]);
  const [agents, setAgents] = useState([]);

  const [filterAgentId, setFilterAgentId] = useState(0); // 0 = global only; >0 = global+agent
  const [year, setYear] = useState(new Date().getFullYear());
  const [q, setQ] = useState("");

  const [form, setForm] = useState(() => ({
    title: "",
    start_date: todayIso(),
    end_date: todayIso(),
    scope: "global",
    agent_id: 0,
    is_recurring_yearly: false,
  }));

  const [loading, setLoading] = useState(false);
  const [toast, setToast] = useState(null);
  const [editRow, setEditRow] = useState(null);

  const pushToast = (type, msg) => setToast({ type, msg });

  const load = async () => {
    if (!isPro) return;
    setLoading(true);
    try {
      const agentParam = filterAgentId ? `&agent_id=${encodeURIComponent(filterAgentId)}` : "";
      const res = await bpFetch(`/admin/holidays?year=${year}${agentParam}`);
      setRows(res?.data || []);
    } catch (e) {
      pushToast("error", e.message || "Load failed");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!isPro) return;
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [year, filterAgentId, isPro]);

  useEffect(() => {
    (async () => {
      if (!isPro) return;
      try {
        const res = await bpFetch("/admin/agents");
        setAgents(res?.data || []);
      } catch (e) {
        setAgents([]);
      }
    })();
  }, [isPro]);

  const agentNameMap = useMemo(() => {
    const map = new Map();
    agents.forEach((a) => map.set(a.id, agentLabel(a)));
    return map;
  }, [agents]);

  const filtered = useMemo(() => {
    const needle = String(q || "").trim().toLowerCase();
    if (!needle) return rows;
    return (rows || []).filter((r) => String(r.title || "Holiday").toLowerCase().includes(needle));
  }, [rows, q]);

  const grouped = useMemo(() => {
    const map = new Map();
    for (const r of filtered) {
      const key = monthName(String(r.start_date || "").slice(0, 10));
      if (!map.has(key)) map.set(key, []);
      map.get(key).push(r);
    }
    return Array.from(map.entries());
  }, [filtered]);

  const stats = useMemo(() => {
    const s = { total: 0, recurring: 0, disabled: 0, agent: 0 };
    for (const r of rows || []) {
      s.total += 1;
      if (Number(r.is_recurring_yearly) === 1 || r.is_recurring_yearly) s.recurring += 1;
      if (Number(r.is_enabled) === 0) s.disabled += 1;
      if (r.agent_id) s.agent += 1;
    }
    return s;
  }, [rows]);

  const templates = useMemo(() => {
    const y = year || new Date().getFullYear();
    const mk = (title, mm, dd, mm2 = null, dd2 = null) => ({
      title,
      start: `${y}-${String(mm).padStart(2, "0")}-${String(dd).padStart(2, "0")}`,
      end: `${y}-${String(mm2 || mm).padStart(2, "0")}-${String(dd2 || dd).padStart(2, "0")}`,
    });
    return [
      mk("New Year's Day", 1, 1),
      mk("Christmas Day", 12, 25),
      mk("Boxing Day", 12, 26),
      mk("Christmas (2 days)", 12, 25, 12, 26),
      mk("Independence Day", 7, 4),
    ];
  }, [year]);

  const applyTemplate = (t) => {
    setForm((prev) => ({
      ...prev,
      title: t.title,
      start_date: t.start,
      end_date: t.end,
      is_recurring_yearly: true,
    }));
  };

  const create = async (e) => {
    e.preventDefault();
    if (form.scope === "agent" && !form.agent_id) {
      pushToast("error", "Select an agent for agent-specific holidays.");
      return;
    }
    try {
      const payload = {
        title: form.title || "Holiday",
        start_date: form.start_date,
        end_date: form.end_date,
        agent_id: form.scope === "agent" ? form.agent_id : null,
        is_recurring: false,
        is_recurring_yearly: form.is_recurring_yearly || false,
        is_enabled: true,
      };
      await bpFetch("/admin/holidays", { method: "POST", body: payload });
      pushToast("success", "Holiday added.");
      setForm((prev) => ({
        ...prev,
        title: "",
        start_date: todayIso(),
        end_date: todayIso(),
        scope: "global",
        agent_id: 0,
        is_recurring_yearly: false,
      }));
      load();
    } catch (e2) {
      pushToast("error", e2.message || "Create failed");
    }
  };

  const toggleEnabled = async (row) => {
    try {
      const next = !(Number(row.is_enabled) === 1 || row.is_enabled === true);
      await bpFetch(`/admin/holidays/${row.id}`, { method: "PATCH", body: { is_enabled: next } });
      pushToast("success", next ? "Enabled." : "Disabled.");
      setRows((prev) => prev.map((r) => (r.id === row.id ? { ...r, is_enabled: next ? 1 : 0 } : r)));
    } catch (e) {
      pushToast("error", e.message || "Update failed");
    }
  };

  const remove = async (id) => {
    if (!confirm("Delete this holiday?")) return;
    try {
      await bpFetch(`/admin/holidays/${id}`, { method: "DELETE" });
      pushToast("success", "Deleted.");
      load();
    } catch (e) {
      pushToast("error", e.message || "Delete failed");
    }
  };

  const wrapClass = embedded ? "bp-holidays bp-holidays--embedded" : "bp-content bp-holidays";

  if (!isPro) {
    return <UpgradeToPro feature="Holidays" />;
  }

  return (
    <div className={wrapClass}>
      {toast ? (
        <div className={`bp-toast ${toast.type === "success" ? "bp-toast-success" : "bp-alert bp-alert-error"}`}>
          <span>{toast.msg}</span>
          <button className="bp-link" style={{ float: "right" }} onClick={() => setToast(null)} aria-label="Close">
            Close
          </button>
        </div>
      ) : null}

      {!embedded ? (
        <div className="bp-page-head">
          <div>
            <div className="bp-h1">Holidays</div>
            <div className="bp-muted">Holidays override the weekly schedule and remove availability.</div>
          </div>
        </div>
      ) : null}

      <div className="bp-holidays__layout">
        <section className="bp-card bp-holidays__list">
          <div className="bp-card-head bp-holidays__head">
            <div style={{ minWidth: 0 }}>
              <div className="bp-section-title" style={{ margin: 0 }}>Holidays</div>
              <div className="bp-muted bp-text-xs">Add date exceptions (global or per agent).</div>
            </div>
            <div className="bp-holidays__stats">
              <span className="bp-hol-tag">Total {stats.total}</span>
              <span className="bp-hol-tag">Yearly {stats.recurring}</span>
              <span className="bp-hol-tag">Disabled {stats.disabled}</span>
            </div>
          </div>

          <div className="bp-holidays__filters">
            <div className="bp-holidays__filter">
              <label className="bp-label">Year</label>
              <input
                className="bp-input-field"
                style={{ width: 140 }}
                type="number"
                value={year}
                onChange={(e) => setYear(parseInt(e.target.value || "0", 10) || new Date().getFullYear())}
              />
            </div>

            <div className="bp-holidays__filter">
              <label className="bp-label">Scope</label>
              <select className="bp-select" value={filterAgentId} onChange={(e) => setFilterAgentId(parseInt(e.target.value, 10) || 0)}>
                <option value={0}>Global only</option>
                {agents.map((a) => (
                  <option key={a.id} value={a.id}>{agentLabel(a)} (global + agent)</option>
                ))}
              </select>
            </div>

            <div className="bp-holidays__filter bp-holidays__filter--search">
              <label className="bp-label">Search</label>
              <input className="bp-input-field" placeholder="Search holidays..." value={q} onChange={(e) => setQ(e.target.value)} />
            </div>
          </div>

          <div className="bp-holidays__note">
            <span className="bp-muted bp-text-sm">
              Tip: keep agent-specific holidays for personal time off, and use global holidays for public holidays.
            </span>
          </div>

          {loading ? <div className="bp-muted" style={{ padding: 14 }}>Loading...</div> : null}
          {!loading && filtered.length === 0 ? (
            <div className="bp-muted" style={{ padding: 14 }}>
              No holidays yet. Add one on the right.
            </div>
          ) : null}

          {!loading && filtered.length > 0 ? (
            <div className="bp-holidays__rows">
              {grouped.map(([m, list]) => (
                <div key={m} className="bp-holidays__month">
                  <div className="bp-holidays__monthTitle">{m}</div>
                  <div className="bp-holidays__monthRows">
                    {list.map((r) => {
                      const isEnabled = Number(r.is_enabled) !== 0;
                      const isYearly = Number(r.is_recurring_yearly) === 1 || r.is_recurring_yearly;
                      const scope = r.agent_id ? (agentNameMap.get(r.agent_id) || `Agent #${r.agent_id}`) : "Global";
                      return (
                        <div key={r.id} className="bp-holidays__row">
                          <div className="bp-holidays__rowMain">
                            <div className="bp-holidays__rowTitle">
                              <span>{r.title || "Holiday"}</span>
                              {isYearly ? <span className="bp-hol-pill">Yearly</span> : null}
                              {!isEnabled ? <span className="bp-hol-pill bp-hol-pill--off">Disabled</span> : null}
                              {r.agent_id ? <span className="bp-hol-pill bp-hol-pill--agent">Agent</span> : null}
                            </div>
                            <div className="bp-holidays__rowMeta">
                              <span className="bp-muted">{formatRange(r.start_date, r.end_date)}</span>
                              <span className="bp-holidays__dot">•</span>
                              <span className="bp-muted">{scope}</span>
                            </div>
                          </div>

                          <div className="bp-holidays__rowActions">
                            <label className="bp-switch" title={isEnabled ? "Enabled" : "Disabled"}>
                              <input type="checkbox" checked={isEnabled} onChange={() => toggleEnabled(r)} />
                              <span className="bp-slider" />
                            </label>
                            <button type="button" className="bp-btn-sm" onClick={() => setEditRow(r)}>Edit</button>
                            <button type="button" className="bp-btn-sm bp-btn-danger" onClick={() => remove(r.id)}>Delete</button>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              ))}
            </div>
          ) : null}
        </section>

        <aside className="bp-card bp-holidays__add">
          <div className="bp-section-title" style={{ marginBottom: 10 }}>Add holiday</div>
          <form onSubmit={create} className="bp-holidays__form">
            <div>
              <label className="bp-label">Title</label>
              <input className="bp-input-field" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} placeholder="Holiday name" />
            </div>

            <div className="bp-grid bp-grid-2 bp-gap-10">
              <div>
                <label className="bp-label">From</label>
                <input type="date" className="bp-input-field" value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} />
              </div>
              <div>
                <label className="bp-label">To</label>
                <input type="date" className="bp-input-field" value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} />
              </div>
            </div>

            <div>
              <label className="bp-label">Apply to</label>
              <div className="bp-pay-seg">
                <button type="button" className={`bp-pay-segbtn ${form.scope === "global" ? "is-active" : ""}`} onClick={() => setForm({ ...form, scope: "global", agent_id: 0 })}>
                  Global
                </button>
                <button type="button" className={`bp-pay-segbtn ${form.scope === "agent" ? "is-active" : ""}`} onClick={() => setForm({ ...form, scope: "agent", agent_id: form.agent_id || filterAgentId || 0 })}>
                  Agent
                </button>
              </div>
              {form.scope === "agent" ? (
                <div className="bp-mt-10">
                  <select className="bp-select" value={form.agent_id || 0} onChange={(e) => setForm({ ...form, agent_id: parseInt(e.target.value, 10) || 0 })}>
                    <option value={0}>Select agent...</option>
                    {agents.map((a) => (
                      <option key={a.id} value={a.id}>{agentLabel(a)}</option>
                    ))}
                  </select>
                </div>
              ) : null}
            </div>

            <div className="bp-holidays__formRow">
              <div>
                <label className="bp-label">Repeat yearly</label>
                <label className="bp-switch">
                  <input type="checkbox" checked={form.is_recurring_yearly || false} onChange={(e) => setForm({ ...form, is_recurring_yearly: e.target.checked })} />
                  <span className="bp-slider" />
                </label>
              </div>
              <button className="bp-btn bp-btn-primary" type="submit">Add</button>
            </div>

            <div className="bp-holidays__templates">
              <div className="bp-muted bp-text-xs" style={{ fontWeight: 900 }}>Quick templates</div>
              <div className="bp-holidays__templateBtns">
                {templates.map((t) => (
                  <button key={t.title} type="button" className="bp-chip-btn" onClick={() => applyTemplate(t)}>
                    {t.title}
                  </button>
                ))}
              </div>
            </div>
          </form>
        </aside>
      </div>

      {editRow ? (
        <HolidayModal
          agents={agents}
          row={editRow}
          onClose={() => setEditRow(null)}
          onSaved={() => {
            pushToast("success", "Saved.");
            load();
          }}
        />
      ) : null}
    </div>
  );
}
