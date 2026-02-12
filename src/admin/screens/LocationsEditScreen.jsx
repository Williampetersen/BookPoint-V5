import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";
import { pickImage } from "../ui/wpMedia";
import UpgradeToPro from "../components/UpgradeToPro";

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

function normalizeLocation(raw, id) {
  const isActive =
    raw?.status !== undefined ? String(raw.status) === "active" : raw?.is_active !== undefined ? !!Number(raw.is_active) : true;

  return {
    id: raw?.id ? Number(raw.id) : Number(id || 0) || 0,
    name: raw?.name || "",
    address: raw?.address || "",
    category_id: raw?.category_id ? Number(raw.category_id) : 0,
    image_id: raw?.image_id ? Number(raw.image_id) : 0,
    image_url: raw?.image_url || raw?.image || "",
    is_active: isActive ? 1 : 0,
    use_custom_schedule: raw?.use_custom_schedule ? 1 : 0,
    schedule: Array.isArray(raw?.schedule) ? raw.schedule : [],
  };
}

export default function LocationsEditScreen() {
  const isPro = Boolean(Number(window.BP_ADMIN?.isPro || 0));
  const initialId = getQueryInt("id");
  const [locationId, setLocationId] = useState(initialId);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [toast, setToast] = useState("");
  const [dirty, setDirty] = useState(false);

  const [location, setLocation] = useState(() =>
    normalizeLocation(
      {
        id: initialId,
        name: "",
        address: "",
        category_id: 0,
        image_id: 0,
        is_active: 1,
        use_custom_schedule: 0,
        schedule: [],
      },
      initialId
    )
  );

  const [categories, setCategories] = useState([]);
  const [agents, setAgents] = useState([]);
  const [services, setServices] = useState([]);

  const [agentSearch, setAgentSearch] = useState("");
  const [serviceSearch, setServiceSearch] = useState("");

  // agent_id -> { selected, customize, services: [serviceId] }
  const [agentMap, setAgentMap] = useState({});

  const title = locationId ? "Edit Location" : "Add Location";
  const statusLabel = Number(location.is_active) ? "Active" : "Inactive";

  useEffect(() => {
    let alive = true;

    async function load() {
      if (!isPro) {
        setLoading(false);
        setError("");
        return;
      }
      setLoading(true);
      setError("");
      try {
        const [catsResp, agentsResp, svcResp] = await Promise.all([
          bpFetch("/admin/location-categories").catch((e) => ({ __error: e })),
          bpFetch("/admin/agents").catch((e) => ({ __error: e })),
          bpFetch("/admin/services").catch((e) => ({ __error: e })),
        ]);

        if (!alive) return;

        setCategories(catsResp?.__error ? [] : catsResp?.data || []);
        setAgents(agentsResp?.__error ? [] : agentsResp?.data || []);
        setServices(svcResp?.__error ? [] : svcResp?.data || []);

        if (locationId > 0) {
          const locResp = await bpFetch(`/admin/locations/${locationId}`);
          const raw = locResp?.data || locResp;
          const next = normalizeLocation(raw, locationId);
          setLocation(next);

          const agentsRes = await bpFetch(`/admin/locations/${locationId}/agents`);
          const rows = agentsRes?.data || [];
          const map = {};
          rows.forEach((r) => {
            const svcIds = Array.isArray(r.services) ? r.services : [];
            map[Number(r.agent_id)] = {
              selected: true,
              customize: svcIds.length > 0,
              services: svcIds.map((x) => Number(x) || 0).filter(Boolean),
            };
          });
          setAgentMap(map);
        } else {
          setAgentMap({});
          setDirty(false);
        }
      } catch (e) {
        console.error(e);
        if (alive) setError(e?.message || "Failed to load location");
      } finally {
        if (alive) setLoading(false);
      }
    }

    load();
    return () => {
      alive = false;
    };
  }, [locationId, isPro]);

  useEffect(() => {
    if (!toast) return;
    const t = setTimeout(() => setToast(""), 2500);
    return () => clearTimeout(t);
  }, [toast]);

  useEffect(() => {
    const onBeforeUnload = (e) => {
      if (!dirty) return;
      e.preventDefault();
      e.returnValue = "";
    };
    window.addEventListener("beforeunload", onBeforeUnload);
    return () => window.removeEventListener("beforeunload", onBeforeUnload);
  }, [dirty]);

  function update(patch) {
    setLocation((prev) => ({ ...prev, ...patch }));
    setDirty(true);
  }

  function validate() {
    if (!location.name.trim()) return "Location name is required.";
    return "";
  }

  async function onPickImage() {
    try {
      const img = await pickImage({ title: "Select location image" });
      update({ image_id: img.id, image_url: img.url });
    } catch (e) {
      setError(e?.message || "Image picker failed");
    }
  }

  function toggleAgent(agentId) {
    const id = Number(agentId) || 0;
    if (!id) return;
    setAgentMap((prev) => {
      const entry = prev[id] || { selected: false, customize: false, services: [] };
      return { ...prev, [id]: { ...entry, selected: !entry.selected } };
    });
    setDirty(true);
  }

  function toggleCustomize(agentId) {
    const id = Number(agentId) || 0;
    if (!id) return;
    setAgentMap((prev) => {
      const entry = prev[id] || { selected: true, customize: false, services: [] };
      return { ...prev, [id]: { ...entry, customize: !entry.customize } };
    });
    setDirty(true);
  }

  function toggleService(agentId, serviceId) {
    const aid = Number(agentId) || 0;
    const sid = Number(serviceId) || 0;
    if (!aid || !sid) return;
    setAgentMap((prev) => {
      const entry = prev[aid] || { selected: true, customize: true, services: [] };
      const set = new Set((entry.services || []).map((x) => Number(x) || 0));
      if (set.has(sid)) set.delete(sid);
      else set.add(sid);
      return { ...prev, [aid]: { ...entry, services: Array.from(set) } };
    });
    setDirty(true);
  }

  function addScheduleDay() {
    update({
      schedule: [...(location.schedule || []), { day: 1, start: "09:00", end: "17:00" }],
      use_custom_schedule: 1,
    });
  }

  function updateScheduleDay(index, key, value) {
    const next = [...(location.schedule || [])];
    next[index] = { ...next[index], [key]: value };
    update({ schedule: next });
  }

  function removeScheduleDay(index) {
    update({ schedule: (location.schedule || []).filter((_, i) => i !== index) });
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
      const payload = {
        name: location.name,
        address: location.address,
        category_id: location.category_id ? Number(location.category_id) : null,
        image_id: location.image_id ? Number(location.image_id) : null,
        use_custom_schedule: Number(location.use_custom_schedule) ? 1 : 0,
        schedule: Number(location.use_custom_schedule) ? location.schedule || [] : [],
        is_active: Number(location.is_active) ? 1 : 0,
      };

      let id = locationId;
      if (id > 0) {
        await bpFetch(`/admin/locations/${id}`, { method: "PUT", body: payload });
      } else {
        const res = await bpFetch("/admin/locations", { method: "POST", body: payload });
        const row = res?.data || res;
        id = Number(row?.id || row?.data?.id || 0) || 0;
        if (id) setLocationId(id);
      }

      if (id > 0) {
        const agentsPayload = agents
          .map((a) => {
            const aid = Number(a.id) || 0;
            const entry = agentMap[aid];
            if (!entry?.selected) return null;
            return {
              agent_id: aid,
              services: entry.customize ? entry.services || [] : null,
            };
          })
          .filter(Boolean);

        await bpFetch(`/admin/locations/${id}/agents`, { method: "POST", body: { agents: agentsPayload } });
      }

      if (!locationId && id) {
        window.history.replaceState(null, "", `admin.php?page=bp_locations_edit&id=${id}`);
      }

      setDirty(false);
      setToast("Saved");
    } catch (e) {
      console.error(e);
      setError(e?.message || "Save failed");
    } finally {
      setSaving(false);
    }
  }

  async function onDelete() {
    if (!locationId) return;
    if (!window.confirm("Delete this location? This cannot be undone.")) return;
    setSaving(true);
    setError("");
    try {
      await bpFetch(`/admin/locations/${locationId}`, { method: "DELETE" });
      window.location.href = "admin.php?page=bp_locations";
    } catch (e) {
      setError(e?.message || "Delete failed");
    } finally {
      setSaving(false);
    }
  }

  const filteredAgents = useMemo(() => {
    const q = agentSearch.trim().toLowerCase();
    if (!q) return agents;
    return agents.filter((a) => `${a.name || ""} ${a.email || ""}`.toLowerCase().includes(q));
  }, [agents, agentSearch]);

  const filteredServices = useMemo(() => {
    const q = serviceSearch.trim().toLowerCase();
    if (!q) return services;
    return services.filter((s) => `${s.name || ""} ${s.description || ""}`.toLowerCase().includes(q));
  }, [services, serviceSearch]);

  const servicesById = useMemo(() => {
    const m = new Map();
    for (const s of services) m.set(Number(s.id), s);
    return m;
  }, [services]);

  if (!isPro) {
    return <UpgradeToPro feature="Locations" />;
  }

  return (
    <div className="myplugin-page bp-location-edit">
      <main className="myplugin-content">
        {toast ? <div className="bp-toast bp-toast-success">{toast}</div> : null}

        <div className="bp-location-edit__head">
          <div>
            <div className="bp-muted bp-text-sm" style={{ fontWeight: 900 }}>
              Locations / Edit
            </div>
            <div className="bp-h1">{title}</div>
          </div>
          <div className="bp-location-edit__pillwrap">
            <span className={`bp-status-pill ${Number(location.is_active) ? "active" : "inactive"}`}>{statusLabel}</span>
          </div>
        </div>

        {error ? <div className="bp-error">{error}</div> : null}

        {loading ? (
          <div className="bp-card">Loading...</div>
        ) : (
          <div className="bp-location-edit__grid">
            <section className="bp-location-edit__main">
              <div className="bp-card bp-location-edit__section">
                <div className="bp-section-title">Basic info</div>
                <div className="bp-grid-2">
                  <div>
                    <label className="bp-filter-label">Location name *</label>
                    <input
                      className="bp-input"
                      value={location.name}
                      onChange={(e) => update({ name: e.target.value })}
                      placeholder="e.g., Copenhagen"
                    />
                  </div>
                  <div>
                    <label className="bp-filter-label">Category</label>
                    <select
                      className="bp-input"
                      value={location.category_id || 0}
                      onChange={(e) => update({ category_id: Number(e.target.value) || 0 })}
                    >
                      <option value={0}>—</option>
                      {categories.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.name || `Category #${c.id}`}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>
                <div className="bp-mt-12">
                  <label className="bp-filter-label">Address</label>
                  <textarea
                    className="bp-textarea"
                    value={location.address}
                    onChange={(e) => update({ address: e.target.value })}
                    placeholder="Street, city, zip…"
                  />
                </div>
              </div>

              <div className="bp-card bp-location-edit__section">
                <div className="bp-section-title">Availability</div>
                <label className="bp-location-edit__toggle">
                  <input
                    type="checkbox"
                    checked={!!Number(location.use_custom_schedule)}
                    onChange={(e) => update({ use_custom_schedule: e.target.checked ? 1 : 0 })}
                  />
                  <span>Use custom schedule for this location</span>
                </label>

                {Number(location.use_custom_schedule) ? (
                  <div className="bp-location-edit__schedule">
                    {(location.schedule || []).length ? (
                      (location.schedule || []).map((row, idx) => (
                        <div key={idx} className="bp-location-edit__schedrow">
                          <select
                            className="bp-input"
                            value={Number(row.day) || 0}
                            onChange={(e) => updateScheduleDay(idx, "day", Number(e.target.value) || 0)}
                          >
                            {DAYS.map((d) => (
                              <option key={d.value} value={d.value}>
                                {d.label}
                              </option>
                            ))}
                          </select>
                          <input
                            className="bp-input"
                            type="time"
                            value={row.start || "09:00"}
                            onChange={(e) => updateScheduleDay(idx, "start", e.target.value)}
                          />
                          <input
                            className="bp-input"
                            type="time"
                            value={row.end || "17:00"}
                            onChange={(e) => updateScheduleDay(idx, "end", e.target.value)}
                          />
                          <button className="bp-top-btn" type="button" onClick={() => removeScheduleDay(idx)}>
                            Remove
                          </button>
                        </div>
                      ))
                    ) : (
                      <div className="bp-muted bp-text-sm" style={{ fontWeight: 850 }}>
                        No custom schedule yet.
                      </div>
                    )}

                    <button className="bp-top-btn" type="button" onClick={addScheduleDay} style={{ marginTop: 10 }}>
                      + Add day
                    </button>
                  </div>
                ) : (
                  <div className="bp-muted bp-text-sm" style={{ fontWeight: 850 }}>
                    Uses default/global schedule.
                  </div>
                )}
              </div>

              <div className="bp-card bp-location-edit__section">
                <div className="bp-section-title">Agents assigned</div>
                <div className="bp-grid-2">
                  <div>
                    <label className="bp-filter-label">Search agents</label>
                    <input
                      className="bp-input"
                      value={agentSearch}
                      onChange={(e) => setAgentSearch(e.target.value)}
                      placeholder="Search agents..."
                    />
                  </div>
                  <div>
                    <label className="bp-filter-label">Search services</label>
                    <input
                      className="bp-input"
                      value={serviceSearch}
                      onChange={(e) => setServiceSearch(e.target.value)}
                      placeholder="Filter services list..."
                    />
                  </div>
                </div>

                <div className="bp-location-edit__agents">
                  {filteredAgents.map((a) => {
                    const aid = Number(a.id) || 0;
                    const entry = agentMap[aid] || { selected: false, customize: false, services: [] };
                    const name = a.name || `${a.first_name || ""} ${a.last_name || ""}`.trim() || `Agent #${aid}`;
                    const selected = !!entry.selected;
                    const customize = !!entry.customize;
                    const selectedServiceLabels = (entry.services || [])
                      .map((sid) => servicesById.get(Number(sid)))
                      .filter(Boolean)
                      .slice(0, 3)
                      .map((s) => s.name || `#${s.id}`);

                    return (
                      <details key={aid} className={`bp-location-edit__agent ${selected ? "is-selected" : ""}`}>
                        <summary className="bp-location-edit__agentrow">
                          <input
                            type="checkbox"
                            checked={selected}
                            onChange={() => toggleAgent(aid)}
                            onClick={(e) => e.stopPropagation()}
                            aria-label={`Assign ${name}`}
                          />
                          <span className="bp-location-edit__agentname">{name}</span>
                          <span className="bp-location-edit__agentmeta">
                            {selected ? (customize ? "Custom services" : "All services") : "Not assigned"}
                          </span>
                        </summary>

                        {selected ? (
                          <div className="bp-location-edit__agentbody">
                            <label className="bp-location-edit__toggle">
                              <input type="checkbox" checked={customize} onChange={() => toggleCustomize(aid)} />
                              <span>Customize services for this agent at this location</span>
                            </label>

                            {customize ? (
                              <div className="bp-location-edit__svcgrid">
                                {filteredServices.map((s) => {
                                  const sid = Number(s.id) || 0;
                                  const checked = (entry.services || []).includes(sid);
                                  return (
                                    <label key={sid} className="bp-location-edit__svcitem">
                                      <input type="checkbox" checked={checked} onChange={() => toggleService(aid, sid)} />
                                      <span>{s.name || `Service #${sid}`}</span>
                                    </label>
                                  );
                                })}
                              </div>
                            ) : selectedServiceLabels.length ? (
                              <div className="bp-muted bp-text-sm" style={{ fontWeight: 850 }}>
                                {selectedServiceLabels.join(", ")}
                                {entry.services && entry.services.length > 3 ? "…" : ""}
                              </div>
                            ) : null}
                          </div>
                        ) : null}
                      </details>
                    );
                  })}
                </div>
              </div>
            </section>

            <aside className="bp-location-edit__side">
              <div className="bp-card bp-location-edit__sidecard">
                <div className="bp-section-title">Image</div>
                <div className="bp-location-edit__avatar">
                  {location.image_url ? <img src={location.image_url} alt={location.name || "Location"} /> : <div className="bp-muted">No image</div>}
                </div>
                <div className="bp-location-edit__side-actions">
                  <button type="button" className="bp-top-btn" onClick={onPickImage}>
                    Choose Image
                  </button>
                  <button
                    type="button"
                    className="bp-top-btn"
                    onClick={() => update({ image_id: 0, image_url: "" })}
                    disabled={!location.image_id && !location.image_url}
                  >
                    Remove
                  </button>
                </div>
              </div>

              <div className="bp-card bp-location-edit__sidecard">
                <div className="bp-section-title">Status</div>
                <div className="bp-location-edit__seg">
                  <button
                    type="button"
                    className={`bp-location-edit__segbtn ${Number(location.is_active) ? "is-active" : ""}`}
                    onClick={() => update({ is_active: 1 })}
                  >
                    Active
                  </button>
                  <button
                    type="button"
                    className={`bp-location-edit__segbtn ${!Number(location.is_active) ? "is-active" : ""}`}
                    onClick={() => update({ is_active: 0 })}
                  >
                    Inactive
                  </button>
                </div>
              </div>

              <div className="bp-card bp-location-edit__sidecard">
                <div className="bp-section-title">Tools</div>
                <a className="bp-top-btn" href="admin.php?page=bp_location_categories_edit">
                  Manage categories
                </a>

                {locationId ? (
                  <div className="bp-location-edit__danger">
                    <button type="button" className="bp-location-edit__dangerbtn" onClick={onDelete} disabled={saving}>
                      Delete location
                    </button>
                  </div>
                ) : null}
              </div>
            </aside>
          </div>
        )}

        <div className="bp-location-edit__bar">
          <a
            className="bp-top-btn"
            href="admin.php?page=bp_locations"
            onClick={(e) => {
              if (!dirty) return;
              if (!window.confirm("You have unsaved changes. Leave anyway?")) e.preventDefault();
            }}
          >
            Cancel
          </a>
          <button className="bp-primary-btn" type="button" onClick={onSave} disabled={saving || loading}>
            {saving ? "Saving..." : "Save changes"}
          </button>
        </div>
      </main>
    </div>
  );
}
