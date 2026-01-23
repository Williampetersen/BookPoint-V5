import React, { useEffect, useState } from "react";
import { bpFetch } from "../api/client";

export default function CategoriesScreen() {
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");

  useEffect(() => {
    loadCategories();
  }, []);

  async function loadCategories() {
    try {
      setLoading(true);
      const resp = await bpFetch("/admin/categories");
      setCategories(resp?.data || []);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  }

  const filtered = categories.filter((c) =>
    (c.name || "").toLowerCase().includes(search.toLowerCase())
  );

  const stats = categories.reduce(
    (acc, c) => {
      const isActive =
        c.is_active !== undefined
          ? !!Number(c.is_active)
          : c.is_enabled !== undefined
            ? !!Number(c.is_enabled)
            : true;
      acc.total += 1;
      if (isActive) acc.active += 1;
      else acc.inactive += 1;
      return acc;
    },
    { total: 0, active: 0, inactive: 0 }
  );

  return (
    <div className="bp-content">
      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Categories</div>
          <div className="bp-muted">Group services for easier discovery.</div>
        </div>
        <div className="bp-head-actions">
          <a className="bp-primary-btn" href="admin.php?page=bp_categories_edit">+ New Category</a>
        </div>
      </div>

      <div className="bp-cards" style={{ marginBottom: 14 }}>
        <div className="bp-card">
          <div className="bp-card-label">Total</div>
          <div className="bp-card-value">{loading ? "…" : stats.total}</div>
        </div>
        <div className="bp-card">
          <div className="bp-card-label">Active</div>
          <div className="bp-card-value">{loading ? "…" : stats.active}</div>
        </div>
        <div className="bp-card">
          <div className="bp-card-label">Inactive</div>
          <div className="bp-card-value">{loading ? "…" : stats.inactive}</div>
        </div>
      </div>

      <div className="bp-card" style={{ marginBottom: 20 }}>
        <input
          type="text"
          placeholder="Search categories..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="bp-input"
          style={{ width: "100%" }}
        />
      </div>

      {loading ? (
        <div className="bp-card">Loading...</div>
      ) : filtered.length === 0 ? (
        <div className="bp-card">No categories found.</div>
      ) : (
        <div className="bp-entity-grid">
          {filtered.map((c) => {
            const name = c.name || c.title || `#${c.id}`;
            const imageUrl = c.image_url || c.image || "";
            const initial = (name || "?").trim().slice(0, 1).toUpperCase();
            const count = c.services_count ?? c.service_count ?? 0;
            const isActive =
              c.is_active !== undefined
                ? !!Number(c.is_active)
                : c.is_enabled !== undefined
                  ? !!Number(c.is_enabled)
                  : true;

            return (
              <div key={c.id} className="bp-entity-card">
                <div className="bp-entity-head">
                  <div className="bp-entity-thumb">
                    {imageUrl ? <img src={imageUrl} alt={name} /> : <div className="bp-entity-initial">{initial}</div>}
                  </div>
                  <div>
                    <div className="bp-entity-title">{name}</div>
                    <div className="bp-entity-sub">Services: {count}</div>
                  </div>
                  <span className={`bp-status-pill ${isActive ? "active" : "inactive"}`}>
                    {isActive ? "Active" : "Inactive"}
                  </span>
                </div>
                <div className="bp-entity-actions">
                  <a className="bp-btn-sm" href={`admin.php?page=bp_categories_edit&id=${c.id}`}>Edit</a>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
