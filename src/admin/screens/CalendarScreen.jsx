import React, { useMemo, useEffect, useState } from 'react';
import { bpFetch } from '../api/client';
import BookingDrawer from '../components/BookingDrawer';
import { toMinutes, clamp, layoutDayOverlaps } from '../utils/calendarLayout';
import { snapMinutes } from '../utils/drag';

export default function CalendarScreen(){
  const [view, setView] = useState('month'); // month | week | day
  const [focusDate, setFocusDate] = useState(() => new Date());
  const [filters, setFilters] = useState({
    agent_id: 'all',
    service_id: 'all',
    status: 'all',
  });
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(false);
  const [selectedEvent, setSelectedEvent] = useState(null);
  const [drawerId, setDrawerId] = useState(null);
  const [agents, setAgents] = useState([]);
  const [services, setServices] = useState([]);

  const refreshEvents = () => setFocusDate(d => new Date(d));

  const headerLabel = useMemo(()=>formatHeader(focusDate, view), [focusDate, view]);

  const goToday = () => setFocusDate(new Date());

  const goPrev = () => {
    const d = new Date(focusDate);
    if (view === 'month') d.setMonth(d.getMonth() - 1);
    if (view === 'week') d.setDate(d.getDate() - 7);
    if (view === 'day') d.setDate(d.getDate() - 1);
    setFocusDate(d);
  };

  const goNext = () => {
    const d = new Date(focusDate);
    if (view === 'month') d.setMonth(d.getMonth() + 1);
    if (view === 'week') d.setDate(d.getDate() + 7);
    if (view === 'day') d.setDate(d.getDate() + 1);
    setFocusDate(d);
  };

  useEffect(()=>{
    (async()=>{
      const { start, end } = getRange(focusDate, view);
      setLoading(true);
      try{
        const qs = new URLSearchParams({
          start, end,
          status: filters.status || 'all',
          agent_id: filters.agent_id || 'all',
          service_id: filters.service_id || 'all',
        }).toString();

        const res = await bpFetch(`/admin/calendar/bookings?${qs}`);
        setEvents(res?.data || []);
      }catch(e){
        console.error(e);
        setEvents([]);
      }finally{
        setLoading(false);
      }
    })();
  }, [focusDate, view, filters.status, filters.agent_id, filters.service_id]);

  useEffect(()=>{
    (async()=>{
      try{
        const [aRes, sRes] = await Promise.all([
          bpFetch('/admin/agents'),
          bpFetch('/admin/services'),
        ]);
        setAgents(aRes?.data || []);
        setServices(sRes?.data || []);
      }catch(e){
        console.error(e);
        setAgents([]);
        setServices([]);
      }
    })();
  }, []);

  return (
    <div className="bp-page">
      <div className="bp-header">
        <div>
          <h2 className="bp-title">Calendar</h2>
          <div className="bp-subtitle">View bookings by day, week, or month.</div>
        </div>

        <div style={{display:'flex', gap:10, flexWrap:'wrap', alignItems:'center'}}>
          <button className="bp-btn bp-btn-soft" onClick={goPrev}>←</button>
          <div className="bp-chip" style={{fontWeight:950}}>{headerLabel}</div>
          <button className="bp-btn bp-btn-soft" onClick={goNext}>→</button>
          <button className="bp-btn" onClick={goToday}>Today</button>

          <ViewTabs view={view} setView={setView} />
        </div>
      </div>

      <div style={{height:14}} />

      <div className="bp-grid" style={{gridTemplateColumns:'1fr 360px', gap:14}}>
        {/* Main calendar */}
        <div className="bp-card" style={{padding:0, overflow:'hidden'}}>
          <div style={{padding:14, borderBottom:'1px solid var(--bp-border)', display:'flex', justifyContent:'space-between', alignItems:'center', gap:10, flexWrap:'wrap'}}>
            <div style={{fontWeight:950}}>Bookings</div>
            <div style={{display:'flex', gap:10, flexWrap:'wrap', alignItems:'center'}}>
              {loading ? <span className="bp-chip">Loading…</span> : null}
              <select className="bp-btn" value={filters.status} onChange={(e)=>setFilters(f=>({...f, status:e.target.value}))}>
                <option value="all">All status</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="cancelled">Cancelled</option>
              </select>
              <select className="bp-btn" value={filters.agent_id} onChange={(e)=>setFilters(f=>({...f, agent_id:e.target.value}))}>
                <option value="all">All agents</option>
                {agents.map(a => (
                  <option key={a.id} value={String(a.id)}>{a.name}</option>
                ))}
              </select>
              <select className="bp-btn" value={filters.service_id} onChange={(e)=>setFilters(f=>({...f, service_id:e.target.value}))}>
                <option value="all">All services</option>
                {services.map(s => (
                  <option key={s.id} value={String(s.id)}>{s.name}</option>
                ))}
              </select>
            </div>
          </div>

          <div style={{padding:14}}>
            {view === 'month' ? <MonthView focusDate={focusDate} events={events} onPickEvent={(ev)=>{ setSelectedEvent(ev); setDrawerId(ev.id); }} /> : null}
            {view === 'week' ? <WeekView focusDate={focusDate} events={events} onPickEvent={(ev)=>{ setSelectedEvent(ev); setDrawerId(ev.id); }} onReschedule={refreshEvents} /> : null}
            {view === 'day' ? <DayView focusDate={focusDate} events={events} onPickEvent={(ev)=>{ setSelectedEvent(ev); setDrawerId(ev.id); }} /> : null}
          </div>
        </div>

        {/* Right sidebar */}
        <div className="bp-card">
          <div className="bp-card-title">Booking details</div>
          <div className="bp-card-muted">Click a booking to see details here.</div>

          <div style={{height:12}} />

          {!selectedEvent ? (
            <div style={{color:'var(--bp-muted)', fontWeight:850}}>
              No booking selected.
            </div>
          ) : (
            <div className="bp-card" style={{boxShadow:'none', background:'#f8fafc'}}>
              <div style={{fontWeight:950}}>{selectedEvent.title}</div>
              <div style={{marginTop:8, color:'var(--bp-muted)', fontWeight:850}}>
                {selectedEvent.date} • {selectedEvent.start_time}–{selectedEvent.end_time}
              </div>
              <div style={{marginTop:8, fontWeight:900}}>
                Agent: <span style={{fontWeight:850}}>{selectedEvent.agent_name}</span>
              </div>
              <div style={{marginTop:6, fontWeight:900}}>
                Customer: <span style={{fontWeight:850}}>{selectedEvent.customer_name}</span>
              </div>

              <div style={{height:12}} />

              <button className="bp-btn bp-btn-soft" onClick={()=>alert('Next: open real Booking editor drawer')}>
                Open booking
              </button>
            </div>
          )}
        </div>
      </div>

      {drawerId ? (
        <BookingDrawer
          bookingId={drawerId}
          onClose={()=>setDrawerId(null)}
          onUpdated={()=>{
            setFocusDate(d => new Date(d));
          }}
        />
      ) : null}
    </div>
  );
}

function ViewTabs({ view, setView }){
  const tabs = [
    { key:'month', label:'Month' },
    { key:'week', label:'Week' },
    { key:'day', label:'Day' },
  ];
  return (
    <div style={{display:'flex', gap:8, background:'#fff', border:'1px solid var(--bp-border)', borderRadius:999, padding:6}}>
      {tabs.map(t => {
        const active = view === t.key;
        return (
          <button
            key={t.key}
            className="bp-btn"
            onClick={()=>setView(t.key)}
            style={{
              borderRadius:999,
              padding:'8px 10px',
              border:'none',
              background: active ? 'var(--bp-primary-10)' : 'transparent'
            }}
          >
            {t.label}
          </button>
        );
      })}
    </div>
  );
}

/* -------- Month View (UI shell) -------- */
function MonthView({ focusDate, events, onPickEvent }){
  const grid = buildMonthGrid(focusDate);
  const byDate = useMemo(()=>{
    const map = {};
    (events || []).forEach(ev=>{
      if(!map[ev.date]) map[ev.date] = [];
      map[ev.date].push(ev);
    });
    return map;
  }, [events]);
  return (
    <div>
      <div style={{display:'grid', gridTemplateColumns:'repeat(7, 1fr)', gap:8, marginBottom:8}}>
        {['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].map(d=>(
          <div key={d} style={{color:'var(--bp-muted)', fontWeight:950, fontSize:12}}>{d}</div>
        ))}
      </div>

      <div style={{display:'grid', gridTemplateColumns:'repeat(7, 1fr)', gap:8}}>
        {grid.map((cell, idx)=>{
          const key = toISODate(cell.date);
          const list = byDate[key] || [];
          const inMonth = cell.inMonth;

          return (
            <div key={idx} style={{
              border:'1px solid var(--bp-border)',
              borderRadius:14,
              padding:10,
              minHeight:92,
              background: inMonth ? '#fff' : '#f8fafc'
            }}>
              <div style={{display:'flex', justifyContent:'space-between', alignItems:'center'}}>
                <div style={{fontWeight:950, opacity: inMonth ? 1 : 0.5}}>
                  {cell.date.getDate()}
                </div>
                <span style={{
                  fontSize:12, fontWeight:950, padding:'4px 8px', borderRadius:999,
                  background:'rgba(67,24,255,.10)', color:'#1e1b4b', border:'1px solid rgba(67,24,255,.15)'
                }}>
                  {list.length}
                </span>
              </div>

              {list.length ? (
                <div style={{marginTop:8, display:'grid', gap:6}}>
                  {list.slice(0,2).map(ev=>(
                    <button key={ev.id} type="button"
                      onClick={()=>onPickEvent(ev)}
                      style={{
                        padding:'6px 8px',
                        borderRadius:12,
                        border:'1px solid rgba(0,0,0,.06)',
                        background: statusBg(ev.status),
                        color: statusColor(ev.status),
                        fontWeight:950,
                        fontSize:12,
                        textAlign:'left',
                        cursor:'pointer'
                      }}>
                      {ev.start_time} • {ev.service_name}
                    </button>
                  ))}
                  {list.length > 2 ? (
                    <div style={{color:'var(--bp-muted)', fontWeight:900, fontSize:12}}>+ {list.length-2} more</div>
                  ) : null}
                </div>
              ) : (
                <div style={{marginTop:10, color:'var(--bp-muted)', fontWeight:850, fontSize:12}}>
                  No bookings
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

/* -------- Week View (UI shell) -------- */
function WeekView({ focusDate, events, onPickEvent, onReschedule }){
  const [drag, setDrag] = useState(null);
  const gridRef = React.useRef(null);
  const weekDates = useMemo(()=>{
    const d = new Date(focusDate);
    const dow = (d.getDay()+6)%7;
    const monday = new Date(d); monday.setDate(d.getDate()-dow);
    return Array.from({length:7}).map((_,i)=>{
      const x = new Date(monday); x.setDate(monday.getDate()+i);
      return {
        dateObj:x,
        key: toISODate(x),
        label: x.toLocaleDateString(undefined,{weekday:'short'}),
        md: x.toLocaleDateString(undefined,{month:'short',day:'numeric'})
      };
    });
  }, [focusDate]);

  const byDate = useMemo(()=>{
    const map = {};
    (events||[]).forEach(ev=>{
      if(!map[ev.date]) map[ev.date]=[];
      map[ev.date].push(ev);
    });
    return map;
  }, [events]);

  // UI config
  const dayStart = 8;   // 08:00
  const dayEnd   = 20;  // 20:00
  const pxPerMin = 1.1; // adjust: higher = taller grid
  const totalMinutes = (dayEnd - dayStart) * 60;

  const hours = Array.from({length: (dayEnd-dayStart)+1}).map((_,i)=> dayStart+i);

  useEffect(()=>{
    if(!drag) return;

    const step = 15;
    const dayStartMin = dayStart * 60;
    const dayEndMin = dayEnd * 60;

    function onMove(e){
      const el = gridRef.current;
      if(!el) return;

      const rect = el.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;

      const colWidth = rect.width / 7;
      const dayIdx = Math.max(0, Math.min(6, Math.floor(x / colWidth)));

      const total = (dayEndMin - dayStartMin);
      const px = (rect.height / total);
      let mins = dayStartMin + (y / px);
      mins = snapMinutes(mins, step);
      mins = Math.max(dayStartMin, Math.min(dayEndMin - drag.durationMin, mins));

      const dateKey = weekDates[dayIdx].key;

      setDrag(prev => prev ? ({...prev, ghost:{dateKey, minutes: mins}}) : prev);
    }

    async function onUp(){
      const final = drag?.ghost;
      if(!final){ setDrag(null); return; }

      try{
        const start_date = final.dateKey;
        const start_time = minutesToHHMM(final.minutes);

        await bpFetch(`/admin/bookings/${drag.id}`, {
          method:'PATCH',
          body:{ start_date, start_time }
        });

        if (onReschedule) onReschedule();
      }catch(err){
        alert(err.message || 'Reschedule failed');
      }finally{
        setDrag(null);
      }
    }

    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp, { once:true });

    return ()=>{
      window.removeEventListener('mousemove', onMove);
    };
  }, [drag, weekDates, dayStart, dayEnd, onReschedule]);

  return (
    <div style={{overflow:'auto'}}>
      {/* Header */}
      <div ref={gridRef} style={{display:'grid', gridTemplateColumns:`80px repeat(7, minmax(220px, 1fr))`, gap:10}}>
        <div />
        {weekDates.map((d, idx)=>(
          <div key={idx} style={{fontWeight:950}}>
            {d.label}
            <div style={{color:'var(--bp-muted)', fontWeight:850, fontSize:12}}>{d.md}</div>
          </div>
        ))}
      </div>

      <div style={{height:10}} />

      {/* Grid */}
      <div style={{display:'grid', gridTemplateColumns:`80px repeat(7, minmax(220px, 1fr))`, gap:10}}>
        {/* Time rail */}
        <div style={{position:'relative', height: totalMinutes*pxPerMin}}>
          {hours.map(h=>{
            const top = ((h - dayStart) * 60) * pxPerMin;
            return (
              <div key={h} style={{
                position:'absolute', left:0, right:0, top,
                transform:'translateY(-7px)',
                color:'var(--bp-muted)', fontWeight:950, fontSize:12
              }}>
                {String(h).padStart(2,'0')}:00
              </div>
            );
          })}
        </div>

        {/* Day columns */}
        {weekDates.map((d, idx)=>{
          const raw = byDate[d.key] || [];
          // keep only events intersecting the visible range
          const dayEvents = raw.map(e=>({...e})).map(e=>{
            const s = toMinutes(e.start_time);
            const en = toMinutes(e.end_time || e.start_time);
            return {
              ...e,
              start_time: e.start_time,
              end_time: e.end_time,
              _s: s,
              _e: Math.max(s+10, en)
            };
          }).filter(e=>{
            const minStart = dayStart*60;
            const minEnd = dayEnd*60;
            return !(e._e <= minStart || e._s >= minEnd);
          }).map(e=>{
            // clamp inside view window
            const minStart = dayStart*60;
            const minEnd = dayEnd*60;
            const s = clamp(e._s, minStart, minEnd);
            const en = clamp(e._e, minStart, minEnd);
            return {...e, start_time: minutesToHHMM(e._s), end_time: minutesToHHMM(e._e), _s:s, _e:en};
          });

          const laid = layoutDayOverlaps(dayEvents);

          return (
            <div key={idx} style={{
              position:'relative',
              height: totalMinutes*pxPerMin,
              background:'#fff',
              border:'1px solid var(--bp-border)',
              borderRadius:16,
              overflow:'hidden'
            }}>
              {/* hour lines */}
              {hours.map(h=>{
                const top = ((h - dayStart) * 60) * pxPerMin;
                return (
                  <div key={h} style={{
                    position:'absolute', left:0, right:0, top,
                    height:1, background:'#f1f5f9'
                  }} />
                );
              })}

              {/* events */}
              {laid.map(ev=>{
                const top = ((ev._s - dayStart*60) * pxPerMin) + 6;
                const height = Math.max(34, ((ev._e - ev._s) * pxPerMin) - 10);

                const gap = 6;
                const colW = (100 / (ev.colCount || 1));
                const left = (ev.col || 0) * colW;

                return (
                  <button
                    key={ev.id}
                    type="button"
                    onMouseDown={(e)=>{
                      e.preventDefault();
                      e.stopPropagation();

                      const durationMin = Math.max(10, (toMinutes(ev.end_time) - toMinutes(ev.start_time)) || 30);

                      setDrag({
                        id: ev.id,
                        durationMin,
                        ghost: { dateKey: d.key, minutes: ev._s }
                      });
                    }}
                    onClick={()=>onPickEvent(ev)}
                    title={ev.title}
                    style={{
                      position:'absolute',
                      top,
                      left: `calc(${left}% + ${gap}px)`,
                      width: `calc(${colW}% - ${gap*2}px)` ,
                      height,
                      borderRadius:14,
                      border:'1px solid rgba(0,0,0,.06)',
                      background: statusBg(ev.status),
                      color: statusColor(ev.status),
                      fontWeight:950,
                      cursor:'pointer',
                      padding:'10px 10px',
                      textAlign:'left',
                      overflow:'hidden'
                    }}
                  >
                    <div style={{fontSize:12, opacity:.95}}>
                      {ev.start_time}–{ev.end_time}
                    </div>
                    <div style={{marginTop:6, fontSize:12}}>
                      {ev.service_name}
                    </div>
                    <div style={{marginTop:6, fontSize:12, color:'rgba(15,23,42,.75)'}}>
                      {ev.customer_name}
                    </div>
                  </button>
                );
              })}

              {drag && drag.ghost?.dateKey === d.key ? (
                <div style={{
                  position:'absolute',
                  left: 8, right: 8,
                  top: ((drag.ghost.minutes - dayStart*60) * pxPerMin) + 6,
                  height: Math.max(34, drag.durationMin * pxPerMin - 10),
                  borderRadius:14,
                  border:'2px dashed rgba(67,24,255,.55)',
                  background:'rgba(67,24,255,.08)',
                  pointerEvents:'none'
                }} />
              ) : null}
            </div>
          );
        })}
      </div>
    </div>
  );
}

function minutesToHHMM(m){
  const h = Math.floor(m/60);
  const mm = m%60;
  return `${String(h).padStart(2,'0')}:${String(mm).padStart(2,'0')}`;
}

/* -------- Day View (UI shell) -------- */
function DayView({ focusDate, events, onPickEvent }){
  const key = toISODate(focusDate);
  const listRaw = useMemo(()=>{
    return (events||[]).filter(ev => ev.date === key).map(e=>({...e}));
  }, [events, key]);

  const dayStart = 8;
  const dayEnd = 20;
  const pxPerMin = 1.2;
  const totalMinutes = (dayEnd-dayStart)*60;
  const hours = Array.from({length:(dayEnd-dayStart)+1}).map((_,i)=> dayStart+i);

  const list = useMemo(()=>{
    const minStart = dayStart*60;
    const minEnd = dayEnd*60;
    const dayEvents = listRaw.map(e=>{
      const s = toMinutes(e.start_time);
      const en = Math.max(s+10, toMinutes(e.end_time || e.start_time));
      const _s = clamp(s, minStart, minEnd);
      const _e = clamp(en, minStart, minEnd);
      return {...e, _s, _e};
    }).filter(e=>!(e._e<=minStart || e._s>=minEnd));

    return layoutDayOverlaps(dayEvents);
  }, [listRaw]);

  const label = focusDate.toLocaleDateString(undefined, { weekday:'long', year:'numeric', month:'short', day:'numeric' });

  return (
    <div>
      <div style={{display:'flex', justifyContent:'space-between', alignItems:'center', gap:10, flexWrap:'wrap'}}>
        <div style={{fontWeight:950}}>{label}</div>
        <span className="bp-chip">{listRaw.length} bookings</span>
      </div>

      <div style={{height:10}} />

      <div style={{display:'grid', gridTemplateColumns:'80px 1fr', gap:10}}>
        {/* Time rail */}
        <div style={{position:'relative', height: totalMinutes*pxPerMin}}>
          {hours.map(h=>{
            const top = ((h-dayStart)*60)*pxPerMin;
            return (
              <div key={h} style={{
                position:'absolute', top, left:0, right:0,
                transform:'translateY(-7px)',
                color:'var(--bp-muted)', fontWeight:950, fontSize:12
              }}>
                {String(h).padStart(2,'0')}:00
              </div>
            );
          })}
        </div>

        {/* Day column */}
        <div style={{
          position:'relative',
          height: totalMinutes*pxPerMin,
          background:'#fff',
          border:'1px solid var(--bp-border)',
          borderRadius:16,
          overflow:'hidden'
        }}>
          {hours.map(h=>{
            const top = ((h-dayStart)*60)*pxPerMin;
            return <div key={h} style={{position:'absolute', left:0, right:0, top, height:1, background:'#f1f5f9'}} />;
          })}

          {list.map(ev=>{
            const top = ((ev._s - dayStart*60) * pxPerMin) + 6;
            const height = Math.max(34, ((ev._e - ev._s) * pxPerMin) - 10);

            const gap = 6;
            const colW = (100 / (ev.colCount || 1));
            const left = (ev.col || 0) * colW;

            return (
              <button
                key={ev.id}
                type="button"
                onClick={()=>onPickEvent(ev)}
                style={{
                  position:'absolute',
                  top,
                  left: `calc(${left}% + ${gap}px)` ,
                  width: `calc(${colW}% - ${gap*2}px)` ,
                  height,
                  borderRadius:14,
                  border:'1px solid rgba(0,0,0,.06)',
                  background: statusBg(ev.status),
                  color: statusColor(ev.status),
                  fontWeight:950,
                  cursor:'pointer',
                  padding:'10px 10px',
                  textAlign:'left',
                  overflow:'hidden'
                }}
              >
                <div style={{fontSize:12}}>
                  {ev.start_time}–{ev.end_time}
                </div>
                <div style={{marginTop:6, fontSize:12}}>
                  {ev.service_name}
                </div>
                <div style={{marginTop:6, fontSize:12, color:'rgba(15,23,42,.75)'}}>
                  {ev.customer_name}
                </div>
              </button>
            );
          })}
        </div>
      </div>
    </div>
  );
}

/* -------- Helpers -------- */
function formatHeader(d, view){
  const optMonth = { year:'numeric', month:'long' };
  const optDay = { weekday:'short', year:'numeric', month:'short', day:'numeric' };

  if(view === 'month') return d.toLocaleDateString(undefined, optMonth);
  if(view === 'day') return d.toLocaleDateString(undefined, optDay);

  // week label
  const week = buildWeekDays(d);
  return `${week[0].dateStr} – ${week[6].dateStr}`;
}

function buildMonthGrid(date){
  const d = new Date(date.getFullYear(), date.getMonth(), 1);
  const firstDow = (d.getDay() + 6) % 7; // monday=0
  const start = new Date(d);
  start.setDate(start.getDate() - firstDow);

  const cells = [];
  for(let i=0;i<42;i++){
    const c = new Date(start);
    c.setDate(start.getDate() + i);
    const inMonth = c.getMonth() === date.getMonth();
    cells.push({ date: c, inMonth });
  }
  return cells;
}

function buildWeekDays(date){
  const d = new Date(date);
  const dow = (d.getDay() + 6) % 7; // monday=0
  const monday = new Date(d);
  monday.setDate(d.getDate() - dow);

  const days = [];
  const labels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  for(let i=0;i<7;i++){
    const x = new Date(monday);
    x.setDate(monday.getDate() + i);
    days.push({
      label: labels[i],
      dateStr: x.toLocaleDateString(undefined, { month:'short', day:'numeric' })
    });
  }
  return days;
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

function toISODate(d){
  const x = new Date(d);
  const y = x.getFullYear();
  const m = String(x.getMonth()+1).padStart(2,'0');
  const day = String(x.getDate()).padStart(2,'0');
  return `${y}-${m}-${day}`;
}

function getRange(focusDate, view){
  if(view === 'day'){
    const s = new Date(focusDate);
    const e = new Date(focusDate);
    return { start: toISODate(s), end: toISODate(e) };
  }
  if(view === 'week'){
    const d = new Date(focusDate);
    const dow = (d.getDay()+6)%7; // monday=0
    const monday = new Date(d); monday.setDate(d.getDate()-dow);
    const sunday = new Date(monday); sunday.setDate(monday.getDate()+6);
    return { start: toISODate(monday), end: toISODate(sunday) };
  }
  const grid = buildMonthGrid(focusDate);
  return { start: toISODate(grid[0].date), end: toISODate(grid[grid.length-1].date) };
}
