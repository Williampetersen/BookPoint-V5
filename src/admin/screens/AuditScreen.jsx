import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";

function humanizeEvent(ev) {
  const s = String(ev || "").trim();
  if (!s) return "Event";
  return s
    .replace(/[_-]+/g, " ")
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

function safeJsonParse(v) {
  if (!v) return null;
  if (typeof v === "object") return v;
  try {
    return JSON.parse(String(v));
  } catch {
    return null;
  }
}

function formatActor(row) {
  const wpName = row.actor_wp_display_name || "";
  if (wpName) return wpName;

  const first = row.customer_first_name || "";
  const last = row.customer_last_name || "";
  const full = `${first} ${last}`.trim();
  if (full) return full;

  return row.actor_type || "system";
}

function formatItem(row) {
  if (Number(row.booking_id) > 0) return `Booking #${row.booking_id}`;
  if (Number(row.customer_id) > 0) return `Customer #${row.customer_id}`;
  return "—";
}

function badgeClass(event) {
  const e = String(event || "").toLowerCase();
  if (e.includes("delete") || e.includes("cancel")) return "is-danger";
  if (e.includes("create") || e.includes("import") || e.includes("generate")) return "is-success";
  if (e.includes("update") || e.includes("edit") || e.includes("change") || e.includes("status")) return "is-info";
  if (e.includes("view") || e.includes("read") || e.includes("list")) return "is-muted";
  return "is-neutral";
}

function downloadText(filename, text) {
  const blob = new Blob([text], { type: "text/plain;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

function clamp(n, min, max) {
  const v = Number(n) || 0;
  return Math.max(min, Math.min(max, v));
}

export default function AuditScreen() {
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");

  const [meta, setMeta] = useState({ events: [], actor_types: ["admin", "customer", "system"] });

  const [q, setQ] = useState("");
  const [event, setEvent] = useState("");
  const [actorType, setActorType] = useState("");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(50);

  const [data, setData] = useState({ items: [], total: 0, page: 1, per_page: 50 });
  const [selectedId, setSelectedId] = useState(0);
  const [drawerOpen, setDrawerOpen] = useState(false);

  const selected = useMemo(() => {
    const id = Number(selectedId) || 0;
    return (data.items || []).find((r) => Number(r.id) === id) || null;
  }, [data.items, selectedId]);

  const isMobile = useMemo(() => window.innerWidth < 1024, []);

  const query = useMemo(() => {
    const params = new URLSearchParams();
    params.set("page", String(page));
    params.set("per_page", String(perPage));
    if (q.trim()) params.set("q", q.trim());
    if (event) params.set("event", event);
    if (actorType) params.set("actor_type", actorType);
    if (dateFrom) params.set("date_from", dateFrom);
    if (dateTo) params.set("date_to", dateTo);
    return params.toString();
  }, [page, perPage, q, event, actorType, dateFrom, dateTo]);

  async function loadMeta() {
    try {
      const res = await bpFetch("/admin/audit-logs/meta");
      setMeta(res?.data || { events: [], actor_types: ["admin", "customer", "system"] });
    } catch (_) {
      // meta is optional; keep defaults
    }
  }

  async function load() {
    setLoading(true);
    setErr("");
    try {
      const res = await bpFetch(`/admin/audit-logs?${query}`);
      const next = res?.data || { items: [], total: 0, page, per_page: perPage };
      setData(next);
      if (selectedId && !(next.items || []).some((r) => Number(r.id) === Number(selectedId))) {
        setSelectedId(0);
        setDrawerOpen(false);
      }
    } catch (e) {
      setErr(e.message || "Failed to load audit log");
      setData({ items: [], total: 0, page, per_page: perPage });
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadMeta();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [query]);

  useEffect(() => {
    if (!isMobile) setDrawerOpen(false);
  }, [isMobile]);

  const totalPages = useMemo(() => {
    const total = Number(data.total) || 0;
    return Math.max(1, Math.ceil(total / (Number(data.per_page) || perPage)));
  }, [data.total, data.per_page, perPage]);

  const rangeLabel = useMemo(() => {
    const total = Number(data.total) || 0;
    const pp = Number(data.per_page) || perPage;
    const p = Number(data.page) || page;
    if (!total) return "0";
    const from = (p - 1) * pp + 1;
    const to = Math.min(total, p * pp);
    return `${from}-${to} of ${total}`;
  }, [data.total, data.page, data.per_page, page, perPage]);

  const resetPage = () => setPage(1);

  const onSelect = (row) => {
    setSelectedId(Number(row.id));
    if (isMobile) setDrawerOpen(true);
  };

  const clearLog = async () => {
    if (!confirm("Clear the audit log? This cannot be undone.")) return;
    setErr("");
    try {
      await bpFetch("/admin/audit-logs/clear", { method: "POST", body: {} });
      setSelectedId(0);
      setDrawerOpen(false);
      setPage(1);
      await load();
    } catch (e) {
      setErr(e.message || "Clear failed");
    }
  };

  const exportCsv = async () => {
    setErr("");
    try {
      const base = window.BP_ADMIN?.restUrl || "/wp-json/bp/v1";
      const url = base.replace(/\/$/, "") + `/admin/audit-logs/export?limit=5000&${query}`;

      const res = await fetch(url, {
        method: "GET",
        credentials: "same-origin",
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce },
      });
      const text = await res.text();
      if (!res.ok) throw new Error("Export failed");
      downloadText(`bookpoint-audit-log-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-")}.csv`, text);
    } catch (e) {
      setErr(e.message || "Export failed");
    }
  };

  const detailsJson = useMemo(() => safeJsonParse(selected?.meta) || safeJsonParse(selected?.details) || safeJsonParse(selected?.message), [selected]);

  const bookingLink = useMemo(() => {
    if (!selected || !Number(selected.booking_id)) return "";
    return `admin.php?page=bp_bookings_edit&id=${Number(selected.booking_id)}`;
  }, [selected]);

  const customerLink = useMemo(() => {
    if (!selected || !Number(selected.customer_id)) return "";
    return `admin.php?page=bp_customers_edit&id=${Number(selected.customer_id)}`;
  }, [selected]);

  const FilterBar = (
    <div className="bp-audit-filters">
      <div className="bp-audit-filters__left">
        <div className="bp-audit-field">
          <label className="bp-label">Search</label>
          <input
            className="bp-input-field"
            value={q}
            onChange={(e) => { setQ(e.target.value); resetPage(); }}
            placeholder="event, actor, IP, customer, meta…"
          />
        </div>
        <div className="bp-audit-filterGrid">
          <div className="bp-audit-field">
            <label className="bp-label">Event</label>
            <select className="bp-select" value={event} onChange={(e) => { setEvent(e.target.value); resetPage(); }}>
              <option value="">All</option>
              {(meta.events || []).map((ev) => (
                <option key={ev} value={ev}>{humanizeEvent(ev)}</option>
              ))}
            </select>
          </div>
          <div className="bp-audit-field">
            <label className="bp-label">Actor Type</label>
            <select className="bp-select" value={actorType} onChange={(e) => { setActorType(e.target.value); resetPage(); }}>
              <option value="">All</option>
              {(meta.actor_types || ["admin", "customer", "system"]).map((t) => (
                <option key={t} value={t}>{t}</option>
              ))}
            </select>
          </div>
          <div className="bp-audit-field">
            <label className="bp-label">From</label>
            <input className="bp-input-field" type="date" value={dateFrom} onChange={(e) => { setDateFrom(e.target.value); resetPage(); }} />
          </div>
          <div className="bp-audit-field">
            <label className="bp-label">To</label>
            <input className="bp-input-field" type="date" value={dateTo} onChange={(e) => { setDateTo(e.target.value); resetPage(); }} />
          </div>
        </div>
      </div>

      <div className="bp-audit-filters__right">
        <button type="button" className="bp-btn" onClick={load} disabled={loading}>Refresh</button>
        <button type="button" className="bp-btn" onClick={exportCsv} disabled={loading}>Export CSV</button>
        <button type="button" className="bp-btn bp-btn-danger" onClick={clearLog} disabled={loading}>Clear</button>
      </div>
    </div>
  );

  const DetailsPanel = selected ? (
    <div className="bp-card bp-audit-details">
      <div className="bp-audit-details__head">
        <div style={{ minWidth: 0 }}>
          <div className="bp-section-title" style={{ margin: 0 }}>{humanizeEvent(selected.event)}</div>
          <div className="bp-muted bp-text-xs" style={{ marginTop: 6 }}>
            #{selected.id} · {selected.created_at ? new Date(selected.created_at).toLocaleString() : "—"}
          </div>
        </div>
        <button type="button" className="bp-btn" onClick={() => { setSelectedId(0); setDrawerOpen(false); }}>Close</button>
      </div>

      <div className="bp-audit-details__meta">
        <div className="bp-audit-kv">
          <div className="k">Actor</div>
          <div className="v">
            {formatActor(selected)}
            {selected.actor_ip ? <span className="bp-audit-pill">{selected.actor_ip}</span> : null}
          </div>
        </div>
        <div className="bp-audit-kv">
          <div className="k">Item</div>
          <div className="v">{formatItem(selected)}</div>
        </div>
        <div className="bp-audit-kv">
          <div className="k">Type</div>
          <div className="v">{selected.actor_type || "—"}</div>
        </div>
        <div className="bp-audit-kv">
          <div className="k">Links</div>
          <div className="v">
            {bookingLink ? <a className="bp-link" href={bookingLink}>Open booking</a> : <span className="bp-muted">—</span>}
            {" "}
            {customerLink ? <a className="bp-link" href={customerLink}>Open customer</a> : null}
          </div>
        </div>
      </div>

      <div className="bp-audit-json">
        <div className="bp-audit-json__head">
          <div className="bp-section-title" style={{ margin: 0 }}>Details</div>
          <button
            type="button"
            className="bp-btn"
            onClick={() => {
              const raw = selected.meta || "";
              downloadText(`audit-${selected.id}.json`, JSON.stringify(safeJsonParse(raw) || raw, null, 2));
            }}
          >
            Copy JSON
          </button>
        </div>
        <pre className="bp-audit-json__pre">
          {detailsJson ? JSON.stringify(detailsJson, null, 2) : (selected.meta ? String(selected.meta) : "—")}
        </pre>
      </div>
    </div>
  ) : (
    <div className="bp-card bp-audit-empty">
      <div className="bp-section-title">Select a log</div>
      <div className="bp-muted bp-text-sm" style={{ marginTop: 6 }}>
        Pick an event on the left to see full details.
      </div>
    </div>
  );

  return (
    <div className="bp-card bp-audit">
      <div className="bp-audit-head">
        <div>
          <div className="bp-h1" style={{ margin: 0 }}>Audit Log</div>
          <div className="bp-muted">Review system activity and changes.</div>
        </div>
        <div className="bp-audit-head__right">
          <div className="bp-muted bp-text-sm">{rangeLabel}</div>
          <select
            className="bp-select"
            value={perPage}
            onChange={(e) => { setPerPage(clamp(e.target.value, 10, 200)); setPage(1); }}
            aria-label="Rows per page"
          >
            {[25, 50, 100, 200].map((n) => <option key={n} value={n}>{n}/page</option>)}
          </select>
          <button type="button" className="bp-btn" onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={loading || page <= 1}>Prev</button>
          <button type="button" className="bp-btn" onClick={() => setPage((p) => Math.min(totalPages, p + 1))} disabled={loading || page >= totalPages}>Next</button>
        </div>
      </div>

      {FilterBar}

      {err ? <div className="bp-alert bp-alert-error" style={{ marginTop: 12 }}>{err}</div> : null}

      <div className="bp-audit-layout">
        <div className="bp-audit-list">
          {loading ? <div className="bp-muted" style={{ padding: 14 }}>Loading…</div> : null}
          {!loading && (data.items || []).length === 0 ? (
            <div className="bp-muted" style={{ padding: 14 }}>No audit logs found.</div>
          ) : null}

          {!loading && (data.items || []).length > 0 ? (
            <div className="bp-audit-items">
              {(data.items || []).map((row) => {
                const active = Number(selectedId) === Number(row.id);
                const actor = formatActor(row);
                const item = formatItem(row);
                const when = row.created_at ? new Date(row.created_at).toLocaleString() : "";

                return (
                  <button
                    key={row.id}
                    type="button"
                    className={`bp-audit-item ${active ? "is-active" : ""}`}
                    onClick={() => onSelect(row)}
                  >
                    <div className={`bp-audit-badge ${badgeClass(row.event)}`} aria-hidden="true" />
                    <div className="bp-audit-itemMain">
                      <div className="bp-audit-itemTop">
                        <div className="bp-audit-itemTitle">
                          <span className="bp-audit-itemEvent">{humanizeEvent(row.event)}</span>
                          <span className="bp-audit-chip">{row.actor_type || "system"}</span>
                          {row.actor_ip ? <span className="bp-audit-chip is-muted">{row.actor_ip}</span> : null}
                        </div>
                        <div className="bp-muted bp-text-xs">{when}</div>
                      </div>
                      <div className="bp-audit-itemSub">
                        <span className="bp-audit-strong">{actor}</span>
                        <span className="bp-audit-dot">·</span>
                        <span className="bp-muted">{item}</span>
                      </div>
                    </div>
                  </button>
                );
              })}
            </div>
          ) : null}
        </div>

        <div className="bp-audit-right">
          {!isMobile ? DetailsPanel : null}
        </div>
      </div>

      {isMobile && drawerOpen ? (
        <div className="bp-audit-drawerOverlay" role="dialog" aria-modal="true" onMouseDown={() => setDrawerOpen(false)}>
          <div className="bp-audit-drawer" onMouseDown={(e) => e.stopPropagation()}>
            {DetailsPanel}
          </div>
        </div>
      ) : null}
    </div>
  );
}

