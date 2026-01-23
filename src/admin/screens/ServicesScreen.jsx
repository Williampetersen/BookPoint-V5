import React, { useEffect, useState } from "react";

export default function ServicesScreen() {
  const [services, setServices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [sortBy, setSortBy] = useState("name");

  useEffect(() => {
    loadServices();
  }, []);

  async function loadServices() {
    try {
      setLoading(true);
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/services`, {
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce },
      });
      const json = await resp.json();
      setServices(json.data || []);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  }

  const filtered = services
    .filter((s) => (s.name || "").toLowerCase().includes(search.toLowerCase()))
    .filter((s) => {
      if (status === "all") return true;
      const isActive =
        s.is_active !== undefined
          ? !!Number(s.is_active)
          : s.is_enabled !== undefined
            ? !!Number(s.is_enabled)
            : true;
      return status === "active" ? isActive : !isActive;
    })
    .sort((a, b) => {
      if (sortBy === "price") {
        const ap = a.price_cents ?? (a.price ? Math.round(parseFloat(a.price) * 100) : 0);
        const bp = b.price_cents ?? (b.price ? Math.round(parseFloat(b.price) * 100) : 0);
        return ap - bp;
      }
      if (sortBy === "duration") {
        const ad = a.duration_minutes ?? a.duration ?? 0;
        const bd = b.duration_minutes ?? b.duration ?? 0;
        return ad - bd;
      }
      const an = (a.name || "").toLowerCase();
      const bn = (b.name || "").toLowerCase();
      return an.localeCompare(bn);
    });

  const stats = services.reduce(
    (acc, s) => {
      const isActive =
        s.is_active !== undefined
          ? !!Number(s.is_active)
          : s.is_enabled !== undefined
            ? !!Number(s.is_enabled)
            : true;
      acc.total += 1;
      if (isActive) acc.active += 1;
      else acc.inactive += 1;
      return acc;
    },
    { total: 0, active: 0, inactive: 0 }
  );

  return (
    <div className="bp-container">
      <div className="bp-header">
        <h1>Services</h1>
        <a
          className="bp-btn bp-btn-primary"
          href="admin.php?page=bp_services_edit"
        >
          + New Service
        </a>
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

      <div className="bp-card" style={{ marginBottom: 16 }}>
        <div className="bp-filters">
          <input
            type="text"
            placeholder="Search services..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="bp-input"
            style={{ flex: 1, minWidth: 240 }}
          />
          <select className="bp-input" value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="all">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
          <select className="bp-input" value={sortBy} onChange={(e) => setSortBy(e.target.value)}>
            <option value="name">Sort: Name</option>
            <option value="price">Sort: Price</option>
            <option value="duration">Sort: Duration</option>
          </select>
        </div>
      </div>

      {loading ? (
        <div className="bp-card">Loading...</div>
      ) : filtered.length === 0 ? (
        <div className="bp-card">No services found.</div>
      ) : (
        <div className="bp-service-grid">
          {filtered.map((s) => {
            const name = s.name || s.title || `#${s.id}`;
            const duration = s.duration_minutes ?? s.duration ?? s.duration_min ?? s.duration_minutes;
            const priceCents =
              s.price_cents ??
              (s.price !== undefined && s.price !== null
                ? Math.round(parseFloat(s.price) * 100)
                : null);
            const priceDisplay =
              priceCents !== null ? `$${(priceCents / 100).toFixed(2)}` : "-";
            const isActive =
              s.is_active !== undefined
                ? !!Number(s.is_active)
                : s.is_enabled !== undefined
                  ? !!Number(s.is_enabled)
                  : true;
            const imageUrl = s.image_url || s.image || "";
            const initials = (name || "?").trim().slice(0, 1).toUpperCase();

            return (
              <div key={s.id} className="bp-service-card">
                <div className="bp-service-media">
                  {imageUrl ? (
                    <img src={imageUrl} alt={name} />
                  ) : (
                    <div className="bp-service-placeholder">{initials}</div>
                  )}
                </div>
                <div className="bp-service-body">
                  <div className="bp-service-head">
                    <div className="bp-service-name">{name}</div>
                    <span className={`bp-status-pill ${isActive ? "active" : "inactive"}`}>
                      {isActive ? "Active" : "Inactive"}
                    </span>
                  </div>

                  <div className="bp-service-meta">
                    <div>
                      <div className="bp-muted" style={{ fontSize: 12 }}>Price</div>
                      <div className="bp-service-value">{priceDisplay}</div>
                    </div>
                    <div>
                      <div className="bp-muted" style={{ fontSize: 12 }}>Duration</div>
                      <div className="bp-service-value">{duration ? `${duration} min` : "-"}</div>
                    </div>
                  </div>

                  <div className="bp-service-actions">
                    <a
                      className="bp-btn-sm"
                      href={`admin.php?page=bp_services_edit&id=${s.id}`}
                    >
                      Edit
                    </a>
                    <button className="bp-btn-sm bp-btn-danger">Delete</button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
