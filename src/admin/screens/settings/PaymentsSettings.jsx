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
    <div className="bp-grid bp-grid-2 bp-gap-14">
      {toast ? <div className="bp-toast bp-toast-success">{toast}</div> : null}
      <div className="bp-card bp-p-14">
        <div className="bp-font-900">Payment Methods</div>
        <div className="bp-text-sm bp-muted bp-mt-6">
          Enable the methods you want to show in the wizard.
        </div>

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
          <div className="bp-pay-save">
            <button
              type="button"
              className="bp-btn bp-btn-primary"
              onClick={save}
              disabled={saving}
            >
              {saving ? 'Saving...' : 'Save'}
            </button>
          </div>
        </div>

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
                      onError={(e) => { e.currentTarget.style.display = 'none'; }}
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
            <div className="bp-text-sm bp-muted">
              For Stripe / PayPal / WooCommerce, booking waits for payment.
            </div>
          </div>
          <input
            type="checkbox"
            checked={!!state.require_payment_to_confirm}
            onChange={(e) =>
              setState({ ...state, require_payment_to_confirm: e.target.checked ? 1 : 0 })
            }
            disabled={!state.payments_enabled}
          />
        </div>

      </div>

      <div className="bp-card bp-p-14">
        <div className="bp-font-900">WooCommerce</div>
        <div className="bp-text-sm bp-muted bp-mt-6">
          Used only if WooCommerce is enabled.
        </div>

        <div className="bp-mt-12">
          <div className="bp-label">Product ID</div>
          <input
            className="bp-input-field"
            type="number"
            value={state.woocommerce?.product_id || 0}
            disabled={!state.payments_enabled}
            onChange={(e) =>
              setState({
                ...state,
                woocommerce: {
                  ...state.woocommerce,
                  product_id: Number(e.target.value || 0),
                },
              })
            }
            placeholder="e.g. 123"
          />
          <div className="bp-text-xs bp-muted bp-mt-6">
            This product is added to the cart when WooCommerce checkout runs.
          </div>
        </div>

      </div>

      <div className="bp-card bp-p-14">
        <div className="bp-card-head">
          <div className="bp-font-900">Stripe (Native)</div>
          <div className="bp-card-logo">
            <img
              src={`${imagesBase}/processor-stripe.png`}
              alt=""
              onError={(e) => { e.currentTarget.style.display = 'none'; }}
            />
          </div>
        </div>

        <div className="bp-mt-12">
          <div className="bp-label">Mode</div>
          <select
            className="bp-select"
            value={state.stripe?.mode || 'test'}
            disabled={!state.payments_enabled}
            onChange={(e) =>
              setState({ ...state, stripe: { ...state.stripe, mode: e.target.value } })
            }
          >
            <option value="test">Test</option>
            <option value="live">Live</option>
          </select>
        </div>

        <div className="bp-mt-12">
          <div className="bp-label">Test Secret Key</div>
          <input
            className="bp-input-field"
            value={state.stripe?.test_secret_key || ''}
            disabled={!state.payments_enabled}
            onChange={(e) =>
              setState({
                ...state,
                stripe: { ...state.stripe, test_secret_key: e.target.value },
              })
            }
            placeholder="sk_test_..."
          />
        </div>

        <div className="bp-mt-12">
          <div className="bp-label">Test Publishable Key</div>
          <input
            className="bp-input-field"
            value={state.stripe?.test_publishable_key || ''}
            disabled={!state.payments_enabled}
            onChange={(e) =>
              setState({
                ...state,
                stripe: { ...state.stripe, test_publishable_key: e.target.value },
              })
            }
            placeholder="pk_test_..."
          />
        </div>

        <div className="bp-mt-12">
          <div className="bp-label">Live Secret Key</div>
          <input
            className="bp-input-field"
            value={state.stripe?.live_secret_key || ''}
            disabled={!state.payments_enabled}
            onChange={(e) =>
              setState({
                ...state,
                stripe: { ...state.stripe, live_secret_key: e.target.value },
              })
            }
            placeholder="sk_live_..."
          />
        </div>

        <div className="bp-mt-12">
          <div className="bp-label">Live Publishable Key</div>
          <input
            className="bp-input-field"
            value={state.stripe?.live_publishable_key || ''}
            disabled={!state.payments_enabled}
            onChange={(e) =>
              setState({
                ...state,
                stripe: { ...state.stripe, live_publishable_key: e.target.value },
              })
            }
            placeholder="pk_live_..."
          />
        </div>

        <div className="bp-mt-12">
          <div className="bp-label">Webhook Secret</div>
          <input
            className="bp-input-field"
            value={state.stripe?.webhook_secret || ''}
            disabled={!state.payments_enabled}
            onChange={(e) =>
              setState({
                ...state,
                stripe: { ...state.stripe, webhook_secret: e.target.value },
              })
            }
            placeholder="whsec_..."
          />
          <div className="bp-text-xs bp-muted bp-mt-6">
            Webhook URL: <strong>{window.location.origin}/wp-json/bp/v1/webhooks/stripe</strong>
          </div>
        </div>

        <div className="bp-mt-12">
          <div className="bp-label">Success URL</div>
          <input
            className="bp-input-field"
            value={state.stripe?.success_url || ''}
            disabled={!state.payments_enabled}
            onChange={(e) =>
              setState({
                ...state,
                stripe: { ...state.stripe, success_url: e.target.value },
              })
            }
            placeholder="https://yoursite.com/booking-success"
          />
        </div>

        <div className="bp-mt-12">
          <div className="bp-label">Cancel URL</div>
          <input
            className="bp-input-field"
            value={state.stripe?.cancel_url || ''}
            disabled={!state.payments_enabled}
            onChange={(e) =>
              setState({
                ...state,
                stripe: { ...state.stripe, cancel_url: e.target.value },
              })
            }
            placeholder="https://yoursite.com/booking-cancelled"
          />
        </div>
      </div>

      <div className="bp-card bp-p-14">
        <div className="bp-card-head">
          <div className="bp-font-900">PayPal (Native)</div>
          <div className="bp-card-logo">
            <img
              src={`${imagesBase}/processor-paypal.png`}
              alt=""
              onError={(e) => { e.currentTarget.style.display = 'none'; }}
            />
          </div>
        </div>

        <div className="bp-mt-12">
          <div className="bp-label">Mode</div>
          <select
            className="bp-select"
            value={state.paypal?.mode || 'test'}
            disabled={!state.payments_enabled}
            onChange={(e) =>
              setState({ ...state, paypal: { ...state.paypal, mode: e.target.value } })
            }
          >
            <option value="test">Sandbox</option>
            <option value="live">Live</option>
          </select>
        </div>

        <div className="bp-mt-12">
          <div className="bp-label">Client ID</div>
          <input
            className="bp-input-field"
            value={state.paypal?.client_id || ''}
            disabled={!state.payments_enabled}
            onChange={(e) =>
              setState({
                ...state,
                paypal: { ...state.paypal, client_id: e.target.value },
              })
            }
            placeholder="PayPal Client ID"
          />
        </div>

        <div className="bp-mt-12">
          <div className="bp-label">Secret</div>
          <input
            className="bp-input-field"
            value={state.paypal?.secret || ''}
            disabled={!state.payments_enabled}
            onChange={(e) =>
              setState({ ...state, paypal: { ...state.paypal, secret: e.target.value } })
            }
            placeholder="PayPal Secret"
          />
        </div>

        <div className="bp-mt-12">
          <div className="bp-label">Return URL</div>
          <input
            className="bp-input-field"
            value={state.paypal?.return_url || ''}
            disabled={!state.payments_enabled}
            onChange={(e) =>
              setState({
                ...state,
                paypal: { ...state.paypal, return_url: e.target.value },
              })
            }
            placeholder="https://yoursite.com/paypal-return"
          />
        </div>

        <div className="bp-mt-12">
          <div className="bp-label">Cancel URL</div>
          <input
            className="bp-input-field"
            value={state.paypal?.cancel_url || ''}
            disabled={!state.payments_enabled}
            onChange={(e) =>
              setState({
                ...state,
                paypal: { ...state.paypal, cancel_url: e.target.value },
              })
            }
            placeholder="https://yoursite.com/paypal-cancel"
          />
        </div>
      </div>
    </div>
  );
}
