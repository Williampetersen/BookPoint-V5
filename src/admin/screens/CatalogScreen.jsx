import React, { useEffect, useMemo, useState } from 'react';
import { bpFetch } from '../api/client';
import { Card } from '../ui/Card';
import { Drawer } from '../ui/Drawer';
import { pickImage } from '../ui/wpMedia';
import { input, inputSm, btn, btn2, danger } from '../ui/Inputs';

const tabs = [
  { key: 'categories', label: 'Categories' },
  { key: 'services', label: 'Services' },
  { key: 'extras', label: 'Service Extras' },
  { key: 'agents', label: 'Agents' },
];

export default function CatalogScreen() {
  const [tab, setTab] = useState('categories');

  const [categories, setCategories] = useState([]);
  const [services, setServices] = useState([]);
  const [extras, setExtras] = useState([]);
  const [agents, setAgents] = useState([]);

  const [loading, setLoading] = useState(false);
  const [toast, setToast] = useState(null);

  const pushToast = (type, msg) => setToast({ type, msg });

  const loadAll = async () => {
    setLoading(true);
    try {
      const [c, s, e, a] = await Promise.all([
        bpFetch('/admin/categories'),
        bpFetch('/admin/services'),
        bpFetch('/admin/extras'),
        bpFetch('/admin/agents'),
      ]);
      setCategories(c?.data || []);
      setServices(s?.data || []);
      setExtras(e?.data || []);
      setAgents(a?.data || []);
    } catch (err) {
      pushToast('error', err.message || 'Failed to load catalog');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadAll();
  }, []);

  return (
    <div style={{ padding: 16 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap', alignItems: 'center' }}>
        <div>
          <h2 style={{ margin: 0 }}>Catalog</h2>
          <div style={{ color: '#6b7280', fontWeight: 800, marginTop: 4 }}>
            Manage categories, services, extras and agents with images and connections.
          </div>
        </div>

        <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
          <button onClick={loadAll} style={btn2} disabled={loading}>
            {loading ? 'Refreshing…' : 'Refresh'}
          </button>
        </div>
      </div>

      <div style={{ display: 'flex', gap: 8, marginTop: 14, flexWrap: 'wrap' }}>
        {tabs.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            style={{
              padding: '10px 12px',
              borderRadius: 999,
              border: '1px solid #e5e7eb',
              background: tab === t.key ? 'rgba(67,24,255,.10)' : '#fff',
              fontWeight: 950,
              cursor: 'pointer',
            }}
          >
            {t.label}
          </button>
        ))}
      </div>

      <div style={{ marginTop: 14 }}>
        {tab === 'categories' ? (
          <CategoriesPanel rows={categories} onChanged={loadAll} pushToast={pushToast} />
        ) : null}

        {tab === 'services' ? (
          <ServicesPanel rows={services} categories={categories} onChanged={loadAll} pushToast={pushToast} />
        ) : null}

        {tab === 'extras' ? (
          <ExtrasPanel rows={extras} services={services} onChanged={loadAll} pushToast={pushToast} />
        ) : null}

        {tab === 'agents' ? (
          <AgentsPanel rows={agents} services={services} onChanged={loadAll} pushToast={pushToast} />
        ) : null}
      </div>

      {toast ? <Toast toast={toast} onClose={() => setToast(null)} /> : null}
    </div>
  );
}

function Toast({ toast, onClose }) {
  return (
    <div
      style={{
        position: 'fixed',
        right: 18,
        bottom: 18,
        zIndex: 999999,
        background: '#0b1437',
        color: '#fff',
        padding: '10px 12px',
        borderRadius: 12,
        fontWeight: 900,
        display: 'flex',
        gap: 10,
        alignItems: 'center',
      }}
    >
      <span>{toast.msg}</span>
      <button
        onClick={onClose}
        style={{ background: 'transparent', border: 'none', color: '#fff', cursor: 'pointer', fontWeight: 900, fontSize: 16 }}
      >
        ×
      </button>
    </div>
  );
}

/* ------------------------- Categories Panel ------------------------- */

function CategoriesPanel({ rows, onChanged, pushToast }) {
  const [open, setOpen] = useState(false);
  const [edit, setEdit] = useState(null);
  const [saving, setSaving] = useState(false);

  const startNew = () => {
    setEdit({ name: '', sort_order: 0, image_id: 0, image_url: '' });
    setOpen(true);
  };

  const startEdit = (r) => {
    setEdit({ ...r });
    setOpen(true);
  };

  const save = async () => {
    setSaving(true);
    try {
      if (edit.id) {
        await bpFetch(`/admin/categories/${edit.id}`, { method: 'PATCH', body: edit });
        pushToast('success', 'Category updated ✅');
      } else {
        await bpFetch(`/admin/categories`, { method: 'POST', body: edit });
        pushToast('success', 'Category created ✅');
      }
      setOpen(false);
      onChanged();
    } catch (e) {
      pushToast('error', e.message || 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  const del = async () => {
    if (!edit?.id) return;
    if (!confirm('Delete this category?')) return;
    setSaving(true);
    try {
      await bpFetch(`/admin/categories/${edit.id}`, { method: 'DELETE' });
      pushToast('success', 'Deleted ✅');
      setOpen(false);
      onChanged();
    } catch (e) {
      pushToast('error', e.message || 'Delete failed');
    } finally {
      setSaving(false);
    }
  };

  const pick = async () => {
    try {
      const img = await pickImage({ title: 'Select category image' });
      setEdit((prev) => ({ ...prev, image_id: img.id, image_url: img.url }));
    } catch (e) {
      pushToast('error', e.message);
    }
  };

  return (
    <>
      <Card
        title="Categories"
        subtitle="Boss level (top). Used as first step in booking wizard."
        right={<button style={btn} onClick={startNew}>+ Add</button>}
      >
        <Table
          cols={['Image', 'Name', 'Order']}
          rows={rows}
          renderRow={(r) => (
            <tr key={r.id} style={{ borderTop: '1px solid #f3f4f6' }}>
              <td style={td}>
                <Avatar url={r.image_url} />
              </td>
              <td style={td}>
                <div style={{ fontWeight: 950 }}>{r.name}</div>
              </td>
              <td style={td}>{r.sort_order}</td>
              <td style={tdRight}>
                <button style={btn2} onClick={() => startEdit(r)}>Edit</button>
              </td>
            </tr>
          )}
        />
      </Card>

      <Drawer
        open={open}
        title={edit?.id ? 'Edit Category' : 'New Category'}
        onClose={() => setOpen(false)}
        footer={
          <div style={{ display: 'flex', gap: 10, justifyContent: 'space-between' }}>
            <div>{edit?.id ? <button style={danger} onClick={del} disabled={saving}>Delete</button> : null}</div>
            <div style={{ display: 'flex', gap: 10 }}>
              <button style={btn2} onClick={() => setOpen(false)} disabled={saving}>Cancel</button>
              <button style={btn} onClick={save} disabled={saving || !edit?.name}>
                {saving ? 'Saving…' : 'Save'}
              </button>
            </div>
          </div>
        }
      >
        {!edit ? null : (
          <div style={{ display: 'grid', gap: 12 }}>
            <div>
              <div style={label}>Name</div>
              <input value={edit.name} onChange={(e) => setEdit((p) => ({ ...p, name: e.target.value }))} style={input} />
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
              <div>
                <div style={label}>Sort order</div>
                <input
                  type="number"
                  value={edit.sort_order || 0}
                  onChange={(e) => setEdit((p) => ({ ...p, sort_order: parseInt(e.target.value || '0', 10) }))}
                  style={input}
                />
              </div>
              <div>
                <div style={label}>Image</div>
                <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                  <Avatar url={edit.image_url} size={44} />
                  <button style={btn2} onClick={pick}>Select image</button>
                  {edit.image_id ? (
                    <button style={btn2} onClick={() => setEdit((p) => ({ ...p, image_id: 0, image_url: '' }))}>Remove</button>
                  ) : null}
                </div>
              </div>
            </div>
          </div>
        )}
      </Drawer>
    </>
  );
}

/* ------------------------- Services Panel ------------------------- */

function ServicesPanel({ rows, categories, onChanged, pushToast }) {
  const [open, setOpen] = useState(false);
  const [edit, setEdit] = useState(null);
  const [saving, setSaving] = useState(false);

  const [serviceCategoryIds, setServiceCategoryIds] = useState([]);

  const startNew = () => {
    setEdit({
      name: '',
      duration: 30,
      price: 0,
      sort_order: 0,
      image_id: 0,
      image_url: '',
      buffer_before: 0,
      buffer_after: 0,
      capacity: 1,
    });
    setServiceCategoryIds([]);
    setOpen(true);
  };

  const startEdit = async (r) => {
    setEdit({ ...r });
    setOpen(true);
    try {
      const rel = await bpFetch(`/admin/services/${r.id}/categories`);
      setServiceCategoryIds(rel?.data || []);
    } catch (e) {
      pushToast('error', e.message || 'Failed loading categories relation');
    }
  };

  const pick = async () => {
    try {
      const img = await pickImage({ title: 'Select service image' });
      setEdit((prev) => ({ ...prev, image_id: img.id, image_url: img.url }));
    } catch (e) {
      pushToast('error', e.message);
    }
  };

  const save = async () => {
    setSaving(true);
    try {
      let id = edit.id;
      if (id) {
        await bpFetch(`/admin/services/${id}`, { method: 'PATCH', body: edit });
      } else {
        const res = await bpFetch(`/admin/services`, { method: 'POST', body: edit });
        id = res?.data?.id;
      }

      // Save categories relation (multi)
      await bpFetch(`/admin/services/${id}/categories`, {
        method: 'PUT',
        body: { category_ids: serviceCategoryIds },
      });

      pushToast('success', 'Service saved ✅');
      setOpen(false);
      onChanged();
    } catch (e) {
      pushToast('error', e.message || 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  const del = async () => {
    if (!edit?.id) return;
    if (!confirm('Delete this service? (Relations will be removed)')) return;
    setSaving(true);
    try {
      await bpFetch(`/admin/services/${edit.id}`, { method: 'DELETE' });
      pushToast('success', 'Deleted ✅');
      setOpen(false);
      onChanged();
    } catch (e) {
      pushToast('error', e.message || 'Delete failed');
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <Card
        title="Services"
        subtitle="Each service can belong to multiple categories and has buffers + capacity."
        right={<button style={btn} onClick={startNew}>+ Add</button>}
      >
        <Table
          cols={['Image', 'Name', 'Duration', 'Price', 'Order']}
          rows={rows}
          renderRow={(r) => (
            <tr key={r.id} style={{ borderTop: '1px solid #f3f4f6' }}>
              <td style={td}><Avatar url={r.image_url} /></td>
              <td style={td}><div style={{ fontWeight: 950 }}>{r.name}</div></td>
              <td style={td}>{r.duration} min</td>
              <td style={td}>{Number(r.price || 0).toFixed(2)}</td>
              <td style={td}>{r.sort_order}</td>
              <td style={tdRight}><button style={btn2} onClick={() => startEdit(r)}>Edit</button></td>
            </tr>
          )}
        />
      </Card>

      <Drawer
        open={open}
        title={edit?.id ? 'Edit Service' : 'New Service'}
        onClose={() => setOpen(false)}
        footer={
          <div style={{ display: 'flex', gap: 10, justifyContent: 'space-between' }}>
            <div>{edit?.id ? <button style={danger} onClick={del} disabled={saving}>Delete</button> : null}</div>
            <div style={{ display: 'flex', gap: 10 }}>
              <button style={btn2} onClick={() => setOpen(false)} disabled={saving}>Cancel</button>
              <button style={btn} onClick={save} disabled={saving || !edit?.name}>
                {saving ? 'Saving…' : 'Save'}
              </button>
            </div>
          </div>
        }
      >
        {!edit ? null : (
          <div style={{ display: 'grid', gap: 12 }}>
            <div>
              <div style={label}>Name</div>
              <input value={edit.name} onChange={(e) => setEdit((p) => ({ ...p, name: e.target.value }))} style={input} />
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
              <div>
                <div style={label}>Duration (min)</div>
                <input
                  type="number"
                  value={edit.duration || 30}
                  onChange={(e) => setEdit((p) => ({ ...p, duration: parseInt(e.target.value || '30', 10) }))}
                  style={input}
                />
              </div>
              <div>
                <div style={label}>Price</div>
                <input
                  type="number"
                  step="0.01"
                  value={edit.price || 0}
                  onChange={(e) => setEdit((p) => ({ ...p, price: parseFloat(e.target.value || '0') }))}
                  style={input}
                />
              </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
              <div>
                <div style={label}>Buffer before (min)</div>
                <input
                  type="number"
                  value={edit.buffer_before || 0}
                  onChange={(e) => setEdit((p) => ({ ...p, buffer_before: parseInt(e.target.value || '0', 10) }))}
                  style={input}
                />
              </div>
              <div>
                <div style={label}>Buffer after (min)</div>
                <input
                  type="number"
                  value={edit.buffer_after || 0}
                  onChange={(e) => setEdit((p) => ({ ...p, buffer_after: parseInt(e.target.value || '0', 10) }))}
                  style={input}
                />
              </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
              <div>
                <div style={label}>Capacity</div>
                <input
                  type="number"
                  value={edit.capacity || 1}
                  onChange={(e) => setEdit((p) => ({ ...p, capacity: Math.max(1, parseInt(e.target.value || '1', 10)) }))}
                  style={input}
                />
              </div>
              <div>
                <div style={label}>Sort order</div>
                <input
                  type="number"
                  value={edit.sort_order || 0}
                  onChange={(e) => setEdit((p) => ({ ...p, sort_order: parseInt(e.target.value || '0', 10) }))}
                  style={input}
                />
              </div>
            </div>

            <div>
              <div style={label}>Categories (multi)</div>
              <MultiSelect
                options={categories.map((c) => ({ id: c.id, label: c.name }))}
                value={serviceCategoryIds}
                onChange={setServiceCategoryIds}
              />
            </div>

            <div>
              <div style={label}>Image</div>
              <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                <Avatar url={edit.image_url} size={44} />
                <button style={btn2} onClick={pick}>Select image</button>
                {edit.image_id ? (
                  <button style={btn2} onClick={() => setEdit((p) => ({ ...p, image_id: 0, image_url: '' }))}>Remove</button>
                ) : null}
              </div>
            </div>
          </div>
        )}
      </Drawer>
    </>
  );
}

/* ------------------------- Extras Panel ------------------------- */

function ExtrasPanel({ rows, services, onChanged, pushToast }) {
  const [open, setOpen] = useState(false);
  const [edit, setEdit] = useState(null);
  const [saving, setSaving] = useState(false);
  const [extraServiceIds, setExtraServiceIds] = useState([]);

  const startNew = () => {
    setEdit({ name: '', price: 0, sort_order: 0, image_id: 0, image_url: '' });
    setExtraServiceIds([]);
    setOpen(true);
  };

  const startEdit = async (r) => {
    setEdit({ ...r });
    setOpen(true);
    try {
      const rel = await bpFetch(`/admin/extras/${r.id}/services`);
      setExtraServiceIds(rel?.data || []);
    } catch (e) {
      pushToast('error', e.message || 'Failed loading services relation');
    }
  };

  const pick = async () => {
    try {
      const img = await pickImage({ title: 'Select extra image' });
      setEdit((prev) => ({ ...prev, image_id: img.id, image_url: img.url }));
    } catch (e) {
      pushToast('error', e.message);
    }
  };

  const save = async () => {
    setSaving(true);
    try {
      let id = edit.id;
      if (id) {
        await bpFetch(`/admin/extras/${id}`, { method: 'PATCH', body: edit });
      } else {
        const res = await bpFetch(`/admin/extras`, { method: 'POST', body: edit });
        id = res?.data?.id;
      }

      await bpFetch(`/admin/extras/${id}/services`, {
        method: 'PUT',
        body: { service_ids: extraServiceIds },
      });

      pushToast('success', 'Extra saved ✅');
      setOpen(false);
      onChanged();
    } catch (e) {
      pushToast('error', e.message || 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  const del = async () => {
    if (!edit?.id) return;
    if (!confirm('Delete this extra?')) return;
    setSaving(true);
    try {
      await bpFetch(`/admin/extras/${edit.id}`, { method: 'DELETE' });
      pushToast('success', 'Deleted ✅');
      setOpen(false);
      onChanged();
    } catch (e) {
      pushToast('error', e.message || 'Delete failed');
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <Card
        title="Service Extras"
        subtitle="Child level. Each extra can connect to multiple services (multi)."
        right={<button style={btn} onClick={startNew}>+ Add</button>}
      >
        <Table
          cols={['Image', 'Name', 'Price', 'Order']}
          rows={rows}
          renderRow={(r) => (
            <tr key={r.id} style={{ borderTop: '1px solid #f3f4f6' }}>
              <td style={td}><Avatar url={r.image_url} /></td>
              <td style={td}><div style={{ fontWeight: 950 }}>{r.name}</div></td>
              <td style={td}>{Number(r.price || 0).toFixed(2)}</td>
              <td style={td}>{r.sort_order}</td>
              <td style={tdRight}><button style={btn2} onClick={() => startEdit(r)}>Edit</button></td>
            </tr>
          )}
        />
      </Card>

      <Drawer
        open={open}
        title={edit?.id ? 'Edit Extra' : 'New Extra'}
        onClose={() => setOpen(false)}
        footer={
          <div style={{ display: 'flex', gap: 10, justifyContent: 'space-between' }}>
            <div>{edit?.id ? <button style={danger} onClick={del} disabled={saving}>Delete</button> : null}</div>
            <div style={{ display: 'flex', gap: 10 }}>
              <button style={btn2} onClick={() => setOpen(false)} disabled={saving}>Cancel</button>
              <button style={btn} onClick={save} disabled={saving || !edit?.name}>
                {saving ? 'Saving…' : 'Save'}
              </button>
            </div>
          </div>
        }
      >
        {!edit ? null : (
          <div style={{ display: 'grid', gap: 12 }}>
            <div>
              <div style={label}>Name</div>
              <input value={edit.name} onChange={(e) => setEdit((p) => ({ ...p, name: e.target.value }))} style={input} />
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
              <div>
                <div style={label}>Price</div>
                <input
                  type="number"
                  step="0.01"
                  value={edit.price || 0}
                  onChange={(e) => setEdit((p) => ({ ...p, price: parseFloat(e.target.value || '0') }))}
                  style={input}
                />
              </div>
              <div>
                <div style={label}>Sort order</div>
                <input
                  type="number"
                  value={edit.sort_order || 0}
                  onChange={(e) => setEdit((p) => ({ ...p, sort_order: parseInt(e.target.value || '0', 10) }))}
                  style={input}
                />
              </div>
            </div>

            <div>
              <div style={label}>Connect to Services (multi)</div>
              <MultiSelect
                options={services.map((s) => ({ id: s.id, label: s.name }))}
                value={extraServiceIds}
                onChange={setExtraServiceIds}
              />
            </div>

            <div>
              <div style={label}>Image</div>
              <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                <Avatar url={edit.image_url} size={44} />
                <button style={btn2} onClick={pick}>Select image</button>
                {edit.image_id ? (
                  <button style={btn2} onClick={() => setEdit((p) => ({ ...p, image_id: 0, image_url: '' }))}>Remove</button>
                ) : null}
              </div>
            </div>
          </div>
        )}
      </Drawer>
    </>
  );
}

/* ------------------------- Agents Panel ------------------------- */

function AgentsPanel({ rows, services, onChanged, pushToast }) {
  const [open, setOpen] = useState(false);
  const [edit, setEdit] = useState(null);
  const [saving, setSaving] = useState(false);

  const [agentServiceIds, setAgentServiceIds] = useState([]);

  const startNew = () => {
    setEdit({ name: '', image_id: 0, image_url: '' });
    setAgentServiceIds([]);
    setOpen(true);
  };

  const startEdit = async (r) => {
    setEdit({ ...r });
    setOpen(true);
    try {
      const rel = await bpFetch(`/admin/agents/${r.id}/services`);
      setAgentServiceIds(rel?.data || []);
    } catch (e) {
      pushToast('error', e.message || 'Failed loading services relation');
    }
  };

  const pick = async () => {
    try {
      const img = await pickImage({ title: 'Select agent photo' });
      setEdit((prev) => ({ ...prev, image_id: img.id, image_url: img.url }));
    } catch (e) {
      pushToast('error', e.message);
    }
  };

  const save = async () => {
    setSaving(true);
    try {
      let id = edit.id;
      if (id) {
        await bpFetch(`/admin/agents/${id}`, { method: 'PATCH', body: edit });
      } else {
        const res = await bpFetch(`/admin/agents`, { method: 'POST', body: edit });
        id = res?.data?.id;
      }

      await bpFetch(`/admin/agents/${id}/services`, {
        method: 'PUT',
        body: { service_ids: agentServiceIds },
      });

      pushToast('success', 'Agent saved ✅');
      setOpen(false);
      onChanged();
    } catch (e) {
      pushToast('error', e.message || 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  const del = async () => {
    if (!edit?.id) return;
    if (!confirm('Delete this agent?')) return;
    setSaving(true);
    try {
      await bpFetch(`/admin/agents/${edit.id}`, { method: 'DELETE' });
      pushToast('success', 'Deleted ✅');
      setOpen(false);
      onChanged();
    } catch (e) {
      pushToast('error', e.message || 'Delete failed');
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <Card
        title="Agents"
        subtitle="Each agent can do multiple services (checkbox mapping)."
        right={<button style={btn} onClick={startNew}>+ Add</button>}
      >
        <Table
          cols={['Photo', 'Name']}
          rows={rows}
          renderRow={(r) => (
            <tr key={r.id} style={{ borderTop: '1px solid #f3f4f6' }}>
              <td style={td}><Avatar url={r.image_url} /></td>
              <td style={td}><div style={{ fontWeight: 950 }}>{r.name}</div></td>
              <td style={tdRight}><button style={btn2} onClick={() => startEdit(r)}>Edit</button></td>
            </tr>
          )}
        />
      </Card>

      <Drawer
        open={open}
        title={edit?.id ? 'Edit Agent' : 'New Agent'}
        onClose={() => setOpen(false)}
        footer={
          <div style={{ display: 'flex', gap: 10, justifyContent: 'space-between' }}>
            <div>{edit?.id ? <button style={danger} onClick={del} disabled={saving}>Delete</button> : null}</div>
            <div style={{ display: 'flex', gap: 10 }}>
              <button style={btn2} onClick={() => setOpen(false)} disabled={saving}>Cancel</button>
              <button style={btn} onClick={save} disabled={saving || !edit?.name}>
                {saving ? 'Saving…' : 'Save'}
              </button>
            </div>
          </div>
        }
      >
        {!edit ? null : (
          <div style={{ display: 'grid', gap: 12 }}>
            <div>
              <div style={label}>Name</div>
              <input value={edit.name} onChange={(e) => setEdit((p) => ({ ...p, name: e.target.value }))} style={input} />
            </div>

            <div>
              <div style={label}>Agent photo</div>
              <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                <Avatar url={edit.image_url} size={44} />
                <button style={btn2} onClick={pick}>Select image</button>
                {edit.image_id ? (
                  <button style={btn2} onClick={() => setEdit((p) => ({ ...p, image_id: 0, image_url: '' }))}>Remove</button>
                ) : null}
              </div>
            </div>

            <div>
              <div style={label}>Services this agent can do</div>
              <CheckboxGrid
                items={services.map((s) => ({ id: s.id, label: s.name }))}
                value={agentServiceIds}
                onChange={setAgentServiceIds}
              />
            </div>
          </div>
        )}
      </Drawer>
    </>
  );
}

/* ------------------------- Shared components ------------------------- */

function Table({ cols, rows, renderRow }) {
  return (
    <div style={{ overflow: 'auto' }}>
      <table style={{ width: '100%', borderCollapse: 'collapse' }}>
        <thead>
          <tr>
            {cols.map((c) => (
              <th key={c} style={th}>{c}</th>
            ))}
            <th style={thRight}></th>
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 ? (
            <tr>
              <td colSpan={cols.length + 1} style={{ padding: 14, color: '#6b7280', fontWeight: 800 }}>
                No items
              </td>
            </tr>
          ) : (
            rows.map(renderRow)
          )}
        </tbody>
      </table>
    </div>
  );
}

function Avatar({ url, size = 36 }) {
  return (
    <div
      style={{
        width: size,
        height: size,
        borderRadius: 12,
        background: '#f3f4f6',
        border: '1px solid #e5e7eb',
        overflow: 'hidden',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
      }}
    >
      {url ? (
        <img src={url} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
      ) : (
        <span style={{ color: '#9ca3af', fontWeight: 900, fontSize: 12 }}>—</span>
      )}
    </div>
  );
}

function MultiSelect({ options, value, onChange }) {
  const valSet = useMemo(() => new Set(value || []), [value]);

  const toggle = (id) => {
    const next = new Set(valSet);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    onChange(Array.from(next));
  };

  return (
    <div
      style={{
        border: '1px solid #e5e7eb',
        borderRadius: 12,
        padding: 10,
        display: 'flex',
        flexWrap: 'wrap',
        gap: 8,
      }}
    >
      {options.length === 0 ? <div style={{ color: '#6b7280', fontWeight: 800 }}>No options</div> : null}
      {options.map((o) => {
        const active = valSet.has(o.id);
        return (
          <button
            key={o.id}
            type="button"
            onClick={() => toggle(o.id)}
            style={{
              padding: '8px 10px',
              borderRadius: 999,
              border: '1px solid #e5e7eb',
              background: active ? 'rgba(67,24,255,.12)' : '#fff',
              fontWeight: 900,
              cursor: 'pointer',
            }}
          >
            {o.label}
          </button>
        );
      })}
    </div>
  );
}

function CheckboxGrid({ items, value, onChange }) {
  const set = useMemo(() => new Set(value || []), [value]);

  const toggle = (id) => {
    const next = new Set(set);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    onChange(Array.from(next));
  };

  return (
    <div
      style={{
        border: '1px solid #e5e7eb',
        borderRadius: 12,
        padding: 10,
        display: 'grid',
        gap: 8,
      }}
    >
      {items.length === 0 ? <div style={{ color: '#6b7280', fontWeight: 800 }}>No services</div> : null}
      {items.map((it) => (
        <label key={it.id} style={{ display: 'flex', gap: 10, alignItems: 'center', fontWeight: 900 }}>
          <input type="checkbox" checked={set.has(it.id)} onChange={() => toggle(it.id)} />
          <span>{it.label}</span>
        </label>
      ))}
    </div>
  );
}

const label = { fontWeight: 950, marginBottom: 6, color: '#0b1437' };
const th = { textAlign: 'left', padding: '10px 8px', color: '#64748b', fontWeight: 950, fontSize: 12 };
const thRight = { ...th, textAlign: 'right' };
const td = { padding: '10px 8px', verticalAlign: 'middle' };
const tdRight = { ...td, textAlign: 'right' };
