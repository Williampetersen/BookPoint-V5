import React, { useMemo } from "react";

export default function ServicesEditScreen() {
  const params = new URLSearchParams(window.location.search);
  const id = params.get("id");

  const iframeUrl = useMemo(() => {
    const base = "admin.php?page=bp_services&action=edit&legacy=1&noadmin=1";
    return id ? `${base}&id=${encodeURIComponent(id)}` : base;
  }, [id]);

  return (
    <div className="bp-container">
      <div className="bp-header">
        <h1>{id ? "Edit Service" : "Add Service"}</h1>
        <a className="bp-btn" href="admin.php?page=bp_services">Back to Services</a>
      </div>

      <div className="bp-card bp-iframe-card">
        <iframe title="Service Edit" src={iframeUrl} className="bp-legacy-iframe" />
      </div>
    </div>
  );
}
