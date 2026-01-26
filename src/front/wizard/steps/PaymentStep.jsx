import React from 'react';

const LABELS = {
  free: 'Free (no payment)',
  cash: 'Pay at location (Cash)',
  woocommerce: 'Pay with WooCommerce',
  stripe: 'Pay with Stripe',
  paypal: 'Pay with PayPal',
};

const NOTES = {
  cash: 'Pay when you arrive.',
  free: 'No payment required.',
  woocommerce: 'Card / PayPal / gateways via WooCommerce.',
  stripe: 'Secure card payment via Stripe.',
  paypal: 'Pay using PayPal account or card.',
};

export default function PaymentStep({
  enabledMethods = ['cash'],
  selected,
  onSelect,
  totalLabel = '-',
  onBack,
  onNext,
  backLabel = '<- Back',
  nextLabel = 'Next ->',
  loading = false,
}) {
  const methods = Array.isArray(enabledMethods) && enabledMethods.length
    ? enabledMethods
    : ['cash'];

  return (
    <div className="bp-step">
      <div className="bp-card-lite bp-p-14">
        <div className="bp-font-900">Payment Method</div>
        <div className="bp-text-sm bp-muted bp-mt-6">
          Total: <strong>{totalLabel}</strong>
        </div>

        <div className="bp-mt-12 bp-pay-grid">
          {methods.map((m) => (
            <button
              key={m}
              type="button"
              className={`bp-pay-option ${selected === m ? 'is-active' : ''}`}
              onClick={() => onSelect?.(m)}
            >
              <div className="bp-font-800">{LABELS[m] || m}</div>
              <div className="bp-text-xs bp-muted">
                {NOTES[m] || ''}
              </div>
            </button>
          ))}
        </div>
      </div>

      <div className="bp-step-footer">
        <button type="button" className="bp-back" onClick={onBack}>{backLabel}</button>
        <button type="button" className="bp-next" onClick={onNext} disabled={loading}>
          {nextLabel}
        </button>
      </div>
    </div>
  );
}
