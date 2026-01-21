import React, { useEffect, useState } from "react";
import { bpFetch } from "../api/client";

export default function FormFieldsScreen(){
  const [scope, setScope] = useState("customer");
  const [rows, setRows] = useState([]);
  const [err, setErr] = useState("");
  const [loading, setLoading] = useState(false);

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

  return (
    <div style={{padding:18}}>
      <div style={{display:'flex', justifyContent:'space-between', alignItems:'center', gap:12, flexWrap:'wrap'}}>
        <div>
          <div style={{fontSize:18, fontWeight:1000}}>Form Fields</div>
          <div style={{opacity:.7, fontWeight:850, marginTop:6}}>If you see rows here, your REST + React is working.</div>
        </div>
        <div style={{display:'flex', gap:10}}>
          <button onClick={()=>setScope("customer")} style={btn(scope==="customer")}>Customer</button>
          <button onClick={()=>setScope("booking")} style={btn(scope==="booking")}>Booking</button>
        </div>
      </div>

      <div style={{height:14}} />

      {err ? <div style={errorBox}>{err}</div> : null}

      <div style={card}>
        {loading ? <div style={{fontWeight:900}}>Loading…</div> : null}

        {!loading && rows.length === 0 ? (
          <div style={{fontWeight:900, opacity:.7}}>No fields in DB for this scope.</div>
        ) : null}

        {!loading && rows.length > 0 ? (
          <table style={{width:'100%', borderCollapse:'collapse'}}>
            <thead>
              <tr>
                <th style={th}>ID</th>
                <th style={th}>Key</th>
                <th style={th}>Label</th>
                <th style={th}>Type</th>
                <th style={th}>Enabled</th>
                <th style={th}>Wizard</th>
                <th style={th}>Required</th>
              </tr>
            </thead>
            <tbody>
              {rows.map(r=>(
                <tr key={r.id} style={{borderTop:'1px solid #f1f5f9'}}>
                  <td style={td}>{r.id}</td>
                  <td style={td}><code>{r.field_key}</code></td>
                  <td style={td}>{r.label}</td>
                  <td style={td}>{r.type}</td>
                  <td style={td}>{Number(r.is_enabled) ? "YES" : "NO"}</td>
                  <td style={td}>{Number(r.show_in_wizard) ? "YES" : "NO"}</td>
                  <td style={td}>{Number(r.is_required) ? "YES" : "NO"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : null}
      </div>

      <div style={{marginTop:12, opacity:.8, fontWeight:850}}>
        Test REST: open <code>/wp-json/bp/v1/admin/form-fields?scope={scope}</code> in browser (while logged in).
      </div>
    </div>
  );
}

const card = { background:'#fff', border:'1px solid #e5e7eb', borderRadius:18, padding:14 };
const errorBox = { background:'#fef2f2', border:'1px solid #fecaca', color:'#991b1b', padding:10, borderRadius:14, fontWeight:900, marginBottom:12 };
const th = { textAlign:'left', padding:'10px', fontWeight:1000, fontSize:12, opacity:.7, borderBottom:'1px solid #e5e7eb' };
const td = { padding:'10px', verticalAlign:'top' };
const btn = (active)=>({ padding:'10px 12px', borderRadius:14, border:'1px solid #e5e7eb', fontWeight:900, background: active?'#4318ff':'#fff', color: active?'#fff':'#111827', cursor:'pointer' });
