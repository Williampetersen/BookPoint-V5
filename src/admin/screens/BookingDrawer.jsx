import React, { useEffect, useMemo, useState } from 'react';
import { bpFetch } from '../api/client';

const badge = (status) => {
  const base = { padding: '4px 10px', borderRadius: 999, fontWeight: 900, fontSize: 12, display: 'inline-flex' };
  if (status === 'confirmed') return <span style={{ ...base, background: '#ecfeff', border: '1px solid #a5f3fc', color: '#155e75' }}>confirmed</span>;
  if (status === 'cancelled') return <span style={{ ...base, background: '#fef2f2', border: '1px solid #fecaca', color: '#991b1b' }}>cancelled</span>;
  return <span style={{ ...base, background: '#fff7ed', border: '1px solid #fed7aa', color: '#9a3412' }}>pending</span>;
};

export default function BookingDrawer({ bookingId, onClose, onChanged, toast }) {
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [booking, setBooking] = useState(null);
  const [agents, setAgents] = useState([]);
  const [notes, setNotes] = useState('');
  const [agentId, setAgentId] = useState(0);
  const [date, setDate] = useState('');
  const [time, setTime] = useState('');
  const [slots, setSlots] = useState([]);
  const [slotsLoading, setSlotsLoading] = useState(false);

  useEffect(() => {
    if (!bookingId) return;
    (async () => {
      setLoading(true);
      try {
        const [bRes, aRes] = await Promise.all([
          bpFetch(`/admin/bookings/${bookingId}`),
          bpFetch(`/admin/agents`),
        ]);
        const b = bRes?.data;
        setBooking(b);
        setAgents(aRes?.data || []);
        setNotes(b?.notes || '');
        setAgentId(parseInt(b?.agent_id || 0, 10));
        setDate((b?.start_date || '').slice(0, 10));
        setTime((b?.start_time || '').slice(0, 5));
      } catch (e) {
        toast('error', e.message || 'Failed to load booking');
      } finally {
        setLoading(false);
      }
    })();
  }, [bookingId, toast]);

  const title = useMemo(() => booking ? `Booking #${booking.id}` : 'Booking', [booking]);

  const patch = async (payload, successMsg) => {
    setSaving(true);
    try {
      await bpFetch(`/admin/bookings/${bookingId}`, { method: 'PATCH', body: payload });
      toast('success', successMsg || 'Saved ✅');
      onChanged?.(); // refresh calendar events
      // reload details (to reflect service/agent names and status)
      const bRes = await bpFetch(`/admin/bookings/${bookingId}`);
      const b = bRes?.data;
      setBooking(b);
    } catch (e) {
      toast('error', e.message || 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  const loadSlots = async () => {
    if (!booking?.service_id) return toast('error', 'Missing service_id');
    setSlotsLoading(true);
    setSlots([]);
    try {
      const params = new URLSearchParams({
        service_id: String(booking.service_id),
        agent_id: String(agentId || 0),
        date: date,
        exclude_booking_id: String(booking.id),
      });
      const res = await bpFetch(`/admin/availability/slots?${params.toString()}`);
      setSlots(res?.data?.slots || []);
      if ((res?.data?.slots || []).length === 0) toast('info', 'No available slots on that day');
    } catch (e) {
      toast('error', e.message || 'Failed to load slots');
    } finally {
      setSlotsLoading(false);
    }
  };

  const saveSlot = async (slot) => {
    const aId = slot.agent_id ? slot.agent_id : agentId;
    await patch({ start_date: date, start_time: slot.time, agent_id: aId }, 'Rescheduled ✅');
  };

  if (!bookingId) return null;

  return (
    <div>
      {loading || !booking ? (
        <div style={{ fontWeight: 800 }}>Loading…</div>
      ) : (
        <>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10 }}>
            <div style={{ fontWeight: 950, fontSize: 16 }}>{title}</div>
            {badge(booking.status)}
          </div>

          <div style={card}>
            <div style={row}><span style={muted}>Customer</span><strong>{booking.customer_name || '—'}</strong></div>
            <div style={row}><span style={muted}>Email</span><strong>{booking.customer_email || '—'}</strong></div>
            <div style={row}><span style={muted}>Service</span><strong>{booking.service_name || `#${booking.service_id}`}</strong></div>
            <div style={row}><span style={muted}>Agent</span><strong>{booking.agent_name || `#${booking.agent_id}`}</strong></div>
            <div style={row}><span style={muted}>When</span><strong>{booking.start_date} {String(booking.start_time || '').slice(0, 5)}</strong></div>
            <div style={row}><span style={muted}>Total</span><strong>€ {Number(booking.total_price || 0).toFixed(2)}</strong></div>
          </div>

          <div style={{ display: 'flex', gap: 10, marginTop: 10, flexWrap: 'wrap' }}>
            <button disabled={saving} style={btn} onClick={() => patch({ status: 'confirmed' }, 'Confirmed ✅')}>
              Confirm
            </button>
            <button disabled={saving} style={btnSecondary} onClick={() => patch({ status: 'cancelled' }, 'Cancelled ✅')}>
              Cancel
            </button>
            <button disabled={saving} style={btnSecondary} onClick={onClose}>
              Close
            </button>
          </div>

          <div style={{ marginTop: 14 }}>
            <div style={label}>Notes</div>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={4}
              style={inputArea}
              placeholder="Internal notes…"
            />
            <button disabled={saving} style={{ ...btn, width: '100%', marginTop: 10 }}
              onClick={() => patch({ notes }, 'Notes saved ✅')}>
              {saving ? 'Saving…' : 'Save Notes'}
            </button>
          </div>

          <div style={{ marginTop: 14 }}>
            <div style={label}>Change Agent</div>
            <select value={agentId} onChange={(e) => setAgentId(parseInt(e.target.value, 10))} style={input}>
              <option value={0}>Unassigned</option>
              {agents.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
            </select>
            <button disabled={saving} style={{ ...btn, width: '100%', marginTop: 10 }}
              onClick={() => patch({ agent_id: agentId }, 'Agent updated ✅')}>
              {saving ? 'Saving…' : 'Save Agent'}
            </button>
          </div>

          <div style={{ marginTop: 14 }}>
            <div style={label}>Reschedule (Pick Available Slot)</div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
              <div>
                <div style={mutedSmall}>Date</div>
                <input type="date" value={date} onChange={(e) => setDate(e.target.value)} style={input} />
              </div>
              <div>
                <div style={mutedSmall}>Agent</div>
                <select value={agentId} onChange={(e) => setAgentId(parseInt(e.target.value, 10))} style={input}>
                  <option value={0}>Any agent</option>
                  {agents.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
                </select>
              </div>
            </div>

            <button disabled={slotsLoading || saving} style={{ ...btnSecondary, width: '100%', marginTop: 10 }}
              onClick={loadSlots}>
              {slotsLoading ? 'Loading slots…' : 'Load available slots'}
            </button>

            {slots.length > 0 ? (
              <div style={{
                marginTop: 10,
                display: 'flex',
                flexWrap: 'wrap',
                gap: 8
              }}>
                {slots.map((s) => (
                  <button
                    key={s.start + '-' + (s.agent_id || 0)}
                    onClick={() => saveSlot(s)}
                    disabled={saving}
                    style={{
                      padding: '9px 12px',
                      borderRadius: 999,
                      border: '1px solid #e5e7eb',
                      background: '#fff',
                      cursor: 'pointer',
                      fontWeight: 900
                    }}
                  >
                    {s.time}{s.agent_name ? ` — ${s.agent_name}` : ''}
                  </button>
                ))}
              </div>
            ) : (
              <div style={{ marginTop: 10, color: '#6b7280', fontWeight: 700, fontSize: 12 }}>
                Tip: choose a date and click "Load available slots".
              </div>
            )}

            <div style={{ marginTop: 8, color: '#6b7280', fontWeight: 700, fontSize: 12 }}>
              Drag & drop also works on the calendar and will reject conflicts.
            </div>
          </div>
        </>
      )}
    </div>
  );
}

const card = {
  marginTop: 10,
  border: '1px solid #e5e7eb',
  borderRadius: 14,
  padding: 12,
  background: '#fff',
};

const row = { display: 'flex', justifyContent: 'space-between', gap: 10, margin: '6px 0' };
const muted = { color: '#6b7280', fontWeight: 800, fontSize: 12 };
const mutedSmall = { color: '#6b7280', fontWeight: 800, fontSize: 12, marginBottom: 6 };
const label = { fontWeight: 950, marginBottom: 6 };

const input = {
  width: '100%',
  border: '1px solid #e5e7eb',
  borderRadius: 12,
  padding: '10px 10px',
  fontWeight: 800,
};

const inputArea = {
  width: '100%',
  border: '1px solid #e5e7eb',
  borderRadius: 12,
  padding: '10px 10px',
  fontWeight: 700,
  resize: 'vertical'
};

const btn = {
  background: '#4318ff',
  color: '#fff',
  border: 'none',
  borderRadius: 12,
  padding: '10px 12px',
  fontWeight: 950,
  cursor: 'pointer'
};
const btnSecondary = {
  background: '#eef2ff',
  color: '#1e1b4b',
  border: '1px solid rgba(67,24,255,.15)',
  borderRadius: 12,
  padding: '10px 12px',
  fontWeight: 950,
  cursor: 'pointer'
};
