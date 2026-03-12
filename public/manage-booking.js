/* global pointlybooking_MANAGE */
"use strict";

(function () {
  function getConfig() {
    var cfg = window.pointlybooking_MANAGE || {};
    var restUrl = typeof cfg.restUrl === "string" ? cfg.restUrl : "";
    restUrl = restUrl.replace(/\/$/, "");
    var i18n = cfg.i18n && typeof cfg.i18n === "object" ? cfg.i18n : {};
    return { restUrl: restUrl, i18n: i18n };
  }

  function setMessage(el, msg) {
    if (!el) return;
    el.textContent = msg || "";
  }

  function clearSelect(selectEl) {
    if (!selectEl) return;
    while (selectEl.options.length > 1) {
      selectEl.remove(1);
    }
    selectEl.value = "";
  }

  function addOption(selectEl, value, label) {
    var opt = document.createElement("option");
    opt.value = value;
    opt.textContent = label;
    selectEl.appendChild(opt);
  }

  function buildSlotsUrl(restUrl, params) {
    var url = restUrl + "/manage/slots";
    var qs = new URLSearchParams(params);
    return url + "?" + qs.toString();
  }

  document.addEventListener("DOMContentLoaded", function () {
    var root = document.querySelector(".bp-reschedule");
    if (!root) return;

    var cfg = getConfig();
    if (!cfg.restUrl) {
      setMessage(root.querySelector(".bp-r-msg"), cfg.i18n.missingRestUrl || "Missing REST URL.");
      return;
    }

    if (!window.fetch) {
      setMessage(root.querySelector(".bp-r-msg"), cfg.i18n.unsupported || "This browser does not support required features.");
      return;
    }

    var serviceId = parseInt(root.getAttribute("data-service-id") || "0", 10) || 0;
    var agentId = parseInt(root.getAttribute("data-agent-id") || "0", 10) || 0;
    var excludeBookingId = parseInt(root.getAttribute("data-exclude-booking-id") || "0", 10) || 0;

    var dateInput = root.querySelector(".bp-r-date");
    var timeSelect = root.querySelector(".bp-r-time");
    var msgEl = root.querySelector(".bp-r-msg");
    var startField = root.querySelector(".bp-new-start");
    var endField = root.querySelector(".bp-new-end");

    if (!serviceId || !dateInput || !timeSelect || !startField || !endField) return;

    var slots = [];

    function clearSelection() {
      startField.value = "";
      endField.value = "";
      timeSelect.value = "";
    }

    function populateSlots(list) {
      clearSelect(timeSelect);
      slots = Array.isArray(list) ? list : [];
      if (!slots.length) return;

      for (var i = 0; i < slots.length; i++) {
        var s = slots[i] || {};
        var label = typeof s.label === "string" ? s.label : "";
        if (!label) continue;
        addOption(timeSelect, String(i), label);
      }
    }

    function applySelectedSlot(indexStr) {
      clearSelection();
      var idx = parseInt(indexStr || "", 10);
      if (!(idx >= 0 && idx < slots.length)) return;
      var s = slots[idx] || {};
      if (typeof s.start === "string") startField.value = s.start;
      if (typeof s.end === "string") endField.value = s.end;
    }

    function loadSlots(dateYmd) {
      setMessage(msgEl, "");
      clearSelection();
      populateSlots([]);

      if (!dateYmd) return;

      timeSelect.disabled = true;
      setMessage(msgEl, cfg.i18n.loadingSlots || "Loading available times...");

      var url = buildSlotsUrl(cfg.restUrl, {
        service_id: String(serviceId),
        agent_id: String(agentId),
        exclude_booking_id: String(excludeBookingId),
        date: dateYmd,
      });

      fetch(url, { credentials: "same-origin" })
        .then(function (r) {
          if (!r.ok) throw new Error("Request failed");
          return r.json();
        })
        .then(function (json) {
          var data = json && json.data;
          populateSlots(data);
          if (!slots.length) {
            setMessage(msgEl, cfg.i18n.noSlots || "No available times for this date.");
          } else {
            setMessage(msgEl, "");
          }
        })
        .catch(function () {
          setMessage(msgEl, cfg.i18n.loadError || "Could not load available times. Please try again.");
        })
        .finally(function () {
          timeSelect.disabled = false;
        });
    }

    dateInput.addEventListener("change", function () {
      loadSlots(dateInput.value || "");
    });

    timeSelect.addEventListener("change", function () {
      applySelectedSlot(timeSelect.value);
    });
  });
})();
