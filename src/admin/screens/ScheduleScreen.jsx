import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";

const DAYS = [
  { k: "1", label: "Mon" },
  { k: "2", label: "Tue" },
  { k: "3", label: "Wed" },
  { k: "4", label: "Thu" },
  { k: "5", label: "Fri" },
  { k: "6", label: "Sat" },
  { k: "7", label: "Sun" },
];

const emptySchedule = () => {
  const o = {};
  for (const d of DAYS) o[d.k] = [];
  return o;
};

export default function ScheduleScreen() {
  const [agents, setAgents] = useState([]);
  const [mode, setMode] = useState("global"); // global | agent
  const [agentId, setAgentId] = useState(0);
  const [schedule, setSchedule] = useState(emptySchedule());
  const [settings, setSettings] = useState({ slot_interval_minutes: 30, timezone: "Europe/Copenhagen" });

  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(null);

  const pushToast = (type, msg) => setToast({ type, msg });

  useEffect(() => {
    (async () => {
      try {
        const res = await bpFetch("/admin/agents");
        const list = res?.data || [];
        setAgents(list);
        if (list[0]?.id) setAgentId(list[0].id);
      } catch (e) {
        setAgents([]);
      }
    })();
  }, []);

  const loadSchedule = async () => {
    setLoading(true);
    try {
      const targetId = mode === "agent" ? agentId : 0;
      const res = await bpFetch(`/admin/schedule?agent_id=${targetId}`);
      setSchedule(res?.data?.schedule || emptySchedule());
      setSettings(res?.data?.settings || { slot_interval_minutes: 30, timezone: "Europe/Copenhagen" });
    } catch (e) {
      pushToast("error", e.message || "Failed to load schedule");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (mode === "agent" && !agentId) return;
    loadSchedule();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [mode, agentId]);

  const addInterval = (dayKey) => {
    setSchedule((prev) => ({
      ...prev,
      [dayKey]: [
        ...(prev[dayKey] || []),
        { start_time: "09:00", end_time: "17:00", is_enabled: true, breaks: [] },
      ],
    }));
  };

  const updateInterval = (dayKey, idx, patch) => {
    setSchedule((prev) => {
      const list = [...(prev[dayKey] || [])];
      list[idx] = { ...list[idx], ...patch };
      return { ...prev, [dayKey]: list };
    });
  };

  const removeInterval = (dayKey, idx) => {
    setSchedule((prev) => {
      const list = [...(prev[dayKey] || [])];
      list.splice(idx, 1);
      return { ...prev, [dayKey]: list };
    });
  };

  const addBreak = (dayKey, idx) => {
    setSchedule((prev) => {
      const list = [...(prev[dayKey] || [])];
      const breaks = [...(list[idx]?.breaks || [])];
      breaks.push({ start: "12:00", end: "13:00" });
      list[idx] = { ...list[idx], breaks };
      return { ...prev, [dayKey]: list };
    });
  };

  const updateBreak = (dayKey, idx, bIdx, patch) => {
    setSchedule((prev) => {
      const list = [...(prev[dayKey] || [])];
      const breaks = [...(list[idx]?.breaks || [])];
      breaks[bIdx] = { ...breaks[bIdx], ...patch };
      list[idx] = { ...list[idx], breaks };
      return { ...prev, [dayKey]: list };
    });
  };

  const removeBreak = (dayKey, idx, bIdx) => {
    setSchedule((prev) => {
      const list = [...(prev[dayKey] || [])];
      const breaks = [...(list[idx]?.breaks || [])];
      breaks.splice(bIdx, 1);
      list[idx] = { ...list[idx], breaks };
      return { ...prev, [dayKey]: list };
    });
  };

  const save = async () => {
    const targetId = mode === "agent" ? agentId : 0;
    if (mode === "agent" && !targetId) return;
    setSaving(true);
    try {
      await bpFetch("/admin/schedule", {
        method: "POST",
        body: {
          agent_id: targetId,
          schedule,
          settings,
        },
      });
      pushToast("success", "Schedule saved ✅");
      await loadSchedule();
    } catch (e) {
      pushToast("error", e.message || "Save failed");
    } finally {
      setSaving(false);
    }
  };

  const agentLabel = useMemo(() => {
    const a = agents.find((x) => x.id === agentId);
    if (!a) return "Agent";
    return a.name || `${a.first_name || ""} ${a.last_name || ""}`.trim() || `#${a.id}`;
  }, [agents, agentId]);

  const [viewTab, setViewTab] = useState("edit"); // edit | week

  return (
    <div className="bp-container">
      <div className="bp-header">
        <div>
          <h1>Schedule</h1>
          <div className="bp-muted">Define working hours, breaks, and availability settings.</div>
        </div>

        <div className="bp-head-actions">
          <div className="bp-seg">
            <button className={`bp-seg-btn ${viewTab === "edit" ? "active" : ""}`} onClick={() => setViewTab("edit")}>Edit Schedule</button>
            <button className={`bp-seg-btn ${viewTab === "week" ? "active" : ""}`} onClick={() => setViewTab("week")}>Week View</button>
          </div>

          <div className="bp-seg">
            <button className={`bp-seg-btn ${mode === "global" ? "active" : ""}`} onClick={() => setMode("global")}>Global</button>
            <button className={`bp-seg-btn ${mode === "agent" ? "active" : ""}`} onClick={() => setMode("agent")}>Per Agent</button>
          </div>

          {mode === "agent" ? (
            <select className="bp-input" value={agentId} onChange={(e) => setAgentId(parseInt(e.target.value, 10) || 0)}>
              {agents.map((a) => (
                <option key={a.id} value={a.id}>{a.name || `${a.first_name || ""} ${a.last_name || ""}`.trim() || `#${a.id}`}</option>
              ))}
            </select>
          ) : null}

          {viewTab === "edit" ? (
            <button className="bp-btn bp-btn-primary" onClick={save} disabled={saving || loading}>
              {saving ? "Saving…" : "Save Schedule"}
            </button>
          ) : null}
        </div>
      </div>

      <div className="bp-grid" style={{ gridTemplateColumns: viewTab === "week" ? "1fr" : "1.6fr 1fr" }}>
        {viewTab === "edit" ? (
          <>
            <div className="bp-card" style={{ marginBottom: 14 }}>
              <div className="bp-section-title" style={{ marginBottom: 12 }}>
                {mode === "global" ? "Global weekly schedule" : `${agentLabel} weekly schedule`}
              </div>

              {loading ? (
                <div className="bp-muted">Loading…</div>
              ) : (
                <div style={{ display: "grid", gap: 12 }}>
                  {DAYS.map((d) => (
                    <div key={d.k} style={{ borderTop: "1px solid var(--bp-border)", paddingTop: 10 }}>
                      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 8 }}>
                        <div style={{ fontWeight: 1000 }}>{d.label}</div>
                        <button className="bp-btn-sm" onClick={() => addInterval(d.k)}>+ Add interval</button>
                      </div>

                      {(schedule[d.k] || []).length === 0 ? (
                        <div className="bp-muted" style={{ marginBottom: 6 }}>No intervals</div>
                      ) : null}

                      {(schedule[d.k] || []).map((it, idx) => (
                        <div key={`${d.k}-${idx}`} style={{ display: "grid", gap: 8, marginBottom: 10 }}>
                          <div style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
                            <input type="time" className="bp-input" style={{ width: 140 }} value={it.start_time || ""}
                              onChange={(e) => updateInterval(d.k, idx, { start_time: e.target.value })} />
                            <span style={{ fontWeight: 900 }}>→</span>
                            <input type="time" className="bp-input" style={{ width: 140 }} value={it.end_time || ""}
                              onChange={(e) => updateInterval(d.k, idx, { end_time: e.target.value })} />
                            <label style={{ display: "flex", alignItems: "center", gap: 6, fontWeight: 900 }}>
                              <input type="checkbox" checked={!!it.is_enabled}
                                onChange={(e) => updateInterval(d.k, idx, { is_enabled: e.target.checked })} />
                              Enabled
                            </label>
                            <button className="bp-btn-sm" onClick={() => removeInterval(d.k, idx)}>Remove</button>
                          </div>

                          <div style={{ display: "grid", gap: 6, paddingLeft: 8 }}>
                            {(it.breaks || []).map((b, bIdx) => (
                              <div key={`${d.k}-${idx}-${bIdx}`} style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
                                <span className="bp-muted" style={{ fontWeight: 900 }}>Break</span>
                                <input type="time" className="bp-input" style={{ width: 120 }} value={b.start || ""}
                                  onChange={(e) => updateBreak(d.k, idx, bIdx, { start: e.target.value })} />
                                <span style={{ fontWeight: 900 }}>→</span>
                                <input type="time" className="bp-input" style={{ width: 120 }} value={b.end || ""}
                                  onChange={(e) => updateBreak(d.k, idx, bIdx, { end: e.target.value })} />
                                <button className="bp-btn-sm" onClick={() => removeBreak(d.k, idx, bIdx)}>Remove</button>
                              </div>
                            ))}
                            <button className="bp-btn-sm" onClick={() => addBreak(d.k, idx)}>+ Add break</button>
                          </div>
                        </div>
                      ))}
                    </div>
                  ))}
                </div>
              )}
            </div>

            <div className="bp-card" style={{ marginBottom: 14 }}>
              <div className="bp-section-title" style={{ marginBottom: 12 }}>Schedule Settings</div>
              <div style={{ display: "grid", gap: 12 }}>
                <div>
                  <label className="bp-label">Slot interval (minutes)</label>
                  <input
                    type="number"
                    className="bp-input"
                    min={5}
                    max={120}
                    value={settings.slot_interval_minutes ?? 30}
                    onChange={(e) => setSettings({ ...settings, slot_interval_minutes: parseInt(e.target.value || "30", 10) })}
                  />
                </div>
                <div>
                  <label className="bp-label">Timezone</label>
                  <input
                    type="text"
                    className="bp-input"
                    value={settings.timezone || ""}
                    onChange={(e) => setSettings({ ...settings, timezone: e.target.value })}
                    placeholder="Europe/Copenhagen"
                  />
                </div>
              </div>
            </div>
          </>
        ) : null}

        {viewTab === "week" ? (
          <div className="bp-card">
            <div className="bp-section-title" style={{ marginBottom: 16 }}>
              {mode === "global" ? "Global weekly schedule" : `${agentLabel} weekly schedule`}
            </div>

            {loading ? (
              <div className="bp-muted">Loading…</div>
            ) : (
              <div style={{ display: "grid", gridTemplateColumns: "repeat(7, 1fr)", gap: 12 }}>
                {DAYS.map((d) => {
                  const intervals = schedule[d.k] || [];
                  const hasIntervals = intervals.length > 0 && intervals.some(it => it.is_enabled !== false);

                  return (
                    <div key={d.k} style={{
                      border: "1px solid var(--bp-border)",
                      borderRadius: 8,
                      padding: 12,
                      background: hasIntervals ? "rgba(67, 24, 255, 0.04)" : "rgba(0, 0, 0, 0.02)",
                      minHeight: 200
                    }}>
                      <div style={{ fontWeight: 1000, marginBottom: 10, textAlign: "center" }}>{d.label}</div>

                      {!hasIntervals ? (
                        <div className="bp-muted" style={{ fontSize: 12, textAlign: "center" }}>Closed</div>
                      ) : (
                        <div style={{ display: "grid", gap: 8, fontSize: 12 }}>
                          {intervals.map((it, idx) => {
                            if (it.is_enabled === false) return null;

                            return (
                              <div key={idx}>
                                <div style={{ fontWeight: 900, marginBottom: 4 }}>
                                  {it.start_time || "?"} – {it.end_time || "?"}
                                </div>

                                {(it.breaks || []).length > 0 ? (
                                  <div style={{ paddingLeft: 8, borderLeft: "2px solid rgba(255, 107, 107, 0.3)" }}>
                                    {it.breaks.map((b, bIdx) => (
                                      <div key={bIdx} style={{ color: "var(--bp-muted)", fontSize: 11, marginBottom: 3 }}>
                                        Break: {b.start || "?"} – {b.end || "?"}
                                      </div>
                                    ))}
                                  </div>
                                ) : null}
                              </div>
                            );
                          })}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
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
