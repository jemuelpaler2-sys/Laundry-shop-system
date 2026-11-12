import { useEffect, useState } from 'react'
import { Link, useOutletContext } from 'react-router-dom'
import { apiRequest } from '../../services/api'
import StatusBadge from '../../components/StatusBadge'
import { formatDate, formatWeight } from '../../utils/orderStatus'

export default function StaffDashboard() {
  const { staff } = useOutletContext()
  const [data, setData] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    apiRequest('staff/dashboard.php').then((result) => {
      if (result.success) {
        setData(result)
      } else {
        setError(result.message || 'Could not load the dashboard.')
      }
    })
  }, [])

  if (error) return <div className="alert-error">{error}</div>
  if (!data) return <p className="loading-text">Loading the dashboard...</p>

  const { stats, recent_orders: recent } = data

  const statCards = [
    { icon: 'groups', value: stats.total_customers, label: 'Total Customers' },
    { icon: 'local_laundry_service', value: stats.active_orders, label: 'Active Orders' },
    { icon: 'inventory_2', value: stats.ready_for_pickup, label: 'Ready for Pickup' },
  ]

  return (
    <div>
      <h1 className="page-title">Welcome, {staff.first_name}!</h1>
      <p className="page-sub">Here is what is happening in the shop today.</p>

      <div className="stat-grid stat-grid-3">
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

      <div className="card">
        <div className="card-head">
          <h2>Recent Orders</h2>
          <Link to="/staff/orders" className="link-inline">View all</Link>
        </div>

        {recent.length === 0 ? (
          <p className="empty">No orders yet.</p>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Customer</th>
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
                    <td>{o.customer_name}</td>
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