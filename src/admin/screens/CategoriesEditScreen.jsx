import React, { useEffect, useState } from "react";
import { bpFetch } from "../api/client";
import { pickImage } from "../ui/wpMedia";

function normalizeCategory(raw, id) {
  const isActive =
    raw?.is_active !== undefined
      ? !!Number(raw.is_active)
      : raw?.is_enabled !== undefined
        ? !!Number(raw.is_enabled)
        : true;

  return {
    id: raw?.id ? Number(raw.id) : Number(id || 0) || 0,
    name: raw?.name || raw?.title || "",
    description: raw?.description || "",
    sort_order: Number(raw?.sort_order || 0) || 0,
    image_id: Number(raw?.image_id || 0) || 0,
    image_url: raw?.image_url || raw?.image || "",
    is_active: isActive ? 1 : 0,
  };
}

export default function CategoriesEditScreen() {
  const params = new URLSearchParams(window.location.search);
  const id = Number(params.get("id") || 0) || 0;

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [toast, setToast] = useState("");
  const [dirty, setDirty] = useState(false);

  const [cat, setCat] = useState(() =>
    normalizeCategory(
      {
        id,
        name: "",
        description: "",
        sort_order: 0,
        is_active: 1,
      },
      id
    )
  );

  const title = id ? "Edit Category" : "Add Category";
  const statusLabel = Number(cat.is_active) ? "Active" : "Inactive";

  useEffect(() => {
    let alive = true;

    async function load() {
      if (!id) {
        setLoading(false);
        setDirty(false);
        return;
      }
      setLoading(true);
      setError("");
      try {
        const resp = await bpFetch(`/admin/categories/${id}`);
        const raw = resp?.data?.category || resp?.data || resp?.category || resp || {};
        if (!alive) return;
        setCat(normalizeCategory(raw, id));
        setDirty(false);
      } catch (e) {
        console.error(e);
        if (alive) setError(e?.message || "Failed to load category");
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
    setCat((prev) => ({ ...prev, ...patch }));
    setDirty(true);
  }

  function validate() {
    if (!cat.name.trim()) return "Category name is required.";
    return "";
  }

  async function onPickImage() {
    try {
      const img = await pickImage({ title: "Select category image" });
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
        ...cat,
        is_active: Number(cat.is_active) ? 1 : 0,
        sort_order: Number(cat.sort_order) || 0,
      };

      let newId = id;
      if (id) {
        await bpFetch(`/admin/categories/${id}`, { method: "PATCH", body: payload });
      } else {
        const res = await bpFetch(`/admin/categories`, { method: "POST", body: payload });
        newId = Number(res?.data?.id || res?.data?.category?.id || res?.id || 0) || 0;
      }

      setToast("Saved");
      setDirty(false);

      if (!id && newId) {
        window.location.href = `admin.php?page=bp_categories_edit&id=${newId}`;
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
    if (!window.confirm("Delete this category? This cannot be undone.")) return;
    setSaving(true);
    setError("");
    try {
      await bpFetch(`/admin/categories/${id}`, { method: "DELETE" });
      window.location.href = "admin.php?page=bp_categories";
    } catch (e) {
      setError(e?.message || "Delete failed");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="myplugin-page bp-category-edit">
      <main className="myplugin-content">
        {toast ? <div className="bp-toast bp-toast-success">{toast}</div> : null}

        <div className="bp-category-edit__head">
          <div>
            <div className="bp-muted bp-text-sm" style={{ fontWeight: 900 }}>
              Categories / Edit
            </div>
            <div className="bp-h1">{title}</div>
          </div>
          <div className="bp-category-edit__pillwrap">
            <span className={`bp-status-pill ${Number(cat.is_active) ? "active" : "inactive"}`}>{statusLabel}</span>
          </div>
        </div>

        {error ? <div className="bp-error">{error}</div> : null}

        {loading ? (
          <div className="bp-card">Loading...</div>
        ) : (
          <div className="bp-category-edit__grid">
            <section className="bp-category-edit__main">
              <div className="bp-card bp-category-edit__section">
                <div className="bp-section-title">Basic info</div>
                <div>
                  <label className="bp-filter-label">Category name *</label>
                  <input
                    className="bp-input"
                    value={cat.name}
                    onChange={(e) => update({ name: e.target.value })}
                    placeholder="e.g., Residential"
                  />
                </div>
                <div className="bp-mt-12">
                  <label className="bp-filter-label">Description</label>
                  <textarea
                    className="bp-textarea"
                    value={cat.description}
                    onChange={(e) => update({ description: e.target.value })}
                    placeholder="Optional description"
                  />
                </div>
              </div>
            </section>

            <aside className="bp-category-edit__side">
              <div className="bp-card bp-category-edit__sidecard">
                <div className="bp-section-title">Image</div>
                <div className="bp-category-edit__avatar">
                  {cat.image_url ? <img src={cat.image_url} alt={cat.name || "Category"} /> : <div className="bp-muted">No image</div>}
                </div>
                <div className="bp-category-edit__side-actions">
                  <button type="button" className="bp-top-btn" onClick={onPickImage}>
                    Choose Image
                  </button>
                  <button
                    type="button"
                    className="bp-top-btn"
                    onClick={() => update({ image_id: 0, image_url: "" })}
                    disabled={!cat.image_id && !cat.image_url}
                  >
                    Remove
                  </button>
                </div>
              </div>

              <div className="bp-card bp-category-edit__sidecard">
                <div className="bp-section-title">Status</div>
                <div className="bp-category-edit__seg">
                  <button
                    type="button"
                    className={`bp-category-edit__segbtn ${Number(cat.is_active) ? "is-active" : ""}`}
                    onClick={() => update({ is_active: 1 })}
                  >
                    Active
                  </button>
                  <button
                    type="button"
                    className={`bp-category-edit__segbtn ${!Number(cat.is_active) ? "is-active" : ""}`}
                    onClick={() => update({ is_active: 0 })}
                  >
                    Inactive
                  </button>
                </div>
              </div>

              <div className="bp-card bp-category-edit__sidecard">
                <div className="bp-section-title">Advanced</div>
                <label className="bp-filter-label">Sort order</label>
                <input className="bp-input" type="number" value={cat.sort_order} onChange={(e) => update({ sort_order: e.target.value })} />

                {id ? (
                  <div className="bp-category-edit__danger">
                    <button type="button" className="bp-category-edit__dangerbtn" onClick={onDelete} disabled={saving}>
                      Delete category
                    </button>
                  </div>
                ) : null}
              </div>
            </aside>
          </div>
        )}

        <div className="bp-category-edit__bar">
          <a
            className="bp-top-btn"
            href="admin.php?page=bp_categories"
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

