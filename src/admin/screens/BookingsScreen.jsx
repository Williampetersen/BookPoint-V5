import React, { useEffect, useState } from "react";
import { bpFetch, bpPost } from "../api/client";

function Badge({ status }){
  const s = (status || "pending").toLowerCase();
  return <span className={`bp-badge ${s}`}>{s}</span>;
}

export default function BookingsScreen(){
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("all");
  const [page, setPage] = useState(1);
  const [per] = useState(20);

  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");
  const [items, setItems] = useState([]);
  const [total, setTotal] = useState(0);

  const [drawer, setDrawer] = useState(null);

  async function load(){
    setLoading(true);
    setErr("");
    try{
      const res = await bpFetch(`/admin/bookings?q=${encodeURIComponent(q)}&status=${status}&page=${page}&per=${per}`);
      setItems(res?.data?.items || []);
      setTotal(res?.data?.total || 0);
    }catch(e){
      setErr(e.message || "Failed to load bookings");
    }finally{
      setLoading(false);
    }
  }

  useEffect(()=>{ load(); /* eslint-disable-next-line */ }, [status, page]);

  function onSearchSubmit(e){
    e.preventDefault();
    setPage(1);
    load();
  }

  const pages = Math.max(1, Math.ceil(total / per));

  async function changeStatus(id, newStatus){
    await bpPost(`/admin/bookings/${id}/status`, { status: newStatus });
    await load();
    if(drawer && drawer.id === id){
      setDrawer({ ...drawer, status: newStatus });
    }
  }

  return (
    <div>
      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Bookings</div>
          <div className="bp-muted">Search, filter, and manage appointments</div>
        </div>
      </div>

      {err ? <div className="bp-error">{err}</div> : null}

      <div className="bp-card" style={{marginBottom:14}}>
        <form className="bp-filters" onSubmit={onSearchSubmit}>
          <input
            className="bp-input"
            placeholder="Search customer, email, service, agent…"
            value={q}
            onChange={(e)=>setQ(e.target.value)}
          />
          <select className="bp-input" value={status} onChange={(e)=>{setStatus(e.target.value); setPage(1);}}>
            <option value="all">All statuses</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="cancelled">Cancelled</option>
          </select>

          <button className="bp-primary-btn" type="submit">Search</button>
        </form>
      </div>

      <div className="bp-card">
        <div className="bp-table">
          <div className="bp-tr bp-th">
            <div>ID</div>
            <div>When</div>
            <div>Service</div>
            <div>Agent</div>
            <div>Customer</div>
            <div>Status</div>
          </div>

          {loading ? <div className="bp-muted" style={{padding:10}}>Loading…</div> : null}

          {!loading && items.map((b)=>(
            <button key={b.id} className="bp-tr bp-tr-btn" onClick={()=>setDrawer(b)}>
              <div>#{b.id}</div>
              <div className="bp-muted">{b.start_datetime}</div>
              <div>{b.service_name || "-"}</div>
              <div>{b.agent_name || "-"}</div>
              <div>{b.customer_name || "-"}</div>
              <div><Badge status={b.status} /></div>
            </button>
          ))}

          {!loading && items.length === 0 ? (
            <div className="bp-muted" style={{padding:10}}>No bookings found.</div>
          ) : null}
        </div>

        <div className="bp-pager">
          <button className="bp-top-btn" disabled={page<=1} onClick={()=>setPage(p=>Math.max(1,p-1))}>Prev</button>
          <div className="bp-muted" style={{fontWeight:1000}}>Page {page} / {pages}</div>
          <button className="bp-top-btn" disabled={page>=pages} onClick={()=>setPage(p=>Math.min(pages,p+1))}>Next</button>
        </div>
      </div>

      {drawer ? (
        <div className="bp-drawer-wrap" onMouseDown={(e)=>{ if(e.target.classList.contains("bp-drawer-wrap")) setDrawer(null); }}>
          <div className="bp-drawer">
            <div className="bp-drawer-head">
              <div>
                <div className="bp-drawer-title">Booking #{drawer.id}</div>
                <div className="bp-muted">{drawer.start_datetime} → {drawer.end_datetime}</div>
              </div>
              <button className="bp-top-btn" onClick={()=>setDrawer(null)}>Close</button>
            </div>

            <div className="bp-drawer-body">
              <div className="bp-row"><div className="bp-k">Status</div><div className="bp-v"><Badge status={drawer.status} /></div></div>
              <div className="bp-row"><div className="bp-k">Service</div><div className="bp-v">{drawer.service_name || "-"}</div></div>
              <div className="bp-row"><div className="bp-k">Agent</div><div className="bp-v">{drawer.agent_name || "-"}</div></div>
              <div className="bp-row"><div className="bp-k">Customer</div><div className="bp-v">{drawer.customer_name || "-"}</div></div>
              <div className="bp-row"><div className="bp-k">Email</div><div className="bp-v">{drawer.customer_email || "-"}</div></div>

              <div style={{height:12}} />

              <div className="bp-drawer-actions">
                <button className="bp-top-btn" onClick={()=>changeStatus(drawer.id, "confirmed")}>Confirm</button>
                <button className="bp-top-btn" onClick={()=>changeStatus(drawer.id, "cancelled")}>Cancel</button>
                <button className="bp-top-btn" onClick={()=>changeStatus(drawer.id, "pending")}>Set Pending</button>
              </div>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
