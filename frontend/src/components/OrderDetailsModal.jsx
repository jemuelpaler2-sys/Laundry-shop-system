import { useEffect, useState } from 'react'
import { apiRequest } from '../services/api'
import StatusBadge from './StatusBadge'
import OrderTracker from './OrderTracker'
import PayOnlineButton from './PayOnlineButton'
import { formatPeso, formatDate, formatWeight, getPaymentText } from '../utils/orderStatus'

// Pop-up with the full details of one order
export default function OrderDetailsModal({ orderNumber, onClose, onViewReceipt }) {
  const [order, setOrder] = useState(null)
  const [error, setError] = useState('')

  // Load the order (the PHP only returns it if it belongs to this customer)
  useEffect(() => {
    let active = true
    apiRequest(`customer/order-status.php?order_number=${encodeURIComponent(orderNumber)}`).then(
      (result) => {
        if (!active) return
        if (result.success && result.order) {
          setOrder(result.order)
        } else {
          setError(result.message || 'Could not load this order.')
        }
      }
    )
    return () => {
      active = false
    }
  }, [orderNumber])

  // Close with the Esc key
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
        className="modal"
        role="dialog"
        aria-modal="true"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="modal-head">
          <h2>Order #{orderNumber}</h2>
          <button className="modal-close" onClick={onClose} aria-label="Close">
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>

        {error && <div className="alert-error">{error}</div>}
        {!order && !error && <p className="loading-text">Loading...</p>}

        {order && (
          <>
            <StatusBadge status={order.order_status} />

            {onViewReceipt && (
              <div className="modal-quick-row">
                <button className="btn-link" onClick={() => onViewReceipt(order.order_number)}>
                  <span className="material-symbols-outlined">arrow_forward</span> View Receipt
                </button>
              </div>
            )}

            <dl className="detail-list">
              <div className="detail-row">
                <dt>Service</dt>
                <dd>{order.service_type}</dd>
              </div>
              <div className="detail-row">
                <dt>Weight</dt>
                <dd>{formatWeight(order.weight)}</dd>
              </div>
              <div className="detail-row">
                <dt>Drop-off Date</dt>
                <dd>{formatDate(order.dropoff_date)}</dd>
              </div>
              <div className="detail-row">
                <dt>Estimated Pickup</dt>
                <dd>{formatDate(order.estimated_pickup_date)}</dd>
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
                  <dt>Picked Up On</dt>
                  <dd>{formatDate(order.actual_pickup_date)}</dd>
                </div>
              )}
              <div className="detail-row">
                <dt>Total Amount</dt>
                <dd>{order.price > 0 ? formatPeso(order.price) : 'To be computed'}</dd>
              </div>
              <div className="detail-row">
                <dt>Amount Paid</dt>
                <dd>{formatPeso(order.amount_paid)}</dd>
              </div>
              <div className="detail-row">
                <dt>Payment Status</dt>
                <dd>{getPaymentText(order)}</dd>
              </div>

              {order.balance > 0 && order.order_status !== 'cancelled' && (
                <div className="detail-row">
                  <dt>Pay Online</dt>
                  <dd>
                    <PayOnlineButton
                      orderNumber={order.order_number}
                      onStatusChange={() => {
                        apiRequest(
                          `customer/order-status.php?order_number=${encodeURIComponent(orderNumber)}`
                        ).then((r) => {
                          if (r.success && r.order) setOrder(r.order)
                        })
                      }}
                    />
                  </dd>
                </div>
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