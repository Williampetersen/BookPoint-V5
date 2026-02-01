import React, { useEffect, useState } from "react";
import { bpFetch } from "../api/client";

export default function AgentsScreen() {
  const [agents, setAgents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");

  useEffect(() => {
    loadAgents();
  }, []);

  async function loadAgents() {
    try {
      setLoading(true);
      const resp = await bpFetch("/admin/agents-full");
      setAgents(resp?.data || []);
    } catch (e) {
      console.error(e);
      setAgents([]);
    } finally {
      setLoading(false);
    }
  }

  const filtered = agents.filter((a) => {
    const fullName = `${a.first_name || ""} ${a.last_name || ""}`.trim();
    const name = fullName || a.name || "";
    return name.toLowerCase().includes(search.toLowerCase());
  });

  return (
    <div className="bp-content">
      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Agents</div>
          <div className="bp-muted">Manage team members, photos, and assignments.</div>
        </div>
        <div className="bp-head-actions">
          <a className="bp-primary-btn" href="admin.php?page=bp_agents_edit">+ New Agent</a>
        </div>
      </div>

      <div className="bp-card" style={{ marginBottom: 14 }}>
        <input
          type="text"
          placeholder="Search agents..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="bp-input"
          style={{ width: "100%" }}
        />
      </div>

      {loading ? (
        <div className="bp-card">Loading...</div>
      ) : filtered.length === 0 ? (
        <div className="bp-card">No agents found.</div>
      ) : (
        <div className="bp-agents-grid">
          {filtered.map((a) => {
            const fullName = `${a.first_name || ""} ${a.last_name || ""}`.trim();
            const name = fullName || a.name || `#${a.id}`;
            const services = a.services_count ?? a.service_count ?? 0;
            const isActive =
              a.is_active !== undefined
                ? !!Number(a.is_active)
                : a.is_enabled !== undefined
                  ? !!Number(a.is_enabled)
                  : true;
            const initials = name
              .split(" ")
              .map((p) => p[0])
              .slice(0, 2)
              .join("")
              .toUpperCase();

            return (
              <div className="bp-agent-card" key={a.id}>
                <div className="bp-agent-thumb">
                  {a.image_url ? (
                    <img src={a.image_url} alt={name} />
                  ) : (
                    <div className="bp-agent-initials">{initials || "A"}</div>
                  )}
                </div>

                <div className="bp-agent-meta">
                  <div className="bp-agent-name">{name}</div>
                  <div className="bp-agent-sub">
                    {a.email || "—"}{a.phone ? ` • ${a.phone}` : ""}
                  </div>
                </div>

                <div className="bp-agent-stats">
                  <div className="bp-agent-stat">
                    <div className="bp-agent-stat-label">Services</div>
                    <div className="bp-agent-stat-value">{services}</div>
                  </div>
                  <div className={`bp-agent-status ${isActive ? "on" : "off"}`}>
                    {isActive ? "Active" : "Inactive"}
                  </div>
                </div>

                <div className="bp-agent-actions">
                  <a className="bp-top-btn" href={`admin.php?page=bp_agents_edit&id=${a.id}`}>Edit</a>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
