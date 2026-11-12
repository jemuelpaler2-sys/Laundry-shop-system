import { useEffect, useState } from 'react'
import { apiRequest } from '../../services/api'
import StatusBadge from '../../components/StatusBadge'
import { formatDate } from '../../utils/orderStatus'

const PICKUP_LABELS = {
  scheduled: 'Scheduled',
  completed: 'Completed',
  cancelled: 'Cancelled',
  missed: 'Missed',
}

function PickupBadge({ status }) {
  return (
    <span className={`badge badge-${status}`}>
      {PICKUP_LABELS[status] || status}
    </span>
  )
}

// Splits 'YYYY-MM-DD' into a short month and a day number for the date block
function dateParts(value) {
  const d = new Date(`${value}T00:00:00`)
  return {
    month: d.toLocaleDateString('en-PH', { month: 'short' }).toUpperCase(),
    day: d.getDate(),
  }
}

export default function CustomerPickup() {
  const [data, setData] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    apiRequest('customer/pickup.php').then((result) => {
      if (result.success) {
        setData(result)
      } else {
        setError(result.message || 'Could not load your pickup schedule.')
      }
    })
  }, [])

  if (error) return <div className="alert-error">{error}</div>
  if (!data) return <p className="loading-text">Loading your pickup schedule...</p>

  const { upcoming, unscheduled, history } = data

  return (
    <div>
      <h1 className="page-title">Pickup Schedule</h1>
      <p className="page-sub">Know when to come and get your clean laundry.</p>

      {/* Upcoming pickups */}
      <div className="card">
        <h2>Upcoming Pickups</h2>

        {upcoming.length === 0 ? (
          <p className="empty">You have no scheduled pickups right now.</p>
        ) : (
          <div className="pickup-grid">
            {upcoming.map((p) => {
              const parts = dateParts(p.scheduled_date)
              return (
                <div key={`${p.order_number}-${p.scheduled_date}`} className="pickup-card">
                  <div className="pickup-date">
                    <small>{parts.month}</small>
                    <strong>{parts.day}</strong>
                  </div>
                  <div className="pickup-info">
                    <h3>Order {p.order_number}</h3>
                    <p>{formatDate(p.scheduled_date)}</p>
                    <p>{p.scheduled_time}</p>
                    <div className="pickup-badges">
                      <PickupBadge status={p.status} />
                      <StatusBadge status={p.order_status} />
                    </div>
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </div>

      {/* Orders with no pickup time yet */}
      {unscheduled.length > 0 && (
        <div className="card">
          <h2>Waiting for a Pickup Schedule</h2>
          <div className="pickup-grid">
            {unscheduled.map((o) => (
              <div key={o.order_number} className="pickup-card waiting">
                <div className="pickup-date pickup-date-alt">
                  <span className="material-symbols-outlined">schedule</span>
                </div>
                <div className="pickup-info">
                  <h3>Order {o.order_number}</h3>
                  <p>No pickup time has been set yet.</p>
                  <p>Estimated pickup: {formatDate(o.estimated_pickup_date)}</p>
                  <div className="pickup-badges">
                    <StatusBadge status={o.order_status} />
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Pickup history */}
      <div className="card">
        <h2>Pickup History</h2>

        {history.length === 0 ? (
          <p className="empty">No past pickups yet.</p>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Order Number</th>
                  <th>Pickup Date</th>
                  <th>Pickup Time</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {history.map((p, index) => (
                  <tr key={`${p.order_number}-${index}`}>
                    <td><strong>{p.order_number}</strong></td>
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
    </div>
  )
}