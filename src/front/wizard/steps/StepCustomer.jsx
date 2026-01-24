import React from 'react';

function isEmptyValue(val, type) {
  if (type === 'checkbox') return val !== '1';
  if (val === undefined || val === null) return true;
  if (Array.isArray(val)) return val.length === 0;
  return String(val).trim() === '';
}

export default function StepCustomer({ formFields, answers, onChange, onBack, onNext, onError }) {
  const fields = [
    ...(formFields?.form || []),
    ...(formFields?.customer || []),
    ...(formFields?.booking || []),
  ];

  function setValue(key, val) {
    onChange((prev) => ({ ...prev, [key]: val }));
  }

  function getValue(key) {
    return answers?.[key] ?? '';
  }

  function handleNext() {
    for (const f of fields) {
      if (!f.is_required) continue;
      const fieldKey = f.field_key || f.name_key || '';
      const key = `${f.scope}.${fieldKey}`;
      const val = getValue(key);
      if (isEmptyValue(val, f.type)) {
        onError?.(`${f.label || fieldKey} is required`);
        return;
      }
    }
    onError?.('');
    onNext();
  }

  return (
    <div className="bp-step">
      <div className="bp-form">
        {fields.map((f) => {
          const fieldKey = f.field_key || f.name_key || '';
          const key = `${f.scope}.${fieldKey}`;
          const val = getValue(key);
          return (
            <div className="bp-field" key={key}>
              <label className="bp-label">
                {f.label} {f.is_required ? <span className="bp-required">*</span> : null}
              </label>
              <FieldInput field={f} value={val} onChange={(v) => setValue(key, v)} />
            </div>
          );
        })}
      </div>

      <div className="bp-step-footer">
        <button type="button" className="bp-back" onClick={onBack}>&lt;- Back</button>
        <button type="button" className="bp-next" onClick={handleNext}>
          Next ->
        </button>
      </div>
    </div>
  );
}

function FieldInput({ field, value, onChange }) {
  const baseProps = {
    className: 'bp-input',
    placeholder: field.placeholder || '',
    value: value || '',
    onChange: (e) => onChange(e.target.value),
  };

  if (field.type === 'textarea') {
    return (
      <textarea
        className="bp-input bp-textarea"
        rows={4}
        placeholder={field.placeholder || ''}
        value={value || ''}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }

  if (field.type === 'select') {
    const choices = field.options?.choices || [];
    return (
      <select className="bp-input" value={value || ''} onChange={(e) => onChange(e.target.value)}>
        <option value="">Select...</option>
        {choices.map((c, idx) => (
          <option key={idx} value={c.value}>{c.label}</option>
        ))}
      </select>
    );
  }

  if (field.type === 'checkbox') {
    const checked = value === '1' || value === true;
    return (
      <label className="bp-checkbox">
        <input
          type="checkbox"
          checked={checked}
          onChange={(e) => onChange(e.target.checked ? '1' : '0')}
        />
        <span>{field.placeholder || 'Yes'}</span>
      </label>
    );
  }

  let inputType = 'text';
  if (field.type === 'email') inputType = 'email';
  if (field.type === 'tel') inputType = 'tel';
  if (field.type === 'number') inputType = 'number';
  if (field.type === 'date') inputType = 'date';

  return <input {...baseProps} type={inputType} />;
}
