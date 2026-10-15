import { useEffect, useState } from 'react'
import { apiRequest } from '../services/api'
import { formatPeso, formatDateTime } from '../utils/orderStatus'

// Pop-up listing every payment recorded for one order
export default function PaymentHistoryModal({ orderId, onClose }) {
  const [data, setData] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true
    apiRequest(`payments/history.php?order_id=${orderId}`).then((result) => {
      if (!active) return
      if (result.success) {
        setData(result)
      } else {
        setError(result.message || 'Could not load the payment history.')
      }
    })
    return () => {
      active = false
    }
  }, [orderId])

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
          <h2>{data ? `Payments for ${data.order.order_number}` : 'Payments'}</h2>
          <button className="modal-close" onClick={onClose} aria-label="Close">✕</button>
        </div>

        {error && <div className="alert-error">{error}</div>}
        {!data && !error && <p className="loading-text">Loading...</p>}

        {data && (
          <>
            <p className="muted">
              {data.order.customer_name} · Total {formatPeso(data.order.price)} · Paid{' '}
              <strong>{formatPeso(data.total_paid)}</strong>
            </p>

            {data.payments.length === 0 ? (
              <p className="empty">No payments recorded yet.</p>
            ) : (
              <div className="table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Amount</th>
                      <th>Method</th>
                      <th>Reference</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.payments.map((p, index) => (
                      <tr key={index}>
                        <td>{formatDateTime(p.payment_date)}</td>
                        <td><strong>{formatPeso(p.amount)}</strong></td>
                        <td>
                          {p.payment_method}
                          {p.gateway && (
                            <span className="badge badge-online" title={`Recorded by ${p.gateway}`}>
                              Online
                            </span>
                          )}
                        </td>
                        <td className="muted">{p.transaction_reference || '—'}</td>
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