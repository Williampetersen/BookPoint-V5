import React, { useEffect, useState } from 'react';
import { bpFetch } from '../api/client';

export default function BookingDrawer({ bookingId, onClose, onUpdated }) {
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState('');
  const [data, setData] = useState(null);

  const [status, setStatus] = useState('pending');
  const [adminNotes, setAdminNotes] = useState('');

  // Reschedule state (C17.2)
  const [rsDate, setRsDate] = useState("");
  const [rsTime, setRsTime] = useState("");
  const [rsSlots, setRsSlots] = useState([]);
  const [rsLoading, setRsLoading] = useState(false);
  const [rsSaving, setRsSaving] = useState(false);
  const [rsErr, setRsErr] = useState("");
  const [rsOk, setRsOk] = useState("");

  // Helper functions for date/time conversion
  function toDateInput(mysqlOrIso){
    if(!mysqlOrIso) return "";
    const s = String(mysqlOrIso).replace("T"," ").slice(0,19);
    return s.slice(0,10);
  }
  function toTimeInput(mysqlOrIso){
    if(!mysqlOrIso) return "";
    const s = String(mysqlOrIso).replace("T"," ").slice(0,19);
    return s.slice(11,16); // HH:mm
  }
  function mysqlFromDateTime(dateStr, timeStr){
    return `${dateStr} ${timeStr}:00`;
  }

  useEffect(()=>{
    if(!bookingId) return;
    (async()=>{
      setLoading(true);
      setErr('');
      try{
        const res = await bpFetch(`/admin/bookings/${bookingId}`);
        const d = res?.data;
        setData(d);
        setStatus(d?.status || 'pending');
        setAdminNotes(d?.admin_notes || '');
        
        // Initialize reschedule fields (C17.2)
        const b = d?.booking || d || {};
        setRsDate(toDateInput(b.start_datetime || b.start));
        setRsTime(toTimeInput(b.start_datetime || b.start));
        setRsSlots([]);
        setRsErr("");
        setRsOk("");
      }catch(e){
        setErr(e.message || 'Failed to load booking');
      }finally{
        setLoading(false);
      }
    })();
  }, [bookingId]);

  // Load timeslots when date changes (C17.2)
  useEffect(()=>{
    (async()=>{
      if(!rsDate) return;
      if(!data) return;

      const booking = data.booking || data || {};
      const agentId = (data.agent?.id) || booking.agent_id || 0;
      const serviceId = (data.service?.id) || booking.service_id || 0;

      setRsLoading(true);
      setRsErr("");
      setRsOk("");

      try{
        const res = await bpFetch(`/admin/availability/slots?date=${rsDate}&agent_id=${agentId}&service_id=${serviceId}`);
        const payload = res?.data?.data ? res.data.data : (res?.data ? res.data : res);
        const slots = payload?.slots || [];
        setRsSlots(Array.isArray(slots) ? slots : []);
        // if current time isn't in slots, reset selection
        if(slots.length && !slots.find(s => s === rsTime)) setRsTime(slots[0]?.time || slots[0] || "");
        if(!slots.length) setRsTime("");
      }catch(e){
        setRsSlots([]);
        setRsTime("");
        setRsErr(e?.message || "Failed to load available times");
      }finally{
        setRsLoading(false);
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [rsDate]);

  const save = async(payload) => {
    setSaving(true);
    setErr('');
    try{
      const res = await bpFetch(`/admin/bookings/${bookingId}`, {
        method:'PATCH',
        body: payload
      });
      const d = res?.data;
      setData(d);
      if (onUpdated) onUpdated(d);
    }catch(e){
      setErr(e.message || 'Save failed');
    }finally{
      setSaving(false);
    }
  };

  // Reschedule handler (C17.2)
  async function saveReschedule(){
    if(!bookingId) return;
    if(!rsDate || !rsTime){
      setRsErr("Please select date and time");
      return;
    }

    setRsSaving(true);
    setRsErr("");
    setRsOk("");

    try{
      const start = mysqlFromDateTime(rsDate, rsTime);

      await bpFetch(`/admin/bookings/${bookingId}/reschedule`, {
        method: "POST",
        body: JSON.stringify({
          start_datetime: start,
          end_datetime: ""
        })
      });

      // Reload booking details to show updated times
      const res = await bpFetch(`/admin/bookings/${bookingId}`);
      const payload =
        res?.data?.booking ? res.data :
        res?.booking ? res :
        res?.data ? res.data :
        res;

      setData(payload);

      const b = payload?.booking || payload || {};
      setRsDate(toDateInput(b.start_datetime || b.start));
      setRsTime(toTimeInput(b.start_datetime || b.start));

      setRsOk("Rescheduled successfully ✅");
      
      // Notify parent to refresh calendar
      if(onUpdated) onUpdated({ id: bookingId });
    }catch(e){
      setRsErr(e?.message || "Reschedule failed");
    }finally{
      setRsSaving(false);
    }
  }

  const quickSetStatus = async(next) => {
    setStatus(next);
    await save({ status: next });
  };

  return (
    <div style={overlay} onMouseDown={onClose}>
      <div style={drawer} onMouseDown={(e)=>e.stopPropagation()}>
        <div style={topbar}>
          <div>
            <div style={{fontWeight:1000}}>Booking</div>
            <div style={{color:'var(--bp-muted)', fontWeight:850, fontSize:12}}>
              #{bookingId}
            </div>
          </div>
          <button className="bp-btn" onClick={onClose}>✕</button>
        </div>

        <div style={{padding:14, overflow:'auto', flex:1}}>
          {err ? <div style={errorBox}>{err}</div> : null}
          {loading ? <div style={{fontWeight:950}}>Loading…</div> : null}

          {!loading && data ? (
            <>
              <div className="bp-card" style={{boxShadow:'none'}}>
                <div style={{display:'flex', justifyContent:'space-between', gap:12, flexWrap:'wrap'}}>
                  <div>
                    <div style={{fontWeight:1000}}>{data.service_name}</div>
                    <div style={{color:'var(--bp-muted)', fontWeight:850, marginTop:6}}>
                      {data.date} • {data.start_time}–{data.end_time}
                    </div>
                    <div style={{color:'var(--bp-muted)', fontWeight:850, marginTop:6}}>
                      Agent: <span style={{fontWeight:950, color:'var(--bp-text)'}}>{data.agent_name}</span>
                    </div>
                  </div>

                  <span style={{
                    padding:'6px 10px',
                    borderRadius:999,
                    fontWeight:950,
                    background: statusBg(status),
                    color: statusColor(status),
                    height:'fit-content'
                  }}>
                    {status}
                  </span>
                </div>
              </div>

              <div style={{height:12}} />

              <div className="bp-card" style={{boxShadow:'none'}}>
                <div style={{fontWeight:950, marginBottom:8}}>Service</div>
                <div style={{fontWeight:900}}>{data.service_name || '—'}</div>
                {data.service?.duration_minutes ? (
                  <div style={{marginTop:6, color:'var(--bp-muted)', fontWeight:850}}>Duration: {data.service.duration_minutes} min</div>
                ) : null}
              </div>

              <div style={{height:12}} />

              <div className="bp-card" style={{boxShadow:'none'}}>
                <div style={{fontWeight:950}}>Customer</div>
                <div style={{marginTop:8, fontWeight:900}}>{data.customer_name || '—'}</div>
                <div style={{marginTop:6, color:'var(--bp-muted)', fontWeight:850}}>
                  {data.customer_email || ''}{data.customer_phone ? ` • ${data.customer_phone}` : ''}
                </div>
              </div>

              <div style={{height:12}} />

              <div className="bp-card" style={{boxShadow:'none'}}>
                <div style={{fontWeight:950, marginBottom:8}}>Status</div>

                <div style={{display:'flex', gap:10, flexWrap:'wrap'}}>
                  <button className="bp-btn bp-btn-soft" disabled={saving} onClick={()=>quickSetStatus('pending')}>Pending</button>
                  <button className="bp-btn bp-btn-primary" disabled={saving} onClick={()=>quickSetStatus('confirmed')}>Confirm</button>
                  <button className="bp-btn bp-btn-danger" disabled={saving} onClick={()=>quickSetStatus('cancelled')}>Cancel</button>
                </div>

                <div style={{marginTop:10}}>
                  <select className="bp-btn" value={status} disabled={saving}
                    onChange={(e)=>setStatus(e.target.value)}>
                    <option value="pending">pending</option>
                    <option value="confirmed">confirmed</option>
                    <option value="cancelled">cancelled</option>
                  </select>

                  <button
                    className="bp-btn"
                    style={{marginLeft:10}}
                    disabled={saving}
                    onClick={()=>save({ status })}
                  >
                    {saving ? 'Saving…' : 'Save'}
                  </button>
                </div>
              </div>

              <div style={{height:12}} />

              <div className="bp-card" style={{boxShadow:'none'}}>
                <div style={{fontWeight:950, marginBottom:8}}>Reschedule</div>

                {rsErr ? <div style={{...errorBox, marginBottom:8}}>{rsErr}</div> : null}
                {rsOk ? <div style={{...successBox, marginBottom:8}}>{rsOk}</div> : null}

                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10 }}>
                  <div>
                    <div className="bp-k" style={{ marginBottom: 6 }}>Date</div>
                    <input
                      className="bp-input"
                      type="date"
                      value={rsDate}
                      onChange={(e)=>setRsDate(e.target.value)}
                    />
                  </div>

                  <div>
                    <div className="bp-k" style={{ marginBottom: 6 }}>Time</div>
                    <select
                      className="bp-input"
                      value={rsTime}
                      onChange={(e)=>setRsTime(e.target.value)}
                      disabled={rsLoading || !rsSlots.length}
                    >
                      {!rsSlots.length ? <option value="">No available times</option> : null}
                      {rsSlots.map(t => <option key={t} value={t}>{t}</option>)}
                    </select>
                  </div>
                </div>

                <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginTop: 10 }}>
                  <div className="bp-muted">
                    {rsLoading ? "Loading available times…" : (rsSlots.length ? `${rsSlots.length} slots available` : "No slots")}
                  </div>

                  <button className="bp-btn bp-btn-primary" onClick={saveReschedule} disabled={rsSaving || rsLoading || !rsSlots.length}>
                    {rsSaving ? "Saving…" : "Save"}
                  </button>
                </div>
              </div>

              <div style={{height:12}} />

              <div className="bp-card" style={{boxShadow:'none'}}>
                <div style={{fontWeight:950, marginBottom:8}}>Admin notes</div>
                <textarea
                  value={adminNotes}
                  onChange={(e)=>setAdminNotes(e.target.value)}
                  rows={6}
                  style={textarea}
                  placeholder="Internal notes…"
                />
                <div style={{marginTop:10}}>
                  <button className="bp-btn bp-btn-primary" disabled={saving}
                    onClick={()=>save({ admin_notes: adminNotes })}
                  >
                    {saving ? 'Saving…' : 'Save notes'}
                  </button>
                </div>
              </div>
            </>
          ) : null}
        </div>

        <div style={footer}>
          <button className="bp-btn" onClick={onClose}>Close</button>
        </div>
      </div>
    </div>
  );
}

function statusBg(s){
  s = String(s||'').toLowerCase();
  if(s==='confirmed') return 'rgba(34,197,94,.12)';
  if(s==='pending') return 'rgba(245,158,11,.12)';
  if(s==='cancelled') return 'rgba(239,68,68,.12)';
  return 'rgba(100,116,139,.12)';
}
function statusColor(s){
  s = String(s||'').toLowerCase();
  if(s==='confirmed') return '#166534';
  if(s==='pending') return '#92400e';
  if(s==='cancelled') return '#991b1b';
  return '#334155';
}

const overlay = { position:'fixed', inset:0, background:'rgba(2,6,23,.55)', zIndex: 999999, display:'flex', justifyContent:'flex-end' };
const drawer  = { width:'min(520px, 100%)', height:'100%', background:'#fff', display:'flex', flexDirection:'column', boxShadow:'-30px 0 80px rgba(0,0,0,.35)' };
const topbar  = { padding:14, borderBottom:'1px solid var(--bp-border)', display:'flex', justifyContent:'space-between', alignItems:'center', gap:10 };
const footer  = { padding:14, borderTop:'1px solid var(--bp-border)', display:'flex', justifyContent:'flex-end', gap:10 };
const textarea = { width:'100%', padding:'10px 12px', borderRadius:14, border:'1px solid var(--bp-border)', fontWeight:850, outline:'none' };
const errorBox = { background:'#fef2f2', border:'1px solid #fecaca', color:'#991b1b', padding:10, borderRadius:14, fontWeight:900, marginBottom:10 };
const successBox = { background:'rgba(34,197,94,.10)', border:'1px solid rgba(34,197,94,.35)', color:'#166534', padding:10, borderRadius:14, fontWeight:900 };
