import React, { useMemo, useState, useEffect } from "react";
import { loadStripe } from "@stripe/stripe-js";
import { Elements, PaymentElement, useElements, useStripe } from "@stripe/react-stripe-js";

function InnerPay({ bookingId, onPaid, onError }) {
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
        setErr(error.message || "Payment failed");
        onError?.(error.message || "Payment failed");
        return;
      }

      const res = await fetch("/wp-json/bp/v1/front/payment/stripe/confirm", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({
          booking_id: bookingId,
          payment_intent_id: paymentIntent?.id,
          status: paymentIntent?.status,
        }),
      });
      const j = await res.json();
      if (j?.success) onPaid?.();
      else setErr("Payment not completed yet. Please try again.");
    } catch (e) {
      const msg = e?.message || "Payment error";
      setErr(msg);
      onError?.(msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bp-pay-card">
      <div className="bp-pay-box">
        <PaymentElement />
      </div>
      {err ? <div className="bp-error">{err}</div> : null}
      <button className="bp-btn bp-btn-primary" onClick={pay} disabled={!stripe || loading}>
        {loading ? "Processing..." : "Pay now"}
      </button>
    </div>
  );
}

export default function PaymentStepStripe({
  bookingId,
  publishableKey,
  amountLabel,
  onPaid,
  onError,
}) {
  const stripePromise = useMemo(() => loadStripe(publishableKey), [publishableKey]);
  const [clientSecret, setClientSecret] = useState("");
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");

  const start = async () => {
    setErr("");
    setLoading(true);
    try {
      const res = await fetch("/wp-json/bp/v1/front/payment/stripe/start", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ booking_id: bookingId }),
      });
      const j = await res.json();
      if (!j?.success || !j?.client_secret) {
        throw new Error(j?.message || "Could not start payment.");
      }
      setClientSecret(j.client_secret);
    } catch (e) {
      setErr(e?.message || "Stripe start error");
      onError?.(e?.message || "Stripe start error");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!bookingId || clientSecret) return;
    start();
  }, [bookingId, clientSecret]);

  if (!publishableKey) {
    return <div className="bp-error">Stripe publishable key missing.</div>;
  }

  return (
    <div className="bp-payment-step">
      <div className="bp-h-row">
        <h3>Payment</h3>
        <div className="bp-amount">{amountLabel}</div>
      </div>

      {err ? <div className="bp-error">{err}</div> : null}
      {loading && !clientSecret ? <div className="bp-muted">Preparing secure payment...</div> : null}

      {clientSecret ? (
        <Elements stripe={stripePromise} options={{ clientSecret }}>
          <InnerPay bookingId={bookingId} onPaid={onPaid} onError={onError} />
        </Elements>
      ) : null}
    </div>
  );
}
