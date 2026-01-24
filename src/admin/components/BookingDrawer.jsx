import React, { useEffect, useState } from 'react';
import { bpFetch } from '../api/client';

export default function BookingDrawer({ bookingId, onClose, onUpdated }) {
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [err, setErr] = useState('');
  const [deleteErr, setDeleteErr] = useState('');
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
  function mysqlFromDateObj(d){
    if (!(d instanceof Date) || isNaN(d.getTime())) return "";
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    return `${y}-${m}-${day} ${hh}:${mm}:00`;
  }

  useEffect(()=>{
    if(!bookingId) return;
    (async()=>{
      setLoading(true);
      setErr('');
      setDeleteErr('');
      try{
        const res = await bpFetch(`/admin/bookings/${bookingId}`);
        const d = normalizeBookingResponse(res);
        setData(d);
        const booking = d?.booking || d || {};
        setStatus(booking?.status || d?.status || 'pending');
        setAdminNotes(booking?.admin_notes || d?.admin_notes || '');
        
        // Initialize reschedule fields (C17.2)
        const b = booking;
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
        const normalized = normalizeSlots(slots);
        setRsSlots(normalized);
        // if current time isn't in slots, reset selection
        if(normalized.length && !normalized.includes(rsTime)) setRsTime(normalized[0] || "");
        if(!normalized.length) setRsTime("");
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
      const d = normalizeBookingResponse(res);
      setData(d);
      if (onUpdated) onUpdated(d);
    }catch(e){
      setErr(e.message || 'Save failed');
    }finally{
      setSaving(false);
    }
  };

  async function handleDelete(){
    if (!bookingId) return;
    if (!window.confirm('Delete this booking? This cannot be undone.')) return;
    setDeleting(true);
    setDeleteErr('');
    try{
      await bpFetch(`/admin/bookings/${bookingId}`, { method: 'DELETE' });
      if (onUpdated) onUpdated({ id: bookingId, deleted: true });
      onClose();
    }catch(e){
      setDeleteErr(e.message || 'Delete failed');
    }finally{
      setDeleting(false);
    }
  }

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
      const booking = data?.booking || data || {};
      const service = data?.service || {};
      const durationMinutes =
        Number(service.duration_minutes || booking.duration_minutes || booking.duration || 30);
      const startTs = new Date(`${rsDate}T${rsTime}:00`).getTime();
      const endTs = startTs + durationMinutes * 60000;
      const endDate = new Date(endTs);
      const end = mysqlFromDateObj(endDate);

      await bpFetch(`/admin/bookings/${bookingId}/reschedule`, {
        method: "POST",
        body: JSON.stringify({
          start_datetime: start,
          end_datetime: end
        })
      });

      // Reload booking details to show updated times
      const res = await bpFetch(`/admin/bookings/${bookingId}`);
      const payload = normalizeBookingResponse(res);

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
          {deleteErr ? <div style={errorBox}>{deleteErr}</div> : null}
          {loading ? <div style={{fontWeight:950}}>Loading…</div> : null}

          {!loading && data ? (
            <>
              <div className="bp-card" style={{boxShadow:'none'}}>
                <div style={{display:'flex', justifyContent:'space-between', gap:12, flexWrap:'wrap'}}>
                  <div>
                    {(() => {
                      const booking = data?.booking || data || {};
                      const service = data?.service || {};
                      const agent = data?.agent || {};
                      const customer = data?.customer || {};
                      const serviceName = service.name || booking.service_name || data.service_name || '—';
                      const agentName = agent.name || booking.agent_name || data.agent_name || `${agent.first_name || ''} ${agent.last_name || ''}`.trim() || '—';
                      const startRaw = booking.start_datetime || booking.start;
                      const endRaw = booking.end_datetime || booking.end;
                      const dateStr = toDateInput(startRaw) || booking.start_date || '—';
                      const startTime = toTimeInput(startRaw);
                      const endTime = toTimeInput(endRaw);
                      return (
                        <>
                          <div style={{fontWeight:1000}}>{serviceName}</div>
                          <div style={{color:'var(--bp-muted)', fontWeight:850, marginTop:6}}>
                            {dateStr} • {startTime && endTime ? `${startTime}–${endTime}` : (startTime || '—')}
                          </div>
                          <div style={{color:'var(--bp-muted)', fontWeight:850, marginTop:6}}>
                            Agent: <span style={{fontWeight:950, color:'var(--bp-text)'}}>{agentName}</span>
                          </div>
                          {customer.email ? (
                            <div style={{color:'var(--bp-muted)', fontWeight:850, marginTop:6}}>
                              {customer.email}
                            </div>
                          ) : null}
                        </>
                      );
                    })()}
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
                <div style={{fontWeight:950, marginBottom:8}}>All Details</div>
                {(() => {
                  const booking = data?.booking || data || {};
                  const customer = data?.customer || {};
                  const service = data?.service || {};
                  const agent = data?.agent || {};
                  const pricing = data?.pricing || {};
                  const answers = data?.answers || {};
                  const fields = data?.field_defs || data?.form_fields || [];

                  const extras = Array.isArray(data?.extras) ? data.extras : [];

                  return (
                    <div className="bp-kv">
                      <div className="bp-k">Booking ID</div><div className="bp-v">{booking.id || booking.booking_id || '—'}</div>
                      <div className="bp-k">Status</div><div className="bp-v">{booking.status || '—'}</div>
                      <div className="bp-k">Start</div><div className="bp-v">{booking.start_datetime || booking.start || '—'}</div>
                      <div className="bp-k">End</div><div className="bp-v">{booking.end_datetime || booking.end || '—'}</div>
                      <div className="bp-k">Service</div><div className="bp-v">{service.name || booking.service_name || '—'}</div>
                      <div className="bp-k">Agent</div><div className="bp-v">{agent.name || booking.agent_name || '—'}</div>
                      <div className="bp-k">Customer</div><div className="bp-v">{customer.name || booking.customer_name || '—'}</div>
                      <div className="bp-k">Email</div><div className="bp-v">{customer.email || booking.customer_email || '—'}</div>
                      <div className="bp-k">Phone</div><div className="bp-v">{customer.phone || booking.customer_phone || '—'}</div>
                      <div className="bp-k">Total</div><div className="bp-v">{pricing.total ?? booking.total_price ?? '—'}</div>
                      <div className="bp-k">Discount</div><div className="bp-v">{pricing.discount_total ?? booking.discount_total ?? '—'}</div>
                      <div className="bp-k">Promo</div><div className="bp-v">{pricing.promo_code || booking.promo_code || '—'}</div>
                      <div className="bp-k">Extras</div>
                      <div className="bp-v">
                        {extras.length ? extras.map((e) => `${e.name || 'Extra'} (${e.price ?? '-'})`).join(', ') : '—'}
                      </div>

                      {Object.keys(answers || {}).length > 0 ? (
                        Object.keys(answers).map((k) => {
                          const def = fields.find((d) => d.key === k || d.field_key === k || d.slug === k);
                          const label = def?.label || def?.name || k;
                          const val = Array.isArray(answers[k]) ? answers[k].join(', ') : answers[k];
                          return (
                            <React.Fragment key={`ans-${k}`}>
                              <div className="bp-k">{label}</div>
                              <div className="bp-v">{val === '' || val == null ? '—' : String(val)}</div>
                            </React.Fragment>
                          );
                        })
                      ) : (
                        <>
                          <div className="bp-k">Form Responses</div>
                          <div className="bp-v">—</div>
                        </>
                      )}
                    </div>
                  );
                })()}
              </div>

              <div className="bp-card" style={{boxShadow:'none'}}>
                <div style={{fontWeight:950, marginBottom:8}}>Service</div>
                {(() => {
                  const booking = data?.booking || data || {};
                  const service = data?.service || {};
                  const serviceName = service.name || booking.service_name || data.service_name || '—';
                  const duration = service.duration_minutes || booking.duration_minutes || booking.duration || null;
                  return (
                    <>
                      <div style={{fontWeight:900}}>{serviceName}</div>
                      {duration ? (
                        <div style={{marginTop:6, color:'var(--bp-muted)', fontWeight:850}}>Duration: {duration} min</div>
                      ) : null}
                    </>
                  );
                })()}
              </div>

              <div style={{height:12}} />

              <div className="bp-card" style={{boxShadow:'none'}}>
                <div style={{fontWeight:950}}>Customer</div>
                {(() => {
                  const booking = data?.booking || data || {};
                  const customer = data?.customer || {};
                  const name = customer.name || booking.customer_name || data.customer_name || `${customer.first_name || ''} ${customer.last_name || ''}`.trim() || '—';
                  const email = customer.email || booking.customer_email || data.customer_email || '';
                  const phone = customer.phone || booking.customer_phone || data.customer_phone || '';
                  return (
                    <>
                      <div style={{marginTop:8, fontWeight:900}}>{name}</div>
                      <div style={{marginTop:6, color:'var(--bp-muted)', fontWeight:850}}>
                        {email}{phone ? ` • ${phone}` : ''}
                      </div>
                    </>
                  );
                })()}
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
                    <option value="completed">completed</option>
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
                      {rsSlots.map((t) => (
                        <option key={t} value={t}>{t}</option>
                      ))}
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
          <button className="bp-btn bp-btn-danger" onClick={handleDelete} disabled={deleting || saving || rsSaving}>
            {deleting ? 'Deleting…' : 'Delete'}
          </button>
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

function normalizeSlots(slots){
  if (!Array.isArray(slots)) return [];
  return slots.map((s) => {
    if (typeof s === 'string') return s;
    if (typeof s === 'number') return String(s);
    if (s && typeof s === 'object') {
      return String(s.time || s.start_time || s.start || s.label || s.value || '');
    }
    return '';
  }).filter(Boolean);
}

function normalizeBookingResponse(res){
  if (!res) return res;
  if (res?.data?.booking) return res.data;
  if (res?.booking) return res;
  if (res?.data) return res.data;
  return res;
}

const overlay = { position:'fixed', inset:0, background:'rgba(2,6,23,.55)', zIndex: 999999, display:'flex', justifyContent:'flex-end' };
const drawer  = { width:'min(520px, 100%)', height:'100%', background:'#fff', display:'flex', flexDirection:'column', boxShadow:'-30px 0 80px rgba(0,0,0,.35)' };
const topbar  = { padding:14, borderBottom:'1px solid var(--bp-border)', display:'flex', justifyContent:'space-between', alignItems:'center', gap:10 };
const footer  = { padding:14, borderTop:'1px solid var(--bp-border)', display:'flex', justifyContent:'flex-end', gap:10 };
const textarea = { width:'100%', padding:'10px 12px', borderRadius:14, border:'1px solid var(--bp-border)', fontWeight:850, outline:'none' };
const errorBox = { background:'#fef2f2', border:'1px solid #fecaca', color:'#991b1b', padding:10, borderRadius:14, fontWeight:900, marginBottom:10 };
const successBox = { background:'rgba(34,197,94,.10)', border:'1px solid rgba(34,197,94,.35)', color:'#166534', padding:10, borderRadius:14, fontWeight:900 };
