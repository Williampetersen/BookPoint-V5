import React, { useMemo } from 'react';

export default function StepReview({
  locationId,
  categoryIds,
  serviceId,
  extraIds,
  agentId,
  date,
  slot,
  locations,
  categories,
  services,
  extras,
  agents,
  formFields,
  answers,
  paymentMethodLabel,
  totalLabel,
  onBack,
  onNext,
  isCreatingBooking,
  createError,
  loading,
  backLabel = '<- Back',
  nextLabel = 'Next ->',
}) {
  const loc = locations.find((x) => String(x.id) === String(locationId));
  const svc = services.find((x) => String(x.id) === String(serviceId));
  const ag = agents.find((x) => String(x.id) === String(agentId));
  const cats = categories.filter((x) => categoryIds.includes(x.id) || categoryIds.includes(String(x.id)));
  const ex = extras.filter((x) => extraIds.includes(x.id) || extraIds.includes(String(x.id)));

  const fields = useMemo(() => ([
    ...(formFields?.form || []),
    ...(formFields?.customer || []),
    ...(formFields?.booking || []),
  ]), [formFields]);

  return (
    <div className="bp-step">
      <div className="bp-review">
        <ReviewRow label="Location" value={loc?.name} />
        <ReviewRow label="Categories" value={cats.length ? cats.map((c) => c.name).join(', ') : '-'} />
        <ReviewRow label="Service" value={svc?.name} />
        <ReviewRow label="Agent" value={ag?.name} />
        <ReviewRow label="Date" value={date} />
        <ReviewRow label="Time" value={slot?.start_time ? `${slot.start_time} - ${slot.end_time}` : '-'} />
        <ReviewRow label="Extras" value={ex.length ? ex.map((e) => e.name).join(', ') : '-'} />
        {paymentMethodLabel ? <ReviewRow label="Payment Method" value={paymentMethodLabel} /> : null}
        {totalLabel ? <ReviewRow label="Total" value={totalLabel} /> : null}

        <div className="bp-review-section">Customer Information</div>
        {fields.map((f) => {
          const fieldKey = f.field_key || f.name_key || '';
          const key = `${f.scope}.${fieldKey}`;
          let v = answers?.[key] ?? '';
          if (f.type === 'checkbox') v = v === '1' ? 'Yes' : 'No';
          return <ReviewRow key={key} label={f.label} value={String(v || '')} />;
        })}
      </div>

      {createError ? (
        <div className="bp-alert bp-alert-error bp-mt-12">
          {createError}
        </div>
      ) : null}

      <div className="bp-step-footer">
        <button type="button" className="bp-back" onClick={onBack}>{backLabel}</button>
        <button type="button" className="bp-next" disabled={!!isCreatingBooking} onClick={onNext}>
          {isCreatingBooking ? 'Preparing payment...' : nextLabel}
        </button>
      </div>
    </div>
  );
}

function ReviewRow({ label, value }) {
  return (
    <div className="bp-review-row">
      <div className="k">{label}</div>
      <div className="v">{value || '-'}</div>
    </div>
  );
}
