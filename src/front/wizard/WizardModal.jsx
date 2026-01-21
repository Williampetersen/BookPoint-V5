import React, { useEffect, useMemo, useState } from 'react';

const API_BASE = (window.BP_FRONT?.restUrl || '/wp-json/bp/v1');

async function apiGet(path) {
  const res = await fetch(`${API_BASE}${path}`, { credentials: 'same-origin' });
  const json = await res.json().catch(() => ({}));
  if (!res.ok || json?.status === 'error') {
    throw new Error(json?.message || 'Request failed');
  }
  return json;
}

async function apiPost(path, body) {
  const res = await fetch(`${API_BASE}${path}`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body || {}),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok || json?.status === 'error') {
    throw new Error(json?.message || 'Request failed');
  }
  return json;
}

export default function WizardModal({ onClose }) {
  const [step, setStep] = useState(0);

  const [categories, setCategories] = useState([]);
  const [services, setServices] = useState([]);
  const [extras, setExtras] = useState([]);
  const [agents, setAgents] = useState([]);

  const [categoryId, setCategoryId] = useState(null);
  const [serviceId, setServiceId] = useState(null);
  const [selectedExtras, setSelectedExtras] = useState([]);
  const [agentId, setAgentId] = useState(null);
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [slot, setSlot] = useState(null);

  const [formFields, setFormFields] = useState([]);
  const [fieldsLoading, setFieldsLoading] = useState(false);
  const [fieldValues, setFieldValues] = useState({});

  const [slots, setSlots] = useState([]);
  const [loadingSlots, setLoadingSlots] = useState(false);

  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState(null);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const [bpSettings, setBpSettings] = useState({
    slot_interval_minutes: 15,
    currency: 'USD',
    currency_position: 'before',
  });

  const selectedCategory = useMemo(
    () => categories.find((c) => String(c.id) === String(categoryId)) || null,
    [categories, categoryId]
  );

  const selectedService = useMemo(
    () => services.find((s) => String(s.id) === String(serviceId)) || null,
    [services, serviceId]
  );

  const selectedAgent = useMemo(
    () => agents.find((a) => String(a.id) === String(agentId)) || null,
    [agents, agentId]
  );

  const resetAfterCategory = () => {
    setServiceId(null);
    setSelectedExtras([]);
    setAgentId(null);
    setServices([]);
    setExtras([]);
    setAgents([]);
    setSlots([]);
    setSlot(null);
  };

  const resetAfterService = () => {
    setSelectedExtras([]);
    setAgentId(null);
    setExtras([]);
    setAgents([]);
    setSlots([]);
    setSlot(null);
  };

  useEffect(() => {
    (async () => {
      setLoading(true);
      try {
        const res = await apiGet('/public/categories');
        setCategories(res?.data || []);
      } catch (e) {
        setError(e.message || 'Failed to load categories');
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  useEffect(() => {
    (async () => {
      try {
        const res = await apiGet('/public/settings');
        if (res?.data) setBpSettings(res.data);
      } catch (e) {
        // keep defaults
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    (async () => {
      setFieldsLoading(true);
      try {
        const res = await apiGet('/public/form-fields');
        setFormFields(Array.isArray(res?.data) ? res.data : []);
      } catch (e) {
        setFormFields([]);
        console.warn('Failed to load form fields', e);
      } finally {
        setFieldsLoading(false);
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const loadServices = async (categoryId) => {
    setLoading(true);
    setError('');
    try {
      const res = await apiGet(`/public/services?category_id=${categoryId}`);
      setServices(res?.data || []);
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
        apiGet(`/public/extras?service_id=${serviceId}`),
        apiGet(`/public/agents?service_id=${serviceId}`),
      ]);
      setExtras(eRes?.data || []);
      setAgents(aRes?.data || []);
    } catch (e) {
      setError(e.message || 'Failed to load extras/agents');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const canLoad = serviceId && agentId && date;
    if (!canLoad) {
      setSlots([]);
      setSlot(null);
      return;
    }

    (async () => {
      setError('');
      setLoadingSlots(true);
      try {
        const qs = new URLSearchParams({
          date,
          service_id: String(serviceId),
          agent_id: String(agentId),
        }).toString();

        const res = await apiGet(`/public/availability-slots?${qs}`);
        const list = Array.isArray(res?.data)
          ? res.data
          : (Array.isArray(res?.data?.slots) ? res.data.slots : []);
        setSlots(list);
        if (slot && !list.some((s) => s.start_time === slot.start_time)) {
          setSlot(null);
        }
      } catch (e) {
        setSlots([]);
        setSlot(null);
        setError(e.message || 'Failed to load time slots');
      } finally {
        setLoadingSlots(false);
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [serviceId, agentId, date]);

  const detailsFields = useMemo(
    () => (formFields || []).filter((f) => (f.step_key || 'details') === 'details'),
    [formFields]
  );

  const bookingFields = useMemo(
    () => detailsFields.filter((f) => f.scope === 'booking'),
    [detailsFields]
  );

  const customerFields = useMemo(
    () => detailsFields.filter((f) => f.scope === 'customer'),
    [detailsFields]
  );

  useEffect(() => {
    if (!formFields?.length) return;

    setFieldValues((prev) => {
      const next = { ...prev };
      for (const f of formFields) {
        const key = `${f.scope}.${f.field_key}`;
        if (f.type === 'checkbox' && next[key] === undefined) next[key] = '0';
      }
      return next;
    });
  }, [formFields]);

  function setField(key, val) {
    setFieldValues((prev) => ({ ...prev, [key]: val }));
  }

  function getField(key) {
    return fieldValues[key] ?? '';
  }

  function validateFields(list) {
    for (const f of list) {
      if (!f.is_required) continue;

      const key = `${f.scope}.${f.field_key}`;
      const v = fieldValues[key];

      const empty =
        v === undefined ||
        v === null ||
        v === '' ||
        (Array.isArray(v) && v.length === 0) ||
        (f.type === 'checkbox' && (v === '0' || v === false));

      if (empty) return `${f.label} is required`;
    }
    return '';
  }

  function nextStep() {
    setError('');

    // 0 category -> 1 service -> 2 agent -> 3 date/time -> 4 details -> 5 summary
    if (step === 0 && !categoryId) return setError('Please select a category');
    if (step === 1 && !serviceId) return setError('Please select a service');
    if (step === 2 && !agentId) return setError('Please select an agent');
    if (step === 3) {
      if (!date) return setError('Please select a date');
      if (!slot?.start_time) return setError('Please select a time slot');
    }
    if (step === 4) {
      const msg = validateFields(detailsFields);
      if (msg) return setError(msg);
    }

    setStep((s) => s + 1);
  }

  function prevStep() {
    setError('');
    setStep((s) => Math.max(0, s - 1));
  }

  async function submitBooking() {
    setError('');
    setSubmitting(true);
    try {
      const payload = {
        service_id: serviceId,
        agent_id: agentId,
        date,
        start_time: slot?.start_time,
        field_values: fieldValues,
      };

      const res = await apiPost('/public/bookings', payload);
      const booking_id = res?.data?.booking_id || res?.data?.id;
      setSuccess({ booking_id });
      setStep(999);
    } catch (e) {
      setError(e.message || 'Booking failed');
    } finally {
      setSubmitting(false);
    }
  }

  function FieldInput({ f, value, onChange }) {
    const base = {
      style: {
        width: '100%',
        padding: '10px 12px',
        borderRadius: 14,
        border: '1px solid #e5e7eb',
        fontWeight: 850,
        background: '#fff',
        outline: 'none',
      }
    };

    if (f.type === 'textarea') {
      return (
        <textarea
          {...base}
          rows={5}
          placeholder={f.placeholder || ''}
          value={value || ''}
          onChange={(e)=>onChange(e.target.value)}
        />
      );
    }

    if (f.type === 'select') {
      const choices = (f.options?.choices || []);
      return (
        <select {...base} value={value || ''} onChange={(e)=>onChange(e.target.value)}>
          <option value="">Select…</option>
          {choices.map((c, idx)=>(
            <option key={idx} value={c.value}>{c.label}</option>
          ))}
        </select>
      );
    }

    if (f.type === 'checkbox') {
      const checked = value === '1' || value === true;
      return (
        <label style={{display:'flex', alignItems:'center', gap:10, fontWeight:900}}>
          <input
            type="checkbox"
            checked={checked}
            onChange={(e)=>onChange(e.target.checked ? '1' : '0')}
          />
          {f.placeholder || 'Yes'}
        </label>
      );
    }

    const inputType =
      f.type === 'email' ? 'email' :
      f.type === 'tel' ? 'tel' :
      f.type === 'number' ? 'number' :
      f.type === 'date' ? 'date' :
      'text';

    return (
      <input
        {...base}
        type={inputType}
        placeholder={f.placeholder || ''}
        value={value || ''}
        onChange={(e)=>onChange(e.target.value)}
      />
    );
  }

  function formatMoney(amount, settings){
    const n = Number(amount || 0);
    const cur = settings?.currency || 'USD';
    const pos = settings?.currency_position || 'before';
    const symbolMap = { USD:'$', EUR:'€', DKK:'kr', GBP:'£' };
    const sym = symbolMap[cur] || cur;

    const value = n.toFixed(2);
    return pos === 'after' ? `${value} ${sym}` : `${sym} ${value}`;
  }

  const totalPrice = useMemo(() => {
    const s = selectedService?.price ? Number(selectedService.price) : 0;
    const ex = selectedExtras.reduce((sum, x) => sum + Number(x.price || 0), 0);
    return (s + ex).toFixed(2);
  }, [selectedService, selectedExtras]);

  if (step === 999 && success) {
    return (
      <div style={overlay}>
        <div style={modal}>
          <div style={topbar}>
            <div style={{ fontWeight: 950 }}>BookPoint</div>
            <div style={{ display: 'flex', gap: 8 }}>
              <button onClick={onClose} style={iconBtn}>×</button>
            </div>
          </div>

          <div style={{ padding: 18, overflow: 'auto', flex: 1 }}>
            <h3 style={{ margin: 0, fontWeight: 1000 }}>Booking created ✅</h3>
            <div style={{ marginTop: 10, fontWeight: 900, opacity: .8 }}>
              Booking ID: {success.booking_id}
            </div>
            <div style={{ marginTop: 16 }}>
              <button className="bp-btn" onClick={() => window.location.reload()}>
                Close
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div style={overlay}>
      <div style={modal}>
        <div style={topbar}>
          <div>
            <div style={{ fontWeight: 950 }}>BookPoint</div>
            <div style={{ color: '#6b7280', fontWeight: 800, fontSize: 12 }}>
              Step {Math.min(step, 5) + 1} / 6
            </div>
          </div>

          <div style={{ display: 'flex', gap: 8 }}>
            <button onClick={step === 0 ? onClose : prevStep} style={btn2}>Back</button>
            <button onClick={onClose} style={iconBtn}>×</button>
          </div>
        </div>

        <div style={{ padding: 14, overflow: 'auto', flex: 1 }}>
          {error && step !== 3 ? <div style={err}>{error}</div> : null}
          {loading ? <div style={{ fontWeight: 900 }}>Loading…</div> : null}

          {!loading && step === 0 ? (
            <div>
              <GridSelect
                title="Choose a category"
                items={categories}
                selectedId={categoryId}
                onSelect={(c) => {
                  setCategoryId(c.id);
                  resetAfterCategory();
                  loadServices(c.id);
                }}
              />

              <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 14 }}>
                <button style={btn} onClick={nextStep}>Next</button>
              </div>
            </div>
          ) : null}

          {!loading && step === 1 ? (
            <div>
              <GridSelect
                title="Choose a service"
                items={services}
                selectedId={serviceId}
                onSelect={(s) => {
                  setServiceId(s.id);
                  resetAfterService();
                  loadExtrasAgents(s.id);
                }}
              />

              <div style={{ height: 14 }} />

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
                <button style={btn} onClick={nextStep}>Next: Choose agent</button>
              </div>
            </div>
          ) : null}

          {!loading && step === 2 ? (
            <div>
              <GridSelect
                title="Choose an agent"
                items={agents}
                selectedId={agentId}
                onSelect={(a) => setAgentId(a.id)}
                compact
              />

              <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 14 }}>
                <button style={btn} onClick={nextStep}>Next: Choose time</button>
              </div>
            </div>
          ) : null}

          {!loading && step === 3 ? (
            <div>
              <h3 style={h3}>Choose date & time</h3>
              <div style={{ color: '#6b7280', fontWeight: 800, marginTop: -8, marginBottom: 10 }}>
                Pick an available time slot.
              </div>

              <div style={{ marginTop: 12 }}>
                <label style={{ fontWeight: 900 }}>Date</label>
                <input
                  type="date"
                  value={date}
                  onChange={(e) => setDate(e.target.value)}
                  style={{ width: '100%', padding: '10px 12px', borderRadius: 12, border: '1px solid #e5e7eb', marginTop: 6 }}
                />
              </div>

              <div style={{ marginTop: 12 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <label style={{ fontWeight: 900 }}>Time slots</label>
                  {loadingSlots ? <span style={{ fontWeight: 900, opacity: .7 }}>Loading…</span> : null}
                </div>

                {error ? (
                  <div style={{ marginTop: 8, background: '#fef2f2', border: '1px solid #fecaca', color: '#991b1b', padding: 10, borderRadius: 12, fontWeight: 900 }}>
                    {error}
                  </div>
                ) : null}

                {!loadingSlots && date && serviceId && agentId && slots.length === 0 ? (
                  <div style={{ marginTop: 8, opacity: .75, fontWeight: 900 }}>
                    No available time slots for this date.
                  </div>
                ) : null}

                <div style={{ marginTop: 10, display: 'grid', gridTemplateColumns: 'repeat(3, minmax(0, 1fr))', gap: 10 }}>
                  {slots.map((s) => {
                    const active = slot?.start_time === s.start_time;
                    return (
                      <button
                        key={s.start_time}
                        type="button"
                        onClick={() => setSlot(s)}
                        style={{
                          padding: '10px 10px',
                          borderRadius: 14,
                          border: active ? '2px solid #4318ff' : '1px solid #e5e7eb',
                          background: active ? 'rgba(67,24,255,.10)' : '#fff',
                          fontWeight: 950,
                          cursor: 'pointer',
                        }}
                      >
                        {s.label || s.start_time}
                      </button>
                    );
                  })}
                </div>
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
                  Selected: {slot ? slot.label || slot.start_time : '—'}
                </div>
                <button style={btn} onClick={nextStep}>Next: Your details</button>
              </div>
            </div>
          ) : null}

          {!loading && step === 4 ? (
            <div>
              <h3 style={h3}>Your details</h3>
              <div style={{ color: '#6b7280', fontWeight: 800, marginTop: -8, marginBottom: 10 }}>
                We’ll use this to confirm your booking.
              </div>

              {fieldsLoading ? (
                <div style={{fontWeight:900, opacity:.7}}>Loading fields…</div>
              ) : null}

              {detailsFields.map((f) => {
                const key = `${f.scope}.${f.field_key}`;
                const val = getField(key);
                return (
                  <div key={key} style={{ marginTop: 12 }}>
                    <label style={{ fontWeight: 950 }}>
                      {f.label} {f.is_required ? <span style={{color:'#ef4444'}}>*</span> : null}
                    </label>
                    <div style={{ marginTop: 6 }}>
                      <FieldInput f={f} value={val} onChange={(v)=>setField(key, v)} />
                    </div>
                  </div>
                );
              })}

              <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 14, alignItems: 'center', flexWrap: 'wrap', gap: 10 }}>
                <div style={{ fontWeight: 950 }}>
                  Selected: {date} {slot ? slot.label || slot.start_time : '—'}
                </div>
                <button style={btn} onClick={nextStep}>
                  Next: Summary
                </button>
              </div>
            </div>
          ) : null}

          {!loading && step === 5 ? (
            <div>
              <h3 style={h3}>Review & submit</h3>
              <div style={{ color: '#6b7280', fontWeight: 800, marginTop: -8, marginBottom: 10 }}>
                Please confirm your booking details.
              </div>

              <div className="bp-card" style={{ boxShadow: 'none' }}>
                <SummaryRow label="Category" value={selectedCategory?.name} />
                <SummaryRow label="Service" value={selectedService?.name} />
                <SummaryRow label="Agent" value={selectedAgent?.name} />
                <SummaryRow label="Date" value={date} />
                <SummaryRow label="Time" value={slot?.start_time} />
                <SummaryRow label="Price" value={formatMoney(totalPrice, bpSettings)} />

                <div style={{ height: 10 }} />
                <div style={{ fontWeight: 1000 }}>Your details</div>

                {detailsFields.map((f) => {
                  const key = `${f.scope}.${f.field_key}`;
                  let v = fieldValues[key];
                  if (f.type === 'checkbox') v = (v === '1') ? 'Yes' : 'No';
                  return <SummaryRow key={key} label={f.label} value={String(v ?? '')} />;
                })}
              </div>

              <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 14, alignItems: 'center', flexWrap: 'wrap', gap: 10 }}>
                <div style={{ fontWeight: 950 }}>Total: {totalPrice}</div>
                <button
                  className="bp-btn bp-btn-primary"
                  disabled={submitting}
                  onClick={submitBooking}
                >
                  {submitting ? 'Submitting…' : 'Submit Booking'}
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

function SummaryRow({ label, value }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, padding: '10px 0', borderBottom: '1px solid #f1f5f9' }}>
      <div style={{ fontWeight: 950, opacity: .7 }}>{label}</div>
      <div style={{ fontWeight: 950 }}>{value || '—'}</div>
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
const inputText = { padding: '10px 12px', borderRadius: 12, border: '1px solid #e5e7eb', fontWeight: 900, outline: 'none' };
const textarea = { padding: '10px 12px', borderRadius: 12, border: '1px solid #e5e7eb', fontWeight: 900, outline: 'none', resize: 'vertical' };

const err = { background: '#fef2f2', border: '1px solid #fecaca', color: '#991b1b', padding: 10, borderRadius: 12, fontWeight: 900, marginBottom: 10 };
