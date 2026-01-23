import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";

export default function HolidaysScreen() {
  const [rows, setRows] = useState([]);
  const [agents, setAgents] = useState([]);
  const [agentId, setAgentId] = useState(0);
  const [year, setYear] = useState(new Date().getFullYear());
  const [form, setForm] = useState({
    title: "",
    start_date: new Date().toISOString().slice(0, 10),
    end_date: new Date().toISOString().slice(0, 10),
    scope: "global",
    is_recurring_yearly: false,
  });
  const [loading, setLoading] = useState(false);
  const [toast, setToast] = useState(null);

  const pushToast = (type, msg) => setToast({ type, msg });

  const load = async () => {
    setLoading(true);
    try {
      const agentParam = agentId ? `&agent_id=${encodeURIComponent(agentId)}` : "";
      const res = await bpFetch(`/admin/holidays?year=${year}${agentParam}`);
      setRows(res?.data || []);
    } catch (e) {
      pushToast("error", e.message || "Load failed");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); /* eslint-disable-next-line */ }, [year, agentId]);

  useEffect(() => {
    (async () => {
      try {
        const res = await bpFetch("/admin/agents");
        setAgents(res?.data || []);
      } catch (e) {
        setAgents([]);
      }
    })();
  }, []);

  const create = async (e) => {
    e.preventDefault();
    if (form.scope === "agent" && !agentId) {
      pushToast("error", "Select an agent for agent-specific holidays");
      return;
    }
    try {
      const payload = {
        title: form.title || "Holiday",
        start_date: form.start_date,
        end_date: form.end_date,
        agent_id: form.scope === "agent" ? agentId : null,
        is_recurring: false,
        is_recurring_yearly: form.is_recurring_yearly || false,
        is_enabled: true,
      };
      await bpFetch("/admin/holidays", { method: "POST", body: payload });
      pushToast("success", "Created ✅");
      setForm({ title: "", start_date: new Date().toISOString().slice(0, 10), end_date: new Date().toISOString().slice(0, 10), scope: "global", is_recurring_yearly: false });
      load();
    } catch (e2) {
      pushToast("error", e2.message || "Create failed");
    }
  };

  const remove = async (id) => {
    if (!confirm("Delete this holiday?")) return;
    try {
      await bpFetch(`/admin/holidays/${id}`, { method: "DELETE" });
      pushToast("success", "Deleted ✅");
      load();
    } catch (e) {
      pushToast("error", e.message || "Delete failed");
    }
  };

  const scopeLabel = useMemo(() => {
    if (!agentId) return "Global";
    const a = agents.find((x) => x.id === agentId);
    return a ? (a.name || `${a.first_name || ""} ${a.last_name || ""}`.trim() || `#${a.id}`) : "Agent";
  }, [agents, agentId]);

  const agentNameMap = useMemo(() => {
    const map = new Map();
    agents.forEach((a) => {
      const label = a.name || `${a.first_name || ""} ${a.last_name || ""}`.trim() || `#${a.id}`;
      map.set(a.id, label);
    });
    return map;
  }, [agents]);

  return (
    <div className="bp-container">
      <div className="bp-header">
        <div>
          <h1>Holidays</h1>
          <div className="bp-muted">Closed dates remove all availability (global or per agent).</div>
        </div>
        <div className="bp-head-actions">
          <input
            className="bp-input"
            style={{ width: 120 }}
            type="number"
            value={year}
            onChange={(e) => setYear(parseInt(e.target.value || "0", 10) || new Date().getFullYear())}
          />
          <select className="bp-input" value={agentId} onChange={(e) => setAgentId(parseInt(e.target.value, 10) || 0)}>
            <option value={0}>Global</option>
            {agents.map((a) => (
              <option key={a.id} value={a.id}>{a.name || `${a.first_name || ""} ${a.last_name || ""}`.trim() || `#${a.id}`}</option>
            ))}
          </select>
        </div>
      </div>

      <div className="bp-card" style={{ marginBottom: 14 }}>
        <div className="bp-section-title" style={{ marginBottom: 10 }}>Add holiday</div>
        <form onSubmit={create} style={{ display: "grid", gap: 10, gridTemplateColumns: "2fr 1fr 1fr 1fr auto", alignItems: "end" }}>
          <div>
            <label className="bp-label">Title</label>
            <input className="bp-input" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
          </div>
          <div>
            <label className="bp-label">From</label>
            <input type="date" className="bp-input" value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} />
          </div>
          <div>
            <label className="bp-label">To</label>
            <input type="date" className="bp-input" value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} />
          </div>
          <div>
            <label className="bp-label">Apply to</label>
            <select className="bp-input" value={form.scope} onChange={(e) => setForm({ ...form, scope: e.target.value })}>
              <option value="global">Global</option>
              <option value="agent">Agent ({scopeLabel})</option>
            </select>
          </div>
          <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
            <label className="bp-check">
              <input
                type="checkbox"
                checked={form.is_recurring_yearly || false}
                onChange={(e) => setForm({ ...form, is_recurring_yearly: e.target.checked })}
              />
              <span>Repeat yearly</span>
            </label>
          </div>
          <button className="bp-btn bp-btn-primary" type="submit">Add</button>
        </form>
      </div>

      <div className="bp-card">
        <div className="bp-section-title" style={{ marginBottom: 10 }}>Holidays list</div>
        {loading ? <div className="bp-muted">Loading…</div> : null}
        {!loading && rows.length === 0 ? <div className="bp-muted">No holidays yet.</div> : null}

        {rows.length > 0 ? (
          <div className="bp-table">
            <div className="bp-tr bp-th">
              <div>Title</div>
              <div>Dates</div>
              <div>Scope</div>
              <div>Actions</div>
            </div>
            {rows.map((r) => (
              <div key={r.id} className="bp-tr">
                <div style={{ fontWeight: 900, display: "flex", alignItems: "center", gap: 8 }}>
                  {r.title || "Holiday"}
                  {r.is_recurring_yearly ? <span className="bp-badge">Yearly</span> : null}
                  {!r.is_enabled ? <span className="bp-badge bp-badge-off">Disabled</span> : null}
                </div>
                <div>{(r.start_date || "").slice(0, 10)} → {(r.end_date || "").slice(0, 10)}</div>
                <div>{r.agent_id ? (agentNameMap.get(r.agent_id) || `Agent #${r.agent_id}`) : "Global"}</div>
                <div className="bp-row-actions">
                  <button className="bp-btn-sm" onClick={() => remove(r.id)}>Delete</button>
                </div>
              </div>
            ))}
          </div>
        ) : null}
      </div>

      {toast ? (
        <div style={{
          position: "fixed", right: 18, bottom: 18,
          background: "#0b1437", color: "#fff", padding: "10px 12px",
          borderRadius: 12, fontWeight: 900, zIndex: 999999
        }}>
          {toast.msg}
          <button onClick={() => setToast(null)} style={{
            marginLeft: 10, background: "transparent", border: "none", color: "#fff",
            cursor: "pointer", fontWeight: 900, fontSize: 16
          }}>×</button>
        </div>
      ) : null}
    </div>
  );
}
