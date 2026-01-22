import React, { useEffect, useState } from "react";
import { bpFetch } from "../api/client";

function Badge({ status }){
  const s = (status || "pending").toLowerCase();
  return <span className={`bp-badge ${s}`}>{s}</span>;
}

export default function DashboardScreen(){
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [data, setData] = useState({
    kpi: { bookings_today:0, upcoming_7d:0, pending:0, services:0, agents:0 },
    recent: [],
    chart7: [],
  });

  async function load(){
    setLoading(true);
    setErr("");
    try{
      const res = await bpFetch("/admin/dashboard");
      setData(res?.data || data);
    }catch(e){
      setErr(e.message || "Failed to load dashboard");
    }finally{
      setLoading(false);
    }
  }

  useEffect(()=>{ load(); /* eslint-disable-next-line */ }, []);

  return (
    <div>
      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Main Dashboard</div>
          <div className="bp-muted">Overview of bookings, services, agents</div>
        </div>
        <div className="bp-head-actions">
          <a className="bp-top-btn" href="admin.php?page=bp-services">+ Add Service</a>
          <a className="bp-top-btn" href="admin.php?page=bp-agents">+ Add Agent</a>
          <button className="bp-primary-btn" onClick={()=>alert("Next: open booking wizard")}>
            + Create Booking
          </button>
        </div>
      </div>

      {err ? <div className="bp-error">{err}</div> : null}

      <div className="bp-cards">
        <div className="bp-card">
          <div className="bp-card-label">Bookings Today</div>
          <div className="bp-card-value">{loading ? "…" : data.kpi.bookings_today}</div>
        </div>
        <div className="bp-card">
          <div className="bp-card-label">Upcoming (7 days)</div>
          <div className="bp-card-value">{loading ? "…" : data.kpi.upcoming_7d}</div>
        </div>
        <div className="bp-card">
          <div className="bp-card-label">Pending</div>
          <div className="bp-card-value">{loading ? "…" : data.kpi.pending}</div>
        </div>
        <div className="bp-card">
          <div className="bp-card-label">Services</div>
          <div className="bp-card-value">{loading ? "…" : data.kpi.services}</div>
        </div>
        <div className="bp-card">
          <div className="bp-card-label">Agents</div>
          <div className="bp-card-value">{loading ? "…" : data.kpi.agents}</div>
        </div>
      </div>

      <div className="bp-grid" style={{marginTop:14}}>
        <div className="bp-card bp-card-big">
          <div className="bp-card-label" style={{marginBottom:10}}>Bookings (last 7 days)</div>
          <div className="bp-mini-chart">
            {(data.chart7 || []).map((p)=>(
              <div key={p.day} className="bp-mini-bar">
                <div
                  className="bp-mini-bar-fill"
                  style={{height: `${Math.min(100, (p.count*18)+10)}%`}}
                  title={`${p.day} • ${p.count} bookings`}
                />
                <div className="bp-mini-bar-label">{p.day.slice(5)}</div>
              </div>
            ))}
            {(!data.chart7 || data.chart7.length===0) && (
              <div className="bp-muted">No chart data yet.</div>
            )}
          </div>
        </div>

        <div className="bp-card">
          <div className="bp-card-label" style={{marginBottom:10}}>Quick Links</div>
          <div className="bp-quick">
            <a className="bp-quick-item" href="admin.php?page=bp-bookings">Manage bookings</a>
            <a className="bp-quick-item" href="admin.php?page=bp-calendar">Open calendar</a>
            <a className="bp-quick-item" href="admin.php?page=bp-form-fields">Edit form fields</a>
            <a className="bp-quick-item" href="admin.php?page=bp-settings">Settings</a>
          </div>
        </div>
      </div>

      <div className="bp-card" style={{marginTop:14}}>
        <div className="bp-card-label" style={{marginBottom:10}}>Recent Bookings</div>

        <div className="bp-table">
          <div className="bp-tr bp-th">
            <div>ID</div>
            <div>When</div>
            <div>Service</div>
            <div>Agent</div>
            <div>Customer</div>
            <div>Status</div>
          </div>

          {(data.recent || []).map((b)=>(
            <a key={b.id} className="bp-tr" href={`admin.php?page=bp-bookings&view=${b.id}`}>
              <div>#{b.id}</div>
              <div className="bp-muted">{b.start}</div>
              <div>{b.service_name || "-"}</div>
              <div>{b.agent_name || "-"}</div>
              <div>{b.customer_name || "-"}</div>
              <div><Badge status={b.status} /></div>
            </a>
          ))}

          {(!data.recent || data.recent.length===0) && (
            <div className="bp-muted" style={{padding:10}}>No bookings yet.</div>
          )}
        </div>
      </div>
    </div>
  );
}

