import React from "react";

function Code({ children }) {
  return (
    <pre
      style={{
        background: "#0b1220",
        color: "#e5e7eb",
        padding: "12px 14px",
        borderRadius: 12,
        overflow: "auto",
        margin: "10px 0 0",
        fontSize: 13,
        lineHeight: 1.45,
      }}
    >
      <code>{children}</code>
    </pre>
  );
}

function Item({ title, children }) {
  return (
    <div className="bp-card" style={{ padding: 16 }}>
      <div className="bp-section-title" style={{ margin: 0, fontSize: 16 }}>
        {title}
      </div>
      <div className="bp-muted" style={{ marginTop: 8, lineHeight: 1.6 }}>
        {children}
      </div>
    </div>
  );
}

export default function HowToUseScreen() {
  return (
    <div className="myplugin-page bp-howto">
      <main className="myplugin-content">
      <div className="bp-page-head">
        <div>
          <div className="bp-title">How to Use BookPoint</div>
          <div className="bp-muted">Everything an admin needs after installing on a new site/device.</div>
        </div>
      </div>

      <div className="bp-grid" style={{ gridTemplateColumns: "repeat(2, minmax(0, 1fr))" }}>
        <Item title="1) First time setup (admin)">
          <ol style={{ margin: "8px 0 0", paddingLeft: 18 }}>
            <li>Activate the plugin (Plugins → Installed Plugins).</li>
            <li>If you use licensing, enter/activate your license in <b>BookPoint → Settings</b>.</li>
            <li>Create at least 1 <b>Service</b> (and Categories/Extras if you use them).</li>
            <li>Create at least 1 <b>Agent</b> and connect agents to services.</li>
            <li>Configure <b>Schedule</b> + <b>Holidays</b> so availability is correct.</li>
            <li>(Optional) Configure <b>Payments</b> and <b>Notifications</b>.</li>
            <li>Add the booking button to your page using the shortcode (next card).</li>
          </ol>
        </Item>

        <Item title="2) Add the booking button (shortcode)">
          <div>The main shortcode is:</div>
          <Code>[bookPoint]</Code>

          <div style={{ marginTop: 10 }}>
            Change the button text:
          </div>
          <Code>[bookPoint label="Book Now"]</Code>

          <div style={{ marginTop: 10 }}>
            If you already have your own button/link, you can trigger the wizard by adding this attribute:
          </div>
          <Code>{`data-bp-open="wizard"`}</Code>

          <div style={{ marginTop: 10 }}>
            Tip: You can use the shortcode on multiple pages (it opens the same booking wizard).
          </div>
        </Item>

        <Item title="3) Button customization (text + CSS)">
          <div>
            Customize the text using the shortcode <code>label</code>.
          </div>
          <div style={{ marginTop: 10 }}>
            For colors, size, border radius, etc. use CSS in your theme (Appearance → Customize → Additional CSS).
          </div>
          <Code>{`.bp-book-btn{
  background:#1973ff;
  color:#fff;
  border-radius:12px;
  padding:12px 18px;
}`}</Code>
        </Item>

        <Item title="4) Skip steps / reduce the wizard">
          <div>
            Go to <b>BookPoint → Booking Form Designer</b> and turn off optional steps you do not need (for example: Location,
            Category, Extras, Payment).
          </div>
          <div style={{ marginTop: 10 }}>
            Note: Some steps are required to create a booking (Service, Agent, Date &amp; Time, Customer, Review, Confirmation).
          </div>
          <div style={{ marginTop: 10 }}>
            You can also customize: step titles/subtitles, step images, help phone, and the wizard primary color.
          </div>
        </Item>

        <Item title="5) Custom fields (Form Fields)">
          <div>
            Open <b>BookPoint → Form Fields</b> to add/remove custom fields (customer + booking) and mark fields as required.
          </div>
          <div style={{ marginTop: 10 }}>
            These fields show in the wizard and are validated before a booking is created.
          </div>
        </Item>

        <Item title="6) Customer Portal page (optional)">
          <div>
            Create a page called <b>My Bookings</b> (or any name) and add:
          </div>
          <Code>[bookPoint_portal]</Code>
          <div style={{ marginTop: 10 }}>
            Customers can enter their email, verify, and view their bookings.
          </div>
        </Item>

        <Item title="7) Quick troubleshooting">
          <ul style={{ margin: "8px 0 0", paddingLeft: 18 }}>
            <li>If the wizard does not open: confirm the page contains <code>[bookPoint]</code> and the site is not blocking JS.</li>
            <li>If no times show: confirm Agent schedule + Holidays, and that the service has at least 1 active agent.</li>
            <li>If payments are missing: enable methods in Settings and verify keys (Stripe/PayPal/WooCommerce).</li>
          </ul>
        </Item>
      </div>
      </main>
    </div>
  );
}
