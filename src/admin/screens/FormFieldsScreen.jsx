import React, { useEffect, useState } from "react";
import { bpFetch } from "../api/client";

const TYPES = ['text','email','tel','textarea','number','date','select','checkbox'];
const STEPS = [{ value:'details', label:'Details' },{ value:'payment', label:'Payment' },{ value:'summary', label:'Summary' }];

export default function FormFieldsScreen(){
  const [scope, setScope] = useState("customer");
  const [rows, setRows] = useState([]);
  const [err, setErr] = useState("");
  const [success, setSuccess] = useState("");
  const [loading, setLoading] = useState(false);
  const [showCreate, setShowCreate] = useState(false);
  const [editId, setEditId] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  const [form, setForm] = useState({
    field_key: '',
    label: '',
    type: 'text',
    step_key: 'details',
    placeholder: '',
    is_required: false,
    is_enabled: true,
    show_in_wizard: true,
  });

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

  async function handleSave(){
    setSubmitting(true);
    setErr("");
    setSuccess("");
    try{
      const data = { ...form, scope };
      if(editId){
        await bpFetch(`/admin/form-fields/${editId}`, { method:'PATCH', body:data });
        setSuccess("Field updated!");
      }else{
        await bpFetch(`/admin/form-fields`, { method:'POST', body:data });
        setSuccess("Field created!");
      }
      setShowCreate(false);
      setEditId(null);
      setForm({ field_key:'', label:'', type:'text', step_key:'details', placeholder:'', is_required:false, is_enabled:true, show_in_wizard:true });
      await load();
      setTimeout(()=>setSuccess(""), 3000);
    }catch(e){
      setErr(e.message || "Save failed");
    }finally{
      setSubmitting(false);
    }
  }

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

  function openEdit(row){
    setForm({
      field_key: row.field_key || row.name_key || '',
      label: row.label,
      type: row.type,
      step_key: row.step_key,
      placeholder: row.placeholder,
      is_required: Number(row.is_required),
      is_enabled: Number(row.is_enabled),
      show_in_wizard: Number(row.show_in_wizard),
    });
    setEditId(row.id);
    setShowCreate(true);
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
    <div style={{padding:18}}>
      <div style={{display:'flex', justifyContent:'space-between', alignItems:'center', gap:12, flexWrap:'wrap', marginBottom:20}}>
        <div>
          <h1 style={{margin:'0 0 6px 0', fontSize:24, fontWeight:1000}}>Form Fields</h1>
          <p style={{margin:0, opacity:.7, fontSize:13}}>Manage custom form fields for booking wizard</p>
        </div>
        <div style={{display:'flex', gap:8, alignItems:'center', flexWrap:'wrap'}}>
          <button onClick={()=>setScope("form")} style={scopeBtn(scope==="form")}>🧩 Form</button>
          <button onClick={()=>setScope("customer")} style={scopeBtn(scope==="customer")}>👤 Customer</button>
          <button onClick={()=>setScope("booking")} style={scopeBtn(scope==="booking")}>📅 Booking</button>
          <button onClick={handleReseed} style={{...secondaryBtn, fontSize:12, padding:'8px 12px'}}>🔄 Reseed Defaults</button>
          <button onClick={()=>{setEditId(null); setForm({ field_key:'', label:'', type:'text', step_key:'details', placeholder:'', is_required:false, is_enabled:true, show_in_wizard:true }); setShowCreate(true);}} style={primaryBtn}>+ Add Field</button>
        </div>
      </div>

      {err && <div style={errorBox}>{err}</div>}
      {success && <div style={successBox}>{success}</div>}

      <div style={card}>
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
                      <button onClick={()=>openEdit(r)} style={miniBtn} disabled={deleting===r.id}>✏️ Edit</button>
                      <button onClick={()=>handleDelete(r.id)} style={{...miniBtn, ...dangerBtn}} disabled={deleting===r.id}>
                        {deleting===r.id ? '⏳' : '🗑️'} Delete
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {showCreate && (
        <Modal title={editId ? "Edit Field" : "Create Field"} onClose={()=>{setShowCreate(false); setEditId(null);}} onSave={handleSave} submitting={submitting}>
          <div style={{display:'grid', gap:16}}>
            <div>
              <label style={lbl}>Field Key *</label>
              <input type="text" value={form.field_key} onChange={e=>setForm({...form, field_key:e.target.value})} placeholder="e.g., company_name" style={input} disabled={editId !== null && !!form.field_key} />
            </div>
            <div>
              <label style={lbl}>Label *</label>
              <input type="text" value={form.label} onChange={e=>setForm({...form, label:e.target.value})} placeholder="Display name" style={input} />
            </div>
            <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap:12}}>
              <div>
                <label style={lbl}>Type *</label>
                <select value={form.type} onChange={e=>setForm({...form, type:e.target.value})} style={select}>
                  {TYPES.map(t=><option key={t}>{t}</option>)}
                </select>
              </div>
              <div>
                <label style={lbl}>Step</label>
                <select value={form.step_key} onChange={e=>setForm({...form, step_key:e.target.value})} style={select}>
                  {STEPS.map(s=><option key={s.value} value={s.value}>{s.label}</option>)}
                </select>
              </div>
            </div>
            <div>
              <label style={lbl}>Placeholder</label>
              <input type="text" value={form.placeholder} onChange={e=>setForm({...form, placeholder:e.target.value})} placeholder="Optional placeholder text" style={input} />
            </div>
            <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap:12}}>
              <label style={chkRow}><input type="checkbox" checked={form.is_required} onChange={e=>setForm({...form, is_required:e.target.checked})} /> Required</label>
              <label style={chkRow}><input type="checkbox" checked={form.is_enabled} onChange={e=>setForm({...form, is_enabled:e.target.checked})} /> Enabled</label>
            </div>
            <label style={chkRow}><input type="checkbox" checked={form.show_in_wizard} onChange={e=>setForm({...form, show_in_wizard:e.target.checked})} /> Show in Wizard</label>
          </div>
        </Modal>
      )}
    </div>
  );
}

function Modal({title, onClose, onSave, submitting, children}){
  return (
    <div style={overlay} onClick={onClose}>
      <div style={modal} onClick={e=>e.stopPropagation()}>
        <div style={modalHead}>
          <h2 style={{margin:0, fontSize:18, fontWeight:1000}}>{title}</h2>
          <button onClick={onClose} style={closeBtn} disabled={submitting}>✕</button>
        </div>
        <div style={modalBody}>{children}</div>
        <div style={modalFoot}>
          <button onClick={onClose} style={secondaryBtn} disabled={submitting}>Cancel</button>
          <button onClick={onSave} style={primaryBtn} disabled={submitting}>{submitting ? '⏳ Saving…' : '💾 Save'}</button>
        </div>
      </div>
    </div>
  );
}

const overlay = { position:'fixed', inset:0, background:'rgba(0,0,0,.3)', zIndex:999998, display:'flex', alignItems:'center', justifyContent:'center' };
const modal = { width:'min(600px, calc(100% - 24px))', background:'#fff', borderRadius:18, border:'1px solid #e5e7eb', boxShadow:'0 20px 60px rgba(0,0,0,.25)' };
const modalHead = { padding:20, borderBottom:'1px solid #f3f4f6', display:'flex', justifyContent:'space-between', alignItems:'center' };
const modalBody = { padding:20, maxHeight:'60vh', overflowY:'auto' };
const modalFoot = { padding:20, borderTop:'1px solid #f3f4f6', display:'flex', justifyContent:'flex-end', gap:10 };

const card = { background:'#fff', border:'1px solid #e5e7eb', borderRadius:18, padding:0, overflow:'hidden' };
const th = { textAlign:'left', padding:'14px 16px', fontWeight:1000, fontSize:13, background:'#f9fafb', borderBottom:'2px solid #e5e7eb' };
const td = { padding:'14px 16px', verticalAlign:'middle', fontSize:14 };
const input = { width:'100%', padding:'10px 12px', borderRadius:10, border:'1px solid #e5e7eb', fontFamily:'inherit', fontWeight:900, fontSize:14, boxSizing:'border-box' };
const select = { width:'100%', padding:'10px 12px', borderRadius:10, border:'1px solid #e5e7eb', fontFamily:'inherit', fontWeight:900, fontSize:14, background:'#fff', boxSizing:'border-box' };
const lbl = { fontWeight:950, display:'block', marginBottom:8, fontSize:14 };
const chkRow = { display:'flex', alignItems:'center', gap:8, fontWeight:900, fontSize:14 };

const errorBox = { background:'#fef2f2', border:'1px solid #fecaca', color:'#991b1b', padding:14, borderRadius:12, fontWeight:900, marginBottom:16, fontSize:14 };
const successBox = { background:'#f0fdf4', border:'1px solid #86efac', color:'#166534', padding:14, borderRadius:12, fontWeight:900, marginBottom:16, fontSize:14 };

const primaryBtn = { padding:'10px 16px', borderRadius:10, border:'none', background:'#4318ff', color:'#fff', fontWeight:900, cursor:'pointer', fontSize:14, transition:'all 0.2s', ':hover':{background:'#3010e0'} };
const secondaryBtn = { padding:'10px 16px', borderRadius:10, border:'1px solid #e5e7eb', background:'#fff', color:'#111', fontWeight:900, cursor:'pointer', fontSize:14, transition:'all 0.2s' };
const dangerBtn = { color:'#dc2626', ':hover':{background:'#fef2f2'} };
const scopeBtn = (active) => ({ padding:'10px 14px', borderRadius:10, border:'1px solid '+( active?'#4318ff':'#e5e7eb'), background:active?'#f0f4ff':'#fff', color:active?'#4318ff':'#111', fontWeight:900, cursor:'pointer', fontSize:14 });
const miniBtn = { padding:'6px 10px', borderRadius:8, border:'1px solid #e5e7eb', background:'#fff', color:'#111', fontWeight:900, cursor:'pointer', fontSize:12, marginRight:6 };
const closeBtn = { background:'none', border:'none', fontSize:20, cursor:'pointer', fontWeight:900, opacity:0.7, ':hover':{opacity:1} };
