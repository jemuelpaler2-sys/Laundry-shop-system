import { useEffect, useState } from 'react'
import { apiRequest } from '../services/api'
import { formatQty } from '../utils/orderStatus'

// mode = 'add' (owner adds stock) or 'use' (record supplies used)
export default function StockModal({ item, mode, onClose, onSaved }) {
  const isAdd = mode === 'add'

  const [quantity, setQuantity] = useState('')
  const [reference, setReference] = useState('')
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    function handleKey(e) {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', handleKey)
    return () => window.removeEventListener('keydown', handleKey)
  }, [onClose])

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')

    const qty = Number(quantity)
    if (quantity === '' || Number.isNaN(qty) || qty <= 0) {
      return setError('Please enter a quantity greater than 0.')
    }
    if (!isAdd && qty > item.quantity) {
      return setError(`Not enough stock. Only ${formatQty(item.quantity)} ${item.unit} left.`)
    }

    setSaving(true)
    const result = await apiRequest('inventory/stock.php', {
      method: 'POST',
      body: { inventory_id: item.id, type: mode, quantity: qty, reference },
    })
    setSaving(false)

    if (result.success) {
      onSaved(result.message)
    } else {
      setError(result.message || 'Could not update the stock.')
    }
  }

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div
        className="modal"
        role="dialog"
        aria-modal="true"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="modal-head">
          <h2>{isAdd ? 'Add Stock' : 'Record Usage'}</h2>
          <button className="modal-close" onClick={onClose} aria-label="Close">✕</button>
        </div>

        <p className="muted">
          <strong>{item.item_name}</strong> · currently {formatQty(item.quantity)} {item.unit}
        </p>

        {error && <div className="alert-error">{error}</div>}

        <form onSubmit={handleSubmit}>
          <div className="field">
            <label htmlFor="st_qty">
              {isAdd ? 'Quantity to add' : 'Quantity used'} ({item.unit})
            </label>
            <input id="st_qty" type="number" step="0.001" min="0"
              value={quantity} onChange={(e) => setQuantity(e.target.value)} autoFocus />
          </div>

          <div className="field">
            <label htmlFor="st_ref">Note (optional)</label>
            <input id="st_ref" type="text" maxLength="100"
              value={reference} onChange={(e) => setReference(e.target.value)}
              placeholder={isAdd ? 'e.g. Bought from supplier' : 'e.g. Extra rinse, spilled, damaged'} />
          </div>

          <div className="modal-actions">
            <button type="submit" className="btn btn-primary" disabled={saving}>
              {saving ? 'Saving...' : isAdd ? 'Add Stock' : 'Record Usage'}
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