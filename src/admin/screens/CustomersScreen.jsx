import React, { useEffect, useState } from "react";
import { bpFetch } from "../api/client";
import CustomerDrawer from "../components/CustomerDrawer";
import { Drawer } from "../ui/Drawer";

export default function CustomersScreen() {
  const [q, setQ] = useState("");
  const [sort, setSort] = useState("desc");
  const [page, setPage] = useState(1);
  const [per] = useState(25);

  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");
  const [items, setItems] = useState([]);
  const [total, setTotal] = useState(0);

  const [selectedId, setSelectedId] = useState(null);

  const [importOpen, setImportOpen] = useState(false);
  const [importFile, setImportFile] = useState(null);
  const [importErr, setImportErr] = useState("");

  async function load() {
    setLoading(true);
    setErr("");
    try {
      const url =
        `/admin/customers?` +
        `q=${encodeURIComponent(q)}` +
        `&sort=${encodeURIComponent(sort)}` +
        `&page=${page}&per=${per}`;

      const res = await bpFetch(url);
      setItems(res?.data?.items || []);
      setTotal(res?.data?.total || 0);
    } catch (e) {
      setErr(e?.message || "Failed to load customers.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, sort]);

  function onSearchSubmit(e) {
    e.preventDefault();
    setPage(1);
    load();
  }

  async function deleteCustomer(id) {
    if (!id) return;
    // eslint-disable-next-line no-alert
    if (!confirm("Delete this customer? This will anonymize their data.")) return;
    try {
      await bpFetch(`/admin/customers/${id}`, { method: "DELETE" });
      if (selectedId === id) setSelectedId(null);
      await load();
    } catch (e) {
      setErr(e?.message || "Failed to delete customer.");
    }
  }

  function goNew() {
    window.location.href = "admin.php?page=bp_customers_edit";
  }

  function goEdit(id) {
    window.location.href = `admin.php?page=bp_customers_edit&id=${id}`;
  }

  const adminPostUrl = window.BP_ADMIN?.adminPostUrl || "admin-post.php";
  const adminNonce = window.BP_ADMIN?.adminNonce || "";
  const exportUrl = `${adminPostUrl}?action=bp_admin_customers_export_csv&_wpnonce=${encodeURIComponent(adminNonce)}`;

  const pages = Math.max(1, Math.ceil(total / per));
  const showingFrom = total ? (page - 1) * per + 1 : 0;
  const showingTo = Math.min(total, page * per);
  const rowStyle = {
    display: "grid",
    gridTemplateColumns: "80px 1.4fr 1.2fr 1fr 90px 170px 240px",
    alignItems: "center",
    gap: 12,
  };

  return (
    <div className="myplugin-page bp-customers">
      <main className="myplugin-content">
      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Customers</div>
          <div className="bp-muted">View and manage customer profiles.</div>
        </div>
        <div className="bp-head-actions">
          <a className="bp-btn" href={exportUrl}>
            Export CSV
          </a>
          <button
            className="bp-btn"
            onClick={() => {
              setImportErr("");
              setImportOpen(true);
            }}
          >
            Import CSV
          </button>
          <button className="bp-btn bp-btn-primary" onClick={goNew}>
            New Customer
          </button>
        </div>
      </div>

      {err ? <div className="bp-error">{err}</div> : null}

      <div className="bp-cards" style={{ marginBottom: 14 }}>
        <div className="bp-card">
          <div className="bp-card-label">Total (filtered)</div>
          <div className="bp-card-value">{loading ? "…" : total}</div>
        </div>
      </div>

      <div className="bp-card" style={{ marginBottom: 14 }}>
        <form className="bp-filters" onSubmit={onSearchSubmit}>
          <input
            className="bp-input"
            placeholder="Search name, email, phone…"
            value={q}
            onChange={(e) => setQ(e.target.value)}
          />

          <select
            className="bp-input"
            value={sort}
            onChange={(e) => {
              setSort(e.target.value);
              setPage(1);
            }}
          >
            <option value="desc">Latest first</option>
            <option value="asc">Earliest first</option>
          </select>

          <button className="bp-primary-btn" type="submit">
            Search
          </button>
        </form>
      </div>

      <div className="bp-card">
        <div className="bp-table">
          <div className="bp-tr bp-th" style={rowStyle}>
            <div>ID</div>
            <div>Name</div>
            <div>Email</div>
            <div>Phone</div>
            <div>Bookings</div>
            <div>Created</div>
            <div>Actions</div>
          </div>

          {loading ? <div className="bp-muted" style={{ padding: 10 }}>Loading…</div> : null}

          {!loading &&
            items.map((c) => {
              const fullName = `${c.first_name || ""} ${c.last_name || ""}`.trim();
              const name = fullName || c.name || `#${c.id}`;
              const bookings = c.bookings_count ?? c.booking_count ?? 0;
              return (
                <div key={c.id} className="bp-tr" style={rowStyle}>
                  <div>#{c.id}</div>
                  <div>
                    <div style={{ fontWeight: 1100 }}>{name}</div>
                  </div>
                  <div>{c.email || ""}</div>
                  <div>{c.phone || ""}</div>
                  <div>{bookings}</div>
                  <div className="bp-muted">{c.created_at || "?"}</div>
                  <div style={{ display: "flex", gap: 6, justifyContent: "flex-end", justifySelf: "end" }}>
                    <button className="bp-btn" onClick={() => setSelectedId(c.id)}>
                      View
                    </button>
                    <button className="bp-btn" onClick={() => goEdit(c.id)}>
                      Edit
                    </button>
                    <button className="bp-btn" onClick={() => deleteCustomer(c.id)}>
                      Delete
                    </button>
                  </div>
                </div>
              );
            })}

          {!loading && items.length === 0 ? (
            <div className="bp-muted" style={{ padding: 10 }}>
              No customers found.
            </div>
          ) : null}
        </div>

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

      <CustomerDrawer customerId={selectedId} onClose={() => setSelectedId(null)} />

      <Drawer
        open={importOpen}
        title="Import Customers"
        onClose={() => setImportOpen(false)}
        footer={
          <div style={{ display: "flex", gap: 8, justifyContent: "flex-end" }}>
            <button className="bp-btn" onClick={() => setImportOpen(false)}>
              Cancel
            </button>
            <button className="bp-btn bp-btn-primary" form="bp-customer-import-form" type="submit">
              Continue
            </button>
          </div>
        }
      >
        {importErr ? <div className="bp-error">{importErr}</div> : null}
        <form
          id="bp-customer-import-form"
          method="post"
          encType="multipart/form-data"
          action={adminPostUrl}
          onSubmit={(e) => {
            if (!importFile) {
              e.preventDefault();
              setImportErr("Please choose a CSV file.");
            }
          }}
        >
          <input type="hidden" name="action" value="bp_admin_customers_import_csv" />
          <input type="hidden" name="_wpnonce" value={adminNonce} />
          <div className="bp-card" style={{ marginBottom: 12 }}>
            <div className="bp-muted" style={{ fontSize: 12, marginBottom: 6 }}>
              Select CSV file to upload
            </div>
            <input
              type="file"
              name="csv"
              accept=".csv"
              onChange={(e) => {
                const file = e.target.files && e.target.files[0] ? e.target.files[0] : null;
                setImportFile(file);
                if (file) setImportErr("");
              }}
            />
          </div>
        </form>
      </Drawer>
      </main>
    </div>
  );
}
