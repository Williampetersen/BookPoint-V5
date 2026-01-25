import React, { useEffect, useMemo, useRef, useState } from 'react';
import { fetchAvailabilityMonth, fetchSlots } from '../api';

const pad2 = (n) => String(n).padStart(2, '0');
const utcMidday = (y, m, d) => new Date(Date.UTC(y, m, d, 12, 0, 0));
const ymdUTC = (date) => {
  const y = date.getUTCFullYear();
  const m = String(date.getUTCMonth() + 1).padStart(2, '0');
  const d = String(date.getUTCDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
};
const addDaysUTC = (date, days) => utcMidday(
  date.getUTCFullYear(),
  date.getUTCMonth(),
  date.getUTCDate() + days,
);
const startOfMonthUTC = (date) => utcMidday(date.getUTCFullYear(), date.getUTCMonth(), 1);
const endOfMonthUTC = (date) => utcMidday(date.getUTCFullYear(), date.getUTCMonth() + 1, 0);
const weekdayMon0UTC = (date) => (date.getUTCDay() + 6) % 7;
const formatMonthTitle = (date) => date.toLocaleString(undefined, { month: 'long', year: 'numeric' });

const toHHMM = (t) => {
  if (!t) return '';
  const s = String(t);
  return s.length >= 5 ? s.slice(0, 5) : s;
};
const parseTimeToMinutes = (t) => {
  const hhmm = toHHMM(t);
  const [h, m] = hhmm.split(':').map(Number);
  return (h || 0) * 60 + (m || 0);
};
const minutesToHHMM = (min) => {
  const h = Math.floor(min / 60);
  const m = min % 60;
  return `${pad2(h)}:${pad2(m)}`;
};
const nowMinutesLocal = () => {
  const n = new Date();
  return n.getHours() * 60 + n.getMinutes();
};

function normalizeSlots(payload) {
  const raw = payload?.data || payload?.slots || payload;
  if (!Array.isArray(raw)) return [];

  return raw
    .map((s) => {
      const start = toHHMM(s.start || s.from || s.time || s.start_time || '');
      const end = toHHMM(s.end || s.to || s.end_time || '');
      const available = typeof s.available === 'boolean' ? s.available : (s.is_available ?? true);
      return { start_time: start, end_time: end, available };
    })
    .filter((x) => x.start_time);
}

export default function StepDateTime({
  locationId,
  serviceId,
  agentId,
  serviceDurationMin = 30,
  valueDate,
  valueSlot,
  onChangeDate,
  onChangeSlot,
  onBack,
  onNext,
}) {
  const now = new Date();
  const todayUTC = utcMidday(now.getFullYear(), now.getMonth(), now.getDate());
  const todayStr = ymdUTC(todayUTC);

  const initialStr = valueDate && valueDate >= todayStr ? valueDate : todayStr;
  const initialUTC = utcMidday(
    Number(initialStr.slice(0, 4)),
    Number(initialStr.slice(5, 7)) - 1,
    Number(initialStr.slice(8, 10)),
  );

  const [month, setMonth] = useState(startOfMonthUTC(initialUTC));
  const [selectedDate, setSelectedDate] = useState(initialStr);
  const [slots, setSlots] = useState([]);
  const [loadingSlots, setLoadingSlots] = useState(false);
  const [slotsError, setSlotsError] = useState('');
  const [availability, setAvailability] = useState({});
  const cacheRef = useRef(new Map());
  const reqIdRef = useRef(0);

  useEffect(() => {
    if (!valueDate) return;
    const next = valueDate >= todayStr ? valueDate : todayStr;
    if (next !== selectedDate) {
      setSelectedDate(next);
      const nextUTC = utcMidday(
        Number(next.slice(0, 4)),
        Number(next.slice(5, 7)) - 1,
        Number(next.slice(8, 10)),
      );
      setMonth(startOfMonthUTC(nextUTC));
    }
  }, [valueDate, todayStr]);

  useEffect(() => {
    if (!valueDate && selectedDate) onChangeDate?.(selectedDate);
  }, [valueDate, selectedDate, onChangeDate]);

  const gridDays = useMemo(() => {
    const start = startOfMonthUTC(month);
    const end = endOfMonthUTC(month);
    const startPad = weekdayMon0UTC(start);
    const gridStart = addDaysUTC(start, -startPad);
    const endPad = 6 - weekdayMon0UTC(end);
    const gridEnd = addDaysUTC(end, endPad);

    const days = [];
    for (let d = new Date(gridStart); d <= gridEnd; d = addDaysUTC(d, 1)) {
      days.push(new Date(d));
    }
    return days;
  }, [month]);

  const isSameMonth = (d) => d.getUTCMonth() === month.getUTCMonth() && d.getUTCFullYear() === month.getUTCFullYear();
  const dayKey = (d) => ymdUTC(d);

  const monthStr = `${month.getUTCFullYear()}-${pad2(month.getUTCMonth() + 1)}`;

  function barIsGreen(dateStr) {
    if (dateStr < todayStr) return false;
    if (availability[dateStr] === 0) return false;
    return true;
  }

  useEffect(() => {
    let alive = true;
    (async () => {
      if (!serviceId || !agentId) {
        setAvailability({});
        return;
      }
      try {
        const data = await fetchAvailabilityMonth({
          month: monthStr,
          service_id: serviceId,
          agent_id: agentId,
          location_id: locationId || 0,
        });
        if (!alive) return;
        setAvailability(data || {});
      } catch (e) {
        if (!alive) return;
        setAvailability({});
      }
    })();
    return () => { alive = false; };
  }, [monthStr, serviceId, agentId, locationId]);

  function selectDay(d) {
    const k = dayKey(d);
    if (k < todayStr) return;
    setSelectedDate(k);
    onChangeDate?.(k);
    onChangeSlot?.(null);
    if (!isSameMonth(d)) setMonth(startOfMonthUTC(d));
  }

  useEffect(() => {
    let alive = true;
    const reqId = ++reqIdRef.current;

    (async () => {
      if (!serviceId || !agentId || !selectedDate) {
        setSlots([]);
        setSlotsError('');
        setLoadingSlots(false);
        return;
      }

      if (selectedDate < todayStr) {
        setSlots([]);
        setSlotsError('Please select a future date.');
        setLoadingSlots(false);
        return;
      }

      const cached = cacheRef.current.get(selectedDate);
      if (Array.isArray(cached)) {
        setSlots(cached);
        setSlotsError(cached.length ? '' : 'No available times for this date.');
        setLoadingSlots(false);
        return;
      }

      setSlotsError('');
      setLoadingSlots(true);

      try {
        const json = await fetchSlots({
          service_id: serviceId,
          agent_id: agentId,
          date: selectedDate,
          location_id: locationId,
        });

        let s = normalizeSlots(json).filter((x) => x.available);
        if (selectedDate === todayStr) {
          const nowMin = nowMinutesLocal();
          s = s.filter((x) => parseTimeToMinutes(x.start_time) >= nowMin);
        }

        cacheRef.current.set(selectedDate, s);
        if (!alive || reqId !== reqIdRef.current) return;
        setSlots(s);
        setSlotsError(s.length ? '' : 'No available times for this date.');
      } catch (e) {
        if (!alive || reqId !== reqIdRef.current) return;
        setSlots([]);
        setSlotsError('No available times for this date.');
        cacheRef.current.set(selectedDate, []);
      } finally {
        if (!alive || reqId !== reqIdRef.current) return;
        setLoadingSlots(false);
      }
    })();

    return () => { alive = false; };
  }, [selectedDate, locationId, serviceId, agentId, todayStr]);

  useEffect(() => {
    if (selectedDate !== todayStr) return;
    const id = setInterval(() => {
      const nowMin = nowMinutesLocal();
      setSlots((prev) => {
        if (!prev?.length) return prev;
        const next = prev.filter((x) => parseTimeToMinutes(x.start_time) >= nowMin);
        if (next.length === prev.length) return prev;
        cacheRef.current.set(selectedDate, next);
        return next;
      });
    }, 60000);
    return () => clearInterval(id);
  }, [selectedDate, todayStr]);

  function slotLabel(slot) {
    const start = toHHMM(slot.start_time || slot.start);
    const end = toHHMM(slot.end_time || slot.end);
    if (end) return `${start} - ${end}`;
    const endMin = parseTimeToMinutes(start) + Number(serviceDurationMin || 30);
    return `${start} - ${minutesToHHMM(endMin)}`;
  }

  const currentStart = toHHMM(valueSlot?.start_time || valueSlot?.start || '');
  const canNext = !!selectedDate && !!currentStart;

  return (
    <div className="bp-step bp-datetime">
      <div className="bp-dt-head">
        <div className="bp-dt-month">
          <button
            type="button"
            className="bp-iconbtn"
            onClick={() => setMonth(startOfMonthUTC(addDaysUTC(month, -31)))}
            aria-label="Prev month"
          >
            &lt;
          </button>
          <div className="bp-dt-month-title">{formatMonthTitle(month)}</div>
          <button
            type="button"
            className="bp-iconbtn"
            onClick={() => setMonth(startOfMonthUTC(addDaysUTC(month, 31)))}
            aria-label="Next month"
          >
            &gt;
          </button>
        </div>

        <div className="bp-dt-weekdays">
          {['M', 'T', 'W', 'T', 'F', 'S', 'S'].map((w) => (
            <div key={w} className="bp-dt-wd">{w}</div>
          ))}
        </div>

        <div className="bp-dt-grid">
          {gridDays.map((d) => {
            const k = dayKey(d);
            const selected = k === selectedDate;
            const inMonth = isSameMonth(d);
            const disabled = k < todayStr;
            const green = barIsGreen(k);

            return (
              <button
                key={k}
                type="button"
                className={[
                  'bp-dt-day',
                  selected ? 'selected' : '',
                  inMonth ? '' : 'muted',
                  disabled ? 'disabled' : '',
                ].join(' ')}
                onClick={() => selectDay(d)}
                disabled={disabled}
              >
                <div className="bp-dt-daynum">{d.getUTCDate()}</div>
                <div className={green ? 'bp-dt-bar on' : 'bp-dt-bar'} />
              </button>
            );
          })}
        </div>
      </div>

      <div className="bp-dt-slots">
        <div className="bp-dt-slots-title">
          Pick a slot for <span className="bp-dt-date">{selectedDate}</span>
        </div>

        {!serviceId || !agentId ? (
          <div className="bp-dt-empty">Please select a service and agent first.</div>
        ) : loadingSlots ? (
          <div className="bp-dt-slotgrid">
            {Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="bp-slot skeleton" />
            ))}
          </div>
        ) : slotsError ? (
          <div className="bp-dt-empty">{slotsError}</div>
        ) : (
          <div className="bp-dt-slotgrid">
            {slots.map((s) => {
              const active = currentStart === toHHMM(s.start_time || s.start);
              return (
                <button
                  key={s.start_time || s.start}
                  type="button"
                  className={active ? 'bp-slot active' : 'bp-slot'}
                  onClick={() => onChangeSlot?.({
                    start_time: toHHMM(s.start_time || s.start),
                    end_time: toHHMM(s.end_time || s.end),
                  })}
                >
                  {slotLabel(s)}
                </button>
              );
            })}
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
