import React, { useEffect, useState } from "react";

export default function AuditScreen() {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState("all");

  useEffect(() => {
    loadLogs();
  }, []);

  async function loadLogs() {
    try {
      setLoading(true);
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/audit-logs`, {
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce },
      });
      const json = await resp.json();
      const data = json.data?.items || json.data || [];
      setLogs(data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  }

  const filtered =
    filter === "all"
      ? logs
      : logs.filter((l) => (l.event || l.action) === filter);

  return (
    <div className="bp-container">
      <div className="bp-header">
        <h1>Audit Log</h1>
        <button onClick={() => setLogs([])} className="bp-btn bp-btn-secondary">
          Clear Log
        </button>
      </div>

      <div className="bp-card" style={{ marginBottom: 20 }}>
        <select
          value={filter}
          onChange={(e) => setFilter(e.target.value)}
          className="bp-input"
        >
          <option value="all">All Actions</option>
          <option value="create">Create</option>
          <option value="update">Update</option>
          <option value="delete">Delete</option>
          <option value="view">View</option>
        </select>
      </div>

      {loading ? (
        <div className="bp-card">Loading...</div>
      ) : filtered.length === 0 ? (
        <div className="bp-card">No audit logs found.</div>
      ) : (
        <div className="bp-table-wrapper">
          <table className="bp-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Action</th>
                <th>Item</th>
                <th>Timestamp</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((l, idx) => {
                const actor = l.actor_name || l.user_name || l.actor_type || "-";
                const action = l.event || l.action || "-";
                const item = l.item_type || l.resource || (l.booking_id ? `Booking #${l.booking_id}` : "-");
                const created = l.created_at ? new Date(l.created_at).toLocaleString() : "-";
                const details = l.details || l.meta || l.message || "-";

                return (
                  <tr key={idx}>
                    <td>{actor}</td>
                    <td>{action}</td>
                    <td>{item}</td>
                    <td>{created}</td>
                    <td>{typeof details === "string" ? details : JSON.stringify(details)}</td>
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
