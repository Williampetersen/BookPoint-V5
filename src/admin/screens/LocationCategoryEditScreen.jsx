import React, { useEffect, useState } from "react";
import { bpFetch } from "../api/client";
import { pickImage } from "../ui/wpMedia";

function getQueryInt(key) {
  const params = new URLSearchParams(window.location.search);
  const raw = params.get(key);
  const parsed = raw ? parseInt(raw, 10) : 0;
  return Number.isFinite(parsed) ? parsed : 0;
}

export default function LocationCategoryEditScreen() {
  const initialId = getQueryInt("id");
  const [categoryId, setCategoryId] = useState(initialId);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const [edit, setEdit] = useState(null);

  useEffect(() => {
    loadCategory();
  }, [categoryId]);

  async function loadCategory() {
    setLoading(true);
    setError("");
    setNotice("");
    try {
      if (categoryId > 0) {
        const res = await bpFetch("/admin/location-categories");
        const rows = res?.data || [];
        const row = rows.find((r) => Number(r.id) === categoryId) || null;
        if (!row) {
          setError("Category not found.");
          setEdit(null);
        } else {
          setEdit(row);
        }
      } else {
        setEdit({ name: "", image_id: 0, image_url: "" });
      }
    } catch (e) {
      setError(e.message || "Failed to load category");
    } finally {
      setLoading(false);
    }
  }

  async function pickCategoryImage() {
    try {
      const img = await pickImage({ title: "Select category image" });
      setEdit((p) => ({ ...p, image_id: img.id, image_url: img.url }));
    } catch (e) {
      setError(e.message || "Image picker failed");
    }
  }

  async function saveCategory() {
    if (!edit?.name) return;
    setSaving(true);
    setError("");
    setNotice("");
    try {
      if (categoryId > 0) {
        await bpFetch(`/admin/location-categories/${categoryId}`, {
          method: "PUT",
          body: { name: edit.name, image_id: edit.image_id || 0 },
        });
        setNotice("Saved changes.");
      } else {
        await bpFetch(`/admin/location-categories`, {
          method: "POST",
          body: { name: edit.name, image_id: edit.image_id || 0 },
        });
        try {
          const list = await bpFetch("/admin/location-categories");
          const rows = list?.data || [];
          const latest = rows.sort((a, b) => (b.id || 0) - (a.id || 0))[0];
          if (latest?.id) {
            window.location.href = `admin.php?page=bp_location_categories_edit&id=${latest.id}`;
            return;
          }
        } catch (e) {
          // ignore, fallback to list
        }
        window.location.href = "admin.php?page=bp_locations";
        return;
      }
    } catch (e) {
      setError(e.message || "Category save failed");
    } finally {
      setSaving(false);
    }
  }

  async function deleteCategory() {
    if (!categoryId) return;
    if (!confirm("Delete this category?")) return;
    setSaving(true);
    setError("");
    try {
      await bpFetch(`/admin/location-categories/${categoryId}`, { method: "DELETE" });
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
          <div className="bp-h1">{categoryId ? "Edit Category" : "Add Category"}</div>
          <div className="bp-muted">Manage category profile and photo.</div>
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
          <div className="bp-section">
            <div className="bp-section-title">Category details</div>
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
                  <th><label>Category Image</label></th>
                  <td>
                    <div style={{ display: "flex", gap: 12, alignItems: "center", marginBottom: 10 }}>
                      <div className="bp-entity-thumb" style={{ width: 72, height: 72 }}>
                        {edit.image_url ? <img src={edit.image_url} alt="" /> : <div className="bp-entity-initial">C</div>}
                      </div>
                      <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
                        <button className="button" onClick={pickCategoryImage}>Choose Image</button>
                        {edit.image_id ? (
                          <button className="button" onClick={() => setEdit((p) => ({ ...p, image_id: 0, image_url: "" }))}>
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
            {categoryId ? (
              <button className="button button-secondary" onClick={deleteCategory} disabled={saving}>
                Delete
              </button>
            ) : null}
            <a className="button" href="admin.php?page=bp_locations">Back</a>
            <button className="button button-primary" onClick={saveCategory} disabled={saving || !edit.name}>
              {saving ? "Saving..." : "Save Changes"}
            </button>
          </p>
        </div>
      )}
    </div>
  );
}
