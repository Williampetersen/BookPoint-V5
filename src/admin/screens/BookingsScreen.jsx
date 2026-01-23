import React, { useEffect, useState } from "react";
import { bpFetch, bpPost } from "../api/client";

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

  const [drawer, setDrawer] = useState(null);
  const [drawerLoading, setDrawerLoading] = useState(false);
  const [drawerErr, setDrawerErr] = useState("");

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

  async function changeStatus(id, newStatus){
    await bpPost(`/admin/bookings/${id}/status`, { status: newStatus });
    await load();
    if(drawer && drawer.booking && drawer.booking.id === id){
      setDrawer({ ...drawer, booking: { ...drawer.booking, status: newStatus } });
    }
  }

  async function openDrawer(id){
    setDrawerLoading(true);
    setDrawerErr("");
    setDrawer({ id, _loading: true });

    try{
      const res = await bpFetch(`/admin/bookings/${id}`);

      // bpFetch might return:
      // 1) {status:'success', data:{booking...}}
      // 2) {booking...} directly
      // 3) {data:{booking...}} (rare)
      const payload = res?.data?.booking ? res.data
                    : res?.booking ? res
                    : res?.data ? res.data
                    : res;

      setDrawer(payload);

      // If still missing booking data → show readable error
      if (!payload || (!payload.booking && !payload.id && !payload.status)) {
        setDrawerErr("Booking details response is empty or wrong format.");
      }

    }catch(e){
      setDrawerErr(e?.message || "Failed to load booking details");
    }finally{
      setDrawerLoading(false);
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
            <button key={b.id} className="bp-tr bp-tr-btn" onClick={()=>openDrawer(b.id)}>
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
                <button className="bp-chip" onClick={(e)=>{ e.stopPropagation(); changeStatus(b.id,"confirmed"); }}>Confirm</button>
                <button className="bp-chip" onClick={(e)=>{ e.stopPropagation(); changeStatus(b.id,"cancelled"); }}>Cancel</button>
              </div>
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
                <div className="bp-drawer-title">
                  Booking #{drawer?.booking?.id || drawer?.id}
                </div>
                <div className="bp-muted">
                  {drawer?.booking?.start_datetime || ""} → {drawer?.booking?.end_datetime || ""}
                </div>
              </div>
              <button className="bp-top-btn" onClick={()=>setDrawer(null)}>Close</button>
            </div>

            {drawerErr ? <div className="bp-error">{drawerErr}</div> : null}
            {drawerLoading ? <div className="bp-muted" style={{padding:12}}>Loading booking details…</div> : null}

            {!drawerLoading && (drawer?.booking || drawer?.id || drawer?.status) ? (
              <div className="bp-drawer-body">

                {(() => {
                  const bookingObj = drawer.booking || drawer;
                  const customerObj = drawer.customer || {};
                  const serviceObj = drawer.service || {};
                  const agentObj = drawer.agent || {};

                  return (
                    <>
                      <div className="bp-drawer-grid">
                        <div className="bp-section">
                          <div className="bp-section-title">Status</div>
                          <div className="bp-row2">
                            <div><Badge status={bookingObj.status} /></div>
                            <div className="bp-row-actions">
                              <button className="bp-chip" onClick={()=>changeStatus(bookingObj.id,"confirmed")}>Confirm</button>
                              <button className="bp-chip" onClick={()=>changeStatus(bookingObj.id,"cancelled")}>Cancel</button>
                              <button className="bp-chip" onClick={()=>changeStatus(bookingObj.id,"pending")}>Set Pending</button>
                            </div>
                          </div>
                        </div>

                        <div className="bp-section">
                          <div className="bp-section-title">Customer</div>
                          <div className="bp-kv">
                            <div className="bp-k">Name</div><div className="bp-v">{customerObj.name || "-"}</div>
                            <div className="bp-k">Email</div><div className="bp-v">{customerObj.email || "-"}</div>
                            <div className="bp-k">Phone</div><div className="bp-v">{customerObj.phone || "-"}</div>
                          </div>
                        </div>

                        <div className="bp-section">
                          <div className="bp-section-title">Service</div>
                          <div className="bp-kv">
                            <div className="bp-k">Service</div><div className="bp-v">{serviceObj.name || "-"}</div>
                            <div className="bp-k">Agent</div><div className="bp-v">{agentObj.name || "-"}</div>
                            <div className="bp-k">Extras</div>
                            <div className="bp-v">
                              {getExtrasLabel(drawer) || "-"}
                            </div>
                          </div>
                        </div>

                        <div className="bp-section">
                          <div className="bp-section-title">Pricing</div>
                          <PricingBox pricing={drawer.pricing || {}} />
                        </div>
                      </div>

                      <div className="bp-section" style={{marginTop:12}}>
                        <div className="bp-section-title">Order Items</div>
                        <OrderItems items={drawer.order_items || []} />
                      </div>

                      <div className="bp-section" style={{marginTop:12}}>
                        <div className="bp-section-title">Form Responses</div>
                        <DynamicFields fields={drawer.form_fields || []} answers={drawer.form_answers || {}} />
                      </div>

                      <div className="bp-section" style={{marginTop:12}}>
                        <div className="bp-section-title">Raw Booking Data (Debug)</div>
                        <pre className="bp-pre">
                          {JSON.stringify(drawer.raw || {}, null, 2)}
                        </pre>
                      </div>
                    </>
                  );
                })()}

              </div>
            ) : null}
          </div>
        </div>
      ) : null}
    </div>
  );
}

function money(v, currency){
  if(v === null || v === undefined || v === "") return "-";
  const num = Number(v);
  if(Number.isNaN(num)) return String(v);
  return currency ? `${num.toFixed(2)} ${currency}` : num.toFixed(2);
}

function PricingBox({ pricing }){
  const c = pricing.currency || "";
  return (
    <div className="bp-kv">
      <div className="bp-k">Subtotal</div><div className="bp-v">{money(pricing.subtotal, c)}</div>
      <div className="bp-k">Extras</div><div className="bp-v">{money(pricing.extras_total, c)}</div>
      <div className="bp-k">Discount</div><div className="bp-v">{money(pricing.discount_total, c)}</div>
      <div className="bp-k">Tax</div><div className="bp-v">{money(pricing.tax_total, c)}</div>
      <div className="bp-k">Total</div><div className="bp-v" style={{fontWeight:1200}}>{money(pricing.total, c)}</div>
      <div className="bp-k">Promo</div><div className="bp-v">{pricing.promo_code || "-"}</div>
    </div>
  );
}

function OrderItems({ items }){
  if(!items || items.length === 0){
    return <div className="bp-muted">No order items stored.</div>;
  }
  return (
    <div className="bp-items">
      {items.map((it, idx)=>(
        <div key={idx} className="bp-item">
          <div style={{fontWeight:1100}}>{it.name || it.title || it.service_name || `Item ${idx+1}`}</div>
          <div className="bp-muted" style={{fontSize:12}}>
            Qty: {it.qty ?? it.quantity ?? 1}
            {it.price ? ` • Price: ${it.price}` : ""}
          </div>
        </div>
      ))}
    </div>
  );
}

function getExtrasLabel(drawer){
  const items = drawer?.order_items || [];
  if (!items.length) return "";
  const parts = items.map((it) => {
    const name = it.name || it.title || it.service_name;
    const price = it.price !== undefined && it.price !== null ? ` (${it.price})` : "";
    return name ? `${name}${price}` : "";
  }).filter(Boolean);
  return parts.length ? parts.join(", ") : "";
}

function DynamicFields({ fields, answers }){
  const list = fields || [];
  const hasFields = list.length > 0;

  if (!hasFields && Object.keys(answers || {}).length === 0) {
    return <div className="bp-muted">No form answers stored.</div>;
  }

  if (!hasFields) {
    return (
      <div className="bp-kv">
        {Object.keys(answers || {}).map((k)=>{
          const val = answers[k];
          const display = Array.isArray(val) ? val.join(", ") : (val === true ? "Yes" : val === false ? "No" : (val ?? "-"));
          return (
            <React.Fragment key={k}>
              <div className="bp-k">{k}</div>
              <div className="bp-v">{String(display)}</div>
            </React.Fragment>
          );
        })}
      </div>
    );
  }

  return (
    <div className="bp-kv">
      {list.map((f)=>{
        const key = f.key;
        const scopedKey = f.scope ? `${f.scope}.${f.key}` : null;
        const val = answers?.[key] ?? (scopedKey ? answers?.[scopedKey] : undefined);
        const display = Array.isArray(val) ? val.join(", ") : (val === true ? "Yes" : val === false ? "No" : (val ?? "-"));
        return (
          <React.Fragment key={`${f.scope || 'field'}:${key}`}>
            <div className="bp-k">{f.label || key}</div>
            <div className="bp-v">{String(display)}</div>
          </React.Fragment>
        );
      })}
    </div>
  );
}
