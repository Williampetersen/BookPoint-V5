export function toMinutes(hhmm) {
  if (!hhmm) return 0;
  const [h, m] = hhmm.split(':').map(n => parseInt(n, 10));
  return (h * 60) + (m || 0);
}

export function clamp(n, a, b) {
  return Math.max(a, Math.min(b, n));
}

/**
 * Layout events for a single day into columns (overlap stacking)
 * Input events: [{id, start_time:"HH:MM", end_time:"HH:MM", ...}]
 * Output: events with {col, colCount} for width calculation
 */
export function layoutDayOverlaps(dayEvents) {
  const evs = (dayEvents || []).slice().sort((a,b) => {
    const sa = toMinutes(a.start_time);
    const sb = toMinutes(b.start_time);
    if (sa !== sb) return sa - sb;
    return toMinutes(a.end_time) - toMinutes(b.end_time);
  });

  const active = [];
  const columns = []; // array of arrays per column

  function removeEnded(currentStartMin) {
    for (let i = active.length - 1; i >= 0; i--) {
      if (active[i].endMin <= currentStartMin) active.splice(i, 1);
    }
  }

  for (const e of evs) {
    const startMin = toMinutes(e.start_time);
    const endMin = Math.max(startMin + 10, toMinutes(e.end_time));
    e.startMin = startMin;
    e.endMin = endMin;

    removeEnded(startMin);

    // find first free column
    let col = 0;
    for (; col < columns.length; col++) {
      const colEvents = columns[col];
      const last = colEvents[colEvents.length - 1];
      if (!last || last.endMin <= startMin) break;
    }
    if (!columns[col]) columns[col] = [];
    columns[col].push(e);
    e.col = col;

    active.push(e);
  }

  // Determine colCount within overlap groups
  // We compute for each event: maximum concurrent columns during its interval
  for (const e of evs) {
    let maxCols = 1;
    for (const other of evs) {
      if (other === e) continue;
      const overlap = !(other.endMin <= e.startMin || other.startMin >= e.endMin);
      if (!overlap) continue;
      maxCols = Math.max(maxCols, (Math.max(e.col, other.col) + 1));
    }
    e.colCount = maxCols;
  }

  return evs;
}
