import { useEffect, useState } from 'react'
import { Link, useOutletContext } from 'react-router-dom'
import { apiRequest } from '../../services/api'
import StatusBadge from '../../components/StatusBadge'
import {
  TRACKER_STEPS,
  STATUS_LABELS,
  getStepState,
  formatPeso,
  formatDate,
  formatWeight,
} from '../../utils/orderStatus'

export default function CustomerDashboard() {
  const { customer } = useOutletContext()
  const [data, setData] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    apiRequest('customer/dashboard.php').then((result) => {
      if (result.success) {
        setData(result)
      } else {
        setError(result.message || 'Could not load your dashboard.')
      }
    })
  }, [])

  if (error) return <div className="alert-error">{error}</div>
  if (!data) return <p className="loading-text">Loading your dashboard...</p>

  const { stats, current_order: order, recent_orders: recent } = data

  // Build the notification list from the data we already have
  const notifications = []
  if (order) {
    notifications.push({
      icon: 'local_laundry_service',
      text: `Your order ${order.order_number} is now ${STATUS_LABELS[order.order_status]}.`,
    })
    if (order.pickup_date) {
      notifications.push({
        icon: 'local_shipping',
        text: `Pickup scheduled for ${formatDate(order.pickup_date)}, ${order.pickup_time}.`,
      })
    }
    if (order.price > 0 && order.balance > 0) {
      notifications.push({
        icon: 'payments',
        text: `You have a balance of ${formatPeso(order.balance)} for ${order.order_number}.`,
      })
    }
  }
  if (stats.ready_for_pickup > 0) {
    const n = stats.ready_for_pickup
    notifications.push({
      icon: 'check_circle',
      text: `${n} ${n === 1 ? 'order is' : 'orders are'} ready for pickup!`,
    })
  }

  const statCards = [
    { icon: 'receipt_long', value: stats.total_orders, label: 'Total Orders' },
    { icon: 'local_laundry_service', value: stats.in_progress, label: 'Orders In Progress' },
    { icon: 'inventory_2', value: stats.ready_for_pickup, label: 'Ready for Pickup' },
    { icon: 'check_circle', value: stats.completed, label: 'Completed Orders' },
  ]

  return (
    <div>
      <h1 className="page-title">Welcome back, {customer.first_name}!</h1>
      <p className="page-sub">Here is what is happening with your laundry.</p>

      {/* Summary cards */}
      <div className="stat-grid">
        {statCards.map((card) => (
          <div key={card.label} className="stat-card">
            <div className="stat-icon">
              <span className="material-symbols-outlined">{card.icon}</span>
            </div>
            <div>
              <div className="stat-value">{card.value}</div>
              <div className="stat-label">{card.label}</div>
            </div>
          </div>
        ))}
      </div>

      {/* Current order */}
      <div className="card current-order">
        <h2>Current Order</h2>

        {!order ? (
          <p className="empty">You have no active orders right now.</p>
        ) : (
          <>
            <div className="co-head">
              <div>
                <h3>Order #{order.order_number}</h3>
                <span className="muted">Dropped off {formatDate(order.dropoff_date)}</span>
              </div>
              <div className="co-head-actions">
                <StatusBadge status={order.order_status} />
                <Link to="/customer/pickup" className="link-inline">
                  Pickup details <span className="material-symbols-outlined">arrow_forward</span>
                </Link>
              </div>
            </div>

            <ul className="stepper">
              {TRACKER_STEPS.map((step, index) => {
                const state = getStepState(order.order_status, index)
                return (
                  <li key={step.key} className={state}>
                    <span className="dot">
                      {state === 'done' ? (
                        <span className="material-symbols-outlined">check</span>
                      ) : (
                        ''
                      )}
                    </span>
                    {step.label}
                  </li>
                )
              })}
            </ul>

            <div className="co-meta">
              <div>
                <small>Service</small>
                <strong>{order.service_type}</strong>
              </div>
              <div>
                <small>Weight</small>
                <strong>{formatWeight(order.weight)}</strong>
              </div>
              <div>
                <small>Amount to Pay</small>
                {order.price > 0 ? (
                  order.balance > 0 ? (
                    <strong className="text-due">{formatPeso(order.balance)}</strong>
                  ) : (
                    <strong className="text-paid">Paid</strong>
                  )
                ) : (
                  <strong>To be computed</strong>
                )}
              </div>
              <div>
                <small>Pickup Schedule</small>
                <strong>
                  {order.pickup_date
                    ? `${formatDate(order.pickup_date)} · ${order.pickup_time}`
                    : order.estimated_pickup_date
                      ? `Estimated ${formatDate(order.estimated_pickup_date)}`
                      : 'Not scheduled yet'}
                </strong>
              </div>
            </div>
          </>
        )}
      </div>

      {/* Notifications */}
      <div className="card">
        <h2>Notifications</h2>
        {notifications.length === 0 ? (
          <p className="empty">You&apos;re all caught up!</p>
        ) : (
          <ul className="notif-list">
            {notifications.map((n, i) => (
              <li key={i}>
                <span className="material-symbols-outlined">{n.icon}</span>
                <span>{n.text}</span>
              </li>
            ))}
          </ul>
        )}
      </div>

      {/* Recent orders */}
      <div className="card">
        <div className="card-head">
          <h2>Recent Orders</h2>
          <Link to="/customer/orders" className="link-inline">View all</Link>
        </div>

        {recent.length === 0 ? (
          <p className="empty">You have no orders yet.</p>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Order Number</th>
                  <th>Service</th>
                  <th>Weight</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                {recent.map((o) => (
                  <tr key={o.order_number}>
                    <td><strong>{o.order_number}</strong></td>
                    <td>{o.service_type}</td>
                    <td>{formatWeight(o.weight)}</td>
                    <td><StatusBadge status={o.order_status} /></td>
                    <td>{formatDate(o.dropoff_date)}</td>
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