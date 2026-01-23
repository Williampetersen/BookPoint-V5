import React, { useEffect, useState } from "react";

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
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/categories`, {
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce },
      });
      const json = await resp.json();
      setCategories(json.data || []);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  }

  const filtered = categories.filter((c) =>
    (c.name || "").toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="bp-container">
      <div className="bp-header">
        <h1>Categories</h1>
        <a className="bp-btn bp-btn-primary" href="admin.php?page=bp_categories_edit">+ New Category</a>
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
        <div className="bp-card">
          <div className="bp-table">
            <div className="bp-tr bp-th">
              <div>Image</div>
              <div>Name</div>
              <div>Services</div>
              <div>Status</div>
              <div>Actions</div>
            </div>
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
                <div key={c.id} className="bp-tr">
                  <div>
                    {imageUrl ? (
                      <img src={imageUrl} alt={name} style={{ width: 44, height: 44, borderRadius: 8, objectFit: "cover" }} />
                    ) : (
                      <div style={{ width: 44, height: 44, borderRadius: 8, background: "var(--bp-bg)", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 12, fontWeight: 900, color: "var(--bp-muted)" }}>{initial}</div>
                    )}
                  </div>
                  <div style={{ fontWeight: 900 }}>{name}</div>
                  <div>{count}</div>
                  <div>
                    <span className={`bp-status-pill ${isActive ? "active" : "inactive"}`}>
                      {isActive ? "Active" : "Inactive"}
                    </span>
                  </div>
                  <div className="bp-row-actions">
                    <a className="bp-btn-sm" href={`admin.php?page=bp_categories_edit&id=${c.id}`}>Edit</a>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
