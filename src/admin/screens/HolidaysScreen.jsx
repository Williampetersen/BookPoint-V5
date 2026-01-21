import React, { useEffect, useState } from 'react';
import { bpFetch } from '../api/client';

export default function HolidaysScreen() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(false);
  const [toast, setToast] = useState(null);

  const pushToast = (type, msg) => setToast({ type, msg });

  const load = async () => {
    setLoading(true);
    try {
      const res = await bpFetch('/admin/holidays');
      setRows(res?.data || []);
    } catch (e) {
      pushToast('error', e.message || 'Load failed');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const create = async () => {
    const today = new Date().toISOString().slice(0,10);
    try {
      await bpFetch('/admin/holidays', {
        method: 'POST',
        body: { title: 'Holiday', start_date: today, end_date: today, is_recurring_yearly: false, is_enabled: true }
      });
      pushToast('success', 'Created ✅');
      load();
    } catch (e) {
      pushToast('error', e.message || 'Create failed');
    }
  };

  const patch = async (id, patchBody) => {
    try {
      await bpFetch(`/admin/holidays/${id}`, { method: 'PATCH', body: patchBody });
      load();
    } catch (e) {
      pushToast('error', e.message || 'Save failed');
    }
  };

  const remove = async (id) => {
    if (!confirm('Delete this holiday?')) return;
    try {
      await bpFetch(`/admin/holidays/${id}`, { method: 'DELETE' });
      pushToast('success', 'Deleted ✅');
      load();
    } catch (e) {
      pushToast('error', e.message || 'Delete failed');
    }
  };

  return (
    <div style={{ padding: 16 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 10 }}>
        <div>
          <h2 style={{ margin: 0 }}>Holidays / Closed Days</h2>
          <div style={{ color: '#6b7280', fontWeight: 800, marginTop: 4 }}>
            Closed dates will remove all availability.
          </div>
        </div>
        <button onClick={create} style={btn}>+ Add holiday</button>
      </div>

      <div style={{ marginTop: 14, background: '#fff', border: '1px solid #e5e7eb', borderRadius: 16, overflow: 'hidden' }}>
        <div style={{ padding: 12, borderBottom: '1px solid #f3f4f6', fontWeight: 950 }}>
          {loading ? 'Loading…' : `${rows.length} items`}
        </div>

        <div style={{ padding: 12, display: 'grid', gap: 10 }}>
          {rows.length === 0 ? (
            <div style={{ color: '#6b7280', fontWeight: 800 }}>No holidays yet.</div>
          ) : null}

          {rows.map(r => (
            <div key={r.id} style={row}>
              <input
                value={r.title || ''}
                onChange={(e) => patch(r.id, { title: e.target.value })}
                style={inputText}
                placeholder="Title"
              />
              <input
                type="date"
                value={(r.start_date || '').slice(0, 10)}
                onChange={(e) => patch(r.id, { start_date: e.target.value })}
                style={input}
              />
              <input
                type="date"
                value={(r.end_date || '').slice(0, 10)}
                onChange={(e) => patch(r.id, { end_date: e.target.value })}
                style={input}
              />
              <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontWeight: 900 }}>
                <input
                  type="checkbox"
                  checked={parseInt(r.is_recurring_yearly || 0, 10) === 1}
                  onChange={(e) => patch(r.id, { is_recurring_yearly: e.target.checked })}
                />
                Yearly
              </label>
              <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontWeight: 900 }}>
                <input
                  type="checkbox"
                  checked={parseInt(r.is_enabled || 0, 10) === 1}
                  onChange={(e) => patch(r.id, { is_enabled: e.target.checked })}
                />
                Enabled
              </label>
              <button onClick={() => remove(r.id)} style={iconBtn}>×</button>
            </div>
          ))}
        </div>
      </div>

      {toast ? (
        <div style={{
          position: 'fixed', right: 18, bottom: 18,
          background: '#0b1437', color: '#fff', padding: '10px 12px',
          borderRadius: 12, fontWeight: 900, zIndex: 999999
        }}>
          {toast.msg}
          <button onClick={() => setToast(null)} style={{
            marginLeft: 10, background: 'transparent', border: 'none', color: '#fff',
            cursor: 'pointer', fontWeight: 900, fontSize: 16
          }}>×</button>
        </div>
      ) : null}
    </div>
  );
}

const btn = { background: '#4318ff', color: '#fff', border: 'none', borderRadius: 12, padding: '10px 12px', fontWeight: 950, cursor: 'pointer' };
const input = { padding: '9px 10px', borderRadius: 12, border: '1px solid #e5e7eb', fontWeight: 800 };
const inputText = { padding: '9px 10px', borderRadius: 12, border: '1px solid #e5e7eb', fontWeight: 800, width: '100%' };
const row = { display: 'grid', gridTemplateColumns: '2fr 1fr 1fr auto auto auto', gap: 10, alignItems: 'center' };
const iconBtn = { width: 32, height: 32, borderRadius: 10, border: '1px solid #e5e7eb', background: '#fff', cursor: 'pointer', fontWeight: 900, fontSize: 18 };
