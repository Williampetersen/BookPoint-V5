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

                <div className="bp-agent-body">
                  <div className="bp-agent-row">
                    <div className="bp-agent-name">{name}</div>
                    <div className={`bp-agent-status ${isActive ? "on" : "off"}`}>
                      {isActive ? "Active" : "Inactive"}
                    </div>
                    <a
                      className="bp-agent-more"
                      href={`admin.php?page=bp_agents_edit&id=${a.id}`}
                      aria-label="Edit agent"
                      title="Edit"
                    >
                      ...
                    </a>
                  </div>

                  <div className="bp-agent-sub">
                    <span className="bp-agent-email">{a.email || "-"}</span>
                    {a.phone ? <span className="bp-agent-phone"> * {a.phone}</span> : null}
                  </div>

                  <div className="bp-agent-row bp-agent-row--meta">
                    <div className="bp-agent-services">
                      <span className="bp-agent-services-label">Services</span>
                      <span className="bp-agent-services-value">{services}</span>
                    </div>
                    <a className="bp-top-btn bp-agent-edit" href={`admin.php?page=bp_agents_edit&id=${a.id}`}>Edit</a>
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
