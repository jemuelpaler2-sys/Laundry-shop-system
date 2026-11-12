import { useEffect, useState } from 'react'
import { apiRequest } from '../services/api'
import { DEFAULT_LIMITS } from '../utils/orderStatus'

// Local date (YYYY-MM-DD) n days from today
function addDays(n) {
  const d = new Date()
  d.setDate(d.getDate() + n)
  const pad = (v) => String(v).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

// Pop-up form to create an order (order = null) or edit one (order = object)
export default function OrderFormModal({ order, services, limits, onClose, onSaved }) {
  const isEdit = Boolean(order)
  const { min_weight, max_weight, min_price, default_service } = { ...DEFAULT_LIMITS, ...limits }

  // The shop only offers one service, so the rate never changes.
  const service = default_service
  const rate = Number(services[service]) || 0

  const [customer, setCustomer] = useState(null)
  const [customerSearch, setCustomerSearch] = useState('')
  const [results, setResults] = useState([])

  const [form, setForm] = useState({
    weight: order && order.weight > 0 ? String(order.weight) : '',
    estimated_pickup_date: order?.estimated_pickup_date || (isEdit ? '' : addDays(2)),
    notes: order?.notes || '',
  })
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  // Close with the Esc key
  useEffect(() => {
    function handleKey(e) {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', handleKey)
    return () => window.removeEventListener('keydown', handleKey)
  }, [onClose])

  // Customer search (new orders only)
  useEffect(() => {
    if (isEdit) return
    const timer = setTimeout(async () => {
      const params = new URLSearchParams({
        search: customerSearch.trim(),
        status: 'active',
        page: '1',
      })
      const result = await apiRequest(`customers/list.php?${params.toString()}`)
      if (result.success) setResults(result.customers)
    }, 300)
    return () => clearTimeout(timer)
  }, [customerSearch, isEdit])

  // The price always follows the weight: rate per kg, never below the shop minimum.
  function priceFor(weightValue) {
    const w = parseFloat(weightValue)
    if (!w || w <= 0) return 0
    return Math.max(w * rate, min_price)
  }

  const price = priceFor(form.weight)

  function handleChange(e) {
    const { name, value } = e.target
    setForm({ ...form, [name]: value })
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')

    if (!isEdit && !customer) return setError('Please choose a customer.')

    const weight = form.weight === '' ? null : Number(form.weight)

    if (weight === null) return setError('Please enter the weight in kg.')
    if (Number.isNaN(weight)) return setError('Weight must be a number.')
    if (weight < min_weight) return setError(`Minimum weight is ${min_weight} kg.`)
    if (weight > max_weight) {
      return setError(`Weight must be between ${min_weight} and ${max_weight} kg.`)
    }

    setSaving(true)
    const result = await apiRequest('orders/save.php', {
      method: 'POST',
      body: {
        id: order ? order.id : 0,
        customer_id: customer ? customer.id : 0,
        service_type: service,
        weight,
        estimated_pickup_date: form.estimated_pickup_date,
        notes: form.notes,
      },
    })
    setSaving(false)

    if (result.success) {
      onSaved(result.message)
    } else {
      setError(result.message || 'Could not save the order.')
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
          <h2>{isEdit ? `Edit Order ${order.order_number}` : 'New Order'}</h2>
          <button className="modal-close" onClick={onClose} aria-label="Close">
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>

        {error && <div className="alert-error">{error}</div>}

        <form onSubmit={handleSubmit}>
          {/* Customer */}
          <div className="field">
            <label htmlFor="of_customer">Customer</label>
            {isEdit ? (
              <div className="chip">{order.customer_name} · {order.customer_code}</div>
            ) : customer ? (
              <div>
                <span className="chip">
                  {customer.first_name} {customer.last_name} · {customer.customer_code}
                </span>{' '}
                <button type="button" className="btn-link" onClick={() => setCustomer(null)}>
                  Change
                </button>
              </div>
            ) : (
              <>
                <input
                  id="of_customer"
                  type="text"
                  value={customerSearch}
                  onChange={(e) => setCustomerSearch(e.target.value)}
                  placeholder="Search by name, phone or Customer ID"
                />
                <div className="picker-results">
                  {results.length === 0 ? (
                    <div className="picker-empty">No active customers found.</div>
                  ) : (
                    results.map((c) => (
                      <button
                        type="button"
                        key={c.id}
                        className="picker-item"
                        onClick={() => setCustomer(c)}
                      >
                        {c.first_name} {c.last_name}
                        <small>{c.customer_code} · {c.phone}</small>
                      </button>
                    ))
                  )}
                </div>
              </>
            )}
          </div>

          {/* Service - the shop only offers one, so it is shown, not chosen */}
          <div className="field">
            <label htmlFor="of_service">Service</label>
            <div id="of_service" className="chip">{service}</div>
          </div>

          <div className="field-row">
            <div className="field">
              <label htmlFor="of_weight">Weight (kg)</label>
              <input id="of_weight" name="weight" type="number" step="0.01"
                min={min_weight} max={max_weight} required
                value={form.weight} onChange={handleChange} placeholder="e.g. 8" />
              <small className="field-hint">Minimum weight is {min_weight} kg.</small>
            </div>
            <div className="field">
              <label htmlFor="of_price">Price (₱)</label>
              <input id="of_price" name="price" type="text" readOnly
                value={price > 0 ? price.toFixed(2) : ''} />
              <small className="field-hint">
                ₱{rate.toFixed(2)}/kg, minimum ₱{Number(min_price).toFixed(2)}
              </small>
            </div>
          </div>

          <div className="field">
            <label htmlFor="of_pickup">Estimated Pickup Date</label>
            <input id="of_pickup" name="estimated_pickup_date" type="date"
              value={form.estimated_pickup_date} onChange={handleChange} />
          </div>

          <div className="field">
            <label htmlFor="of_notes">Notes</label>
            <textarea id="of_notes" name="notes" rows="3"
              value={form.notes} onChange={handleChange}
              placeholder="Special instructions (optional)" />
          </div>

          <div className="modal-actions">
            <button type="submit" className="btn btn-primary" disabled={saving}>
              {saving ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Order'}
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