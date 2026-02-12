import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";
import UpgradeToPro from "../components/UpgradeToPro";

export default function ExtrasScreen() {
  const isPro = Boolean(Number(window.BP_ADMIN?.isPro || 0));
  const [extras, setExtras] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const defaultCurrency = (window.BP_ADMIN?.currency || "USD").toUpperCase();

  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [sortBy, setSortBy] = useState("name");
  const [filtersOpen, setFiltersOpen] = useState(false);

  useEffect(() => {
    if (!isPro) {
      setLoading(false);
      setExtras([]);
      return;
    }
    loadExtras();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isPro]);

  async function loadExtras() {
    try {
      setError("");
      setLoading(true);
      const resp = await bpFetch("/admin/extras");
      setExtras(resp?.data || []);
    } catch (e) {
      console.error(e);
      setError(e?.message || "Failed to load extras");
    } finally {
      setLoading(false);
    }
  }

  const filtered = useMemo(() => {
    return extras
      .filter((x) => {
        const hay = `${x.name || ""} ${x.title || ""} ${x.description || ""} ${x.type || ""}`.toLowerCase();
        return hay.includes(search.toLowerCase());
      })
      .filter((x) => {
        if (status === "all") return true;
        const isActive =
          x.is_active !== undefined ? !!Number(x.is_active) : x.is_enabled !== undefined ? !!Number(x.is_enabled) : true;
        return status === "active" ? isActive : !isActive;
      })
      .sort((a, b) => {
        if (sortBy === "price") {
          const ap = a.price_cents ?? (a.price !== undefined && a.price !== null ? Math.round(parseFloat(a.price) * 100) : 0);
          const bp = b.price_cents ?? (b.price !== undefined && b.price !== null ? Math.round(parseFloat(b.price) * 100) : 0);
          return ap - bp;
        }
        if (sortBy === "sort") {
          const as = Number(a.sort_order || 0) || 0;
          const bs = Number(b.sort_order || 0) || 0;
          return as - bs;
        }
        const an = (a.name || "").toLowerCase();
        const bn = (b.name || "").toLowerCase();
        return an.localeCompare(bn);
      });
  }, [extras, search, status, sortBy]);

  const stats = useMemo(() => {
    return extras.reduce(
      (acc, x) => {
        const isActive =
          x.is_active !== undefined ? !!Number(x.is_active) : x.is_enabled !== undefined ? !!Number(x.is_enabled) : true;
        acc.total += 1;
        if (isActive) acc.active += 1;
        else acc.inactive += 1;
        return acc;
      },
      { total: 0, active: 0, inactive: 0 }
    );
  }, [extras]);

  if (!isPro) {
    return <UpgradeToPro feature="Service Extras" />;
  }

  return (
    <div className="myplugin-page bp-extras">
      <main className="myplugin-content">
        <div className="bp-page-head">
          <div>
            <div className="bp-h1">Service Extras</div>
            <div className="bp-muted">Upsell add-ons and extras for services.</div>
          </div>
          <div className="bp-head-actions">
            <a className="bp-primary-btn" href="admin.php?page=bp_extras_edit">
              + New Extra
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
        <div className="bp-card bp-extras__filters-inline" style={{ marginBottom: 16 }}>
          <div className="bp-filters">
            <input
              type="text"
              placeholder="Search extras..."
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
              <option value="sort">Sort: Order</option>
            </select>
          </div>
        </div>

        {/* Mobile toolbar */}
        <div className="bp-extras__toolbar">
          <button className="bp-top-btn" type="button" onClick={() => setFiltersOpen(true)}>
            Filters
          </button>
          <div className="bp-muted" style={{ fontWeight: 900 }}>
            {loading ? "..." : `${filtered.length} shown`}
          </div>
        </div>

        {loading ? (
          <div className="bp-card">Loading...</div>
        ) : filtered.length === 0 ? (
          <div className="bp-card">No extras found.</div>
        ) : (
          <div className="bp-entity-grid bp-extras__grid">
            {filtered.map((x) => {
              const name = x.name || x.title || `#${x.id}`;
              const description = (x.description || "").trim();
              const isActive =
                x.is_active !== undefined ? !!Number(x.is_active) : x.is_enabled !== undefined ? !!Number(x.is_enabled) : true;
              const imageUrl = x.image_url || x.image || "";
              const initial = (name || "E").trim().charAt(0).toUpperCase();
              const priceCents =
                x.price_cents ??
                (x.price !== undefined && x.price !== null ? Math.round(parseFloat(x.price) * 100) : null);
              const currency = (x.currency || defaultCurrency || "USD").toUpperCase();
              const priceDisplay = priceCents !== null ? `${currency} ${(priceCents / 100).toFixed(2)}` : "???";
              const typeDisplay = x.type || (x.duration_min ? `Duration ${x.duration_min} min` : "");
              const editHref = `admin.php?page=bp_extras_edit&id=${x.id}`;

              return (
                <a key={x.id} className="bp-entity-card bp-entity-card--link" href={editHref}>
                  <div className="bp-entity-head">
                    <div className="bp-entity-thumb">
                      {imageUrl ? <img src={imageUrl} alt={name} /> : <div className="bp-entity-initial">{initial}</div>}
                    </div>
                    <div className="bp-entity-main">
                      <div className="bp-entity-title">{name}</div>
                      <div className="bp-entity-sub">{description || typeDisplay || "Extra"}</div>
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
                    {typeDisplay ? (
                      <div>
                        <div className="bp-entity-meta-label">Type</div>
                        <div className="bp-entity-meta-value">{typeDisplay}</div>
                      </div>
                    ) : null}
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
                      placeholder="Search extras..."
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
                      <option value="sort">Order</option>
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
