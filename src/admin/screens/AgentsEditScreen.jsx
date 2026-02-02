import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";
import { pickImage } from "../ui/wpMedia";

const DAYS = [
  { key: 1, label: "Mon" },
  { key: 2, label: "Tue" },
  { key: 3, label: "Wed" },
  { key: 4, label: "Thu" },
  { key: 5, label: "Fri" },
  { key: 6, label: "Sat" },
  { key: 0, label: "Sun" },
];

function emptySchedule() {
  const out = {};
  for (const d of DAYS) out[d.key] = { closed: true, start: "09:00", end: "17:00" };
  return out;
}

function parseScheduleJson(raw) {
  const base = emptySchedule();
  if (!raw || typeof raw !== "string") return { enabled: false, days: base };
  const txt = raw.trim();
  if (!txt) return { enabled: false, days: base };

  try {
    const obj = JSON.parse(txt);
    if (!obj || typeof obj !== "object") return { enabled: true, days: base };
    for (const d of DAYS) {
      const v = obj[String(d.key)];
      if (!v) {
        base[d.key] = { ...base[d.key], closed: true };
        continue;
      }
      const s = String(v);
      const m = s.match(/^(\d{2}:\d{2})-(\d{2}:\d{2})$/);
      if (!m) {
        base[d.key] = { ...base[d.key], closed: true };
        continue;
      }
      base[d.key] = { closed: false, start: m[1], end: m[2] };
    }
    return { enabled: true, days: base };
  } catch (e) {
    return { enabled: true, days: base };
  }
}

function serializeScheduleJson(enabled, days) {
  if (!enabled) return "";
  const obj = {};
  for (const d of DAYS) {
    const row = days[d.key] || {};
    if (row.closed) obj[String(d.key)] = "";
    else obj[String(d.key)] = `${row.start || "09:00"}-${row.end || "17:00"}`;
  }
  return JSON.stringify(obj);
}

function normalizeAgent(raw) {
  const isActive =
    raw?.is_active !== undefined
      ? !!Number(raw.is_active)
      : raw?.is_enabled !== undefined
        ? !!Number(raw.is_enabled)
        : true;
  return {
    id: raw?.id ? Number(raw.id) : 0,
    first_name: raw?.first_name || "",
    last_name: raw?.last_name || "",
    email: raw?.email || "",
    phone: raw?.phone || "",
    image_id: Number(raw?.image_id || 0) || 0,
    image_url: raw?.image_url || "",
    is_active: isActive ? 1 : 0,
    schedule_json: (raw?.schedule_json ?? "").toString(),
  };
}

export default function AgentsEditScreen() {
  const params = new URLSearchParams(window.location.search);
  const id = Number(params.get("id") || 0) || 0;

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [toast, setToast] = useState("");
  const [dirty, setDirty] = useState(false);

  const [agent, setAgent] = useState(() => normalizeAgent({ id }));
  const [serviceIds, setServiceIds] = useState([]);

  const [servicesLoading, setServicesLoading] = useState(true);
  const [servicesError, setServicesError] = useState("");
  const [services, setServices] = useState([]);
  const [serviceSearch, setServiceSearch] = useState("");

  const [scheduleEnabled, setScheduleEnabled] = useState(false);
  const [scheduleDays, setScheduleDays] = useState(() => emptySchedule());
  const [advancedOpen, setAdvancedOpen] = useState(false);

  const title = id ? "Edit Agent" : "Add Agent";
  const statusLabel = Number(agent.is_active) ? "Active" : "Inactive";

  useEffect(() => {
    let alive = true;
    (async () => {
      setLoading(true);
      setError("");
      try {
        const [svcResp, agentResp, relResp] = await Promise.all([
          bpFetch("/admin/services").catch((e) => ({ __error: e })),
          id ? bpFetch(`/admin/agents/${id}`).catch((e) => ({ __error: e })) : Promise.resolve({ data: null }),
          id ? bpFetch(`/admin/agents/${id}/services`).catch((e) => ({ __error: e })) : Promise.resolve({ data: [] }),
        ]);

        if (!alive) return;

        if (svcResp?.__error) {
          setServicesError(svcResp.__error?.message || "Failed to load services");
          setServices([]);
        } else {
          setServicesError("");
          setServices(svcResp?.data || []);
        }
        setServicesLoading(false);

        if (id) {
          if (agentResp?.__error) throw agentResp.__error;
          const raw = agentResp?.data?.agent || agentResp?.data || agentResp?.agent || agentResp || {};
          const next = normalizeAgent(raw);
          setAgent(next);

          if (relResp?.__error) {
            setServiceIds([]);
          } else {
            const rel = relResp?.data || [];
            setServiceIds(Array.isArray(rel) ? rel.map((x) => Number(x) || 0).filter(Boolean) : []);
          }

          const parsed = parseScheduleJson(next.schedule_json);
          setScheduleEnabled(parsed.enabled);
          setScheduleDays(parsed.days);
        } else {
          setServiceIds([]);
          setScheduleEnabled(false);
          setScheduleDays(emptySchedule());
        }

        setDirty(false);
      } catch (e) {
        console.error(e);
        if (!alive) return;
        setError(e?.message || "Failed to load agent");
      } finally {
        if (alive) setLoading(false);
      }
    })();
    return () => {
      alive = false;
    };
  }, [id]);

  useEffect(() => {
    const onBeforeUnload = (e) => {
      if (!dirty) return;
      e.preventDefault();
      e.returnValue = "";
    };
    window.addEventListener("beforeunload", onBeforeUnload);
    return () => window.removeEventListener("beforeunload", onBeforeUnload);
  }, [dirty]);

  useEffect(() => {
    if (!toast) return;
    const t = setTimeout(() => setToast(""), 2500);
    return () => clearTimeout(t);
  }, [toast]);

  function update(patch) {
    setAgent((prev) => ({ ...prev, ...patch }));
    setDirty(true);
  }

  function setStatus(next) {
    update({ is_active: next ? 1 : 0 });
  }

  async function onPickImage() {
    try {
      const img = await pickImage({ title: "Select agent image" });
      update({ image_id: img.id, image_url: img.url });
    } catch (e) {
      setError(e?.message || "Image picker failed");
    }
  }

  function toggleService(sid) {
    const id = Number(sid) || 0;
    if (!id) return;
    setServiceIds((prev) => {
      const set = new Set(prev || []);
      if (set.has(id)) set.delete(id);
      else set.add(id);
      setDirty(true);
      return Array.from(set);
    });
  }

  const filteredServices = useMemo(() => {
    const q = serviceSearch.trim().toLowerCase();
    const list = Array.isArray(services) ? services : [];
    if (!q) return list;
    return list.filter((s) => `${s.name || ""}`.toLowerCase().includes(q));
  }, [services, serviceSearch]);

  function updateScheduleDay(dayKey, patch) {
    setScheduleDays((prev) => {
      const next = { ...prev };
      next[dayKey] = { ...(prev[dayKey] || {}), ...patch };
      return next;
    });
    setDirty(true);
  }

  function validate() {
    if (!agent.first_name.trim() && !agent.last_name.trim()) return "First name or last name is required.";
    if (agent.email && !String(agent.email).includes("@")) return "Email looks invalid.";
    return "";
  }

  async function onSave() {
    const msg = validate();
    if (msg) {
      setError(msg);
      return;
    }

    setSaving(true);
    setError("");
    try {
      const schedule_json = serializeScheduleJson(scheduleEnabled, scheduleDays);
      const payload = {
        first_name: agent.first_name,
        last_name: agent.last_name,
        email: agent.email,
        phone: agent.phone,
        image_id: Number(agent.image_id) || 0,
        is_active: Number(agent.is_active) ? 1 : 0,
        schedule_json,
      };

      let newId = id;
      if (id) {
        await bpFetch(`/admin/agents/${id}`, { method: "PATCH", body: payload });
      } else {
        const res = await bpFetch(`/admin/agents`, { method: "POST", body: payload });
        newId = Number(res?.data?.id || res?.id || 0) || 0;
        if (!newId) throw new Error("Create failed (missing id)");
      }

      await bpFetch(`/admin/agents/${newId}/services`, {
        method: "PUT",
        body: { service_ids: Array.isArray(serviceIds) ? serviceIds : [] },
      });

      setToast("Saved");
      setDirty(false);

      if (!id) {
        window.location.href = `admin.php?page=bp_agents_edit&id=${newId}`;
      }
    } catch (e) {
      console.error(e);
      setError(e?.message || "Save failed");
    } finally {
      setSaving(false);
    }
  }

  return (
    <form className="bp-agent-edit" onSubmit={(e) => e.preventDefault()}>
      {toast ? <div className="bp-toast">{toast}</div> : null}

      <div className="bp-agent-edit__top">
        <div>
          <div className="bp-breadcrumb">Agents / Edit</div>
          <div className="bp-h1">{title}</div>
        </div>
        <div className="bp-agent-edit__status">
          <span className={`bp-agent-edit__pill ${Number(agent.is_active) ? "on" : "off"}`}>{statusLabel}</span>
        </div>
      </div>

      {error ? <div className="bp-error">{error}</div> : null}

      {loading ? (
        <div className="bp-card">Loading…</div>
      ) : (
        <div className="bp-agent-edit__grid">
          <section className="bp-agent-edit__main">
            <div className="bp-card bp-agent-edit__section">
              <div className="bp-section-title">Basic info</div>
              <div className="bp-grid-2">
                <div>
                  <div className="bp-k">
                    First name <span className="bp-req">*</span>
                  </div>
                  <input className="bp-input" value={agent.first_name} onChange={(e) => update({ first_name: e.target.value })} />
                </div>
                <div>
                  <div className="bp-k">Last name</div>
                  <input className="bp-input" value={agent.last_name} onChange={(e) => update({ last_name: e.target.value })} />
                </div>
                <div>
                  <div className="bp-k">Email</div>
                  <input className="bp-input" type="email" value={agent.email} onChange={(e) => update({ email: e.target.value })} />
                </div>
                <div>
                  <div className="bp-k">Phone</div>
                  <input className="bp-input" value={agent.phone} onChange={(e) => update({ phone: e.target.value })} />
                </div>
              </div>
            </div>

            <div className="bp-card bp-agent-edit__section">
              <div className="bp-agent-edit__section-head">
                <div className="bp-section-title">Availability</div>
                <label className="bp-inline-check">
                  <input
                    type="checkbox"
                    checked={scheduleEnabled}
                    onChange={(e) => {
                      setScheduleEnabled(e.target.checked);
                      setDirty(true);
                    }}
                  />
                  <span>Override schedule</span>
                </label>
              </div>

              {scheduleEnabled ? (
                <div className="bp-agent-edit__schedule">
                  {DAYS.map((d) => {
                    const row = scheduleDays[d.key] || { closed: true, start: "09:00", end: "17:00" };
                    return (
                      <div className="bp-agent-edit__schedule-row" key={d.key}>
                        <div className="bp-agent-edit__day">{d.label}</div>
                        <label className="bp-inline-check">
                          <input
                            type="checkbox"
                            checked={!!row.closed}
                            onChange={(e) => updateScheduleDay(d.key, { closed: e.target.checked })}
                          />
                          <span>Closed</span>
                        </label>
                        <input
                          className="bp-input bp-agent-edit__time"
                          type="time"
                          disabled={row.closed}
                          value={row.start}
                          onChange={(e) => updateScheduleDay(d.key, { start: e.target.value })}
                        />
                        <input
                          className="bp-input bp-agent-edit__time"
                          type="time"
                          disabled={row.closed}
                          value={row.end}
                          onChange={(e) => updateScheduleDay(d.key, { end: e.target.value })}
                        />
                      </div>
                    );
                  })}
                </div>
              ) : (
                <div className="bp-muted">Uses the global schedule unless you enable an override.</div>
              )}

              <button className="bp-top-btn bp-agent-edit__advanced" type="button" onClick={() => setAdvancedOpen((v) => !v)}>
                {advancedOpen ? "Hide advanced" : "Advanced"}
              </button>

              {advancedOpen ? (
                <div style={{ marginTop: 10 }}>
                  <div className="bp-k">Schedule JSON</div>
                  <textarea
                    className="bp-textarea"
                    rows={4}
                    value={serializeScheduleJson(scheduleEnabled, scheduleDays)}
                    onChange={(e) => {
                      const parsed = parseScheduleJson(e.target.value);
                      setScheduleEnabled(parsed.enabled);
                      setScheduleDays(parsed.days);
                      setDirty(true);
                    }}
                  />
                </div>
              ) : null}
            </div>

            <div className="bp-card bp-agent-edit__section">
              <div className="bp-section-title">Services assigned</div>
              <div className="bp-agent-edit__services-head">
                <input
                  className="bp-input"
                  placeholder="Search services..."
                  value={serviceSearch}
                  onChange={(e) => setServiceSearch(e.target.value)}
                />
                <div className="bp-agent-edit__services-actions">
                  <button
                    type="button"
                    className="bp-btn bp-btn-soft"
                    onClick={() => {
                      const ids = (services || []).map((s) => Number(s.id) || 0).filter(Boolean);
                      setServiceIds(ids);
                      setDirty(true);
                    }}
                    disabled={servicesLoading}
                  >
                    Select all
                  </button>
                  <button
                    type="button"
                    className="bp-btn bp-btn-ghost"
                    onClick={() => {
                      setServiceIds([]);
                      setDirty(true);
                    }}
                  >
                    Clear
                  </button>
                </div>
              </div>

              {servicesError ? <div className="bp-error">{servicesError}</div> : null}

              <div className="bp-agent-edit__services">
                {servicesLoading ? (
                  <div className="bp-muted">Loading services…</div>
                ) : filteredServices.length === 0 ? (
                  <div className="bp-muted">No services found.</div>
                ) : (
                  filteredServices.map((s) => {
                    const sid = Number(s.id) || 0;
                    const checked = serviceIds.includes(sid);
                    return (
                      <label className="bp-agent-edit__service" key={sid}>
                        <input type="checkbox" checked={checked} onChange={() => toggleService(sid)} />
                        <span>{s.name || `#${sid}`}</span>
                      </label>
                    );
                  })
                )}
              </div>
              <div className="bp-muted">Only selected services will be available when choosing this agent.</div>
            </div>
          </section>

          <aside className="bp-agent-edit__side bp-card">
            <div className="bp-agent-edit__seg">
              <div className="bp-k">Status</div>
              <div className="bp-seg">
                <button
                  type="button"
                  className={`bp-seg-btn ${Number(agent.is_active) ? "active" : ""}`}
                  onClick={() => setStatus(true)}
                >
                  Active
                </button>
                <button
                  type="button"
                  className={`bp-seg-btn ${!Number(agent.is_active) ? "active" : ""}`}
                  onClick={() => setStatus(false)}
                >
                  Inactive
                </button>
              </div>
              <div className="bp-muted">Inactive agents won’t appear for booking selection.</div>
            </div>

            <div className="bp-agent-edit__avatar">
              {agent.image_url ? (
                <img src={agent.image_url} alt="Agent" />
              ) : (
                <div className="bp-agent-edit__avatar-empty">No image</div>
              )}
            </div>

            <div className="bp-agent-edit__side-actions">
              <button className="bp-btn" type="button" onClick={onPickImage}>
                Choose Image
              </button>
              <button
                className="bp-btn bp-btn-ghost"
                type="button"
                onClick={() => update({ image_id: 0, image_url: "" })}
                disabled={!agent.image_id && !agent.image_url}
              >
                Remove
              </button>
            </div>
          </aside>
        </div>
      )}

      <div className="bp-agent-edit__bar">
        <a className="bp-btn bp-btn-ghost" href="admin.php?page=bp_agents">
          Cancel
        </a>
        <button className="bp-btn bp-btn-primary" type="button" onClick={onSave} disabled={saving}>
          {saving ? "Saving..." : id ? "Save changes" : "Create agent"}
        </button>
      </div>
    </form>
  );
}
