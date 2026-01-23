import React, { useEffect, useState } from "react";

export default function ServicesScreen() {
  const [services, setServices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [sortBy, setSortBy] = useState("name");
  const [editingId, setEditingId] = useState(null);
  const [editData, setEditData] = useState(null);
  const [editLoading, setEditLoading] = useState(false);
  const [editSaving, setEditSaving] = useState(false);

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

  async function openEdit(serviceId) {
    setEditingId(serviceId);
    setEditLoading(true);
    try {
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/services/${serviceId}`, {
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce },
      });
      const json = await resp.json();
      setEditData(json.data || {});
    } catch (e) {
      console.error(e);
      setEditData(null);
    } finally {
      setEditLoading(false);
    }
  }

  async function saveEdit() {
    if (!editData || !editingId) return;
    setEditSaving(true);
    try {
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/services/${editingId}`, {
        method: "PATCH",
        headers: { 
          "X-WP-Nonce": window.BP_ADMIN?.nonce,
          "Content-Type": "application/json"
        },
        body: JSON.stringify(editData),
      });
      const json = await resp.json();
      if (json.status === "success") {
        setEditingId(null);
        setEditData(null);
        await loadServices();
      }
    } catch (e) {
      console.error(e);
      alert("Save failed: " + e.message);
    } finally {
      setEditSaving(false);
    }
  }

  function closeEdit() {
    setEditingId(null);
    setEditData(null);
    setEditLoading(false);
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
        <div className="bp-card">
          <div className="bp-table">
            <div className="bp-tr bp-th">
              <div>Image</div>
              <div>Name</div>
              <div>Duration</div>
              <div>Price</div>
              <div>Status</div>
              <div>Actions</div>
            </div>
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

              return (
                <div key={s.id} className="bp-tr">
                  <div>
                    {imageUrl ? (
                      <img src={imageUrl} alt={name} style={{ width: 44, height: 44, borderRadius: 8, objectFit: "cover" }} />
                    ) : (
                      <div style={{ width: 44, height: 44, borderRadius: 8, background: "var(--bp-bg)", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 12, fontWeight: 900, color: "var(--bp-muted)" }}>—</div>
                    )}
                  </div>
                  <div style={{ fontWeight: 900 }}>{name}</div>
                  <div>{duration ? `${duration} min` : "-"}</div>
                  <div>{priceDisplay}</div>
                  <div>
                    <span className={`bp-status-pill ${isActive ? "active" : "inactive"}`}>
                      {isActive ? "Active" : "Inactive"}
                    </span>
                  </div>
                  <div className="bp-row-actions">
                    <button
                      className="bp-btn-sm"
                      onClick={() => openEdit(s.id)}
                    >
                      Edit
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {editingId && (
        <div className="bp-drawer-wrap" onClick={(e) => e.target.classList.contains("bp-drawer-wrap") && closeEdit()}>
          <div className="bp-drawer">
            <div className="bp-drawer-head">
              <div>
                <div className="bp-drawer-title">Edit Service</div>
              </div>
              <button className="bp-top-btn" onClick={closeEdit}>Close</button>
            </div>

            {editLoading ? (
              <div className="bp-drawer-body" style={{ textAlign: "center", padding: 20 }}>Loading...</div>
            ) : !editData ? (
              <div className="bp-drawer-body" style={{ textAlign: "center", padding: 20 }}>Failed to load service</div>
            ) : (
              <>
                <div className="bp-drawer-body">
                  <div style={{ marginBottom: 16 }}>
                    <label style={{ fontWeight: 900, display: "block", marginBottom: 8 }}>Name</label>
                    <input
                      type="text"
                      value={editData.name || ""}
                      onChange={(e) => setEditData({ ...editData, name: e.target.value })}
                      className="bp-input"
                      style={{ width: "100%" }}
                    />
                  </div>

                  <div style={{ marginBottom: 16 }}>
                    <label style={{ fontWeight: 900, display: "block", marginBottom: 8 }}>Description</label>
                    <textarea
                      value={editData.description || ""}
                      onChange={(e) => setEditData({ ...editData, description: e.target.value })}
                      className="bp-input"
                      style={{ width: "100%", minHeight: 80, fontFamily: "inherit" }}
                    />
                  </div>

                  <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, marginBottom: 16 }}>
                    <div>
                      <label style={{ fontWeight: 900, display: "block", marginBottom: 8 }}>Price</label>
                      <input
                        type="number"
                        value={editData.price_cents ? (editData.price_cents / 100).toFixed(2) : ""}
                        onChange={(e) => setEditData({ ...editData, price_cents: Math.round(parseFloat(e.target.value || 0) * 100) })}
                        className="bp-input"
                        step="0.01"
                        min="0"
                      />
                    </div>
                    <div>
                      <label style={{ fontWeight: 900, display: "block", marginBottom: 8 }}>Duration (min)</label>
                      <input
                        type="number"
                        value={editData.duration_minutes || ""}
                        onChange={(e) => setEditData({ ...editData, duration_minutes: parseInt(e.target.value || 0, 10) })}
                        className="bp-input"
                        min="5"
                      />
                    </div>
                  </div>

                  <div style={{ marginBottom: 16 }}>
                    <label style={{ fontWeight: 900, display: "flex", gap: 8, alignItems: "center" }}>
                      <input
                        type="checkbox"
                        checked={Number(editData.is_active) === 1}
                        onChange={(e) => setEditData({ ...editData, is_active: e.target.checked ? 1 : 0 })}
                      />
                      Active
                    </label>
                  </div>
                </div>

                <div style={{ padding: 16, borderTop: "1px solid var(--bp-border)", display: "flex", gap: 10, justifyContent: "flex-end" }}>
                  <button onClick={closeEdit} className="bp-btn" disabled={editSaving}>Cancel</button>
                  <button onClick={saveEdit} className="bp-btn bp-btn-primary" disabled={editSaving}>
                    {editSaving ? "Saving…" : "Save"}
                  </button>
                </div>
              </>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
