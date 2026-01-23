import React, { useEffect, useState } from "react";

export default function PromoCodesScreen() {
  const [promoCodes, setPromoCodes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");

  useEffect(() => {
    loadPromoCodes();
  }, []);

  async function loadPromoCodes() {
    try {
      setLoading(true);
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/promo-codes`, {
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce },
      });
      const json = await resp.json();
      setPromoCodes(json.data || []);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  }

  const filtered = promoCodes.filter((p) =>
    (p.code || "").toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="bp-container">
      <div className="bp-header">
        <h1>Promo Codes</h1>
        <button className="bp-btn bp-btn-primary">+ New Promo Code</button>
      </div>

      <div className="bp-card" style={{ marginBottom: 20 }}>
        <input
          type="text"
          placeholder="Search promo codes..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="bp-input"
          style={{ width: "100%" }}
        />
      </div>

      {loading ? (
        <div className="bp-card">Loading...</div>
      ) : filtered.length === 0 ? (
        <div className="bp-card">No promo codes found.</div>
      ) : (
        <div className="bp-table-wrapper">
          <table className="bp-table">
            <thead>
              <tr>
                <th>Code</th>
                <th>Discount</th>
                <th>Uses</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((p) => {
                const code = p.code || p.name || `#${p.id}`;
                const type = p.type || p.discount_type || "percent";
                const amount = p.amount ?? p.discount_value ?? 0;
                const discountDisplay =
                  type === "percent" || type === "percentage"
                    ? `${amount}%`
                    : `$${(parseFloat(amount) || 0).toFixed(2)}`;
                const uses = p.uses_count ?? p.usage_count ?? 0;
                const maxUses = p.max_uses ?? p.usage_limit ?? "∞";
                const isActive =
                  p.is_active !== undefined
                    ? !!Number(p.is_active)
                    : p.is_enabled !== undefined
                      ? !!Number(p.is_enabled)
                      : true;

                return (
                  <tr key={p.id}>
                    <td>{code}</td>
                    <td>{discountDisplay}</td>
                    <td>{uses} / {maxUses}</td>
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
