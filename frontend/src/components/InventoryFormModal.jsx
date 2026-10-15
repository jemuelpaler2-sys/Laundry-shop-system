import { useEffect, useState } from 'react'
import { apiRequest } from '../services/api'

// Pop-up form to add an item (item = null) or edit one (item = object)
export default function InventoryFormModal({ item, categories, onClose, onSaved }) {
  const isEdit = Boolean(item)

  const [form, setForm] = useState({
    item_name: item?.item_name || '',
    category: item?.category || '',
    unit: item?.unit || 'pcs',
    quantity: '',
    reorder_level: item ? String(item.reorder_level) : '',
    unit_cost: item ? String(item.unit_cost) : '',
  })
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    function handleKey(e) {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', handleKey)
    return () => window.removeEventListener('keydown', handleKey)
  }, [onClose])

  function handleChange(e) {
    setForm({ ...form, [e.target.name]: e.target.value })
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')

    const reorder = form.reorder_level === '' ? 0 : Number(form.reorder_level)
    const cost = form.unit_cost === '' ? 0 : Number(form.unit_cost)
    const quantity = form.quantity === '' ? 0 : Number(form.quantity)

    if (!form.item_name.trim()) return setError('Please enter the item name.')
    if (!form.category.trim()) return setError('Please enter a category.')
    if (!form.unit.trim()) return setError('Please enter the unit (pcs, liters, kg...).')
    if ([reorder, cost, quantity].some((n) => Number.isNaN(n) || n < 0)) {
      return setError('Quantity, reorder level and unit cost must be 0 or more.')
    }

    setSaving(true)
    const result = await apiRequest('inventory/save.php', {
      method: 'POST',
      body: {
        id: item ? item.id : 0,
        item_name: form.item_name,
        category: form.category,
        unit: form.unit,
        quantity,
        reorder_level: reorder,
        unit_cost: cost,
      },
    })
    setSaving(false)

    if (result.success) {
      onSaved(result.message)
    } else {
      setError(result.message || 'Could not save the item.')
    }
  }

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div
        className="modal modal-wide"
        role="dialog"
        aria-modal="true"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="modal-head">
          <h2>{isEdit ? `Edit ${item.item_name}` : 'Add Inventory Item'}</h2>
          <button className="modal-close" onClick={onClose} aria-label="Close">✕</button>
        </div>

        {error && <div className="alert-error">{error}</div>}

        <form onSubmit={handleSubmit}>
          <div className="field">
            <label htmlFor="inv_name">Item Name</label>
            <input id="inv_name" name="item_name" type="text"
              value={form.item_name} onChange={handleChange} placeholder="e.g. Detergent" />
          </div>

          <div className="field-row">
            <div className="field">
              <label htmlFor="inv_category">Category</label>
              <input id="inv_category" name="category" type="text" list="inv-categories"
                value={form.category} onChange={handleChange} placeholder="e.g. Cleaning Supplies" />
              <datalist id="inv-categories">
                {categories.map((c) => (
                  <option key={c} value={c} />
                ))}
              </datalist>
            </div>
            <div className="field">
              <label htmlFor="inv_unit">Unit</label>
              <input id="inv_unit" name="unit" type="text" list="inv-units"
                value={form.unit} onChange={handleChange} placeholder="pcs, liters, kg" />
              <datalist id="inv-units">
                {['pcs', 'liters', 'kg', 'bottles', 'boxes', 'packs'].map((u) => (
                  <option key={u} value={u} />
                ))}
              </datalist>
            </div>
          </div>

          {!isEdit && (
            <div className="field">
              <label htmlFor="inv_qty">Starting Quantity</label>
              <input id="inv_qty" name="quantity" type="number" step="0.001" min="0"
                value={form.quantity} onChange={handleChange} placeholder="How many do you have now?" />
            </div>
          )}

          <div className="field-row">
            <div className="field">
              <label htmlFor="inv_reorder">Reorder Level</label>
              <input id="inv_reorder" name="reorder_level" type="number" step="0.01" min="0"
                value={form.reorder_level} onChange={handleChange} placeholder="Warn me at or below" />
            </div>
            <div className="field">
              <label htmlFor="inv_cost">Unit Cost (₱)</label>
              <input id="inv_cost" name="unit_cost" type="number" step="0.01" min="0"
                value={form.unit_cost} onChange={handleChange} placeholder="Cost per unit" />
            </div>
          </div>

          {isEdit && (
            <p className="hint">
              To change the quantity, use <strong>Add Stock</strong> or <strong>Record Usage</strong>.
              That keeps the history correct.
            </p>
          )}

          <div className="modal-actions">
            <button type="submit" className="btn btn-primary" disabled={saving}>
              {saving ? 'Saving...' : isEdit ? 'Save Changes' : 'Add Item'}
            </button>
            <button type="button" className="btn btn-outline" onClick={onClose}>
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}