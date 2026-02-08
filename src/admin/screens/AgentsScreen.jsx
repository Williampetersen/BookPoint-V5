import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";

export default function AgentsScreen() {
  const [agents, setAgents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all"); // all | active | inactive
  const [sort, setSort] = useState("name"); // name | newest
  const [filtersOpen, setFiltersOpen] = useState(false);

  useEffect(() => {
    let alive = true;
    (async () => {
      try {
        setLoading(true);
        setError("");
        const resp = await bpFetch("/admin/agents-full");
        if (!alive) return;
        setAgents(resp?.data || []);
      } catch (e) {
        console.error(e);
        if (!alive) return;
        setAgents([]);
        setError(e?.message || "Failed to load agents");
      } finally {
        if (alive) setLoading(false);
      }
    })();
    return () => {
      alive = false;
    };
  }, []);

  const derived = useMemo(() => {
    const q = search.trim().toLowerCase();
    const list = Array.isArray(agents) ? agents : [];

    const mapped = list.map((a) => {
      const fullName = `${a.first_name || ""} ${a.last_name || ""}`.trim();
      const name = (fullName || a.name || "").trim() || `#${a.id}`;
      const isActive =
        a.is_active !== undefined
          ? !!Number(a.is_active)
          : a.is_enabled !== undefined
            ? !!Number(a.is_enabled)
            : true;
      const services = Number(a.services_count ?? a.service_count ?? 0) || 0;
      const email = a.email || "";
      const phone = a.phone || "";
      const image_url = a.image_url || "";

      return { id: Number(a.id) || 0, name, isActive, services, email, phone, image_url };
    });

    const counts = mapped.reduce(
      (acc, a) => {
        acc.total += 1;
        if (a.isActive) acc.active += 1;
        else acc.inactive += 1;
        return acc;
      },
      { total: 0, active: 0, inactive: 0 }
    );

    let items = mapped;

    if (q) {
      items = items.filter((a) => `${a.name} ${a.email} ${a.phone}`.toLowerCase().includes(q));
    }
    if (status !== "all") {
      items = items.filter((a) => (status === "active" ? a.isActive : !a.isActive));
    }
    items = [...items].sort((a, b) => {
      if (sort === "newest") return (b.id || 0) - (a.id || 0);
      return (a.name || "").toLowerCase().localeCompare((b.name || "").toLowerCase());
    });

    return { counts, items };
  }, [agents, search, status, sort]);

  return (
    <div className="myplugin-page bp-agents">
      <main className="myplugin-content">
      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Agents</div>
          <div className="bp-muted">Manage team members, photos, and assignments.</div>
        </div>
        <div className="bp-head-actions">
          <a className="bp-primary-btn" href="admin.php?page=bp_agents_edit">
            + New Agent
          </a>
        </div>
      </div>

      {error ? (
        <div className="bp-error" style={{ marginBottom: 14 }}>
          {error}
        </div>
      ) : null}

      <div className="bp-cards" style={{ marginBottom: 14 }}>
        <div className="bp-card">
          <div className="bp-card-label">Total</div>
          <div className="bp-card-value">{loading ? "…" : derived.counts.total}</div>
        </div>
        <div className="bp-card">
          <div className="bp-card-label">Active</div>
          <div className="bp-card-value">{loading ? "…" : derived.counts.active}</div>
        </div>
        <div className="bp-card">
          <div className="bp-card-label">Inactive</div>
          <div className="bp-card-value">{loading ? "…" : derived.counts.inactive}</div>
        </div>
      </div>

      <div className="bp-agents__filters-inline bp-card" style={{ marginBottom: 16 }}>
        <div className="bp-filters">
          <input
            type="text"
            placeholder="Search agents..."
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
          <select className="bp-input" value={sort} onChange={(e) => setSort(e.target.value)}>
            <option value="name">Sort: Name</option>
            <option value="newest">Sort: Newest</option>
          </select>
        </div>
      </div>

      <div className="bp-agents__toolbar">
        <button className="bp-top-btn" type="button" onClick={() => setFiltersOpen(true)}>
          Filters
        </button>
      </div>

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
                    placeholder="Search agents..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                  />
                </div>
                <div>
                  <label className="bp-filter-label">Status</label>
                  <select className="bp-input" value={status} onChange={(e) => setStatus(e.target.value)}>
                    <option value="all">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
              </div>
              <div className="bp-sheet-grid2" style={{ marginTop: 10 }}>
                <div>
                  <label className="bp-filter-label">Sort</label>
                  <select className="bp-input" value={sort} onChange={(e) => setSort(e.target.value)}>
                    <option value="name">Name</option>
                    <option value="newest">Newest</option>
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
                    setSort("name");
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

      {loading ? (
        <div className="bp-card">Loading...</div>
      ) : derived.items.length === 0 ? (
        <div className="bp-card">No agents found.</div>
      ) : (
        <div className="bp-entity-grid">
          {derived.items.map((a) => {
            const initials = (a.name || "A")
              .split(" ")
              .map((p) => p[0])
              .slice(0, 2)
              .join("")
              .toUpperCase();

            return (
              <div className="bp-entity-card" key={a.id}>
                <div className="bp-entity-head">
                  <div className="bp-entity-thumb">
                    {a.image_url ? <img src={a.image_url} alt={a.name} /> : <div className="bp-entity-initial">{initials}</div>}
                  </div>
                  <div>
                    <div className="bp-entity-title">{a.name}</div>
                    <div className="bp-entity-sub">
                      {a.email || "—"}
                      {a.phone ? ` • ${a.phone}` : ""}
                    </div>
                  </div>
                  <span className={`bp-status-pill ${a.isActive ? "active" : "inactive"}`}>{a.isActive ? "Active" : "Inactive"}</span>
                </div>

                <div className="bp-entity-meta">
                  <div>
                    <div className="bp-entity-meta-label">Services</div>
                    <div className="bp-entity-meta-value">{a.services}</div>
                  </div>
                  <div>
                    <div className="bp-entity-meta-label">ID</div>
                    <div className="bp-entity-meta-value">#{a.id}</div>
                  </div>
                </div>

                <div className="bp-entity-actions">
                  <a className="bp-btn-sm" href={`admin.php?page=bp_agents_edit&id=${a.id}`}>
                    Edit
                  </a>
                </div>
              </div>
            );
          })}
        </div>
      )}
      </main>
    </div>
  );
}
