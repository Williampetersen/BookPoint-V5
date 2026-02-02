import React, { useEffect, useMemo, useState } from "react";
import { bpFetch } from "../api/client";
import WorkflowEditScreen from "./WorkflowEditScreen";

const EVENT_LABELS = {
  booking_created: "Booking Created",
  booking_updated: "Booking Updated",
  booking_confirmed: "Booking Confirmed",
  booking_cancelled: "Booking Cancelled",
  customer_created: "Customer Created",
};

export default function NotificationsScreen() {
  const [workflows, setWorkflows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [toast, setToast] = useState("");

  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [eventFilter, setEventFilter] = useState("");

  const [selectedId, setSelectedId] = useState(null);
  const [showEditor, setShowEditor] = useState(false);
  const [tick, setTick] = useState(0);
  const [meta, setMeta] = useState({ events: Object.keys(EVENT_LABELS), counts: {} });

  const [templatesOpen, setTemplatesOpen] = useState(false);
  const [busyId, setBusyId] = useState(null);

  const showToast = (msg) => {
    setToast(msg);
    setTimeout(() => setToast(""), 2500);
  };

  useEffect(() => {
    loadWorkflows();
    loadMeta();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search, statusFilter, eventFilter, tick]);

  async function loadWorkflows() {
    setLoading(true);
    setError("");
    try {
      const query = new URLSearchParams();
      if (search) query.set("q", search);
      if (statusFilter && statusFilter !== "all") query.set("status", statusFilter);
      if (eventFilter) query.set("event", eventFilter);
      query.set("page", "1");
      query.set("per", "50");
      const resp = await bpFetch(`/admin/notifications/workflows?${query.toString()}`);
      setWorkflows(resp?.data?.items || []);
    } catch (err) {
      setWorkflows([]);
      setError(err.message || "Failed to load workflows.");
    } finally {
      setLoading(false);
    }
  }

  async function loadMeta() {
    try {
      const resp = await bpFetch("/admin/notifications/meta");
      if (resp?.data) setMeta(resp.data);
    } catch (_) {
      // optional
    }
  }

  function openEditor(id) {
    setSelectedId(id);
    setShowEditor(true);
    window.location.hash = `#/notifications/${id}`;
  }

  function closeEditor() {
    setShowEditor(false);
    setSelectedId(null);
    window.history.replaceState(null, "", window.location.pathname);
  }

  async function addWorkflow() {
    setError("");
    try {
      const resp = await bpFetch("/admin/notifications/workflows", {
        method: "POST",
        body: {
          name: "New workflow",
          event_key: "booking_created",
          status: "active",
        },
      });
      if (resp?.data?.id) {
        setTick((prev) => prev + 1);
        openEditor(resp.data.id);
      }
    } catch (err) {
      setError(err.message || "Could not create workflow.");
    }
  }

  async function toggleWorkflowStatus(workflow) {
    const next = workflow.status === "active" ? "disabled" : "active";
    setBusyId(workflow.id);
    setError("");
    try {
      await bpFetch(`/admin/notifications/workflows/${workflow.id}`, { method: "PUT", body: { status: next } });
      setWorkflows((prev) => prev.map((w) => (w.id === workflow.id ? { ...w, status: next } : w)));
      showToast(next === "active" ? "Enabled." : "Disabled.");
      loadMeta();
    } catch (err) {
      setError(err.message || "Could not update workflow.");
    } finally {
      setBusyId(null);
    }
  }

  async function testWorkflow(workflowId) {
    if (!confirm("Run workflow test now? This will trigger all ACTIVE actions using the latest booking.")) return;
    setBusyId(workflowId);
    setError("");
    try {
      const resp = await bpFetch(`/admin/notifications/workflows/${workflowId}/test`, { method: "POST" });
      const results = resp?.data?.results || [];
      const sent = results.filter((r) => r.sent).length;
      showToast(`Test complete: ${sent}/${results.length} action(s) sent.`);
      setTick((prev) => prev + 1);
    } catch (err) {
      setError(err.message || "Test failed.");
    } finally {
      setBusyId(null);
    }
  }

  async function deleteWorkflow(workflow) {
    if (!workflow?.id) return;
    if (!confirm(`Delete workflow \"${workflow.name || workflow.id}\"? This cannot be undone.`)) return;
    setBusyId(workflow.id);
    setError("");
    try {
      await bpFetch(`/admin/notifications/workflows/${workflow.id}`, { method: "DELETE" });
      setWorkflows((prev) => prev.filter((w) => w.id !== workflow.id));
      showToast("Workflow deleted.");
      loadMeta();
    } catch (err) {
      setError(err.message || "Delete failed.");
    } finally {
      setBusyId(null);
    }
  }

  const templates = useMemo(
    () => [
      {
        id: "booking_created_customer",
        name: "Booking Created -> Email Customer",
        event_key: "booking_created",
        workflow_name: "Booking Created: Customer email",
        actions: [
          {
            type: "send_email",
            status: "active",
            config: {
              to: "{{customer_email}}",
              from_name: "{{site_name}}",
              from_email: "{{admin_email}}",
              subject: "Booking received (#{{booking_id}})",
              body:
                "<p>Hi {{customer_name}},</p>" +
                "<p>We received your booking for <strong>{{service_name}}</strong> on {{start_date}} at {{start_time}}.</p>" +
                "<p>You can manage your booking here: <a href=\"{{manage_booking_url_customer}}\">Manage booking</a>.</p>" +
                "<p>Thanks,<br>{{site_name}}</p>",
              attach_ics: true,
            },
          },
        ],
      },
      {
        id: "booking_confirmed_customer",
        name: "Booking Confirmed -> Email Customer",
        event_key: "booking_confirmed",
        workflow_name: "Booking Confirmed: Customer email",
        actions: [
          {
            type: "send_email",
            status: "active",
            config: {
              to: "{{customer_email}}",
              from_name: "{{site_name}}",
              from_email: "{{admin_email}}",
              subject: "Booking confirmed (#{{booking_id}})",
              body:
                "<p>Hi {{customer_name}},</p>" +
                "<p>Your booking for <strong>{{service_name}}</strong> is confirmed for {{start_date}} at {{start_time}}.</p>" +
                "<p>Manage your booking: <a href=\"{{manage_booking_url_customer}}\">Manage booking</a>.</p>" +
                "<p>Thanks,<br>{{site_name}}</p>",
              attach_ics: true,
            },
          },
        ],
      },
      {
        id: "booking_cancelled_admin",
        name: "Booking Cancelled -> Email Admin",
        event_key: "booking_cancelled",
        workflow_name: "Booking Cancelled: Admin notification",
        actions: [
          {
            type: "send_email",
            status: "active",
            config: {
              to: "{{admin_email}}",
              from_name: "{{site_name}}",
              from_email: "{{admin_email}}",
              subject: "Booking cancelled (#{{booking_id}})",
              body:
                "<p>A booking was cancelled.</p>" +
                "<ul>" +
                "<li>ID: {{booking_id}}</li>" +
                "<li>Customer: {{customer_name}} ({{customer_email}})</li>" +
                "<li>Service: {{service_name}}</li>" +
                "<li>When: {{start_date}} {{start_time}}</li>" +
                "</ul>",
              attach_ics: false,
            },
          },
        ],
      },
    ],
    []
  );

  async function addFromTemplate(tpl) {
    setError("");
    setBusyId(tpl.id);
    try {
      const resp = await bpFetch("/admin/notifications/workflows", {
        method: "POST",
        body: {
          name: tpl.workflow_name || tpl.name || "New workflow",
          event_key: tpl.event_key,
          status: "active",
        },
      });
      const workflowId = resp?.data?.id;
      if (!workflowId) throw new Error("Could not create workflow.");

      for (const a of tpl.actions || []) {
        await bpFetch(`/admin/notifications/workflows/${workflowId}/actions`, {
          method: "POST",
          body: { type: a.type || "send_email", status: a.status || "active", config: a.config || {} },
        });
      }

      setTemplatesOpen(false);
      setTick((prev) => prev + 1);
      showToast("Template added.");
      openEditor(workflowId);
    } catch (err) {
      setError(err.message || "Could not add template.");
    } finally {
      setBusyId(null);
    }
  }

  const workflowsCount = workflows.length;

  return (
    <div className="bp-content">
      {toast ? <div className="bp-toast bp-toast-success">{toast}</div> : null}

      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Notifications</div>
          <div className="bp-muted">Automate emails and workflow actions for events.</div>
        </div>
        <div className="bp-head-actions">
          <button className="bp-btn" onClick={() => setTemplatesOpen(true)}>
            Templates
          </button>
          <button className="bp-primary-btn" onClick={addWorkflow}>
            + Add workflow
          </button>
        </div>
      </div>

      {!!error && <div className="bp-error">{error}</div>}

      <div className="bp-card" style={{ marginBottom: 14 }}>
        <div style={{ display: "flex", gap: 10, flexWrap: "wrap", alignItems: "center" }}>
          <input
            className="bp-input"
            placeholder="Search workflows..."
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            style={{ flex: "1 1 220px" }}
          />

          <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
            <button
              type="button"
              className={`bp-chip-btn ${eventFilter === "" ? "is-active" : ""}`}
              onClick={() => setEventFilter("")}
            >
              All
            </button>
            {(meta.events || Object.keys(EVENT_LABELS)).map((ev) => (
              <button
                key={ev}
                type="button"
                className={`bp-chip-btn ${eventFilter === ev ? "is-active" : ""}`}
                onClick={() => setEventFilter(ev)}
              >
                {EVENT_LABELS[ev] || ev}
                {meta.counts?.[ev]?.total ? <span className="bp-muted" style={{ marginLeft: 6 }}>({meta.counts[ev].total})</span> : null}
              </button>
            ))}
          </div>

          <select className="bp-input" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
            <option value="all">Status: All</option>
            <option value="active">Active</option>
            <option value="disabled">Disabled</option>
          </select>

          <button className="bp-btn bp-btn-ghost" onClick={loadWorkflows}>
            Refresh
          </button>

          <div className="bp-muted" style={{ marginLeft: "auto" }}>
            {loading ? "Loading..." : `${workflowsCount} workflows`}
          </div>
        </div>
      </div>

      {loading ? (
        <div className="bp-card">Loading workflows...</div>
      ) : workflowsCount === 0 ? (
        <div className="bp-card">No workflows yet. Use "+ Add workflow" to get started.</div>
      ) : (
        <div style={{ display: "grid", gap: 10 }}>
          {workflows.map((workflow) => (
            <div
              key={workflow.id}
              className="bp-card"
              style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 12, flexWrap: "wrap" }}
            >
              <div style={{ minWidth: 0, flex: "1 1 380px" }}>
                <div
                  style={{
                    fontSize: 16,
                    fontWeight: 900,
                    whiteSpace: "nowrap",
                    overflow: "hidden",
                    textOverflow: "ellipsis",
                  }}
                >
                  {workflow.name || `Workflow #${workflow.id}`}
                </div>
                <div className="bp-muted" style={{ marginTop: 4 }}>
                  {(EVENT_LABELS[workflow.event_key] || workflow.event_key) ?? "Event"} * {workflow.event_key}
                  {workflow.last_run_at ? (
                    <span>
                      {" "}* Last run: {new Date(workflow.last_run_at).toLocaleString()}
                      {workflow.last_run_status ? ` (${workflow.last_run_status})` : ""}
                    </span>
                  ) : null}
                </div>
              </div>

              <div style={{ display: "flex", gap: 10, alignItems: "center", flexWrap: "wrap" }}>
                <button
                  type="button"
                  className={`bp-btn-sm ${workflow.status === "active" ? "" : "bp-btn-danger"}`}
                  disabled={busyId === workflow.id}
                  onClick={() => toggleWorkflowStatus(workflow)}
                >
                  {workflow.status === "active" ? "Disable" : "Enable"}
                </button>

                <div className="bp-muted">
                  {workflow.actions_count ?? 0} {workflow.actions_count === 1 ? "action" : "actions"}
                </div>

                <button className="bp-btn" disabled={busyId === workflow.id} onClick={() => testWorkflow(workflow.id)}>
                  Test
                </button>

                <button className="bp-btn" onClick={() => openEditor(workflow.id)}>
                  Edit
                </button>

                <button
                  type="button"
                  className="bp-btn bp-btn-danger"
                  disabled={busyId === workflow.id}
                  onClick={() => deleteWorkflow(workflow)}
                >
                  Delete
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {templatesOpen ? (
        <div className="bp-modal-overlay" onMouseDown={(e) => e.target === e.currentTarget && setTemplatesOpen(false)}>
          <div className="bp-modal bp-card bp-p-16" style={{ width: "min(900px, calc(100vw - 30px))" }}>
            <div className="bp-flex bp-justify-between bp-items-center">
              <div>
                <div className="bp-text-lg bp-font-800">Templates</div>
                <div className="bp-text-sm bp-muted">Quick-start common notification workflows.</div>
              </div>
              <button className="bp-btn bp-btn-ghost" onClick={() => setTemplatesOpen(false)}>
                Close
              </button>
            </div>

            <div className="bp-grid bp-grid-2 bp-gap-10 bp-mt-14">
              {templates.map((tpl) => (
                <div key={tpl.id} className="bp-card bp-p-12" style={{ border: "1px solid rgba(15,23,42,.08)" }}>
                  <div className="bp-font-900">{tpl.name}</div>
                  <div className="bp-muted bp-text-xs" style={{ marginTop: 6 }}>
                    Event: {EVENT_LABELS[tpl.event_key] || tpl.event_key} * {tpl.actions.length} action(s)
                  </div>
                  <div className="bp-flex bp-justify-end bp-mt-12">
                    <button className="bp-btn bp-btn-primary" disabled={busyId === tpl.id} onClick={() => addFromTemplate(tpl)}>
                      {busyId === tpl.id ? "Adding..." : "Use template"}
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      ) : null}

      {showEditor && selectedId ? (
        <div
          className="bp-drawer-wrap"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) {
              closeEditor();
            }
          }}
        >
          <div className="bp-drawer" style={{ width: "min(940px, 100%)" }}>
            <WorkflowEditScreen workflowId={selectedId} onClose={closeEditor} onSaved={() => setTick((prev) => prev + 1)} />
          </div>
        </div>
      ) : null}
    </div>
  );
}
