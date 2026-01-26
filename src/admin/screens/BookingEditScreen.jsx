import React, { useEffect, useMemo, useState } from "react";
import { bpFetch, bpPost } from "../api/client";

function toDateInput(mysqlOrIso){
  if(!mysqlOrIso) return "";
  const s = String(mysqlOrIso).replace("T"," ").slice(0,19);
  return s.slice(0,10);
}
function toTimeInput(mysqlOrIso){
  if(!mysqlOrIso) return "";
  const s = String(mysqlOrIso).replace("T"," ").slice(0,19);
  return s.slice(11,16);
}
function mysqlFromDateTime(dateStr, timeStr){
  return `${dateStr} ${timeStr}:00`;
}
function mysqlFromDateObj(d){
  if (!(d instanceof Date) || isNaN(d.getTime())) return "";
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  const hh = String(d.getHours()).padStart(2, "0");
  const mm = String(d.getMinutes()).padStart(2, "0");
  return `${y}-${m}-${day} ${hh}:${mm}:00`;
}
function normalizeSlots(slots){
  if (!Array.isArray(slots)) return [];
  return slots.map((s) => {
    if (typeof s === "string") return s;
    if (typeof s === "number") return String(s);
    if (s && typeof s === "object") {
      return String(s.time || s.start_time || s.start || s.label || s.value || "");
    }
    return "";
  }).filter(Boolean);
}

export default function BookingEditScreen(){
  const id = useMemo(() => {
    const p = new URLSearchParams(window.location.search);
    return parseInt(p.get("id") || "0", 10);
  }, []);

  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState("");
  const [data, setData] = useState(null);

  const [status, setStatus] = useState("pending");
  const [adminNotes, setAdminNotes] = useState("");
  const [summarySaving, setSummarySaving] = useState(false);
  const [summaryMsg, setSummaryMsg] = useState("");
  const [summary, setSummary] = useState({
    customer_name: "",
    customer_email: "",
    customer_phone: "",
    agent_id: 0,
  });
  const [agentsList, setAgentsList] = useState([]);

  const [rsDate, setRsDate] = useState("");
  const [rsTime, setRsTime] = useState("");
  const [rsSlots, setRsSlots] = useState([]);
  const [rsLoading, setRsLoading] = useState(false);
  const [rsSaving, setRsSaving] = useState(false);
  const [rsErr, setRsErr] = useState("");
  const [rsOk, setRsOk] = useState("");

  useEffect(() => {
    if (!id) return;
    (async () => {
      setLoading(true);
      setErr("");
      try{
        const res = await bpFetch(`/admin/bookings/${id}`);
        const d = normalizeBookingResponse(res);
        setData(d);
        const booking = d?.booking || d || {};
        const cust = d?.customer || {};
        setStatus(booking?.status || "pending");
        setAdminNotes(booking?.admin_notes || "");
        setRsDate(toDateInput(booking.start_datetime || booking.start));
        setRsTime(toTimeInput(booking.start_datetime || booking.start));
        setRsSlots([]);
        setRsErr("");
        setRsOk("");
        setSummary({
          customer_name: cust.name || booking.customer_name || "",
          customer_email: cust.email || booking.customer_email || "",
          customer_phone: cust.phone || booking.customer_phone || "",
          agent_id: (d?.agent?.id) || booking.agent_id || 0,
        });
        setSummaryMsg("");
      }catch(e){
        setErr(e?.message || "Failed to load booking");
      }finally{
        setLoading(false);
      }
    })();
  }, [id]);

  useEffect(() => {
    let alive = true;
    (async () => {
      try{
        const res = await bpFetch("/admin/agents");
        const list = res?.data || res?.items || res?.data?.items || [];
        if (alive) setAgentsList(list);
      }catch(e){
        if (alive) setAgentsList([]);
      }
    })();
    return () => { alive = false; };
  }, []);

  useEffect(() => {
    (async () => {
      if (!rsDate || !data) return;
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
        if (normalized.length && !normalized.includes(rsTime)) setRsTime(normalized[0] || "");
        if (!normalized.length) setRsTime("");
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

  async function save(payload){
    setSaving(true);
    setErr("");
    try{
      const res = await bpFetch(`/admin/bookings/${id}`, { method: "PATCH", body: payload });
      const d = normalizeBookingResponse(res);
      setData(d);
    }catch(e){
      setErr(e?.message || "Save failed");
    }finally{
      setSaving(false);
    }
  }

  async function saveSummary(){
    if (!id) return;
    setSummarySaving(true);
    setSummaryMsg("");
    try{
      await save({
        customer_name: summary.customer_name,
        customer_email: summary.customer_email,
        customer_phone: summary.customer_phone,
        agent_id: summary.agent_id || 0,
      });
      setSummaryMsg("Summary saved");
    }catch(e){
      setSummaryMsg(e?.message || "Summary save failed");
    }finally{
      setSummarySaving(false);
    }
  }

  async function saveReschedule(){
    if(!id) return;
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
      const durationMinutes = Number(service.duration_minutes || booking.duration_minutes || booking.duration || 30);
      const startTs = new Date(`${rsDate}T${rsTime}:00`).getTime();
      const endTs = startTs + durationMinutes * 60000;
      const end = mysqlFromDateObj(new Date(endTs));

      await bpFetch(`/admin/bookings/${id}/reschedule`, {
        method: "POST",
        body: JSON.stringify({ start_datetime: start, end_datetime: end })
      });

      const res = await bpFetch(`/admin/bookings/${id}`);
      const payload = normalizeBookingResponse(res);
      setData(payload);
      const b = payload?.booking || payload || {};
      setRsDate(toDateInput(b.start_datetime || b.start));
      setRsTime(toTimeInput(b.start_datetime || b.start));
      setRsOk("Rescheduled successfully");
    }catch(e){
      setRsErr(e?.message || "Reschedule failed");
    }finally{
      setRsSaving(false);
    }
  }

  async function handleDelete(){
    if (!id) return;
    if (!window.confirm("Delete this booking? This cannot be undone.")) return;
    await bpFetch(`/admin/bookings/${id}`, { method: "DELETE" });
    window.location.href = "admin.php?page=bp_bookings";
  }

  const booking = data?.booking || data || {};
  const customer = data?.customer || {};
  const service = data?.service || {};
  const agent = data?.agent || {};

  return (
    <div className="bp-booking-edit">
      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Booking #{booking.id || id}</div>
          <div className="bp-muted">Edit booking details</div>
        </div>
        <div className="bp-head-actions">
          <button className="bp-top-btn" onClick={() => window.location.href = "admin.php?page=bp_bookings"}>
            Back to Bookings
          </button>
          <button className="bp-btn bp-btn-danger" onClick={handleDelete}>Delete</button>
        </div>
      </div>

      {err ? <div className="bp-error">{err}</div> : null}
      {loading ? <div className="bp-muted">Loading...</div> : null}

      {!loading && data ? (
        <div className="bp-be-grid">
          <div className="bp-card bp-be-card">
            <div className="bp-section-title">Summary</div>

            <div className="bp-form-grid">
              <div className="bp-form-row">
                <label className="bp-label">Service</label>
                <div className="bp-value">{service.name || booking.service_name || "-"}</div>
              </div>
              <div className="bp-form-row">
                <label className="bp-label">Start</label>
                <div className="bp-value">{booking.start_datetime || booking.start || "-"}</div>
              </div>
              <div className="bp-form-row">
                <label className="bp-label">End</label>
                <div className="bp-value">{booking.end_datetime || booking.end || "-"}</div>
              </div>

              <div className="bp-form-row">
                <label className="bp-label">Agent</label>
                <select
                  className="bp-input"
                  value={summary.agent_id || 0}
                  onChange={(e)=>setSummary((s)=>({ ...s, agent_id: Number(e.target.value || 0) }))}
                >
                  <option value={0}>{agent.name || booking.agent_name || "Unassigned"}</option>
                  {agentsList.map((a)=>(
                    <option key={a.id} value={a.id}>{a.name || `Agent #${a.id}`}</option>
                  ))}
                </select>
              </div>

              <div className="bp-form-row">
                <label className="bp-label">Customer</label>
                <input
                  className="bp-input"
                  value={summary.customer_name}
                  onChange={(e)=>setSummary((s)=>({ ...s, customer_name: e.target.value }))}
                  placeholder="Customer name"
                />
              </div>
              <div className="bp-form-row">
                <label className="bp-label">Email</label>
                <input
                  className="bp-input"
                  type="email"
                  value={summary.customer_email}
                  onChange={(e)=>setSummary((s)=>({ ...s, customer_email: e.target.value }))}
                  placeholder="Email"
                />
              </div>
              <div className="bp-form-row">
                <label className="bp-label">Phone</label>
                <input
                  className="bp-input"
                  value={summary.customer_phone}
                  onChange={(e)=>setSummary((s)=>({ ...s, customer_phone: e.target.value }))}
                  placeholder="Phone"
                />
              </div>
            </div>

            <div className="bp-form-actions">
              {summaryMsg ? <div className="bp-muted">{summaryMsg}</div> : null}
              <button className="bp-btn bp-btn-primary" onClick={saveSummary} disabled={summarySaving}>
                {summarySaving ? "Saving..." : "Save Summary"}
              </button>
            </div>
          </div>

          <div className="bp-card bp-be-card">
            <div className="bp-section-title">Status</div>
            <div className="bp-status-row">
              <div className={`bp-badge ${status}`}>{status}</div>
              <div className="bp-status-controls">
                <select className="bp-input" value={status} onChange={(e)=>setStatus(e.target.value)}>
                  <option value="pending">pending</option>
                  <option value="confirmed">confirmed</option>
                  <option value="cancelled">cancelled</option>
                  <option value="completed">completed</option>
                </select>
                <button className="bp-btn bp-btn-primary" disabled={saving} onClick={()=>save({ status })}>
                  {saving ? "Saving..." : "Save Status"}
                </button>
              </div>
            </div>
          </div>

          <div className="bp-card bp-be-card">
            <div className="bp-section-title">Reschedule</div>
            {rsErr ? <div className="bp-error" style={{ marginBottom: 8 }}>{rsErr}</div> : null}
            {rsOk ? <div className="bp-alert" style={{ marginBottom: 8 }}>{rsOk}</div> : null}
            <div className="bp-form-grid">
              <div className="bp-form-row">
                <label className="bp-label">Date</label>
                <input className="bp-input" type="date" value={rsDate} onChange={(e)=>setRsDate(e.target.value)} />
              </div>
              <div className="bp-form-row">
                <label className="bp-label">Time</label>
                <select className="bp-input" value={rsTime} onChange={(e)=>setRsTime(e.target.value)} disabled={rsLoading || !rsSlots.length}>
                  {!rsSlots.length ? <option value="">No available times</option> : null}
                  {rsSlots.map((t) => (
                    <option key={t} value={t}>{t}</option>
                  ))}
                </select>
              </div>
            </div>
            <div className="bp-form-actions">
              <div className="bp-muted">
                {rsLoading ? "Loading available times..." : (rsSlots.length ? `${rsSlots.length} slots available` : "No slots")}
              </div>
              <button className="bp-btn bp-btn-primary" onClick={saveReschedule} disabled={rsSaving || rsLoading || !rsSlots.length}>
                {rsSaving ? "Saving..." : "Save"}
              </button>
            </div>
          </div>

          <div className="bp-card bp-be-card">
            <div className="bp-section-title">Admin notes</div>
            <textarea
              value={adminNotes}
              onChange={(e)=>setAdminNotes(e.target.value)}
              rows={6}
              className="bp-textarea"
              placeholder="Internal notes..."
            />
            <div className="bp-form-actions">
              <button className="bp-btn bp-btn-primary" disabled={saving} onClick={()=>save({ admin_notes: adminNotes })}>
                {saving ? "Saving..." : "Save notes"}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}

function normalizeBookingResponse(res){
  if (!res) return res;
  if (res?.data?.booking) return res.data;
  if (res?.booking) return res;
  if (res?.data) return res.data;
  return res;
}
