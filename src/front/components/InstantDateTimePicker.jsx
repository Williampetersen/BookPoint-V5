import React, { useEffect, useMemo, useRef, useState } from "react";

const cache = new Map();

async function postJson(url, body, { signal } = {}) {
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "same-origin",
    body: JSON.stringify(body),
    signal,
  });
  const txt = await res.text();
  let json;
  try { json = JSON.parse(txt); } catch { json = { ok:false, raw: txt }; }
  if (!res.ok || json?.ok === false) throw new Error(json?.message || `HTTP ${res.status}`);
  return json;
}

const pad2 = (n) => String(n).padStart(2, "0");
const fmtYM = (d) => `${d.getFullYear()}-${pad2(d.getMonth()+1)}`;
const monthLabel = (ym) => {
  const [y,m] = ym.split("-").map(Number);
  return new Date(y, m-1, 1).toLocaleString(undefined, { month:"long", year:"numeric" });
};
const mondayIndex = (jsDay) => (jsDay + 6) % 7;

export default function InstantDateTimePicker({
  serviceId, agentId, locationId, intervalMinutes,
  valueDate, valueTime,
  onChangeDate, onChangeTime,
}) {
  const init = useMemo(() => valueDate ? new Date(valueDate) : new Date(), [valueDate]);
  const [month, setMonth] = useState(() => fmtYM(init));
  const [days, setDays] = useState([]);
  const [slotsByDay, setSlotsByDay] = useState({});
  const [loading, setLoading] = useState(false);

  const acRef = useRef(null);

  const key = useMemo(() => JSON.stringify({
    serviceId, agentId: agentId||0, locationId: locationId||0, intervalMinutes: intervalMinutes||30, month
  }), [serviceId, agentId, locationId, intervalMinutes, month]);

  useEffect(() => {
    if (!serviceId) return;
    if (cache.has(key)) {
      const cached = cache.get(key);
      setDays(cached.days || []);
      setSlotsByDay(cached.slots_by_day || {});
      return;
    }

    if (acRef.current) acRef.current.abort();
    const ac = new AbortController();
    acRef.current = ac;

    setLoading(true);

    postJson("/wp-json/bp/v1/front/availability/month-slots", {
      service_id: serviceId,
      agent_id: agentId || 0,
      location_id: locationId || 0,
      interval: intervalMinutes || 30,
      month,
    }, { signal: ac.signal })
      .then((json) => {
        const data = json?.data || {};
        cache.set(key, data);
        setDays(data.days || []);
        setSlotsByDay(data.slots_by_day || {});
      })
      .catch((e) => {
        if (e.name !== "AbortError") console.error(e);
      })
      .finally(() => setLoading(false));
  }, [key, serviceId, agentId, locationId, intervalMinutes, month]);

  const daysMap = useMemo(() => {
    const m = new Map();
    (days || []).forEach(d => m.set(d.date, d));
    return m;
  }, [days]);

  const grid = useMemo(() => {
    const [y,m] = month.split("-").map(Number);
    const first = new Date(y, m-1, 1);
    const pad = mondayIndex(first.getDay());
    const dim = new Date(y, m, 0).getDate();

    const cells = [];
    for (let i=0;i<pad;i++) cells.push({ type:"pad", key:`p-${i}` });
    for (let d=1; d<=dim; d++) {
      const date = `${y}-${pad2(m)}-${pad2(d)}`;
      const info = daysMap.get(date);
      cells.push({ type:"day", key:date, date, day:d, hasSlots: !!info?.has_slots });
    }
    return cells;
  }, [month, daysMap]);

  const slots = useMemo(() => {
    if (!valueDate) return [];
    const s = slotsByDay?.[valueDate] || [];
    return s.map(x => String(x).slice(0,5));
  }, [slotsByDay, valueDate]);

  function changeMonth(delta) {
    const [y,m] = month.split("-").map(Number);
    const d = new Date(y, m-1 + delta, 1);
    setMonth(fmtYM(d));
  }

  function pickDate(date) {
    onChangeTime?.("");
    onChangeDate?.(date);
  }

  return (
    <div className="bp-iwrap">
      <div className="bp-ical">
        <div className="bp-ihead">
          <button type="button" className="bp-inav" onClick={() => changeMonth(-1)}>‹</button>
          <div className="bp-ititle">{monthLabel(month)}</div>
          <button type="button" className="bp-inav" onClick={() => changeMonth(1)}>›</button>
        </div>

        <div className="bp-idow">
          {["M","T","W","T","F","S","S"].map(x => <div key={x} className="bp-idowc">{x}</div>)}
        </div>

        <div className="bp-igrid">
          {grid.map(c => {
            if (c.type === "pad") return <div key={c.key} className="bp-ipad" />;
            const selected = valueDate === c.date;
            return (
              <button
                key={c.key}
                type="button"
                className={`bp-iday ${selected ? "is-selected" : ""} ${c.hasSlots ? "has" : "no"}`}
                onClick={() => pickDate(c.date)}
              >
                <div className="bp-inum">{c.day}</div>
                <div className={`bp-ibar ${c.hasSlots ? "g" : "x"}`} />
              </button>
            );
          })}
        </div>

        {loading && <div className="bp-iload">Preparing availability…</div>}
      </div>

      <div className="bp-islots">
        <div className="bp-isTitle">
          Pick a slot for <span>{valueDate || "—"}</span>
        </div>

        {!valueDate && <div className="bp-ibox">Select a date first.</div>}

        {valueDate && slots.length === 0 && (
          <div className="bp-ibox">No available times for this date.</div>
        )}

        <div className="bp-isGrid">
          {slots.map(t => (
            <button
              key={t}
              type="button"
              className={`bp-islot ${valueTime === t ? "is-active" : ""}`}
              onClick={() => onChangeTime?.(t)}
            >
              {t}
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}
