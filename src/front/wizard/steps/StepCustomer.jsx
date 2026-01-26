import React from 'react';
import DynamicFields from '../DynamicFields';

function isEmptyValue(val, type) {
  if (type === 'checkbox') return !val;
  if (val === undefined || val === null) return true;
  if (Array.isArray(val)) return val.length === 0;
  return String(val).trim() === '';
}

function normalizeId(def) {
  return def?.id || def?.field_key || def?.name_key || '';
}

function layoutFromDefs(defs = []) {
  return defs
    .map((d) => ({
      id: normalizeId(d),
      required: !!(d?.is_required ?? d?.required ?? false),
      width: 'full',
    }))
    .filter((d) => d.id);
}

export default function StepCustomer({
  formFields,
  answers,
  onChange,
  onBack,
  onNext,
  onError,
  layout,
  backLabel = '<- Back',
  nextLabel = 'Next ->',
}) {
  const customerDefs = formFields?.customer || [];
  const bookingDefs = formFields?.booking || [];

  const customerLayout = layout?.customer?.fields?.length
    ? layout.customer.fields
    : layoutFromDefs(customerDefs);
  const bookingLayout = layout?.booking?.fields?.length
    ? layout.booking.fields
    : layoutFromDefs(bookingDefs);

  function setValue(keyOrNext, val) {
    if (keyOrNext && typeof keyOrNext === 'object' && val === undefined) {
      onChange(keyOrNext);
      return;
    }
    onChange((prev) => ({ ...prev, [keyOrNext]: val }));
  }

  function getValue(key) {
    return answers?.[key] ?? '';
  }

  function handleNext() {
    const allGroups = [
      { scope: 'customer', defs: customerDefs, layout: customerLayout },
      { scope: 'booking', defs: bookingDefs, layout: bookingLayout },
    ];

    for (const group of allGroups) {
      for (const entry of group.layout) {
        const def = group.defs.find((d) => normalizeId(d) === entry.id);
        if (!def) continue;
        const required = entry.required ?? !!(def.is_required ?? def.required ?? false);
        if (!required) continue;
        const key = `${group.scope}.${entry.id}`;
        const val = getValue(key);
        if (isEmptyValue(val, def.type)) {
          onError?.(`${def.label || entry.id} is required`);
          return;
        }
      }
    }
    onError?.('');
    onNext();
  }

  return (
    <div className="bp-step">
      <DynamicFields
        defs={customerDefs}
        layout={customerLayout}
        values={answers}
        onChange={setValue}
        scope="customer"
      />

      {bookingDefs.length ? (
        <div style={{ marginTop: 14 }}>
          <DynamicFields
            defs={bookingDefs}
            layout={bookingLayout}
            values={answers}
            onChange={setValue}
            scope="booking"
          />
        </div>
      ) : null}

      <div className="bp-step-footer">
        <button type="button" className="bp-back" onClick={onBack}>{backLabel}</button>
        <button type="button" className="bp-next" onClick={handleNext}>
          {nextLabel}
        </button>
      </div>
    </div>
  );
}
