import React, { useEffect, useState } from 'react';
import { bpFetch } from '../api/client';

export default function DashboardScreen(){
  const [stats, setStats] = useState({
    bookings_today: 0,
    bookings_week: 0,
    revenue_month: 0,
    pending: 0,
    recent: []
  });

  const [loading, setLoading] = useState(false);

  useEffect(()=>{
    (async()=>{
      setLoading(true);
      try{
        // if endpoint not ready yet, dashboard still looks good with zeros
        const res = await bpFetch('/admin/dashboard-stats').catch(()=>null);
        if(res?.data) setStats(res.data);
      }finally{
        setLoading(false);
      }
    })();
  }, []);

  return (
    <div className="bp-page">
      <div className="bp-header">
        <div>
          <h2 className="bp-title">Dashboard</h2>
          <div className="bp-subtitle">Overview of bookings, revenue, and quick actions.</div>
        </div>

        <div style={{display:'flex', gap:10, flexWrap:'wrap'}}>
          <button className="bp-btn bp-btn-soft" onClick={()=>location.href='admin.php?page=bp_catalog'}>Catalog</button>
          <button className="bp-btn bp-btn-soft" onClick={()=>location.href='admin.php?page=bp_calendar'}>Calendar</button>
          <button className="bp-btn bp-btn-primary" onClick={()=>location.href='admin.php?page=bp_bookings'}>Bookings</button>
        </div>
      </div>

      <div style={{height:14}} />

      <div className="bp-grid bp-grid-4">
        <KPI title="Bookings Today" value={stats.bookings_today} hint="Today" />
        <KPI title="This Week" value={stats.bookings_week} hint="Last 7 days" />
        <KPI title="Pending" value={stats.pending} hint="Need review" />
        <KPI title="Revenue (Month)" value={formatMoney(stats.revenue_month)} hint="This month" />
      </div>

      <div style={{height:14}} />

      <div className="bp-grid" style={{gridTemplateColumns:'2fr 1fr', alignItems:'start'}}>
        <div className="bp-card">
          <div style={{display:'flex', justifyContent:'space-between', alignItems:'center', gap:12}}>
            <div>
              <div className="bp-card-title">Recent bookings</div>
              <div className="bp-card-muted">Latest activity inside BookPoint.</div>
            </div>
            <span className="bp-chip">{loading ? 'Loading…' : 'Live'}</span>
          </div>

          <div style={{height:10}} />

          <table className="bp-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Customer</th>
                <th>Service</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {(stats.recent?.length ? stats.recent : demoRows).map((r, idx)=>(
                <tr key={idx}>
                  <td>{r.date}</td>
                  <td>{r.customer}</td>
                  <td>{r.service}</td>
                  <td>
                    <StatusPill status={r.status} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="bp-card">
          <div className="bp-card-title">Quick actions</div>
          <div className="bp-card-muted">Most-used shortcuts.</div>

          <div style={{height:12}} />

          <div style={{display:'grid', gap:10}}>
            <button className="bp-btn bp-btn-primary" onClick={()=>location.href='admin.php?page=bp_catalog'}>
              + Add service / category
            </button>
            <button className="bp-btn bp-btn-soft" onClick={()=>location.href='admin.php?page=bp_schedule'}>
              Edit schedule
            </button>
            <button className="bp-btn bp-btn-soft" onClick={()=>location.href='admin.php?page=bp_holidays'}>
              Add holiday / closed dates
            </button>
          </div>

          <div style={{height:12}} />

          <div className="bp-card" style={{boxShadow:'none', background:'#f8fafc'}}>
            <div style={{fontWeight:950}}>Next milestone</div>
            <div style={{color:'#64748b', fontWeight:850, marginTop:6}}>
              Build Booking Calendar (day/week/month) + Bookings CRUD.
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function KPI({ title, value, hint }){
  return (
    <div className="bp-card">
      <div className="bp-kpi">
        <div>
          <div className="lbl">{title}</div>
          <div className="num">{value}</div>
        </div>
        <span className="bp-chip">{hint}</span>
      </div>
    </div>
  );
}

function StatusPill({ status }){
  const s = String(status || '').toLowerCase();
  const bg =
    s === 'confirmed' ? 'rgba(34,197,94,.12)' :
    s === 'pending' ? 'rgba(245,158,11,.12)' :
    s === 'cancelled' ? 'rgba(239,68,68,.12)' : 'rgba(100,116,139,.12)';

  const color =
    s === 'confirmed' ? '#166534' :
    s === 'pending' ? '#92400e' :
    s === 'cancelled' ? '#991b1b' : '#334155';

  return (
    <span style={{
      padding:'6px 10px',
      borderRadius:999,
      fontWeight:950,
      background:bg,
      color
    }}>
      {status}
    </span>
  );
}

function formatMoney(n){
  const x = Number(n || 0);
  return x.toFixed(2);
}

const demoRows = [
  { date:'Today 10:00', customer:'Demo Customer', service:'Demo Service', status:'pending' },
  { date:'Today 12:30', customer:'Demo Customer', service:'Demo Service', status:'confirmed' },
  { date:'Yesterday 16:00', customer:'Demo Customer', service:'Demo Service', status:'cancelled' },
];
