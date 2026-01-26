import React, { useEffect, useMemo, useState } from "react";
import { loadStripe } from "@stripe/stripe-js";
import { Elements, PaymentElement, useElements, useStripe } from "@stripe/react-stripe-js";

function InnerStripePay({ bookingId, onPaid, onBack }) {
  const stripe = useStripe();
  const elements = useElements();
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");

  const pay = async () => {
    setErr("");
    if (!stripe || !elements) return;

    setLoading(true);
    try {
      const { error, paymentIntent } = await stripe.confirmPayment({
        elements,
        confirmParams: {
          return_url: window.location.href,
        },
        redirect: "if_required",
      });

      if (error) {
        setErr(error.message || "Payment failed.");
        setLoading(false);
        return;
      }

      const res = await fetch("/wp-json/bp/v1/front/payment/stripe/confirm", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": window.BP_FRONT?.nonce || "",
        },
        body: JSON.stringify({
          booking_id: bookingId,
          payment_intent_id: paymentIntent?.id || "",
          status: paymentIntent?.status || "",
        }),
      });

      const j = await res.json();
      if (j?.success) {
        onPaid();
        return;
      }

      setErr("Payment not completed yet. Please try again.");
    } catch (e) {
      setErr(e?.message || "Payment error.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bp-pay-wrap">
      <div className="bp-pay-box">
        <PaymentElement />
      </div>

      {err ? <div className="bp-alert bp-alert-error">{err}</div> : null}

      <div className="bp-pay-actions">
        <button type="button" className="bp-btn bp-btn-light" onClick={onBack} disabled={loading}>
          Back
        </button>
        <button type="button" className="bp-btn bp-btn-primary" onClick={pay} disabled={!stripe || loading}>
          {loading ? "Processing..." : "Pay now"}
        </button>
      </div>
    </div>
  );
}

export default function StepPayment({
  bookingId,
  totalLabel,
  paymentMethod,
  onPaid,
  onBack,
}) {
  const pk = window.BP_FRONT?.stripe_pk || "";
  const stripePromise = useMemo(() => (pk ? loadStripe(pk) : null), [pk]);

  const [clientSecret, setClientSecret] = useState("");
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");

  useEffect(() => {
    if (!bookingId) return;
    if (paymentMethod !== "stripe") return;

    let alive = true;
    setErr("");
    setLoading(true);

    fetch("/wp-json/bp/v1/front/payment/stripe/start", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": window.BP_FRONT?.nonce || "",
      },
      body: JSON.stringify({ booking_id: bookingId }),
    })
      .then((r) => r.json().then((j) => ({ ok: r.ok, j })))
      .then(({ ok, j }) => {
        if (!alive) return;
        if (!ok || !j?.success || !j?.client_secret) {
          throw new Error(j?.message || "Could not start Stripe payment.");
        }
        setClientSecret(j.client_secret);
      })
      .catch((e) => {
        if (!alive) return;
        setErr(e?.message || "Stripe start error.");
      })
      .finally(() => alive && setLoading(false));

    return () => {
      alive = false;
    };
  }, [bookingId, paymentMethod]);

  if (paymentMethod !== "stripe") {
    return (
      <div className="bp-step">
        <h3>Payment</h3>
        <div className="bp-muted">Selected method: {paymentMethod}</div>
        <div className="bp-pay-actions" style={{ marginTop: 12 }}>
          <button type="button" className="bp-btn bp-btn-light" onClick={onBack}>
            Back
          </button>
        </div>
      </div>
    );
  }

  if (!pk) {
    return (
      <div className="bp-step">
        <h3>Payment</h3>
        <div className="bp-alert bp-alert-error">
          Stripe publishable key is missing. Please configure it in BookPoint Settings.
        </div>
        <div className="bp-pay-actions">
          <button type="button" className="bp-btn bp-btn-light" onClick={onBack}>
            Back
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="bp-step">
      <div className="bp-step-head">
        <h3>Payment</h3>
        <div className="bp-total">{totalLabel}</div>
      </div>

      {err ? <div className="bp-alert bp-alert-error">{err}</div> : null}
      {loading && !clientSecret ? <div className="bp-muted">Preparing secure payment...</div> : null}

      {clientSecret && stripePromise ? (
        <Elements stripe={stripePromise} options={{ clientSecret }}>
          <InnerStripePay bookingId={bookingId} onPaid={onPaid} onBack={onBack} />
        </Elements>
      ) : null}
    </div>
  );
}
