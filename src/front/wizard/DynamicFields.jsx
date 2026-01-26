import React from 'react';

function normalizeId(def) {
  return def?.id || def?.field_key || def?.name_key || '';
}

function optionLabel(op) {
  if (op && typeof op === 'object') return op.label ?? op.value ?? '';
  return String(op);
}

function optionValue(op) {
  if (op && typeof op === 'object') return op.value ?? op.label ?? '';
  return String(op);
}

export default function DynamicFields({ defs = [], layout = [], values, onChange, scope }) {
  const ordered = layout
    .map((l) => ({ layout: l, def: defs.find((d) => normalizeId(d) === l.id) }))
    .filter((x) => !!x.def);

  const setVal = (id, v) => {
    const key = scope ? `${scope}.${id}` : id;
    onChange({ ...(values || {}), [key]: v });
  };

  return (
    <div className="bp-fields-grid">
      {ordered.map(({ layout: l, def }) => {
        const id = normalizeId(def);
        const required = l.required ?? !!(def.is_required ?? def.required);
        const width = l.width === 'half' ? 'half' : 'full';
        const key = scope ? `${scope}.${id}` : id;
        const v = (values || {})[key] ?? '';

        const label = (
          <label className="bp-label">
            {def.label} {required ? <span className="bp-req">*</span> : null}
          </label>
        );

        if (def.type === 'textarea') {
          return (
            <div className={`bp-field bp-${width}`} key={key}>
              {label}
              <textarea
                className="bp-textarea"
                placeholder={def.placeholder || ''}
                value={v}
                onChange={(e) => setVal(id, e.target.value)}
              />
            </div>
          );
        }

        if (def.type === 'select') {
          const options = Array.isArray(def.options) ? def.options : (def.options?.choices || []);
          return (
            <div className={`bp-field bp-${width}`} key={key}>
              {label}
              <select
                className="bp-select"
                value={v}
                onChange={(e) => setVal(id, e.target.value)}
              >
                <option value="">Select…</option>
                {options.map((op, idx) => (
                  <option key={idx} value={optionValue(op)}>
                    {optionLabel(op)}
                  </option>
                ))}
              </select>
            </div>
          );
        }

        if (def.type === 'checkbox') {
          return (
            <div className={`bp-field bp-${width}`} key={key}>
              <label className="bp-checkbox-row">
                <input
                  type="checkbox"
                  checked={!!v}
                  onChange={(e) => setVal(id, e.target.checked)}
                />
                <span>
                  {def.label} {required ? <span className="bp-req">*</span> : null}
                </span>
              </label>
            </div>
          );
        }

        if (def.type === 'date') {
          return (
            <div className={`bp-field bp-${width}`} key={key}>
              {label}
              <input
                className="bp-input-field"
                type="date"
                value={v}
                onChange={(e) => setVal(id, e.target.value)}
              />
            </div>
          );
        }

        return (
          <div className={`bp-field bp-${width}`} key={key}>
            {label}
            <input
              className="bp-input-field"
              type={def.type === 'tel' ? 'tel' : def.type === 'email' ? 'email' : 'text'}
              placeholder={def.placeholder || ''}
              value={v}
              onChange={(e) => setVal(id, e.target.value)}
            />
          </div>
        );
      })}
    </div>
  );
}
