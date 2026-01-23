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
        <button className="bp-btn bp-btn-primary">+ New Category</button>
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
        <div className="bp-table-wrapper">
          <table className="bp-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Services Count</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((c) => {
                const name = c.name || c.title || `#${c.id}`;
                const count = c.services_count ?? c.service_count ?? 0;
                const isActive =
                  c.is_active !== undefined
                    ? !!Number(c.is_active)
                    : c.is_enabled !== undefined
                      ? !!Number(c.is_enabled)
                      : true;

                return (
                  <tr key={c.id}>
                    <td>{name}</td>
                    <td>{count}</td>
                    <td>{isActive ? "Active" : "Inactive"}</td>
                  <td>
                    <button className="bp-btn-sm">Edit</button>
                    <button className="bp-btn-sm bp-btn-danger">Delete</button>
                  </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
