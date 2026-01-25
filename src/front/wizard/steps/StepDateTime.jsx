import React, { useEffect, useMemo, useRef, useState } from 'react';

const pad2 = (n) => String(n).padStart(2, '0');
const utcMidday = (y, m, d) => new Date(Date.UTC(y, m, d, 12, 0, 0));
const ymdUTC = (date) => {
  const y = date.getUTCFullYear();
  const m = String(date.getUTCMonth() + 1).padStart(2, '0');
  const d = String(date.getUTCDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
};
const startOfMonthUTC = (date) => utcMidday(date.getUTCFullYear(), date.getUTCMonth(), 1);
const addDaysUTC = (date, days) => utcMidday(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate() + days);
const weekdayMon0UTC = (date) => (date.getUTCDay() + 6) % 7;
const buildMonthGridUTC = (viewDate) => {
  const start = startOfMonthUTC(viewDate);
  const offset = weekdayMon0UTC(start);
  const gridStart = addDaysUTC(start, -offset);
  return Array.from({ length: 42 }, (_, i) => addDaysUTC(gridStart, i));
};
const isSameUTCMonth = (a, b) => a.getUTCFullYear() === b.getUTCFullYear() && a.getUTCMonth() === b.getUTCMonth();
const isPastUTC = (date) => {
  const now = new Date();
  const today = utcMidday(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate());
  return date.getTime() < today.getTime();
};
const toHHMM = (t) => (t ? String(t).slice(0, 5) : '');

async function bpPost(path, body, { signal } = {}) {
  const base = window.BP_FRONT?.restUrl || '/wp-json/bp/v1';
  const nonce = window.BP_FRONT?.nonce;
  const res = await fetch(`${base}${path}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      ...(nonce ? { 'X-WP-Nonce': nonce } : {}),
    },
    body: JSON.stringify(body),
    signal,
    credentials: 'same-origin',
  });

  const text = await res.text();
  let json;
  try { json = JSON.parse(text); } catch (e) { json = { ok: false, raw: text }; }
  if (!res.ok) {
    const msg = json?.message || json?.raw || `HTTP ${res.status}`;
    throw new Error(msg);
  }
  return json;
}

export default function StepDateTime({
  serviceId,
  agentId,
  locationId,
  valueDate,
  valueSlot,
  onChangeDate,
  onChangeSlot,
  onBack,
  onNext,
}) {
  const [viewDate, setViewDate] = useState(() => {
    const now = new Date();
    return utcMidday(now.getUTCFullYear(), now.getUTCMonth(), 1);
  });

  const [selectedDate, setSelectedDate] = useState(valueDate || null);
  const [monthDays, setMonthDays] = useState([]);
  const [loadingMonth, setLoadingMonth] = useState(false);

  const [slots, setSlots] = useState([]);
  const [loadingSlots, setLoadingSlots] = useState(false);
  const [slotError, setSlotError] = useState('');

  const monthCache = useRef(new Map());
  const dayCache = useRef(new Map());
  const abortMonthRef = useRef(null);
  const abortDayRef = useRef(null);

  const monthKey = useMemo(() => {
    const y = viewDate.getUTCFullYear();
    const m = pad2(viewDate.getUTCMonth() + 1);
    return `${y}-${m}`;
  }, [viewDate]);

  const monthParams = useMemo(() => ({
    service_id: serviceId,
    agent_id: agentId,
    location_id: locationId,
    month: monthKey,
  }), [serviceId, agentId, locationId, monthKey]);

  useEffect(() => {
    if (!serviceId || !agentId) return;
    const key = JSON.stringify(monthParams);
    if (monthCache.current.has(key)) {
      setMonthDays(monthCache.current.get(key));
      return;
    }

    if (abortMonthRef.current) abortMonthRef.current.abort();
    const ac = new AbortController();
    abortMonthRef.current = ac;

    setLoadingMonth(true);
    bpPost('/front/availability/month', monthParams, { signal: ac.signal })
      .then((json) => {
        const days = json?.data?.days || [];
        monthCache.current.set(key, days);
        setMonthDays(days);
      })
      .catch(() => {})
      .finally(() => setLoadingMonth(false));
  }, [monthParams, serviceId, agentId]);

  useEffect(() => {
    if (!valueDate) return;
    const next = valueDate;
    if (next !== selectedDate) setSelectedDate(next);
  }, [valueDate]);

  const fetchDay = useMemo(() => ({
    service_id: serviceId,
    agent_id: agentId,
    location_id: locationId,
    date: selectedDate,
  }), [serviceId, agentId, locationId, selectedDate]);

  useEffect(() => {
    if (!selectedDate || !serviceId || !agentId) return;
    const key = JSON.stringify(fetchDay);
    if (dayCache.current.has(key)) {
      setSlots(dayCache.current.get(key));
      return;
    }

    if (abortDayRef.current) abortDayRef.current.abort();
    const ac = new AbortController();
    abortDayRef.current = ac;

    setLoadingSlots(true);
    setSlots([]);
    setSlotError('');

    bpPost('/front/availability/day', fetchDay, { signal: ac.signal })
      .then((json) => {
        const s = json?.slots || [];
        dayCache.current.set(key, s);
        setSlots(s);
      })
      .catch((e) => {
        if (e.name !== 'AbortError') {
          setSlots([]);
          setSlotError('No available times for this date.');
        }
      })
      .finally(() => setLoadingSlots(false));
  }, [fetchDay, selectedDate, serviceId, agentId]);

  const daysSet = useMemo(() => {
    const map = new Map();
    monthDays.forEach((d) => map.set(d.date, d));
    return map;
  }, [monthDays]);

  const grid = useMemo(() => buildMonthGridUTC(viewDate), [viewDate]);

  function pickDate(dateObj) {
    if (isPastUTC(dateObj)) return;
    const dateStr = ymdUTC(dateObj);
    onChangeSlot?.(null);
    onChangeDate?.(dateStr);
  }

  const canNext = !!selectedDate && !!valueSlot?.start_time;

  return (
    <div className="bp-step bp-datetime">
      <div className="bp-calendar">
        <div className="bp-cal-header">
          <button
            type="button"
            className="bp-cal-nav"
            onClick={() => setViewDate((d) => utcMidday(d.getUTCFullYear(), d.getUTCMonth() - 1, 1))}
          >
            &lt;
          </button>
          <div className="bp-cal-title">
            {viewDate.toLocaleString(undefined, { month: 'long', year: 'numeric', timeZone: 'UTC' })}
          </div>
          <button
            type="button"
            className="bp-cal-nav"
            onClick={() => setViewDate((d) => utcMidday(d.getUTCFullYear(), d.getUTCMonth() + 1, 1))}
          >
            &gt;
          </button>
        </div>

        <div className="bp-cal-weekdays">
          {['M', 'T', 'W', 'T', 'F', 'S', 'S'].map((d) => (
            <div key={d} className="bp-cal-wd">{d}</div>
          ))}
        </div>

        <div className="bp-cal-grid">
          {grid.map((d, idx) => {
            const inMonth = isSameUTCMonth(d, viewDate);
            const disabled = isPastUTC(d) || !inMonth;
            const dateStr = ymdUTC(d);
            const isSelected = selectedDate === dateStr;
            const info = daysSet.get(dateStr);
            const hasSlots = info ? info.has_slots : false;

            return (
              <button
                key={`${dateStr}-${idx}`}
                type="button"
                className={`bp-cal-day ${inMonth ? '' : 'is-out'} ${isSelected ? 'is-selected' : ''}`}
                disabled={disabled}
                onClick={() => pickDate(d)}
              >
                <div>{d.getUTCDate()}</div>
                <div style={{
                  height: 4,
                  width: 28,
                  borderRadius: 999,
                  margin: '6px auto 0',
                  background: hasSlots ? '#22c55e' : '#e5e7eb',
                  opacity: loadingMonth && !info ? 0.3 : 1,
                }} />
              </button>
            );
          })}
        </div>
      </div>

      <div className="bp-slots">
        <div className="bp-slots-title">
          Pick a slot for <strong>{selectedDate || '—'}</strong>
        </div>

        {!selectedDate && <div className="bp-muted">Select a date to see available times.</div>}

        {selectedDate && loadingSlots && (
          <div className="bp-slot-skeleton">
            <div className="bp-skel" />
            <div className="bp-skel" />
            <div className="bp-skel" />
          </div>
        )}

        {selectedDate && !loadingSlots && slotError && <div className="bp-error">{slotError}</div>}

        {selectedDate && !loadingSlots && !slotError && (
          <div className="bp-slot-grid">
            {slots.map((s, i) => (
              <button
                key={`${s.time}-${i}`}
                type="button"
                className={`bp-slot ${valueSlot?.start_time === s.time ? 'is-selected' : ''}`}
                onClick={() => onChangeSlot?.({ start_time: s.time, end_time: s.end || '' })}
              >
                {s.time}
              </button>
            ))}
            {slots.length === 0 && <div className="bp-muted">No times found for this date.</div>}
          </div>
        )}
      </div>

      <div className="bp-step-footer">
        <button type="button" className="bp-back" onClick={onBack}>
          &lt;- Back
        </button>
        <button type="button" className="bp-next" disabled={!canNext} onClick={onNext}>
          Next -&gt;
        </button>
      </div>
    </div>
  );
}
