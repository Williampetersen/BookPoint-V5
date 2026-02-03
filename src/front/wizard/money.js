export function normalizeCurrencyCode(input) {
  const raw = String(input || '').trim();
  if (!raw) return '';

  const match = raw.match(/[A-Za-z]{3}/);
  return (match ? match[0] : raw).toUpperCase();
}

export function currencySymbol(currencyCode) {
  const code = normalizeCurrencyCode(currencyCode) || 'USD';

  try {
    const parts = new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: code,
      currencyDisplay: 'narrowSymbol',
    }).formatToParts(0);
    const part = parts.find((p) => p.type === 'currency');
    return part?.value || code;
  } catch (e) {
    const fallback = {
      USD: '$',
      EUR: '€',
      GBP: '£',
      DKK: 'Kr',
      NOK: 'Kr',
      SEK: 'Kr',
      ISK: 'Kr',
      CAD: '$',
      AUD: '$',
    };
    return fallback[code] || code;
  }
}

export function formatMoney(amount, settings = {}) {
  if (amount == null) return '-';
  const value = Number(amount);
  if (!Number.isFinite(value)) return '-';

  const currencyCode =
    normalizeCurrencyCode(settings.currency) ||
    normalizeCurrencyCode(settings.bp_default_currency) ||
    normalizeCurrencyCode(typeof window !== 'undefined' ? window.BP_FRONT?.currency : '') ||
    'USD';

  const positionRaw = settings.currency_position || settings.bp_currency_position || 'before';
  const position = positionRaw === 'after' ? 'after' : 'before';

  const symbol = currencySymbol(currencyCode);

  const formattedNumber = value.toLocaleString(undefined, {
    minimumFractionDigits: value % 1 === 0 ? 0 : 2,
    maximumFractionDigits: 2,
  });

  const needsSpace = /^[A-Z]{2,5}$/.test(symbol);
  if (position === 'after') {
    return needsSpace ? `${formattedNumber} ${symbol}` : `${formattedNumber}${symbol}`;
  }
  return needsSpace ? `${symbol} ${formattedNumber}` : `${symbol}${formattedNumber}`;
}

