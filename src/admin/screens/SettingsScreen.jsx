import React, { useEffect, useMemo, useState } from "react";
import ScheduleScreen from "./ScheduleScreen";
import HolidaysScreen from "./HolidaysScreen";
import FormFieldsScreen from "./FormFieldsScreen";
import PromoCodesScreen from "./PromoCodesScreen";
import NotificationsScreen from "./NotificationsScreen";
import AuditScreen from "./AuditScreen";
import ToolsScreen from "./ToolsScreen";

const SETTINGS_TABS = [
  { key: "general", label: "General" },
  { key: "schedule", label: "Schedule" },
  { key: "holidays", label: "Holidays" },
  { key: "form_fields", label: "Form Fields" },
  { key: "promo_codes", label: "Promo Codes" },
  { key: "notifications", label: "Notifications" },
  { key: "audit_log", label: "Audit Log" },
  { key: "tools", label: "Tools" },
];

const DAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
const BOOKING_STATUS_OPTIONS = [
  { value: "confirmed", label: "Approved" },
  { value: "pending", label: "Pending Approval" },
  { value: "cancelled", label: "Cancelled" },
  { value: "completed", label: "Completed" },
];

export default function SettingsScreen() {
  const [settings, setSettings] = useState({});
  const [loading, setLoading] = useState(true);
  const [saved, setSaved] = useState(false);

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
    { code: "CRC", name: "Costa Rican Colón" },
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
    { code: "ISK", name: "Icelandic Króna" },
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
    { code: "MNT", name: "Mongolian Tögrög" },
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
    { code: "NIO", name: "Nicaraguan Córdoba" },
    { code: "NOK", name: "Norwegian Krone" },
    { code: "NPR", name: "Nepalese Rupee" },
    { code: "NZD", name: "New Zealand Dollar" },
    { code: "OMR", name: "Omani Rial" },
    { code: "PAB", name: "Panamanian Balboa" },
    { code: "PEN", name: "Peruvian Sol" },
    { code: "PGK", name: "Papua New Guinean Kina" },
    { code: "PHP", name: "Philippine Peso" },
    { code: "PKR", name: "Pakistani Rupee" },
    { code: "PLN", name: "Polish Złoty" },
    { code: "PYG", name: "Paraguayan Guaraní" },
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
    { code: "STN", name: "São Tomé and Príncipe Dobra" },
    { code: "SYP", name: "Syrian Pound" },
    { code: "SZL", name: "Swazi Lilangeni" },
    { code: "THB", name: "Thai Baht" },
    { code: "TJS", name: "Tajikistani Somoni" },
    { code: "TMT", name: "Turkmenistani Manat" },
    { code: "TND", name: "Tunisian Dinar" },
    { code: "TOP", name: "Tongan Paʻanga" },
    { code: "TRY", name: "Turkish Lira" },
    { code: "TTD", name: "Trinidad and Tobago Dollar" },
    { code: "TWD", name: "New Taiwan Dollar" },
    { code: "TZS", name: "Tanzanian Shilling" },
    { code: "UAH", name: "Ukrainian Hryvnia" },
    { code: "UGX", name: "Ugandan Shilling" },
    { code: "USD", name: "US Dollar" },
    { code: "UYU", name: "Uruguayan Peso" },
    { code: "UZS", name: "Uzbekistan Som" },
    { code: "VES", name: "Venezuelan Bolívar" },
    { code: "VND", name: "Vietnamese Đồng" },
    { code: "VUV", name: "Vanuatu Vatu" },
    { code: "WST", name: "Samoan Tālā" },
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

  const advancedKeys = useMemo(() => {
    const keys = Object.keys(settings || {});
    return keys
      .filter((k) => ![
        "slot_interval_minutes",
        "currency",
        "currency_position",
        "bp_open_time",
        "bp_close_time",
        "bp_future_days_limit",
        "bp_breaks",
        "bp_email_enabled",
        "bp_admin_email",
        "bp_email_from_name",
        "bp_email_from_email",
        "bp_default_booking_status",
        "webhooks_enabled",
        "webhooks_secret",
        "webhooks_url_booking_created",
        "webhooks_url_booking_status_changed",
        "webhooks_url_booking_updated",
        "webhooks_url_booking_cancelled",
        "bp_remove_data_on_uninstall",
      ].includes(k))
      .sort();
  }, [settings]);

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
                      type="text"
                      placeholder="09:00"
                      value={getSetting("bp_open_time", "09:00")}
                      onChange={(e) => updateSetting("bp_open_time", e.target.value)}
                      className="bp-input"
                    />
                  </div>
                  <div className="bp-settings-field">
                    <label className="bp-label">Close time</label>
                    <input
                      type="text"
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

                <div className="bp-section-title" style={{ marginTop: 16 }}>Uninstall</div>
                <label className="bp-check">
                  <input
                    type="checkbox"
                    checked={toBool(getSetting("bp_remove_data_on_uninstall", 0))}
                    onChange={(e) => updateSetting("bp_remove_data_on_uninstall", e.target.checked ? 1 : 0)}
                  />
                  Delete all BookPoint data when the plugin is uninstalled.
                </label>

                <div className="bp-settings-actions">
                  <button onClick={saveSettings} className="bp-btn bp-btn-primary">Save Settings</button>
                  {saved && <span className="bp-settings-saved">✓ Saved!</span>}
                </div>
              </div>

              <div className="bp-card">
                <div className="bp-section-title">All Settings</div>
                {advancedKeys.length === 0 ? (
                  <div className="bp-muted">No additional settings found.</div>
                ) : (
                  <div className="bp-kv">
                    {advancedKeys.map((key) => (
                      <React.Fragment key={key}>
                        <div className="bp-k">{key}</div>
                        <div className="bp-v">{renderInput(key, settings[key])}</div>
                      </React.Fragment>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}

          {activeTab === "schedule" && (
            <ScheduleScreen />
          )}

          {activeTab === "holidays" && (
            <HolidaysScreen />
          )}

          {activeTab === "form_fields" && (
            <FormFieldsScreen />
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

        </div>
      </div>
    </div>
  );
}
