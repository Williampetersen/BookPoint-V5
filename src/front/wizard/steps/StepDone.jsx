import React from 'react';

export default function StepDone({ booking, onClose }) {
  return (
    <div className="bp-step bp-done">
      <div className="bp-done-card">
        <div className="bp-done-title">Booking confirmed</div>
        {booking?.booking_id ? (
          <div className="bp-done-sub">Booking ID: {booking.booking_id}</div>
        ) : null}
        {booking?.manage_url ? (
          <a className="bp-done-link" href={booking.manage_url} target="_blank" rel="noreferrer">
            Manage your booking
          </a>
        ) : null}
        <div className="bp-done-actions">
          <button type="button" className="bp-next" onClick={onClose}>
            Close
          </button>
        </div>
      </div>
    </div>
  );
}
