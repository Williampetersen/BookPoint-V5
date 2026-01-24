import React, { useEffect, useState } from "react";
import { bpFetch } from "../api/client";

export default function SmartVariablesDrawer({ eventKey, open, onClose }) {
  const [groups, setGroups] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [copiedKey, setCopiedKey] = useState("");

  useEffect(() => {
    if (!open) return;
    setLoading(true);
    setError("");
    bpFetch(`/smart-variables?event_key=${encodeURIComponent(eventKey)}`)
      .then((response) => {
        setGroups(response?.data || []);
      })
      .catch((err) => {
        console.error(err);
        setError(err.message || "Could not load variables.");
      })
      .finally(() => setLoading(false));
  }, [eventKey, open]);

  async function copyValue(key) {
    const value = `{{${key}}}`;
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(value);
      } else {
        const temp = document.createElement("textarea");
        temp.value = value;
        temp.style.position = "fixed";
        temp.style.opacity = "0";
        document.body.appendChild(temp);
        temp.select();
        document.execCommand("copy");
        document.body.removeChild(temp);
      }
      setCopiedKey(key);
      setTimeout(() => setCopiedKey(""), 1500);
    } catch (err) {
      console.error(err);
      setError("Copy failed.");
    }
  }

  if (!open) {
    return null;
  }

  return (
    <div
      className="bp-drawer-wrap"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) {
          onClose();
        }
      }}
    >
      <div className="bp-drawer" style={{ width: "min(520px, 100%)" }}>
        <div className="bp-drawer-head">
          <div>
            <div className="bp-drawer-title">Smart variables</div>
            <div className="bp-muted" style={{ marginTop: 4 }}>
              Event: {eventKey}
            </div>
          </div>
          <button className="bp-top-btn" onClick={onClose}>
            Close
          </button>
        </div>

        <div className="bp-drawer-body">
          {error ? <div className="bp-error">{error}</div> : null}
          {loading ? <div className="bp-muted">Loading variables…</div> : null}
          {!loading && !error && groups.length === 0 ? (
            <div className="bp-muted">No variables available.</div>
          ) : null}

          {groups.map((group) => (
            <div key={group.label} style={{ marginBottom: 20 }}>
              <div className="bp-section-title" style={{ marginBottom: 8 }}>
                {group.label}
              </div>
              <div style={{ display: "grid", gap: 10 }}>
                {group.variables?.map((variable) => (
                  <div
                    key={variable.key}
                    style={{
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "space-between",
                      padding: "8px 10px",
                      borderRadius: 12,
                      background: "#f8fafc",
                      border: "1px solid #e2e8f0",
                    }}
                  >
                    <div>
                      <div
                        style={{
                          fontFamily: "monospace",
                          fontSize: 13,
                          marginBottom: 4,
                        }}
                      >
                        {`{{${variable.key}}}`}
                      </div>
                      <div className="bp-muted" style={{ fontSize: 12 }}>
                        {variable.label}
                      </div>
                    </div>
                    <button
                      className="bp-btn-sm"
                      style={{
                        marginLeft: 12,
                        fontSize: 12,
                        padding: "6px 10px",
                      }}
                      onClick={() => copyValue(variable.key)}
                    >
                      {copiedKey === variable.key ? "Copied" : "Copy"}
                    </button>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
