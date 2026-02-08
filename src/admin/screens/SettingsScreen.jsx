import React, { useEffect, useMemo, useState } from "react";
import ScheduleScreen from "./ScheduleScreen";
import HolidaysScreen from "./HolidaysScreen";
import FormFieldsScreen from "./FormFieldsScreen";
import PromoCodesScreen from "./PromoCodesScreen";
import NotificationsScreen from "./NotificationsScreen";
import AuditScreen from "./AuditScreen";
import ToolsScreen from "./ToolsScreen";
import PaymentsSettings from "./settings/PaymentsSettings";

const SETTINGS_TABS = [
  { key: "general", label: "General" },
  { key: "payments", label: "Payments" },
  { key: "schedule", label: "Schedule" },
  { key: "holidays", label: "Holidays" },
  { key: "form_fields", label: "Form Fields" },
  { key: "promo_codes", label: "Promo Codes" },
  { key: "notifications", label: "Notifications" },
  { key: "audit_log", label: "Audit Log" },
  { key: "tools", label: "Tools" },
  { key: "license", label: "License" },
];

const DAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
const BOOKING_STATUS_OPTIONS = [
  { value: "confirmed", label: "Approved" },
  { value: "pending", label: "Pending Approval" },
  { value: "cancelled", label: "Cancelled" },
  { value: "completed", label: "Completed" },
];

function currencyPreview(code, position) {
  const currency = String(code || "USD").toUpperCase();
  const pos = position === "after" ? "after" : "before";
  const amount = 12.5;
  let symbol = currency;
  try {
    const parts = new Intl.NumberFormat(undefined, {
      style: "currency",
      currency,
      currencyDisplay: "narrowSymbol",
    }).formatToParts(0);
    const part = parts.find((p) => p.type === "currency");
    if (part?.value) symbol = part.value;
  } catch {}
  const formatted = amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const needsSpace = /^[A-Z]{2,5}$/.test(symbol);
  return pos === "after"
    ? (needsSpace ? `${formatted} ${symbol}` : `${formatted}${symbol}`)
    : (needsSpace ? `${symbol} ${formatted}` : `${symbol}${formatted}`);
}

export default function SettingsScreen() {
  const [settings, setSettings] = useState({});
  const [loading, setLoading] = useState(true);
  const [saved, setSaved] = useState(false);
  const [license, setLicense] = useState({
    key: "",
    status: "unset",
    server_base_url: "",
    server_base_effective: "",
    checked_at: 0,
    last_error: "",
    plan: "",
    expires_at: "",
    licensed_domain: "",
    instance_id: "",
    data: "",
  });
  const [licenseSaving, setLicenseSaving] = useState(false);
  const [licenseValidating, setLicenseValidating] = useState(false);
  const [licenseActivating, setLicenseActivating] = useState(false);
  const [licenseDeactivating, setLicenseDeactivating] = useState(false);
  const [showLicenseKey, setShowLicenseKey] = useState(false);
  const [showLicenseDetails, setShowLicenseDetails] = useState(false);
  const licenseStatusKey = String(license.status || "unset").toLowerCase();

  const [activeTab, setActiveTab] = useState(() => {
    const tab = new URLSearchParams(window.location.search).get("tab");
    return SETTINGS_TABS.find((t) => t.key === tab)?.key || "general";
  });

  const currencyOptions = useMemo(() => ([
    { code: "AED", name: "UAE Dirham" },
    { code: "AFN", name: "Afghan Afghani" },
    { code: "ALL", name: "Albanian Lek" },
    { code: "AMD", name: "Armenian Dram" },
    { code: "ANG", name: "Netherlands Antillean Guilder" },
    { code: "AOA", name: "Angolan Kwanza" },
    { code: "ARS", name: "Argentine Peso" },
    { code: "AUD", name: "Australian Dollar" },
    { code: "AWG", name: "Aruban Florin" },
    { code: "AZN", name: "Azerbaijani Manat" },
    { code: "BAM", name: "Bosnia-Herzegovina Convertible Mark" },
    { code: "BBD", name: "Barbadian Dollar" },
    { code: "BDT", name: "Bangladeshi Taka" },
    { code: "BGN", name: "Bulgarian Lev" },
    { code: "BHD", name: "Bahraini Dinar" },
    { code: "BIF", name: "Burundian Franc" },
    { code: "BMD", name: "Bermudian Dollar" },
    { code: "BND", name: "Brunei Dollar" },
    { code: "BOB", name: "Bolivian Boliviano" },
    { code: "BRL", name: "Brazilian Real" },
    { code: "BSD", name: "Bahamian Dollar" },
    { code: "BTN", name: "Bhutanese Ngultrum" },
    { code: "BWP", name: "Botswana Pula" },
    { code: "BYN", name: "Belarusian Ruble" },
    { code: "BZD", name: "Belize Dollar" },
    { code: "CAD", name: "Canadian Dollar" },
    { code: "CDF", name: "Congolese Franc" },
    { code: "CHF", name: "Swiss Franc" },
    { code: "CLP", name: "Chilean Peso" },
    { code: "CNY", name: "Chinese Yuan" },
    { code: "COP", name: "Colombian Peso" },
    { code: "CRC", name: "Costa Rican ColÃ³n" },
    { code: "CUC", name: "Cuban Convertible Peso" },
    { code: "CUP", name: "Cuban Peso" },
    { code: "CVE", name: "Cape Verdean Escudo" },
    { code: "CZK", name: "Czech Koruna" },
    { code: "DJF", name: "Djiboutian Franc" },
    { code: "DKK", name: "Danish Krone" },
    { code: "DOP", name: "Dominican Peso" },
    { code: "DZD", name: "Algerian Dinar" },
    { code: "EGP", name: "Egyptian Pound" },
    { code: "ERN", name: "Eritrean Nakfa" },
    { code: "ETB", name: "Ethiopian Birr" },
    { code: "EUR", name: "Euro" },
    { code: "FJD", name: "Fijian Dollar" },
    { code: "FKP", name: "Falkland Islands Pound" },
    { code: "GBP", name: "British Pound" },
    { code: "GEL", name: "Georgian Lari" },
    { code: "GHS", name: "Ghanaian Cedi" },
    { code: "GIP", name: "Gibraltar Pound" },
    { code: "GMD", name: "Gambian Dalasi" },
    { code: "GNF", name: "Guinean Franc" },
    { code: "GTQ", name: "Guatemalan Quetzal" },
    { code: "GYD", name: "Guyanaese Dollar" },
    { code: "HKD", name: "Hong Kong Dollar" },
    { code: "HNL", name: "Honduran Lempira" },
    { code: "HRK", name: "Croatian Kuna" },
    { code: "HTG", name: "Haitian Gourde" },
    { code: "HUF", name: "Hungarian Forint" },
    { code: "IDR", name: "Indonesian Rupiah" },
    { code: "ILS", name: "Israeli New Shekel" },
    { code: "INR", name: "Indian Rupee" },
    { code: "IQD", name: "Iraqi Dinar" },
    { code: "IRR", name: "Iranian Rial" },
    { code: "ISK", name: "Icelandic KrÃ³na" },
    { code: "JMD", name: "Jamaican Dollar" },
    { code: "JOD", name: "Jordanian Dinar" },
    { code: "JPY", name: "Japanese Yen" },
    { code: "KES", name: "Kenyan Shilling" },
    { code: "KGS", name: "Kyrgystani Som" },
    { code: "KHR", name: "Cambodian Riel" },
    { code: "KMF", name: "Comorian Franc" },
    { code: "KPW", name: "North Korean Won" },
    { code: "KRW", name: "South Korean Won" },
    { code: "KWD", name: "Kuwaiti Dinar" },
    { code: "KYD", name: "Cayman Islands Dollar" },
    { code: "KZT", name: "Kazakhstani Tenge" },
    { code: "LAK", name: "Laotian Kip" },
    { code: "LBP", name: "Lebanese Pound" },
    { code: "LKR", name: "Sri Lankan Rupee" },
    { code: "LRD", name: "Liberian Dollar" },
    { code: "LSL", name: "Lesotho Loti" },
    { code: "LYD", name: "Libyan Dinar" },
    { code: "MAD", name: "Moroccan Dirham" },
    { code: "MDL", name: "Moldovan Leu" },
    { code: "MGA", name: "Malagasy Ariary" },
    { code: "MKD", name: "Macedonian Denar" },
    { code: "MMK", name: "Myanmar Kyat" },
    { code: "MNT", name: "Mongolian TÃ¶grÃ¶g" },
    { code: "MOP", name: "Macanese Pataca" },
    { code: "MRU", name: "Mauritanian Ouguiya" },
    { code: "MUR", name: "Mauritian Rupee" },
    { code: "MVR", name: "Maldivian Rufiyaa" },
    { code: "MWK", name: "Malawian Kwacha" },
    { code: "MXN", name: "Mexican Peso" },
    { code: "MYR", name: "Malaysian Ringgit" },
    { code: "MZN", name: "Mozambican Metical" },
    { code: "NAD", name: "Namibian Dollar" },
    { code: "NGN", name: "Nigerian Naira" },
    { code: "NIO", name: "Nicaraguan CÃ³rdoba" },
    { code: "NOK", name: "Norwegian Krone" },
    { code: "NPR", name: "Nepalese Rupee" },
    { code: "NZD", name: "New Zealand Dollar" },
    { code: "OMR", name: "Omani Rial" },
    { code: "PAB", name: "Panamanian Balboa" },
    { code: "PEN", name: "Peruvian Sol" },
    { code: "PGK", name: "Papua New Guinean Kina" },
    { code: "PHP", name: "Philippine Peso" },
    { code: "PKR", name: "Pakistani Rupee" },
    { code: "PLN", name: "Polish ZÅ‚oty" },
    { code: "PYG", name: "Paraguayan GuaranÃ­" },
    { code: "QAR", name: "Qatari Riyal" },
    { code: "RON", name: "Romanian Leu" },
    { code: "RSD", name: "Serbian Dinar" },
    { code: "RUB", name: "Russian Ruble" },
    { code: "RWF", name: "Rwandan Franc" },
    { code: "SAR", name: "Saudi Riyal" },
    { code: "SBD", name: "Solomon Islands Dollar" },
    { code: "SCR", name: "Seychellois Rupee" },
    { code: "SDG", name: "Sudanese Pound" },
    { code: "SEK", name: "Swedish Krona" },
    { code: "SGD", name: "Singapore Dollar" },
    { code: "SHP", name: "Saint Helena Pound" },
    { code: "SLE", name: "Sierra Leonean Leone" },
    { code: "SOS", name: "Somali Shilling" },
    { code: "SRD", name: "Surinamese Dollar" },
    { code: "SSP", name: "South Sudanese Pound" },
    { code: "STN", name: "SÃ£o TomÃ© and PrÃ­ncipe Dobra" },
    { code: "SYP", name: "Syrian Pound" },
    { code: "SZL", name: "Swazi Lilangeni" },
    { code: "THB", name: "Thai Baht" },
    { code: "TJS", name: "Tajikistani Somoni" },
    { code: "TMT", name: "Turkmenistani Manat" },
    { code: "TND", name: "Tunisian Dinar" },
    { code: "TOP", name: "Tongan PaÊ»anga" },
    { code: "TRY", name: "Turkish Lira" },
    { code: "TTD", name: "Trinidad and Tobago Dollar" },
    { code: "TWD", name: "New Taiwan Dollar" },
    { code: "TZS", name: "Tanzanian Shilling" },
    { code: "UAH", name: "Ukrainian Hryvnia" },
    { code: "UGX", name: "Ugandan Shilling" },
    { code: "USD", name: "US Dollar" },
    { code: "UYU", name: "Uruguayan Peso" },
    { code: "UZS", name: "Uzbekistan Som" },
    { code: "VES", name: "Venezuelan BolÃ­var" },
    { code: "VND", name: "Vietnamese Äá»“ng" },
    { code: "VUV", name: "Vanuatu Vatu" },
    { code: "WST", name: "Samoan TÄlÄ" },
    { code: "XAF", name: "Central African CFA Franc" },
    { code: "XCD", name: "East Caribbean Dollar" },
    { code: "XOF", name: "West African CFA Franc" },
    { code: "XPF", name: "CFP Franc" },
    { code: "YER", name: "Yemeni Rial" },
    { code: "ZAR", name: "South African Rand" },
    { code: "ZMW", name: "Zambian Kwacha" },
    { code: "ZWL", name: "Zimbabwean Dollar" },
  ]), []);

  useEffect(() => {
    loadSettings();
    loadLicense();
  }, []);

  const setTab = (key) => {
    const url = new URL(window.location.href);
    url.searchParams.set("tab", key);
    window.history.pushState({}, "", url.toString());
    setActiveTab(key);
  };

  async function loadSettings() {
    try {
      setLoading(true);
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/settings`, {
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce },
      });
      const json = await resp.json();
      setSettings(json.data || {});
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  }

  async function loadLicense() {
    try {
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/license`, {
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce },
      });
      const json = await resp.json();
      if (json.status === "success") {
        setLicense(json.data || {});
      }
    } catch (e) {
      console.error(e);
    }
  }

  function updateSetting(key, value) {
    setSettings({ ...settings, [key]: value });
  }

  function getSetting(keys, fallback) {
    const list = Array.isArray(keys) ? keys : [keys];
    for (const key of list) {
      if (Object.prototype.hasOwnProperty.call(settings, key)) {
        return settings[key];
      }
    }
    return fallback;
  }

  function toBool(val) {
    return val === true || val === 1 || val === "1" || val === "true";
  }

  async function saveSettings() {
    try {
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/settings`, {
        method: "POST",
        headers: {
          "X-WP-Nonce": window.BP_ADMIN?.nonce,
          "Content-Type": "application/json",
        },
        body: JSON.stringify(settings),
      });
      const json = await resp.json();
      if (json.status === "success") {
        setSaved(true);
        setTimeout(() => setSaved(false), 3000);
      }
    } catch (e) {
      console.error(e);
    }
  }

  async function saveLicense() {
    try {
      setLicenseSaving(true);
      setShowLicenseDetails(false);
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/license`, {
        method: "POST",
        headers: {
          "X-WP-Nonce": window.BP_ADMIN?.nonce,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          key: String(license.key || "").trim(),
          server_base_url: String(license.server_base_url || "").trim(),
        }),
      });
      const json = await resp.json();
      if (json.status === "success") {
        setLicense(json.data || {});
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLicenseSaving(false);
    }
  }

  async function validateLicense() {
    try {
      setLicenseValidating(true);
      setShowLicenseDetails(false);
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/license/validate`, {
        method: "POST",
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce, "Content-Type": "application/json" },
        body: JSON.stringify({ key: license?.key || "", server_base_url: license?.server_base_url || "" }),
      });
      const json = await resp.json();
      if (json.status === "success") {
        setLicense(json.data || {});
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLicenseValidating(false);
    }
  }

  async function activateLicense() {
    try {
      setLicenseActivating(true);
      setShowLicenseDetails(false);
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/license/activate`, {
        method: "POST",
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce, "Content-Type": "application/json" },
        body: JSON.stringify({ key: license?.key || "", server_base_url: license?.server_base_url || "" }),
      });
      const json = await resp.json();
      if (json.status === "success") {
        setLicense(json.data || {});
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLicenseActivating(false);
    }
  }

  async function deactivateLicense() {
    try {
      setLicenseDeactivating(true);
      setShowLicenseDetails(false);
      const resp = await fetch(`${window.BP_ADMIN?.restUrl}/admin/license/deactivate`, {
        method: "POST",
        headers: { "X-WP-Nonce": window.BP_ADMIN?.nonce, "Content-Type": "application/json" },
        body: JSON.stringify({ key: license?.key || "", server_base_url: license?.server_base_url || "" }),
      });
      const json = await resp.json();
      if (json.status === "success") {
        setLicense(json.data || {});
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLicenseDeactivating(false);
    }
  }

  const licenseMeta = useMemo(() => {
    try {
      const raw = license?.data;
      if (!raw) return null;
      if (typeof raw === "object") return raw;
      return JSON.parse(String(raw));
    } catch {
      return null;
    }
  }, [license]);

  const copyText = async (text) => {
    const v = String(text || "");
    try {
      await navigator.clipboard.writeText(v);
      return true;
    } catch (_) {
      try {
        const ta = document.createElement("textarea");
        ta.value = v;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand("copy");
        ta.remove();
        return true;
      } catch {
        return false;
      }
    }
  };

  const timeAgo = (unixSeconds) => {
    const s = Number(unixSeconds) || 0;
    if (!s) return "";
    const diff = Math.max(0, Date.now() - s * 1000);
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return "just now";
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 48) return `${hrs}h ago`;
    const days = Math.floor(hrs / 24);
    return `${days}d ago`;
  };

  // Advanced settings panel removed (keeps Settings clean and avoids accidental edits).

  function renderInput(key, value) {
    if (typeof value === "boolean") {
      return (
        <input
          type="checkbox"
          checked={value}
          onChange={(e) => updateSetting(key, e.target.checked)}
        />
      );
    }

    if (typeof value === "number") {
      return (
        <input
          type="number"
          value={value}
          onChange={(e) => updateSetting(key, Number(e.target.value))}
          className="bp-input"
        />
      );
    }

    if (Array.isArray(value) || (value && typeof value === "object")) {
      return (
        <textarea
          className="bp-input"
          rows={3}
          value={JSON.stringify(value)}
          onChange={(e) => {
            try {
              updateSetting(key, JSON.parse(e.target.value));
            } catch {
              updateSetting(key, e.target.value);
            }
          }}
        />
      );
    }

    return (
      <input
        type="text"
        value={value ?? ""}
        onChange={(e) => updateSetting(key, e.target.value)}
        className="bp-input"
      />
    );
  }

  if (loading) return <div className="bp-card">Loading...</div>;

  return (
    <div className="bp-container bp-settings">
      <div className="bp-settings-hero">
        <div>
          <div className="bp-settings-title">Settings</div>
          <div className="bp-muted">Configure scheduling, notifications, and system preferences.</div>
        </div>
      </div>

      <div className="bp-settings-layout">
        <div className="bp-settings-nav bp-card">
          {SETTINGS_TABS.map((tab) => (
            <button
              key={tab.key}
              className={`bp-tab ${activeTab === tab.key ? "active" : ""}`}
              onClick={() => setTab(tab.key)}
              type="button"
            >
              {tab.label}
            </button>
          ))}
        </div>

        <div className="bp-settings-panel">
          {activeTab === "general" && (
            <div className="bp-settings-grid">
              <div className="bp-card">
                <div className="bp-section-title">Business Hours</div>
                <div className="bp-settings-grid-2">
                  <div className="bp-settings-field">
                    <label className="bp-label">Open time</label>
                    <input
                      type="time"
                      step={300}
                      placeholder="09:00"
                      value={getSetting("bp_open_time", "09:00")}
                      onChange={(e) => updateSetting("bp_open_time", e.target.value)}
                      className="bp-input"
                    />
                  </div>
                  <div className="bp-settings-field">
                    <label className="bp-label">Close time</label>
                    <input
                      type="time"
                      step={300}
                      placeholder="17:00"
                      value={getSetting("bp_close_time", "17:00")}
                      onChange={(e) => updateSetting("bp_close_time", e.target.value)}
                      className="bp-input"
                    />
                  </div>
                  <div className="bp-settings-field">
                    <label className="bp-label">Slot Interval (minutes)</label>
                    <input
                      type="number"
                      min={5}
                      max={120}
                      step={5}
                      value={getSetting("slot_interval_minutes", 15)}
                      onChange={(e) => updateSetting("slot_interval_minutes", parseInt(e.target.value || 15, 10))}
                      className="bp-input"
                    />
                  </div>
                  <div className="bp-settings-field">
                    <label className="bp-label">Booking limit (days ahead)</label>
                    <input
                      type="number"
                      min={1}
                      max={365}
                      value={getSetting("bp_future_days_limit", 60)}
                      onChange={(e) => updateSetting("bp_future_days_limit", parseInt(e.target.value || 60, 10))}
                      className="bp-input"
                    />
                    <div className="bp-muted" style={{ fontSize: 12, marginTop: 6 }}>
                      Customers can only book up to this many days in advance
                    </div>
                  </div>
                </div>

                <div className="bp-section-title" style={{ marginTop: 16 }}>Pricing</div>
                <div className="bp-settings-grid-2">
                  <div className="bp-settings-field">
                    <label className="bp-label">Currency</label>
                    <select
                      value={getSetting(["currency", "bp_default_currency"], "USD")}
                      onChange={(e) => updateSetting("currency", e.target.value)}
                      className="bp-input"
                    >
                      {currencyOptions.map((c) => (
                        <option key={c.code} value={c.code}>
                          {c.code} - {c.name}
                        </option>
                      ))}
                    </select>
                    <div className="bp-muted bp-settings-help">
                      Preview: {currencyPreview(getSetting(["currency", "bp_default_currency"], "USD"), getSetting(["currency_position", "bp_currency_position"], "before"))}
                    </div>
                  </div>
                  <div className="bp-settings-field">
                    <label className="bp-label">Currency Position</label>
                    <select
                      value={getSetting(["currency_position", "bp_currency_position"], "before")}
                      onChange={(e) => updateSetting("currency_position", e.target.value)}
                      className="bp-input"
                    >
                      <option value="before">Before amount (e.g., $10)</option>
                      <option value="after">After amount (e.g., 10$)</option>
                    </select>
                    <div className="bp-muted bp-settings-help">
                      Applies across booking form, services, and invoices.
                    </div>
                  </div>
                  <div className="bp-settings-field" style={{ gridColumn: "1 / -1" }}>
                    <label className="bp-label">Daily breaks</label>
                    <input
                      type="text"
                      placeholder="12:00-13:00,15:00-15:15"
                      value={getSetting("bp_breaks", "12:00-13:00")}
                      onChange={(e) => updateSetting("bp_breaks", e.target.value)}
                      className="bp-input"
                    />
                    <div className="bp-muted" style={{ fontSize: 12, marginTop: 6 }}>
                      Comma-separated break times (e.g., 12:00-13:00,15:00-15:15)
                    </div>
                  </div>
                </div>

                <div className="bp-section-title" style={{ marginTop: 16 }}>Weekly Schedule</div>
                <div className="bp-settings-week">
                  {DAYS.map((label, index) => (
                    <div key={label} className="bp-settings-week-row">
                      <div className="bp-settings-week-day">{label}</div>
                      <input
                        type="text"
                        placeholder="09:00-17:00"
                        value={getSetting(`bp_schedule_${index}`, "")}
                        onChange={(e) => updateSetting(`bp_schedule_${index}`, e.target.value)}
                        className="bp-input"
                      />
                    </div>
                  ))}
                  <div className="bp-muted" style={{ fontSize: 12 }}>
                    Leave empty for closed days. Format: HH:MM-HH:MM (e.g., 09:00-17:00)
                  </div>
                </div>

                <div className="bp-section-title" style={{ marginTop: 16 }}>Bookings</div>
                <div className="bp-settings-grid-2">
                  <div className="bp-settings-field">
                    <label className="bp-label">Default status</label>
                    <select
                      value={getSetting("bp_default_booking_status", "pending")}
                      onChange={(e) => updateSetting("bp_default_booking_status", e.target.value)}
                      className="bp-input"
                    >
                      {BOOKING_STATUS_OPTIONS.map((opt) => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="bp-settings-actions">
                  <button onClick={saveSettings} className="bp-btn bp-btn-primary">Save Settings</button>
                  {saved && <span className="bp-settings-saved">âœ“ Saved!</span>}
                </div>
              </div>
            </div>
          )}

          {activeTab === "schedule" && (
            <ScheduleScreen embedded />
          )}

          {activeTab === "payments" && (
            <PaymentsSettings />
          )}

          {activeTab === "holidays" && (
            <HolidaysScreen embedded />
          )}

          {activeTab === "form_fields" && (
            <FormFieldsScreen embedded />
          )}

          {activeTab === "promo_codes" && (
            <PromoCodesScreen />
          )}

          {activeTab === "notifications" && (
            <NotificationsScreen />
          )}

          {activeTab === "audit_log" && (
            <AuditScreen />
          )}

          {activeTab === "tools" && (
            <ToolsScreen />
          )}

          {activeTab === "license" && (
  <div className="bp-license">
    <div className="bp-license-grid">
      <div className="bp-card bp-license-status">
        <div className="bp-card-head" style={{ padding: 14, borderBottom: "1px solid rgba(15,23,42,.06)" }}>
          <div>
            <div className="bp-section-title" style={{ margin: 0 }}>License Status</div>
            <div className="bp-muted bp-text-xs">Validation, plan, and support info.</div>
          </div>
          <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
            <button type="button" onClick={validateLicense} className="bp-btn" disabled={licenseValidating}>
              {licenseValidating ? "Validating..." : "Validate Now"}
            </button>
            <a className="bp-btn" href="admin.php?page=bp_settings&tab=tools">Diagnostics</a>
          </div>
        </div>

          <div className="bp-license-statusBody">
            <div className={`bp-license-pill is-${licenseStatusKey}`}>
              <span className="k">Status</span>
              <span className="v">{licenseStatusKey}</span>
            </div>

          <div className="bp-license-kvGrid">
            <div className="bp-license-kv">
              <div className="k">Plan</div>
              <div className="v">{license.plan || licenseMeta?.plan || "-"}</div>
            </div>
            <div className="bp-license-kv">
              <div className="k">Expires</div>
              <div className="v">{license.expires_at || licenseMeta?.expires_at || licenseMeta?.expires || "-"}</div>
            </div>
            <div className="bp-license-kv">
              <div className="k">Licensed Domain</div>
              <div className="v">{license.licensed_domain || licenseMeta?.licensed_domain || licenseMeta?.domain || "-"}</div>
            </div>
            <div className="bp-license-kv">
              <div className="k">Instance ID</div>
              <div className="v">{license.instance_id || licenseMeta?.instance_id || "-"}</div>
            </div>
          </div>

          <div className="bp-license-kvRow">
            <div className="k">Last checked</div>
            <div className="v">
              {license.checked_at ? (
                <span>
                  {new Date(license.checked_at * 1000).toLocaleString()} {" "}
                  <span className="bp-muted">({timeAgo(license.checked_at)})</span>
                </span>
              ) : (
                "-"
              )}
            </div>
          </div>

          {license.last_error ? (
            <div className={`bp-license-error is-${licenseStatusKey}`}>
              <div style={{ display: "flex", justifyContent: "space-between", gap: 10, alignItems: "center" }}>
                <div style={{ minWidth: 0 }}>
                  <div className="bp-label" style={{ margin: 0 }}>Server message</div>
                  <div className="bp-muted bp-text-xs" style={{ marginTop: 4 }}>{license.last_error}</div>
                </div>
                <button className="bp-btn" type="button" onClick={() => setShowLicenseDetails((v) => !v)}>
                  {showLicenseDetails ? "Hide" : "Details"}
                </button>
              </div>
              {showLicenseDetails && licenseMeta ? (
                <pre className="bp-license-pre">{JSON.stringify(licenseMeta, null, 2)}</pre>
              ) : null}
            </div>
          ) : null}

          <div className="bp-license-help">
            <div className="bp-section-title" style={{ margin: 0, fontSize: 14 }}>Support</div>
            <div className="bp-muted bp-text-xs" style={{ marginTop: 6 }}>
              If validation fails, copy these and send to support:
            </div>
            <div className="bp-license-helpRow">
              <code className="bp-license-code">{license.server_base_effective || "-"}</code>
              <button className="bp-btn" type="button" onClick={async () => { await copyText(license.server_base_effective || ""); }}>
                Copy Server
              </button>
            </div>
            <div className="bp-license-helpRow">
              <code className="bp-license-code">{window.location.origin}</code>
              <button className="bp-btn" type="button" onClick={async () => { await copyText(window.location.origin); }}>Copy</button>
            </div>
            <div className="bp-license-helpRow">
              <code className="bp-license-code">{window.location.hostname}</code>
              <button className="bp-btn" type="button" onClick={async () => { await copyText(window.location.hostname); }}>Copy</button>
            </div>
          </div>
        </div>
      </div>

      <div className="bp-card bp-license-manage">
        <div className="bp-card-head" style={{ padding: 14, borderBottom: "1px solid rgba(15,23,42,.06)" }}>
          <div>
            <div className="bp-section-title" style={{ margin: 0 }}>Manage License</div>
            <div className="bp-muted bp-text-xs">Set server URL, paste key, then activate.</div>
          </div>
        </div>

          <div className="bp-license-manageBody">
            <div className="bp-license-field">
              <label className="bp-label">License server URL</label>
              <input
                type="text"
                value={license.server_base_url || ""}
                onChange={(e) => setLicense({ ...license, server_base_url: e.target.value })}
                className="bp-input-field"
                placeholder="https://wpbookpoint.com"
                autoComplete="off"
              />
            <div className="bp-muted bp-text-xs" style={{ marginTop: 8 }}>
              This must be the store domain that runs the BookPoint License Server plugin. Leave empty to use the default server.
            </div>
          </div>

          <div className="bp-license-field">
            <label className="bp-label">License key</label>
            <div className="bp-license-keyRow">
              <input
                type={showLicenseKey ? "text" : "password"}
                value={license.key || ""}
                onChange={(e) => setLicense({ ...license, key: e.target.value })}
                className="bp-input-field"
                placeholder="Paste your license key..."
                autoComplete="off"
              />
              <button type="button" className="bp-btn" onClick={() => setShowLicenseKey((v) => !v)}>
                {showLicenseKey ? "Hide" : "Show"}
              </button>
            </div>
            <div className="bp-muted bp-text-xs" style={{ marginTop: 8 }}>
              Tip: whitespace is trimmed on save.
            </div>
          </div>

          <div className="bp-license-actions">
            <button type="button" onClick={saveLicense} className="bp-btn bp-btn-primary" disabled={licenseSaving}>
              {licenseSaving ? "Saving..." : "Save Key"}
            </button>
            <button
              type="button"
              onClick={activateLicense}
              className="bp-btn"
              disabled={licenseActivating || licenseSaving || !license.key}
            >
              {licenseActivating ? "Activating..." : "Activate"}
            </button>
            <button
              type="button"
              onClick={deactivateLicense}
              className="bp-btn"
              disabled={licenseDeactivating || licenseSaving || !license.key}
            >
              {licenseDeactivating ? "Deactivating..." : "Deactivate"}
            </button>
            <button type="button" onClick={validateLicense} className="bp-btn" disabled={licenseValidating}>
              {licenseValidating ? "Validating..." : "Validate Now"}
            </button>
            <button
              type="button"
              onClick={async () => {
                if (!license.key) return;
                if (!confirm("Remove license key from this site?")) return;
                setLicense({ ...license, key: "" });
                await saveLicense();
              }}
              className="bp-btn bp-btn-danger"
              disabled={licenseSaving || !license.key}
            >
              Remove Key
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
)}</div>
      </div>
    </div>
  );
}

