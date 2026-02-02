import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";
import { pickImage } from "../ui/wpMedia";

function normalizeExtra(raw, id) {
  const isActive =
    raw?.is_active !== undefined
      ? !!Number(raw.is_active)
      : raw?.is_enabled !== undefined
        ? !!Number(raw.is_enabled)
        : true;

  const price =
    raw?.price !== undefined && raw?.price !== null
      ? Number(raw.price) || 0
      : raw?.price_cents !== undefined && raw?.price_cents !== null
        ? (Number(raw.price_cents) || 0) / 100
        : 0;

  return {
    id: raw?.id ? Number(raw.id) : Number(id || 0) || 0,
    name: raw?.name || raw?.title || "",
    description: raw?.description || "",
    price,
    sort_order: Number(raw?.sort_order || 0) || 0,
    image_id: Number(raw?.image_id || 0) || 0,
    image_url: raw?.image_url || raw?.image || "",
    is_active: isActive ? 1 : 0,
    service_ids: [],
  };
}

export default function ExtrasEditScreen() {
  const params = new URLSearchParams(window.location.search);
  const id = Number(params.get("id") || 0) || 0;

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [toast, setToast] = useState("");
  const [dirty, setDirty] = useState(false);

  const [services, setServices] = useState([]);
  const [svcSearch, setSvcSearch] = useState("");

  const [extra, setExtra] = useState(() =>
    normalizeExtra(
      {
        id,
        name: "",
        description: "",
        price: 0,
        sort_order: 0,
        is_active: 1,
      },
      id
    )
  );

  const title = id ? "Edit Extra" : "Add Extra";
  const statusLabel = Number(extra.is_active) ? "Active" : "Inactive";

  useEffect(() => {
    let alive = true;

    async function load() {
      setLoading(true);
      setError("");
      try {
        const [svcResp, relResp, extraResp] = await Promise.all([
          bpFetch("/admin/services").catch((e) => ({ __error: e })),
          id ? bpFetch(`/admin/extras/${id}/services`).catch((e) => ({ __error: e })) : Promise.resolve({ data: [] }),
          id ? bpFetch(`/admin/extras/${id}`).catch((e) => ({ __error: e })) : Promise.resolve({ data: null }),
        ]);

        if (!alive) return;

        if (svcResp?.__error) {
          setServices([]);
        } else {
          setServices(svcResp?.data || []);
        }

        if (id) {
          if (extraResp?.__error) throw extraResp.__error;
          const raw = extraResp?.data?.extra || extraResp?.data || extraResp?.extra || extraResp || {};
          const next = normalizeExtra(raw, id);

          let rel = [];
          if (relResp?.__error) {
            rel = [];
          } else {
            rel = relResp?.data || [];
          }
          next.service_ids = Array.isArray(rel) ? rel.map((x) => Number(x) || 0).filter(Boolean) : [];
          setExtra(next);
        }

        setDirty(false);
      } catch (e) {
        console.error(e);
        if (alive) setError(e?.message || "Failed to load extra");
      } finally {
        if (alive) setLoading(false);
      }
    }

    load();
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
    setExtra((prev) => ({ ...prev, ...patch }));
    setDirty(true);
  }

  const servicesById = useMemo(() => {
    const m = new Map();
    for (const s of services) m.set(Number(s.id), s);
    return m;
  }, [services]);

  const selectedServices = useMemo(() => {
    return (extra.service_ids || [])
      .map((id) => servicesById.get(Number(id)))
      .filter(Boolean);
  }, [extra.service_ids, servicesById]);

  const filteredServices = useMemo(() => {
    const q = svcSearch.trim().toLowerCase();
    if (!q) return services;
    return services.filter((s) => `${s.name || ""} ${s.description || ""}`.toLowerCase().includes(q));
  }, [services, svcSearch]);

  function toggleService(serviceId) {
    const sid = Number(serviceId) || 0;
    if (!sid) return;
    update({
      service_ids: (extra.service_ids || []).includes(sid)
        ? (extra.service_ids || []).filter((x) => Number(x) !== sid)
        : [...(extra.service_ids || []), sid],
    });
  }

  function validate() {
    if (!extra.name.trim()) return "Extra name is required.";
    const price = Number(extra.price);
    if (!Number.isFinite(price) || price < 0) return "Price must be 0 or greater.";
    return "";
  }

  async function onPickImage() {
    try {
      const img = await pickImage({ title: "Select extra image" });
      update({ image_id: img.id, image_url: img.url });
    } catch (e) {
      setError(e?.message || "Image picker failed");
    }
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
        name: extra.name,
        description: extra.description,
        price: Number(extra.price) || 0,
        sort_order: Number(extra.sort_order) || 0,
        image_id: Number(extra.image_id) || 0,
        is_active: Number(extra.is_active) ? 1 : 0,
      };

      let newId = id;
      if (id) {
        await bpFetch(`/admin/extras/${id}`, { method: "PATCH", body: payload });
        await bpFetch(`/admin/extras/${id}/services`, { method: "PUT", body: { service_ids: extra.service_ids || [] } });
      } else {
        const res = await bpFetch(`/admin/extras`, { method: "POST", body: payload });
        newId = Number(res?.data?.id || res?.data?.extra?.id || res?.id || 0) || 0;
        if (newId) {
          await bpFetch(`/admin/extras/${newId}/services`, { method: "PUT", body: { service_ids: extra.service_ids || [] } });
          window.location.href = `admin.php?page=bp_extras_edit&id=${newId}`;
          return;
        }
      }

      setToast("Saved");
      setDirty(false);
    } catch (e) {
      console.error(e);
      setError(e?.message || "Save failed");
    } finally {
      setSaving(false);
    }
  }

  async function onDelete() {
    if (!id) return;
    if (!window.confirm("Delete this extra? This cannot be undone.")) return;
    setSaving(true);
    setError("");
    try {
      await bpFetch(`/admin/extras/${id}`, { method: "DELETE" });
      window.location.href = "admin.php?page=bp_extras";
    } catch (e) {
      setError(e?.message || "Delete failed");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="myplugin-page bp-extra-edit">
      <main className="myplugin-content">
        {toast ? <div className="bp-toast bp-toast-success">{toast}</div> : null}

        <div className="bp-extra-edit__head">
          <div>
            <div className="bp-muted bp-text-sm" style={{ fontWeight: 900 }}>
              Extras / Edit
            </div>
            <div className="bp-h1">{title}</div>
          </div>
          <div className="bp-extra-edit__pillwrap">
            <span className={`bp-status-pill ${Number(extra.is_active) ? "active" : "inactive"}`}>{statusLabel}</span>
          </div>
        </div>

        {error ? <div className="bp-error">{error}</div> : null}

        {loading ? (
          <div className="bp-card">Loading...</div>
        ) : (
          <div className="bp-extra-edit__grid">
            <section className="bp-extra-edit__main">
              <div className="bp-card bp-extra-edit__section">
                <div className="bp-section-title">Basic info</div>
                <div>
                  <label className="bp-filter-label">Extra name *</label>
                  <input
                    className="bp-input"
                    value={extra.name}
                    onChange={(e) => update({ name: e.target.value })}
                    placeholder="e.g., Inside cleaning"
                  />
                </div>
                <div className="bp-mt-12">
                  <label className="bp-filter-label">Description</label>
                  <textarea
                    className="bp-textarea"
                    value={extra.description}
                    onChange={(e) => update({ description: e.target.value })}
                    placeholder="Optional description"
                  />
                </div>
              </div>

              <div className="bp-card bp-extra-edit__section">
                <div className="bp-section-title">Pricing</div>
                <div className="bp-grid-2">
                  <div>
                    <label className="bp-filter-label">Price</label>
                    <input
                      className="bp-input"
                      type="number"
                      step="0.01"
                      min="0"
                      value={extra.price}
                      onChange={(e) => update({ price: e.target.value })}
                    />
                  </div>
                  <div>
                    <label className="bp-filter-label">Sort order</label>
                    <input
                      className="bp-input"
                      type="number"
                      value={extra.sort_order}
                      onChange={(e) => update({ sort_order: e.target.value })}
                    />
                  </div>
                </div>
              </div>

              <div className="bp-card bp-extra-edit__section">
                <div className="bp-section-title">Services assigned</div>
                <div className="bp-grid-2">
                  <div>
                    <label className="bp-filter-label">Search services</label>
                    <input
                      className="bp-input"
                      value={svcSearch}
                      onChange={(e) => setSvcSearch(e.target.value)}
                      placeholder="Search services..."
                    />
                  </div>
                  <div className="bp-service-edit__cat-actions">
                    <button
                      className="bp-top-btn"
                      type="button"
                      onClick={() => update({ service_ids: services.map((s) => Number(s.id)).filter(Boolean) })}
                    >
                      Select all
                    </button>
                    <button className="bp-top-btn" type="button" onClick={() => update({ service_ids: [] })}>
                      Clear
                    </button>
                  </div>
                </div>

                {selectedServices.length ? (
                  <div className="bp-service-edit__chips">
                    {selectedServices.map((s) => (
                      <button
                        key={s.id}
                        type="button"
                        className="bp-chip"
                        onClick={() => toggleService(s.id)}
                        title="Remove"
                      >
                        {s.name || `Service #${s.id}`} ×
                      </button>
                    ))}
                  </div>
                ) : (
                  <div className="bp-muted bp-text-sm" style={{ marginTop: 10, fontWeight: 850 }}>
                    No services selected.
                  </div>
                )}

                <div className="bp-extra-edit__services">
                  {filteredServices.map((s) => {
                    const sid = Number(s.id) || 0;
                    const checked = (extra.service_ids || []).includes(sid);
                    const label = s.name || `Service #${sid}`;
                    return (
                      <label key={sid} className="bp-extra-edit__service">
                        <input type="checkbox" checked={checked} onChange={() => toggleService(sid)} />
                        <span className="bp-extra-edit__service-text">{label}</span>
                      </label>
                    );
                  })}
                </div>
              </div>
            </section>

            <aside className="bp-extra-edit__side">
              <div className="bp-card bp-extra-edit__sidecard">
                <div className="bp-section-title">Image</div>
                <div className="bp-extra-edit__avatar">
                  {extra.image_url ? (
                    <img src={extra.image_url} alt={extra.name || "Extra"} />
                  ) : (
                    <div className="bp-muted">No image</div>
                  )}
                </div>
                <div className="bp-extra-edit__side-actions">
                  <button type="button" className="bp-top-btn" onClick={onPickImage}>
                    Choose Image
                  </button>
                  <button
                    type="button"
                    className="bp-top-btn"
                    onClick={() => update({ image_id: 0, image_url: "" })}
                    disabled={!extra.image_id && !extra.image_url}
                  >
                    Remove
                  </button>
                </div>
              </div>

              <div className="bp-card bp-extra-edit__sidecard">
                <div className="bp-section-title">Status</div>
                <div className="bp-extra-edit__seg">
                  <button
                    type="button"
                    className={`bp-extra-edit__segbtn ${Number(extra.is_active) ? "is-active" : ""}`}
                    onClick={() => update({ is_active: 1 })}
                  >
                    Active
                  </button>
                  <button
                    type="button"
                    className={`bp-extra-edit__segbtn ${!Number(extra.is_active) ? "is-active" : ""}`}
                    onClick={() => update({ is_active: 0 })}
                  >
                    Inactive
                  </button>
                </div>
              </div>

              <div className="bp-card bp-extra-edit__sidecard">
                <div className="bp-section-title">Advanced</div>
                <div className="bp-muted bp-text-sm" style={{ fontWeight: 850 }}>
                  Extras can be assigned to services to appear as add-ons in the booking flow.
                </div>

                {id ? (
                  <div className="bp-extra-edit__danger">
                    <button type="button" className="bp-extra-edit__dangerbtn" onClick={onDelete} disabled={saving}>
                      Delete extra
                    </button>
                  </div>
                ) : null}
              </div>
            </aside>
          </div>
        )}

        <div className="bp-extra-edit__bar">
          <a
            className="bp-top-btn"
            href="admin.php?page=bp_extras"
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

