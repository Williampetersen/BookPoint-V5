import React, { useEffect, useState } from "react";
import { bpFetch } from "../api/client";
import SmartVariablesDrawer from "../components/SmartVariablesDrawer";

const EVENT_OPTIONS = [
  { value: "booking_created", label: "Booking Created" },
  { value: "booking_updated", label: "Booking Updated" },
  { value: "booking_confirmed", label: "Booking Confirmed" },
  { value: "booking_cancelled", label: "Booking Cancelled" },
  { value: "customer_created", label: "Customer Created" },
];

export default function WorkflowEditScreen({ workflowId, onClose, onSaved }) {
  const [workflow, setWorkflow] = useState(null);
  const [actions, setActions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [actionSavingId, setActionSavingId] = useState(null);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [showVariables, setShowVariables] = useState(false);
  const [testMessage, setTestMessage] = useState("");

  useEffect(() => {
    if (!workflowId) return;
    loadWorkflow();
  }, [workflowId]);

  async function loadWorkflow() {
    setLoading(true);
    setError("");
    try {
      const resp = await bpFetch(`/admin/notifications/workflows/${workflowId}`);
      setWorkflow(resp?.data || null);
      setActions(resp?.data?.actions || []);
    } catch (err) {
      console.error(err);
      setError(err.message || "Failed to load workflow.");
    } finally {
      setLoading(false);
    }
  }

  async function saveWorkflow() {
    if (!workflow) return;
    setSaving(true);
    setError("");
    setMessage("");
    try {
      await bpFetch(`/admin/notifications/workflows/${workflowId}`, {
        method: "PUT",
        body: {
          name: workflow.name,
          status: workflow.status,
          event_key: workflow.event_key,
          is_conditional: !!workflow.is_conditional,
          has_time_offset: !!workflow.has_time_offset,
          time_offset_minutes: workflow.time_offset_minutes,
        },
      });
      setMessage("Workflow saved.");
      onSaved?.();
      await loadWorkflow();
    } catch (err) {
      console.error(err);
      setError(err.message || "Unable to save workflow.");
    } finally {
      setSaving(false);
    }
  }

  function updateActionField(actionId, key, value) {
    setActions((prev) =>
      prev.map((action) =>
        action.id === actionId
          ? {
              ...action,
              config: {
                ...(action.config || {}),
                [key]: value,
              },
            }
          : action
      )
    );
  }

  async function saveAction(action) {
    setActionSavingId(action.id);
    setError("");
    setTestMessage("");
    try {
      await bpFetch(`/admin/notifications/actions/${action.id}`, {
        method: "PUT",
        body: {
          type: action.type,
          status: action.status,
          config: action.config,
        },
      });
      setMessage("Action saved.");
      onSaved?.();
      await loadWorkflow();
    } catch (err) {
      console.error(err);
      setError(err.message || "Failed to save action.");
    } finally {
      setActionSavingId(null);
    }
  }

  async function deleteAction(actionId) {
    setError("");
    try {
      await bpFetch(`/admin/notifications/actions/${actionId}`, { method: "DELETE" });
      setMessage("Action removed.");
      onSaved?.();
      await loadWorkflow();
    } catch (err) {
      console.error(err);
      setError(err.message || "Failed to delete action.");
    }
  }

  async function testAction(actionId) {
    setTestMessage("");
    setError("");
    try {
      await bpFetch(`/admin/notifications/actions/${actionId}/test`, { method: "POST" });
      setTestMessage("Test email triggered (uses latest booking).");
    } catch (err) {
      console.error(err);
      setError(err.message || "Test failed.");
    }
  }

  async function addAction() {
    if (!workflow) return;
    setError("");
    try {
      await bpFetch(`/admin/notifications/workflows/${workflowId}/actions`, {
        method: "POST",
        body: {
          type: "send_email",
        },
      });
      setMessage("Action added.");
      onSaved?.();
      await loadWorkflow();
    } catch (err) {
      console.error(err);
      setError(err.message || "Could not add action.");
    }
  }

  if (loading || !workflow) {
    return (
      <div className="bp-card" style={{ margin: 14 }}>
        Loading workflow…
      </div>
    );
  }

  return (
    <>
      <div className="bp-drawer-head">
        <div>
          <div className="bp-drawer-title">{workflow.name || `Workflow #${workflow.id}`}</div>
          <div className="bp-muted">Configure events, conditions, and email actions.</div>
        </div>
        <div style={{ display: "flex", gap: 10 }}>
          <button className="bp-btn bp-btn-ghost" onClick={() => setShowVariables(true)}>
            Smart variables
          </button>
          <button className="bp-top-btn" onClick={onClose}>
            Close
          </button>
        </div>
      </div>
      <div className="bp-drawer-body">
        {error && <div className="bp-error">{error}</div>}
        {message && <div className="bp-success" style={{ marginBottom: 12 }}>{message}</div>}
        {testMessage && <div className="bp-success" style={{ marginBottom: 12 }}>{testMessage}</div>}

        <div className="bp-grid" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}>
          <div className="bp-settings-field">
            <label className="bp-label">Workflow name</label>
            <input
              className="bp-input"
              value={workflow.name || ""}
              onChange={(event) => setWorkflow({ ...workflow, name: event.target.value })}
            />
          </div>
          <div className="bp-settings-field">
            <label className="bp-label">Event</label>
            <select
              className="bp-input"
              value={workflow.event_key || "booking_created"}
              onChange={(event) => setWorkflow({ ...workflow, event_key: event.target.value })}
            >
              {EVENT_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
          <div className="bp-settings-field">
            <label className="bp-label">Status</label>
            <select
              className="bp-input"
              value={workflow.status || "active"}
              onChange={(event) => setWorkflow({ ...workflow, status: event.target.value })}
            >
              <option value="active">Active</option>
              <option value="disabled">Disabled</option>
            </select>
          </div>
          <div className="bp-settings-field">
            <label className="bp-label">Conditional</label>
            <label className="bp-check">
              <input
                type="checkbox"
                checked={!!workflow.is_conditional}
                onChange={(event) => setWorkflow({ ...workflow, is_conditional: event.target.checked })}
              />
              Only trigger when conditions match (coming soon)
            </label>
          </div>
          <div className="bp-settings-field">
            <label className="bp-label">Time offset</label>
            <label className="bp-check">
              <input
                type="checkbox"
                checked={!!workflow.has_time_offset}
                onChange={(event) => setWorkflow({ ...workflow, has_time_offset: event.target.checked })}
              />
              Delay actions
            </label>
          </div>
          {workflow.has_time_offset ? (
            <div className="bp-settings-field">
              <label className="bp-label">Delay (minutes)</label>
              <input
                type="number"
                className="bp-input"
                value={workflow.time_offset_minutes || 0}
                min="0"
                onChange={(event) =>
                  setWorkflow({ ...workflow, time_offset_minutes: Math.max(0, Number(event.target.value)) })
                }
              />
            </div>
          ) : null}
        </div>

        <div className="bp-settings-actions" style={{ marginTop: 12 }}>
          <button className="bp-btn bp-btn-primary" onClick={saveWorkflow} disabled={saving}>
            {saving ? "Saving…" : "Save workflow"}
          </button>
          <button className="bp-btn" onClick={addAction}>
            + Add action
          </button>
        </div>

        <div style={{ marginTop: 16 }}>
          {actions.map((action) => (
            <div key={action.id} className="bp-card" style={{ marginBottom: 12 }}>
              <div className="bp-row">
                <div>
                  <div className="bp-card-label" style={{ marginBottom: 6 }}>
                    Action #{action.id}
                  </div>
                  <div className="bp-muted" style={{ marginBottom: 4 }}>
                    Type: {action.type}
                  </div>
                </div>
                <div className="bp-row-actions">
                  <select
                    className="bp-input"
                    value={action.status === "disabled" ? "disabled" : "active"}
                    onChange={(event) =>
                      setActions((prev) =>
                        prev.map((item) =>
                          item.id === action.id ? { ...item, status: event.target.value } : item
                        )
                      )
                    }
                  >
                    <option value="active">Active</option>
                    <option value="disabled">Disabled</option>
                  </select>
                  <button className="bp-chip" onClick={() => saveAction(action)} disabled={actionSavingId === action.id}>
                    {actionSavingId === action.id ? "Saving…" : "Save"}
                  </button>
                  <button className="bp-chip" onClick={() => testAction(action.id)}>
                    Test
                  </button>
                  <button className="bp-chip" onClick={() => deleteAction(action.id)}>
                    Remove
                  </button>
                </div>
              </div>

              <div className="bp-grid" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}>
                <div className="bp-settings-field">
                  <label className="bp-label">To</label>
                  <input
                    className="bp-input"
                    value={action.config?.to || ""}
                    onChange={(event) => updateActionField(action.id, "to", event.target.value)}
                  />
                </div>
                <div className="bp-settings-field">
                  <label className="bp-label">Subject</label>
                  <input
                    className="bp-input"
                    value={action.config?.subject || ""}
                    onChange={(event) => updateActionField(action.id, "subject", event.target.value)}
                  />
                </div>
                <div className="bp-settings-field">
                  <label className="bp-label">From name</label>
                  <input
                    className="bp-input"
                    value={action.config?.from_name || ""}
                    onChange={(event) => updateActionField(action.id, "from_name", event.target.value)}
                  />
                </div>
                <div className="bp-settings-field">
                  <label className="bp-label">From email</label>
                  <input
                    className="bp-input"
                    value={action.config?.from_email || ""}
                    onChange={(event) => updateActionField(action.id, "from_email", event.target.value)}
                  />
                </div>
              </div>

              <div className="bp-settings-field" style={{ marginTop: 12 }}>
                <label className="bp-label">Body (HTML)</label>
                <textarea
                  className="bp-textarea"
                  rows="6"
                  value={action.config?.body || ""}
                  onChange={(event) => updateActionField(action.id, "body", event.target.value)}
                />
              </div>

              <label className="bp-check" style={{ marginTop: 10 }}>
                <input
                  type="checkbox"
                  checked={!!action.config?.attach_ics}
                  onChange={(event) => updateActionField(action.id, "attach_ics", event.target.checked)}
                />
                Attach ICS calendar file
              </label>
            </div>
          ))}
        </div>
      </div>
      <SmartVariablesDrawer
        eventKey={workflow.event_key || "booking_created"}
        open={showVariables}
        onClose={() => setShowVariables(false)}
      />
    </>
  );
}
