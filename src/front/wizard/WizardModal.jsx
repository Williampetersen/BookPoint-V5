import React, { useEffect, useMemo, useState } from 'react';
import {
  fetchLocations,
  fetchCategories,
  fetchServices,
  fetchExtras,
  fetchAgents,
  fetchFormFields,
  createBooking,
} from './api';
import StepLocation from './steps/StepLocation';
import StepCategory from './steps/StepCategory';
import StepService from './steps/StepService';
import StepExtras from './steps/StepExtras';
import StepAgent from './steps/StepAgent';
import StepDateTime from './steps/StepDateTime';
import StepCustomer from './steps/StepCustomer';
import StepReview from './steps/StepReview';
import StepDone from './steps/StepDone';

const STEPS = [
  { key: 'location', title: 'Location Selection', icon: 'location-image.png' },
  { key: 'category', title: 'Choose Category', icon: 'service-image.png' },
  { key: 'service', title: 'Choose Service', icon: 'service-image.png' },
  { key: 'extras', title: 'Service Extras', icon: 'service-image.png' },
  { key: 'agent', title: 'Choose Agent', icon: 'default-avatar.jpg' },
  { key: 'datetime', title: 'Choose Date & Time', icon: 'blue-dot.png' },
  { key: 'customer', title: 'Customer Information', icon: 'default-avatar.jpg' },
  { key: 'review', title: 'Review Order', icon: 'white-curve.png' },
  { key: 'done', title: 'Done', icon: 'logo.png' },
];

export default function WizardModal({ open, onClose, brand }) {
  const [stepIndex, setStepIndex] = useState(0);

  const [locationId, setLocationId] = useState(null);
  const [categoryIds, setCategoryIds] = useState([]);
  const [serviceId, setServiceId] = useState(null);
  const [extraIds, setExtraIds] = useState([]);
  const [agentId, setAgentId] = useState(null);
  const [date, setDate] = useState(null);
  const [slot, setSlot] = useState(null);

  const [formFields, setFormFields] = useState({ form: [], customer: [], booking: [] });
  const [answers, setAnswers] = useState({});

  const [locations, setLocations] = useState([]);
  const [categories, setCategories] = useState([]);
  const [services, setServices] = useState([]);
  const [extras, setExtras] = useState([]);
  const [agents, setAgents] = useState([]);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const step = STEPS[stepIndex];
  const iconUrl = useMemo(() => (brand?.imagesBase ? brand.imagesBase + step.icon : ''), [brand, step]);

  useEffect(() => {
    if (!open) return;
    document.body.classList.add('bp-modal-open');
    return () => document.body.classList.remove('bp-modal-open');
  }, [open]);

  useEffect(() => {
    if (!open) return;
    setStepIndex(0);
    setError('');
    setLocationId(null);
    setCategoryIds([]);
    setServiceId(null);
    setExtraIds([]);
    setAgentId(null);
    setDate(null);
    setSlot(null);
    setAnswers({});
  }, [open]);

  useEffect(() => {
    if (!open) return;
    (async () => {
      try {
        setLoading(true);
        const [locs, cats, fields] = await Promise.all([
          fetchLocations(),
          fetchCategories(),
          fetchFormFields(),
        ]);
        setLocations(locs);
        setCategories(cats);
        setFormFields(fields);
      } catch (e) {
        setError(e?.message || 'Failed to load booking data.');
      } finally {
        setLoading(false);
      }
    })();
  }, [open]);

  useEffect(() => {
    if (!open) return;
    (async () => {
      try {
        if (step.key === 'service' || step.key === 'extras' || step.key === 'agent' || step.key === 'datetime' || step.key === 'review') {
          const svc = await fetchServices({ category_ids: categoryIds, location_id: locationId });
          setServices(svc);
        }
      } catch (e) {
        setServices([]);
      }
    })();
  }, [open, step.key, categoryIds, locationId]);

  useEffect(() => {
    if (!open) return;
    (async () => {
      try {
        if (!serviceId) return;
        const [ex, ag] = await Promise.all([
          fetchExtras({ service_id: serviceId }),
          fetchAgents({ service_id: serviceId, location_id: locationId }),
        ]);
        setExtras(ex);
        setAgents(ag);
      } catch (e) {
        setExtras([]);
        setAgents([]);
      }
    })();
  }, [open, serviceId, locationId]);


  function next() {
    setError('');
    setStepIndex((i) => Math.min(i + 1, STEPS.length - 1));
  }

  function back() {
    setError('');
    setStepIndex((i) => Math.max(i - 1, 0));
  }

  async function submitBooking() {
    try {
      setLoading(true);
      setError('');

      const payload = {
        location_id: locationId,
        category_ids: categoryIds,
        service_id: serviceId,
        extra_ids: extraIds,
        agent_id: agentId,
        date,
        start_time: slot?.start_time || slot?.start || '',
        end_time: slot?.end_time || slot?.end || '',
        field_values: answers,
        extras: extraIds,
      };

      const res = await createBooking(payload);
      setAnswers((a) => ({ ...a, __booking: res }));
      setStepIndex(STEPS.findIndex((s) => s.key === 'done'));
    } catch (e) {
      setError(e?.message || 'Could not submit booking.');
    } finally {
      setLoading(false);
    }
  }

  if (!open) return null;

  return (
    <div className="bp-modal-overlay" role="dialog" aria-modal="true">
      <div className="bp-modal">
        <button className="bp-modal-close" onClick={onClose} aria-label="Close">x</button>

        <div className="bp-modal-grid">
          <aside className="bp-side">
            <div className="bp-side-icon">
              {iconUrl ? <img src={iconUrl} alt="" /> : null}
            </div>
            <h3 className="bp-side-title">{step.title}</h3>
            <p className="bp-side-desc">
              Please complete the steps to schedule your booking.
            </p>

            <div className="bp-side-help">
              <div>Need help?</div>
              <div className="bp-help-phone">{brand?.helpPhone}</div>
            </div>
          </aside>

          <main className="bp-main">
            <div className="bp-main-head">
              <h2>{step.title}</h2>
              <div className="bp-step-dots">
                {STEPS.slice(0, 8).map((s, idx) => (
                  <span key={s.key} className={idx === stepIndex ? 'bp-dot active' : 'bp-dot'} />
                ))}
              </div>
            </div>

            {error ? <div className="bp-error">{error}</div> : null}
            {loading ? <div className="bp-loading">Loading...</div> : null}

            {step.key === 'location' && (
              <StepLocation
                locations={locations}
                value={locationId}
                onChange={setLocationId}
                onNext={next}
              />
            )}

            {step.key === 'category' && (
              <StepCategory
                categories={categories}
                value={categoryIds}
                onChange={setCategoryIds}
                onBack={back}
                onNext={next}
              />
            )}

            {step.key === 'service' && (
              <StepService
                services={services}
                value={serviceId}
                onChange={setServiceId}
                onBack={back}
                onNext={next}
              />
            )}

            {step.key === 'extras' && (
              <StepExtras
                extras={extras}
                value={extraIds}
                onChange={setExtraIds}
                onBack={back}
                onNext={next}
              />
            )}

            {step.key === 'agent' && (
              <StepAgent
                agents={agents}
                value={agentId}
                onChange={setAgentId}
                onBack={back}
                onNext={next}
              />
            )}

            {step.key === 'datetime' && (
              <StepDateTime
                locationId={locationId}
                serviceId={serviceId}
                agentId={agentId}
                serviceDurationMin={services.find((s) => String(s.id) === String(serviceId))?.duration || 30}
                valueDate={date}
                valueSlot={slot}
                onChangeDate={(v) => {
                  setDate(v);
                  setSlot(null);
                }}
                onChangeSlot={setSlot}
                onBack={back}
                onNext={next}
              />
            )}

            {step.key === 'customer' && (
              <StepCustomer
                formFields={formFields}
                answers={answers}
                onChange={setAnswers}
                onBack={back}
                onNext={next}
                onError={setError}
              />
            )}

            {step.key === 'review' && (
              <StepReview
                locationId={locationId}
                categoryIds={categoryIds}
                serviceId={serviceId}
                extraIds={extraIds}
                agentId={agentId}
                date={date}
                slot={slot}
                locations={locations}
                categories={categories}
                services={services}
                extras={extras}
                agents={agents}
                formFields={formFields}
                answers={answers}
                onBack={back}
                onSubmit={submitBooking}
                loading={loading}
              />
            )}

            {step.key === 'done' && (
              <StepDone
                booking={answers.__booking}
                onClose={onClose}
              />
            )}
          </main>

          <aside className="bp-summary">
            <div className="bp-summary-title">Summary</div>
            <div className="bp-summary-box">
              <SummaryBlock
                locations={locations}
                categories={categories}
                services={services}
                extras={extras}
                agents={agents}
                locationId={locationId}
                categoryIds={categoryIds}
                serviceId={serviceId}
                extraIds={extraIds}
                agentId={agentId}
                date={date}
                slot={slot}
              />
            </div>
          </aside>
        </div>
      </div>
    </div>
  );
}

function SummaryBlock(props) {
  const {
    locations, categories, services, extras, agents,
    locationId, categoryIds, serviceId, extraIds, agentId, date, slot,
  } = props;

  const loc = locations.find((x) => String(x.id) === String(locationId));
  const svc = services.find((x) => String(x.id) === String(serviceId));
  const ag = agents.find((x) => String(x.id) === String(agentId));
  const ex = extras.filter((x) => extraIds.includes(x.id) || extraIds.includes(String(x.id)));
  const cats = categories.filter((x) => categoryIds.includes(x.id) || categoryIds.includes(String(x.id)));

  return (
    <div className="bp-summary-items">
      <div className="bp-summary-row">
        <div className="k">Location</div>
        <div className="v">{loc?.name || '-'}</div>
      </div>
      <div className="bp-summary-row">
        <div className="k">Category</div>
        <div className="v">{cats.length ? cats.map((c) => c.name).join(', ') : '-'}</div>
      </div>
      <div className="bp-summary-row">
        <div className="k">Service</div>
        <div className="v">{svc?.name || '-'}</div>
      </div>
      <div className="bp-summary-row">
        <div className="k">Agent</div>
        <div className="v">{ag?.name || '-'}</div>
      </div>
      <div className="bp-summary-row">
        <div className="k">Date</div>
        <div className="v">{date || '-'}</div>
      </div>
      <div className="bp-summary-row">
        <div className="k">Time</div>
        <div className="v">{slot?.start_time ? `${slot.start_time} - ${slot.end_time}` : '-'}</div>
      </div>
      <div className="bp-summary-row">
        <div className="k">Extras</div>
        <div className="v">{ex.length ? ex.map((e) => e.name).join(', ') : '-'}</div>
      </div>
    </div>
  );
}
