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

    fetch(`/wp-json/bp/v1/front/settings?_t=${Date.now()}`, { credentials: 'same-origin' })
      .then((res) => res.json())
      .then((json) => {
        if (!alive) return;
        const payload = json?.settings || null;
        cache = payload;
        setSettings(payload);
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
