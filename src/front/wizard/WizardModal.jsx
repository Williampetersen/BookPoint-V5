import React, { useEffect, useMemo, useState } from 'react';
import { bpPublicFetch } from '../api/client';

export default function WizardModal({ onClose }) {
  const [step, setStep] = useState(1);

  const [categories, setCategories] = useState([]);
  const [services, setServices] = useState([]);
  const [extras, setExtras] = useState([]);
  const [agents, setAgents] = useState([]);

  const [selectedCategory, setSelectedCategory] = useState(null);
  const [selectedService, setSelectedService] = useState(null);
  const [selectedExtras, setSelectedExtras] = useState([]);
  const [selectedAgent, setSelectedAgent] = useState(null);
  const [selectedDate, setSelectedDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [slots, setSlots] = useState([]);
  const [selectedSlot, setSelectedSlot] = useState(null);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const resetAfterCategory = () => {
    setSelectedService(null);
    setSelectedExtras([]);
    setSelectedAgent(null);
    setServices([]);
    setExtras([]);
    setAgents([]);
  };

  const resetAfterService = () => {
    setSelectedExtras([]);
    setSelectedAgent(null);
    setExtras([]);
    setAgents([]);
  };

  useEffect(() => {
    (async () => {
      setLoading(true);
      try {
        const res = await bpPublicFetch('/public/categories');
        setCategories(res?.data || []);
      } catch (e) {
        setError(e.message || 'Failed to load categories');
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  const loadServices = async (categoryId) => {
    setLoading(true);
    setError('');
    try {
      const res = await bpPublicFetch(`/public/services?category_id=${categoryId}`);
      setServices(res?.data || []);
      setStep(2);
    } catch (e) {
      setError(e.message || 'Failed to load services');
    } finally {
      setLoading(false);
    }
  };

  const loadExtrasAgents = async (serviceId) => {
    setLoading(true);
    setError('');
    try {
      const [eRes, aRes] = await Promise.all([
        bpPublicFetch(`/public/extras?service_id=${serviceId}`),
        bpPublicFetch(`/public/agents?service_id=${serviceId}`),
      ]);
      setExtras(eRes?.data || []);
      setAgents(aRes?.data || []);
      setStep(3); // extras step
    } catch (e) {
      setError(e.message || 'Failed to load extras/agents');
    } finally {
      setLoading(false);
    }
  };

  const loadSlots = async (date, serviceId, agentId) => {
    if (!serviceId || !agentId || !date) return;
    setLoading(true);
    setError('');
    try {
      const res = await bpPublicFetch(
        `/public/availability-slots?date=${date}&service_id=${serviceId}&agent_id=${agentId}`
      );
      setSlots(res?.data?.slots || []);
    } catch (e) {
      setError(e.message || 'Failed to load slots');
      setSlots([]);
    } finally {
      setLoading(false);
    }
  };

  const goBack = () => {
    if (step === 1) return onClose();
    if (step === 2) { setStep(1); return; }
    if (step === 3) { setStep(2); return; }
    if (step === 4) { setStep(3); return; }
    if (step === 5) { setStep(4); return; }
  };

  const nextFromExtras = () => setStep(4);

  const totalPrice = useMemo(() => {
    const s = selectedService?.price ? Number(selectedService.price) : 0;
    const ex = selectedExtras.reduce((sum, x) => sum + Number(x.price || 0), 0);
    return (s + ex).toFixed(2);
  }, [selectedService, selectedExtras]);

  return (
    <div style={overlay}>
      <div style={modal}>
        <div style={topbar}>
          <div>
            <div style={{ fontWeight: 950 }}>BookPoint</div>
            <div style={{ color: '#6b7280', fontWeight: 800, fontSize: 12 }}>
              Step {step} / 7
            </div>
          </div>

          <div style={{ display: 'flex', gap: 8 }}>
            <button onClick={goBack} style={btn2}>Back</button>
            <button onClick={onClose} style={iconBtn}>×</button>
          </div>
        </div>

        <div style={{ padding: 14, overflow: 'auto', flex: 1 }}>
          {error ? <div style={err}>{error}</div> : null}
          {loading ? <div style={{ fontWeight: 900 }}>Loading…</div> : null}

          {!loading && step === 1 ? (
            <GridSelect
              title="Choose a category"
              items={categories}
              selectedId={selectedCategory?.id}
              onSelect={(c) => {
                setSelectedCategory(c);
                resetAfterCategory();
                loadServices(c.id);
              }}
            />
          ) : null}

          {!loading && step === 2 ? (
            <GridSelect
              title="Choose a service"
              items={services}
              selectedId={selectedService?.id}
              onSelect={(s) => {
                setSelectedService(s);
                resetAfterService();
                loadExtrasAgents(s.id);
              }}
            />
          ) : null}

          {!loading && step === 3 ? (
            <div>
              <h3 style={h3}>Service Extras</h3>
              <div style={{ color: '#6b7280', fontWeight: 800, marginTop: -8, marginBottom: 10 }}>
                Optional. Choose any extras.
              </div>

              <div style={grid}>
                {extras.length === 0 ? (
                  <div style={{ color: '#6b7280', fontWeight: 800 }}>No extras for this service.</div>
                ) : extras.map((x) => {
                  const active = selectedExtras.some((e) => e.id === x.id);
                  return (
                    <button
                      key={x.id}
                      type="button"
                      onClick={() => {
                        setSelectedExtras((prev) => {
                          const has = prev.some((p) => p.id === x.id);
                          if (has) return prev.filter((p) => p.id !== x.id);
                          return [...prev, x];
                        });
                      }}
                      style={{
                        ...tile,
                        borderColor: active ? 'rgba(67,24,255,.55)' : '#e5e7eb',
                        background: active ? 'rgba(67,24,255,.08)' : '#fff',
                      }}
                    >
                      <img
                        src={x.image_url || ''}
                        alt=""
                        style={img}
                        onError={(e) => {
                          e.currentTarget.style.display = 'none';
                        }}
                      />
                      <div style={{ fontWeight: 950, textAlign: 'left' }}>{x.name}</div>
                      <div style={{ color: '#6b7280', fontWeight: 800, textAlign: 'left' }}>
                        + {Number(x.price || 0).toFixed(2)}
                      </div>
                    </button>
                  );
                })}
              </div>

              <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 14, alignItems: 'center', flexWrap: 'wrap', gap: 10 }}>
                <div style={{ fontWeight: 950 }}>Total so far: {totalPrice}</div>
                <button style={btn} onClick={nextFromExtras}>Next: Choose agent</button>
              </div>
            </div>
          ) : null}

          {!loading && step === 4 ? (
            <GridSelect
              title="Choose an agent"
              items={agents}
              selectedId={selectedAgent?.id}
              onSelect={(a) => {
                setSelectedAgent(a);
                setStep(5);
                setSelectedSlot(null);
                loadSlots(selectedDate, selectedService.id, a.id);
              }}
              compact
            />
          ) : null}

          {!loading && step === 5 ? (
            <div>
              <h3 style={h3}>Choose date & time</h3>
              <div style={{ color: '#6b7280', fontWeight: 800, marginTop: -8, marginBottom: 10 }}>
                Pick an available time slot.
              </div>

              <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', alignItems: 'center' }}>
                <div style={{ fontWeight: 900 }}>Date</div>
                <input
                  type="date"
                  value={selectedDate}
                  onChange={(e) => {
                    const d = e.target.value;
                    setSelectedDate(d);
                    setSelectedSlot(null);
                    if (selectedService?.id && selectedAgent?.id) {
                      loadSlots(d, selectedService.id, selectedAgent.id);
                    }
                  }}
                  style={{ padding: '10px 12px', borderRadius: 12, border: '1px solid #e5e7eb', fontWeight: 900 }}
                />
                <button
                  style={btn2}
                  onClick={() => {
                    if (selectedService?.id && selectedAgent?.id) {
                      loadSlots(selectedDate, selectedService.id, selectedAgent.id);
                    }
                  }}
                >
                  Refresh slots
                </button>
              </div>

              <div style={{ marginTop: 14 }}>
                {slots.length === 0 ? (
                  <div style={{ color: '#6b7280', fontWeight: 800 }}>
                    No available times for this date.
                  </div>
                ) : (
                  <div
                    style={{
                      display: 'grid',
                      gridTemplateColumns: 'repeat(auto-fill, minmax(140px, 1fr))',
                      gap: 10,
                    }}
                  >
                    {slots.map((s, idx) => {
                      const active = selectedSlot?.start_time === s.start_time;
                      return (
                        <button
                          key={idx}
                          type="button"
                          onClick={() => setSelectedSlot(s)}
                          style={{
                            padding: '12px 10px',
                            borderRadius: 14,
                            border: '1px solid',
                            borderColor: active ? 'rgba(67,24,255,.55)' : '#e5e7eb',
                            background: active ? 'rgba(67,24,255,.10)' : '#fff',
                            fontWeight: 950,
                            cursor: 'pointer',
                            textAlign: 'center',
                          }}
                        >
                          {s.label || s.start_time.slice(0, 5)}
                        </button>
                      );
                    })}
                  </div>
                )}
              </div>

              <div
                style={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  marginTop: 14,
                  alignItems: 'center',
                  flexWrap: 'wrap',
                  gap: 10,
                }}
              >
                <div style={{ fontWeight: 950 }}>
                  Selected: {selectedSlot ? selectedSlot.label : '—'}
                </div>
                <button
                  style={btn}
                  disabled={!selectedSlot}
                  onClick={() => {
                    alert('A8 done ✅ Next step (A9) will add Form Fields + Summary + Submit booking');
                  }}
                >
                  Next: Your details
                </button>
              </div>
            </div>
          ) : null}
        </div>

        <div style={footer}>
          <div style={{ fontWeight: 900, fontSize: 12, color: '#6b7280' }}>
            Selected: {selectedCategory?.name || '—'} → {selectedService?.name || '—'} → {selectedAgent?.name || '—'}
          </div>
          <div style={{ fontWeight: 950 }}>Total: {totalPrice}</div>
        </div>
      </div>
    </div>
  );
}

function GridSelect({ title, items, selectedId, onSelect, compact = false }) {
  return (
    <div>
      <h3 style={h3}>{title}</h3>
      <div style={{ ...grid, gridTemplateColumns: compact ? 'repeat(auto-fill, minmax(160px, 1fr))' : 'repeat(auto-fill, minmax(200px, 1fr))' }}>
        {items.length === 0 ? (
          <div style={{ color: '#6b7280', fontWeight: 800 }}>No items</div>
        ) : items.map((it) => {
          const active = selectedId === it.id;
          return (
            <button
              key={it.id}
              type="button"
              onClick={() => onSelect(it)}
              style={{
                ...tile,
                borderColor: active ? 'rgba(67,24,255,.55)' : '#e5e7eb',
                background: active ? 'rgba(67,24,255,.08)' : '#fff',
              }}
            >
              <img
                src={it.image_url || ''}
                alt=""
                style={img}
                onError={(e) => {
                  e.currentTarget.style.display = 'none';
                }}
              />
              <div style={{ fontWeight: 950, textAlign: 'left' }}>{it.name}</div>
              {'price' in it ? (
                <div style={{ color: '#6b7280', fontWeight: 800, textAlign: 'left' }}>{Number(it.price || 0).toFixed(2)}</div>
              ) : null}
              {'duration' in it ? (
                <div style={{ color: '#6b7280', fontWeight: 800, textAlign: 'left' }}>{it.duration} min</div>
              ) : null}
            </button>
          );
        })}
      </div>
    </div>
  );
}

/* styles */
const overlay = { position: 'fixed', inset: 0, background: 'rgba(2,6,23,.55)', zIndex: 999999, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 14 };
const modal = { width: 'min(980px, 100%)', height: 'min(86vh, 720px)', background: '#fff', borderRadius: 20, overflow: 'hidden', display: 'flex', flexDirection: 'column', boxShadow: '0 30px 80px rgba(0,0,0,.35)' };
const topbar = { padding: 14, borderBottom: '1px solid #eef2f7', display: 'flex', justifyContent: 'space-between', alignItems: 'center' };
const footer = { padding: 14, borderTop: '1px solid #eef2f7', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10 };
const h3 = { margin: '0 0 10px 0' };

const grid = { display: 'grid', gap: 12, gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))' };
const tile = { border: '1px solid #e5e7eb', borderRadius: 16, padding: 12, cursor: 'pointer', display: 'grid', gap: 8, textAlign: 'left' };
const img = { width: '100%', height: 110, borderRadius: 14, objectFit: 'cover', background: '#f3f4f6', border: '1px solid #eef2f7' };

const btn = { background: '#4318ff', color: '#fff', border: 'none', borderRadius: 12, padding: '10px 12px', fontWeight: 950, cursor: 'pointer' };
const btn2 = { background: '#eef2ff', color: '#1e1b4b', border: '1px solid rgba(67,24,255,.15)', borderRadius: 12, padding: '10px 12px', fontWeight: 950, cursor: 'pointer' };
const iconBtn = { width: 34, height: 34, borderRadius: 12, border: '1px solid #e5e7eb', background: '#fff', cursor: 'pointer', fontWeight: 900, fontSize: 18 };

const err = { background: '#fef2f2', border: '1px solid #fecaca', color: '#991b1b', padding: 10, borderRadius: 12, fontWeight: 900, marginBottom: 10 };
