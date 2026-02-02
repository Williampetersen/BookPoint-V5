import React, { useEffect, useMemo, useRef, useState } from "react";
import { bpFetch } from "../api/client";
import { bpEmit, bpOn } from "../lib/bpEvents";
import BookingDrawer from "../components/BookingDrawer";
import FullCalendar from "@fullcalendar/react";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listPlugin from "@fullcalendar/list";

function pad(n){ return String(n).padStart(2, "0"); }
function ymd(d){ return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`; }
function addDays(date, days){ const d = new Date(date); d.setDate(d.getDate()+days); return d; }
function startOfWeek(date){
  const d = new Date(date);
  const day = d.getDay();
  d.setDate(d.getDate() - day);
  d.setHours(0,0,0,0);
  return d;
}
function formatDateTime(d){ return `${ymd(d)} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`; }

export default function CalendarScreen(){
  function fmtDate(d){
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
  }

  const [isMobile, setIsMobile] = useState(() => window.innerWidth < 768);
  const [view, setView] = useState(() => (window.innerWidth < 768 ? "day" : "week"));
  const [cursor, setCursor] = useState(() => new Date());
  const [title, setTitle] = useState("");
  const calendarRef = useRef(null);
  const [events, setEvents] = useState([]);
  const [agents, setAgents] = useState([]);
  const [agentId, setAgentId] = useState(0);
  const [status, setStatus] = useState("all");
  const [query, setQuery] = useState("");
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");
  const [selectedBookingId, setSelectedBookingId] = useState(null);
  const [currentRange, setCurrentRange] = useState({ start: new Date(), end: addDays(new Date(), 30) });
  const [currentRangeStart, setCurrentRangeStart] = useState(new Date());
  const [currentRangeEnd, setCurrentRangeEnd] = useState(addDays(new Date(), 30));

  const [filtersOpen, setFiltersOpen] = useState(false);

  const [holidayOpen, setHolidayOpen] = useState(false);
  const [holidayForm, setHolidayForm] = useState({
    title: "",
    start_date: "",
    end_date: "",
    is_recurring_yearly: false,
    agent_id: 0,
    is_enabled: true
  });
  const [holidaySaving, setHolidaySaving] = useState(false);
  const [holidayErr, setHolidayErr] = useState("");

  async function loadEvents(startDate, endDate){
    setLoading(true);
    setErr("");
    try{
      const start = ymd(startDate);
      const end = ymd(addDays(endDate, -1));
      const agentParam = agentId ? `&agent_id=${encodeURIComponent(agentId)}` : "";
      const statusParam = status && status !== "all" ? `&status=${encodeURIComponent(status)}` : "";
      const qParam = query ? `&q=${encodeURIComponent(query)}` : "";
      const res = await bpFetch(`/admin/calendar?start=${start}&end=${end}${agentParam}${statusParam}${qParam}`);
      setEvents(res?.data || []);
    }catch(e){
      setEvents([]);
      setErr(e.message || "Failed to load calendar");
    }finally{
      setLoading(false);
    }
  }

  useEffect(() => {
    if (currentRange?.start && currentRange?.end) {
      loadEvents(currentRange.start, currentRange.end);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [agentId, status, query]);

  useEffect(() => {
    const unsubscribe = bpOn("booking_updated", () => {
      if (currentRange?.start && currentRange?.end) {
        loadEvents(currentRange.start, currentRange.end);
      }
    });
    return unsubscribe;
  }, [currentRange]);

  useEffect(() => {
    (async () => {
      try {
        const res = await bpFetch("/admin/agents");
        setAgents(res?.data || []);
      } catch (e) {
        setAgents([]);
      }
    })();
  }, []);

  useEffect(() => {
    const onResize = () => {
      const mobile = window.innerWidth < 768;
      setIsMobile(mobile);
      if (mobile && view === "week") setView("day");
    };
    window.addEventListener("resize", onResize);
    return () => window.removeEventListener("resize", onResize);
  }, [view]);

  function titleText(){ return title || cursor.toLocaleDateString(); }
  function prev(){ calendarRef.current?.getApi().prev(); }
  function next(){ calendarRef.current?.getApi().next(); }
  function today(){ calendarRef.current?.getApi().today(); }

  const filteredEvents = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return events;
    return events.filter((ev) => {
      const hay = `${ev.title || ""} ${ev.customer_name || ""} ${ev.customer_email || ""} ${ev.agent_name || ""}`.toLowerCase();
      return hay.includes(q);
    });
  }, [events, query]);

  const calendarEvents = useMemo(() => {
    return filteredEvents.map((ev) => {
      const status = (ev.status || "pending").toLowerCase();
      return {
        id: String(ev.id),
        title: `${ev.service_name || "Service"} * ${ev.customer_name || "Customer"}`,
        start: ev.start,
        end: ev.end,
        classNames: [`bp-evt-${status}`],
        extendedProps: ev,
      };
    });
  }, [filteredEvents]);

  const viewMap = {
    month: "dayGridMonth",
    week: "timeGridWeek",
    day: "timeGridDay",
    list: "listWeek",
  };

  useEffect(() => {
    const api = calendarRef.current?.getApi();
    if (!api) return;
    api.changeView(viewMap[view]);
  }, [view]);

  function handleEventClick(info){
    const id = info?.event?.id;
    if (!id) return;
    setSelectedBookingId(id);
  }

  async function handleEventDrop(info){
    const id = info.event.id;
    const start = info.event.start;
    const slotMinutes = 30;
    const end = info.event.end ? info.event.end : new Date(start.getTime() + slotMinutes * 60000);

    const startStr = formatDateTime(start);
    const endStr = formatDateTime(end);

    try{
      await bpFetch(`/admin/bookings/${id}/reschedule`, {
        method: "POST",
        body: { start_datetime: startStr, end_datetime: endStr },
      });
      bpEmit("booking_updated", { id });
      await loadEvents(currentRange.start, currentRange.end);
    }catch(e){
      info.revert();
      setErr(e.message || "Reschedule failed");
    }
  }

  function handleDatesSet(info){
    setCurrentRange({ start: info.start, end: info.end });
    setCurrentRangeStart(info.start);
    setCurrentRangeEnd(info.end);
    setTitle(info.view?.title || "");
    setCursor(info.start);
    loadEvents(info.start, info.end);
  }

  async function saveHolidayFromModal(){
    setHolidaySaving(true);
    setHolidayErr("");

    try{
      if(!holidayForm.title.trim()) throw new Error("Title is required");
      if(!holidayForm.start_date || !holidayForm.end_date) throw new Error("Start and end dates are required");

      await bpFetch(`/admin/holidays`, {
        method: "POST",
        body: JSON.stringify({
          title: holidayForm.title,
          start_date: holidayForm.start_date,
          end_date: holidayForm.end_date,
          is_recurring_yearly: !!holidayForm.is_recurring_yearly,
          is_enabled: holidayForm.is_enabled !== false,
          agent_id: Number(holidayForm.agent_id || 0)
        })
      });

      setHolidayOpen(false);
      await loadEvents(currentRangeStart, currentRangeEnd);

    }catch(e){
      setHolidayErr(e?.message || "Failed to save holiday");
    }finally{
      setHolidaySaving(false);
    }
  }

  const weekStart = startOfWeek(cursor);
  const weekDays = Array.from({ length: 7 }).map((_, i) => addDays(weekStart, i));

  return (
    <div className="myplugin-page bp-calendar">
      <main className="myplugin-content">
      <div className="bp-cal-top">
        <div className="bp-cal-title">
          <div className="bp-cal-year">{cursor.getFullYear()}</div>
          <div className="bp-cal-title-text">{titleText()}</div>
        </div>

        <div className="bp-cal-controls">
          <div className="bp-cal-pill">
            <button className={`bp-cal-tab ${view==="day"?"active":""}`} onClick={()=>setView("day")}>Day</button>
            <button className={`bp-cal-tab ${view==="week"?"active":""}`} onClick={()=>setView("week")}>Week</button>
            <button className={`bp-cal-tab ${view==="month"?"active":""}`} onClick={()=>setView("month")}>Month</button>
            <button className={`bp-cal-tab ${view==="list"?"active":""}`} onClick={()=>setView("list")}>List</button>
          </div>

          <div className="bp-cal-pill">
            <button className="bp-cal-btn" onClick={today}>Today</button>
            <button className="bp-cal-icon" onClick={prev} aria-label="Previous">&lt;</button>
            <button className="bp-cal-icon" onClick={next} aria-label="Next">&gt;</button>
          </div>

          <div className="bp-cal-pill">
            <button className="bp-cal-btn" onClick={()=>setFiltersOpen(true)}>Filters</button>
          </div>

          <button className="bp-primary-btn bp-cal-cta" onClick={()=>alert("Next: open booking wizard")}>+ Booking</button>
        </div>
      </div>

      {isMobile && (
        <div className="bp-cal-weekstrip">
          {weekDays.map((d) => {
            const isActive = ymd(d) === ymd(cursor);
            return (
              <button
                key={ymd(d)}
                className={`bp-cal-day ${isActive ? "active" : ""}`}
                onClick={() => calendarRef.current?.getApi().gotoDate(d)}
              >
                <div className="bp-cal-day-dow">{d.toLocaleDateString(undefined, { weekday: "short" })}</div>
                <div className="bp-cal-day-num">{d.getDate()}</div>
              </button>
            );
          })}
        </div>
      )}

      {err ? <div className="bp-error">{err}</div> : null}

      <div className="bp-card bp-cal-panel">
        {loading ? <div className="bp-cal-loading">Loading...</div> : null}
        <FullCalendar
          ref={calendarRef}
          plugins={[dayGridPlugin, timeGridPlugin, listPlugin]}
          initialView={viewMap[view]}
          headerToolbar={false}
          height="auto"
          editable={true}
          eventDurationEditable={false}
          eventDrop={handleEventDrop}
          eventClick={handleEventClick}
          datesSet={handleDatesSet}
          events={calendarEvents}
          selectable={true}
          selectMirror={true}
          unselectAuto={true}
          select={(info)=>{
            const start = fmtDate(info.start);
            const endExclusive = info.end;
            const endDate = new Date(endExclusive.getTime() - 86400000);
            const end = fmtDate(endDate);

            setHolidayErr("");
            setHolidayForm(f => ({
              ...f,
              title: "Holiday",
              start_date: start,
              end_date: end,
              agent_id: agentId || 0,
              is_recurring_yearly: false,
              is_enabled: true
            }));
            setHolidayOpen(true);
          }}
        />
      </div>

      {filtersOpen && (
        <div className="bp-cal-sheet" onMouseDown={(e)=>{ if(e.target.classList.contains("bp-cal-sheet")) setFiltersOpen(false); }}>
          <div className="bp-cal-sheet-card">
            <div className="bp-cal-sheet-head">
              <div className="bp-h2">Filters</div>
              <button className="bp-top-btn" onClick={()=>setFiltersOpen(false)}>Close</button>
            </div>
            <div className="bp-cal-sheet-body">
              <select className="bp-input" value={agentId} onChange={(e)=>setAgentId(parseInt(e.target.value,10)||0)}>
                <option value={0}>All agents</option>
                {agents.map(a => (
                  <option key={a.id} value={a.id}>{a.name || `${a.first_name || ""} ${a.last_name || ""}`.trim() || `#${a.id}`}</option>
                ))}
              </select>
              <select className="bp-input" value={status} onChange={(e)=>setStatus(e.target.value)}>
                <option value="all">All status</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="cancelled">Cancelled</option>
                <option value="completed">Completed</option>
              </select>
              <input
                className="bp-input"
                placeholder="Search..."
                value={query}
                onChange={(e) => setQuery(e.target.value)}
              />
            </div>
          </div>
        </div>
      )}

      {selectedBookingId ? (
        <DrawerErrorBoundary onClose={() => setSelectedBookingId(null)}>
          <BookingDrawer
            bookingId={selectedBookingId}
            onClose={() => setSelectedBookingId(null)}
            onUpdated={() => {
              bpEmit("booking_updated", { id: selectedBookingId });
              loadEvents(currentRange.start, currentRange.end);
            }}
          />
        </DrawerErrorBoundary>
      ) : null}

      {holidayOpen ? (
        <div className="bp-modal-wrap" onMouseDown={(e)=>{ if(e.target.classList.contains("bp-modal-wrap")) setHolidayOpen(false); }}>
          <div className="bp-modal">
            <div className="bp-modal-head">
              <div>
                <div className="bp-modal-title">Add Holiday</div>
                <div className="bp-muted">{holidayForm.start_date} -> {holidayForm.end_date}</div>
              </div>
              <button className="bp-top-btn" onClick={()=>setHolidayOpen(false)}>Close</button>
            </div>

            {holidayErr ? <div className="bp-error" style={{marginTop:10}}>{holidayErr}</div> : null}

            <div style={{ display:"grid", gap:10, marginTop:12 }}>
              <div>
                <div className="bp-k" style={{ marginBottom: 6 }}>Title</div>
                <input
                  className="bp-input"
                  value={holidayForm.title}
                  onChange={(e)=>setHolidayForm({...holidayForm, title:e.target.value})}
                  placeholder="e.g. Christmas"
                />
              </div>

              <div style={{ display:"grid", gridTemplateColumns:"1fr 1fr", gap:10 }}>
                <div>
                  <div className="bp-k" style={{ marginBottom: 6 }}>Start date</div>
                  <input
                    className="bp-input"
                    type="date"
                    value={holidayForm.start_date}
                    onChange={(e)=>setHolidayForm({...holidayForm, start_date:e.target.value})}
                  />
                </div>
                <div>
                  <div className="bp-k" style={{ marginBottom: 6 }}>End date</div>
                  <input
                    className="bp-input"
                    type="date"
                    value={holidayForm.end_date}
                    onChange={(e)=>setHolidayForm({...holidayForm, end_date:e.target.value})}
                  />
                </div>
              </div>

              <div style={{ display:"grid", gridTemplateColumns:"1fr 1fr", gap:10, alignItems:"end" }}>
                <div>
                  <div className="bp-k" style={{ marginBottom: 6 }}>Scope</div>
                  <select
                    className="bp-input"
                    value={holidayForm.agent_id}
                    onChange={(e)=>setHolidayForm({...holidayForm, agent_id:Number(e.target.value)})}
                  >
                    <option value={0}>Global (all agents)</option>
                    {agents.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
                  </select>
                </div>

                <label className="bp-check" style={{ paddingBottom: 6 }}>
                  <input
                    type="checkbox"
                    checked={holidayForm.is_recurring_yearly || false}
                    onChange={(e)=>setHolidayForm({...holidayForm, is_recurring_yearly:e.target.checked})}
                  />
                  <span>Repeat yearly</span>
                </label>
              </div>

              <div style={{ display:"flex", justifyContent:"space-between", alignItems:"center", marginTop: 6 }}>
                <label className="bp-check">
                  <input
                    type="checkbox"
                    checked={holidayForm.is_enabled !== false}
                    onChange={(e)=>setHolidayForm({...holidayForm, is_enabled:e.target.checked})}
                  />
                  <span>Enabled</span>
                </label>

                <div style={{ display:"flex", gap:10 }}>
                  <button className="bp-btn bp-btn-ghost" onClick={()=>setHolidayOpen(false)}>Cancel</button>
                  <button className="bp-btn" disabled={holidaySaving} onClick={saveHolidayFromModal}>
                    {holidaySaving ? "Saving..." : "Save Holiday"}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      ) : null}
      </main>
    </div>
  );
}

class DrawerErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, message: "" };
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, message: error?.message || "Unexpected error" };
  }

  componentDidCatch(error, info) {
    console.error("Booking drawer crashed", error, info);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="bp-error" style={{ marginTop: 12 }}>
          Failed to open booking details. {this.state.message}
          <button
            className="bp-btn"
            style={{ marginLeft: 10 }}
            onClick={this.props.onClose}
          >
            Close
          </button>
        </div>
      );
    }
    return this.props.children;
  }
}
