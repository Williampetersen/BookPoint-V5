import React, { useEffect, useRef, useState } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import { bpFetch } from '../api/client';
import Drawer from '../components/Drawer';
import BookingDrawer from './BookingDrawer';

const statusColor = (status) => {
  if (status === 'confirmed') return '#06b6d4';
  if (status === 'cancelled') return '#ef4444';
  return '#f59e0b'; // pending
};

export default function CalendarScreen() {
  const [events, setEvents] = useState([]);
  const [bgEvents, setBgEvents] = useState([]);
  const [loading, setLoading] = useState(false);
  const [filters, setFilters] = useState({ agent_id: 0, service_id: 0, status: '' });
  const [toast, setToast] = useState(null);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [selectedId, setSelectedId] = useState(null);
  const [agents, setAgents] = useState([]);
  const [services, setServices] = useState([]);
  const lastRangeRef = useRef(null);

  const pushToast = (type, msg) => setToast({ type, msg });

  // Load agents and services on mount
  useEffect(() => {
    (async () => {
      try {
        const [aRes, sRes] = await Promise.all([
          bpFetch('/admin/agents'),
          bpFetch('/admin/services'),
        ]);
        setAgents(aRes?.data || []);
        setServices(sRes?.data || []);
      } catch (e) {
        pushToast('error', e.message || 'Failed to load filters');
      }
    })();
  }, []);

  const fetchEvents = async (info) => {
    lastRangeRef.current = info;
    setLoading(true);
    try {
      const params = new URLSearchParams({
        start: info.startStr,
        end: info.endStr,
      });
      if (filters.agent_id) params.set('agent_id', String(filters.agent_id));
      if (filters.service_id) params.set('service_id', String(filters.service_id));
      if (filters.status) params.set('status', filters.status);

      const [evRes, bgRes] = await Promise.all([
        bpFetch(`/admin/calendar?${params.toString()}`),
        filters.agent_id
          ? bpFetch(`/admin/schedule/unavailable?start=${encodeURIComponent(info.startStr)}&end=${encodeURIComponent(info.endStr)}&agent_id=${filters.agent_id}`)
          : Promise.resolve({ data: [] }),
      ]);

      const data = evRes?.data || [];
      setEvents(data.map(ev => ({
        ...ev,
        backgroundColor: statusColor(ev.extendedProps?.status),
        borderColor: 'transparent',
        textColor: '#0b1437',
      })));

      setBgEvents((bgRes?.data || []).map((e, idx) => ({
        ...e,
        id: `bg-${idx}`,
        backgroundColor: 'rgba(148,163,184,.25)',
      })));
    } catch (e) {
      pushToast('error', e.message || 'Failed to load calendar');
    } finally {
      setLoading(false);
    }
  };

  // Drag & drop handler
  const onEventDrop = async (arg) => {
    const id = arg.event.id;
    const start = arg.event.start?.toISOString();
    const end = arg.event.end?.toISOString();
    const agent_id = arg.event.extendedProps?.agent_id || 0;

    try {
      await bpFetch(`/admin/bookings/${id}/reschedule`, {
        method: 'PATCH',
        body: { start, end, agent_id },
      });

      pushToast('success', 'Rescheduled ✅');
    } catch (e) {
      // revert UI move if server rejects
      arg.revert();
      pushToast('error', e.message || 'Cannot reschedule');
    }
  };

  const onEventClick = (arg) => {
    setSelectedId(parseInt(arg.event.id, 10));
    setDrawerOpen(true);
  };

  const refreshEvents = async () => {
    if (lastRangeRef.current) await fetchEvents(lastRangeRef.current);
  };

  return (
    <div style={{ padding: 16 }}>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
        <h2 style={{ margin: 0 }}>Calendar</h2>
        {loading ? <span style={{ fontWeight: 700 }}>Loading…</span> : null}
      </div>

      {/* Status chips + dropdowns */}
      <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'center', marginBottom: 12 }}>
        {/* Status chips */}
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          {[
            { key: '', label: 'All' },
            { key: 'pending', label: 'Pending' },
            { key: 'confirmed', label: 'Confirmed' },
            { key: 'cancelled', label: 'Cancelled' },
          ].map(x => (
            <button
              key={x.key || 'all'}
              onClick={() => setFilters(f => ({ ...f, status: x.key }))}
              style={{
                padding: '8px 10px',
                borderRadius: 999,
                border: '1px solid #e5e7eb',
                background: filters.status === x.key ? 'rgba(67,24,255,.10)' : '#fff',
                fontWeight: 900,
                cursor: 'pointer'
              }}
            >
              {x.label}
            </button>
          ))}
        </div>

        <div style={{ flex: 1 }} />

        {/* Agent dropdown */}
        <select
          value={filters.agent_id || 0}
          onChange={(e) => setFilters(f => ({ ...f, agent_id: parseInt(e.target.value, 10) }))}
          style={{ padding: '9px 10px', borderRadius: 12, border: '1px solid #e5e7eb', fontWeight: 800 }}
        >
          <option value={0}>All Agents</option>
          {agents.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
        </select>

        {/* Service dropdown */}
        <select
          value={filters.service_id || 0}
          onChange={(e) => setFilters(f => ({ ...f, service_id: parseInt(e.target.value, 10) }))}
          style={{ padding: '9px 10px', borderRadius: 12, border: '1px solid #e5e7eb', fontWeight: 800 }}
        >
          <option value={0}>All Services</option>
          {services.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
        </select>
      </div>

      {/* Color legend */}
      <div style={{ display: 'flex', gap: 10, marginBottom: 12, flexWrap: 'wrap', fontWeight: 800, color: '#6b7280' }}>
        <span><span style={{ display: 'inline-block', width: 10, height: 10, borderRadius: 999, background: '#f59e0b', marginRight: 6 }} />Pending</span>
        <span><span style={{ display: 'inline-block', width: 10, height: 10, borderRadius: 999, background: '#06b6d4', marginRight: 6 }} />Confirmed</span>
        <span><span style={{ display: 'inline-block', width: 10, height: 10, borderRadius: 999, background: '#ef4444', marginRight: 6 }} />Cancelled</span>
      </div>

      <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 16, padding: 10 }}>
        <FullCalendar
          plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
          initialView="timeGridWeek"
          headerToolbar={{
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay',
          }}
          editable={true}
          eventDrop={onEventDrop}
          eventClick={onEventClick}
          events={[...bgEvents, ...events]}
          datesSet={fetchEvents}
          slotDuration="00:15:00"
          snapDuration="00:15:00"
          slotLabelInterval="01:00"
          height="auto"
          nowIndicator={true}
          firstDay={1}
        />
      </div>

      {toast ? (
        <div style={{
          position: 'fixed', right: 18, bottom: 18,
          background: '#0b1437', color: '#fff', padding: '10px 12px',
          borderRadius: 12, fontWeight: 800
        }}>
          {toast.msg}
          <button
            onClick={() => setToast(null)}
            style={{ marginLeft: 10, background: 'transparent', border: 'none', color: '#fff', cursor: 'pointer', fontWeight: 900 }}
          >
            ×
          </button>
        </div>
      ) : null}

      <Drawer
        open={drawerOpen}
        title={selectedId ? `Booking #${selectedId}` : 'Booking'}
        onClose={() => setDrawerOpen(false)}
      >
        <BookingDrawer
          bookingId={selectedId}
          onClose={() => setDrawerOpen(false)}
          onChanged={refreshEvents}
          toast={pushToast}
        />
      </Drawer>
    </div>
  );
}
