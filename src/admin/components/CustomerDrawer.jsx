import React, { useEffect, useState } from "react";
import { bpFetch } from "../api/client";
import { Drawer } from "../ui/Drawer";

function FieldRow({ label, value }) {
  return (
    <div style={{ marginBottom: 10 }}>
      <div className="bp-muted" style={{ fontSize: 12 }}>{label}</div>
      <div style={{ fontWeight: 900 }}>{value || "—"}</div>
    </div>
  );
}

export default function CustomerDrawer({ customerId, onClose }) {
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");
  const [data, setData] = useState(null);

  useEffect(() => {
    if (!customerId) return;
    (async () => {
      setLoading(true);
      setErr("");
      try {
        const res = await bpFetch(`/admin/customers/${customerId}`);
        setData(res?.data || null);
      } catch (e) {
        setErr(e?.message || "Failed to load customer.");
      } finally {
        setLoading(false);
      }
    })();
  }, [customerId]);

  const customer = data?.customer || {};
  const bookings = data?.bookings || [];

  const fullName = `${customer.first_name || ""} ${customer.last_name || ""}`.trim() || customer.name || `#${customer.id || ""}`;

  return (
    <Drawer open={!!customerId} title={`Customer: ${fullName}`} onClose={onClose}>
      {err ? <div className="bp-error">{err}</div> : null}
      {loading ? <div className="bp-muted">Loading…</div> : null}

      {!loading && customer ? (
        <div>
          <div className="bp-card" style={{ marginBottom: 12 }}>
            <FieldRow label="ID" value={customer.id ? `#${customer.id}` : "—"} />
            <FieldRow label="Name" value={fullName} />
            <FieldRow label="Email" value={customer.email} />
            <FieldRow label="Phone" value={customer.phone} />
            <FieldRow label="WP User" value={customer.wp_user_id || "—"} />
            <FieldRow label="Created" value={customer.created_at} />
            <FieldRow label="Updated" value={customer.updated_at} />
          </div>

          {customer.custom_fields && Object.keys(customer.custom_fields).length ? (
            <div className="bp-card" style={{ marginBottom: 12 }}>
              <div style={{ fontWeight: 1000, marginBottom: 8 }}>Custom Fields</div>
              {Object.entries(customer.custom_fields).map(([key, val]) => (
                <FieldRow key={key} label={key} value={Array.isArray(val) ? val.join(", ") : String(val)} />
              ))}
            </div>
          ) : null}

          <div className="bp-card">
            <div style={{ fontWeight: 1000, marginBottom: 8 }}>Bookings</div>
            {bookings.length === 0 ? (
              <div className="bp-muted">No bookings found.</div>
            ) : (
              <div className="bp-table">
                <div className="bp-tr bp-th">
                  <div>ID</div>
                  <div>When</div>
                  <div>Service</div>
                  <div>Agent</div>
                  <div>Status</div>
                </div>
                {bookings.map((b) => (
                  <div key={b.id} className="bp-tr">
                    <div>#{b.id}</div>
                    <div className="bp-muted">{b.start_datetime || b.start || "—"}</div>
                    <div>{b.service_name || "—"}</div>
                    <div>{b.agent_name || "—"}</div>
                    <div>{b.status || "—"}</div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      ) : null}
    </Drawer>
  );
}
