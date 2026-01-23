import React, { useEffect, useState } from "react";
import { bpFetch } from "../api/client";

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
      const resp = await bpFetch("/admin/promo-codes");
      setPromoCodes(resp?.data || []);
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
    <div className="bp-content">
      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Promo Codes</div>
          <div className="bp-muted">Create discounts and track usage.</div>
        </div>
        <div className="bp-head-actions">
          <a className="bp-primary-btn" href="admin.php?page=bp_promo_codes_edit">+ New Promo Code</a>
        </div>
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
        <div className="bp-entity-grid">
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
              <div key={p.id} className="bp-entity-card">
                <div className="bp-entity-head">
                  <div>
                    <div className="bp-entity-title">{code}</div>
                    <div className="bp-entity-sub">Discount: {discountDisplay}</div>
                  </div>
                  <span className={`bp-status-pill ${isActive ? "active" : "inactive"}`}>
                    {isActive ? "Active" : "Inactive"}
                  </span>
                </div>
                <div className="bp-entity-meta">
                  <div>
                    <div className="bp-entity-meta-label">Usage</div>
                    <div className="bp-entity-meta-value">{uses} / {maxUses}</div>
                  </div>
                </div>
                <div className="bp-entity-actions">
                  <a className="bp-btn-sm" href={`admin.php?page=bp_promo_codes_edit&id=${p.id}`}>Edit</a>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
