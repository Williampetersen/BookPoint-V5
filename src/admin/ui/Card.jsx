import React from 'react';

export function Card({ title, subtitle, right, children }) {
  return (
    <div
      style={{
        background: '#fff',
        border: '1px solid #e5e7eb',
        borderRadius: 16,
        padding: 14,
      }}
    >
      {title || right ? (
        <div
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            gap: 12,
            flexWrap: 'wrap',
            alignItems: 'center',
          }}
        >
          <div>
            {title ? <div style={{ fontWeight: 950, fontSize: 14 }}>{title}</div> : null}
            {subtitle ? (
              <div style={{ color: '#6b7280', fontWeight: 800, marginTop: 4 }}>{subtitle}</div>
            ) : null}
          </div>
          {right}
        </div>
      ) : null}
      <div style={{ marginTop: 12 }}>{children}</div>
    </div>
  );
}
