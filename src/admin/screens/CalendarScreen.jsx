import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";

function pad(n){ return String(n).padStart(2,'0'); }
function ymd(d){ return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`; }
function addDays(date, days){ const d=new Date(date); d.setDate(d.getDate()+days); return d; }

function monthRange(date){
  const first = new Date(date.getFullYear(), date.getMonth(), 1);
  const start = addDays(first, -first.getDay()); // sunday start
  const last = new Date(date.getFullYear(), date.getMonth()+1, 0);
  const end = addDays(last, 6-last.getDay());
  return { start, end };
}

function statusBadge(status){
  const s = (status||'pending').toLowerCase();
  return <span className={`bp-badge ${s}`}>{s}</span>;
}

export default function CalendarScreen(){
  const [view, setView] = useState("month"); // month | week | day
  const [cursor, setCursor] = useState(()=> new Date());
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");
  const [drawer, setDrawer] = useState(null);

  const range = useMemo(()=>{
    if(view==="month") return monthRange(cursor);
    if(view==="week"){
      const d = new Date(cursor);
      const start = addDays(d, -d.getDay());
      const end = addDays(start, 6);
      return { start, end };
    }
    // day
    return { start: new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate()), end: new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate()) };
  }, [view, cursor]);

  async function load(){
    setLoading(true);
    setErr("");
    try{
      const start = ymd(range.start);
      const end = ymd(range.end);
      const res = await bpFetch(`/admin/calendar?start=${start}&end=${end}`);
      setEvents(res?.data || []);
    }catch(e){
      setEvents([]);
      setErr(e.message || "Failed to load calendar");
    }finally{
      setLoading(false);
    }
  }

  useEffect(()=>{ load(); /* eslint-disable-next-line */ }, [view, cursor]);

  function titleText(){
    const m = cursor.toLocaleString(undefined, { month:'long', year:'numeric' });
    if(view==="month") return m;
    if(view==="week"){
      const s = range.start.toLocaleDateString();
      const e = range.end.toLocaleDateString();
      return `Week • ${s} – ${e}`;
    }
    return `Day • ${cursor.toLocaleDateString()}`;
  }

  function prev(){
    if(view==="month") setCursor(new Date(cursor.getFullYear(), cursor.getMonth()-1, 1));
    else if(view==="week") setCursor(addDays(cursor, -7));
    else setCursor(addDays(cursor, -1));
  }
  function next(){
    if(view==="month") setCursor(new Date(cursor.getFullYear(), cursor.getMonth()+1, 1));
    else if(view==="week") setCursor(addDays(cursor, 7));
    else setCursor(addDays(cursor, 1));
  }
  function today(){ setCursor(new Date()); }

  // Build month grid cells
  const cells = useMemo(()=>{
    if(view!=="month") return [];
    const { start, end } = range;
    const arr = [];
    let d = new Date(start);
    while(d <= end){
      arr.push(new Date(d));
      d = addDays(d, 1);
    }
    return arr;
  }, [view, range]);

  const eventsByDay = useMemo(()=>{
    const map = new Map();
    for(const ev of events){
      const day = (ev.start || "").slice(0,10);
      if(!map.has(day)) map.set(day, []);
      map.get(day).push(ev);
    }
    return map;
  }, [events]);

  return (
    <div>
      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Calendar</div>
          <div className="bp-muted">{titleText()}</div>
        </div>

        <div className="bp-head-actions">
          <button className="bp-top-btn" onClick={today}>Today</button>
          <button className="bp-top-btn" onClick={prev}>←</button>
          <button className="bp-top-btn" onClick={next}>→</button>

          <div className="bp-seg">
            <button className={`bp-seg-btn ${view==="month"?"active":""}`} onClick={()=>setView("month")}>Month</button>
            <button className={`bp-seg-btn ${view==="week"?"active":""}`} onClick={()=>setView("week")}>Week</button>
            <button className={`bp-seg-btn ${view==="day"?"active":""}`} onClick={()=>setView("day")}>Day</button>
          </div>

          <button className="bp-primary-btn" onClick={()=>alert("Next: open booking wizard")}>
            + New booking
          </button>
        </div>
      </div>

      {err ? <div className="bp-error">{err}</div> : null}

      <div className="bp-card" style={{padding:0}}>
        {loading ? <div style={{padding:14, fontWeight:900}}>Loading…</div> : null}

        {!loading && view === "month" ? (
          <div className="bp-cal-month">
            <div className="bp-cal-dow">Sun</div>
            <div className="bp-cal-dow">Mon</div>
            <div className="bp-cal-dow">Tue</div>
            <div className="bp-cal-dow">Wed</div>
            <div className="bp-cal-dow">Thu</div>
            <div className="bp-cal-dow">Fri</div>
            <div className="bp-cal-dow">Sat</div>

            {cells.map((d) => {
              const dayKey = ymd(d);
              const inMonth = d.getMonth() === cursor.getMonth();
              const dayEvents = eventsByDay.get(dayKey) || [];
              return (
                <div key={dayKey} className={`bp-cal-cell ${inMonth ? "" : "muted"}`}>
                  <div className="bp-cal-date">{d.getDate()}</div>
                  <div className="bp-cal-events">
                    {dayEvents.slice(0,3).map(ev=>(
                      <button key={ev.id} className={`bp-cal-ev ${ev.status||"pending"}`} onClick={()=>setDrawer(ev)}>
                        <span className="bp-cal-ev-dot" />
                        <span className="bp-cal-ev-title">{ev.title}</span>
                      </button>
                    ))}
                    {dayEvents.length > 3 ? (
                      <div className="bp-cal-more">+{dayEvents.length-3} more</div>
                    ) : null}
                  </div>
                </div>
              );
            })}
          </div>
        ) : null}

        {!loading && view !== "month" ? (
          <div style={{padding:14}}>
            <div style={{fontWeight:1000, marginBottom:10}}>Events</div>
            {events.length === 0 ? <div className="bp-muted">No bookings in this range.</div> : null}
            <div className="bp-list">
              {events.map(ev=>(
                <button key={ev.id} className="bp-list-row" onClick={()=>setDrawer(ev)}>
                  <div className="bp-list-main">
                    <div className="bp-list-title">{ev.title}</div>
                    <div className="bp-muted">
                      {ev.start} → {ev.end} {ev.agent_name ? ` • ${ev.agent_name}` : ""}
                    </div>
                  </div>
                  {statusBadge(ev.status)}
                </button>
              ))}
            </div>
          </div>
        ) : null}
      </div>

      {drawer ? (
        <div className="bp-drawer-wrap" onMouseDown={(e)=>{ if(e.target.classList.contains("bp-drawer-wrap")) setDrawer(null); }}>
          <div className="bp-drawer">
            <div className="bp-drawer-head">
              <div>
                <div className="bp-drawer-title">Booking #{drawer.id}</div>
                <div className="bp-muted">{drawer.start} → {drawer.end}</div>
              </div>
              <button className="bp-top-btn" onClick={()=>setDrawer(null)}>Close</button>
            </div>

            <div className="bp-drawer-body">
              <div className="bp-row"><div className="bp-k">Status</div><div className="bp-v">{statusBadge(drawer.status)}</div></div>
              <div className="bp-row"><div className="bp-k">Service</div><div className="bp-v">{drawer.service_name || "-"}</div></div>
              <div className="bp-row"><div className="bp-k">Agent</div><div className="bp-v">{drawer.agent_name || "-"}</div></div>
              <div className="bp-row"><div className="bp-k">Customer</div><div className="bp-v">{drawer.customer_name || "-"}</div></div>
              <div className="bp-row"><div className="bp-k">Email</div><div className="bp-v">{drawer.customer_email || "-"}</div></div>

              <div style={{height:12}} />

              <div className="bp-drawer-actions">
                <button className="bp-top-btn" onClick={()=>alert("Next: reschedule UI")}>Reschedule</button>
                <button className="bp-top-btn" onClick={()=>alert("Next: confirm booking")}>Confirm</button>
                <button className="bp-top-btn" onClick={()=>alert("Next: cancel booking")}>Cancel</button>
              </div>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
