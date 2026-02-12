import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";
import UpgradeToPro from "../components/UpgradeToPro";

export default function LocationsScreen() {
  const isPro = Boolean(Number(window.BP_ADMIN?.isPro || 0));
  const [locations, setLocations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [sortBy, setSortBy] = useState("name");
  const [filtersOpen, setFiltersOpen] = useState(false);

  useEffect(() => {
    if (!isPro) {
      setLoading(false);
      setLocations([]);
      return;
    }
    loadLocations();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isPro]);

  async function loadLocations() {
    try {
      setError("");
      setLoading(true);
      const resp = await bpFetch("/admin/locations");
      setLocations(resp?.data || []);
    } catch (e) {
      console.error(e);
      setError(e?.message || "Failed to load locations");
    } finally {
      setLoading(false);
    }
  }

  const filtered = useMemo(() => {
    return locations
      .filter((l) => {
        const hay = `${l.name || ""} ${l.address || ""} ${l.category_name || ""}`.toLowerCase();
        return hay.includes(search.toLowerCase());
      })
      .filter((l) => {
        if (status === "all") return true;
        const isActive =
          l.status !== undefined
            ? String(l.status) === "active"
            : l.is_active !== undefined
              ? !!Number(l.is_active)
              : true;
        return status === "active" ? isActive : !isActive;
      })
      .sort((a, b) => {
        if (sortBy === "id") return (Number(b.id) || 0) - (Number(a.id) || 0);
        if (sortBy === "category") {
          const ac = (a.category_name || "").toLowerCase();
          const bc = (b.category_name || "").toLowerCase();
          return ac.localeCompare(bc);
        }
        const an = (a.name || "").toLowerCase();
        const bn = (b.name || "").toLowerCase();
        return an.localeCompare(bn);
      });
  }, [locations, search, status, sortBy]);

  const stats = useMemo(() => {
    return locations.reduce(
      (acc, l) => {
        const isActive =
          l.status !== undefined
            ? String(l.status) === "active"
            : l.is_active !== undefined
              ? !!Number(l.is_active)
              : true;
        acc.total += 1;
        if (isActive) acc.active += 1;
        else acc.inactive += 1;
        return acc;
      },
      { total: 0, active: 0, inactive: 0 }
    );
  }, [locations]);

  if (!isPro) {
    return <UpgradeToPro feature="Locations" />;
  }

  return (
    <div className="myplugin-page bp-locations">
      <main className="myplugin-content">
        <div className="bp-page-head">
          <div>
            <div className="bp-h1">Locations</div>
            <div className="bp-muted">Manage locations, images, and assignments.</div>
          </div>
          <div className="bp-head-actions">
            <a className="bp-primary-btn" href="admin.php?page=bp_locations_edit">
              + New Location
            </a>
            <a className="bp-top-btn" href="admin.php?page=bp_location_categories_edit">
              Categories
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
        <div className="bp-card bp-locations__filters-inline" style={{ marginBottom: 16 }}>
          <div className="bp-filters">
            <input
              type="text"
              placeholder="Search locations..."
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
              <option value="category">Sort: Category</option>
              <option value="id">Sort: Newest</option>
            </select>
          </div>
        </div>

        {/* Mobile toolbar */}
        <div className="bp-locations__toolbar">
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
          <div className="bp-card">No locations found.</div>
        ) : (
          <div className="bp-entity-grid bp-locations__grid">
            {filtered.map((l) => {
              const name = l.name || `#${l.id}`;
              const address = (l.address || "").trim();
              const category = (l.category_name || "").trim();
              const imageUrl = l.image_url || l.image || "";
              const initial = (name || "L").trim().charAt(0).toUpperCase();
              const isActive =
                l.status !== undefined ? String(l.status) === "active" : l.is_active !== undefined ? !!Number(l.is_active) : true;
              const editHref = `admin.php?page=bp_locations_edit&id=${l.id}`;

              return (
                <a key={l.id} className="bp-entity-card bp-entity-card--link" href={editHref}>
                  <div className="bp-entity-head">
                    <div className="bp-entity-thumb">
                      {imageUrl ? <img src={imageUrl} alt={name} /> : <div className="bp-entity-initial">{initial}</div>}
                    </div>
                    <div className="bp-entity-main">
                      <div className="bp-entity-title">{name}</div>
                      <div className="bp-entity-sub">{address || category || "Location"}</div>
                    </div>
                    <span className={`bp-status-pill ${isActive ? "active" : "inactive"}`}>
                      {isActive ? "Active" : "Inactive"}
                    </span>
                  </div>
                  <div className="bp-entity-meta">
                    {category ? (
                      <div>
                        <div className="bp-entity-meta-label">Category</div>
                        <div className="bp-entity-meta-value">{category}</div>
                      </div>
                    ) : (
                      <div>
                        <div className="bp-entity-meta-label">Category</div>
                        <div className="bp-entity-meta-value">—</div>
                      </div>
                    )}
                    <div>
                      <div className="bp-entity-meta-label">ID</div>
                      <div className="bp-entity-meta-value">#{l.id}</div>
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
                      placeholder="Search locations..."
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
                      <option value="category">Category</option>
                      <option value="id">Newest</option>
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
