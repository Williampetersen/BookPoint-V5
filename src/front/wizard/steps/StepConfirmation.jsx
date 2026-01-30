import React from "react";

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

  return (
    <div className="bp-step">
      <div className="bp-step-head">
        <h3>Confirmation</h3>
      </div>

      {loading ? <div className="bp-muted">Loading booking status...</div> : null}
      {error ? <div className="bp-alert bp-alert-error">{error}</div> : null}

      {!loading && !error ? (
        <>
          {isSuccess ? (
            <div className="bp-alert bp-alert-success">
              ✅ Payment successful. Your booking is confirmed.
            </div>
          ) : isPending ? (
            <div className="bp-alert bp-alert-warn">
              ⏳ Payment is still processing. If it doesn’t confirm in a moment, click Retry.
            </div>
          ) : (
            <div className="bp-alert bp-alert-error">
              ❌ Payment was not completed. Please try again.
            </div>
          )}

          <div className="bp-confirm-card">
            <div className="bp-row"><strong>Booking ID:</strong> <span>#{bookingId}</span></div>
            <div className="bp-row"><strong>Status:</strong> <span>{status || "-"}</span></div>
            <div className="bp-row"><strong>Payment:</strong> <span>{pay || "-"}</span></div>
            {confirmInfo?.payment_method ? (
              <div className="bp-row"><strong>Method:</strong> <span>{confirmInfo.payment_method}</span></div>
            ) : null}
          </div>

          {summary ? (
            <div className="bp-confirm-summary">
              <h4>Summary</h4>
              <div className="bp-muted">{summary}</div>
            </div>
          ) : null}

          <div className="bp-pay-actions" style={{ justifyContent: "space-between" }}>
            <button type="button" className="bp-btn bp-btn-light" onClick={onClose}>
              Close
            </button>

            {!isSuccess ? (
              <button type="button" className="bp-btn bp-btn-primary" onClick={onRetry}>
                Retry payment
              </button>
            ) : null}
          </div>
        </>
      ) : null}
    </div>
  );
}
