import { useEffect, useState } from 'react'
import { apiRequest } from '../../services/api'
import StatusBadge from '../../components/StatusBadge'
import OrderDetailsModal from '../../components/OrderDetailsModal'
import { formatPeso, formatDate, formatWeight } from '../../utils/orderStatus'

const FILTERS = [
  { value: 'all', label: 'All orders' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'ready_for_pickup', label: 'Ready for pickup' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
]

const IN_PROGRESS = ['dropoff', 'washing', 'drying', 'folding']

function matchesFilter(status, filter) {
  if (filter === 'all') return true
  if (filter === 'in_progress') return IN_PROGRESS.includes(status)
  return status === filter
}

export default function CustomerOrders() {
  const [orders, setOrders] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [selected, setSelected] = useState(null) // order number shown in the pop-up

  useEffect(() => {
    apiRequest('customer/orders.php').then((result) => {
      if (result.success) {
        setOrders(result.orders)
      } else {
        setError(result.message || 'Could not load your orders.')
      }
      setLoading(false)
    })
  }, [])

  const term = search.trim().toLowerCase()
  const filtered = orders.filter(
    (o) =>
      matchesFilter(o.order_status, statusFilter) &&
      (term === '' ||
        o.order_number.toLowerCase().includes(term) ||
        o.service_type.toLowerCase().includes(term))
  )

  function clearFilters() {
    setSearch('')
    setStatusFilter('all')
  }

  if (loading) return <p className="loading-text">Loading your orders...</p>

  return (
    <div>
      <h1 className="page-title">My Orders</h1>
      <p className="page-sub">Your laundry order history.</p>

      {error && <div className="alert-error">{error}</div>}

      {/* Search and filter */}
      <div className="card">
        <div className="finder finder-3">
          <div>
            <label htmlFor="order-search">Search</label>
            <input
              id="order-search"
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Order number or service"
            />
          </div>

          <div>
            <label htmlFor="status-filter">Status</label>
            <select
              id="status-filter"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
            >
              {FILTERS.map((f) => (
                <option key={f.value} value={f.value}>
                  {f.label}
                </option>
              ))}
            </select>
          </div>

          <button className="btn btn-outline" onClick={clearFilters}>
            Clear
          </button>
        </div>
      </div>

      {/* Orders table */}
      <div className="card">
        <p className="muted result-count">
          Showing {filtered.length} of {orders.length} orders
        </p>

        {orders.length === 0 ? (
          <p className="empty">You have no orders yet.</p>
        ) : filtered.length === 0 ? (
          <p className="empty">No orders match your search.</p>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Order Number</th>
                  <th>Service</th>
                  <th>Weight</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {filtered.map((o) => (
                  <tr key={o.order_number}>
                    <td><strong>{o.order_number}</strong></td>
                    <td>{o.service_type}</td>
                    <td>{formatWeight(o.weight)}</td>
                    <td>{o.price > 0 ? formatPeso(o.price) : 'To be computed'}</td>
                    <td><StatusBadge status={o.order_status} /></td>
                    <td>{formatDate(o.dropoff_date)}</td>
                    <td>
                      <button
                        className="btn-link"
                        onClick={() => setSelected(o.order_number)}
                      >
                        View Details
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {selected && (
        <OrderDetailsModal orderNumber={selected} onClose={() => setSelected(null)} />
      )}
    </div>
  )
}