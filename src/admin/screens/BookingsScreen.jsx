import React, { useEffect, useState } from "react";
import { bpFetch } from "../api/client";

function Badge({ status }){
  const s = (status || "pending").toLowerCase();
  return <span className={`bp-badge ${s}`}>{s}</span>;
}

export default function BookingsScreen(){
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("all");
  const [sort, setSortState] = useState("desc");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [page, setPage] = useState(1);
  const [per] = useState(20);

  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");
  const [items, setItems] = useState([]);
  const [total, setTotal] = useState(0);
  const [stats, setStats] = useState({ total:0, pending:0, confirmed:0, cancelled:0 });

  async function load(){
    setLoading(true);
    setErr("");
    try{
      const url =
        `/admin/bookings?` +
        `q=${encodeURIComponent(q)}` +
        `&status=${encodeURIComponent(status)}` +
        `&sort=${encodeURIComponent(sort)}` +
        `&date_from=${encodeURIComponent(dateFrom)}` +
        `&date_to=${encodeURIComponent(dateTo)}` +
        `&page=${page}&per=${per}`;

      const res = await bpFetch(url);
      const list = res?.data?.items || [];
      const sorted = [...list].sort((a, b) => (Number(b.id) || 0) - (Number(a.id) || 0));
      setItems(sorted);
      setTotal(res?.data?.total || 0);
      
      const s = { total: res?.data?.total || 0, pending:0, confirmed:0, cancelled:0 };
      for(const b of sorted){
        const st = (b.status || "pending").toLowerCase();
        if(st === "pending") s.pending++;
        if(st === "confirmed") s.confirmed++;
        if(st === "cancelled") s.cancelled++;
      }
      setStats(s);
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

  async function deleteBooking(id){
    if (!window.confirm("Delete this booking? This cannot be undone.")) return;
    await bpFetch(`/admin/bookings/${id}`, { method: "DELETE" });
    await load();
  }

  function goEdit(id){
    window.location.href = `admin.php?page=bp_bookings_edit&id=${id}`;
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

      <div className="bp-cards" style={{marginBottom:14}}>
        <div className="bp-card">
          <div className="bp-card-label">Total (filtered)</div>
          <div className="bp-card-value">{loading ? "…" : stats.total}</div>
        </div>
        <div className="bp-card">
          <div className="bp-card-label">Pending</div>
          <div className="bp-card-value">{loading ? "…" : stats.pending}</div>
        </div>
        <div className="bp-card">
          <div className="bp-card-label">Confirmed</div>
          <div className="bp-card-value">{loading ? "…" : stats.confirmed}</div>
        </div>
        <div className="bp-card">
          <div className="bp-card-label">Cancelled</div>
          <div className="bp-card-value">{loading ? "…" : stats.cancelled}</div>
        </div>
      </div>

      <div className="bp-card" style={{marginBottom:14}}>
        <form className="bp-filters" onSubmit={onSearchSubmit}>
          <div className="bp-filter-group">
            <label className="bp-filter-label">Search</label>
            <input
              className="bp-input"
              placeholder="Search customer, email, service, agent…"
              value={q}
              onChange={(e)=>setQ(e.target.value)}
            />
          </div>

          <div className="bp-filter-group">
            <label className="bp-filter-label">Status</label>
            <select className="bp-input" value={status} onChange={(e)=>{setStatus(e.target.value); setPage(1);}}>
              <option value="all">All statuses</option>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>

          <div className="bp-filter-group">
            <label className="bp-filter-label">From date</label>
            <input
              className="bp-input"
              type="date"
              value={dateFrom}
              onChange={(e)=>setDateFrom(e.target.value)}
            />
          </div>

          <div className="bp-filter-group">
            <label className="bp-filter-label">To date</label>
            <input
              className="bp-input"
              type="date"
              value={dateTo}
              onChange={(e)=>setDateTo(e.target.value)}
            />
          </div>

          <div className="bp-filter-group">
            <label className="bp-filter-label">Sort by</label>
            <select
              className="bp-input"
              value={sort}
              onChange={(e)=>{ setSortState(e.target.value); setPage(1); }}
            >
              <option value="desc">Newest created first</option>
              <option value="asc">Oldest created first</option>
            </select>
          </div>

          <div className="bp-filter-group">
            <label className="bp-filter-label">&nbsp;</label>
            <button className="bp-primary-btn" type="submit">Search</button>
          </div>
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
            <div
              key={b.id}
              className="bp-tr bp-tr-btn"
              role="button"
              tabIndex={0}
              onClick={()=>goEdit(b.id)}
              onKeyDown={(e)=>{ if (e.key === "Enter" || e.key === " ") goEdit(b.id); }}
            >
              <div>#{b.id}</div>
              <div className="bp-muted">{b.start_datetime}</div>
              <div>{b.service_name || "-"}</div>
              <div>{b.agent_name || "-"}</div>
              <div>
                <div style={{fontWeight:1100}}>{b.customer_name || "-"}</div>
                <div className="bp-muted" style={{fontSize:12}}>{b.customer_email || "-"}</div>
              </div>
              <div className="bp-row-actions">
                <Badge status={b.status} />
                <button className="bp-chip" onClick={(e)=>{ e.stopPropagation(); goEdit(b.id); }}>Edit</button>
              </div>
            </div>
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
    </div>
  );
}

