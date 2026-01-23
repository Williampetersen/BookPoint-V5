import React, { useEffect, useState } from "react";

export default function ExtrasScreen() {
  const [extras, setExtras] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");

  useEffect(() => {
    loadExtras();
  }, []);

  async function loadExtras() {
    try {
      setLoading(true);
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/extras`, {
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce },
      });
      const json = await resp.json();
      setExtras(json.data || []);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  }

  const filtered = extras.filter((e) =>
    (e.name || "").toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="bp-container">
      <div className="bp-header">
        <h1>Service Extras</h1>
        <button className="bp-btn bp-btn-primary">+ New Extra</button>
      </div>

      <div className="bp-card" style={{ marginBottom: 20 }}>
        <input
          type="text"
          placeholder="Search extras..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="bp-input"
          style={{ width: "100%" }}
        />
      </div>

      {loading ? (
        <div className="bp-card">Loading...</div>
      ) : filtered.length === 0 ? (
        <div className="bp-card">No extras found.</div>
      ) : (
        <div className="bp-table-wrapper">
          <table className="bp-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Price</th>
                <th>Type</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((e) => {
                const name = e.name || e.title || `#${e.id}`;
                const priceCents =
                  e.price_cents ??
                  (e.price !== undefined && e.price !== null
                    ? Math.round(parseFloat(e.price) * 100)
                    : null);
                const priceDisplay =
                  priceCents !== null ? `$${(priceCents / 100).toFixed(2)}` : "-";
                const typeDisplay = e.type || (e.duration_min ? `Duration ${e.duration_min} min` : "-");
                const isActive =
                  e.is_active !== undefined
                    ? !!Number(e.is_active)
                    : e.is_enabled !== undefined
                      ? !!Number(e.is_enabled)
                      : true;

                return (
                  <tr key={e.id}>
                    <td>{name}</td>
                    <td>{priceDisplay}</td>
                    <td>{typeDisplay}</td>
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
