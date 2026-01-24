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

export default function LocationsScreen() {
  const [tab, setTab] = useState("locations");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [locations, setLocations] = useState([]);
  const [categories, setCategories] = useState([]);
  const [agents, setAgents] = useState([]);
  const [services, setServices] = useState([]);

  const [drawerOpen, setDrawerOpen] = useState(false);
  const [edit, setEdit] = useState(null);
  const [saving, setSaving] = useState(false);
  const [agentMap, setAgentMap] = useState({});
  const [useCustomSchedule, setUseCustomSchedule] = useState(false);
  const [schedule, setSchedule] = useState([]);

  const [catOpen, setCatOpen] = useState(false);
  const [catEdit, setCatEdit] = useState(null);
  const [catSaving, setCatSaving] = useState(false);

  useEffect(() => {
    loadAll();
  }, []);

  async function loadAll() {
    setLoading(true);
    setError("");
    try {
      const [l, c, a, s] = await Promise.all([
        bpFetch("/admin/locations"),
        bpFetch("/admin/location-categories"),
        bpFetch("/admin/agents"),
        bpFetch("/admin/services"),
      ]);
      setLocations(l?.data || []);
      setCategories(c?.data || []);
      setAgents(a?.data || []);
      setServices(s?.data || []);
    } catch (e) {
      setError(e.message || "Failed to load locations");
    } finally {
      setLoading(false);
    }
  }

  async function openNewLocation() {
    window.location.href = "admin.php?page=bp_locations_edit";
  }

  async function openEdit(id) {
    setDrawerOpen(true);
    setEdit(null);
    setAgentMap({});
    setUseCustomSchedule(false);
    setSchedule([]);
    setError("");
    try {
      const res = await bpFetch(`/admin/locations/${id}`);
      const row = res?.data || res;
      setEdit(row);
      setUseCustomSchedule(!!row.use_custom_schedule);
      setSchedule(Array.isArray(row.schedule) ? row.schedule : []);

      const agentsRes = await bpFetch(`/admin/locations/${id}/agents`);
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
    } catch (e) {
      setError(e.message || "Failed to load location");
    }
  }

  async function saveLocation() {
    if (!edit?.id) return;
    setSaving(true);
    setError("");
    try {
      const payload = {
        name: edit.name || "",
        address: edit.address || "",
        category_id: edit.category_id ? Number(edit.category_id) : null,
        image_id: edit.image_id ? Number(edit.image_id) : null,
        use_custom_schedule: useCustomSchedule ? 1 : 0,
        schedule: useCustomSchedule ? schedule : [],
      };

      await bpFetch(`/admin/locations/${edit.id}`, {
        method: "PUT",
        body: payload,
      });

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

      await bpFetch(`/admin/locations/${edit.id}/agents`, {
        method: "POST",
        body: { agents: agentsPayload },
      });

      setDrawerOpen(false);
      await loadAll();
    } catch (e) {
      setError(e.message || "Save failed");
    } finally {
      setSaving(false);
    }
  }

  async function deleteLocation() {
    if (!edit?.id) return;
    if (!confirm("Delete this location?")) return;
    setSaving(true);
    setError("");
    try {
      await bpFetch(`/admin/locations/${edit.id}`, { method: "DELETE" });
      setDrawerOpen(false);
      await loadAll();
    } catch (e) {
      setError(e.message || "Delete failed");
    } finally {
      setSaving(false);
    }
  }

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

  async function pickCategoryImage() {
    try {
      const img = await pickImage({ title: "Select category image" });
      setCatEdit((p) => ({ ...p, image_id: img.id, image_url: img.url }));
    } catch (e) {
      setError(e.message || "Image picker failed");
    }
  }

  async function openNewCategory() {
    window.location.href = "admin.php?page=bp_location_categories_edit";
  }

  async function openEditCategory(row) {
    window.location.href = `admin.php?page=bp_location_categories_edit&id=${row.id}`;
  }

  async function saveCategory() {
    if (!catEdit?.name) return;
    setCatSaving(true);
    try {
      if (catEdit.id) {
        await bpFetch(`/admin/location-categories/${catEdit.id}`, {
          method: "PUT",
          body: { name: catEdit.name, image_id: catEdit.image_id || 0 },
        });
      } else {
        await bpFetch(`/admin/location-categories`, {
          method: "POST",
          body: { name: catEdit.name, image_id: catEdit.image_id || 0 },
        });
      }
      setCatOpen(false);
      await loadAll();
    } catch (e) {
      setError(e.message || "Category save failed");
    } finally {
      setCatSaving(false);
    }
  }

  async function deleteCategory() {
    if (!catEdit?.id) return;
    if (!confirm("Delete this category?")) return;
    setCatSaving(true);
    try {
      await bpFetch(`/admin/location-categories/${catEdit.id}`, { method: "DELETE" });
      setCatOpen(false);
      await loadAll();
    } catch (e) {
      setError(e.message || "Delete failed");
    } finally {
      setCatSaving(false);
    }
  }

  const activeCategoryOptions = useMemo(() => {
    return (categories || []).map((c) => ({
      id: c.id,
      name: c.name,
    }));
  }, [categories]);

  return (
    <div className="bp-content">
      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Locations</div>
          <div className="bp-muted">Manage locations and categories.</div>
        </div>
        <div className="bp-head-actions">
          <button className="bp-top-btn" onClick={loadAll} disabled={loading}>
            {loading ? "Loading..." : "Refresh"}
          </button>
        </div>
      </div>

      <div className="bp-seg" style={{ marginBottom: 14 }}>
        <button
          className={`bp-seg-btn ${tab === "locations" ? "active" : ""}`}
          onClick={() => setTab("locations")}
        >
          Locations
        </button>
        <button
          className={`bp-seg-btn ${tab === "categories" ? "active" : ""}`}
          onClick={() => setTab("categories")}
        >
          Categories
        </button>
      </div>

      {error ? <div className="bp-error">{error}</div> : null}

      {tab === "locations" ? (
        <div className="bp-entity-grid">
          <button
            className="bp-entity-card"
            style={{ cursor: "pointer", borderStyle: "dashed" }}
            onClick={openNewLocation}
          >
            <div className="bp-entity-head">
              <div className="bp-entity-thumb">
                <div className="bp-entity-initial">+</div>
              </div>
              <div>
                <div className="bp-entity-title">New Location</div>
                <div className="bp-entity-sub">Create a new location</div>
              </div>
              <span className="bp-status-pill active">Active</span>
            </div>
          </button>

          {loading ? (
            <div className="bp-card">Loading...</div>
          ) : locations.length === 0 ? (
            <div className="bp-card">No locations found.</div>
          ) : (
            locations.map((l) => (
              <div key={l.id} className="bp-entity-card">
                <div className="bp-entity-head">
                  <div className="bp-entity-thumb">
                    {l.image_url ? (
                      <img src={l.image_url} alt={l.name} />
                    ) : (
                      <div className="bp-entity-initial">{(l.name || "L").trim().slice(0, 1).toUpperCase()}</div>
                    )}
                  </div>
                  <div>
                    <div className="bp-entity-title">{l.name}</div>
                    <div className="bp-entity-sub">{l.address || "No address"}</div>
                  </div>
                  <span className="bp-status-pill active">Active</span>
                </div>
                <div className="bp-entity-meta">
                  <div className="bp-entity-meta-label">Category</div>
                  <div className="bp-entity-meta-value">{l.category_name || "Uncategorized"}</div>
                </div>
                <div className="bp-entity-actions">
                  <button className="bp-btn-sm" onClick={() => { window.location.href = `admin.php?page=bp_locations_edit&id=${l.id}`; }}>
                    Edit
                  </button>
                </div>
              </div>
            ))
          )}
        </div>
      ) : null}

      {tab === "categories" ? (
        <div className="bp-entity-grid">
          <button
            className="bp-entity-card"
            style={{ cursor: "pointer", borderStyle: "dashed" }}
            onClick={openNewCategory}
          >
            <div className="bp-entity-head">
              <div className="bp-entity-thumb">
                <div className="bp-entity-initial">+</div>
              </div>
              <div>
                <div className="bp-entity-title">New Category</div>
                <div className="bp-entity-sub">Create a category</div>
              </div>
              <span className="bp-status-pill active">Active</span>
            </div>
          </button>

          {loading ? (
            <div className="bp-card">Loading...</div>
          ) : categories.length === 0 ? (
            <div className="bp-card">No categories found.</div>
          ) : (
            categories.map((c) => (
              <div key={c.id} className="bp-entity-card">
                <div className="bp-entity-head">
                  <div className="bp-entity-thumb">
                    {c.image_url ? (
                      <img src={c.image_url} alt={c.name} />
                    ) : (
                      <div className="bp-entity-initial">{(c.name || "C").trim().slice(0, 1).toUpperCase()}</div>
                    )}
                  </div>
                  <div>
                    <div className="bp-entity-title">{c.name}</div>
                    <div className="bp-entity-sub">Active</div>
                  </div>
                  <span className="bp-status-pill active">Active</span>
                </div>
                <div className="bp-entity-actions">
                  <button className="bp-btn-sm" onClick={() => openEditCategory(c)}>
                    Edit
                  </button>
                </div>
              </div>
            ))
          )}
        </div>
      ) : null}

      {drawerOpen ? (
        <div className="bp-drawer-wrap" onMouseDown={(e) => { if (e.target.classList.contains("bp-drawer-wrap")) setDrawerOpen(false); }}>
          <div className="bp-drawer" style={{ width: "min(720px, 100%)" }}>
            <div className="bp-drawer-head">
              <div>
                <div className="bp-drawer-title">{edit?.id ? "Edit Location" : "Add Location"}</div>
                <div className="bp-muted">Manage location profile, photo, and assignments.</div>
              </div>
              <button className="bp-top-btn" onClick={() => setDrawerOpen(false)}>Close</button>
            </div>
            <div className="bp-drawer-body">
              {!edit ? <div className="bp-muted">Loading...</div> : (
                <div style={{ display: "grid", gap: 16 }}>
                  <div className="bp-section">
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

                  <div className="bp-section">
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
                    {edit?.id ? (
                      <button className="button button-secondary" onClick={deleteLocation} disabled={saving}>
                        Delete
                      </button>
                    ) : null}
                    <button className="button" onClick={() => setDrawerOpen(false)} disabled={saving}>Back</button>
                    <button className="button button-primary" onClick={saveLocation} disabled={saving || !edit.name}>
                      {saving ? "Saving..." : "Save Changes"}
                    </button>
                  </p>
                </div>
              )}
            </div>
          </div>
        </div>
      ) : null}

      {catOpen ? (
        <div className="bp-drawer-wrap" onMouseDown={(e) => { if (e.target.classList.contains("bp-drawer-wrap")) setCatOpen(false); }}>
          <div className="bp-drawer" style={{ width: "min(520px, 100%)" }}>
            <div className="bp-drawer-head">
              <div>
                <div className="bp-drawer-title">{catEdit?.id ? "Edit Category" : "Add Category"}</div>
                <div className="bp-muted">Manage category profile and photo.</div>
              </div>
              <button className="bp-top-btn" onClick={() => setCatOpen(false)}>Close</button>
            </div>
            <div className="bp-drawer-body">
              {!catEdit ? null : (
                <div style={{ display: "grid", gap: 16 }}>
                  <div className="bp-section">
                    <div className="bp-section-title">Category details</div>
                    <table className="form-table" role="presentation">
                      <tbody>
                        <tr>
                          <th><label>Name</label></th>
                          <td>
                            <input
                              className="regular-text"
                              value={catEdit.name || ""}
                              onChange={(e) => setCatEdit((p) => ({ ...p, name: e.target.value }))}
                            />
                          </td>
                        </tr>
                        <tr>
                          <th><label>Category Image</label></th>
                          <td>
                            <div style={{ display: "flex", gap: 12, alignItems: "center", marginBottom: 10 }}>
                              <div className="bp-entity-thumb" style={{ width: 72, height: 72 }}>
                                {catEdit.image_url ? <img src={catEdit.image_url} alt="" /> : <div className="bp-entity-initial">C</div>}
                              </div>
                              <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
                                <button className="button" onClick={pickCategoryImage}>Choose Image</button>
                                {catEdit.image_id ? (
                                  <button className="button" onClick={() => setCatEdit((p) => ({ ...p, image_id: 0, image_url: "" }))}>
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

                  <p className="submit" style={{ display: "flex", gap: 10, justifyContent: "flex-end" }}>
                    {catEdit.id ? (
                      <button className="button button-secondary" onClick={deleteCategory} disabled={catSaving}>
                        Delete
                      </button>
                    ) : null}
                    <button className="button" onClick={() => setCatOpen(false)} disabled={catSaving}>Back</button>
                    <button className="button button-primary" onClick={saveCategory} disabled={catSaving || !catEdit.name}>
                      {catSaving ? "Saving..." : "Save Changes"}
                    </button>
                  </p>
                </div>
              )}
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
