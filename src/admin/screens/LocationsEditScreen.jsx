import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";
import { pickImage } from "../ui/wpMedia";

const DAYS = [
  { value: 0, label: "Sunday" },
  { value: 1, label: "Monday" },
  { value: 2, label: "Tuesday" },
  { value: 3, label: "Wednesday" },
  { value: 4, label: "Thursday" },
  { value: 5, label: "Friday" },
  { value: 6, label: "Saturday" },
];

function getQueryInt(key) {
  const params = new URLSearchParams(window.location.search);
  const raw = params.get(key);
  const parsed = raw ? parseInt(raw, 10) : 0;
  return Number.isFinite(parsed) ? parsed : 0;
}

export default function LocationsEditScreen() {
  const initialId = getQueryInt("id");
  const [locationId, setLocationId] = useState(initialId);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const [edit, setEdit] = useState(null);
  const [categories, setCategories] = useState([]);
  const [agents, setAgents] = useState([]);
  const [services, setServices] = useState([]);
  const [agentMap, setAgentMap] = useState({});
  const [useCustomSchedule, setUseCustomSchedule] = useState(false);
  const [schedule, setSchedule] = useState([]);

  useEffect(() => {
    loadAll();
  }, [locationId]);

  async function loadAll() {
    setLoading(true);
    setError("");
    setNotice("");
    try {
      const [c, a, s] = await Promise.all([
        bpFetch("/admin/location-categories"),
        bpFetch("/admin/agents"),
        bpFetch("/admin/services"),
      ]);
      setCategories(c?.data || []);
      setAgents(a?.data || []);
      setServices(s?.data || []);

      if (locationId > 0) {
        const loc = await bpFetch(`/admin/locations/${locationId}`);
        const row = loc?.data || loc;
        setEdit(row);
        setUseCustomSchedule(!!row.use_custom_schedule);
        setSchedule(Array.isArray(row.schedule) ? row.schedule : []);

        const agentsRes = await bpFetch(`/admin/locations/${locationId}/agents`);
        const rows = agentsRes?.data || [];
        const map = {};
        rows.forEach((r) => {
          const services = Array.isArray(r.services) ? r.services : [];
          map[r.agent_id] = {
            selected: true,
            customize: services.length > 0,
            services,
          };
        });
        setAgentMap(map);
      } else {
        setEdit({
          name: "",
          address: "",
          category_id: null,
          image_id: 0,
          image_url: "",
        });
        setUseCustomSchedule(false);
        setSchedule([]);
        setAgentMap({});
      }
    } catch (e) {
      setError(e.message || "Failed to load location");
    } finally {
      setLoading(false);
    }
  }

  const activeCategoryOptions = useMemo(() => {
    return (categories || []).map((c) => ({
      id: c.id,
      name: c.name,
    }));
  }, [categories]);

  function toggleAgent(id) {
    setAgentMap((prev) => {
      const entry = prev[id] || { selected: false, customize: false, services: [] };
      return { ...prev, [id]: { ...entry, selected: !entry.selected } };
    });
  }

  function toggleAllAgents() {
    const allSelected = agents.length > 0 && agents.every((a) => agentMap[a.id]?.selected);
    const next = {};
    agents.forEach((a) => {
      const entry = agentMap[a.id] || { selected: false, customize: false, services: [] };
      next[a.id] = { ...entry, selected: !allSelected };
    });
    setAgentMap(next);
  }

  function toggleCustomize(agentId) {
    setAgentMap((prev) => {
      const entry = prev[agentId] || { selected: true, customize: false, services: [] };
      return { ...prev, [agentId]: { ...entry, customize: !entry.customize } };
    });
  }

  function toggleService(agentId, serviceId) {
    setAgentMap((prev) => {
      const entry = prev[agentId] || { selected: true, customize: true, services: [] };
      const set = new Set(entry.services || []);
      if (set.has(serviceId)) set.delete(serviceId);
      else set.add(serviceId);
      return { ...prev, [agentId]: { ...entry, services: Array.from(set) } };
    });
  }

  function addScheduleDay() {
    setSchedule((prev) => [
      ...prev,
      { day: 1, start: "09:00", end: "17:00" },
    ]);
  }

  function updateScheduleDay(index, key, value) {
    setSchedule((prev) => {
      const next = [...prev];
      next[index] = { ...next[index], [key]: value };
      return next;
    });
  }

  function removeScheduleDay(index) {
    setSchedule((prev) => prev.filter((_, i) => i !== index));
  }

  async function pickLocationImage() {
    try {
      const img = await pickImage({ title: "Select location image" });
      setEdit((p) => ({ ...p, image_id: img.id, image_url: img.url }));
    } catch (e) {
      setError(e.message || "Image picker failed");
    }
  }

  async function saveLocation() {
    if (!edit?.name) return;
    setSaving(true);
    setError("");
    setNotice("");
    try {
      const payload = {
        name: edit.name || "",
        address: edit.address || "",
        category_id: edit.category_id ? Number(edit.category_id) : null,
        image_id: edit.image_id ? Number(edit.image_id) : null,
        use_custom_schedule: useCustomSchedule ? 1 : 0,
        schedule: useCustomSchedule ? schedule : [],
      };

      let id = locationId;
      if (id > 0) {
        await bpFetch(`/admin/locations/${id}`, {
          method: "PUT",
          body: payload,
        });
      } else {
        const res = await bpFetch("/admin/locations", {
          method: "POST",
          body: payload,
        });
        const row = res?.data || res;
        id = row?.id || 0;
        if (id > 0) {
          setLocationId(id);
        }
      }

      if (id > 0) {
        const agentsPayload = agents
          .map((a) => {
            const entry = agentMap[a.id];
            if (!entry?.selected) return null;
            return {
              agent_id: a.id,
              services: entry.customize ? entry.services : null,
            };
          })
          .filter(Boolean);

        await bpFetch(`/admin/locations/${id}/agents`, {
          method: "POST",
          body: { agents: agentsPayload },
        });

        if (!locationId && id) {
          window.history.replaceState(null, "", `admin.php?page=bp_locations_edit&id=${id}`);
        }
        setNotice("Saved changes.");
      }
    } catch (e) {
      setError(e.message || "Save failed");
    } finally {
      setSaving(false);
    }
  }

  async function deleteLocation() {
    if (!locationId) return;
    if (!confirm("Delete this location?")) return;
    setSaving(true);
    setError("");
    try {
      await bpFetch(`/admin/locations/${locationId}`, { method: "DELETE" });
      window.location.href = "admin.php?page=bp_locations";
    } catch (e) {
      setError(e.message || "Delete failed");
      setSaving(false);
    }
  }

  return (
    <div className="bp-content">
      <div className="bp-page-head">
        <div>
          <div className="bp-h1">{locationId ? "Edit Location" : "Add Location"}</div>
          <div className="bp-muted">Manage location profile, photo, and assignments.</div>
        </div>
        <div className="bp-head-actions">
          <a className="bp-top-btn" href="admin.php?page=bp_locations">Back to Locations</a>
        </div>
      </div>

      {error ? <div className="bp-error">{error}</div> : null}
      {notice ? <div className="bp-success">{notice}</div> : null}

      {loading || !edit ? (
        <div className="bp-card">Loading...</div>
      ) : (
        <div className="bp-card" style={{ padding: 18 }}>
          <div className="bp-section" style={{ marginBottom: 16 }}>
            <div className="bp-section-title">Basic information</div>
            <table className="form-table" role="presentation">
              <tbody>
                <tr>
                  <th><label>Name</label></th>
                  <td>
                    <input
                      className="regular-text"
                      value={edit.name || ""}
                      onChange={(e) => setEdit((p) => ({ ...p, name: e.target.value }))}
                    />
                  </td>
                </tr>
                <tr>
                  <th><label>Address</label></th>
                  <td>
                    <input
                      className="regular-text"
                      value={edit.address || ""}
                      onChange={(e) => setEdit((p) => ({ ...p, address: e.target.value }))}
                    />
                  </td>
                </tr>
                <tr>
                  <th><label>Category</label></th>
                  <td>
                    <select
                      className="regular-text"
                      value={edit.category_id || ""}
                      onChange={(e) => setEdit((p) => ({ ...p, category_id: e.target.value ? Number(e.target.value) : null }))}
                    >
                      <option value="">Uncategorized</option>
                      {activeCategoryOptions.map((c) => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                      ))}
                    </select>
                  </td>
                </tr>
                <tr>
                  <th><label>Status</label></th>
                  <td>
                    <select className="regular-text" value="active" disabled>
                      <option value="active">Active</option>
                    </select>
                  </td>
                </tr>
                <tr>
                  <th><label>Location Image</label></th>
                  <td>
                    <div style={{ display: "flex", gap: 12, alignItems: "center", marginBottom: 10 }}>
                      <div className="bp-entity-thumb" style={{ width: 72, height: 72 }}>
                        {edit.image_url ? <img src={edit.image_url} alt="" /> : <div className="bp-entity-initial">L</div>}
                      </div>
                      <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
                        <button className="button" onClick={pickLocationImage}>Choose Image</button>
                        {edit.image_id ? (
                          <button
                            className="button"
                            onClick={() => setEdit((p) => ({ ...p, image_id: 0, image_url: "" }))}
                          >
                            Remove
                          </button>
                        ) : null}
                      </div>
                    </div>
                    <p className="description">Uses Media Library. Stores attachment ID.</p>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div className="bp-section" style={{ marginBottom: 16 }}>
            <div className="bp-section-title">Agents</div>
            <table className="form-table" role="presentation">
              <tbody>
                <tr>
                  <th><label>Assignments</label></th>
                  <td>
                    <div style={{ marginBottom: 10 }}>
                      <button className="button" onClick={toggleAllAgents}>
                        {agents.length > 0 && agents.every((a) => agentMap[a.id]?.selected) ? "Unselect All" : "Select All"}
                      </button>
                    </div>
                    {agents.length === 0 ? (
                      <div className="bp-muted">No agents found.</div>
                    ) : (
                      <div style={{ display: "grid", gap: 10 }}>
                        {agents.map((a) => {
                          const entry = agentMap[a.id] || { selected: false, customize: false, services: [] };
                          return (
                            <div key={a.id} style={{ border: "1px solid var(--bp-border)", borderRadius: 12, padding: 10 }}>
                              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 10 }}>
                                <label style={{ display: "flex", gap: 10, alignItems: "center", fontWeight: 900 }}>
                                  <input type="checkbox" checked={!!entry.selected} onChange={() => toggleAgent(a.id)} />
                                  <span>{a.name || `Agent #${a.id}`}</span>
                                </label>
                                <button
                                  className="button"
                                  onClick={() => toggleCustomize(a.id)}
                                  disabled={!entry.selected}
                                >
                                  {entry.customize ? "All services" : "Customize services"}
                                </button>
                              </div>
                              {entry.selected && entry.customize ? (
                                <div style={{ marginTop: 10, display: "grid", gap: 6 }}>
                                  {services.length === 0 ? (
                                    <div className="bp-muted">No services found.</div>
                                  ) : (
                                    services.map((s) => (
                                      <label key={s.id} style={{ display: "flex", gap: 8, alignItems: "center", fontWeight: 900 }}>
                                        <input
                                          type="checkbox"
                                          checked={(entry.services || []).includes(s.id)}
                                          onChange={() => toggleService(a.id, s.id)}
                                        />
                                        <span>{s.name || s.title || `Service #${s.id}`}</span>
                                      </label>
                                    ))
                                  )}
                                </div>
                              ) : null}
                            </div>
                          );
                        })}
                      </div>
                    )}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div className="bp-section">
            <div className="bp-section-title">Location schedule</div>
            <table className="form-table" role="presentation">
              <tbody>
                <tr>
                  <th><label>Custom schedule</label></th>
                  <td>
                    <label className="bp-check" style={{ marginBottom: 10 }}>
                      <input
                        type="checkbox"
                        checked={useCustomSchedule}
                        onChange={(e) => setUseCustomSchedule(e.target.checked)}
                      />
                      Use custom schedule
                    </label>
                    {!useCustomSchedule ? (
                      <div className="bp-muted">Using general schedule settings.</div>
                    ) : (
                      <div style={{ display: "grid", gap: 10 }}>
                        {schedule.length === 0 ? (
                          <div className="bp-muted">No custom days yet.</div>
                        ) : (
                          schedule.map((d, idx) => (
                            <div key={`${idx}`} style={{ display: "grid", gap: 8, gridTemplateColumns: "1fr 1fr 1fr auto", alignItems: "center" }}>
                              <select
                                className="regular-text"
                                value={d.day}
                                onChange={(e) => updateScheduleDay(idx, "day", parseInt(e.target.value, 10))}
                              >
                                {DAYS.map((day) => (
                                  <option key={day.value} value={day.value}>{day.label}</option>
                                ))}
                              </select>
                              <input
                                className="regular-text"
                                type="time"
                                value={d.start || "09:00"}
                                onChange={(e) => updateScheduleDay(idx, "start", e.target.value)}
                              />
                              <input
                                className="regular-text"
                                type="time"
                                value={d.end || "17:00"}
                                onChange={(e) => updateScheduleDay(idx, "end", e.target.value)}
                              />
                              <button className="button" onClick={() => removeScheduleDay(idx)}>Remove</button>
                            </div>
                          ))
                        )}
                        <button className="button" onClick={addScheduleDay}>Add Day</button>
                      </div>
                    )}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <p className="submit" style={{ display: "flex", gap: 10, justifyContent: "flex-end" }}>
            {locationId ? (
              <button className="button button-secondary" onClick={deleteLocation} disabled={saving}>
                Delete
              </button>
            ) : null}
            <a className="button" href="admin.php?page=bp_locations">Back</a>
            <button className="button button-primary" onClick={saveLocation} disabled={saving || !edit.name}>
              {saving ? "Saving..." : "Save Changes"}
            </button>
          </p>
        </div>
      )}
    </div>
  );
}
