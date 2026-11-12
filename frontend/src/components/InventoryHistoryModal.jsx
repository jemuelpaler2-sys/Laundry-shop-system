import { useEffect, useState } from 'react'
import { apiRequest } from '../services/api'
import { formatDateTime, formatQty } from '../utils/orderStatus'

// Pop-up listing the latest stock movements of one item
export default function InventoryHistoryModal({ itemId, onClose }) {
  const [data, setData] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true
    apiRequest(`inventory/transactions.php?inventory_id=${itemId}`).then((result) => {
      if (!active) return
      if (result.success) {
        setData(result)
      } else {
        setError(result.message || 'Could not load the history.')
      }
    })
    return () => {
      active = false
    }
  }, [itemId])

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
          <h2>{data ? `${data.item.item_name} History` : 'History'}</h2>
          <button className="modal-close" onClick={onClose} aria-label="Close">
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>

        {error && <div className="alert-error">{error}</div>}
        {!data && !error && <p className="loading-text">Loading...</p>}

        {data && (
          <>
            <p className="muted">
              In stock now: <strong>{formatQty(data.item.quantity)} {data.item.unit}</strong>
              {' '}· showing the latest 30 movements
            </p>

            {data.transactions.length === 0 ? (
              <p className="empty">No stock movements yet.</p>
            ) : (
              <div className="table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Type</th>
                      <th>Quantity</th>
                      <th>Note</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.transactions.map((t, index) => {
                      const added = t.transaction_type === 'stock_added'
                      return (
                        <tr key={index}>
                          <td>{formatDateTime(t.created_at)}</td>
                          <td>
                            <span className={`badge badge-${t.transaction_type}`}>
                              {added ? 'Stock Added' : 'Stock Deducted'}
                            </span>
                          </td>
                          <td className={added ? 'qty-plus' : 'qty-minus'}>
                            {added ? '+' : '−'}{formatQty(t.quantity)} {data.item.unit}
                          </td>
                          <td>{t.reference || '—'}</td>
                        </tr>
                      )
                    })}
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