import React from 'react';

export default function Drawer({ open, title, onClose, children }) {
  if (!open) return null;

  return (
    <div style={styles.backdrop} onMouseDown={onClose}>
      <div style={styles.panel} onMouseDown={(e) => e.stopPropagation()}>
        <div style={styles.header}>
          <div style={{ fontWeight: 900, fontSize: 14 }}>{title}</div>
          <button onClick={onClose} style={styles.closeBtn}>×</button>
        </div>
        <div style={styles.body}>{children}</div>
      </div>
    </div>
  );
}

const styles = {
  backdrop: {
    position: 'fixed', inset: 0, background: 'rgba(15,23,42,.35)',
    display: 'flex', justifyContent: 'flex-end', zIndex: 99999
  },
  panel: {
    width: 'min(440px, 92vw)',
    height: '100%',
    background: '#fff',
    borderLeft: '1px solid #e5e7eb',
    boxShadow: '-18px 0 38px rgba(15,23,42,.16)',
    display: 'flex', flexDirection: 'column'
  },
  header: {
    padding: '14px 14px',
    borderBottom: '1px solid #e5e7eb',
    display: 'flex', alignItems: 'center', justifyContent: 'space-between'
  },
  closeBtn: {
    width: 32, height: 32, borderRadius: 10, border: '1px solid #e5e7eb',
    background: '#fff', cursor: 'pointer', fontWeight: 900, fontSize: 18
  },
  body: { padding: 14, overflow: 'auto' }
};
