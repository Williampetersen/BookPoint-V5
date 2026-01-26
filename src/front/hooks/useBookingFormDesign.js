import { useEffect, useState } from 'react';

export default function useBookingFormDesign(open) {
  const [loading, setLoading] = useState(false);
  const [config, setConfig] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!open) return;

    let alive = true;
    setLoading(true);
    setError('');

    const url = `/wp-json/bp/v1/front/booking-form-design?_t=${Date.now()}`;

    fetch(url, { credentials: 'same-origin' })
      .then(async (r) => {
        const j = await r.json().catch(() => null);
        if (!r.ok) {
          throw new Error(j?.message || `Design fetch failed (${r.status})`);
        }
        return j;
      })
      .then((j) => {
        if (!alive) return;
        setConfig(j?.config || null);
      })
      .catch((e) => {
        if (!alive) return;
        setConfig(null);
        setError(e.message || 'Design fetch failed');
      })
      .finally(() => {
        if (!alive) return;
        setLoading(false);
      });

    return () => {
      alive = false;
    };
  }, [open]);

  return { config, loading, error };
}
