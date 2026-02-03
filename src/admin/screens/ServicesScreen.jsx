import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";

export default function ServicesScreen() {
  const [services, setServices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const defaultCurrency = (window.BP_ADMIN?.currency || "USD").toUpperCase();

  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [sortBy, setSortBy] = useState("name");
  const [filtersOpen, setFiltersOpen] = useState(false);

  useEffect(() => {
    loadServices();
  }, []);

  async function loadServices() {
    try {
      setError("");
      setLoading(true);
      const resp = await bpFetch("/admin/services");
      setServices(resp?.data || []);
    } catch (e) {
      console.error(e);
      setError(e?.message || "Failed to load services");
    } finally {
      setLoading(false);
    }
  }

  const filtered = useMemo(() => {
    return services
      .filter((s) => {
        const hay = `${s.name || ""} ${s.title || ""} ${s.description || ""}`.toLowerCase();
        return hay.includes(search.toLowerCase());
      })
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
  }, [services, search, status, sortBy]);

  const stats = useMemo(() => {
    return services.reduce(
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
  }, [services]);

  return (
    <div className="myplugin-page bp-services">
      <main className="myplugin-content">
        <div className="bp-page-head">
          <div>
            <div className="bp-h1">Services</div>
            <div className="bp-muted">Manage services, pricing, and availability.</div>
          </div>
          <div className="bp-head-actions">
            <a className="bp-primary-btn" href="admin.php?page=bp_services_edit">
              + New Service
            </a>
          </div>
        </div>

        {error ? <div className="bp-error">{error}</div> : null}

        <div className="bp-cards" style={{ marginBottom: 14 }}>
          <div className="bp-card">
            <div className="bp-card-label">Total</div>
            <div className="bp-card-value">{loading ? "..." : stats.total}</div>
          </div>
          <div className="bp-card">
            <div className="bp-card-label">Active</div>
            <div className="bp-card-value">{loading ? "..." : stats.active}</div>
          </div>
          <div className="bp-card">
            <div className="bp-card-label">Inactive</div>
            <div className="bp-card-value">{loading ? "..." : stats.inactive}</div>
          </div>
        </div>

        {/* Desktop/tablet filters */}
        <div className="bp-card bp-services__filters-inline" style={{ marginBottom: 16 }}>
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

        {/* Mobile toolbar (filters in sheet) */}
        <div className="bp-services__toolbar">
          <button className="bp-top-btn" type="button" onClick={() => setFiltersOpen(true)}>
            Filters
          </button>
          <div className="bp-muted" style={{ fontWeight: 800 }}>
            {loading ? "..." : `${filtered.length} shown`}
          </div>
        </div>

        {loading ? (
          <div className="bp-card">Loading...</div>
        ) : filtered.length === 0 ? (
          <div className="bp-card">No services found.</div>
        ) : (
          <div className="bp-entity-grid bp-services__grid">
            {filtered.map((s) => {
              const name = s.name || s.title || `#${s.id}`;
              const description = (s.description || "").trim();
              const duration = s.duration_minutes ?? s.duration ?? s.duration_min ?? 0;
              const priceCents =
                s.price_cents ??
                (s.price !== undefined && s.price !== null ? Math.round(parseFloat(s.price) * 100) : null);
              const currency = (s.currency || defaultCurrency || "USD").toUpperCase();
              const priceDisplay = priceCents !== null ? `${currency} ${(priceCents / 100).toFixed(2)}` : "???";
              const isActive =
                s.is_active !== undefined ? !!Number(s.is_active) : s.is_enabled !== undefined ? !!Number(s.is_enabled) : true;
              const imageUrl = s.image_url || s.image || "";
              const initial = (name || "S").trim().charAt(0).toUpperCase();
              const typeDisplay = duration ? `${duration} min` : "???";
              const editHref = `admin.php?page=bp_services_edit&id=${s.id}`;

              return (
                <a key={s.id} className="bp-entity-card bp-entity-card--link" href={editHref}>
                  <div className="bp-entity-head">
                    <div className="bp-entity-thumb">
                      {imageUrl ? <img src={imageUrl} alt={name} /> : <div className="bp-entity-initial">{initial}</div>}
                    </div>
                    <div className="bp-entity-main">
                      <div className="bp-entity-title">{name}</div>
                      <div className="bp-entity-sub">{description || typeDisplay}</div>
                    </div>
                    <span className={`bp-status-pill ${isActive ? "active" : "inactive"}`}>
                      {isActive ? "Active" : "Inactive"}
                    </span>
                  </div>
                  <div className="bp-entity-meta">
                    <div>
                      <div className="bp-entity-meta-label">Duration</div>
                      <div className="bp-entity-meta-value">{typeDisplay}</div>
                    </div>
                    <div>
                      <div className="bp-entity-meta-label">Price</div>
                      <div className="bp-entity-meta-value">{priceDisplay}</div>
                    </div>
                  </div>
                  <div className="bp-entity-actions">
                    <span className="bp-btn-sm">Edit</span>
                  </div>
                </a>
              );
            })}
          </div>
        )}

        {filtersOpen ? (
          <div
            className="bp-sheet"
            onMouseDown={(e) => {
              if (e.target.classList.contains("bp-sheet")) setFiltersOpen(false);
            }}
          >
            <div className="bp-sheet-card">
              <div className="bp-sheet-head">
                <div style={{ fontWeight: 1000 }}>Filters</div>
                <button className="bp-top-btn" type="button" onClick={() => setFiltersOpen(false)}>
                  Close
                </button>
              </div>
              <div className="bp-sheet-body">
                <div className="bp-sheet-grid2">
                  <div>
                    <label className="bp-filter-label">Search</label>
                    <input
                      className="bp-input"
                      placeholder="Search services..."
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                    />
                  </div>
                  <div>
                    <label className="bp-filter-label">Status</label>
                    <select className="bp-input" value={status} onChange={(e) => setStatus(e.target.value)}>
                      <option value="all">All</option>
                      <option value="active">Active</option>
                      <option value="inactive">Inactive</option>
                    </select>
                  </div>
                </div>
                <div className="bp-sheet-grid2" style={{ marginTop: 10 }}>
                  <div>
                    <label className="bp-filter-label">Sort</label>
                    <select className="bp-input" value={sortBy} onChange={(e) => setSortBy(e.target.value)}>
                      <option value="name">Name</option>
                      <option value="price">Price</option>
                      <option value="duration">Duration</option>
                    </select>
                  </div>
                  <div />
                </div>
                <div className="bp-sheet-actions">
                  <button
                    className="bp-btn"
                    type="button"
                    onClick={() => {
                      setSearch("");
                      setStatus("all");
                      setSortBy("name");
                    }}
                  >
                    Clear
                  </button>
                  <button className="bp-primary-btn" type="button" onClick={() => setFiltersOpen(false)}>
                    Apply
                  </button>
                </div>
              </div>
            </div>
          </div>
        ) : null}
      </main>
    </div>
  );
}
