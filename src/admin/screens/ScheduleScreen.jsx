import React, { useEffect, useMemo, useState } from 'react';
import { bpFetch } from '../api/client';

const DAYS = [
  { k: '1', label: 'Mon' },
  { k: '2', label: 'Tue' },
  { k: '3', label: 'Wed' },
  { k: '4', label: 'Thu' },
  { k: '5', label: 'Fri' },
  { k: '6', label: 'Sat' },
  { k: '7', label: 'Sun' },
];

const emptyHours = () => {
  const o = {};
  for (const d of DAYS) o[d.k] = [];
  return o;
};

export default function ScheduleScreen() {
  const [agents, setAgents] = useState([]);
  const [agentId, setAgentId] = useState(0);

  const [hours, setHours] = useState(emptyHours());
  const [breaks, setBreaks] = useState([]);

  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(null);

  const pushToast = (type, msg) => setToast({ type, msg });

  useEffect(() => {
    (async () => {
      try {
        const aRes = await bpFetch('/admin/agents');
        const list = aRes?.data || [];
        setAgents(list);
        if (list[0]?.id) setAgentId(list[0].id);
      } catch (e) {
        pushToast('error', e.message || 'Failed to load agents');
      }
    })();
  }, []);

  const loadSchedule = async (id) => {
    if (!id) return;
    setLoading(true);
    try {
      const res = await bpFetch(`/admin/agents/${id}/schedule`);
      setHours(res?.data?.hours || emptyHours());
      setBreaks(res?.data?.breaks || []);
    } catch (e) {
      pushToast('error', e.message || 'Failed to load schedule');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { if (agentId) loadSchedule(agentId); }, [agentId]);

  const addInterval = (dayKey) => {
    setHours(prev => ({
      ...prev,
      [dayKey]: [
        ...(prev[dayKey] || []),
        { start_time: '09:00', end_time: '17:00', is_enabled: true }
      ]
    }));
  };

  const updateInterval = (dayKey, idx, patch) => {
    setHours(prev => {
      const list = [...(prev[dayKey] || [])];
      list[idx] = { ...list[idx], ...patch };
      return { ...prev, [dayKey]: list };
    });
  };

  const removeInterval = (dayKey, idx) => {
    setHours(prev => {
      const list = [...(prev[dayKey] || [])];
      list.splice(idx, 1);
      return { ...prev, [dayKey]: list };
    });
  };

  const addBreak = () => {
    const today = new Date().toISOString().slice(0, 10);
    setBreaks(prev => [
      ...prev,
      { break_date: today, start_time: '12:00', end_time: '13:00', note: '' }
    ]);
  };

  const updateBreak = (idx, patch) => {
    setBreaks(prev => {
      const list = [...prev];
      list[idx] = { ...list[idx], ...patch };
      return list;
    });
  };

  const removeBreak = (idx) => {
    setBreaks(prev => {
      const list = [...prev];
      list.splice(idx, 1);
      return list;
    });
  };

  const save = async () => {
    if (!agentId) return;
    setSaving(true);
    try {
      await bpFetch(`/admin/agents/${agentId}/schedule`, {
        method: 'POST',
        body: { hours, breaks }
      });
      pushToast('success', 'Schedule saved ✅');
      await loadSchedule(agentId);
    } catch (e) {
      pushToast('error', e.message || 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  const copyFrom = async (fromId) => {
    if (!agentId || !fromId) return;
    if (fromId === agentId) return pushToast('error', 'Choose another agent to copy from');
    setSaving(true);
    try {
      await bpFetch(`/admin/agents/${agentId}/schedule/copy`, {
        method: 'POST',
        body: { from_agent_id: fromId }
      });
      pushToast('success', 'Schedule copied ✅');
      await loadSchedule(agentId);
    } catch (e) {
      pushToast('error', e.message || 'Copy failed');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div style={{ padding: 16 }}>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap' }}>
        <div>
          <h2 style={{ margin: 0 }}>Schedule</h2>
          <div style={{ color: '#6b7280', fontWeight: 800, marginTop: 4 }}>
            Configure working hours and breaks per agent.
          </div>
        </div>

        <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
          <select value={agentId} onChange={(e) => setAgentId(parseInt(e.target.value, 10))}
            style={input}>
            {agents.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
          </select>

          <CopyBox agents={agents} currentId={agentId} onCopy={copyFrom} disabled={saving || loading} />

          <button onClick={save} disabled={saving || loading} style={btn}>
            {saving ? 'Saving…' : 'Save Schedule'}
          </button>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 14, marginTop: 14 }}>
        <div style={card}>
          <div style={cardTitle}>Working Hours</div>

          {loading ? <div style={{ fontWeight: 900 }}>Loading…</div> : (
            <div style={{ display: 'grid', gap: 10 }}>
              {DAYS.map(d => (
                <div key={d.k} style={dayRow}>
                  <div style={{ width: 60, fontWeight: 950 }}>{d.label}</div>

                  <div style={{ flex: 1, display: 'grid', gap: 8 }}>
                    {(hours[d.k] || []).length === 0 ? (
                      <div style={{ color: '#6b7280', fontWeight: 800 }}>
                        No intervals
                      </div>
                    ) : null}

                    {(hours[d.k] || []).map((it, idx) => (
                      <div key={idx} style={intervalRow}>
                        <input type="time" value={it.start_time}
                          onChange={(e) => updateInterval(d.k, idx, { start_time: e.target.value })}
                          style={inputSmall}
                        />
                        <span style={{ fontWeight: 900 }}>→</span>
                        <input type="time" value={it.end_time}
                          onChange={(e) => updateInterval(d.k, idx, { end_time: e.target.value })}
                          style={inputSmall}
                        />
                        <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontWeight: 900 }}>
                          <input type="checkbox" checked={!!it.is_enabled}
                            onChange={(e) => updateInterval(d.k, idx, { is_enabled: e.target.checked })}
                          />
                          Enabled
                        </label>
                        <button onClick={() => removeInterval(d.k, idx)} style={iconBtn} title="Remove">
                          ×
                        </button>
                      </div>
                    ))}
                  </div>

                  <button onClick={() => addInterval(d.k)} style={btnSecondary}>
                    + Add
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>

        <div style={card}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div style={cardTitle}>Breaks (Date-based)</div>
            <button onClick={addBreak} style={btnSecondary}>+ Add break</button>
          </div>

          {loading ? <div style={{ fontWeight: 900 }}>Loading…</div> : (
            <div style={{ display: 'grid', gap: 10, marginTop: 10 }}>
              {breaks.length === 0 ? (
                <div style={{ color: '#6b7280', fontWeight: 800 }}>No breaks</div>
              ) : null}

              {breaks.map((b, idx) => (
                <div key={idx} style={breakRow}>
                  <input type="date" value={b.break_date}
                    onChange={(e) => updateBreak(idx, { break_date: e.target.value })}
                    style={inputSmall}
                  />
                  <input type="time" value={b.start_time}
                    onChange={(e) => updateBreak(idx, { start_time: e.target.value })}
                    style={inputSmall}
                  />
                  <input type="time" value={b.end_time}
                    onChange={(e) => updateBreak(idx, { end_time: e.target.value })}
                    style={inputSmall}
                  />
                  <input type="text" value={b.note || ''}
                    onChange={(e) => updateBreak(idx, { note: e.target.value })}
                    placeholder="Note (optional)"
                    style={inputText}
                  />
                  <button onClick={() => removeBreak(idx)} style={iconBtn} title="Remove">×</button>
                </div>
              ))}
            </div>
          )}
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

function CopyBox({ agents, currentId, onCopy, disabled }) {
  const [fromId, setFromId] = useState(0);
  const options = useMemo(() => agents.filter(a => a.id !== currentId), [agents, currentId]);

  return (
    <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
      <select value={fromId} onChange={(e) => setFromId(parseInt(e.target.value, 10))}
        style={input} disabled={disabled}>
        <option value={0}>Copy from…</option>
        {options.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
      </select>
      <button onClick={() => fromId && onCopy(fromId)} disabled={disabled || !fromId} style={btnSecondary}>
        Copy
      </button>
    </div>
  );
}

const card = {
  background: '#fff',
  border: '1px solid #e5e7eb',
  borderRadius: 16,
  padding: 14
};
const cardTitle = { fontWeight: 950, fontSize: 14 };
const dayRow = { display: 'flex', gap: 10, alignItems: 'flex-start', borderTop: '1px solid #f3f4f6', paddingTop: 10 };
const intervalRow = { display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' };
const breakRow = { display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 2fr auto', gap: 8, alignItems: 'center' };

const input = { padding: '9px 10px', borderRadius: 12, border: '1px solid #e5e7eb', fontWeight: 800 };
const inputSmall = { padding: '8px 10px', borderRadius: 12, border: '1px solid #e5e7eb', fontWeight: 800, width: 140 };
const inputText = { padding: '8px 10px', borderRadius: 12, border: '1px solid #e5e7eb', fontWeight: 700, width: '100%' };

const btn = { background: '#4318ff', color: '#fff', border: 'none', borderRadius: 12, padding: '10px 12px', fontWeight: 950, cursor: 'pointer' };
const btnSecondary = { background: '#eef2ff', color: '#1e1b4b', border: '1px solid rgba(67,24,255,.15)', borderRadius: 12, padding: '10px 12px', fontWeight: 950, cursor: 'pointer' };
const iconBtn = { width: 32, height: 32, borderRadius: 10, border: '1px solid #e5e7eb', background: '#fff', cursor: 'pointer', fontWeight: 900, fontSize: 18 };
