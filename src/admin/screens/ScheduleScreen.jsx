import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";

const DAYS = [
  { k: "1", label: "Monday", short: "Mon" },
  { k: "2", label: "Tuesday", short: "Tue" },
  { k: "3", label: "Wednesday", short: "Wed" },
  { k: "4", label: "Thursday", short: "Thu" },
  { k: "5", label: "Friday", short: "Fri" },
  { k: "6", label: "Saturday", short: "Sat" },
  { k: "7", label: "Sunday", short: "Sun" },
];

const emptySchedule = () => {
  const o = {};
  for (const d of DAYS) o[d.k] = [];
  return o;
};

function timeToMin(t) {
  if (!t || typeof t !== "string") return null;
  const m = /^(\d{2}):(\d{2})$/.exec(t.trim());
  if (!m) return null;
  const hh = parseInt(m[1], 10);
  const mm = parseInt(m[2], 10);
  if (Number.isNaN(hh) || Number.isNaN(mm)) return null;
  if (hh < 0 || hh > 23 || mm < 0 || mm > 59) return null;
  return hh * 60 + mm;
}

function cloneIntervals(list) {
  return (list || []).map((it) => ({
    ...it,
    breaks: (it.breaks || []).map((b) => ({ ...b })),
  }));
}

function buildSummary(schedule) {
  const perDay = DAYS.map((d) => {
    const intervals = (schedule?.[d.k] || []).filter((it) => it?.is_enabled !== false);
    if (!intervals.length) return { day: d, text: "Closed" };
    if (intervals.length === 1 && (!intervals[0].breaks || intervals[0].breaks.length === 0)) {
      const a = intervals[0].start_time || "?";
      const b = intervals[0].end_time || "?";
      return { day: d, text: `${a}-${b}` };
    }
    return { day: d, text: "Custom" };
  });

  const groups = [];
  for (let i = 0; i < perDay.length; i++) {
    const row = perDay[i];
    const last = groups[groups.length - 1];
    if (!last || last.text !== row.text) {
      groups.push({ start: i, end: i, text: row.text });
    } else {
      last.end = i;
    }
  }

  const fmt = (g) => {
    const a = perDay[g.start].day.short;
    const b = perDay[g.end].day.short;
    const label = g.start === g.end ? a : `${a}-${b}`;
    return `${label} ${g.text}`;
  };

  return groups.map(fmt).join(", ");
}

function validateSchedule(schedule) {
  const issues = {};
  let hasErrors = false;

  for (const d of DAYS) {
    const dayList = schedule?.[d.k] || [];
    for (let i = 0; i < dayList.length; i++) {
      const it = dayList[i] || {};
      if (it.is_enabled === false) continue;

      const s = timeToMin(it.start_time);
      const e = timeToMin(it.end_time);
      const intervalErr = { start: false, end: false, message: "", breaks: {} };

      if (s === null) intervalErr.start = true;
      if (e === null) intervalErr.end = true;
      if (s !== null && e !== null && e <= s) {
        intervalErr.start = true;
        intervalErr.end = true;
        intervalErr.message = "End time must be after start time.";
      }

      const breaks = it.breaks || [];
      for (let b = 0; b < breaks.length; b++) {
        const br = breaks[b] || {};
        const bs = timeToMin(br.start);
        const be = timeToMin(br.end);
        const berr = { start: false, end: false, message: "" };

        if (bs === null) berr.start = true;
        if (be === null) berr.end = true;
        if (bs !== null && be !== null && be <= bs) {
          berr.start = true;
          berr.end = true;
          berr.message = "Break end must be after start.";
        }
        if (s !== null && e !== null && bs !== null && be !== null) {
          if (bs < s || be > e) {
            berr.start = true;
            berr.end = true;
            berr.message = "Break must be within the interval.";
          }
        }

        if (berr.start || berr.end) intervalErr.breaks[b] = berr;
      }

      if (intervalErr.start || intervalErr.end || Object.keys(intervalErr.breaks).length) {
        hasErrors = true;
        if (!issues[d.k]) issues[d.k] = { intervals: {} };
        issues[d.k].intervals[i] = intervalErr;
      }
    }
  }

  return { hasErrors, issues };
}

function dayHasEnabled(schedule, dayKey) {
  return (schedule?.[dayKey] || []).some((it) => it?.is_enabled !== false);
}

export default function ScheduleScreen({ embedded = false }) {
  const [agents, setAgents] = useState([]);
  const [mode, setMode] = useState("global"); // global | agent
  const [agentId, setAgentId] = useState(0);
  const [schedule, setSchedule] = useState(emptySchedule());
  const [settings, setSettings] = useState({ slot_interval_minutes: 30, timezone: "Europe/Copenhagen" });
  const [timezones, setTimezones] = useState([]);
  const [tzMode, setTzMode] = useState("select"); // select | custom

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

  useEffect(() => {
    try {
      if (typeof Intl !== "undefined" && typeof Intl.supportedValuesOf === "function") {
        setTimezones(Intl.supportedValuesOf("timeZone"));
        return;
      }
    } catch (e) {
      // ignore
    }
    setTimezones([
      "UTC",
      "Europe/London",
      "Europe/Copenhagen",
      "Europe/Paris",
      "Europe/Berlin",
      "Europe/Madrid",
      "Europe/Rome",
      "Europe/Oslo",
      "Europe/Stockholm",
      "Europe/Helsinki",
      "America/New_York",
      "America/Chicago",
      "America/Denver",
      "America/Los_Angeles",
      "America/Toronto",
      "America/Vancouver",
      "America/Sao_Paulo",
      "Asia/Dubai",
      "Asia/Kolkata",
      "Asia/Bangkok",
      "Asia/Singapore",
      "Asia/Tokyo",
      "Asia/Seoul",
      "Australia/Sydney",
    ]);
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

  useEffect(() => {
    const tz = settings?.timezone || "UTC";
    setTzMode(timezones.includes(tz) ? "select" : "custom");
  }, [settings?.timezone, timezones]);

  const addInterval = (dayKey) => {
    setSchedule((prev) => ({
      ...prev,
      [dayKey]: [
        ...(prev[dayKey] || []),
        { start_time: "09:00", end_time: "17:00", is_enabled: true, breaks: [] },
      ],
    }));
  };

  const setDayOpen = (dayKey, open) => {
    setSchedule((prev) => {
      const next = { ...prev };
      if (!open) {
        next[dayKey] = [];
        return next;
      }
      const list = cloneIntervals(prev[dayKey] || []);
      const hasEnabled = list.some((it) => it.is_enabled !== false);
      next[dayKey] = list.length && hasEnabled ? list : [{ start_time: "09:00", end_time: "17:00", is_enabled: true, breaks: [] }];
      return next;
    });
  };

  const copyDayToAll = (dayKey) => {
    setSchedule((prev) => {
      const base = cloneIntervals(prev[dayKey] || []);
      const next = { ...prev };
      for (const d of DAYS) next[d.k] = cloneIntervals(base);
      return next;
    });
  };

  const applyPreset = (preset) => {
    const make = (openDays, start, end) => {
      const next = emptySchedule();
      for (const d of DAYS) {
        if (openDays.includes(d.k)) {
          next[d.k] = [{ start_time: start, end_time: end, is_enabled: true, breaks: [] }];
        }
      }
      return next;
    };

    if (preset === "wk_09_17") setSchedule(make(["1", "2", "3", "4", "5"], "09:00", "17:00"));
    if (preset === "wk_08_16") setSchedule(make(["1", "2", "3", "4", "5"], "08:00", "16:00"));
    if (preset === "all_09_17") setSchedule(make(["1", "2", "3", "4", "5", "6", "7"], "09:00", "17:00"));
    if (preset === "clear") setSchedule(emptySchedule());
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
      pushToast("success", "Schedule saved.");
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
  const summary = useMemo(() => buildSummary(schedule), [schedule]);
  const validation = useMemo(() => validateSchedule(schedule), [schedule]);
  const canSave = !validation.hasErrors && !saving && !loading;
  const wrapClass = embedded ? "bp-schedule bp-schedule--embedded" : "bp-content bp-schedule";

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
            <div className="bp-h1">Schedule</div>
            <div className="bp-muted">Define working hours, breaks, and availability settings.</div>
          </div>
        </div>
      ) : null}

      <div className="bp-card bp-sched-card">
        <div className="bp-card-head bp-sched-top">
          <div style={{ minWidth: 0 }}>
            <div className="bp-section-title" style={{ margin: 0 }}>
              {mode === "global" ? "Global schedule" : `${agentLabel} schedule`}
            </div>
            <div className="bp-muted bp-text-sm bp-sched-summary">{summary}</div>
          </div>

          <div className="bp-sched-top__controls">
            <div className="bp-seg">
              <button type="button" className={`bp-seg-btn ${viewTab === "edit" ? "active" : ""}`} onClick={() => setViewTab("edit")}>
                Edit
              </button>
              <button type="button" className={`bp-seg-btn ${viewTab === "week" ? "active" : ""}`} onClick={() => setViewTab("week")}>
                Week
              </button>
            </div>

            <div className="bp-seg">
              <button type="button" className={`bp-seg-btn ${mode === "global" ? "active" : ""}`} onClick={() => setMode("global")}>
                Global
              </button>
              <button type="button" className={`bp-seg-btn ${mode === "agent" ? "active" : ""}`} onClick={() => setMode("agent")}>
                Per agent
              </button>
            </div>

            {mode === "agent" ? (
              <select className="bp-input" value={agentId} onChange={(e) => setAgentId(parseInt(e.target.value, 10) || 0)} aria-label="Select agent">
                {agents.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.name || `${a.first_name || ""} ${a.last_name || ""}`.trim() || `#${a.id}`}
                  </option>
                ))}
              </select>
            ) : null}
          </div>
        </div>

        {viewTab === "edit" ? (
          <div className="bp-sched-body">
            <div className="bp-sched-toolbar">
              <div className="bp-sched-toolbar__chips" role="group" aria-label="Presets">
                <button type="button" className="bp-chip-btn" onClick={() => applyPreset("wk_09_17")}>
                  Mon-Fri 09-17
                </button>
                <button type="button" className="bp-chip-btn" onClick={() => applyPreset("wk_08_16")}>
                  Mon-Fri 08-16
                </button>
                <button type="button" className="bp-chip-btn" onClick={() => applyPreset("all_09_17")}>
                  All days 09-17
                </button>
                <button type="button" className="bp-chip-btn" onClick={() => applyPreset("clear")}>
                  Clear
                </button>
              </div>

              <div className="bp-muted bp-text-xs">
                Exceptions (holidays) override schedule.{" "}
                <a className="bp-link" href="admin.php?page=bp_settings&tab=holidays">
                  Manage holidays
                </a>
                .
              </div>
            </div>

            <div className="bp-sched-bar is-top">
              <div className="bp-muted bp-text-sm" style={{ minWidth: 0 }}>
                {validation.hasErrors ? "Fix validation errors before saving." : "Changes apply to booking availability."}
              </div>
              <button className="bp-btn bp-btn-primary" onClick={save} disabled={!canSave}>
                {saving ? "Saving..." : "Save schedule"}
              </button>
            </div>

            {loading ? (
              <div className="bp-muted">Loading...</div>
            ) : (
              <div className="bp-sched-days">
                {DAYS.map((d) => {
                  const open = dayHasEnabled(schedule, d.k);
                  return (
                    <div key={d.k} className="bp-day">
                      <div className="bp-day-head">
                        <div className="bp-day-title">{d.label}</div>
                        <div className="bp-day-actions">
                          <label className="bp-switch" title={open ? "Open" : "Closed"}>
                            <input type="checkbox" checked={open} onChange={(e) => setDayOpen(d.k, e.target.checked)} />
                            <span className="bp-slider" />
                          </label>
                          <button type="button" className="bp-btn-sm" onClick={() => copyDayToAll(d.k)} disabled={!open}>
                            Copy to all
                          </button>
                          <button type="button" className="bp-btn-sm" onClick={() => addInterval(d.k)} disabled={!open}>
                            + Interval
                          </button>
                        </div>
                      </div>

                      {!open ? <div className="bp-day-empty">Closed</div> : null}

                      {(schedule[d.k] || []).map((it, idx) => {
                        const errs = validation.issues?.[d.k]?.intervals?.[idx] || null;
                        const intervalDisabled = it?.is_enabled === false;

                        return (
                          <div key={`${d.k}-${idx}`} className={`bp-interval ${intervalDisabled ? "is-disabled" : ""}`}>
                            <div className="bp-interval-row">
                              <input
                                type="time"
                                className={`bp-time-input ${errs?.start ? "bp-field-error" : ""}`}
                                value={it.start_time || ""}
                                onChange={(e) => updateInterval(d.k, idx, { start_time: e.target.value })}
                                disabled={intervalDisabled}
                              />
                              <span style={{ fontWeight: 900 }}>to</span>
                              <input
                                type="time"
                                className={`bp-time-input ${errs?.end ? "bp-field-error" : ""}`}
                                value={it.end_time || ""}
                                onChange={(e) => updateInterval(d.k, idx, { end_time: e.target.value })}
                                disabled={intervalDisabled}
                              />

                              <label className="bp-muted" style={{ display: "flex", alignItems: "center", gap: 6, fontWeight: 900 }}>
                                <input
                                  type="checkbox"
                                  checked={it.is_enabled !== false}
                                  onChange={(e) => updateInterval(d.k, idx, { is_enabled: e.target.checked })}
                                />
                                Enabled
                              </label>

                              <button type="button" className="bp-btn-sm" onClick={() => removeInterval(d.k, idx)}>
                                Remove
                              </button>
                            </div>

                            {errs?.message ? <div className="bp-alert bp-alert-error bp-sched-err">{errs.message}</div> : null}

                            <div className="bp-breaks">
                              {(it.breaks || []).map((b, bIdx) => {
                                const berr = errs?.breaks?.[bIdx] || null;
                                return (
                                  <div key={`${d.k}-${idx}-${bIdx}`} className="bp-break-row">
                                    <span className="bp-muted" style={{ fontWeight: 900 }}>
                                      Break
                                    </span>
                                    <input
                                      type="time"
                                      className={`bp-time-input ${berr?.start ? "bp-field-error" : ""}`}
                                      value={b.start || ""}
                                      onChange={(e) => updateBreak(d.k, idx, bIdx, { start: e.target.value })}
                                      disabled={intervalDisabled}
                                    />
                                    <span style={{ fontWeight: 900 }}>to</span>
                                    <input
                                      type="time"
                                      className={`bp-time-input ${berr?.end ? "bp-field-error" : ""}`}
                                      value={b.end || ""}
                                      onChange={(e) => updateBreak(d.k, idx, bIdx, { end: e.target.value })}
                                      disabled={intervalDisabled}
                                    />
                                    <button type="button" className="bp-btn-sm" onClick={() => removeBreak(d.k, idx, bIdx)}>
                                      Remove
                                    </button>
                                    {berr?.message ? <div className="bp-alert bp-alert-error bp-sched-err">{berr.message}</div> : null}
                                  </div>
                                );
                              })}
                              <button type="button" className="bp-btn-sm" onClick={() => addBreak(d.k, idx)} disabled={intervalDisabled}>
                                + Break
                              </button>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  );
                })}
              </div>
            )}

            <div className="bp-card bp-sched-settings-card">
              <div className="bp-section-title" style={{ marginBottom: 12 }}>
                Settings
              </div>
              <div className="bp-sched-settings">
                <div>
                  <label className="bp-label">Slot interval (minutes)</label>
                  <input
                    type="number"
                    className="bp-input-field"
                    min={5}
                    max={120}
                    value={settings.slot_interval_minutes ?? 30}
                    onChange={(e) => setSettings({ ...settings, slot_interval_minutes: parseInt(e.target.value || "30", 10) })}
                  />
                </div>
                <div>
                  <label className="bp-label">Timezone</label>
                  <div className="bp-grid bp-grid-2 bp-gap-10">
                    <select
                      className="bp-input-field"
                      value={tzMode === "custom" ? "__custom__" : settings.timezone || ""}
                      onChange={(e) => {
                        const v = e.target.value;
                        if (v === "__custom__") {
                          setTzMode("custom");
                        } else {
                          setTzMode("select");
                          setSettings({ ...settings, timezone: v });
                        }
                      }}
                    >
                      {timezones.map((tz) => (
                        <option key={tz} value={tz}>
                          {tz}
                        </option>
                      ))}
                      <option value="__custom__">Custom...</option>
                    </select>

                    {tzMode === "custom" ? (
                      <input
                        type="text"
                        className="bp-input-field"
                        value={settings.timezone || ""}
                        onChange={(e) => setSettings({ ...settings, timezone: e.target.value })}
                        placeholder="Europe/Copenhagen"
                      />
                    ) : (
                      <div className="bp-muted" style={{ alignSelf: "center" }}>
                        {settings.timezone || "UTC"}
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </div>

            <div className="bp-sched-bar">
              <div className="bp-muted bp-text-sm" style={{ minWidth: 0 }}>
                {validation.hasErrors ? "Fix validation errors before saving." : "Changes apply to booking availability."}
              </div>
              <button className="bp-btn bp-btn-primary" onClick={save} disabled={!canSave}>
                {saving ? "Saving..." : "Save schedule"}
              </button>
            </div>
          </div>
        ) : null}

        {viewTab === "week" ? (
          <div className="bp-sched-week">
            {loading ? (
              <div className="bp-muted">Loading...</div>
            ) : (
              <div className="bp-week-grid">
                {DAYS.map((d) => {
                  const intervals = schedule[d.k] || [];
                  const hasIntervals = intervals.length > 0 && intervals.some((it) => it.is_enabled !== false);

                  return (
                    <div key={d.k} className={`bp-week-day ${hasIntervals ? "open" : ""}`}>
                      <div className="bp-week-title">{d.label}</div>

                      {!hasIntervals ? (
                        <div className="bp-week-closed">Closed</div>
                      ) : (
                        <div style={{ display: "grid", gap: 8, fontSize: 12 }}>
                          {intervals.map((it, idx) => {
                            if (it.is_enabled === false) return null;

                            return (
                              <div key={idx}>
                                <div className="bp-week-slot">
                                  {it.start_time || "?"} - {it.end_time || "?"}
                                </div>

                                {(it.breaks || []).length > 0 ? (
                                  <div className="bp-week-breaks">
                                    {it.breaks.map((b, bIdx) => (
                                      <div key={bIdx} style={{ color: "var(--bp-muted)", fontSize: 11, marginBottom: 3 }}>
                                        Break: {b.start || "?"} - {b.end || "?"}
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
    </div>
  );
}
