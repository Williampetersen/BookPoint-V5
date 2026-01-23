import React, { useEffect, useMemo, useRef, useState } from "react";
import { bpFetch } from "../api/client";
import { bpEmit, bpOn } from "../lib/bpEvents";
import BookingDrawer from "../components/BookingDrawer";
import FullCalendar from "@fullcalendar/react";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";

function pad(n){ return String(n).padStart(2,'0'); }
function ymd(d){ return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`; }
function addDays(date, days){ const d=new Date(date); d.setDate(d.getDate()+days); return d; }
function formatDateTime(d){ return `${ymd(d)} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`; }

function monthRange(date){
  const first = new Date(date.getFullYear(), date.getMonth(), 1);
  const start = addDays(first, -first.getDay()); // sunday start
  const last = new Date(date.getFullYear(), date.getMonth()+1, 0);
  const end = addDays(last, 6-last.getDay());
  return { start, end };
}

export default function CalendarScreen(){
  const [view, setView] = useState("month"); // month | week | day
  const [cursor, setCursor] = useState(()=> new Date());
  const [title, setTitle] = useState("");
  const calendarRef = useRef(null);
  const [events, setEvents] = useState([]);
  const [agents, setAgents] = useState([]);
  const [agentId, setAgentId] = useState(0);
  const [status, setStatus] = useState("all");
  const [query, setQuery] = useState("");
  const [showHolidayModal, setShowHolidayModal] = useState(false);
  const [holidayDates, setHolidayDates] = useState({ start: "", end: "" });
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");
  const [selectedBookingId, setSelectedBookingId] = useState(null);
  const [drawer, setDrawer] = useState(null);
  const [drawerLoading, setDrawerLoading] = useState(false);
  const [drawerErr, setDrawerErr] = useState("");
  const [currentRange, setCurrentRange] = useState({ start: new Date(), end: addDays(new Date(), 30) });

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

  async function openBookingDrawer(id){
    setDrawerLoading(true);
    setDrawerErr("");
    setDrawer({ id, _loading: true });

    try{
      const res = await bpFetch(`/admin/bookings/${id}`);
      const payload =
        res?.data?.booking ? res.data :
        res?.booking ? res :
        res?.data ? res.data :
        res;

      setDrawer(payload);
    }catch(e){
      setDrawerErr(e?.message || "Failed to load booking details");
    }finally{
      setDrawerLoading(false);
    }
  }

  useEffect(() => {
    if (currentRange?.start && currentRange?.end) {
      loadEvents(currentRange.start, currentRange.end);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [agentId, status, query]);

  useEffect(() => {
    const unsubscribe = bpOn('booking_updated', (payload) => {
      if (currentRange?.start && currentRange?.end) {
        loadEvents(currentRange.start, currentRange.end);
      }
    });
    return unsubscribe;
  }, [currentRange]);

  useEffect(() => {
    (async () => {
      try {
        const res = await bpFetch('/admin/agents');
        setAgents(res?.data || []);
      } catch (e) {
        setAgents([]);
      }
    })();
  }, []);

  function titleText(){ return title || cursor.toLocaleDateString(); }

  function prev(){ calendarRef.current?.getApi().prev(); }
  function next(){ calendarRef.current?.getApi().next(); }
  function today(){ calendarRef.current?.getApi().today(); }

  const filteredEvents = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return events;
    return events.filter((ev) => {
      const hay = `${ev.title || ''} ${ev.customer_name || ''} ${ev.customer_email || ''} ${ev.agent_name || ''}`.toLowerCase();
      return hay.includes(q);
    });
  }, [events, query]);

  const calendarEvents = useMemo(() => {
    return filteredEvents.map((ev) => {
      const status = (ev.status || 'pending').toLowerCase();
      return {
        id: String(ev.id),
        title: `${ev.service_name || 'Service'} • ${ev.customer_name || 'Customer'}`,
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
  };

  useEffect(() => {
    const api = calendarRef.current?.getApi();
    if (!api) return;
    api.changeView(viewMap[view]);
  }, [view]);

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
      bpEmit('booking_updated', { id });
      await loadEvents(currentRange.start, currentRange.end);
    }catch(e){
      info.revert();
      setErr(e.message || "Reschedule failed");
    }
  }

  function handleEventClick(info){
    openBookingDrawer(info.event.id);
  }

  function handleDatesSet(info){
    setCurrentRange({ start: info.start, end: info.end });
    setTitle(info.view?.title || "");
    setCursor(info.start);
    loadEvents(info.start, info.end);
  }

  return (
    <div>
      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Calendar</div>
          <div className="bp-muted">{titleText()}</div>
        </div>

        <div className="bp-head-actions" style={{display:'flex', gap:10, alignItems:'center', flexWrap:'wrap', justifyContent:'space-between'}}>
          <div style={{display:'flex', gap:10, alignItems:'center'}}>
            <button className="bp-top-btn" onClick={today}>Today</button>
            <button className="bp-top-btn" onClick={prev}>←</button>
            <button className="bp-top-btn" onClick={next}>→</button>

            <div className="bp-seg">
              <button className={`bp-seg-btn ${view==="month"?"active":""}`} onClick={()=>setView("month")}>Month</button>
              <button className={`bp-seg-btn ${view==="week"?"active":""}`} onClick={()=>setView("week")}>Week</button>
              <button className={`bp-seg-btn ${view==="day"?"active":""}`} onClick={()=>setView("day")}>Day</button>
            </div>
          </div>

          <div style={{display:'flex', gap:10, alignItems:'center', flex:1, justifyContent:'flex-end', minWidth:300}}>
            <select className="bp-input" value={agentId} onChange={(e)=>setAgentId(parseInt(e.target.value,10)||0)}>
              <option value={0}>All agents</option>
              {agents.map(a => (
                <option key={a.id} value={a.id}>{a.name || `${a.first_name || ''} ${a.last_name || ''}`.trim() || `#${a.id}`}</option>
              ))}
            </select>

            <select className="bp-input" value={status} onChange={(e)=>setStatus(e.target.value)}>
              <option value="all">All status</option>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="cancelled">Cancelled</option>
            </select>

            <input
              className="bp-input"
              style={{ minWidth: 200 }}
              placeholder="Search…"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
            />

            <button className="bp-primary-btn" onClick={()=>alert("Next: open booking wizard")}>
              + New booking
            </button>
          </div>
        </div>
      </div>

      {err ? <div className="bp-error">{err}</div> : null}

      <div className="bp-card" style={{padding:0}}>
        {loading ? <div style={{padding:14, fontWeight:900}}>Loading…</div> : null}
        <FullCalendar
          ref={calendarRef}
          plugins={[dayGridPlugin, timeGridPlugin]}
          initialView="dayGridMonth"
          headerToolbar={false}
          height="auto"
          editable={true}
          eventDurationEditable={false}
          eventDrop={handleEventDrop}
          eventClick={handleEventClick}
          datesSet={handleDatesSet}
          events={calendarEvents}
        />
      </div>

      {selectedBookingId ? (
        <BookingDrawer
          bookingId={selectedBookingId}
          onClose={() => setSelectedBookingId(null)}
          onUpdated={(data) => {
            bpEmit('booking_updated', { id: selectedBookingId });
            loadEvents(currentRange.start, currentRange.end);
          }}
        />
      ) : null}

      {drawer ? (
        <div className="bp-drawer-wrap" onMouseDown={(e)=>{ if(e.target.classList.contains("bp-drawer-wrap")) setDrawer(null); }}>
          <div className="bp-drawer">
            <div className="bp-drawer-head">
              <div>
                <div className="bp-drawer-title">Booking #{drawer?.booking?.id || drawer?.id}</div>
                <div className="bp-muted">
                  {drawer?.booking?.start_datetime || drawer?.start || "-"} → {drawer?.booking?.end_datetime || drawer?.end || "-"}
                </div>
              </div>
              <button className="bp-top-btn" onClick={()=>setDrawer(null)}>Close</button>
            </div>

            {drawerErr ? <div className="bp-error">{drawerErr}</div> : null}
            {drawerLoading ? <div className="bp-muted" style={{padding:12}}>Loading…</div> : null}

            {!drawerLoading && !drawerErr ? (
              <div className="bp-drawer-body">
                <div className="bp-drawer-grid">
                  {(() => {
                    const booking = drawer?.booking || drawer || {};
                    const customer = drawer?.customer || {};
                    const service = drawer?.service || {};
                    const agent = drawer?.agent || {};
                    const pricing = drawer?.pricing || {};
                    const answers = drawer?.answers || {};
                    const fieldDefs = drawer?.field_defs || drawer?.form_fields || [];

                    return (
                      <>
                        <div className="bp-section">
                          <div className="bp-section-title">Customer</div>
                          <div className="bp-kv">
                            <div className="bp-k">Name</div><div className="bp-v">{customer.name || "-"}</div>
                            <div className="bp-k">Email</div><div className="bp-v">{customer.email || "-"}</div>
                            <div className="bp-k">Phone</div><div className="bp-v">{customer.phone || "-"}</div>
                          </div>
                        </div>

                        <div className="bp-section">
                          <div className="bp-section-title">Service</div>
                          <div className="bp-kv">
                            <div className="bp-k">Service</div><div className="bp-v">{service.name || "-"}</div>
                            <div className="bp-k">Agent</div><div className="bp-v">{agent.name || "-"}</div>
                          </div>
                        </div>

                        <div className="bp-section">
                          <div className="bp-section-title">Pricing</div>
                          <div className="bp-kv">
                            <div className="bp-k">Total</div><div className="bp-v">{pricing.total ?? "-"}</div>
                            <div className="bp-k">Promo</div><div className="bp-v">{pricing.promo_code || "-"}</div>
                          </div>
                        </div>

                        <div className="bp-section">
                          <div className="bp-section-title">Status</div>
                          <div className="bp-v">{booking.status || "-"}</div>
                        </div>

                        {Object.keys(answers || {}).length > 0 ? (
                          <div className="bp-section">
                            <div className="bp-section-title">Form Responses</div>
                            <div className="bp-kv">
                              {Object.keys(answers).map((k)=>{
                                const def = (fieldDefs||[]).find(d => d.key === k);
                                const label = def?.label || k;
                                const val = Array.isArray(answers[k]) ? answers[k].join(", ") : answers[k];
                                return (
                                  <React.Fragment key={k}>
                                    <div className="bp-k">{label}</div>
                                    <div className="bp-v">{val === "" || val == null ? "-" : String(val)}</div>
                                  </React.Fragment>
                                );
                              })}
                            </div>
                          </div>
                        ) : null}
                      </>
                    );
                  })()}
                </div>
              </div>
            ) : null}
          </div>
        </div>
      ) : null}
    </div>
  );
}
