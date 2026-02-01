import React, { useEffect, useState } from "react";
import { bpFetch } from "../api/client";

function Badge({ status }) {
  const s = (status || "pending").toLowerCase();
  return <span className={`bp-badge ${s}`}>{s}</span>;
}

export default function DashboardScreen() {
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [data, setData] = useState({
    kpi: { bookings_today: 0, upcoming_7d: 0, pending: 0, services: 0, agents: 0 },
    recent: [],
    chart7: [],
  });
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
    return { x, y, label: p.day ? p.day.slice(5) : "" };
  });
  const chartPath = chartPoints.map((pt, i) => `${i === 0 ? "M" : "L"}${pt.x},${pt.y}`).join(" ");

  async function load() {
    setLoading(true);
    setErr("");
    try {
      const res = await bpFetch("/admin/dashboard");
      setData(res?.data || data);
    } catch (e) {
      setErr(e.message || "Failed to load dashboard");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); /* eslint-disable-next-line */ }, []);

  return (
    <div className="myplugin-page">
      <main className="myplugin-content">
        <div className="myplugin-headerText">
          <h1 className="myplugin-title">Main Dashboard</h1>
          <p className="myplugin-subtitle">Overview of bookings, services, agents</p>
        </div>

        <div className="bp-head-actions">
          <a className="bp-top-btn" href="admin.php?page=bp_services">+ Add Service</a>
          <a className="bp-top-btn" href="admin.php?page=bp_agents">+ Add Agent</a>
          <button className="bp-primary-btn" onClick={() => alert("Next: open booking wizard")}>
            + Create Booking
          </button>
        </div>

        {err ? <div className="bp-error">{err}</div> : null}

        <section className="myplugin-card">
          <header className="myplugin-card__head">
            <h2 className="myplugin-card__title">Performance</h2>
            <button className="myplugin-iconbtn" type="button" aria-label="More">
              <span className="myplugin-dots">...</span>
            </button>
          </header>

          <div className="myplugin-kpis">
            <div className="myplugin-kpi">
              <div className="myplugin-kpi__top">
                <div className="myplugin-kpi__value">{loading ? "..." : data.kpi.bookings_today}</div>
                <div className="myplugin-kpi__delta myplugin-kpi__delta--up">+0%</div>
              </div>
              <div className="myplugin-kpi__label">Bookings Today</div>
            </div>
            <div className="myplugin-kpi">
              <div className="myplugin-kpi__top">
                <div className="myplugin-kpi__value">{loading ? "..." : data.kpi.upcoming_7d}</div>
                <div className="myplugin-kpi__delta myplugin-kpi__delta--up">+0%</div>
              </div>
              <div className="myplugin-kpi__label">Upcoming (7 days)</div>
            </div>
            <div className="myplugin-kpi">
              <div className="myplugin-kpi__top">
                <div className="myplugin-kpi__value">{loading ? "..." : data.kpi.pending}</div>
                <div className="myplugin-kpi__delta myplugin-kpi__delta--down">-0%</div>
              </div>
              <div className="myplugin-kpi__label">Pending</div>
            </div>
            <div className="myplugin-kpi">
              <div className="myplugin-kpi__top">
                <div className="myplugin-kpi__value">{loading ? "..." : data.kpi.services}</div>
                <div className="myplugin-kpi__delta myplugin-kpi__delta--up">+0%</div>
              </div>
              <div className="myplugin-kpi__label">Services</div>
            </div>
          </div>

          <div className="myplugin-chart">
            {chart.length === 0 ? (
              <div className="bp-muted">No chart data yet.</div>
            ) : (
              <svg className="myplugin-chart__svg" viewBox={`0 0 ${chartWidth} ${chartHeight}`} role="img" aria-label="Weekly performance chart">
                <g className="myplugin-chart__grid">
                  {chartPoints.map((pt) => (
                    <line key={`grid-${pt.x}`} x1={pt.x} y1={chartMinY} x2={pt.x} y2={chartMaxY} />
                  ))}
                </g>
                <path className="myplugin-chart__line" d={chartPath} />
                <g className="myplugin-chart__points">
                  {chartPoints.map((pt, idx) => (
                    <circle key={`pt-${idx}`} cx={pt.x} cy={pt.y} r="5" />
                  ))}
                </g>
                <g className="myplugin-chart__labels">
                  {chartPoints.map((pt, idx) => (
                    <text key={`lbl-${idx}`} x={pt.x} y={210} textAnchor="middle">{pt.label}</text>
                  ))}
                </g>
              </svg>
            )}
          </div>
        </section>

        <div className="bp-card">
          <div className="bp-card-label" style={{ marginBottom: 10 }}>Recent Bookings</div>

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
      </main>
    </div>
  );
}
