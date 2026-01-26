import React from 'react';

export default function StepDone({
  booking,
  confirmData,
  returnLoading,
  returnError,
  onRetry,
  onClose,
}) {
  const paid = confirmData?.paid === true;
  const pending = confirmData?.pending === true;
  const errorMsg = confirmData?.error || returnError || '';
  const bookingId = confirmData?.booking?.id || booking?.booking_id || confirmData?.booking_id;
  const showRetry = confirmData && !paid && !pending;

  let title = 'Booking confirmed';
  if (paid) title = 'Paid & confirmed';
  else if (pending) title = 'Payment processing';
  else if (showRetry || errorMsg) title = 'Payment cancelled/failed';

  return (
    <div className="bp-step bp-done">
      <div className="bp-done-card">
        <div className="bp-done-title">{title}</div>
        {returnLoading ? (
          <div className="bp-done-sub">Processing payment...</div>
        ) : null}
        {!returnLoading && errorMsg ? (
          <div className="bp-done-sub">{errorMsg}</div>
        ) : null}
        {!returnLoading && pending ? (
          <div className="bp-done-sub">Payment is still processing. Please refresh or wait.</div>
        ) : null}
        {bookingId ? (
          <div className="bp-done-sub">Booking ID: {bookingId}</div>
        ) : null}
        {booking?.manage_url ? (
          <a className="bp-done-link" href={booking.manage_url} target="_blank" rel="noreferrer">
            Manage your booking
          </a>
        ) : null}
        <div className="bp-done-actions">
          {showRetry ? (
            <button type="button" className="bp-next" onClick={onRetry}>
              Retry payment
            </button>
          ) : null}
          <button type="button" className="bp-next" onClick={onClose}>
            Close
          </button>
        </div>
      </div>
    </div>
  );
}
