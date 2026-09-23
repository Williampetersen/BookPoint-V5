import React from "react";

function StatusIcon({ kind }) {
  if (kind === "success") {
    return (
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
        <circle cx="12" cy="12" r="10" />
        <path d="M8 12.5l2.5 2.5L16 9.5" />
      </svg>
    );
  }
  if (kind === "warning") {
    return (
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
        <circle cx="12" cy="12" r="10" />
        <line x1="12" y1="7.5" x2="12" y2="13" />
        <circle cx="12" cy="16.5" r="0.5" fill="currentColor" />
      </svg>
    );
  }
  return (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
      <circle cx="12" cy="12" r="10" />
      <line x1="8.5" y1="8.5" x2="15.5" y2="15.5" />
      <line x1="15.5" y1="8.5" x2="8.5" y2="15.5" />
    </svg>
  );
}

export default function StepConfirmation({
  bookingId,
  confirmInfo,
  loading,
  error,
  onRetry,
  onClose,
  summary,
}) {
  const status = confirmInfo?.status || "";
  const pay = confirmInfo?.payment_status || "";
  const isSuccess = status === "confirmed" && pay === "paid";
  const isPending = status === "pending_payment" || pay === "unpaid" || pay === "processing";
  const alertKind = isSuccess ? "success" : isPending ? "warning" : "error";

  return (
    <div className="bp-step">
      <div className="bp-step-head">
        <h3>Confirmation</h3>
      </div>

      {loading ? <div className="bp-loading">Loading booking status...</div> : null}
      {error ? <div className="bp-error">{error}</div> : null}

      {!loading && !error ? (
        <>
          <div className={`bp-alert bp-alert-${alertKind === "error" ? "error" : alertKind === "warning" ? "warn" : "success"} bp-confirm-alert`}>
            <StatusIcon kind={alertKind} />
            <span>
              {isSuccess
                ? "Payment successful. Your booking is confirmed."
                : isPending
                  ? "Payment is still processing. If it doesn't confirm in a moment, click Retry."
                  : "Payment was not completed. Please try again."}
            </span>
          </div>

          <div className="bp-confirm-card">
            <div className="bp-row"><strong>Booking ID:</strong> <span>#{bookingId}</span></div>
            <div className="bp-row"><strong>Status:</strong> <span>{status || "-"}</span></div>
            <div className="bp-row"><strong>Payment:</strong> <span>{pay || "-"}</span></div>
            {confirmInfo?.payment_method ? (
              <div className="bp-row"><strong>Method:</strong> <span>{confirmInfo.payment_method}</span></div>
            ) : null}
          </div>

          {summary ? (
            <div className="bp-confirm-card" style={{ marginTop: 12 }}>
              <div className="bp-row" style={{ justifyContent: "flex-start", fontWeight: 800 }}>Summary</div>
              <div className="bp-loading" style={{ marginBottom: 0 }}>{summary}</div>
            </div>
          ) : null}

          <div className="bp-step-footer" style={{ position: "static", marginTop: 18 }}>
            <button type="button" className="bp-back" onClick={onClose}>
              Close
            </button>

            {!isSuccess ? (
              <button type="button" className="bp-next" onClick={onRetry}>
                Retry payment
              </button>
            ) : null}
          </div>
        </>
      ) : null}
    </div>
  );
}
