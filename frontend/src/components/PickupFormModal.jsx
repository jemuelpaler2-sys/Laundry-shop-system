import { useEffect, useState } from 'react'
import { apiRequest } from '../services/api'

// Local date (YYYY-MM-DD) n days from today
function addDays(n) {
  const d = new Date()
  d.setDate(d.getDate() + n)
  const pad = (v) => String(v).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

// order = an item from "needs scheduling" (new pickup)
// pickup = an item from "upcoming" (reschedule)
export default function PickupFormModal({ order, pickup, slots, onClose, onSaved }) {
  const isReschedule = Boolean(pickup)
  const orderNumber = isReschedule ? pickup.order_number : order.order_number

  const [date, setDate] = useState(
    isReschedule ? pickup.scheduled_date : order.estimated_pickup_date || addDays(2)
  )
  const [time, setTime] = useState(isReschedule ? pickup.scheduled_time : slots[0] || '')
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

    if (!date) return setError('Please choose a pickup date.')
    if (!time) return setError('Please choose a time slot.')

    setSaving(true)
    const result = await apiRequest('pickups/save.php', {
      method: 'POST',
      body: isReschedule
        ? { id: pickup.id, scheduled_date: date, scheduled_time: time }
        : { order_id: order.id, scheduled_date: date, scheduled_time: time },
    })
    setSaving(false)

    if (result.success) {
      onSaved(result.message)
    } else {
      setError(result.message || 'Could not save the pickup.')
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
          <h2>{isReschedule ? 'Reschedule Pickup' : 'Schedule Pickup'}</h2>
          <button className="modal-close" onClick={onClose} aria-label="Close">✕</button>
        </div>

        <p className="muted">
          Order <strong>{orderNumber}</strong>
          {!isReschedule && ` · ${order.customer_name} (${order.customer_code})`}
        </p>

        {error && <div className="alert-error">{error}</div>}

        <form onSubmit={handleSubmit}>
          <div className="field">
            <label htmlFor="pk_date">Pickup Date</label>
            <input id="pk_date" type="date" value={date} onChange={(e) => setDate(e.target.value)} />
          </div>

          <div className="field">
            <label htmlFor="pk_time">Time Slot</label>
            <select id="pk_time" value={time} onChange={(e) => setTime(e.target.value)}>
              {slots.map((s) => (
                <option key={s} value={s}>{s}</option>
              ))}
            </select>
          </div>

          <div className="modal-actions">
            <button type="submit" className="btn btn-primary" disabled={saving}>
              {saving ? 'Saving...' : isReschedule ? 'Save New Time' : 'Schedule Pickup'}
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