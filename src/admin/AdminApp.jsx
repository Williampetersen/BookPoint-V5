import React from "react";
import FormFieldsScreen from "./screens/FormFieldsScreen";

export default function AdminApp(){
  const page = window.BP_ADMIN?.page || "bp";

  if (page === "bp-form-fields") return <FormFieldsScreen />;

  return (
    <div style={{padding:18, fontWeight:900}}>
      BookPoint admin page: {page}
    </div>
  );
}
