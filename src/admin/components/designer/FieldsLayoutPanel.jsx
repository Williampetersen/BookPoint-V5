import React, { useEffect, useMemo, useState } from "react";

function moveItem(arr, from, to) {
  const copy = [...arr];
  const [item] = copy.splice(from, 1);
  copy.splice(to, 0, item);
  return copy;
}

function normalizeId(def) {
  return def?.id || def?.field_key || def?.name_key || "";
}

function toLayoutFromDefs(defs = []) {
  return defs
    .map((d) => ({
      id: normalizeId(d),
      required: !!(d?.is_required ?? d?.required ?? false),
      width: "full",
    }))
    .filter((d) => d.id);
}

export default function FieldsLayoutPanel({ fieldsByGroup, value, onChange }) {
  const [drag, setDrag] = useState({ group: null, index: null });

  const defsCustomer = fieldsByGroup?.customer || [];
  const defsBooking = fieldsByGroup?.booking || [];

  const customerLayout = useMemo(() => {
    const list = value?.customer?.fields || [];
    return list.length ? list : toLayoutFromDefs(defsCustomer);
  }, [value, defsCustomer]);

  const bookingLayout = useMemo(() => {
    const list = value?.booking?.fields || [];
    return list.length ? list : toLayoutFromDefs(defsBooking);
  }, [value, defsBooking]);

  useEffect(() => {
    if (!value) {
      onChange?.({
        customer: { fields: customerLayout },
        booking: { fields: bookingLayout },
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  function setLayout(nextCustomer, nextBooking) {
    onChange?.({
      customer: { fields: nextCustomer },
      booking: { fields: nextBooking },
    });
  }

  function labelById(group, id) {
    const defs = group === "booking" ? defsBooking : defsCustomer;
    const def = defs.find((d) => normalizeId(d) === id);
    return def?.label || id;
  }

  function toggleReqCustomer(id) {
    setLayout(
      customerLayout.map((f) => (f.id === id ? { ...f, required: !f.required } : f)),
      bookingLayout
    );
  }

  function toggleReqBooking(id) {
    setLayout(
      customerLayout,
      bookingLayout.map((f) => (f.id === id ? { ...f, required: !f.required } : f))
    );
  }

  function removeCustomer(id) {
    setLayout(customerLayout.filter((f) => f.id !== id), bookingLayout);
  }

  function removeBooking(id) {
    setLayout(customerLayout, bookingLayout.filter((f) => f.id !== id));
  }

  return (
    <div>
      <div className="bp-font-800">Fields Layout</div>
      <div className="bp-text-sm bp-muted">Drag to reorder. Set required + width.</div>

      <div className="bp-mt-12">
        <div className="bp-font-700">Customer Fields</div>
        <div className="bp-used-list">
          {customerLayout.map((x, idx) => (
            <div
              key={x.id}
              className="bp-used bp-used-draggable"
              draggable
              onDragStart={() => setDrag({ group: "customer", index: idx })}
              onDragOver={(e) => e.preventDefault()}
              onDrop={() => {
                if (drag.group !== "customer") return;
                const next = moveItem(customerLayout, drag.index, idx);
                setLayout(next, bookingLayout);
                setDrag({ group: null, index: null });
              }}
            >
              <div className="bp-drag-grip">⋮⋮</div>

              <div className="bp-used-main">
                <div className="bp-font-700">{labelById("customer", x.id)}</div>
                <div className="bp-text-xs bp-muted">{x.id}</div>
              </div>

              <button className="bp-mini" onClick={() => toggleReqCustomer(x.id)}>
                {x.required ? "Required" : "Optional"}
              </button>

              <button
                className="bp-mini"
                onClick={() => {
                  const w = x.width === "half" ? "full" : "half";
                  setLayout(
                    customerLayout.map((f) => (f.id === x.id ? { ...f, width: w } : f)),
                    bookingLayout
                  );
                }}
              >
                {x.width === "half" ? "50%" : "100%"}
              </button>

              <button className="bp-mini" onClick={() => removeCustomer(x.id)}>Remove</button>
            </div>
          ))}
        </div>
      </div>

      <div className="bp-mt-14">
        <div className="bp-font-700">Booking Fields</div>
        <div className="bp-used-list">
          {bookingLayout.map((x, idx) => (
            <div
              key={x.id}
              className="bp-used bp-used-draggable"
              draggable
              onDragStart={() => setDrag({ group: "booking", index: idx })}
              onDragOver={(e) => e.preventDefault()}
              onDrop={() => {
                if (drag.group !== "booking") return;
                const next = moveItem(bookingLayout, drag.index, idx);
                setLayout(customerLayout, next);
                setDrag({ group: null, index: null });
              }}
            >
              <div className="bp-drag-grip">⋮⋮</div>

              <div className="bp-used-main">
                <div className="bp-font-700">{labelById("booking", x.id)}</div>
                <div className="bp-text-xs bp-muted">{x.id}</div>
              </div>

              <button className="bp-mini" onClick={() => toggleReqBooking(x.id)}>
                {x.required ? "Required" : "Optional"}
              </button>

              <button
                className="bp-mini"
                onClick={() => {
                  const w = x.width === "half" ? "full" : "half";
                  setLayout(
                    customerLayout,
                    bookingLayout.map((f) => (f.id === x.id ? { ...f, width: w } : f))
                  );
                }}
              >
                {x.width === "half" ? "50%" : "100%"}
              </button>

              <button className="bp-mini" onClick={() => removeBooking(x.id)}>Remove</button>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
