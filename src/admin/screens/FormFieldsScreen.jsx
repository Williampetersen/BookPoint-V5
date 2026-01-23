import React, { useEffect, useState } from "react";
import { bpFetch } from "../api/client";

export default function FormFieldsScreen(){
  const [scope, setScope] = useState("customer");
  const [rows, setRows] = useState([]);
  const [err, setErr] = useState("");
  const [success, setSuccess] = useState("");
  const [loading, setLoading] = useState(false);
  const [deleting, setDeleting] = useState(null);

  async function load(){
    setLoading(true);
    setErr("");
    try{
      const res = await bpFetch(`/admin/form-fields?scope=${scope}`);
      setRows(res?.data || []);
    }catch(e){
      setRows([]);
      setErr(e.message || "Failed to load");
    }finally{
      setLoading(false);
    }
  }

  useEffect(()=>{ load(); /* eslint-disable-next-line */ }, [scope]);


  async function handleDelete(id){
    if(!confirm("Delete this field?")) return;
    setDeleting(id);
    setErr("");
    try{
      await bpFetch(`/admin/form-fields/${id}`, { method:'DELETE' });
      await load();
      setSuccess("Field deleted!");
      setTimeout(()=>setSuccess(""), 3000);
    }catch(e){
      setErr(e.message || "Delete failed");
    }finally{
      setDeleting(null);
    }
  }


  async function handleReseed(){
    if(!confirm("Reseed all default fields (first_name, last_name, email, phone, notes)?")) return;
    setErr("");
    setSuccess("");
    try{
      await bpFetch(`/admin/form-fields/reseed`, { method:'POST', body:{} });
      setSuccess("Defaults reseeded!");
      await load();
      setTimeout(()=>setSuccess(""), 3000);
    }catch(e){
      setErr(e.message || "Reseed failed");
    }
  }

  return (
    <div className="bp-content">
      <div className="bp-page-head">
        <div>
          <div className="bp-h1">Form Fields</div>
          <div className="bp-muted">Manage custom fields for booking wizard.</div>
        </div>
        <div className="bp-head-actions">
          <button onClick={()=>setScope("form")} className={`bp-btn ${scope==="form" ? "bp-btn-primary" : ""}`}>Form</button>
          <button onClick={()=>setScope("customer")} className={`bp-btn ${scope==="customer" ? "bp-btn-primary" : ""}`}>Customer</button>
          <button onClick={()=>setScope("booking")} className={`bp-btn ${scope==="booking" ? "bp-btn-primary" : ""}`}>Booking</button>
          <button onClick={handleReseed} className="bp-btn">Reseed Defaults</button>
          <a className="bp-primary-btn" href={`admin.php?page=bp_form_fields_edit&scope=${scope}`}>+ Add Field</a>
        </div>
      </div>

      {err && <div style={errorBox}>{err}</div>}
      {success && <div style={successBox}>{success}</div>}

      <div className="bp-card" style={{ padding: 0 }}>
        {loading ? <div style={{padding:20, textAlign:'center', fontWeight:900}}>⏳ Loading…</div> : null}

        {!loading && rows.length === 0 && <div style={{padding:20, textAlign:'center', opacity:.7, fontWeight:900}}>📭 No fields yet. Add one to get started!</div>}

        {!loading && rows.length > 0 && (
          <div style={{overflowX:'auto'}}>
            <table style={{width:'100%', borderCollapse:'collapse', minWidth:900}}>
              <thead>
                <tr style={{background:'#f9fafb', borderBottom:'2px solid #e5e7eb'}}>
                  <th style={th}>Key</th>
                  <th style={th}>Label</th>
                  <th style={th}>Type</th>
                  <th style={{...th, textAlign:'center'}}>Step</th>
                  <th style={{...th, textAlign:'center'}}>Required</th>
                  <th style={{...th, textAlign:'center'}}>Wizard</th>
                  <th style={{...th, textAlign:'center'}}>Enabled</th>
                  <th style={{...th, textAlign:'right'}}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map(r=>(
                  <tr key={r.id} style={{borderBottom:'1px solid #f3f4f6', ':hover':{background:'#fafbfc'}}}>
                    <td style={td}><code style={{background:'#f1f5f9', padding:'4px 8px', borderRadius:6, fontSize:12, fontWeight:900}}>{r.field_key || r.name_key || ''}</code></td>
                    <td style={td}>{r.label}</td>
                    <td style={td}><span style={{background:'#eef2ff', color:'#3730a3', padding:'4px 8px', borderRadius:6, fontSize:12, fontWeight:900}}>{r.type}</span></td>
                    <td style={{...td, textAlign:'center', fontSize:12}}>{r.step_key}</td>
                    <td style={{...td, textAlign:'center'}}>{Number(r.is_required) ? '✅' : '❌'}</td>
                    <td style={{...td, textAlign:'center'}}>{Number(r.show_in_wizard) ? '✅' : '❌'}</td>
                    <td style={{...td, textAlign:'center'}}>{Number(r.is_enabled) ? '✅' : '❌'}</td>
                    <td style={{...td, textAlign:'right', whiteSpace:'nowrap'}}>
                      <a className="bp-btn-sm" href={`admin.php?page=bp_form_fields_edit&id=${r.id}&scope=${scope}`}>Edit</a>
                      <button onClick={()=>handleDelete(r.id)} className="bp-btn-sm bp-btn-danger" disabled={deleting===r.id}>
                        {deleting===r.id ? '⏳' : 'Delete'}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
const th = { textAlign:'left', padding:'14px 16px', fontWeight:1000, fontSize:13, background:'#f9fafb', borderBottom:'2px solid #e5e7eb' };
const td = { padding:'14px 16px', verticalAlign:'middle', fontSize:14 };
const errorBox = { background:'#fef2f2', border:'1px solid #fecaca', color:'#991b1b', padding:14, borderRadius:12, fontWeight:900, marginBottom:16, fontSize:14 };
const successBox = { background:'#f0fdf4', border:'1px solid #86efac', color:'#166534', padding:14, borderRadius:12, fontWeight:900, marginBottom:16, fontSize:14 };
