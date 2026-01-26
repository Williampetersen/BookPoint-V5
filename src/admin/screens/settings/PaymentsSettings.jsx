import React, { useEffect, useState } from 'react';

const METHODS = [
  { key: 'free', label: 'Free (No payment)' },
  { key: 'cash', label: 'Cash / Pay at location' },
  { key: 'woocommerce', label: 'WooCommerce' },
  { key: 'stripe', label: 'Stripe (Native)' },
  { key: 'paypal', label: 'PayPal (Native)' },
];

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
  const [state, setState] = useState({
    enabled_methods: ['cash', 'free'],
    default_method: 'cash',
    require_payment_to_confirm: 1,
    woocommerce: { product_id: 0 },
    stripe: { enabled: 0 },
    paypal: { enabled: 0 },
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
      window.alert('Payments saved');
    } catch (e) {
      window.alert(e.message);
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <div className="bp-card bp-p-14">Loading…</div>;

  return (
    <div className="bp-grid bp-grid-2 bp-gap-14">
      <div className="bp-card bp-p-14">
        <div className="bp-font-900">Payment Methods</div>
        <div className="bp-text-sm bp-muted bp-mt-6">
          Enable the methods you want to show in the wizard.
        </div>

        <div className="bp-mt-12">
          {METHODS.map((method) => (
            <label key={method.key} className="bp-row">
              <input
                type="checkbox"
                checked={(state.enabled_methods || []).includes(method.key)}
                onChange={() => toggleMethod(method.key)}
              />
              <span className="bp-font-700">{method.label}</span>
            </label>
          ))}
        </div>

        <div className="bp-mt-12">
          <div className="bp-label">Default method</div>
          <select
            className="bp-select"
            value={state.default_method}
            onChange={(e) => setState({ ...state, default_method: e.target.value })}
          >
            {(state.enabled_methods || []).map((methodKey) => (
              <option key={methodKey} value={methodKey}>
                {methodKey}
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
          />
        </div>

        <div className="bp-mt-14">
          <button
            type="button"
            className="bp-btn bp-btn-primary"
            onClick={save}
            disabled={saving}
          >
            {saving ? 'Saving…' : 'Save Payments'}
          </button>
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

        <div className="bp-mt-12 bp-text-sm bp-muted">
          Native Stripe / PayPal keys will be configured later.
        </div>
      </div>
    </div>
  );
}
