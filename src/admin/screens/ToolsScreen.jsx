import React, { useEffect, useState } from "react";

export default function ToolsScreen() {
  const [tools] = useState([
    { id: "sync_relations", name: "Sync Relations", description: "Rebuild service/agent/category relations" },
    { id: "generate_demo", name: "Generate Demo Data", description: "Create sample services, agents, customers, bookings" },
    { id: "reset_cache", name: "Reset Cache", description: "Clear cached data" },
  ]);

  const [running, setRunning] = useState(null);
  const [status, setStatus] = useState(null);

  useEffect(() => {
    loadStatus();
  }, []);

  async function loadStatus() {
    try {
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/tools/status`, {
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce },
      });
      const json = await resp.json();
      setStatus(json.data || null);
    } catch (e) {
      console.error(e);
    }
  }

  async function runTool(toolId) {
    try {
      setRunning(toolId);
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/tools/run/${toolId}`, {
        method: "POST",
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce, "Content-Type": "application/json" },
        body: JSON.stringify({}),
      });
      const json = await resp.json();
      alert(json.message || json.data?.message || "Tool completed");
      loadStatus();
    } catch (e) {
      console.error(e);
      alert("Error running tool");
    } finally {
      setRunning(null);
    }
  }

  return (
    <div className="bp-container">
      <div className="bp-header">
        <h1>Tools</h1>
      </div>

      {status ? (
        <div className="bp-card" style={{ marginBottom: 20 }}>
          <div className="bp-card-label">System Status</div>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 10, marginTop: 10 }}>
            <div><strong>DB Version:</strong> {status.db_version || "-"}</div>
            <div><strong>Plugin Version:</strong> {status.plugin_version || "-"}</div>
            <div><strong>Tables:</strong> {status.tables_ok_count || 0} / {status.tables_total || 0}</div>
          </div>
        </div>
      ) : null}

      <div className="bp-grid" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(300px, 1fr))", gap: 20 }}>
        {tools.map((tool) => (
          <div key={tool.id} className="bp-card">
            <h3>{tool.name}</h3>
            <p style={{ color: "#666", marginBottom: 15 }}>{tool.description}</p>
            <button
              onClick={() => runTool(tool.id)}
              disabled={running === tool.id}
              className="bp-btn bp-btn-primary"
              style={{ width: "100%" }}
            >
              {running === tool.id ? "Running..." : "Run"}
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}
