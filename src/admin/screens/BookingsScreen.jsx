import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";
import BookingDrawer from "../components/BookingDrawer";

function Badge({ status }) {
  const s = (status || "pending").toLowerCase();
  const labels = {
    pending: "pending",
    confirmed: "confirmed",
    cancelled: "cancelled",
    completed: "completed",
    pending_payment: "pending payment",
    paid: "paid",
    refunded: "refunded",
    no_show: "no-show",
  };
  return <span className={`bp-badge ${s}`}>{labels[s] || s}</span>;
}

function fmtWhen(start) {
  if (!start) return "-";
  const s = String(start).replace("T", " ").slice(0, 19);
  const date = s.slice(0, 10);
  const time = s.slice(11, 16);
  return time ? `${date} • ${time}` : date;
}

export default function BookingsScreen() {
  const [isMobile, setIsMobile] = useState(() => window.innerWidth < 768);
  const [filtersOpen, setFiltersOpen] = useState(false);
  const [selectedId, setSelectedId] = useState(null);

  const [q, setQ] = useState("");
  const [status, setStatus] = useState("all");
  const [sort, setSortState] = useState("desc");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [page, setPage] = useState(1);
  const per = 20;

  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");
  const [items, setItems] = useState([]);
  const [total, setTotal] = useState(0);
  const [stats, setStats] = useState({ total: 0, pending: 0, confirmed: 0, cancelled: 0 });

  const pages = Math.max(1, Math.ceil(total / per));

  useEffect(() => {
    const onResize = () => setIsMobile(window.innerWidth < 768);
    window.addEventListener("resize", onResize);
    return () => window.removeEventListener("resize", onResize);
  }, []);

  async function load(opts = {}) {
    setLoading(true);
    setErr("");
    try {
      const nextQ = opts.q ?? q;
      const nextStatus = opts.status ?? status;
      const nextSort = opts.sort ?? sort;
      const nextDateFrom = opts.dateFrom ?? dateFrom;
      const nextDateTo = opts.dateTo ?? dateTo;
      const nextPage = opts.page ?? page;

      const url =
        `/admin/bookings?` +
        `q=${encodeURIComponent(nextQ)}` +
        `&status=${encodeURIComponent(nextStatus)}` +
        `&sort=${encodeURIComponent(nextSort)}` +
        `&date_from=${encodeURIComponent(nextDateFrom)}` +
        `&date_to=${encodeURIComponent(nextDateTo)}` +
        `&page=${nextPage}&per=${per}`;

      const res = await bpFetch(url);
      const list = res?.data?.items || [];
      const sorted = [...list].sort((a, b) => {
        const ai = Number(a.id) || 0;
        const bi = Number(b.id) || 0;
        return nextSort === "asc" ? ai - bi : bi - ai;
      });
      setItems(sorted);
      setTotal(res?.data?.total || 0);

      const s = { total: res?.data?.total || 0, pending: 0, confirmed: 0, cancelled: 0 };
      for (const b of sorted) {
        const st = (b.status || "pending").toLowerCase();
        if (st === "pending") s.pending++;
        if (st === "confirmed") s.confirmed++;
        if (st === "cancelled") s.cancelled++;
      }
      setStats(s);
    } catch (e) {
      setErr(e.message || "Failed to load bookings");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page]);

  useEffect(() => {
    const view = new URLSearchParams(window.location.search).get("view");
    if (!view) return;
    const id = Number(view);
    if (!Number.isFinite(id) || id <= 0) return;
    setSelectedId(id);
  }, []);

  function goEdit(id) {
    window.location.href = `admin.php?page=bp_bookings_edit&id=${id}`;
  }

  function onSearchSubmit(e) {
    e.preventDefault();
    setPage(1);
    load({ page: 1 });
    setFiltersOpen(false);
  }

  function resetFilters() {
    setQ("");
    setStatus("all");
    setDateFrom("");
    setDateTo("");
    setSortState("desc");
    setPage(1);
    load({ q: "", status: "all", dateFrom: "", dateTo: "", sort: "desc", page: 1 });
    setFiltersOpen(false);
  }

  const activeFiltersLabel = useMemo(() => {
    const parts = [];
    if (status && status !== "all") parts.push(`Status: ${status}`);
    if (dateFrom) parts.push(`From: ${dateFrom}`);
    if (dateTo) parts.push(`To: ${dateTo}`);
    if (q.trim()) parts.push(`Search: ${q.trim()}`);
    return parts.length ? parts.join(" | ") : "All bookings";
  }, [status, dateFrom, dateTo, q]);

  const hasActiveFilters = !!(q.trim() || (status && status !== "all") || dateFrom || dateTo || sort !== "desc");

  const showingFrom = total === 0 ? 0 : (page - 1) * per + 1;
  const showingTo = Math.min(total, (page - 1) * per + (items?.length || 0));

  return (
    <div className="myplugin-page bp-bookings">
      <main className="myplugin-content">
        <div className="bp-page-head bp-bookings__head">
          <div>
            <div className="bp-h1">Bookings</div>
            <div className="bp-muted">Search, filter, and manage appointments</div>
          </div>
          <div className="bp-bookings__head-actions">
            <a className="bp-primary-btn" href="admin.php?page=bp_bookings_edit">
              + Booking
            </a>
          </div>
        </div>

        {err ? <div className="bp-error">{err}</div> : null}

        <div className="bp-cards bp-bookings__kpis">
          <div className="bp-card">
            <div className="bp-card-label">Total (filtered)</div>
            <div className="bp-card-value">{loading ? "..." : stats.total}</div>
          </div>
          <div className="bp-card">
            <div className="bp-card-label">Pending (page)</div>
            <div className="bp-card-value">{loading ? "..." : stats.pending}</div>
          </div>
          <div className="bp-card">
            <div className="bp-card-label">Confirmed (page)</div>
            <div className="bp-card-value">{loading ? "..." : stats.confirmed}</div>
          </div>
          <div className="bp-card">
            <div className="bp-card-label">Cancelled (page)</div>
            <div className="bp-card-value">{loading ? "..." : stats.cancelled}</div>
          </div>
        </div>

        {!isMobile ? (
          <div className="bp-card bp-bookings__filters">
            <form className="bp-filters" onSubmit={onSearchSubmit}>
              <div className="bp-filter-group">
                <label className="bp-filter-label">Search</label>
                <input
                  className="bp-input"
                  placeholder="Search customer, email, service, agent..."
                  value={q}
                  onChange={(e) => setQ(e.target.value)}
                />
              </div>

              <div className="bp-filter-group">
                <label className="bp-filter-label">Status</label>
                <select className="bp-input" value={status} onChange={(e) => setStatus(e.target.value)}>
                  <option value="all">All statuses</option>
                  <option value="pending">Pending</option>
                  <option value="confirmed">Confirmed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>

              <div className="bp-filter-group">
                <label className="bp-filter-label">From date</label>
                <input className="bp-input" type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
              </div>

              <div className="bp-filter-group">
                <label className="bp-filter-label">To date</label>
                <input className="bp-input" type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
              </div>

              <div className="bp-filter-group">
                <label className="bp-filter-label">Sort by</label>
                <select className="bp-input" value={sort} onChange={(e) => setSortState(e.target.value)}>
                  <option value="desc">Newest created first</option>
                  <option value="asc">Oldest created first</option>
                </select>
              </div>

              <div className="bp-filter-group">
                <label className="bp-filter-label">&nbsp;</label>
                <div style={{ display: "flex", gap: 10, justifyContent: "flex-end" }}>
                  <button className="bp-btn" type="button" onClick={resetFilters} disabled={!hasActiveFilters}>
                    Clear
                  </button>
                  <button className="bp-primary-btn" type="submit">
                    Apply
                  </button>
                </div>
              </div>
            </form>

            {hasActiveFilters ? (
              <div className="bp-bookings__chips" style={{ display: "flex", flexWrap: "wrap", gap: 8, paddingTop: 12 }}>
                {status && status !== "all" ? (
                  <button className="bp-chip-btn" type="button" onClick={() => { setStatus("all"); setPage(1); load({ status: "all", page: 1 }); }}>
                    Status: {status} ×
                  </button>
                ) : null}
                {dateFrom ? (
                  <button className="bp-chip-btn" type="button" onClick={() => { setDateFrom(""); setPage(1); load({ dateFrom: "", page: 1 }); }}>
                    From: {dateFrom} ×
                  </button>
                ) : null}
                {dateTo ? (
                  <button className="bp-chip-btn" type="button" onClick={() => { setDateTo(""); setPage(1); load({ dateTo: "", page: 1 }); }}>
                    To: {dateTo} ×
                  </button>
                ) : null}
                {q.trim() ? (
                  <button className="bp-chip-btn" type="button" onClick={() => { setQ(""); setPage(1); load({ q: "", page: 1 }); }}>
                    Search ×
                  </button>
                ) : null}
                {sort !== "desc" ? (
                  <button className="bp-chip-btn" type="button" onClick={() => { setSortState("desc"); setPage(1); load({ sort: "desc", page: 1 }); }}>
                    Sort: oldest ×
                  </button>
                ) : null}
              </div>
            ) : null}
          </div>
        ) : (
          <div className="bp-card bp-bookings__toolbar">
            <div className="bp-bookings__toolbar-row">
              <button className="bp-btn bp-btn-ghost" type="button" onClick={() => setFiltersOpen(true)}>
                Filters
              </button>
              <div className="bp-muted bp-bookings__toolbar-meta">{activeFiltersLabel}</div>
            </div>
          </div>
        )}

        <div className="bp-card bp-bookings__list">
          {!isMobile ? (
            <div className="bp-table-scroll">
              <div className="bp-table bp-bookings-table">
              <div className="bp-tr bp-th">
                <div>ID</div>
                <div>When</div>
                <div>Service</div>
                <div>Customer</div>
                <div>Status</div>
                <div style={{ justifySelf: "end" }}>Actions</div>
              </div>

              {loading ? <div className="bp-muted" style={{ padding: 10 }}>Loading...</div> : null}

              {!loading &&
                items.map((b) => (
                  <div
                    key={b.id}
                    className="bp-tr bp-tr-btn"
                    role="button"
                    tabIndex={0}
                    onClick={() => setSelectedId(Number(b.id))}
                    onKeyDown={(e) => {
                      if (e.key === "Enter" || e.key === " ") setSelectedId(Number(b.id));
                    }}
                  >
                    <div className="bp-bookings-cell-id">#{b.id}</div>
                    <div className="bp-muted bp-bookings-cell-when">{fmtWhen(b.start_datetime || b.start)}</div>
                    <div className="bp-bookings-cell-service">
                      <div style={{ fontWeight: 900 }}>{b.service_name || "-"}</div>
                      <div className="bp-muted bp-bookings-sub">{b.agent_name || "-"}</div>
                    </div>
                    <div>
                      <div style={{ fontWeight: 1100 }}>{b.customer_name || "-"}</div>
                      <div className="bp-muted bp-bookings-sub">
                        {b.customer_email || "-"}
                      </div>
                    </div>
                    <div>
                      <Badge status={b.status} />
                    </div>
                    <div style={{ display: "flex", gap: 8, justifyContent: "flex-end", justifySelf: "end", flexWrap: "wrap" }}>
                      <button
                        className="bp-btn"
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          setSelectedId(Number(b.id));
                        }}
                      >
                        View
                      </button>
                      <button
                        className="bp-btn"
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          goEdit(b.id);
                        }}
                      >
                        Edit
                      </button>
                    </div>
                  </div>
                ))}

              {!loading && items.length === 0 ? (
                <div className="bp-muted" style={{ padding: 10 }}>
                  No bookings found.
                </div>
              ) : null}
              </div>
            </div>
          ) : (
            <div className="bp-bookings__cards">
              {loading ? <div className="bp-muted" style={{ padding: 12 }}>Loading...</div> : null}

              {!loading &&
                items.map((b) => (
                  <button key={b.id} type="button" className="bp-booking-card" onClick={() => setSelectedId(Number(b.id))}>
                    <div className="bp-booking-card__top">
                      <div className="bp-booking-card__id">#{b.id}</div>
                      <Badge status={b.status} />
                    </div>
                    <div className="bp-booking-card__mid">
                      <div className="bp-booking-card__service">{b.service_name || "-"}</div>
                      <div className="bp-booking-card__customer">{b.customer_name || "-"}</div>
                      {b.customer_email ? <div className="bp-booking-card__email">{b.customer_email}</div> : null}
                    </div>
                    <div className="bp-booking-card__bottom">
                      <div className="bp-booking-card__when">{b.start_datetime || "-"}</div>
                      <div className="bp-booking-card__agent">{b.agent_name || "-"}</div>
                    </div>
                  </button>
                ))}

              {!loading && items.length === 0 ? (
                <div className="bp-muted" style={{ padding: 12 }}>
                  No bookings found.
                </div>
              ) : null}
            </div>
          )}

          <div className="bp-pager">
            <button className="bp-top-btn" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}>
              ← Previous
            </button>
            <div className="bp-pager-info bp-muted">
              Page {page} / {pages}
              <div className="bp-pager-sub">
                Showing {showingFrom}–{showingTo} of {total}
              </div>
            </div>
            <button className="bp-top-btn" disabled={page >= pages} onClick={() => setPage((p) => Math.min(pages, p + 1))}>
              Next →
            </button>
          </div>
        </div>

        {selectedId ? (
          <BookingDrawer
            bookingId={selectedId}
            onClose={() => setSelectedId(null)}
            onUpdated={() => load()}
          />
        ) : null}

        {isMobile && filtersOpen ? (
          <div className="bp-sheet" onMouseDown={(e) => { if (e.target.classList.contains("bp-sheet")) setFiltersOpen(false); }}>
            <div className="bp-sheet-card">
              <div className="bp-sheet-head">
                <div className="bp-h2">Filters</div>
                <button className="bp-top-btn" type="button" onClick={() => setFiltersOpen(false)}>
                  Close
                </button>
              </div>
              <form className="bp-sheet-body" onSubmit={onSearchSubmit}>
                <input
                  className="bp-input"
                  placeholder="Search customer, email, service, agent..."
                  value={q}
                  onChange={(e) => setQ(e.target.value)}
                />
                <select className="bp-input" value={status} onChange={(e) => setStatus(e.target.value)}>
                  <option value="all">All statuses</option>
                  <option value="pending">Pending</option>
                  <option value="confirmed">Confirmed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
                <div className="bp-sheet-grid2">
                  <input className="bp-input" type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
                  <input className="bp-input" type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
                </div>
                <select className="bp-input" value={sort} onChange={(e) => setSortState(e.target.value)}>
                  <option value="desc">Newest created first</option>
                  <option value="asc">Oldest created first</option>
                </select>
                <div className="bp-sheet-actions">
                  <button className="bp-btn bp-btn-ghost" type="button" onClick={resetFilters}>
                    Reset
                  </button>
                  <button className="bp-btn bp-btn-primary" type="submit">
                    Apply
                  </button>
                </div>
              </form>
            </div>
          </div>
        ) : null}
      </main>
    </div>
  );
}
