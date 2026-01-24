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
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [selectedId, setSelectedId] = useState(null);
  const [showEditor, setShowEditor] = useState(false);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    loadWorkflows();
  }, [search, statusFilter, tick]);

  async function loadWorkflows() {
    setLoading(true);
    setError("");
    try {
      const query = new URLSearchParams();
      if (search) query.set("q", search);
      if (statusFilter && statusFilter !== "all") query.set("status", statusFilter);
      query.set("page", "1");
      query.set("per", "50");
      const resp = await bpFetch(`/admin/notifications/workflows?${query.toString()}`);
      setWorkflows(resp?.data?.items || []);
    } catch (err) {
      console.error(err);
      setWorkflows([]);
      setError(err.message || "Failed to load workflows.");
    } finally {
      setLoading(false);
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
      console.error(err);
      setError(err.message || "Could not create workflow.");
    }
  }

  const workflowsCount = workflows.length;

  return (
    <div className="bp-content">
      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Notifications</div>
          <div className="bp-muted">Automate emails and workflow actions for events.</div>
        </div>
        <div className="bp-head-actions">
          <button className="bp-primary-btn" onClick={addWorkflow}>
            + Add workflow
          </button>
        </div>
      </div>

      {!!error && <div className="bp-error">{error}</div>}

      <div className="bp-card" style={{ marginBottom: 14 }}>
        <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
          <input
            className="bp-input"
            placeholder="Search workflows..."
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            style={{ flex: "1 1 220px" }}
          />
          <select
            className="bp-input"
            value={statusFilter}
            onChange={(event) => setStatusFilter(event.target.value)}
          >
            <option value="all">Status: All</option>
            <option value="active">Active</option>
            <option value="disabled">Disabled</option>
          </select>
          <button className="bp-btn bp-btn-ghost" onClick={loadWorkflows}>
            Refresh
          </button>
          <div className="bp-muted" style={{ marginLeft: "auto" }}>
            {loading ? "Loading…" : `${workflowsCount} workflows`}
          </div>
        </div>
      </div>

      {loading ? (
        <div className="bp-card">Loading workflows…</div>
      ) : workflowsCount === 0 ? (
        <div className="bp-card">No workflows yet. Use “+ Add workflow” to get started.</div>
      ) : (
        <div style={{ display: "grid", gap: 10 }}>
          {workflows.map((workflow) => (
            <div
              key={workflow.id}
              className="bp-card"
              style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}
            >
              <div style={{ minWidth: 0 }}>
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
                  {(EVENT_LABELS[workflow.event_key] || workflow.event_key) ?? "Event"} •{" "}
                  {workflow.event_key}
                </div>
              </div>
              <div style={{ display: "flex", gap: 10, alignItems: "center" }}>
                <span
                  className={`bp-chip ${workflow.status === "active" ? "bp-chip" : ""}`}
                  style={{ textTransform: "capitalize" }}
                >
                  {workflow.status}
                </span>
                <div className="bp-muted">
                  {workflow.actions_count ?? 0} {workflow.actions_count === 1 ? "action" : "actions"}
                </div>
                <button className="bp-btn" onClick={() => openEditor(workflow.id)}>
                  Edit
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

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
            <WorkflowEditScreen
              workflowId={selectedId}
              onClose={closeEditor}
              onSaved={() => setTick((prev) => prev + 1)}
            />
          </div>
        </div>
      ) : null}
    </div>
  );
}
