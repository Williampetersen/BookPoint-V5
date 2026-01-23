import React, { useEffect, useState } from "react";

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
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/agents-full`, {
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce },
      });
      const json = await resp.json();
      setAgents(json.data || []);
    } catch (e) {
      console.error(e);
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
    <div className="bp-container">
      <div className="bp-header">
        <h1>Agents</h1>
        <button className="bp-btn bp-btn-primary">+ New Agent</button>
      </div>

      <div className="bp-card" style={{ marginBottom: 20 }}>
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
        <div className="bp-table-wrapper">
          <table className="bp-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Services</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
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

                return (
                  <tr key={a.id}>
                    <td>{name}</td>
                    <td>{a.email || "-"}</td>
                    <td>{a.phone || "-"}</td>
                    <td>{services}</td>
                    <td>{isActive ? "Active" : "Inactive"}</td>
                  <td>
                    <button className="bp-btn-sm">Edit</button>
                    <button className="bp-btn-sm bp-btn-danger">Delete</button>
                  </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
