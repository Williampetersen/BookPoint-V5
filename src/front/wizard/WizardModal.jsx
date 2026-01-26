import React, { useEffect, useMemo, useState } from 'react';
import {
  fetchLocations,
  fetchCategories,
  fetchServices,
  fetchExtras,
  fetchAgents,
  fetchFormFields,
  createBooking,
  startWooCheckout,
  startStripeCheckout,
  startPaypalCheckout,
  capturePaypal,
} from './api';
import useBookingFormDesign from '../hooks/useBookingFormDesign';
import StepLocation from './steps/StepLocation';
import StepCategory from './steps/StepCategory';
import StepService from './steps/StepService';
import StepExtras from './steps/StepExtras';
import StepAgent from './steps/StepAgent';
import StepDateTime from './steps/StepDateTime';
import StepCustomer from './steps/StepCustomer';
import PaymentStep from './steps/PaymentStep';
import StepReview from './steps/StepReview';
import StepDone from './steps/StepDone';
import useBpFrontSettings from '../hooks/useBpFrontSettings';

const DEFAULT_STEPS = [
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

function buildSteps(designConfig) {
  const raw = Array.isArray(designConfig?.steps) ? designConfig.steps : [];
  const enabled = raw.filter((s) => s && s.key && s.enabled !== false);
  const baseMap = new Map(DEFAULT_STEPS.map((s) => [s.key, s]));
  const normalizeKey = (k) => {
    if (k === 'agents') return 'agent';
    if (k === 'confirm' || k === 'confirmation') return 'done';
    return k;
  };

  const list = enabled.length
    ? enabled.map((s) => ({
        ...(baseMap.get(normalizeKey(s.key)) || { key: normalizeKey(s.key), title: s.title || s.key, icon: 'service-image.png' }),
        ...s,
        key: normalizeKey(s.key),
        title: s.title || baseMap.get(normalizeKey(s.key))?.title || s.key,
        subtitle: s.subtitle || '',
      }))
    : DEFAULT_STEPS.slice();

  const seen = new Set();
  return list.filter((s) => {
    if (seen.has(s.key)) return false;
    seen.add(s.key);
    return true;
  });
}

export default function WizardModal({ open, onClose, brand }) {
  const [stepIndex, setStepIndex] = useState(0);
  const { config: designConfig, loading: designLoading, error: designError } = useBookingFormDesign(open);
  const steps = useMemo(() => buildSteps(designConfig), [designConfig]);
  const { settings: bpSettings } = useBpFrontSettings(open);

  const [locationId, setLocationId] = useState(null);
  const [categoryIds, setCategoryIds] = useState([]);
  const [serviceId, setServiceId] = useState(null);
  const [extraIds, setExtraIds] = useState([]);
  const [agentId, setAgentId] = useState(null);
  const [date, setDate] = useState(null);
  const [slot, setSlot] = useState(null);
  const [paymentMethod, setPaymentMethod] = useState(null);
  const [returnHandled, setReturnHandled] = useState(false);

  const [formFields, setFormFields] = useState({ form: [], customer: [], booking: [] });
  const [answers, setAnswers] = useState({});

  const [locations, setLocations] = useState([]);
  const [categories, setCategories] = useState([]);
  const [services, setServices] = useState([]);
  const [extras, setExtras] = useState([]);
  const [agents, setAgents] = useState([]);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const step = steps[stepIndex] || steps[0] || DEFAULT_STEPS[0];
  const iconUrl = useMemo(() => {
    if (step?.imageUrl) return step.imageUrl;
    const file = step?.icon || step?.image || 'service-image.png';
    return brand?.imagesBase ? brand.imagesBase + file : '';
  }, [brand, step]);
  const helpTitle = designConfig?.texts?.helpTitle || 'Need help?';
  const helpPhone = designConfig?.texts?.helpPhone || designConfig?.layout?.helpPhone || brand?.helpPhone || '';
  const showLeft = step?.showLeftPanel !== false;
  const showHelp = step?.showHelpBox !== false;
  const paymentEnabledMethods = Array.isArray(bpSettings?.payments_enabled_methods) && bpSettings.payments_enabled_methods.length
    ? bpSettings.payments_enabled_methods
    : ['cash'];
  const totalAmount = useMemo(() => {
    const svc = services.find((x) => String(x.id) === String(serviceId));
    const svcPrice = svc?.price != null ? Number(svc.price) : 0;
    const selectedExtras = extras.filter((extra) => (
      extraIds.includes(extra.id) || extraIds.includes(String(extra.id))
    ));
    const extrasPrice = selectedExtras.reduce((sum, item) => (
      sum + (item?.price != null ? Number(item.price) : 0)
    ), 0);
    return svcPrice + extrasPrice;
  }, [services, serviceId, extras, extraIds]);

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
    setPaymentMethod(null);
    setReturnHandled(false);
    setAnswers({});
  }, [open]);

  useEffect(() => {
    if (!open) return;
    if (!paymentMethod && bpSettings?.payments_default_method) {
      setPaymentMethod(bpSettings.payments_default_method);
    }
  }, [open, bpSettings, paymentMethod]);

  useEffect(() => {
    if (!open || returnHandled) return;

    const params = new URLSearchParams(window.location.search);
    const bpPayment = params.get('bp_payment');
    const token = params.get('token');
    const bookingId = Number(params.get('booking_id') || 0);

    const doneIndex = steps.findIndex((s) => s.key === 'done');
    const goDone = (booking_id) => {
      setAnswers((a) => ({ ...a, __booking: { booking_id } }));
      setStepIndex(doneIndex >= 0 ? doneIndex : steps.length - 1);
    };

    const cleanUrl = () => {
      const url = new URL(window.location.href);
      url.searchParams.delete('bp_payment');
      url.searchParams.delete('token');
      url.searchParams.delete('booking_id');
      window.history.replaceState({}, document.title, url.toString());
    };

    if (bpPayment === 'paypal_cancel') {
      setReturnHandled(true);
      setError('PayPal payment cancelled');
      cleanUrl();
      return;
    }

    if (bpPayment === 'stripe_cancel') {
      setReturnHandled(true);
      setError('Stripe payment cancelled');
      cleanUrl();
      return;
    }

    if ((bpPayment === 'paypal_return' || bpPayment === 'paypal') && token && bookingId) {
      setReturnHandled(true);
      setLoading(true);
      capturePaypal({ order_id: token, booking_id: bookingId })
        .then(() => {
          goDone(bookingId);
          cleanUrl();
        })
        .catch((e) => setError(e?.message || 'PayPal capture failed'))
        .finally(() => setLoading(false));
      return;
    }

    if (bpPayment === 'stripe_success' && bookingId) {
      setReturnHandled(true);
      goDone(bookingId);
      cleanUrl();
    }
  }, [open, returnHandled, steps]);

  useEffect(() => {
    if (error === 'Select a payment method' && paymentMethod) {
      setError('');
    }
  }, [error, paymentMethod]);

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
    const keySig = steps.map((s) => s.key).join('|');
    if (!keySig) return;
    setStepIndex(0);
  }, [open, steps]);

  useEffect(() => {
    setStepIndex((i) => {
      if (i < 0) return 0;
      if (i >= steps.length) return Math.max(0, steps.length - 1);
      return i;
    });
  }, [steps.length]);

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
    if (step?.key === 'payment' && !paymentMethod) {
      setError('Select a payment method');
      return;
    }
    setStepIndex((i) => Math.min(i + 1, steps.length - 1));
  }

  function back() {
    setError('');
    setStepIndex((i) => Math.max(i - 1, 0));
  }

  async function submitBooking() {
    try {
      setLoading(true);
      setError('');

      const customer_fields = {};
      const booking_fields = {};
      Object.entries(answers || {}).forEach(([k, v]) => {
        if (k.startsWith('customer.')) {
          customer_fields[k.slice(9)] = v;
        } else if (k.startsWith('booking.')) {
          booking_fields[k.slice(8)] = v;
        }
      });

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
        customer_fields,
        booking_fields,
        extras: extraIds,
        total_price: totalAmount,
        currency: bpSettings?.currency || 'USD',
      };

      const method = paymentMethod || bpSettings?.payments_default_method || 'cash';
      payload.payment_method = method;

      if (method === 'woocommerce') {
        const res = await startWooCheckout(payload);
        if (!res?.checkout_url) {
          throw new Error('Checkout URL missing');
        }
        window.location.href = res.checkout_url;
        return;
      }

      if (method === 'stripe') {
        const res = await startStripeCheckout(payload);
        if (!res?.checkout_url) {
          throw new Error('Stripe checkout URL missing');
        }
        window.location.href = res.checkout_url;
        return;
      }

      if (method === 'paypal') {
        const res = await startPaypalCheckout(payload);
        if (!res?.approve_url) {
          throw new Error('PayPal approve URL missing');
        }
        window.location.href = res.approve_url;
        return;
      }

      if (method !== 'cash' && method !== 'free') {
        throw new Error('Unsupported payment method');
      }

      const res = await createBooking(payload);
      setAnswers((a) => ({ ...a, __booking: res }));
      const doneIdx = steps.findIndex((s) => s.key === 'done');
      setStepIndex(doneIdx >= 0 ? doneIdx : steps.length - 1);
    } catch (e) {
      setError(e?.message || 'Could not submit booking.');
    } finally {
      setLoading(false);
    }
  }

  if (!open) return null;
  if (designLoading && !designConfig) {
    return <div className="bp-wizard-loading">Loading booking form…</div>;
  }
  if (designError && !designConfig) {
    return <div className="bp-wizard-loading">Wizard error: {designError}</div>;
  }
  if (!steps.length) {
    return <div className="bp-wizard-loading">Wizard config invalid (no steps)</div>;
  }

  return (
    <div className="bp-modal-overlay" role="dialog" aria-modal="true">
      <div className="bp-modal">
        <button className="bp-modal-close" onClick={onClose} aria-label="Close">x</button>

        <div className="bp-modal-grid">
          {showLeft ? (
            <aside className="bp-side">
              <div className="bp-side-icon">
                {iconUrl ? <img src={iconUrl} alt="" /> : null}
              </div>
              <h3 className="bp-side-title">{step.title}</h3>
              <p className="bp-side-desc">
                {step.subtitle || 'Please complete the steps to schedule your booking.'}
              </p>

              {showHelp ? (
                <div className="bp-side-help">
                  <div>{helpTitle}</div>
                  <div className="bp-help-phone">{helpPhone}</div>
                </div>
              ) : null}
            </aside>
          ) : null}

          <main className="bp-main">
            <div className="bp-main-head">
              <h2>{step.title}</h2>
              <div className="bp-step-dots">
                {steps.slice(0, 8).map((s, idx) => (
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
                layout={designConfig?.fieldsLayout}
              />
            )}

            {step.key === 'payment' && (
              <PaymentStep
                enabledMethods={paymentEnabledMethods}
                selected={paymentMethod}
                onSelect={setPaymentMethod}
                totalLabel={formatMoney(totalAmount, bpSettings)}
                onBack={back}
                onNext={next}
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

function formatMoney(amount, settings = {}) {
  if (amount == null) return '-';
  const value = Number(amount);
  if (!Number.isFinite(value)) return '-';

  const currency = settings.currency || 'USD';
  const position = settings.currency_position === 'after' ? 'after' : 'before';
  const formatted = value.toLocaleString(undefined, {
    minimumFractionDigits: value % 1 === 0 ? 0 : 2,
    maximumFractionDigits: 2,
  });

  return position === 'after' ? `${formatted} ${currency}` : `${currency} ${formatted}`;
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
  const svcPrice = svc?.price != null ? Number(svc.price) : 0;
  const extrasPrice = ex.reduce((sum, item) => sum + (item?.price != null ? Number(item.price) : 0), 0);
  const totalPrice = svcPrice + extrasPrice;

  function formatPrice(val) {
    if (!val && val !== 0) return '-';
    return `${Number(val).toFixed(0)} Kr`;
  }

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
        <div className="k">Service Price</div>
        <div className="v">{svc?.price != null ? formatPrice(svcPrice) : '-'}</div>
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
      <div className="bp-summary-row">
        <div className="k">Extras Price</div>
        <div className="v">{ex.length ? formatPrice(extrasPrice) : '-'}</div>
      </div>
      <div className="bp-summary-row">
        <div className="k">Total</div>
        <div className="v">{svc?.price != null || ex.length ? formatPrice(totalPrice) : '-'}</div>
      </div>
    </div>
  );
}
