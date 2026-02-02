import React, { useEffect, useMemo, useRef, useState } from "react";
import { bpFetch } from "../api/client";

function Badge({ status }) {
  const s = (status || "pending").toLowerCase();
  return <span className={`bp-badge ${s}`}>{s}</span>;
}

function pad2(n) {
  return String(n).padStart(2, "0");
}

function toYMD(d) {
  return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

function addDays(d, days) {
  const x = new Date(d);
  x.setDate(x.getDate() + days);
  return x;
}

function startOfDay(d) {
  const x = new Date(d);
  x.setHours(0, 0, 0, 0);
  return x;
}

function presetToRange(preset) {
  const today = startOfDay(new Date());
  if (preset === "today") return { from: toYMD(today), to: toYMD(today), label: "Today" };
  if (preset === "7d") return { from: toYMD(addDays(today, -6)), to: toYMD(today), label: "Last 7 days" };
  if (preset === "30d") return { from: toYMD(addDays(today, -29)), to: toYMD(today), label: "Last 30 days" };
  if (preset === "90d") return { from: toYMD(addDays(today, -89)), to: toYMD(today), label: "Last 90 days" };
  if (preset === "ytd") {
    const jan1 = new Date(today.getFullYear(), 0, 1);
    return { from: toYMD(jan1), to: toYMD(today), label: "YTD" };
  }
  return { from: toYMD(addDays(today, -6)), to: toYMD(today), label: "Last 7 days" };
}

export default function DashboardScreen() {
  const popRef = useRef(null);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [actionsOpen, setActionsOpen] = useState(false);
  const [rangeOpen, setRangeOpen] = useState(false);
  const [customOpen, setCustomOpen] = useState(false);
  const [preset, setPreset] = useState("7d");
  const defaultRange = useMemo(() => presetToRange("7d"), []);
  const [from, setFrom] = useState(defaultRange.from);
  const [to, setTo] = useState(defaultRange.to);
  const [isMobile, setIsMobile] = useState(() => {
    if (typeof window === "undefined" || !window.matchMedia) return false;
    return window.matchMedia("(max-width: 767px)").matches;
  });
  const [data, setData] = useState({
    kpi: { bookings_today: 0, upcoming_7d: 0, pending: 0, services: 0, agents: 0 },
    recent: [],
    chart7: [],
  });

  const rangeLabel = useMemo(() => {
    if (preset === "custom") return `${from} – ${to}`;
    return presetToRange(preset).label;
  }, [preset, from, to]);

  useEffect(() => {
    if (!window.matchMedia) return;
    const mq = window.matchMedia("(max-width: 767px)");
    const onChange = () => setIsMobile(mq.matches);
    onChange();
    mq.addEventListener?.("change", onChange);
    return () => mq.removeEventListener?.("change", onChange);
  }, []);

  useEffect(() => {
    const onKey = (e) => {
      if (e.key === "Escape") {
        setActionsOpen(false);
        setRangeOpen(false);
        setCustomOpen(false);
      }
    };
    const onClick = (e) => {
      const el = popRef.current;
      if (el && !el.contains(e.target)) {
        setActionsOpen(false);
        setRangeOpen(false);
        setCustomOpen(false);
      }
    };
    document.addEventListener("keydown", onKey);
    document.addEventListener("mousedown", onClick);
    return () => {
      document.removeEventListener("keydown", onKey);
      document.removeEventListener("mousedown", onClick);
    };
  }, []);
  const chart = data.chart7 || [];
  const chartMax = Math.max(1, ...chart.map((p) => Number(p.count || 0)));
  const chartWidth = 700;
  const chartHeight = 220;
  const chartMinY = 20;
  const chartMaxY = 180;
  const chartLeft = 50;
  const chartRight = 650;
  const chartStep = chart.length > 1 ? (chartRight - chartLeft) / (chart.length - 1) : 0;
  const chartPoints = chart.map((p, i) => {
    const v = Math.max(0, Number(p.count || 0));
    const ratio = v / chartMax;
    const x = chartLeft + (chartStep * i);
    const y = chartMaxY - (ratio * (chartMaxY - chartMinY));
    return { x, y, label: p.day ? p.day.slice(5) : "", fullLabel: p.day || "", count: v };
  });
  const chartPath = chartPoints.map((pt, i) => `${i === 0 ? "M" : "L"}${pt.x},${pt.y}`).join(" ");
  const chartArea = chartPoints.length
    ? `${chartPath} L${chartRight},${chartMaxY} L${chartLeft},${chartMaxY} Z`
    : "";

  const showLabel = (idx) => !isMobile || idx % 2 === 0;

  function applyPreset(nextPreset) {
    setPreset(nextPreset);
    const r = presetToRange(nextPreset);
    setFrom(r.from);
    setTo(r.to);
    setRangeOpen(false);
    setCustomOpen(false);
  }

  function openCustom() {
    setPreset("custom");
    setCustomOpen(true);
    setRangeOpen(false);
  }

  function applyCustom() {
    if (!from || !to) return;
    if (from > to) {
      setFrom(to);
      setTo(from);
    }
    setCustomOpen(false);
    setRangeOpen(false);
  }

  async function load() {
    setLoading(true);
    setErr("");
    try {
      const qs = `?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&preset=${encodeURIComponent(preset)}`;
      const res = await bpFetch(`/admin/dashboard${qs}`);
      setData(res?.data || data);
    } catch (e) {
      setErr(e.message || "Failed to load dashboard");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); /* eslint-disable-next-line */ }, [from, to, preset]);

  return (
    <div className="myplugin-page bp-dashboard">
      <main className="myplugin-content">
        <div className="bp-dashboard__head">
          <div className="myplugin-headerText">
          <h1 className="myplugin-title">Main Dashboard</h1>
          <p className="myplugin-subtitle">Overview of bookings, services, agents</p>
          </div>

          <div className="bp-dashboard__actions">
            <button className="bp-primary-btn" onClick={() => alert("Next: open booking wizard")}>
              + Create Booking
            </button>
            <div className="bp-actions-menu">
              <button
                className="bp-top-btn bp-actions-trigger"
                type="button"
                aria-haspopup="true"
                aria-expanded={actionsOpen}
                onClick={() => setActionsOpen((v) => !v)}
              >
                More
              </button>
              {actionsOpen ? (
                <div className="bp-actions-pop" role="menu">
                  <a role="menuitem" href="admin.php?page=bp_services">+ Add Service</a>
                  <a role="menuitem" href="admin.php?page=bp_agents">+ Add Agent</a>
                </div>
              ) : null}
            </div>
          </div>
        </div>

        {err ? <div className="bp-error">{err}</div> : null}

        <div className="bp-dashboard__grid">
          <section className="myplugin-card bp-dashboard__performance">
            <header className="myplugin-card__head">
              <h2 className="myplugin-card__title">Performance</h2>
              <div className="bp-range" ref={popRef}>
                <button
                  className="bp-range__btn bp-range__btn--mobile"
                  type="button"
                  onClick={() => setRangeOpen((v) => !v)}
                  aria-haspopup="dialog"
                  aria-expanded={rangeOpen}
                >
                  {rangeLabel} <span className="bp-range__caret" aria-hidden="true">▾</span>
                </button>

                <div className="bp-range__desktop">
                  <div className="bp-range__chips" role="group" aria-label="Date range">
                    <button type="button" className={`bp-chip-btn ${preset === "today" ? "is-active" : ""}`} onClick={() => applyPreset("today")}>Today</button>
                    <button type="button" className={`bp-chip-btn ${preset === "7d" ? "is-active" : ""}`} onClick={() => applyPreset("7d")}>7D</button>
                    <button type="button" className={`bp-chip-btn ${preset === "30d" ? "is-active" : ""}`} onClick={() => applyPreset("30d")}>30D</button>
                    <button type="button" className={`bp-chip-btn ${preset === "90d" ? "is-active" : ""}`} onClick={() => applyPreset("90d")}>90D</button>
                    <button type="button" className={`bp-chip-btn ${preset === "ytd" ? "is-active" : ""}`} onClick={() => applyPreset("ytd")}>YTD</button>
                  </div>
                  <button type="button" className="bp-top-btn" onClick={openCustom}>Custom</button>
                </div>

                {(rangeOpen || customOpen) && (
                  <>
                    {isMobile ? (
                      <div
                        className="bp-range__overlay"
                        role="presentation"
                        onClick={() => { setRangeOpen(false); setCustomOpen(false); }}
                      />
                    ) : null}
                    <div className={`bp-range__panel ${isMobile ? "is-sheet" : "is-pop"}`} role="dialog" aria-label="Choose date range">
                    <div className="bp-range__panel-head">
                      <div className="bp-range__panel-title">Date range</div>
                      <button type="button" className="bp-btn bp-btn-ghost" onClick={() => { setRangeOpen(false); setCustomOpen(false); }}>Close</button>
                    </div>

                    <div className="bp-range__panel-body">
                      <div className="bp-range__panel-chips">
                        <button type="button" className={`bp-chip-btn ${preset === "today" ? "is-active" : ""}`} onClick={() => applyPreset("today")}>Today</button>
                        <button type="button" className={`bp-chip-btn ${preset === "7d" ? "is-active" : ""}`} onClick={() => applyPreset("7d")}>7D</button>
                        <button type="button" className={`bp-chip-btn ${preset === "30d" ? "is-active" : ""}`} onClick={() => applyPreset("30d")}>30D</button>
                        <button type="button" className={`bp-chip-btn ${preset === "90d" ? "is-active" : ""}`} onClick={() => applyPreset("90d")}>90D</button>
                        <button type="button" className={`bp-chip-btn ${preset === "ytd" ? "is-active" : ""}`} onClick={() => applyPreset("ytd")}>YTD</button>
                        <button type="button" className={`bp-chip-btn ${preset === "custom" ? "is-active" : ""}`} onClick={openCustom}>Custom</button>
                      </div>

                      <div className="bp-range__inputs">
                        <label className="bp-range__field">
                          <span className="bp-range__label">From</span>
                          <input className="bp-input" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                        </label>
                        <label className="bp-range__field">
                          <span className="bp-range__label">To</span>
                          <input className="bp-input" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                        </label>
                      </div>
                    </div>

                    <div className="bp-range__panel-actions">
                      <button type="button" className="bp-btn bp-btn-ghost" onClick={() => applyPreset("7d")}>Reset</button>
                      <button type="button" className="bp-btn bp-btn-primary" onClick={applyCustom}>Apply</button>
                    </div>
                    </div>
                  </>
                )}
              </div>
            </header>

            <div className="bp-kpi-grid">
              <div className="bp-kpi-tile">
                {loading ? (
                  <div className="bp-skel bp-skel-kpi" />
                ) : (
                  <>
                    <div className="bp-kpi-top">
                      <div className="bp-kpi-value">{data.kpi.bookings_today}</div>
                      <div className="bp-kpi-delta up">+0%</div>
                    </div>
                    <div className="bp-kpi-label">Bookings Today</div>
                  </>
                )}
              </div>
              <div className="bp-kpi-tile">
                {loading ? (
                  <div className="bp-skel bp-skel-kpi" />
                ) : (
                  <>
                    <div className="bp-kpi-top">
                      <div className="bp-kpi-value">{data.kpi.upcoming_7d}</div>
                      <div className="bp-kpi-delta up">+0%</div>
                    </div>
                    <div className="bp-kpi-label">Upcoming (7 days)</div>
                  </>
                )}
              </div>
              <div className="bp-kpi-tile">
                {loading ? (
                  <div className="bp-skel bp-skel-kpi" />
                ) : (
                  <>
                    <div className="bp-kpi-top">
                      <div className="bp-kpi-value">{data.kpi.pending}</div>
                      <div className="bp-kpi-delta down">-0%</div>
                    </div>
                    <div className="bp-kpi-label">Pending</div>
                  </>
                )}
              </div>
              <div className="bp-kpi-tile">
                {loading ? (
                  <div className="bp-skel bp-skel-kpi" />
                ) : (
                  <>
                    <div className="bp-kpi-top">
                      <div className="bp-kpi-value">{data.kpi.services}</div>
                      <div className="bp-kpi-delta up">+0%</div>
                    </div>
                    <div className="bp-kpi-label">Services</div>
                  </>
                )}
              </div>
            </div>

            <div className="bp-chart-wrap">
              {loading ? (
                <div className="bp-skel bp-skel-chart" />
              ) : chart.length === 0 ? (
                <div className="bp-muted">No chart data yet.</div>
              ) : (
                <svg className="bp-chart-svg" viewBox={`0 0 ${chartWidth} ${chartHeight}`} role="img" aria-label="Weekly performance chart">
                  <defs>
                    <linearGradient id="bpChartFill" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor="#2b7fff" stopOpacity="0.18" />
                      <stop offset="100%" stopColor="#2b7fff" stopOpacity="0.00" />
                    </linearGradient>
                  </defs>
                  <g className="bp-chart-grid">
                    {chartPoints.map((pt) => (
                      <line key={`grid-${pt.x}`} x1={pt.x} y1={chartMinY} x2={pt.x} y2={chartMaxY} />
                    ))}
                  </g>
                  <line className="bp-chart-baseline" x1={chartLeft} y1={chartMaxY} x2={chartRight} y2={chartMaxY} />
                  <path className="bp-chart-area" d={chartArea} />
                  <path className="bp-chart-line" d={chartPath} />
                  <g className="bp-chart-points">
                    {chartPoints.map((pt, idx) => (
                      <circle key={`pt-${idx}`} cx={pt.x} cy={pt.y} r="4">
                        <title>{`${pt.fullLabel || pt.label}: ${pt.count} bookings`}</title>
                      </circle>
                    ))}
                  </g>
                  <g className="bp-chart-labels">
                    {chartPoints.map((pt, idx) => (
                      showLabel(idx) ? (
                        <text key={`lbl-${idx}`} x={pt.x} y={210} textAnchor="middle">{pt.label}</text>
                      ) : null
                    ))}
                  </g>
                </svg>
              )}
            </div>
          </section>

          <aside className="bp-dashboard__side">
            <div className="bp-card bp-quick-card">
              <div className="bp-card-label">Quick Actions</div>
              <div className="bp-quick-actions">
                <a className="bp-quick-link" href="admin.php?page=bp_bookings">Manage bookings</a>
                <a className="bp-quick-link" href="admin.php?page=bp_calendar">Open calendar</a>
                <a className="bp-quick-link" href="admin.php?page=bp_form_fields">Edit form fields</a>
                <a className="bp-quick-link" href="admin.php?page=bp_settings">Settings</a>
              </div>
            </div>

            <div className="bp-card bp-summary-card">
              <div className="bp-card-label">Today</div>
              <div className="bp-summary-grid">
                <div className="bp-summary-item">
                  <div className="bp-summary-value">{loading ? "..." : data.kpi.bookings_today}</div>
                  <div className="bp-summary-label">Bookings</div>
                </div>
                <div className="bp-summary-item">
                  <div className="bp-summary-value">{loading ? "..." : data.kpi.pending}</div>
                  <div className="bp-summary-label">Pending</div>
                </div>
                <div className="bp-summary-item">
                  <div className="bp-summary-value">{loading ? "..." : data.kpi.agents}</div>
                  <div className="bp-summary-label">Agents</div>
                </div>
              </div>
            </div>
          </aside>
        </div>

        <div className="bp-card bp-dashboard__table">
          <div className="bp-card-label" style={{ marginBottom: 10 }}>Recent Bookings</div>

          <div className="bp-table-scroll">
            <div className="bp-table">
            <div className="bp-tr bp-th">
              <div>ID</div>
              <div>When</div>
              <div>Service</div>
              <div>Agent</div>
              <div>Customer</div>
              <div>Status</div>
            </div>

            {(data.recent || []).map((b) => (
              <a key={b.id} className="bp-tr" href={`admin.php?page=bp-bookings&view=${b.id}`}>
                <div>#{b.id}</div>
                <div className="bp-muted">{b.start}</div>
                <div>{b.service_name || "-"}</div>
                <div>{b.agent_name || "-"}</div>
                <div>{b.customer_name || "-"}</div>
                <div><Badge status={b.status} /></div>
              </a>
            ))}

            {(!data.recent || data.recent.length === 0) && (
              <div className="bp-muted" style={{ padding: 10 }}>No bookings yet.</div>
            )}
            </div>
          </div>
        </div>
      </main>
    </div>
  );
}
