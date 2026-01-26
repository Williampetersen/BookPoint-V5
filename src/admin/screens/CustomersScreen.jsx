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
  const [createOpen, setCreateOpen] = useState(false);
  const [editOpen, setEditOpen] = useState(false);
  const [editId, setEditId] = useState(null);
  const [editLoading, setEditLoading] = useState(false);
  const [editErr, setEditErr] = useState("");
  const [editForm, setEditForm] = useState({ first_name: "", last_name: "", email: "", phone: "" });
  const [editCustomValues, setEditCustomValues] = useState({});
  const [importOpen, setImportOpen] = useState(false);
  const [importFile, setImportFile] = useState(null);
  const [importErr, setImportErr] = useState("");
  const [saving, setSaving] = useState(false);
  const [saveErr, setSaveErr] = useState("");
  const [form, setForm] = useState({ first_name: "", last_name: "", email: "", phone: "" });
  const [customFields, setCustomFields] = useState([]);
  const [customValues, setCustomValues] = useState({});

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

  useEffect(() => {
    if (!createOpen) return;
    if (customFields.length) return;
    (async () => {
      try {
        const res = await bpFetch("/admin/customers/form-fields");
        const fields = (res?.data || []).filter((f) => (f.is_enabled ?? 1) === 1);
        setCustomFields(fields);
      } catch (e) {
        // ignore, custom fields are optional
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [createOpen]);

  useEffect(() => {
    if (!editOpen || !editId) return;
    (async () => {
      setEditLoading(true);
      setEditErr("");
      try {
        const [customerRes, fieldsRes] = await Promise.all([
          bpFetch(`/admin/customers/${editId}`),
          customFields.length ? Promise.resolve({ data: customFields }) : bpFetch("/admin/customers/form-fields"),
        ]);

        const customer = customerRes?.data?.customer || {};
        setEditForm({
          first_name: customer.first_name || "",
          last_name: customer.last_name || "",
          email: customer.email || "",
          phone: customer.phone || "",
        });
        setEditCustomValues(customer.custom_fields || {});

        if (!customFields.length) {
          const fields = (fieldsRes?.data || []).filter((f) => (f.is_enabled ?? 1) === 1);
          setCustomFields(fields);
        }
      } catch (e) {
        setEditErr(e?.message || "Failed to load customer.");
      } finally {
        setEditLoading(false);
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editOpen, editId]);

  function onSearchSubmit(e) {
    e.preventDefault();
    setPage(1);
    load();
  }

  const adminPostUrl = window.BP_ADMIN?.adminPostUrl || "admin-post.php";
  const adminNonce = window.BP_ADMIN?.adminNonce || "";
  const exportUrl = `${adminPostUrl}?action=bp_admin_customers_export_csv&_wpnonce=${encodeURIComponent(adminNonce)}`;

  async function saveCustomer() {
    setSaving(true);
    setSaveErr("");
    try {
      const res = await bpFetch("/admin/customers", { method: "POST", body: { ...form, custom_fields: customValues } });
      setCreateOpen(false);
      setForm({ first_name: "", last_name: "", email: "", phone: "" });
      setCustomValues({});
      if (res?.data?.customer?.id) {
        setSelectedId(res.data.customer.id);
      }
      await load();
    } catch (e) {
      setSaveErr(e?.message || "Failed to create customer.");
    } finally {
      setSaving(false);
    }
  }

  async function updateCustomer() {
    if (!editId) return;
    setSaving(true);
    setEditErr("");
    try {
      await bpFetch(`/admin/customers/${editId}`, {
        method: "PUT",
        body: { ...editForm, custom_fields: editCustomValues },
      });
      setEditOpen(false);
      setEditId(null);
      await load();
    } catch (e) {
      setEditErr(e?.message || "Failed to update customer.");
    } finally {
      setSaving(false);
    }
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

  const pages = Math.max(1, Math.ceil(total / per));
  const rowStyle = {
    display: "grid",
    gridTemplateColumns: "80px 1.4fr 1.2fr 1fr 90px 170px 240px",
    alignItems: "center",
    gap: 12,
  };

  return (
    <div>
      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Customers</div>
          <div className="bp-muted">View and manage customer profiles.</div>
        </div>
        <div className="bp-head-actions">
          <a className="bp-btn" href={exportUrl}>Export CSV</a>
          <button className="bp-btn" onClick={() => { setImportErr(""); setImportOpen(true); }}>Import CSV</button>
          <button className="bp-btn bp-btn-primary" onClick={() => setCreateOpen(true)}>New Customer</button>
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

          <button className="bp-primary-btn" type="submit">Search</button>
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

          {!loading && items.map((c) => {
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
                  <button className="bp-btn" onClick={() => setSelectedId(c.id)}>View</button>
                  <button className="bp-btn" onClick={() => { setEditId(c.id); setEditOpen(true); }}>Edit</button>
                  <button className="bp-btn" onClick={() => deleteCustomer(c.id)}>Delete</button>
                </div>
              </div>
            );

          })}

          {!loading && items.length === 0 ? (
            <div className="bp-muted" style={{ padding: 10 }}>No customers found.</div>
          ) : null}
        </div>

        <div className="bp-pager">
          <button className="bp-top-btn" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}>Prev</button>
          <div className="bp-muted" style={{ fontWeight: 1000 }}>Page {page} / {pages}</div>
          <button className="bp-top-btn" disabled={page >= pages} onClick={() => setPage((p) => Math.min(pages, p + 1))}>Next</button>
        </div>
      </div>

      <CustomerDrawer customerId={selectedId} onClose={() => setSelectedId(null)} />

      <Drawer open={createOpen} title="New Customer" onClose={() => setCreateOpen(false)} footer={
        <div style={{ display: "flex", gap: 8, justifyContent: "flex-end" }}>
          <button className="bp-btn" onClick={() => setCreateOpen(false)} disabled={saving}>Cancel</button>
          <button className="bp-btn bp-btn-primary" onClick={saveCustomer} disabled={saving}>Save</button>
        </div>
      }>
        {saveErr ? <div className="bp-error">{saveErr}</div> : null}
        <div className="bp-card" style={{ marginBottom: 12 }}>
          <div className="bp-muted" style={{ fontSize: 12 }}>First Name</div>
          <input className="bp-input" value={form.first_name} onChange={(e) => setForm({ ...form, first_name: e.target.value })} />
        </div>
        <div className="bp-card" style={{ marginBottom: 12 }}>
          <div className="bp-muted" style={{ fontSize: 12 }}>Last Name</div>
          <input className="bp-input" value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} />
        </div>
        <div className="bp-card" style={{ marginBottom: 12 }}>
          <div className="bp-muted" style={{ fontSize: 12 }}>Email</div>
          <input className="bp-input" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
        </div>
        <div className="bp-card" style={{ marginBottom: 12 }}>
          <div className="bp-muted" style={{ fontSize: 12 }}>Phone</div>
          <input className="bp-input" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
        </div>

        {customFields.length ? (
          <div className="bp-card" style={{ marginBottom: 12 }}>
            <div style={{ fontWeight: 1000, marginBottom: 8 }}>Custom Fields</div>
            {customFields.map((f) => {
              const key = f.field_key || f.name_key || `field_${f.id}`;
              const label = f.label || key;
              const type = f.type || "text";
              const placeholder = f.placeholder || "";
              const opts = Array.isArray(f.options) ? f.options : [];
              const value = customValues[key] ?? (type === "checkbox" ? [] : "");

              if (type === "textarea") {
                return (
                  <div key={key} style={{ marginBottom: 12 }}>
                    <div className="bp-muted" style={{ fontSize: 12 }}>{label}</div>
                    <textarea className="bp-input" rows={3} placeholder={placeholder} value={value} onChange={(e) => setCustomValues({ ...customValues, [key]: e.target.value })} />
                  </div>
                );
              }

              if (type === "select") {
                return (
                  <div key={key} style={{ marginBottom: 12 }}>
                    <div className="bp-muted" style={{ fontSize: 12 }}>{label}</div>
                    <select className="bp-input" value={value} onChange={(e) => setCustomValues({ ...customValues, [key]: e.target.value })}>
                      <option value="">Select…</option>
                      {opts.map((o, idx) => {
                        const optValue = typeof o === "string" ? o : (o?.value ?? o?.label ?? "");
                        const optLabel = typeof o === "string" ? o : (o?.label ?? o?.value ?? "");
                        return <option key={`${key}-${idx}`} value={optValue}>{optLabel}</option>;
                      })}
                    </select>
                  </div>
                );
              }

              if (type === "checkbox") {
                if (opts.length) {
                  const arr = Array.isArray(value) ? value : [];
                  return (
                    <div key={key} style={{ marginBottom: 12 }}>
                      <div className="bp-muted" style={{ fontSize: 12 }}>{label}</div>
                      {opts.map((o, idx) => {
                        const optValue = typeof o === "string" ? o : (o?.value ?? o?.label ?? "");
                        const optLabel = typeof o === "string" ? o : (o?.label ?? o?.value ?? "");
                        const checked = arr.includes(optValue);
                        return (
                          <label key={`${key}-${idx}`} style={{ display: "flex", gap: 8, alignItems: "center", marginTop: 6 }}>
                            <input
                              type="checkbox"
                              checked={checked}
                              onChange={(e) => {
                                const next = e.target.checked
                                  ? [...arr, optValue]
                                  : arr.filter((v) => v !== optValue);
                                setCustomValues({ ...customValues, [key]: next });
                              }}
                            />
                            <span>{optLabel}</span>
                          </label>
                        );
                      })}
                    </div>
                  );
                }

                const boolVal = !!customValues[key];
                return (
                  <label key={key} style={{ display: "flex", gap: 8, alignItems: "center", marginBottom: 12 }}>
                    <input
                      type="checkbox"
                      checked={boolVal}
                      onChange={(e) => setCustomValues({ ...customValues, [key]: e.target.checked })}
                    />
                    <span>{label}</span>
                  </label>
                );
              }

              return (
                <div key={key} style={{ marginBottom: 12 }}>
                  <div className="bp-muted" style={{ fontSize: 12 }}>{label}</div>
                  <input
                    className="bp-input"
                    type={type === "number" ? "number" : type === "date" ? "date" : type === "email" ? "email" : type === "tel" ? "tel" : "text"}
                    placeholder={placeholder}
                    value={value}
                    onChange={(e) => setCustomValues({ ...customValues, [key]: e.target.value })}
                  />
                </div>
              );
            })}
          </div>
        ) : null}
      </Drawer>

      <Drawer open={editOpen} title="Edit Customer" onClose={() => setEditOpen(false)} footer={
        <div style={{ display: "flex", gap: 8, justifyContent: "flex-end" }}>
          <button className="bp-btn" onClick={() => setEditOpen(false)} disabled={saving}>Cancel</button>
          <button className="bp-btn bp-btn-primary" onClick={updateCustomer} disabled={saving || editLoading}>Save</button>
        </div>
      }>
        {editErr ? <div className="bp-error">{editErr}</div> : null}
        {editLoading ? <div className="bp-muted">Loadingâ€¦</div> : null}

        {!editLoading ? (
          <div>
            <div className="bp-card" style={{ marginBottom: 12 }}>
              <div className="bp-muted" style={{ fontSize: 12 }}>First Name</div>
              <input className="bp-input" value={editForm.first_name} onChange={(e) => setEditForm({ ...editForm, first_name: e.target.value })} />
            </div>
            <div className="bp-card" style={{ marginBottom: 12 }}>
              <div className="bp-muted" style={{ fontSize: 12 }}>Last Name</div>
              <input className="bp-input" value={editForm.last_name} onChange={(e) => setEditForm({ ...editForm, last_name: e.target.value })} />
            </div>
            <div className="bp-card" style={{ marginBottom: 12 }}>
              <div className="bp-muted" style={{ fontSize: 12 }}>Email</div>
              <input className="bp-input" value={editForm.email} onChange={(e) => setEditForm({ ...editForm, email: e.target.value })} />
            </div>
            <div className="bp-card" style={{ marginBottom: 12 }}>
              <div className="bp-muted" style={{ fontSize: 12 }}>Phone</div>
              <input className="bp-input" value={editForm.phone} onChange={(e) => setEditForm({ ...editForm, phone: e.target.value })} />
            </div>

            {customFields.length ? (
              <div className="bp-card" style={{ marginBottom: 12 }}>
                <div style={{ fontWeight: 1000, marginBottom: 8 }}>Custom Fields</div>
                {customFields.map((f) => {
                  const key = f.field_key || f.name_key || `field_${f.id}`;
                  const label = f.label || key;
                  const type = f.type || "text";
                  const placeholder = f.placeholder || "";
                  const opts = Array.isArray(f.options) ? f.options : [];
                  const value = editCustomValues[key] ?? (type === "checkbox" ? [] : "");

                  if (type === "textarea") {
                    return (
                      <div key={key} style={{ marginBottom: 12 }}>
                        <div className="bp-muted" style={{ fontSize: 12 }}>{label}</div>
                        <textarea className="bp-input" rows={3} placeholder={placeholder} value={value} onChange={(e) => setEditCustomValues({ ...editCustomValues, [key]: e.target.value })} />
                      </div>
                    );
                  }

                  if (type === "select") {
                    return (
                      <div key={key} style={{ marginBottom: 12 }}>
                        <div className="bp-muted" style={{ fontSize: 12 }}>{label}</div>
                        <select className="bp-input" value={value} onChange={(e) => setEditCustomValues({ ...editCustomValues, [key]: e.target.value })}>
                          <option value="">Selectâ€¦</option>
                          {opts.map((o, idx) => {
                            const optValue = typeof o === "string" ? o : (o?.value ?? o?.label ?? "");
                            const optLabel = typeof o === "string" ? o : (o?.label ?? o?.value ?? "");
                            return <option key={`${key}-${idx}`} value={optValue}>{optLabel}</option>;
                          })}
                        </select>
                      </div>
                    );
                  }

                  if (type === "checkbox") {
                    if (opts.length) {
                      const arr = Array.isArray(value) ? value : [];
                      return (
                        <div key={key} style={{ marginBottom: 12 }}>
                          <div className="bp-muted" style={{ fontSize: 12 }}>{label}</div>
                          {opts.map((o, idx) => {
                            const optValue = typeof o === "string" ? o : (o?.value ?? o?.label ?? "");
                            const optLabel = typeof o === "string" ? o : (o?.label ?? o?.value ?? "");
                            const checked = arr.includes(optValue);
                            return (
                              <label key={`${key}-${idx}`} style={{ display: "flex", gap: 8, alignItems: "center", marginTop: 6 }}>
                                <input
                                  type="checkbox"
                                  checked={checked}
                                  onChange={(e) => {
                                    const next = e.target.checked
                                      ? [...arr, optValue]
                                      : arr.filter((v) => v !== optValue);
                                    setEditCustomValues({ ...editCustomValues, [key]: next });
                                  }}
                                />
                                <span>{optLabel}</span>
                              </label>
                            );
                          })}
                        </div>
                      );
                    }

                    const boolVal = !!editCustomValues[key];
                    return (
                      <label key={key} style={{ display: "flex", gap: 8, alignItems: "center", marginBottom: 12 }}>
                        <input
                          type="checkbox"
                          checked={boolVal}
                          onChange={(e) => setEditCustomValues({ ...editCustomValues, [key]: e.target.checked })}
                        />
                        <span>{label}</span>
                      </label>
                    );
                  }

                  return (
                    <div key={key} style={{ marginBottom: 12 }}>
                      <div className="bp-muted" style={{ fontSize: 12 }}>{label}</div>
                      <input
                        className="bp-input"
                        type={type === "number" ? "number" : type === "date" ? "date" : type === "email" ? "email" : type === "tel" ? "tel" : "text"}
                        placeholder={placeholder}
                        value={value}
                        onChange={(e) => setEditCustomValues({ ...editCustomValues, [key]: e.target.value })}
                      />
                    </div>
                  );
                })}
              </div>
            ) : null}
          </div>
        ) : null}
      </Drawer>

      <Drawer open={importOpen} title="Import Customers" onClose={() => setImportOpen(false)} footer={
        <div style={{ display: "flex", gap: 8, justifyContent: "flex-end" }}>
          <button className="bp-btn" onClick={() => setImportOpen(false)}>Cancel</button>
          <button className="bp-btn bp-btn-primary" form="bp-customer-import-form" type="submit">Continue</button>
        </div>
      }>
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
            <div className="bp-muted" style={{ fontSize: 12, marginBottom: 6 }}>Select CSV file to upload</div>
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
    </div>
  );
}
