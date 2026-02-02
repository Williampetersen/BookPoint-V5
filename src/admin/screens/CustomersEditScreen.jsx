import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";

function normalizeCustomer(raw) {
  return {
    id: raw?.id ? Number(raw.id) : 0,
    first_name: (raw?.first_name ?? "").toString(),
    last_name: (raw?.last_name ?? "").toString(),
    email: (raw?.email ?? "").toString(),
    phone: (raw?.phone ?? "").toString(),
    custom_fields: raw?.custom_fields && typeof raw.custom_fields === "object" ? raw.custom_fields : {},
    created_at: raw?.created_at ?? null,
  };
}

function customerInitials(c) {
  const a = `${c.first_name || ""}`.trim();
  const b = `${c.last_name || ""}`.trim();
  const init = `${a ? a[0] : ""}${b ? b[0] : ""}`.trim();
  if (init) return init.toUpperCase();
  if (c.email) return String(c.email).trim().slice(0, 1).toUpperCase();
  return "C";
}

function fieldKey(f) {
  return f.field_key || f.name_key || `field_${f.id}`;
}

function safeOption(o) {
  if (typeof o === "string") return { value: o, label: o };
  if (!o || typeof o !== "object") return { value: "", label: "" };
  return { value: o.value ?? o.label ?? "", label: o.label ?? o.value ?? "" };
}

export default function CustomersEditScreen() {
  const params = new URLSearchParams(window.location.search);
  const id = Number(params.get("id") || 0) || 0;

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [toast, setToast] = useState("");
  const [dirty, setDirty] = useState(false);

  const [customer, setCustomer] = useState(() => normalizeCustomer({ id }));
  const [bookings, setBookings] = useState([]);
  const [fields, setFields] = useState([]);
  const [fieldSearch, setFieldSearch] = useState("");

  const title = id ? "Edit Customer" : "Add Customer";
  const breadcrumb = "Customers / Edit";

  useEffect(() => {
    let alive = true;
    (async () => {
      setLoading(true);
      setError("");
      try {
        const [fieldsRes, custRes] = await Promise.all([
          bpFetch("/admin/customers/form-fields").catch(() => ({ data: [] })),
          id ? bpFetch(`/admin/customers/${id}`) : Promise.resolve({ data: { customer: {}, bookings: [] } }),
        ]);

        if (!alive) return;

        const rawFields = Array.isArray(fieldsRes?.data) ? fieldsRes.data : [];
        const enabledFields = rawFields.filter((f) => Number(f.is_enabled ?? 1) === 1);
        setFields(enabledFields);

        const rawCustomer = custRes?.data?.customer || {};
        const rawBookings = custRes?.data?.bookings || [];

        const next = normalizeCustomer(rawCustomer);
        setCustomer((prev) => ({ ...prev, ...next, id: next.id || prev.id || id }));
        setBookings(Array.isArray(rawBookings) ? rawBookings : []);
        setDirty(false);
      } catch (e) {
        console.error(e);
        if (!alive) return;
        setError(e?.message || "Failed to load customer");
      } finally {
        if (alive) setLoading(false);
      }
    })();

    return () => {
      alive = false;
    };
  }, [id]);

  useEffect(() => {
    const onBeforeUnload = (e) => {
      if (!dirty) return;
      e.preventDefault();
      e.returnValue = "";
    };
    window.addEventListener("beforeunload", onBeforeUnload);
    return () => window.removeEventListener("beforeunload", onBeforeUnload);
  }, [dirty]);

  useEffect(() => {
    if (!toast) return;
    const t = setTimeout(() => setToast(""), 2600);
    return () => clearTimeout(t);
  }, [toast]);

  function update(patch) {
    setCustomer((prev) => ({ ...prev, ...patch }));
    setDirty(true);
  }

  function setCustomField(k, v) {
    setCustomer((prev) => ({
      ...prev,
      custom_fields: { ...(prev.custom_fields || {}), [k]: v },
    }));
    setDirty(true);
  }

  const filteredFields = useMemo(() => {
    const q = fieldSearch.trim().toLowerCase();
    if (!q) return fields;
    return (fields || []).filter((f) => `${f.label || ""} ${fieldKey(f)}`.toLowerCase().includes(q));
  }, [fields, fieldSearch]);

  async function onSave() {
    setSaving(true);
    setError("");
    try {
      const payload = {
        first_name: customer.first_name,
        last_name: customer.last_name,
        email: customer.email,
        phone: customer.phone,
        custom_fields: customer.custom_fields || {},
      };

      if (id) {
        await bpFetch(`/admin/customers/${id}`, { method: "PUT", body: payload });
        setDirty(false);
        setToast("Saved");
      } else {
        const res = await bpFetch("/admin/customers", { method: "POST", body: payload });
        const newId = Number(res?.data?.customer?.id || 0) || 0;
        if (newId) {
          window.location.href = `admin.php?page=bp_customers_edit&id=${newId}&saved=1`;
          return;
        }
        setToast("Saved");
        setDirty(false);
      }
    } catch (e) {
      setError(e?.message || "Save failed");
    } finally {
      setSaving(false);
    }
  }

  useEffect(() => {
    const p = new URLSearchParams(window.location.search);
    if (p.get("saved") === "1") {
      setToast("Saved");
      p.delete("saved");
      const next = `${window.location.pathname}?${p.toString()}`.replace(/\?$/, "");
      window.history.replaceState({}, "", next);
    }
  }, []);

  async function onDelete() {
    if (!id) return;
    // eslint-disable-next-line no-alert
    if (!confirm("Delete this customer? This will anonymize their data.")) return;
    setSaving(true);
    setError("");
    try {
      await bpFetch(`/admin/customers/${id}`, { method: "DELETE" });
      window.location.href = "admin.php?page=bp_customers";
    } catch (e) {
      setError(e?.message || "Delete failed");
    } finally {
      setSaving(false);
    }
  }

  const fullName = `${customer.first_name || ""} ${customer.last_name || ""}`.trim();
  const displayName = fullName || customer.email || (id ? `Customer #${id}` : "New customer");

  return (
    <form
      className="bp-customer-edit"
      onSubmit={(e) => {
        e.preventDefault();
        onSave();
      }}
    >
      <div className="bp-customer-edit__top">
        <div>
          <div className="bp-breadcrumb">{breadcrumb}</div>
          <div className="bp-h1">{title}</div>
          <div className="bp-muted">{displayName}</div>
        </div>

        <div className="bp-customer-edit__meta">
          {id ? <span className="bp-customer-edit__pill">#{id}</span> : <span className="bp-customer-edit__pill">New</span>}
        </div>
      </div>

      {toast ? <div className="bp-toast bp-toast-success">{toast}</div> : null}
      {error ? <div className="bp-error">{error}</div> : null}

      {loading ? (
        <div className="bp-card bp-customer-edit__section">
          <div className="bp-muted">Loading…</div>
        </div>
      ) : (
        <div className="bp-customer-edit__grid">
          <section className="bp-customer-edit__main">
            <div className="bp-card bp-customer-edit__section">
              <div className="bp-section-title">Basic info</div>
              <div className="bp-grid-2">
                <div>
                  <div className="bp-k">First name</div>
                  <input
                    className="bp-input"
                    value={customer.first_name}
                    onChange={(e) => update({ first_name: e.target.value })}
                    placeholder="First name"
                    autoComplete="off"
                  />
                </div>
                <div>
                  <div className="bp-k">Last name</div>
                  <input
                    className="bp-input"
                    value={customer.last_name}
                    onChange={(e) => update({ last_name: e.target.value })}
                    placeholder="Last name"
                    autoComplete="off"
                  />
                </div>
                <div>
                  <div className="bp-k">Email</div>
                  <input
                    className="bp-input"
                    type="email"
                    value={customer.email}
                    onChange={(e) => update({ email: e.target.value })}
                    placeholder="name@example.com"
                    autoComplete="off"
                  />
                </div>
                <div>
                  <div className="bp-k">Phone</div>
                  <input
                    className="bp-input"
                    value={customer.phone}
                    onChange={(e) => update({ phone: e.target.value })}
                    placeholder="Phone"
                    autoComplete="off"
                  />
                </div>
              </div>
            </div>

            <div className="bp-card bp-customer-edit__section">
              <div className="bp-customer-edit__section-head">
                <div className="bp-section-title" style={{ margin: 0 }}>
                  Custom fields
                </div>
                {fields.length ? (
                  <input
                    className="bp-input"
                    style={{ maxWidth: 320 }}
                    placeholder="Search fields…"
                    value={fieldSearch}
                    onChange={(e) => setFieldSearch(e.target.value)}
                  />
                ) : null}
              </div>

              {!fields.length ? (
                <div className="bp-muted">No customer custom fields configured.</div>
              ) : (
                <div className="bp-customer-edit__fields">
                  {filteredFields.map((f) => {
                    const k = fieldKey(f);
                    const label = f.label || k;
                    const type = f.type || "text";
                    const placeholder = f.placeholder || "";
                    const opts = Array.isArray(f.options) ? f.options.map(safeOption).filter((o) => o.value !== "") : [];
                    const v = customer.custom_fields?.[k];

                    if (type === "textarea") {
                      return (
                        <div key={k} className="bp-customer-edit__field">
                          <div className="bp-k">{label}</div>
                          <textarea
                            className="bp-textarea"
                            rows={4}
                            placeholder={placeholder}
                            value={(v ?? "").toString()}
                            onChange={(e) => setCustomField(k, e.target.value)}
                          />
                        </div>
                      );
                    }

                    if (type === "select") {
                      return (
                        <div key={k} className="bp-customer-edit__field">
                          <div className="bp-k">{label}</div>
                          <select
                            className="bp-input"
                            value={(v ?? "").toString()}
                            onChange={(e) => setCustomField(k, e.target.value)}
                          >
                            <option value="">Select…</option>
                            {opts.map((o) => (
                              <option key={`${k}-${o.value}`} value={o.value}>
                                {o.label}
                              </option>
                            ))}
                          </select>
                        </div>
                      );
                    }

                    if (type === "checkbox") {
                      if (opts.length) {
                        const arr = Array.isArray(v) ? v.map((x) => String(x)) : [];
                        return (
                          <div key={k} className="bp-customer-edit__field">
                            <div className="bp-k">{label}</div>
                            <div className="bp-customer-edit__checks">
                              {opts.map((o) => {
                                const checked = arr.includes(String(o.value));
                                return (
                                  <label key={`${k}-${o.value}`} className="bp-check">
                                    <input
                                      type="checkbox"
                                      checked={checked}
                                      onChange={(e) => {
                                        const next = e.target.checked
                                          ? Array.from(new Set([...arr, String(o.value)]))
                                          : arr.filter((x) => x !== String(o.value));
                                        setCustomField(k, next);
                                      }}
                                    />
                                    <span>{o.label}</span>
                                  </label>
                                );
                              })}
                            </div>
                          </div>
                        );
                      }

                      const boolVal = !!v;
                      return (
                        <div key={k} className="bp-customer-edit__field">
                          <label className="bp-check">
                            <input
                              type="checkbox"
                              checked={boolVal}
                              onChange={(e) => setCustomField(k, e.target.checked ? 1 : 0)}
                            />
                            <span>{label}</span>
                          </label>
                        </div>
                      );
                    }

                    const inputType =
                      type === "number"
                        ? "number"
                        : type === "date"
                          ? "date"
                          : type === "email"
                            ? "email"
                            : type === "tel"
                              ? "tel"
                              : "text";

                    return (
                      <div key={k} className="bp-customer-edit__field">
                        <div className="bp-k">{label}</div>
                        <input
                          className="bp-input"
                          type={inputType}
                          placeholder={placeholder}
                          value={(v ?? "").toString()}
                          onChange={(e) => setCustomField(k, e.target.value)}
                        />
                      </div>
                    );
                  })}
                </div>
              )}
            </div>

            {id ? (
              <div className="bp-card bp-customer-edit__section">
                <div className="bp-section-title">Booking history</div>
                <div className="bp-table-scroll">
                  <div className="bp-table">
                    <div className="bp-tr bp-th">
                      <div>ID</div>
                      <div>When</div>
                      <div>Service</div>
                      <div>Agent</div>
                      <div>Status</div>
                    </div>
                    {(bookings || []).slice(0, 50).map((b) => (
                      <a
                        key={b.id}
                        className="bp-tr"
                        href={`admin.php?page=bp_bookings_edit&id=${b.id}`}
                      >
                        <div>#{b.id}</div>
                        <div className="bp-muted">{b.start_datetime || "-"}</div>
                        <div>{b.service_name || "-"}</div>
                        <div>{b.agent_name || "-"}</div>
                        <div>
                          <span className={`bp-badge ${(b.status || "pending").toLowerCase()}`}>
                            {(b.status || "pending").toLowerCase()}
                          </span>
                        </div>
                      </a>
                    ))}
                    {!bookings || bookings.length === 0 ? (
                      <div className="bp-muted" style={{ padding: 10 }}>
                        No bookings yet.
                      </div>
                    ) : null}
                  </div>
                </div>
              </div>
            ) : null}
          </section>

          <aside className="bp-card bp-customer-edit__side">
            <div className="bp-customer-edit__profile">
              <div className="bp-customer-edit__avatar" aria-hidden="true">
                {customerInitials(customer)}
              </div>
              <div style={{ minWidth: 0 }}>
                <div className="bp-customer-edit__name">{displayName}</div>
                <div className="bp-muted">
                  {customer.email ? customer.email : " "}
                  {customer.phone ? ` • ${customer.phone}` : ""}
                </div>
              </div>
            </div>

            <div className="bp-customer-edit__stats">
              <div className="bp-customer-edit__stat">
                <div className="bp-customer-edit__statV">{bookings?.length || 0}</div>
                <div className="bp-customer-edit__statK">Bookings</div>
              </div>
              <div className="bp-customer-edit__stat">
                <div className="bp-customer-edit__statV">{customer.created_at ? String(customer.created_at).slice(0, 10) : "—"}</div>
                <div className="bp-customer-edit__statK">Created</div>
              </div>
            </div>

            {id ? (
              <div className="bp-customer-edit__danger">
                <div className="bp-muted" style={{ fontWeight: 900 }}>
                  Danger zone
                </div>
                <button type="button" className="bp-btn bp-btn-danger" onClick={onDelete} disabled={saving}>
                  Delete customer
                </button>
              </div>
            ) : null}
          </aside>
        </div>
      )}

      <div className="bp-customer-edit__bar">
        <a className="bp-btn bp-btn-ghost" href="admin.php?page=bp_customers">
          Cancel
        </a>
        <button type="submit" className="bp-btn bp-btn-primary" disabled={saving}>
          {saving ? "Saving..." : id ? "Save changes" : "Create customer"}
        </button>
      </div>
    </form>
  );
}
