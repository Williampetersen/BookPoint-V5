import React from 'react';

export function Drawer({ open, title, onClose, children, footer }) {
  if (!open) return null;
  return (
    <div style={{ position: 'fixed', inset: 0, zIndex: 999999 }}>
      <div
        onClick={onClose}
        style={{ position: 'absolute', inset: 0, background: 'rgba(15,23,42,.40)' }}
      />
      <div
        style={{
          position: 'absolute',
          right: 0,
          top: 0,
          height: '100%',
          width: 'min(520px, 92vw)',
          background: '#fff',
          boxShadow: '-10px 0 30px rgba(0,0,0,.10)',
          display: 'flex',
          flexDirection: 'column',
        }}
      >
        <div
          style={{
            padding: 14,
            borderBottom: '1px solid #eef2f7',
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
          }}
        >
          <div style={{ fontWeight: 950 }}>{title}</div>
          <button onClick={onClose} style={iconBtn}>
            ×
          </button>
        </div>
        <div style={{ padding: 14, overflow: 'auto', flex: 1 }}>{children}</div>
        {footer ? <div style={{ padding: 14, borderTop: '1px solid #eef2f7' }}>{footer}</div> : null}
      </div>
    </div>
  );
}

const iconBtn = {
  width: 32,
  height: 32,
  borderRadius: 10,
  border: '1px solid #e5e7eb',
  background: '#fff',
  cursor: 'pointer',
  fontWeight: 900,
  fontSize: 18,
};
