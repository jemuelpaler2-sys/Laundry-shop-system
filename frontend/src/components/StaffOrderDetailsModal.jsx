import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { apiRequest } from '../services/api'
import StatusBadge from './StatusBadge'
import OrderTracker from './OrderTracker'
import { formatPeso, formatDate, formatWeight, getPaymentText } from '../utils/orderStatus'
// Pop-up with the full details of one order (staff view)
export default function StaffOrderDetailsModal({ orderNumber, onClose }) {
  const [order, setOrder] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true
    apiRequest(`orders/view.php?order_number=${encodeURIComponent(orderNumber)}`).then((result) => {
      if (!active) return
      if (result.success) {
        setOrder(result.order)
      } else {
        setError(result.message || 'Could not load the order.')
      }
    })
    return () => {
      active = false
    }
  }, [orderNumber])

  useEffect(() => {
    function handleKey(e) {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', handleKey)
    return () => window.removeEventListener('keydown', handleKey)
  }, [onClose])

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div
        className="modal modal-wide"
        role="dialog"
        aria-modal="true"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="modal-head">
          <h2>Order #{orderNumber}</h2>
          <button className="modal-close" onClick={onClose} aria-label="Close">✕</button>
        </div>

        {error && <div className="alert-error">{error}</div>}
        {!order && !error && <p className="loading-text">Loading...</p>}

        {order && (
          <>
            <div className="modal-quick-row">
              <StatusBadge status={order.order_status} />
              {!['completed', 'cancelled'].includes(order.order_status) && (
                <>
                  <Link to="/staff/load-status" className="btn-link">→ Load Status</Link>
                  <Link to="/staff/pickups" className="btn-link">→ Schedule Pickup</Link>
                  {order.balance > 0 && (
                    <Link to="/staff/payments" className="btn-link">→ Record Payment</Link>
                  )}
                </>
              )}
            </div>

            <dl className="detail-list">
              <div className="detail-row">
                <dt>Customer</dt>
                <dd>{order.customer_name} ({order.customer_code})</dd>
              </div>
              <div className="detail-row"><dt>Phone</dt><dd>{order.phone}</dd></div>
              <div className="detail-row"><dt>Service</dt><dd>{order.service_type}</dd></div>
              <div className="detail-row"><dt>Weight</dt><dd>{formatWeight(order.weight)}</dd></div>
              <div className="detail-row">
                <dt>Drop-off Date</dt><dd>{formatDate(order.dropoff_date)}</dd>
              </div>
              <div className="detail-row">
                <dt>Estimated Pickup</dt><dd>{formatDate(order.estimated_pickup_date)}</dd>
              </div>
              <div className="detail-row">
                <dt>Pickup Schedule</dt>
                <dd>
                  {order.pickup_date
                    ? `${formatDate(order.pickup_date)} · ${order.pickup_time}`
                    : 'Not scheduled yet'}
                </dd>
              </div>
              {order.actual_pickup_date && (
                <div className="detail-row">
                  <dt>Picked Up On</dt><dd>{formatDate(order.actual_pickup_date)}</dd>
                </div>
              )}
              <div className="detail-row">
                <dt>Total Amount</dt>
                <dd>{order.price > 0 ? formatPeso(order.price) : 'To be computed'}</dd>
              </div>
              <div className="detail-row">
                <dt>Amount Paid</dt><dd>{formatPeso(order.amount_paid)}</dd>
              </div>
              <div className="detail-row">
                <dt>Payment Status</dt><dd>{getPaymentText(order)}</dd>
              </div>
              {order.notes && (
                <div className="detail-row"><dt>Notes</dt><dd>{order.notes}</dd></div>
              )}
            </dl>

            <h3>Progress</h3>
            {order.order_status === 'cancelled' ? (
              <div className="cancel-note">This order was cancelled.</div>
            ) : (
              <OrderTracker status={order.order_status} />
            )}
          </>
        )}
      </div>
    </div>
  )
}