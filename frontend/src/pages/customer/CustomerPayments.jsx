import { useEffect, useState } from 'react'
import { apiRequest } from '../../services/api'
import { formatPeso, formatDate } from '../../utils/orderStatus'

const PAYMENT_LABELS = {
  paid: 'Paid',
  partial: 'Partially Paid',
  unpaid: 'Unpaid',
  pending: 'To be computed',
}

function PaymentBadge({ status }) {
  return (
    <span className={`badge badge-${status}`}>
      {PAYMENT_LABELS[status] || status}
    </span>
  )
}

export default function CustomerPayments() {
  const [data, setData] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    apiRequest('customer/payments.php').then((result) => {
      if (result.success) {
        setData(result)
      } else {
        setError(result.message || 'Could not load your payments.')
      }
    })
  }, [])

  if (error) return <div className="alert-error">{error}</div>
  if (!data) return <p className="loading-text">Loading your payments...</p>

  const { summary, orders, history } = data

  const summaryCards = [
    { icon: '🧾', value: formatPeso(summary.total_amount), label: 'Total Amount' },
    { icon: '✅', value: formatPeso(summary.amount_paid), label: 'Amount Paid' },
    { icon: '⏳', value: formatPeso(summary.remaining_balance), label: 'Remaining Balance' },
  ]

  return (
    <div>
      <h1 className="page-title">Payments</h1>
      <p className="page-sub">See what you have paid and what is still due.</p>

      <div className="info-note">
        Payments are recorded by the shop when you pay (Cash, GCash, or other).
        Your balance updates as soon as the shop records your payment.
      </div>

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

      {/* Payment by order */}
      <div className="card">
        <h2>Payment by Order</h2>

        {orders.length === 0 ? (
          <p className="empty">You have no orders yet.</p>
        ) : (
          <div className="table-wrap">
            <table className="data-table pay-table">
              <thead>
                <tr>
                  <th>Order Number</th>
                  <th>Total</th>
                  <th>Paid</th>
                  <th>Balance</th>
                  <th>Status</th>
                  <th>Method</th>
                  <th>Payment Date</th>
                </tr>
              </thead>
              <tbody>
                {orders.map((o) => (
                  <tr key={o.order_number}>
                    <td><strong>{o.order_number}</strong></td>
                    <td>{o.price > 0 ? formatPeso(o.price) : 'To be computed'}</td>
                    <td>{formatPeso(o.amount_paid)}</td>
                    <td>
                      {o.price > 0 ? (
                        <span className={o.balance > 0 ? 'amount-due' : 'amount-ok'}>
                          {formatPeso(o.balance)}
                        </span>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td><PaymentBadge status={o.payment_status} /></td>
                    <td>{o.payment_method || '—'}</td>
                    <td>{o.payment_date ? formatDate(o.payment_date) : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Payment history */}
      <div className="card">
        <h2>Payment History</h2>

        {history.length === 0 ? (
          <p className="empty">No payments have been recorded yet.</p>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Order</th>
                  <th>Amount</th>
                  <th>Method</th>
                </tr>
              </thead>
              <tbody>
                {history.map((h, index) => (
                  <tr key={`${h.order_number}-${index}`}>
                    <td>{formatDate(h.payment_date)}</td>
                    <td><strong>{h.order_number}</strong></td>
                    <td>{formatPeso(h.amount)}</td>
                    <td>{h.payment_method}</td>
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