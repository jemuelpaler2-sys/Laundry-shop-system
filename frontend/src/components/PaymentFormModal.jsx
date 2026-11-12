import { useEffect, useState } from 'react'
import { apiRequest } from '../services/api'
import { formatPeso } from '../utils/orderStatus'

const METHODS = ['Cash', 'GCash', 'Other']

// Pop-up to record a payment for one order
export default function PaymentFormModal({ order, onClose, onSaved }) {
  const [amount, setAmount] = useState(order.balance > 0 ? String(order.balance) : '')
  const [method, setMethod] = useState('Cash')
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

    const value = Number(amount)
    if (amount === '' || Number.isNaN(value) || value <= 0) {
      return setError('Please enter an amount greater than 0.')
    }
    if (value > order.balance + 0.005) {
      return setError(`This is more than what is owed (${formatPeso(order.balance)}).`)
    }

    setSaving(true)
    const result = await apiRequest('payments/save.php', {
      method: 'POST',
      body: { order_id: order.id, amount: value, payment_method: method },
    })
    setSaving(false)

    if (result.success) {
      onSaved(result.message)
    } else {
      setError(result.message || 'Could not record the payment.')
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
          <h2>Record Payment</h2>
          <button className="modal-close" onClick={onClose} aria-label="Close">
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>

        <p className="muted">
          Order <strong>{order.order_number}</strong> · {order.customer_name}<br />
          Total {formatPeso(order.price)} · Paid {formatPeso(order.amount_paid)} · Balance{' '}
          <strong>{formatPeso(order.balance)}</strong>
        </p>

        {error && <div className="alert-error">{error}</div>}

        <form onSubmit={handleSubmit}>
          <div className="field">
            <label htmlFor="pay_amount">Amount (₱)</label>
            <input id="pay_amount" type="number" step="0.01" min="0"
              value={amount} onChange={(e) => setAmount(e.target.value)} autoFocus />
          </div>

          <div className="field">
            <label htmlFor="pay_method">Payment Method</label>
            <select id="pay_method" value={method} onChange={(e) => setMethod(e.target.value)}>
              {METHODS.map((m) => (
                <option key={m} value={m}>{m}</option>
              ))}
            </select>
          </div>

          <div className="modal-actions">
            <button type="submit" className="btn btn-primary" disabled={saving}>
              {saving ? 'Saving...' : 'Record Payment'}
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