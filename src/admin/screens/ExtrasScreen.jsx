import React, { useEffect, useState } from "react";
import { bpFetch } from "../api/client";

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
      const resp = await bpFetch("/admin/extras");
      setExtras(resp?.data || []);
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
    <div className="bp-content">
      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Service Extras</div>
          <div className="bp-muted">Upsell add-ons and extras for services.</div>
        </div>
        <div className="bp-head-actions">
          <a className="bp-primary-btn" href="admin.php?page=bp_extras_edit">+ New Extra</a>
        </div>
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
        <div className="bp-entity-grid">
          {filtered.map((e) => {
            const name = e.name || e.title || `#${e.id}`;
            const imageUrl = e.image_url || e.image || "";
            const initial = (name || "E").trim().slice(0, 1).toUpperCase();
            const priceCents =
              e.price_cents ??
              (e.price !== undefined && e.price !== null
                ? Math.round(parseFloat(e.price) * 100)
                : null);
            const priceDisplay =
              priceCents !== null ? `$${(priceCents / 100).toFixed(2)}` : "—";
            const typeDisplay = e.type || (e.duration_min ? `Duration ${e.duration_min} min` : "—");
            const isActive =
              e.is_active !== undefined
                ? !!Number(e.is_active)
                : e.is_enabled !== undefined
                  ? !!Number(e.is_enabled)
                  : true;

            return (
              <div key={e.id} className="bp-entity-card">
                <div className="bp-entity-head">
                  <div className="bp-entity-thumb">
                    {imageUrl ? <img src={imageUrl} alt={name} /> : <div className="bp-entity-initial">{initial}</div>}
                  </div>
                  <div>
                    <div className="bp-entity-title">{name}</div>
                    <div className="bp-entity-sub">{typeDisplay}</div>
                  </div>
                  <span className={`bp-status-pill ${isActive ? "active" : "inactive"}`}>
                    {isActive ? "Active" : "Inactive"}
                  </span>
                </div>
                <div className="bp-entity-meta">
                  <div>
                    <div className="bp-entity-meta-label">Price</div>
                    <div className="bp-entity-meta-value">{priceDisplay}</div>
                  </div>
                </div>
                <div className="bp-entity-actions">
                  <a className="bp-btn-sm" href={`admin.php?page=bp_extras_edit&id=${e.id}`}>Edit</a>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
