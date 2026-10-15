import { useEffect, useState } from 'react'
import { apiRequest, API_BASE } from '../services/api'
import { formatPeso, formatDateTime } from '../utils/orderStatus'

// Full receipt view. Works for staff (any order, can email) and for a customer
// (their own order only, no email action). Printing uses the browser dialog
// with a receipt-only layout defined in index.css.
export default function ReceiptModal({ orderNumber, canEmail = false, onClose }) {
  const [receipt, setReceipt] = useState(null)
  const [error, setError] = useState('')

  const [emailOpen, setEmailOpen] = useState(false)
  const [recipient, setRecipient] = useState('')
  const [sending, setSending] = useState(false)
  const [notice, setNotice] = useState({ type: '', text: '' })

  useEffect(() => {
    let active = true
    apiRequest(`receipts/view.php?order_number=${encodeURIComponent(orderNumber)}`).then(
      (result) => {
        if (!active) return
        if (result.success) {
          setReceipt(result.receipt)
          setRecipient(result.receipt.customer.email || '')
        } else {
          setError(result.message || 'Could not load the receipt.')
        }
      }
    )
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

  function handlePrint() {
    window.print()
  }

  function handleDownloadPdf() {
    const url = `${API_BASE}/receipts/pdf.php?order_number=${encodeURIComponent(orderNumber)}&download=1`
    window.open(url, '_blank', 'noopener')
  }

  async function handleSend() {
    setNotice({ type: '', text: '' })

    if (!/^\S+@\S+\.\S+$/.test(recipient.trim())) {
      setNotice({ type: 'error', text: 'Please enter a valid recipient email address.' })
      return
    }

    setSending(true)
    const result = await apiRequest('receipts/send.php', {
      method: 'POST',
      body: { order_number: orderNumber, email: recipient.trim() },
    })
    setSending(false)

    if (result.success) {
      setNotice({ type: 'success', text: result.message })
      setEmailOpen(false)
    } else {
      setNotice({ type: 'error', text: result.message || 'Could not send the receipt.' })
    }
  }

  const order = receipt?.order
  const payment = receipt?.payment
  const customer = receipt?.customer
  const shop = receipt?.shop

  const weightText =
    order && Number(order.weight) > 0 ? `${Number(order.weight)} kg` : 'Not weighed yet'

  const hasTxRef = Boolean(receipt?.transactions?.some((t) => t.reference))

  return (
    <div className="modal-backdrop receipt-backdrop" onClick={onClose}>
      <div
        className="modal modal-wide receipt-modal"
        role="dialog"
        aria-modal="true"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="modal-head receipt-no-print">
          <h2>Receipt {receipt ? `· ${receipt.receipt_number}` : ''}</h2>
          <button className="modal-close" onClick={onClose} aria-label="Close">✕</button>
        </div>

        {error && <div className="alert-error receipt-no-print">{error}</div>}
        {!receipt && !error && <p className="loading-text receipt-no-print">Loading receipt...</p>}

        {notice.text && (
          <div className={`${notice.type === 'success' ? 'alert-success' : 'alert-error'} receipt-no-print`}>
            {notice.text}
          </div>
        )}

        {receipt && (
          <>
            <div id="receipt-print" className="receipt-doc">
              <div className="receipt-head">
                <div className="receipt-shop">
                  <div className="receipt-shop-name">{shop.name}</div>
                  <div className="receipt-shop-line">
                    {[shop.address, shop.phone, shop.email].filter(Boolean).join(' • ')}
                  </div>
                </div>
                <div className="receipt-head-right">
                  <div className="receipt-label">RECEIPT</div>
                  <div className="receipt-shop-line">No. <strong>{receipt.receipt_number}</strong></div>
                  <div className="receipt-shop-line">Issued: {formatDateTime(receipt.issued_at)}</div>
                </div>
              </div>

              <hr className="receipt-rule" />

              <div className="receipt-meta">
                <div>
                  <div className="receipt-label">Bill To</div>
                  <div className="receipt-strong">{customer.name}</div>
                  <div className="receipt-shop-line">Customer ID: {customer.code}</div>
                  <div className="receipt-shop-line">{customer.email}</div>
                  <div className="receipt-shop-line">{customer.phone}</div>
                </div>
                <div className="receipt-meta-right">
                  <div className="receipt-label">Order Details</div>
                  <div className="receipt-shop-line">Order ID: <strong>{order.number}</strong></div>
                  <div className="receipt-shop-line">Order Date: {formatDateTime(order.date)}</div>
                </div>
              </div>

              <hr className="receipt-rule" />

              <table className="receipt-table">
                <thead>
                  <tr>
                    <th>Service</th>
                    <th className="receipt-num">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      {order.service} — {weightText} × {formatPeso(order.rate)}/kg
                    </td>
                    <td className="receipt-num">
                      {order.total > 0 ? formatPeso(order.subtotal) : 'To be computed'}
                    </td>
                  </tr>
                </tbody>
              </table>

              <table className="receipt-totals">
                <tbody>
                  <tr>
                    <td className="receipt-tot-label">Subtotal</td>
                    <td className="receipt-num">{order.total > 0 ? formatPeso(order.subtotal) : '—'}</td>
                  </tr>
                  <tr>
                    <td className="receipt-tot-label">Discount</td>
                    <td className="receipt-num">{formatPeso(order.discount)}</td>
                  </tr>
                  <tr className="receipt-tot-final">
                    <td>Final Total</td>
                    <td className="receipt-num">{order.total > 0 ? formatPeso(order.total) : 'To be computed'}</td>
                  </tr>
                </tbody>
              </table>

              <h3 className="receipt-subhead">Payment Details</h3>
              <table className="receipt-pay">
                <tbody>
                  <tr>
                    <td className="receipt-tot-label">Amount Paid</td>
                    <td className="receipt-num receipt-strong">{formatPeso(payment.amount_paid)}</td>
                  </tr>
                  <tr>
                    <td className="receipt-tot-label">Payment Method</td>
                    <td className="receipt-num">{payment.method || '—'}</td>
                  </tr>
                  {payment.reference && (
                    <tr>
                      <td className="receipt-tot-label">Transaction Reference</td>
                      <td className="receipt-num receipt-ref">{payment.reference}</td>
                    </tr>
                  )}
                  <tr>
                    <td className="receipt-tot-label">Payment Status</td>
                    <td className="receipt-num">{payment.status_label}</td>
                  </tr>
                  {order.total > 0 && (
                    <tr>
                      <td className="receipt-tot-label">Remaining Balance</td>
                      <td className="receipt-num receipt-strong">{formatPeso(payment.balance)}</td>
                    </tr>
                  )}
                  {payment.change > 0 && (
                    <tr>
                      <td className="receipt-tot-label">Change</td>
                      <td className="receipt-num">{formatPeso(payment.change)}</td>
                    </tr>
                  )}
                  {payment.last_date && (
                    <tr>
                      <td className="receipt-tot-label">Last Payment</td>
                      <td className="receipt-num">{formatDateTime(payment.last_date)}</td>
                    </tr>
                  )}
                </tbody>
              </table>

              {receipt.transactions.length > 0 && (
                <>
                  <h3 className="receipt-subhead">Payment History</h3>
                  <table className="receipt-table">
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Method</th>
                        {hasTxRef && <th>Reference</th>}
                        <th className="receipt-num">Amount</th>
                      </tr>
                    </thead>
                    <tbody>
                      {receipt.transactions.map((t, i) => (
                        <tr key={i}>
                          <td>{formatDateTime(t.date)}</td>
                          <td>{t.method}</td>
                          {hasTxRef && <td className="receipt-ref">{t.reference || '—'}</td>}
                          <td className="receipt-num">{formatPeso(t.amount)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </>
              )}

              <hr className="receipt-rule" />
              <p className="receipt-foot">Thank you for choosing {shop.name}!</p>
              <p className="receipt-foot receipt-foot-small">
                This is a system-generated receipt for Order {order.number}.
              </p>
            </div>

            <div className="receipt-actions receipt-no-print">
              <button className="btn btn-primary" onClick={handlePrint}>🖨 Print Receipt</button>
              <button className="btn btn-outline" onClick={handleDownloadPdf}>⬇ Download PDF</button>
              {canEmail && (
                <button
                  className="btn btn-outline"
                  onClick={() => setEmailOpen((open) => !open)}
                >
                  ✉ Email Receipt
                </button>
              )}
              <button className="btn btn-outline" onClick={onClose}>← Back to Order</button>
            </div>

            {canEmail && emailOpen && (
              <div className="receipt-email receipt-no-print">
                <label htmlFor="receipt-email-to">Send to</label>
                <input
                  id="receipt-email-to"
                  type="email"
                  value={recipient}
                  onChange={(e) => setRecipient(e.target.value)}
                  placeholder="customer@example.com"
                  disabled={sending}
                />
                <button
                  className="btn btn-primary"
                  onClick={handleSend}
                  disabled={sending}
                >
                  {sending ? 'Sending...' : 'Send Receipt'}
                </button>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  )
}
