import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";

export default function CategoriesScreen() {
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [sortBy, setSortBy] = useState("name");
  const [filtersOpen, setFiltersOpen] = useState(false);

  useEffect(() => {
    loadCategories();
  }, []);

  async function loadCategories() {
    try {
      setError("");
      setLoading(true);
      const resp = await bpFetch("/admin/categories");
      setCategories(resp?.data || []);
    } catch (e) {
      console.error(e);
      setError(e?.message || "Failed to load categories");
    } finally {
      setLoading(false);
    }
  }

  const filtered = useMemo(() => {
    return (categories || [])
      .filter((c) => {
        const hay = `${c.name || ""} ${c.title || ""} ${c.description || ""}`.toLowerCase();
        return hay.includes(search.toLowerCase());
      })
      .filter((c) => {
        if (status === "all") return true;
        const isActive =
          c.is_active !== undefined
            ? !!Number(c.is_active)
            : c.is_enabled !== undefined
              ? !!Number(c.is_enabled)
              : true;
        return status === "active" ? isActive : !isActive;
      })
      .sort((a, b) => {
        if (sortBy === "services") {
          const ac = Number(a.services_count ?? a.service_count ?? 0) || 0;
          const bc = Number(b.services_count ?? b.service_count ?? 0) || 0;
          return bc - ac;
        }
        const an = (a.name || "").toLowerCase();
        const bn = (b.name || "").toLowerCase();
        return an.localeCompare(bn);
      });
  }, [categories, search, status, sortBy]);

  const stats = useMemo(() => {
    return (categories || []).reduce(
      (acc, c) => {
        const isActive =
          c.is_active !== undefined
            ? !!Number(c.is_active)
            : c.is_enabled !== undefined
              ? !!Number(c.is_enabled)
              : true;
        acc.total += 1;
        if (isActive) acc.active += 1;
        else acc.inactive += 1;
        return acc;
      },
      { total: 0, active: 0, inactive: 0 }
    );
  }, [categories]);

  return (
    <div className="myplugin-page bp-categories">
      <main className="myplugin-content">
        <div className="bp-page-head">
          <div>
            <div className="bp-h1">Categories</div>
            <div className="bp-muted">Group services for easier discovery.</div>
          </div>
          <div className="bp-head-actions">
            <a className="bp-primary-btn" href="admin.php?page=bp_categories_edit">
              + New Category
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
        <div className="bp-card bp-categories__filters-inline" style={{ marginBottom: 16 }}>
          <div className="bp-filters">
            <input
              type="text"
              placeholder="Search categories..."
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
              <option value="services">Sort: Services</option>
            </select>
          </div>
        </div>

        {/* Mobile toolbar (filters in sheet) */}
        <div className="bp-categories__toolbar">
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
          <div className="bp-card">No categories found.</div>
        ) : (
          <div className="bp-entity-grid bp-categories__grid">
            {filtered.map((c) => {
              const name = c.name || c.title || `#${c.id}`;
              const imageUrl = c.image_url || c.image || "";
              const initial = (name || "?").trim().slice(0, 1).toUpperCase();
              const count = Number(c.services_count ?? c.service_count ?? 0) || 0;
              const isActive =
                c.is_active !== undefined
                  ? !!Number(c.is_active)
                  : c.is_enabled !== undefined
                    ? !!Number(c.is_enabled)
                    : true;
              const editHref = `admin.php?page=bp_categories_edit&id=${c.id}`;

              return (
                <a key={c.id} className="bp-entity-card bp-entity-card--link" href={editHref}>
                  <div className="bp-entity-head">
                    <div className="bp-entity-thumb">
                      {imageUrl ? <img src={imageUrl} alt={name} /> : <div className="bp-entity-initial">{initial}</div>}
                    </div>
                    <div className="bp-entity-main">
                      <div className="bp-entity-title">{name}</div>
                      <div className="bp-entity-sub">Services: {count}</div>
                    </div>
                    <span className={`bp-status-pill ${isActive ? "active" : "inactive"}`}>
                      {isActive ? "Active" : "Inactive"}
                    </span>
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
                      placeholder="Search categories..."
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
                      <option value="services">Services</option>
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

