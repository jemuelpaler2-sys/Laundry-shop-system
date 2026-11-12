import { useEffect, useState } from 'react'
import { apiRequest } from '../../services/api'
import StatusBadge from '../../components/StatusBadge'
import OrderTracker from '../../components/OrderTracker'
import {
  STATUS_LABELS,
  STATUS_DESCRIPTIONS,
  STATUS_ICONS,
  formatPeso,
  formatDate,
  formatWeight,
} from '../../utils/orderStatus'

function paymentText(order) {
  if (order.price <= 0) return 'To be computed'
  if (order.balance <= 0) return 'Paid'
  if (order.amount_paid > 0) return 'Partially paid'
  return 'Unpaid'
}

export default function CustomerOrderStatus() {
  const [orders, setOrders] = useState([])
  const [order, setOrder] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [search, setSearch] = useState('')
  const [searchMessage, setSearchMessage] = useState('')

  async function loadOrder(orderNumber = '') {
    setError('')
    const path = orderNumber
      ? `customer/order-status.php?order_number=${encodeURIComponent(orderNumber)}`
      : 'customer/order-status.php'

    const result = await apiRequest(path)
    if (result.success) {
      setOrders(result.orders)
      setOrder(result.order)
    } else {
      setError(result.message || 'Could not load your order.')
    }
    setLoading(false)
  }

  useEffect(() => {
    loadOrder()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  function handleSearch(e) {
    e.preventDefault()
    setSearchMessage('')

    const term = search.trim().toLowerCase()
    if (!term) return

    // Search only inside this customer's own orders
    const match = orders.find((o) => o.order_number.toLowerCase().includes(term))
    if (match) {
      loadOrder(match.order_number)
      setSearch('')
    } else {
      setSearchMessage(`No order matching "${search.trim()}" was found in your orders.`)
    }
  }

  if (loading) return <p className="loading-text">Loading your order...</p>

  return (
    <div>
      <h1 className="page-title">Order Status</h1>
      <p className="page-sub">See exactly where your laundry is right now.</p>

      {error && <div className="alert-error">{error}</div>}

      {orders.length === 0 ? (
        !error && <div className="card"><p className="empty">You have no orders yet.</p></div>
      ) : (
        <>
          {/* Find an order */}
          <div className="card">
            <div className="finder">
              <div>
                <label htmlFor="order-select">Select an order</label>
                <select
                  id="order-select"
                  value={order ? order.order_number : ''}
                  onChange={(e) => loadOrder(e.target.value)}
                >
                  {orders.map((o) => (
                    <option key={o.order_number} value={o.order_number}>
                      {o.order_number} · {STATUS_LABELS[o.order_status]}
                    </option>
                  ))}
                </select>
              </div>

              <form onSubmit={handleSearch}>
                <label htmlFor="order-search">Or search by order number</label>
                <div className="search-row">
                  <input
                    id="order-search"
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="e.g. ORD-1005 or 1005"
                  />
                  <button type="submit" className="btn btn-primary">Search</button>
                </div>
              </form>
            </div>
            {searchMessage && <p className="search-msg">{searchMessage}</p>}
          </div>

          {order && (
            <>
              {/* Big status banner */}
              <div className="status-hero">
                <div className="status-hero-icon">
                  <span className="material-symbols-outlined">
                    {STATUS_ICONS[order.order_status]}
                  </span>
                </div>
                <div>
                  <small>Order #{order.order_number} · Current status</small>
                  <h2>{STATUS_LABELS[order.order_status]}</h2>
                  <p>{STATUS_DESCRIPTIONS[order.order_status]}</p>
                </div>
              </div>

              <div className="status-grid">
                {/* Progress tracker */}
                <div className="card">
                  <h2>Progress</h2>
                  {order.order_status === 'cancelled' ? (
                    <div className="cancel-note">
                      This order was cancelled. Please contact the shop if you have questions.
                    </div>
                  ) : (
                    <OrderTracker status={order.order_status} />
                  )}
                </div>

                {/* Order details */}
                <div className="card">
                  <div className="card-head">
                    <h2>Order Details</h2>
                    <StatusBadge status={order.order_status} />
                  </div>

                  <dl className="detail-list">
                    <div className="detail-row">
                      <dt>Order Number</dt>
                      <dd>{order.order_number}</dd>
                    </div>
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
                      <dd>{paymentText(order)}</dd>
                    </div>
                  </dl>
                </div>
              </div>
            </>
          )}
        </>
      )}
    </div>
  )
}