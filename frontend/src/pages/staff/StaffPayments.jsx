import { useEffect, useState } from 'react'
import { apiRequest } from '../../services/api'
import PaymentFormModal from '../../components/PaymentFormModal'
import PaymentHistoryModal from '../../components/PaymentHistoryModal'
import { formatPeso, formatDate } from '../../utils/orderStatus'

const FILTERS = [
  { value: 'all', label: 'All' },
  { value: 'unpaid', label: 'Unpaid' },
  { value: 'partial', label: 'Partially Paid' },
  { value: 'paid', label: 'Paid' },
]

const PAY_LABELS = {
  paid: 'Paid',
  partial: 'Partially Paid',
  unpaid: 'Unpaid',
  pending: 'No price yet',
}

function PayBadge({ status }) {
  return <span className={`badge badge-${status}`}>{PAY_LABELS[status] || status}</span>
}

export default function StaffPayments() {
  const [orders, setOrders] = useState([])
  const [summary, setSummary] = useState({ total_billed: 0, total_paid: 0, outstanding: 0 })
  const [total, setTotal] = useState(0)
  const [totalPages, setTotalPages] = useState(1)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState({ type: '', text: '' })
  const [reloadKey, setReloadKey] = useState(0)

  const [payOrder, setPayOrder] = useState(null)
  const [historyId, setHistoryId] = useState(null)

  useEffect(() => {
    const timer = setTimeout(async () => {
      const params = new URLSearchParams({
        search: search.trim(),
        status: statusFilter,
        page: String(page),
      })
      const result = await apiRequest(`payments/list.php?${params.toString()}`)
      if (result.success) {
        setOrders(result.orders)
        setSummary(result.summary)
        setTotal(result.total)
        setTotalPages(result.total_pages)
        setError('')
      } else {
        setError(result.message || 'Could not load payments.')
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
    setPayOrder(null)
    setNotice({ type: 'success', text: message })
    setReloadKey((k) => k + 1)
  }

  const summaryCards = [
    { icon: '🧾', value: formatPeso(summary.total_billed), label: 'Total Billed' },
    { icon: '✅', value: formatPeso(summary.total_paid), label: 'Total Collected' },
    { icon: '⏳', value: formatPeso(summary.outstanding), label: 'Outstanding Balance' },
  ]

  return (
    <div>
      <h1 className="page-title">Payment Management</h1>
      <p className="page-sub">Record payments and see what customers still owe.</p>

      {notice.text && (
        <div className={notice.type === 'success' ? 'alert-success' : 'alert-error'}>
          {notice.text}
        </div>
      )}
      {error && <div className="alert-error">{error}</div>}

      {/* Summary cards */}
      <div className="stat-grid stat-grid-3">
        {summaryCards.map((card) => (
          <div key={card.label} className="stat-card">
            <div className="stat-icon">{card.icon}</div>
            <div>
              <div className="stat-value stat-value-money">{card.value}</div>
              <div className="stat-label">{card.label}</div>
            </div>
          </div>
        ))}
      </div>

      {/* Search and filter */}
      <div className="card">
        <div className="finder finder-3">
          <div>
            <label htmlFor="pay-search">Search</label>
            <input
              id="pay-search"
              type="text"
              value={search}
              onChange={handleSearchChange}
              placeholder="Order number, customer name or Customer ID"
            />
          </div>

          <div>
            <label htmlFor="pay-status">Payment Status</label>
            <select id="pay-status" value={statusFilter} onChange={handleStatusChange}>
              {FILTERS.map((f) => (
                <option key={f.value} value={f.value}>{f.label}</option>
              ))}
            </select>
          </div>

          <button className="btn btn-outline" onClick={clearFilters}>Clear</button>
        </div>
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
                <table className="data-table pay-table">
                  <thead>
                    <tr>
                      <th>Order</th>
                      <th>Customer</th>
                      <th>Total</th>
                      <th>Paid</th>
                      <th>Balance</th>
                      <th>Status</th>
                      <th>Date</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {orders.map((o) => (
                      <tr key={o.id}>
                        <td><strong>{o.order_number}</strong></td>
                        <td>{o.customer_name} <div className="muted">{o.customer_code}</div></td>
                        <td>{o.price > 0 ? formatPeso(o.price) : '—'}</td>
                        <td>{formatPeso(o.amount_paid)}</td>
                        <td>
                          {o.price > 0 ? (
                            <span className={o.balance > 0 ? 'amount-due' : 'amount-ok'}>
                              {formatPeso(o.balance)}
                            </span>
                          ) : '—'}
                        </td>
                        <td><PayBadge status={o.payment_status} /></td>
                        <td>{formatDate(o.dropoff_date)}</td>
                        <td>
                          <div className="row-actions">
                            {o.balance > 0 && o.price > 0 && (
                              <button className="btn-link" onClick={() => setPayOrder(o)}>
                                Record Payment
                              </button>
                            )}
                            <button className="btn-link" onClick={() => setHistoryId(o.id)}>
                              View Payments
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {totalPages > 1 && (
              <div className="pager">
                <button onClick={() => setPage(page - 1)} disabled={page <= 1}>← Previous</button>
                <span>Page {page} of {totalPages}</span>
                <button onClick={() => setPage(page + 1)} disabled={page >= totalPages}>Next →</button>
              </div>
            )}
          </>
        )}
      </div>

      {payOrder && (
        <PaymentFormModal order={payOrder} onClose={() => setPayOrder(null)} onSaved={handleSaved} />
      )}

      {historyId && (
        <PaymentHistoryModal orderId={historyId} onClose={() => setHistoryId(null)} />
      )}
    </div>
  )
}