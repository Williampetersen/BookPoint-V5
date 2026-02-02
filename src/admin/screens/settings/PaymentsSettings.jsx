import React, { useEffect, useState } from 'react';

const METHODS = [
  { key: 'free', label: 'Free (No payment)' },
  { key: 'cash', label: 'Cash / Pay at location' },
  { key: 'woocommerce', label: 'WooCommerce' },
  { key: 'stripe', label: 'Stripe (Native)' },
  { key: 'paypal', label: 'PayPal (Native)' },
];

const METHOD_NOTES = {
  free: 'No payment required.',
  cash: 'Pay when you arrive.',
  woocommerce: 'Card / PayPal / gateways via WooCommerce.',
  stripe: 'Secure card payment via Stripe.',
  paypal: 'Pay using PayPal account or card.',
};

const METHOD_ICONS = {
  free: 'payment_later.png',
  cash: 'payment_type_cash.png',
  woocommerce: 'payment_type_cards.png',
  stripe: 'processor-stripe.png',
  paypal: 'processor-paypal.png',
};

function getPublicImagesBase() {
  const base =
    window.BP_ADMIN?.publicImagesUrl ||
    window.bpAdmin?.publicImagesUrl ||
    (window.BP_ADMIN?.pluginUrl ? window.BP_ADMIN.pluginUrl.replace(/\/$/, '') + '/public/images' : '') ||
    (window.bpAdmin?.pluginUrl ? window.bpAdmin.pluginUrl.replace(/\/$/, '') + '/public/images' : '') ||
    `${window.location.origin}/wp-content/plugins/bookpoint-v5/public/images`;
  return base.replace(/\/$/, '');
}

function getRestBase() {
  const url = window.bpAdmin?.restUrl || window.BP_ADMIN?.restUrl || '/wp-json/bp/v1';
  return url.replace(/\/$/, '');
}

function getNonce() {
  return window.bpAdmin?.nonce || window.BP_ADMIN?.nonce || '';
}

async function api(path, opts = {}) {
  const base = getRestBase();
  const res = await fetch(`${base}${path}`, {
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': getNonce(),
      ...(opts.headers || {}),
    },
    ...opts,
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok || json?.status === 'error') {
    throw new Error(json?.message || 'Request failed');
  }
  return json;
}

export default function PaymentsSettings() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState('');
  const imagesBase = getPublicImagesBase();
  const [state, setState] = useState({
    payments_enabled: 1,
    enabled_methods: ['cash', 'free'],
    default_method: 'cash',
    require_payment_to_confirm: 1,
    woocommerce: { product_id: 0 },
    stripe: {
      enabled: 0,
      mode: 'test',
      test_secret_key: '',
      test_publishable_key: '',
      live_secret_key: '',
      live_publishable_key: '',
      webhook_secret: '',
      success_url: '',
      cancel_url: '',
    },
    paypal: {
      enabled: 0,
      mode: 'test',
      client_id: '',
      secret: '',
      return_url: '',
      cancel_url: '',
    },
  });

  useEffect(() => {
    let alive = true;
    api('/admin/settings/payments')
      .then((data) => {
        if (!alive) return;
        setState((prev) => ({
          ...prev,
          ...(data?.payments || {}),
        }));
      })
      .catch(() => {
        if (!alive) return;
      })
      .finally(() => {
        if (!alive) return;
        setLoading(false);
      });
    return () => {
      alive = false;
    };
  }, []);

  const toggleMethod = (key) => {
    const current = state.enabled_methods || [];
    const exists = current.includes(key);
    const enabled = exists ? current.filter((m) => m !== key) : [...current, key];
    const filtered = enabled.length ? enabled : ['cash'];
    const defaultMethod = filtered.includes(state.default_method)
      ? state.default_method
      : filtered[0];
    setState({
      ...state,
      enabled_methods: filtered,
      default_method: defaultMethod || 'cash',
    });
  };

  const paymentsOn = !!state.payments_enabled;
  const enabledMethods = Array.isArray(state.enabled_methods) ? state.enabled_methods : [];
  const methodOn = (k) => paymentsOn && enabledMethods.includes(k);

  const stripeMode = (state.stripe?.mode || 'test') === 'live' ? 'live' : 'test';
  const paypalMode = (state.paypal?.mode || 'test') === 'live' ? 'live' : 'test';

  const stripeNeeds =
    methodOn('stripe')
      ? stripeMode === 'test'
        ? {
            secret: !(state.stripe?.test_secret_key || '').trim(),
            pub: !(state.stripe?.test_publishable_key || '').trim(),
          }
        : {
            secret: !(state.stripe?.live_secret_key || '').trim(),
            pub: !(state.stripe?.live_publishable_key || '').trim(),
          }
      : { secret: false, pub: false };

  const paypalNeeds =
    methodOn('paypal')
      ? {
          client: !(state.paypal?.client_id || '').trim(),
          secret: !(state.paypal?.secret || '').trim(),
        }
      : { client: false, secret: false };

  const hasConfigErrors =
    stripeNeeds.secret ||
    stripeNeeds.pub ||
    paypalNeeds.client ||
    paypalNeeds.secret;

  const save = async () => {
    setSaving(true);
    try {
      const response = await api('/admin/settings/payments', {
        method: 'POST',
        body: JSON.stringify({ payments: state }),
      });
      if (response?.payments) {
        setState((prev) => ({ ...prev, ...response.payments }));
      }
      setToast('All settings saved');
      setTimeout(() => setToast(''), 2500);
    } catch (e) {
      setToast(e.message || 'Save failed');
      setTimeout(() => setToast(''), 2500);
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <div className="bp-card bp-p-14">Loading…</div>;

  return (
    <div className="bp-payments">
      {toast ? <div className="bp-toast bp-toast-success">{toast}</div> : null}

      <div className="bp-payments__grid">
        <div className="bp-card bp-p-14 bp-payments__overview">
          <div className="bp-font-900">Payments</div>
          <div className="bp-text-sm bp-muted bp-mt-6">Enable payments and choose which methods are available.</div>

          <div className="bp-pay-header bp-mt-12">
            <div className="bp-font-800">Payments active</div>
            <label className="bp-switch">
              <input
                type="checkbox"
                checked={!!state.payments_enabled}
                onChange={(e) => setState({ ...state, payments_enabled: e.target.checked ? 1 : 0 })}
              />
              <span className="bp-slider" />
            </label>
          </div>

          <div className="bp-mt-12">
            <div className="bp-label">Default method</div>
            <select
              className="bp-select"
              value={state.default_method}
              onChange={(e) => setState({ ...state, default_method: e.target.value })}
              disabled={!state.payments_enabled}
            >
              {METHODS.map((method) => (
                <option key={method.key} value={method.key}>
                  {method.label}
                </option>
              ))}
            </select>
          </div>

          <div className="bp-mt-12 bp-flex bp-items-center bp-justify-between">
            <div>
              <div className="bp-font-800">Require payment to confirm</div>
              <div className="bp-text-sm bp-muted">For Stripe / PayPal / WooCommerce, booking waits for payment.</div>
            </div>
            <input
              type="checkbox"
              checked={!!state.require_payment_to_confirm}
              onChange={(e) => setState({ ...state, require_payment_to_confirm: e.target.checked ? 1 : 0 })}
              disabled={!state.payments_enabled}
            />
          </div>

          {paymentsOn && enabledMethods.length === 0 ? (
            <div className="bp-alert bp-alert-error bp-mt-12">Enable at least one payment method.</div>
          ) : null}
          {paymentsOn && hasConfigErrors ? (
            <div className="bp-alert bp-alert-error bp-mt-12">
              Some enabled providers are missing required keys (see highlighted fields).
            </div>
          ) : null}
        </div>

        <div className="bp-card bp-p-14 bp-payments__methods">
          <div className="bp-font-900">Payment methods</div>
          <div className="bp-text-sm bp-muted bp-mt-6">Shown in the booking wizard.</div>
          <div className="bp-mt-12">
            {METHODS.map((method) => (
              <label key={method.key} className="bp-row">
                <input
                  type="checkbox"
                  checked={(state.enabled_methods || []).includes(method.key)}
                  onChange={() => toggleMethod(method.key)}
                  disabled={!state.payments_enabled}
                />
                <span className="bp-pay-row">
                  <span className="bp-pay-icon">
                    {METHOD_ICONS[method.key] ? (
                      <img
                        src={`${imagesBase}/${METHOD_ICONS[method.key]}`}
                        alt=""
                        onError={(e) => {
                          e.currentTarget.style.display = 'none';
                        }}
                      />
                    ) : null}
                  </span>
                  <span className="bp-pay-text">
                    <span className="bp-font-700">{method.label}</span>
                    <span className="bp-text-xs bp-muted">{METHOD_NOTES[method.key] || ''}</span>
                  </span>
                </span>
              </label>
            ))}
          </div>
        </div>

        <div className="bp-card bp-p-14 bp-payments__providers">
          <div className="bp-font-900">Provider setup</div>
          <div className="bp-text-sm bp-muted bp-mt-6">Configure only the providers you enabled.</div>

          {!paymentsOn ? (
            <div className="bp-muted bp-mt-12">Turn on Payments to configure providers.</div>
          ) : null}

          {methodOn('woocommerce') ? (
            <details className="bp-acc bp-mt-12" open>
              <summary className="bp-acc__sum">WooCommerce</summary>
              <div className="bp-acc__body">
                <div className="bp-label">Product ID</div>
                <input
                  className="bp-input-field"
                  type="number"
                  value={state.woocommerce?.product_id || 0}
                  disabled={!paymentsOn}
                  onChange={(e) =>
                    setState({
                      ...state,
                      woocommerce: { ...state.woocommerce, product_id: Number(e.target.value || 0) },
                    })
                  }
                  placeholder="e.g. 123"
                />
                <div className="bp-text-xs bp-muted bp-mt-6">
                  This product is added to the cart when WooCommerce checkout runs.
                </div>
              </div>
            </details>
          ) : null}

          {methodOn('stripe') ? (
            <details className="bp-acc bp-mt-12" open>
              <summary className="bp-acc__sum">Stripe (Native)</summary>
              <div className="bp-acc__body">
                <div className="bp-label">Mode</div>
                <div className="bp-pay-seg bp-mt-10">
                  <button
                    type="button"
                    className={`bp-pay-segbtn ${stripeMode === 'test' ? 'is-active' : ''}`}
                    onClick={() => setState({ ...state, stripe: { ...state.stripe, mode: 'test' } })}
                    disabled={!paymentsOn}
                  >
                    Test
                  </button>
                  <button
                    type="button"
                    className={`bp-pay-segbtn ${stripeMode === 'live' ? 'is-active' : ''}`}
                    onClick={() => setState({ ...state, stripe: { ...state.stripe, mode: 'live' } })}
                    disabled={!paymentsOn}
                  >
                    Live
                  </button>
                </div>

                {stripeMode === 'test' ? (
                  <div className="bp-grid bp-grid-2 bp-gap-10 bp-mt-12">
                    <div>
                      <div className="bp-label">Test Secret Key</div>
                      <input
                        className={`bp-input-field ${stripeNeeds.secret ? 'bp-field-error' : ''}`}
                        value={state.stripe?.test_secret_key || ''}
                        disabled={!paymentsOn}
                        onChange={(e) =>
                          setState({ ...state, stripe: { ...state.stripe, test_secret_key: e.target.value } })
                        }
                        placeholder="sk_test_..."
                      />
                    </div>
                    <div>
                      <div className="bp-label">Test Publishable Key</div>
                      <input
                        className={`bp-input-field ${stripeNeeds.pub ? 'bp-field-error' : ''}`}
                        value={state.stripe?.test_publishable_key || ''}
                        disabled={!paymentsOn}
                        onChange={(e) =>
                          setState({ ...state, stripe: { ...state.stripe, test_publishable_key: e.target.value } })
                        }
                        placeholder="pk_test_..."
                      />
                    </div>
                  </div>
                ) : (
                  <div className="bp-grid bp-grid-2 bp-gap-10 bp-mt-12">
                    <div>
                      <div className="bp-label">Live Secret Key</div>
                      <input
                        className={`bp-input-field ${stripeNeeds.secret ? 'bp-field-error' : ''}`}
                        value={state.stripe?.live_secret_key || ''}
                        disabled={!paymentsOn}
                        onChange={(e) =>
                          setState({ ...state, stripe: { ...state.stripe, live_secret_key: e.target.value } })
                        }
                        placeholder="sk_live_..."
                      />
                    </div>
                    <div>
                      <div className="bp-label">Live Publishable Key</div>
                      <input
                        className={`bp-input-field ${stripeNeeds.pub ? 'bp-field-error' : ''}`}
                        value={state.stripe?.live_publishable_key || ''}
                        disabled={!paymentsOn}
                        onChange={(e) =>
                          setState({ ...state, stripe: { ...state.stripe, live_publishable_key: e.target.value } })
                        }
                        placeholder="pk_live_..."
                      />
                    </div>
                  </div>
                )}

                <details className="bp-acc bp-mt-12">
                  <summary className="bp-acc__sum">Advanced</summary>
                  <div className="bp-acc__body">
                    <div className="bp-label">Webhook Secret</div>
                    <input
                      className="bp-input-field"
                      value={state.stripe?.webhook_secret || ''}
                      disabled={!paymentsOn}
                      onChange={(e) =>
                        setState({ ...state, stripe: { ...state.stripe, webhook_secret: e.target.value } })
                      }
                      placeholder="whsec_..."
                    />
                    <div className="bp-text-xs bp-muted bp-mt-6">
                      Webhook URL: <strong>{window.location.origin}/wp-json/bp/v1/webhooks/stripe</strong>
                    </div>

                    <div className="bp-mt-12">
                      <div className="bp-label">Success URL</div>
                      <input
                        className="bp-input-field"
                        value={state.stripe?.success_url || ''}
                        disabled={!paymentsOn}
                        onChange={(e) =>
                          setState({ ...state, stripe: { ...state.stripe, success_url: e.target.value } })
                        }
                        placeholder="https://yoursite.com/booking-success"
                      />
                    </div>

                    <div className="bp-mt-12">
                      <div className="bp-label">Cancel URL</div>
                      <input
                        className="bp-input-field"
                        value={state.stripe?.cancel_url || ''}
                        disabled={!paymentsOn}
                        onChange={(e) =>
                          setState({ ...state, stripe: { ...state.stripe, cancel_url: e.target.value } })
                        }
                        placeholder="https://yoursite.com/booking-cancelled"
                      />
                    </div>
                  </div>
                </details>
              </div>
            </details>
          ) : null}

          {methodOn('paypal') ? (
            <details className="bp-acc bp-mt-12" open>
              <summary className="bp-acc__sum">PayPal (Native)</summary>
              <div className="bp-acc__body">
                <div className="bp-label">Mode</div>
                <div className="bp-pay-seg bp-mt-10">
                  <button
                    type="button"
                    className={`bp-pay-segbtn ${paypalMode === 'test' ? 'is-active' : ''}`}
                    onClick={() => setState({ ...state, paypal: { ...state.paypal, mode: 'test' } })}
                    disabled={!paymentsOn}
                  >
                    Sandbox
                  </button>
                  <button
                    type="button"
                    className={`bp-pay-segbtn ${paypalMode === 'live' ? 'is-active' : ''}`}
                    onClick={() => setState({ ...state, paypal: { ...state.paypal, mode: 'live' } })}
                    disabled={!paymentsOn}
                  >
                    Live
                  </button>
                </div>

                <div className="bp-grid bp-grid-2 bp-gap-10 bp-mt-12">
                  <div>
                    <div className="bp-label">Client ID</div>
                    <input
                      className={`bp-input-field ${paypalNeeds.client ? 'bp-field-error' : ''}`}
                      value={state.paypal?.client_id || ''}
                      disabled={!paymentsOn}
                      onChange={(e) => setState({ ...state, paypal: { ...state.paypal, client_id: e.target.value } })}
                      placeholder="PayPal Client ID"
                    />
                  </div>
                  <div>
                    <div className="bp-label">Secret</div>
                    <input
                      className={`bp-input-field ${paypalNeeds.secret ? 'bp-field-error' : ''}`}
                      value={state.paypal?.secret || ''}
                      disabled={!paymentsOn}
                      onChange={(e) => setState({ ...state, paypal: { ...state.paypal, secret: e.target.value } })}
                      placeholder="PayPal Secret"
                    />
                  </div>
                </div>

                <details className="bp-acc bp-mt-12">
                  <summary className="bp-acc__sum">Advanced</summary>
                  <div className="bp-acc__body">
                    <div className="bp-label">Return URL</div>
                    <input
                      className="bp-input-field"
                      value={state.paypal?.return_url || ''}
                      disabled={!paymentsOn}
                      onChange={(e) => setState({ ...state, paypal: { ...state.paypal, return_url: e.target.value } })}
                      placeholder="https://yoursite.com/paypal-return"
                    />

                    <div className="bp-mt-12">
                      <div className="bp-label">Cancel URL</div>
                      <input
                        className="bp-input-field"
                        value={state.paypal?.cancel_url || ''}
                        disabled={!paymentsOn}
                        onChange={(e) => setState({ ...state, paypal: { ...state.paypal, cancel_url: e.target.value } })}
                        placeholder="https://yoursite.com/paypal-cancel"
                      />
                    </div>
                  </div>
                </details>
              </div>
            </details>
          ) : null}

          {paymentsOn && !methodOn('woocommerce') && !methodOn('stripe') && !methodOn('paypal') ? (
            <div className="bp-muted bp-mt-12">No provider setup required for the currently enabled methods.</div>
          ) : null}
        </div>
      </div>

      <div className="bp-payments-bar">
        <button
          type="button"
          className="bp-btn bp-btn-primary"
          onClick={save}
          disabled={saving || (paymentsOn && hasConfigErrors)}
        >
          {saving ? 'Saving...' : 'Save payments'}
        </button>
      </div>
    </div>
  );
}
