import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";
import { pickImage } from "../ui/wpMedia";

const defaultCurrency = (window.BP_ADMIN?.currency || "USD").toUpperCase();

function toMoneyNumber(v) {
  const n = Number(v);
  return Number.isFinite(n) ? n : 0;
}

function normalizeService(raw) {
  const duration = Number(raw?.duration ?? raw?.duration_minutes ?? raw?.duration_min ?? 0) || 0;
  const price =
    raw?.price !== undefined && raw?.price !== null
      ? toMoneyNumber(raw.price)
      : raw?.price_cents !== undefined && raw?.price_cents !== null
        ? toMoneyNumber(raw.price_cents) / 100
        : 0;
  const currency = (raw?.currency || defaultCurrency || "USD").toUpperCase();
  const isActive =
    raw?.is_active !== undefined
      ? !!Number(raw.is_active)
      : raw?.is_enabled !== undefined
        ? !!Number(raw.is_enabled)
        : true;

  return {
    id: raw?.id ? Number(raw.id) : 0,
    name: raw?.name || raw?.title || "",
    description: raw?.description || "",
    category_ids: [],
    duration,
    price,
    currency,
    buffer_before: Number(raw?.buffer_before || 0) || 0,
    buffer_after: Number(raw?.buffer_after || 0) || 0,
    capacity: Number(raw?.capacity || 1) || 1,
    sort_order: Number(raw?.sort_order || 0) || 0,
    image_id: Number(raw?.image_id || 0) || 0,
    image_url: raw?.image_url || raw?.image || "",
    is_active: isActive ? 1 : 0,
  };
}

export default function ServicesEditScreen() {
  const params = new URLSearchParams(window.location.search);
  const id = Number(params.get("id") || 0) || 0;

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [toast, setToast] = useState("");

  const [catsLoading, setCatsLoading] = useState(true);
  const [catsError, setCatsError] = useState("");
  const [categories, setCategories] = useState([]);
  const [catSearch, setCatSearch] = useState("");

  const [service, setService] = useState(() =>
    normalizeService({
      id,
      name: "",
      description: "",
      duration: 30,
      price: 0,
      currency: defaultCurrency || "USD",
      capacity: 1,
      is_active: 1,
    })
  );
  const [dirty, setDirty] = useState(false);

  const title = id ? "Edit Service" : "Add Service";
  const statusLabel = Number(service.is_active) ? "Active" : "Inactive";

  useEffect(() => {
    let alive = true;

    async function load() {
      setLoading(true);
      setError("");
      try {
        const [catsResp, relResp, svcResp] = await Promise.all([
          bpFetch("/admin/categories").catch((e) => ({ __error: e })),
          id ? bpFetch(`/admin/services/${id}/categories`).catch((e) => ({ __error: e })) : Promise.resolve({ data: [] }),
          id ? bpFetch(`/admin/services/${id}`).catch((e) => ({ __error: e })) : Promise.resolve({ data: null }),
        ]);

        if (!alive) return;

        // categories
        if (catsResp?.__error) {
          setCatsError(catsResp.__error?.message || "Failed to load categories");
          setCategories([]);
        } else {
          setCatsError("");
          setCategories(catsResp?.data || []);
        }
        setCatsLoading(false);

        // service
        if (id) {
          if (svcResp?.__error) throw svcResp.__error;
          const raw = svcResp?.data?.service || svcResp?.data || svcResp?.service || svcResp || {};
          const next = normalizeService(raw);

          // relation
          let rel = [];
          if (relResp?.__error) {
            // non-fatal, just show empty
            rel = [];
          } else {
            rel = relResp?.data || [];
          }
          next.category_ids = Array.isArray(rel) ? rel.map((x) => Number(x) || 0).filter(Boolean) : [];

          setService(next);
        }

        setDirty(false);
      } catch (e) {
        console.error(e);
        setError(e?.message || "Failed to load service");
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
    setService((prev) => ({ ...prev, ...patch }));
    setDirty(true);
  }

  async function onPickImage() {
    try {
      const img = await pickImage({ title: "Select service image" });
      update({ image_id: img.id, image_url: img.url });
    } catch (e) {
      setError(e?.message || "Image picker failed");
    }
  }

  function toggleCategory(catId) {
    const cid = Number(catId) || 0;
    if (!cid) return;
    const set = new Set(service.category_ids || []);
    if (set.has(cid)) set.delete(cid);
    else set.add(cid);
    update({ category_ids: Array.from(set) });
  }

  const filteredCategories = useMemo(() => {
    const q = catSearch.trim().toLowerCase();
    const list = categories || [];
    if (!q) return list;
    return list.filter((c) => `${c.name || ""}`.toLowerCase().includes(q));
  }, [categories, catSearch]);

  const selectedCategoryNames = useMemo(() => {
    const byId = new Map((categories || []).map((c) => [Number(c.id), c]));
    return (service.category_ids || [])
      .map((cid) => byId.get(Number(cid)))
      .filter(Boolean)
      .map((c) => ({ id: Number(c.id), name: c.name || `#${c.id}` }));
  }, [categories, service.category_ids]);

  function validate() {
    if (!service.name.trim()) return "Service name is required.";
    if (!Number.isFinite(Number(service.duration)) || Number(service.duration) <= 0) return "Duration must be > 0.";
    if (!Number.isFinite(Number(service.price)) || Number(service.price) < 0) return "Price must be 0 or more.";
    if (!Number.isFinite(Number(service.capacity)) || Number(service.capacity) <= 0) return "Capacity must be > 0.";
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
      const payload = {
        ...service,
        // backend expects ints/strings; keep stable types
        duration: Number(service.duration) || 0,
        price: toMoneyNumber(service.price),
        buffer_before: Number(service.buffer_before) || 0,
        buffer_after: Number(service.buffer_after) || 0,
        capacity: Number(service.capacity) || 1,
        sort_order: Number(service.sort_order) || 0,
        is_active: Number(service.is_active) ? 1 : 0,
      };

      let newId = id;
      if (id) {
        await bpFetch(`/admin/services/${id}`, { method: "PATCH", body: payload });
      } else {
        const res = await bpFetch(`/admin/services`, { method: "POST", body: payload });
        newId = Number(res?.data?.id || res?.data?.service?.id || res?.id || 0) || 0;
      }

      if (newId) {
        await bpFetch(`/admin/services/${newId}/categories`, {
          method: "PUT",
          body: { category_ids: service.category_ids || [] },
        });
      }

      setToast("Saved");
      setDirty(false);

      // if creating, redirect into edit URL so refresh works and future saves patch
      if (!id && newId) {
        window.location.href = `admin.php?page=bp_services_edit&id=${newId}`;
      }
    } catch (e) {
      console.error(e);
      setError(e?.message || "Save failed");
    } finally {
      setSaving(false);
    }
  }

  async function onDelete() {
    if (!id) return;
    if (!window.confirm("Delete this service? This cannot be undone.")) return;
    setSaving(true);
    setError("");
    try {
      await bpFetch(`/admin/services/${id}`, { method: "DELETE" });
      window.location.href = "admin.php?page=bp_services";
    } catch (e) {
      setError(e?.message || "Delete failed");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="myplugin-page bp-service-edit">
      <main className="myplugin-content">
        {toast ? <div className="bp-toast bp-toast-success">{toast}</div> : null}

        <div className="bp-service-edit__head">
          <div>
            <div className="bp-muted bp-text-sm" style={{ fontWeight: 900 }}>
              Services / Edit
            </div>
            <div className="bp-h1">{title}</div>
          </div>
          <div className="bp-service-edit__pillwrap">
            <span className={`bp-status-pill ${Number(service.is_active) ? "active" : "inactive"}`}>{statusLabel}</span>
          </div>
        </div>

        {error ? <div className="bp-error">{error}</div> : null}

        {loading ? (
          <div className="bp-card">Loading...</div>
        ) : (
          <div className="bp-service-edit__grid">
            <section className="bp-service-edit__main">
              <div className="bp-card bp-service-edit__section">
                <div className="bp-section-title">Basic info</div>
                <div className="bp-grid bp-grid-2 bp-gap-10">
                  <div>
                    <label className="bp-filter-label">Service name *</label>
                    <input
                      className="bp-input"
                      value={service.name}
                      onChange={(e) => update({ name: e.target.value })}
                      placeholder="e.g., Cleaning"
                    />
                  </div>
                  <div>
                    <label className="bp-filter-label">Category (optional)</label>
                    <div className="bp-muted" style={{ fontWeight: 800, paddingTop: 10 }}>
                      {selectedCategoryNames.length ? `${selectedCategoryNames.length} selected` : "None"}
                    </div>
                  </div>
                </div>
                <div className="bp-mt-12">
                  <label className="bp-filter-label">Description</label>
                  <textarea
                    className="bp-textarea"
                    value={service.description}
                    onChange={(e) => update({ description: e.target.value })}
                    placeholder="Short description shown in admin (optional)"
                  />
                </div>
              </div>

              <div className="bp-card bp-service-edit__section">
                <div className="bp-section-title">Pricing</div>
                <div className="bp-grid bp-grid-2 bp-gap-10">
                  <div>
                    <label className="bp-filter-label">Price</label>
                    <input
                      className="bp-input"
                      type="number"
                      step="0.01"
                      min="0"
                      value={service.price}
                      onChange={(e) => update({ price: e.target.value })}
                    />
                  </div>
                  <div>
                    <label className="bp-filter-label">Currency</label>
                    <select className="bp-input" value={service.currency} onChange={(e) => update({ currency: e.target.value })}>
                      <option value="USD">USD</option>
                      <option value="EUR">EUR</option>
                      <option value="GBP">GBP</option>
                      <option value="DKK">DKK</option>
                      <option value="NOK">NOK</option>
                      <option value="SEK">SEK</option>
                    </select>
                  </div>
                </div>
              </div>

              <div className="bp-card bp-service-edit__section">
                <div className="bp-section-title">Duration & capacity</div>
                <div className="bp-grid bp-grid-2 bp-gap-10">
                  <div>
                    <label className="bp-filter-label">Duration (minutes) *</label>
                    <input
                      className="bp-input"
                      type="number"
                      min="1"
                      value={service.duration}
                      onChange={(e) => update({ duration: e.target.value })}
                    />
                  </div>
                  <div>
                    <label className="bp-filter-label">Capacity *</label>
                    <input
                      className="bp-input"
                      type="number"
                      min="1"
                      value={service.capacity}
                      onChange={(e) => update({ capacity: e.target.value })}
                    />
                  </div>
                </div>
                <div className="bp-grid bp-grid-2 bp-gap-10 bp-mt-10">
                  <div>
                    <label className="bp-filter-label">Buffer before (minutes)</label>
                    <input
                      className="bp-input"
                      type="number"
                      min="0"
                      value={service.buffer_before}
                      onChange={(e) => update({ buffer_before: e.target.value })}
                    />
                  </div>
                  <div>
                    <label className="bp-filter-label">Buffer after (minutes)</label>
                    <input
                      className="bp-input"
                      type="number"
                      min="0"
                      value={service.buffer_after}
                      onChange={(e) => update({ buffer_after: e.target.value })}
                    />
                  </div>
                </div>
              </div>

              <div className="bp-card bp-service-edit__section">
                <div className="bp-section-title">Categories</div>
                <div className="bp-grid bp-grid-2 bp-gap-10">
                  <div>
                    <label className="bp-filter-label">Search</label>
                    <input className="bp-input" value={catSearch} onChange={(e) => setCatSearch(e.target.value)} placeholder="Search categories..." />
                  </div>
                  <div className="bp-service-edit__cat-actions">
                    <button
                      type="button"
                      className="bp-top-btn"
                      onClick={() => update({ category_ids: (categories || []).map((c) => Number(c.id)).filter(Boolean) })}
                      disabled={catsLoading || !categories?.length}
                    >
                      Select all
                    </button>
                    <button type="button" className="bp-top-btn" onClick={() => update({ category_ids: [] })}>
                      Clear
                    </button>
                  </div>
                </div>

                {catsError ? <div className="bp-error" style={{ marginTop: 10 }}>{catsError}</div> : null}

                <div className="bp-service-edit__cats">
                  {catsLoading ? (
                    <div className="bp-muted" style={{ padding: 10 }}>Loading categories…</div>
                  ) : filteredCategories.length === 0 ? (
                    <div className="bp-muted" style={{ padding: 10 }}>No categories.</div>
                  ) : (
                    filteredCategories.map((c) => {
                      const cid = Number(c.id) || 0;
                      const checked = (service.category_ids || []).includes(cid);
                      return (
                        <label key={cid} className="bp-service-edit__cat">
                          <input
                            type="checkbox"
                            checked={checked}
                            onChange={() => toggleCategory(cid)}
                          />
                          <span>{c.name || `#${cid}`}</span>
                        </label>
                      );
                    })
                  )}
                </div>

                {selectedCategoryNames.length ? (
                  <div className="bp-service-edit__chips">
                    {selectedCategoryNames.map((c) => (
                      <button
                        key={c.id}
                        type="button"
                        className="bp-chip-btn"
                        onClick={() => toggleCategory(c.id)}
                        title="Remove"
                      >
                        {c.name} ×
                      </button>
                    ))}
                  </div>
                ) : null}
              </div>
            </section>

            <aside className="bp-service-edit__side">
              <div className="bp-card bp-service-edit__sidecard">
                <div className="bp-section-title">Image</div>
                <div className="bp-service-edit__avatar">
                  {service.image_url ? <img src={service.image_url} alt={service.name || "Service"} /> : <div className="bp-muted">No image</div>}
                </div>
                <div className="bp-service-edit__side-actions">
                  <button type="button" className="bp-top-btn" onClick={onPickImage}>
                    Choose Image
                  </button>
                  <button
                    type="button"
                    className="bp-top-btn"
                    onClick={() => update({ image_id: 0, image_url: "" })}
                    disabled={!service.image_id && !service.image_url}
                  >
                    Remove
                  </button>
                </div>
              </div>

              <div className="bp-card bp-service-edit__sidecard">
                <div className="bp-section-title">Status</div>
                <div className="bp-service-edit__seg">
                  <button
                    type="button"
                    className={`bp-service-edit__segbtn ${Number(service.is_active) ? "is-active" : ""}`}
                    onClick={() => update({ is_active: 1 })}
                  >
                    Active
                  </button>
                  <button
                    type="button"
                    className={`bp-service-edit__segbtn ${!Number(service.is_active) ? "is-active" : ""}`}
                    onClick={() => update({ is_active: 0 })}
                  >
                    Inactive
                  </button>
                </div>
                <div className="bp-muted bp-text-sm" style={{ marginTop: 8, fontWeight: 800 }}>
                  Inactive services won’t be selectable for new bookings.
                </div>
              </div>

              <div className="bp-card bp-service-edit__sidecard">
                <div className="bp-section-title">Advanced</div>
                <label className="bp-filter-label">Sort order</label>
                <input className="bp-input" type="number" value={service.sort_order} onChange={(e) => update({ sort_order: e.target.value })} />

                {id ? (
                  <div className="bp-service-edit__danger">
                    <button type="button" className="bp-service-edit__dangerbtn" onClick={onDelete} disabled={saving}>
                      Delete service
                    </button>
                  </div>
                ) : null}
              </div>
            </aside>
          </div>
        )}

        <div className="bp-service-edit__bar">
          <a
            className="bp-top-btn"
            href="admin.php?page=bp_services"
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
