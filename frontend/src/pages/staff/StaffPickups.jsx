import { useEffect, useState } from 'react'
import { apiRequest } from '../../services/api'
import StatusBadge from '../../components/StatusBadge'
import PickupFormModal from '../../components/PickupFormModal'
import { formatDate, formatWeight } from '../../utils/orderStatus'

const PICKUP_LABELS = {
  scheduled: 'Scheduled',
  completed: 'Completed',
  cancelled: 'Cancelled',
  missed: 'Missed',
}

function PickupBadge({ status }) {
  return <span className={`badge badge-${status}`}>{PICKUP_LABELS[status] || status}</span>
}

const TABS = [
  { key: 'needs', label: 'Needs Scheduling' },
  { key: 'upcoming', label: 'Upcoming' },
  { key: 'history', label: 'History' },
]

export default function StaffPickups() {
  const [data, setData] = useState(null)
  const [error, setError] = useState('')
  const [tab, setTab] = useState('needs')
  const [notice, setNotice] = useState({ type: '', text: '' })
  const [reloadKey, setReloadKey] = useState(0)

  // formTarget: { order } for a new pickup, { pickup } for a reschedule, or null (closed)
  const [formTarget, setFormTarget] = useState(null)

  useEffect(() => {
    let active = true
    apiRequest('pickups/list.php').then((result) => {
      if (!active) return
      if (result.success) {
        setData(result)
        setError('')
      } else {
        setError(result.message || 'Could not load the pickup schedule.')
      }
    })
    return () => {
      active = false
    }
  }, [reloadKey])

  function handleSaved(message) {
    setFormTarget(null)
    setNotice({ type: 'success', text: message })
    setReloadKey((k) => k + 1)
  }

  async function handleCancel(pickup) {
    if (!window.confirm(`Cancel the pickup schedule for order ${pickup.order_number}?`)) return

    const result = await apiRequest('pickups/cancel.php', { method: 'POST', body: { id: pickup.id } })
    setNotice({
      type: result.success ? 'success' : 'error',
      text: result.message || 'Could not cancel the pickup.',
    })
    setReloadKey((k) => k + 1)
  }

  async function handleMissed(pickup) {
    if (!window.confirm(`Mark order ${pickup.order_number}'s pickup as missed?`)) return

    const result = await apiRequest('pickups/miss.php', { method: 'POST', body: { id: pickup.id } })
    setNotice({
      type: result.success ? 'success' : 'error',
      text: result.message || 'Could not update the pickup.',
    })
    setReloadKey((k) => k + 1)
  }

  if (error) return <div className="alert-error">{error}</div>
  if (!data) return <p className="loading-text">Loading the pickup schedule...</p>

  const { needs_scheduling: needs, upcoming, history, slots } = data

  return (
    <div>
      <h1 className="page-title">Pickup Scheduling</h1>
      <p className="page-sub">Set pickup times and keep track of who is coming for their laundry.</p>

      {notice.text && (
        <div className={notice.type === 'success' ? 'alert-success' : 'alert-error'}>
          {notice.text}
        </div>
      )}

      {/* Tabs */}
      <div className="tab-row">
        {TABS.map((t) => {
          const count = t.key === 'needs' ? needs.length : t.key === 'upcoming' ? upcoming.length : history.length
          return (
            <button
              key={t.key}
              className={`tab ${tab === t.key ? 'tab-active' : ''}`}
              onClick={() => setTab(t.key)}
            >
              {t.label} {count > 0 && <span className="tab-count">{count}</span>}
            </button>
          )
        })}
      </div>

      {/* Needs scheduling */}
      {tab === 'needs' && (
        <div className="card">
          {needs.length === 0 ? (
            <p className="empty">Every open order has a pickup time. 🎉</p>
          ) : (
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Weight</th>
                    <th>Status</th>
                    <th>Estimated Pickup</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {needs.map((o) => (
                    <tr key={o.id}>
                      <td><strong>{o.order_number}</strong></td>
                      <td>{o.customer_name} <div className="muted">{o.customer_code}</div></td>
                      <td>{formatWeight(o.weight)}</td>
                      <td><StatusBadge status={o.order_status} /></td>
                      <td>{formatDate(o.estimated_pickup_date)}</td>
                      <td>
                        <button className="btn-link" onClick={() => setFormTarget({ order: o })}>
                          Schedule Pickup
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* Upcoming */}
      {tab === 'upcoming' && (
        <div className="card">
          {upcoming.length === 0 ? (
            <p className="empty">No pickups are scheduled right now.</p>
          ) : (
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Pickup Date</th>
                    <th>Time</th>
                    <th>Order Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {upcoming.map((p) => (
                    <tr key={p.id}>
                      <td><strong>{p.order_number}</strong></td>
                      <td>{p.customer_name} <div className="muted">{p.customer_code}</div></td>
                      <td>{formatDate(p.scheduled_date)}</td>
                      <td>{p.scheduled_time}</td>
                      <td><StatusBadge status={p.order_status} /></td>
                      <td>
                        <div className="row-actions">
                          <button className="btn-link" onClick={() => setFormTarget({ pickup: p })}>
                            Reschedule
                          </button>
                          <button className="btn-link" onClick={() => handleMissed(p)}>
                            Mark Missed
                          </button>
                          <button className="btn-link btn-link-danger" onClick={() => handleCancel(p)}>
                            Cancel
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* History */}
      {tab === 'history' && (
        <div className="card">
          {history.length === 0 ? (
            <p className="empty">No pickup history yet.</p>
          ) : (
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Pickup Date</th>
                    <th>Time</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {history.map((p) => (
                    <tr key={p.id}>
                      <td><strong>{p.order_number}</strong></td>
                      <td>{p.customer_name} <div className="muted">{p.customer_code}</div></td>
                      <td>{formatDate(p.scheduled_date)}</td>
                      <td>{p.scheduled_time}</td>
                      <td><PickupBadge status={p.status} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {formTarget && (
        <PickupFormModal
          order={formTarget.order}
          pickup={formTarget.pickup}
          slots={slots}
          onClose={() => setFormTarget(null)}
          onSaved={handleSaved}
        />
      )}
    </div>
  )
}