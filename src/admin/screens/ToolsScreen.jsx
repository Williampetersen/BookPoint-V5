import React, { useEffect, useMemo, useRef, useState } from "react";
import { bpFetch } from "../api/client";

function downloadText(filename, text, type = "application/octet-stream;charset=utf-8") {
  const blob = new Blob([text], { type });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

function fmtBool(ok) {
  return ok ? "OK" : "Missing";
}

function nowLabel() {
  return new Date().toLocaleString();
}

export default function ToolsScreen() {
  const [status, setStatus] = useState(null);
  const [report, setReport] = useState(null);
  const [loadingStatus, setLoadingStatus] = useState(false);
  const [running, setRunning] = useState("");
  const [err, setErr] = useState("");
  const [toast, setToast] = useState("");
  const toastTimer = useRef(null);

  const [runLog, setRunLog] = useState([]); // newest first

  const [demo, setDemo] = useState({ services: 3, agents: 3, customers: 5, bookings: 10 });
  const [emailTo, setEmailTo] = useState("");
  const [webhookEvent, setWebhookEvent] = useState("booking_created");
  const [importFileName, setImportFileName] = useState("");
  const [importJson, setImportJson] = useState(null);

  const showToast = (msg) => {
    setToast(msg);
    if (toastTimer.current) clearTimeout(toastTimer.current);
    toastTimer.current = setTimeout(() => setToast(""), 2500);
  };

  const tables = useMemo(() => {
    return status?.tables || report?.tables || {};
  }, [status, report]);

  async function loadStatus() {
    setLoadingStatus(true);
    setErr("");
    try {
      const res = await bpFetch("/admin/tools/status");
      setStatus(res?.data || null);
    } catch (e) {
      setErr(e.message || "Failed to load status");
      setStatus(null);
    } finally {
      setLoadingStatus(false);
    }
  }

  async function loadReport() {
    try {
      const res = await bpFetch("/admin/tools/report");
      setReport(res?.data || null);
    } catch (_) {
      setReport(null);
    }
  }

  useEffect(() => {
    loadStatus();
    loadReport();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const pushLog = (entry) => {
    setRunLog((prev) => [entry, ...(prev || [])].slice(0, 15));
  };

  async function runTool(action, body = {}, opts = {}) {
    const label = opts.label || action;
    const startedAt = Date.now();
    try {
      if (opts.confirm && !confirm(opts.confirm)) return;
      setRunning(action);
      setErr("");
      const res = await bpFetch(`/admin/tools/run/${encodeURIComponent(action)}`, { method: "POST", body });
      const msg = res?.message || "Done";
      showToast(msg);
      pushLog({
        id: `${action}-${startedAt}`,
        at: nowLabel(),
        action,
        label,
        ok: true,
        message: msg,
        ms: Date.now() - startedAt,
        data: res?.data || null,
      });
      await loadStatus();
      await loadReport();
      return res;
    } catch (e) {
      const msg = e.message || "Tool failed";
      setErr(msg);
      pushLog({
        id: `${action}-${startedAt}`,
        at: nowLabel(),
        action,
        label,
        ok: false,
        message: msg,
        ms: Date.now() - startedAt,
        data: e.payload || null,
      });
    } finally {
      setRunning("");
    }
  }

  async function exportSettings() {
    setErr("");
    try {
      const base = window.BP_ADMIN?.restUrl || "/wp-json/bp/v1";
      const url = base.replace(/\/$/, "") + "/admin/tools/export-settings";
      const res = await fetch(url, {
        method: "GET",
        credentials: "same-origin",
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce },
      });
      const text = await res.text();
      if (!res.ok) throw new Error("Export failed");
      downloadText(`bookpoint-settings-${new Date().toISOString().slice(0, 10)}.json`, text, "application/json;charset=utf-8");
      showToast("Settings exported.");
    } catch (e) {
      setErr(e.message || "Export failed");
    }
  }

  async function importSettings() {
    if (!importJson) {
      setErr("Choose a settings JSON file first.");
      return;
    }
    const action = "import_settings";
    const label = "Import Settings";
    const startedAt = Date.now();
    if (!confirm("Import settings from file? This will overwrite existing BookPoint settings.")) return;

    setRunning(action);
    setErr("");
    try {
      const res = await bpFetch("/admin/tools/import-settings", { method: "POST", body: importJson });
      const msg = res?.message || "Settings imported";
      showToast(msg);
      pushLog({
        id: `${action}-${startedAt}`,
        at: nowLabel(),
        action,
        label,
        ok: true,
        message: msg,
        ms: Date.now() - startedAt,
        data: res?.data || null,
      });
      await loadStatus();
      await loadReport();
    } catch (e) {
      const msg = e.message || "Import failed";
      setErr(msg);
      pushLog({
        id: `${action}-${startedAt}`,
        at: nowLabel(),
        action,
        label,
        ok: false,
        message: msg,
        ms: Date.now() - startedAt,
        data: e.payload || null,
      });
    } finally {
      setRunning("");
    }
  }

  async function downloadReport() {
    setErr("");
    try {
      const res = await bpFetch("/admin/tools/report");
      const data = res?.data || {};
      downloadText(
        `bookpoint-system-report-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-")}.json`,
        JSON.stringify(data, null, 2),
        "application/json;charset=utf-8"
      );
      showToast("Report downloaded.");
    } catch (e) {
      setErr(e.message || "Report failed");
    }
  }

  const StatusCard = (
    <div className="bp-card bp-tools-status">
      <div className="bp-card-head" style={{ padding: 14, borderBottom: "1px solid rgba(15,23,42,.06)" }}>
        <div>
          <div className="bp-section-title" style={{ margin: 0 }}>System Status</div>
          <div className="bp-muted bp-text-xs">Quick health check + versions.</div>
        </div>
        <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
          <button type="button" className="bp-btn" onClick={() => { loadStatus(); loadReport(); }} disabled={loadingStatus}>Refresh</button>
          <button type="button" className="bp-btn" onClick={downloadReport}>Download report</button>
        </div>
      </div>

      <div className="bp-tools-statusBody">
        <div className="bp-tools-kpis">
          <div className="bp-tools-kpi">
            <div className="k">Plugin</div>
            <div className="v">{status?.plugin_version || report?.plugin_version || "—"}</div>
          </div>
          <div className="bp-tools-kpi">
            <div className="k">DB Version</div>
            <div className="v">{status?.db_version || report?.db_version || "—"}</div>
          </div>
          <div className="bp-tools-kpi">
            <div className="k">Tables</div>
            <div className="v">{status ? `${status.tables_ok_count || 0}/${status.tables_total || 0}` : "—"}</div>
          </div>
          <div className="bp-tools-kpi">
            <div className="k">WP / PHP</div>
            <div className="v">{report ? `${report.wp_version || "—"} / ${report.php_version || "—"}` : "—"}</div>
          </div>
        </div>

        <div className="bp-tools-tableGrid">
          {Object.keys(tables || {}).length === 0 ? (
            <div className="bp-muted">No table status yet.</div>
          ) : (
            Object.entries(tables).map(([name, ok]) => (
              <div key={name} className={`bp-tools-tablePill ${ok ? "is-ok" : "is-bad"}`}>
                <span className="n">{name}</span>
                <span className="s">{fmtBool(!!ok)}</span>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );

  const ToolCard = ({ title, desc, children, action, onRun, danger }) => (
    <div className={`bp-card bp-tools-card ${danger ? "is-danger" : ""}`}>
      <div className="bp-tools-cardHead">
        <div style={{ minWidth: 0 }}>
          <div className="bp-section-title" style={{ margin: 0 }}>{title}</div>
          <div className="bp-muted bp-text-xs" style={{ marginTop: 6 }}>{desc}</div>
        </div>
        <button
          type="button"
          className={`bp-btn ${danger ? "bp-btn-danger" : "bp-btn-primary"}`}
          disabled={running === action}
          onClick={onRun}
        >
          {running === action ? "Running..." : "Run"}
        </button>
      </div>
      {children ? <div className="bp-tools-cardBody">{children}</div> : null}
    </div>
  );

  const SafeTools = (
    <div className="bp-tools-section">
      <div className="bp-tools-sectionTitle">Maintenance</div>
      <div className="bp-tools-grid">
        <ToolCard
          title="Sync Relations"
          desc="Rebuild service/agent/category relations."
          action="sync_relations"
          onRun={() => runTool("sync_relations", {}, { label: "Sync Relations" })}
        />

        <ToolCard
          title="Run Migrations"
          desc="Ensure BookPoint database tables exist (safe to run)."
          action="run_migrations"
          onRun={() => runTool("run_migrations", {}, { label: "Run Migrations" })}
        />

        <ToolCard
          title="Reset Cache"
          desc="Flush WordPress object cache for fresh data."
          action="reset_cache"
          onRun={() => runTool("reset_cache", {}, { label: "Reset Cache", confirm: "Clear cache now?" })}
          danger
        />
      </div>
    </div>
  );

  const DataTools = (
    <div className="bp-tools-section">
      <div className="bp-tools-sectionTitle">Data</div>
      <div className="bp-tools-grid">
        <ToolCard
          title="Generate Demo Data"
          desc="Create sample services, agents, customers, and bookings."
          action="generate_demo"
          onRun={() => runTool("generate_demo", demo, { label: "Generate Demo Data", confirm: "Generate demo data now? This will add records to your database." })}
        >
          <div className="bp-tools-formGrid">
            <div>
              <label className="bp-label">Services</label>
              <input className="bp-input-field" type="number" min={0} max={50} value={demo.services} onChange={(e) => setDemo({ ...demo, services: Number(e.target.value) || 0 })} />
            </div>
            <div>
              <label className="bp-label">Agents</label>
              <input className="bp-input-field" type="number" min={0} max={50} value={demo.agents} onChange={(e) => setDemo({ ...demo, agents: Number(e.target.value) || 0 })} />
            </div>
            <div>
              <label className="bp-label">Customers</label>
              <input className="bp-input-field" type="number" min={0} max={200} value={demo.customers} onChange={(e) => setDemo({ ...demo, customers: Number(e.target.value) || 0 })} />
            </div>
            <div>
              <label className="bp-label">Bookings</label>
              <input className="bp-input-field" type="number" min={0} max={500} value={demo.bookings} onChange={(e) => setDemo({ ...demo, bookings: Number(e.target.value) || 0 })} />
            </div>
          </div>
        </ToolCard>

        <div className="bp-card bp-tools-card">
          <div className="bp-tools-cardHead">
            <div style={{ minWidth: 0 }}>
              <div className="bp-section-title" style={{ margin: 0 }}>Export / Import Settings</div>
              <div className="bp-muted bp-text-xs" style={{ marginTop: 6 }}>Backup settings or move them between sites.</div>
            </div>
          </div>
          <div className="bp-tools-cardBody">
            <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
              <button type="button" className="bp-btn bp-btn-primary" onClick={exportSettings}>Export settings</button>
              <label className="bp-btn" style={{ cursor: "pointer" }}>
                Choose file…
                <input
                  type="file"
                  accept="application/json"
                  style={{ display: "none" }}
                  onChange={(e) => {
                    const f = e.target.files && e.target.files[0];
                    setImportFileName(f ? f.name : "");
                    setImportJson(null);
                    if (!f) return;
                    const reader = new FileReader();
                    reader.onload = () => {
                      try {
                        const parsed = JSON.parse(String(reader.result || ""));
                        setImportJson(parsed);
                      } catch {
                        setErr("Invalid JSON file.");
                      }
                    };
                    reader.readAsText(f);
                  }}
                />
              </label>
              <button type="button" className="bp-btn bp-btn-danger" onClick={importSettings} disabled={!importJson || running === "import_settings"}>
                {running === "import_settings" ? "Importing..." : "Import settings"}
              </button>
            </div>
            <div className="bp-muted bp-text-xs" style={{ marginTop: 8 }}>
              Selected: {importFileName || "—"}
            </div>
          </div>
        </div>
      </div>
    </div>
  );

  const TestTools = (
    <div className="bp-tools-section">
      <div className="bp-tools-sectionTitle">Diagnostics</div>
      <div className="bp-tools-grid">
        <ToolCard
          title="Send Test Email"
          desc="Verify email delivery from WordPress."
          action="email_test"
          onRun={() => runTool("email_test", { to: emailTo || undefined }, { label: "Send Test Email" })}
        >
          <label className="bp-label">To</label>
          <input className="bp-input-field" value={emailTo} onChange={(e) => setEmailTo(e.target.value)} placeholder="admin@example.com (blank uses site admin email)" />
        </ToolCard>

        <ToolCard
          title="Fire Test Webhook"
          desc="Send a test webhook event to your configured webhook endpoint(s)."
          action="webhook_test"
          onRun={() => runTool("webhook_test", { event: webhookEvent }, { label: "Fire Test Webhook", confirm: "Fire webhook now?" })}
        >
          <label className="bp-label">Event</label>
          <input className="bp-input-field" value={webhookEvent} onChange={(e) => setWebhookEvent(e.target.value)} placeholder="booking_created" />
        </ToolCard>
      </div>
    </div>
  );

  const RunLog = (
    <div className="bp-card bp-tools-log">
      <div className="bp-card-head" style={{ padding: 14, borderBottom: "1px solid rgba(15,23,42,.06)" }}>
        <div>
          <div className="bp-section-title" style={{ margin: 0 }}>Run Log</div>
          <div className="bp-muted bp-text-xs">Latest tool runs in this browser.</div>
        </div>
        <button type="button" className="bp-btn" onClick={() => setRunLog([])}>Clear</button>
      </div>
      <div className="bp-tools-logBody">
        {runLog.length === 0 ? (
          <div className="bp-muted">No runs yet.</div>
        ) : (
          runLog.map((r) => (
            <div key={r.id} className={`bp-tools-logRow ${r.ok ? "is-ok" : "is-bad"}`}>
              <div className="a">{r.label}</div>
              <div className="m">{r.message}</div>
              <div className="t">{r.at}</div>
            </div>
          ))
        )}
      </div>
    </div>
  );

  return (
    <div className="bp-tools">
      {toast ? <div className="bp-toast bp-toast-success">{toast}</div> : null}

      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Tools</div>
          <div className="bp-muted">Maintenance, diagnostics, and data utilities.</div>
        </div>
      </div>

      {err ? <div className="bp-alert bp-alert-error">{err}</div> : null}

      {StatusCard}

      <div className="bp-tools-layout">
        <div className="bp-tools-main">
          {SafeTools}
          {DataTools}
          {TestTools}
        </div>
        <div className="bp-tools-side">
          {RunLog}
        </div>
      </div>
    </div>
  );
}
