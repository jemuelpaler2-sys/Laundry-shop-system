import { useEffect, useState } from 'react'
import { apiRequest } from '../../services/api'
import { formatPeso, formatDate } from '../../utils/orderStatus'
import PayOnlineButton from '../../components/PayOnlineButton'

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

// Banner shown after returning from PayMongo (?paid= / ?cancelled=).
function ReturnBanner({ paidOrder, cancelledOrder, error, onDismiss }) {
  if (error) {
    return (
      <div className="alert-error pay-return-banner">
        {error}
        <button className="banner-dismiss" onClick={onDismiss} aria-label="Dismiss"><span className="material-symbols-outlined">close</span></button>
      </div>
    )
  }
  if (paidOrder) {
    return (
      <div className="alert-success pay-return-banner">
        Payment received for <strong>{paidOrder}</strong>. Your receipt has been
        emailed to you and your balance is updated.
        <button className="banner-dismiss" onClick={onDismiss} aria-label="Dismiss"><span className="material-symbols-outlined">close</span></button>
      </div>
    )
  }
  if (cancelledOrder) {
    return (
      <div className="info-note pay-return-banner">
        The online payment for <strong>{cancelledOrder}</strong> was cancelled.
        No payment was recorded.
        <button className="banner-dismiss" onClick={onDismiss} aria-label="Dismiss"><span className="material-symbols-outlined">close</span></button>
      </div>
    )
  }
  return null
}

export default function CustomerPayments() {
  const [data, setData] = useState(null)
  const [error, setError] = useState('')

  // Read the return URL state from PayMongo / the local simulator.
  const params = new URLSearchParams(window.location.search)
  const paidOrder = params.get('paid')
  const cancelledOrder = params.get('cancelled')
  const returnError = params.get('error')
  const [bannerDismissed, setBannerDismissed] = useState(false)

  function load() {
    apiRequest('customer/payments.php').then((result) => {
      if (result.success) {
        setData(result)
      } else {
        setError(result.message || 'Could not load your payments.')
      }
    })
  }

  useEffect(() => {
    load()
  }, [])

  // While any order has an online payment still pending, poll its status so the
  // page updates on its own once PayMongo confirms the payment.
  const pendingOrderNumbers = (data?.orders || [])
    .filter((o) => o.online_status === 'pending')
    .map((o) => o.order_number)
  const pendingKey = pendingOrderNumbers.join(',')

  useEffect(() => {
    if (pendingKey === '') return undefined
    const numbers = pendingKey.split(',')
    const timer = setInterval(() => {
      Promise.all(
        numbers.map((orderNumber) =>
          apiRequest(
            `payments/paymongo/status.php?order_number=${encodeURIComponent(orderNumber)}`
          ).then((r) => r?.online_payment?.status || 'pending')
        )
      ).then((statuses) => {
        if (statuses.some((s) => s !== 'pending')) load()
      })
    }, 6000)
    return () => clearInterval(timer)
  }, [pendingKey])

  if (error) return <div className="alert-error">{error}</div>
  if (!data) return <p className="loading-text">Loading your payments...</p>

  const { summary, orders, history } = data

  const summaryCards = [
    { icon: 'receipt_long', value: formatPeso(summary.total_amount), label: 'Total Amount' },
    { icon: 'check_circle', value: formatPeso(summary.amount_paid), label: 'Amount Paid' },
    { icon: 'pending', value: formatPeso(summary.remaining_balance), label: 'Remaining Balance' },
  ]

  return (
    <div>
      <h1 className="page-title">Payments</h1>
      <p className="page-sub">See what you have paid and what is still due.</p>

      {!bannerDismissed && (
        <ReturnBanner
          paidOrder={paidOrder}
          cancelledOrder={cancelledOrder}
          error={returnError}
          onDismiss={() => setBannerDismissed(true)}
        />
      )}

      <div className="info-note">
        You can pay your balance online via PayMongo (GCash, card, Maya, and more).
        After you pay, your receipt is emailed to you automatically. Cash, GCash,
        and other in-store payments are still recorded by the shop.
      </div>

      {/* Summary cards */}
      <div className="stat-grid stat-grid-3">
        {summaryCards.map((card) => (
          <div key={card.label} className="stat-card">
            <div className="stat-icon">
              <span className="material-symbols-outlined">{card.icon}</span>
            </div>
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
                  <th></th>
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
                    <td>
                      <PaymentBadge status={o.payment_status} />
                      {o.online_status === 'pending' && (
                        <span className="badge badge-online-pending" title="Waiting for PayMongo confirmation">
                          Online pending
                        </span>
                      )}
                    </td>
                    <td>{o.payment_method || '—'}</td>
                    <td>{o.payment_date ? formatDate(o.payment_date) : '—'}</td>
                    <td>
                      {o.price > 0 && o.balance > 0 && o.online_status !== 'pending' && (
                        <PayOnlineButton
                          orderNumber={o.order_number}
                          onStatusChange={load}
                        />
                      )}
                    </td>
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
