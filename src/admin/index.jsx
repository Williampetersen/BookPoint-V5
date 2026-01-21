import React from "react";
import { createRoot } from "react-dom/client";
import AdminApp from "./AdminApp";

document.addEventListener("DOMContentLoaded", () => {
  const el = document.getElementById("bp-admin-app");
  if (!el) return;
  createRoot(el).render(<AdminApp />);
});
