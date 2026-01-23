import React, { useEffect, useState } from "react";

export default function CustomersScreen() {
  const [customers, setCustomers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");

  useEffect(() => {
    loadCustomers();
  }, []);

  async function loadCustomers() {
    try {
      setLoading(true);
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/customers`, {
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce },
      });
      const json = await resp.json();
      setCustomers(json.data || []);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  }

  const filtered = customers.filter((c) => {
    const fullName = `${c.first_name || ""} ${c.last_name || ""}`.trim();
    const name = fullName || c.name || "";
    return (
      name.toLowerCase().includes(search.toLowerCase()) ||
      (c.email || "").toLowerCase().includes(search.toLowerCase())
    );
  });

  return (
    <div className="bp-container">
      <div className="bp-header">
        <h1>Customers</h1>
        <button className="bp-btn bp-btn-primary">+ New Customer</button>
      </div>

      <div className="bp-card" style={{ marginBottom: 20 }}>
        <input
          type="text"
          placeholder="Search customers..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="bp-input"
          style={{ width: "100%" }}
        />
      </div>

      {loading ? (
        <div className="bp-card">Loading...</div>
      ) : filtered.length === 0 ? (
        <div className="bp-card">No customers found.</div>
      ) : (
        <div className="bp-table-wrapper">
          <table className="bp-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Bookings</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((c) => {
                const fullName = `${c.first_name || ""} ${c.last_name || ""}`.trim();
                const name = fullName || c.name || `#${c.id}`;
                const bookings = c.bookings_count ?? c.booking_count ?? 0;

                return (
                  <tr key={c.id}>
                    <td>{name}</td>
                    <td>{c.email || "-"}</td>
                    <td>{c.phone || "-"}</td>
                    <td>{bookings}</td>
                  <td>
                    <button className="bp-btn-sm">View</button>
                    <button className="bp-btn-sm">Edit</button>
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
