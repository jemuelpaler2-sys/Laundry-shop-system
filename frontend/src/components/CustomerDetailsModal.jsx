import { useEffect, useState } from 'react'
import { apiRequest } from '../services/api'
import StatusBadge from './StatusBadge'
import { formatPeso, formatDate, formatWeight } from '../utils/orderStatus'

// Pop-up with one customer's details, totals and latest orders
export default function CustomerDetailsModal({ customerId, onClose }) {
  const [data, setData] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true
    apiRequest(`customers/view.php?id=${customerId}`).then((result) => {
      if (!active) return
      if (result.success) {
        setData(result)
      } else {
        setError(result.message || 'Could not load the customer.')
      }
    })
    return () => {
      active = false
    }
  }, [customerId])

  useEffect(() => {
    function handleKey(e) {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', handleKey)
    return () => window.removeEventListener('keydown', handleKey)
  }, [onClose])

  const c = data?.customer

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div
        className="modal modal-wide"
        role="dialog"
        aria-modal="true"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="modal-head">
          <h2>{c ? `${c.first_name} ${c.last_name}` : 'Customer'}</h2>
          <button className="modal-close" onClick={onClose} aria-label="Close">
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>

        {error && <div className="alert-error">{error}</div>}
        {!data && !error && <p className="loading-text">Loading...</p>}

        {data && (
          <>
            <span className={`badge badge-${c.status}`}>
              {c.status === 'active' ? 'Active' : 'Inactive'}
            </span>

            <dl className="detail-list">
              <div className="detail-row"><dt>Customer ID</dt><dd>{c.customer_code}</dd></div>
              <div className="detail-row"><dt>Email</dt><dd>{c.email}</dd></div>
              <div className="detail-row"><dt>Phone</dt><dd>{c.phone}</dd></div>
              <div className="detail-row"><dt>Address</dt><dd>{c.address}</dd></div>
              <div className="detail-row"><dt>Registered</dt><dd>{formatDate(c.created_at)}</dd></div>
            </dl>

            <div className="mini-stats">
              <div className="mini-stat">
                <strong>{data.stats.order_count}</strong>
                <span>Orders</span>
              </div>
              <div className="mini-stat">
                <strong>{formatPeso(data.stats.total_billed)}</strong>
                <span>Total Billed</span>
              </div>
              <div className="mini-stat">
                <strong>{formatPeso(data.stats.total_paid)}</strong>
                <span>Total Paid</span>
              </div>
            </div>

            <h3>Recent Orders</h3>
            {data.recent_orders.length === 0 ? (
              <p className="empty">This customer has no orders yet.</p>
            ) : (
              <div className="table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>Order</th>
                      <th>Weight</th>
                      <th>Amount</th>
                      <th>Status</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.recent_orders.map((o) => (
                      <tr key={o.order_number}>
                        <td><strong>{o.order_number}</strong></td>
                        <td>{formatWeight(o.weight)}</td>
                        <td>{o.price > 0 ? formatPeso(o.price) : '—'}</td>
                        <td><StatusBadge status={o.order_status} /></td>
                        <td>{formatDate(o.dropoff_date)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  )
}