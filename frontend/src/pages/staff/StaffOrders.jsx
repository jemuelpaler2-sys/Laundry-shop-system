import { useEffect, useState } from 'react'
import { useOutletContext } from 'react-router-dom'
import { apiRequest } from '../../services/api'
import StatusBadge from '../../components/StatusBadge'
import OrderFormModal from '../../components/OrderFormModal'
import StaffOrderDetailsModal from '../../components/StaffOrderDetailsModal'
import { formatPeso, formatDate, formatWeight } from '../../utils/orderStatus'

const FILTERS = [
  { value: 'all', label: 'All orders' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'ready_for_pickup', label: 'Ready for pickup' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
]

export default function StaffOrders() {
  const { staff } = useOutletContext()
  const isOwner = staff.role === 'owner'

  const [orders, setOrders] = useState([])
  const [services, setServices] = useState({})
  const [limits, setLimits] = useState(undefined)
  const [total, setTotal] = useState(0)
  const [totalPages, setTotalPages] = useState(1)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState({ type: '', text: '' })
  const [reloadKey, setReloadKey] = useState(0)

  const [viewNumber, setViewNumber] = useState(null)
  // undefined = form closed, null = new order, object = editing that order
  const [formOrder, setFormOrder] = useState(undefined)

  // Load one page of orders (waits 300 ms while the user is still typing)
  useEffect(() => {
    const timer = setTimeout(async () => {
      const params = new URLSearchParams({
        search: search.trim(),
        status: statusFilter,
        page: String(page),
      })
      const result = await apiRequest(`orders/list.php?${params.toString()}`)
      if (result.success) {
        setOrders(result.orders)
        setServices(result.services)
        setLimits(result.limits)
        setTotal(result.total)
        setTotalPages(result.total_pages)
        setError('')
      } else {
        setError(result.message || 'Could not load orders.')
      }
      setLoading(false)
    }, 300)

    return () => clearTimeout(timer)
  }, [search, statusFilter, page, reloadKey])

  function handleSearchChange(e) {
    setSearch(e.target.value)
    setPage(1)
  }

  function handleStatusChange(e) {
    setStatusFilter(e.target.value)
    setPage(1)
  }

  function clearFilters() {
    setSearch('')
    setStatusFilter('all')
    setPage(1)
  }

  function handleSaved(message) {
    setFormOrder(undefined)
    setNotice({ type: 'success', text: message })
    setReloadKey((k) => k + 1)
  }

  async function handleCancel(o) {
    if (!window.confirm(`Cancel order ${o.order_number}? This cannot be undone.`)) return

    const result = await apiRequest('orders/cancel.php', {
      method: 'POST',
      body: { id: o.id },
    })

    if (result.success) {
      setNotice({ type: 'success', text: result.message })
      setReloadKey((k) => k + 1)
    } else {
      setNotice({ type: 'error', text: result.message || 'Could not cancel the order.' })
    }
  }

  return (
    <div>
      <h1 className="page-title">Order Management</h1>
      <p className="page-sub">Create orders, record the weight, and keep track of every load.</p>

      {notice.text && (
        <div className={notice.type === 'success' ? 'alert-success' : 'alert-error'}>
          {notice.text}
        </div>
      )}
      {error && <div className="alert-error">{error}</div>}

      {/* Search, filter and new order */}
      <div className="card">
        <div className="finder finder-3">
          <div>
            <label htmlFor="ord-search">Search</label>
            <input
              id="ord-search"
              type="text"
              value={search}
              onChange={handleSearchChange}
              placeholder="Order number, customer name or Customer ID"
            />
          </div>

          <div>
            <label htmlFor="ord-status">Status</label>
            <select id="ord-status" value={statusFilter} onChange={handleStatusChange}>
              {FILTERS.map((f) => (
                <option key={f.value} value={f.value}>{f.label}</option>
              ))}
            </select>
          </div>

          <button className="btn btn-primary" onClick={() => setFormOrder(null)}>
            + New Order
          </button>
        </div>
        {(search || statusFilter !== 'all') && (
          <button className="btn-link" onClick={clearFilters}>Clear filters</button>
        )}
      </div>

      {/* Orders table */}
      <div className="card">
        {loading ? (
          <p className="loading-text">Loading orders...</p>
        ) : (
          <>
            <p className="muted result-count">
              {total} {total === 1 ? 'order' : 'orders'} found
            </p>

            {orders.length === 0 ? (
              <p className="empty">No orders match your search.</p>
            ) : (
              <div className="table-wrap">
                <table className="data-table orders-table">
                  <thead>
                    <tr>
                      <th>Order</th>
                      <th>Customer</th>
                      <th>Service</th>
                      <th>Weight</th>
                      <th>Amount</th>
                      <th>Status</th>
                      <th>Date</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {orders.map((o) => {
                      const open = !['completed', 'cancelled'].includes(o.order_status)
                      return (
                        <tr key={o.id}>
                          <td><strong>{o.order_number}</strong></td>
                          <td>
                            {o.customer_name}
                            <div className="muted">{o.customer_code}</div>
                          </td>
                          <td>{o.service_type}</td>
                          <td>{formatWeight(o.weight)}</td>
                          <td>{o.price > 0 ? formatPeso(o.price) : '—'}</td>
                          <td><StatusBadge status={o.order_status} /></td>
                          <td>{formatDate(o.dropoff_date)}</td>
                          <td>
                            <div className="row-actions">
                              <button className="btn-link" onClick={() => setViewNumber(o.order_number)}>
                                View
                              </button>
                              {open && (
                                <button className="btn-link" onClick={() => setFormOrder(o)}>
                                  Edit
                                </button>
                              )}
                              {open && isOwner && (
                                <button className="btn-link btn-link-danger" onClick={() => handleCancel(o)}>
                                  Cancel
                                </button>
                              )}
                            </div>
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            )}

            {totalPages > 1 && (
              <div className="pager">
                <button onClick={() => setPage(page - 1)} disabled={page <= 1}>
                  ← Previous
                </button>
                <span>Page {page} of {totalPages}</span>
                <button onClick={() => setPage(page + 1)} disabled={page >= totalPages}>
                  Next →
                </button>
              </div>
            )}
          </>
        )}
      </div>

      {viewNumber && (
        <StaffOrderDetailsModal orderNumber={viewNumber} onClose={() => setViewNumber(null)} />
      )}

      {formOrder !== undefined && (
        <OrderFormModal
          order={formOrder}
          services={services}
          limits={limits}
          onClose={() => setFormOrder(undefined)}
          onSaved={handleSaved}
        />
      )}
    </div>
  )
}