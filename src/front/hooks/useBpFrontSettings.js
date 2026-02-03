import { useEffect, useState } from 'react';

const clientSideSettings = typeof window !== 'undefined' ? window.BP_FRONT?.settings || null : null;
let cache = clientSideSettings;

export default function useBpFrontSettings(open) {
  const [settings, setSettings] = useState(cache);
  const [loading, setLoading] = useState(!cache && !!open);

  useEffect(() => {
    if (!open) return;
    if (cache) {
      setSettings(cache);
      return;
    }

    let alive = true;
    setLoading(true);

    const base = (typeof window !== 'undefined' ? window.BP_FRONT?.restUrl : '') || '/wp-json/bp/v1';
    const url = `${String(base).replace(/\/$/, '')}/public/settings?_t=${Date.now()}`;

    fetch(url, { credentials: 'same-origin' })
      .then((res) => res.json())
      .then((json) => {
        if (!alive) return;
        const payload = json?.data || json?.settings || null;
        cache = payload || null;
        setSettings(cache);
      })
      .catch(() => {
        if (!alive) return;
        setSettings(null);
      })
      .finally(() => {
        if (!alive) return;
        setLoading(false);
      });

    return () => {
      alive = false;
    };
  }, [open]);

  return { settings, loading };
}
