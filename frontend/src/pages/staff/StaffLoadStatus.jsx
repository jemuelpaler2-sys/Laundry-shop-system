import { useEffect, useState } from 'react'
import { useOutletContext } from 'react-router-dom'
import { apiRequest } from '../../services/api'
import { formatPeso, formatDate, DEFAULT_LIMITS } from '../../utils/orderStatus'

const COLUMNS = [
  { key: 'dropoff', title: 'Drop-off', icon: 'move_to_inbox' },
  { key: 'washing', title: 'Washing', icon: 'local_laundry_service' },
  { key: 'drying', title: 'Drying', icon: 'air' },
  { key: 'folding', title: 'Folding', icon: 'checkroom' },
  { key: 'ready_for_pickup', title: 'Ready for Pickup', icon: 'inventory_2' },
]

// Text on the button that moves a load to the next stage
const NEXT_LABEL = {
  dropoff: 'Save weight & start Washing',
  washing: 'Move to Drying',
  drying: 'Move to Folding',
  folding: 'Mark Ready for Pickup',
  ready_for_pickup: 'Complete Order',
}

const CAN_GO_BACK = ['washing', 'drying', 'folding']

export default function StaffLoadStatus() {
  const { staff } = useOutletContext()
  const isOwner = staff.role === 'owner'

  const [orders, setOrders] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState({ type: '', text: '' })
  const [busyId, setBusyId] = useState(null)
  const [weights, setWeights] = useState({}) // weight typed for each order at drop-off
  const [limits, setLimits] = useState(DEFAULT_LIMITS)
  const [reloadKey, setReloadKey] = useState(0)

  const min_weight = limits.min_weight
  const max_weight = limits.max_weight

  useEffect(() => {
    let active = true
    apiRequest('orders/board.php').then((result) => {
      if (!active) return
      if (result.success) {
        setOrders(result.orders)
        setLimits({ ...DEFAULT_LIMITS, ...result.limits })
        setError('')
      } else {
        setError(result.message || 'Could not load the board.')
      }
      setLoading(false)
    })
    return () => {
      active = false
    }
  }, [reloadKey])

  async function handleMove(order, action) {
    setNotice({ type: '', text: '' })

    const body = { id: order.id, action }

    if (action === 'next' && order.order_status === 'dropoff') {
      const raw = weights[order.id] ?? (order.weight > 0 ? String(order.weight) : '')
      const value = Number(raw)
      if (raw === '' || Number.isNaN(value) || value <= 0) {
        setNotice({ type: 'error', text: 'Please enter the weight (kg) first.' })
        return
      }
      if (value < min_weight || value > max_weight) {
        setNotice({
          type: 'error',
          text: `The weight must be between ${min_weight} and ${max_weight} kg.`,
        })
        return
      }
      body.weight = value
    }

    if (
      action === 'next' &&
      order.order_status === 'ready_for_pickup' &&
      !window.confirm(`Complete order ${order.order_number}? This means the customer picked up the laundry.`)
    ) {
      return
    }

    setBusyId(order.id)
    const result = await apiRequest('orders/update-status.php', { method: 'POST', body })
    setBusyId(null)

    setNotice({
      type: result.success ? 'success' : 'error',
      text: result.message || 'Could not update the order.',
    })
    setReloadKey((k) => k + 1) // always refresh so the board matches the database
  }

  if (loading) return <p className="loading-text">Loading the board...</p>

  return (
    <div>
      <div className="card-head">
        <div>
          <h1 className="page-title">Load Status Tracking</h1>
          <p className="page-sub">Move each load through the shop, one step at a time.</p>
        </div>
        <button className="btn-link" onClick={() => setReloadKey((k) => k + 1)}>
          <span className="material-symbols-outlined">refresh</span> Refresh
        </button>
      </div>

      {notice.text && (
        <div className={notice.type === 'success' ? 'alert-success' : 'alert-error'}>
          {notice.text}
        </div>
      )}
      {error && <div className="alert-error">{error}</div>}

      <div className="board">
        {COLUMNS.map((col) => {
          const cards = orders.filter((o) => o.order_status === col.key)
          return (
            <div key={col.key} className="board-col">
              <div className="board-col-head">
                <span>
                  <span className="material-symbols-outlined">{col.icon}</span> {col.title}
                </span>
                <span className="board-count">{cards.length}</span>
              </div>

              {cards.length === 0 && <p className="board-empty">No loads</p>}

              {cards.map((o) => {
                const busy = busyId === o.id
                const notPaid = o.order_status === 'ready_for_pickup' && (o.price <= 0 || o.balance > 0)

                return (
                  <div key={o.id} className="load-card">
                    <h4>{o.order_number}</h4>
                    <p>{o.customer_name} · {o.customer_code}</p>
                    <p>
                      {o.service_type}
                      {o.weight > 0 ? ` · ${o.weight} kg` : ''}
                    </p>
                    <p>Est. pickup: {formatDate(o.estimated_pickup_date)}</p>

                    {o.order_status === 'ready_for_pickup' &&
                      (o.price <= 0 ? (
                        <p className="load-due">No price set</p>
                      ) : o.balance > 0 ? (
                        <p className="load-due">Balance due: {formatPeso(o.balance)}</p>
                      ) : (
                        <p className="load-paid">
                          <span className="material-symbols-outlined">check_circle</span> Paid in full
                        </p>
                      ))}

                    <div className="load-actions">
                      {o.order_status === 'dropoff' && (
                        <input
                          type="number"
                          step="0.01"
                          min={min_weight}
                          max={max_weight}
                          className="weight-input"
                          placeholder={`Weight (kg, min ${min_weight})`}
                          value={weights[o.id] ?? (o.weight > 0 ? String(o.weight) : '')}
                          onChange={(e) => setWeights({ ...weights, [o.id]: e.target.value })}
                        />
                      )}

                      <button
                        className="btn btn-primary"
                        disabled={busy || notPaid}
                        title={notPaid ? 'The order must be fully paid first' : ''}
                        onClick={() => handleMove(o, 'next')}
                      >
                        {busy ? 'Saving...' : NEXT_LABEL[o.order_status]}
                      </button>

                      {isOwner && CAN_GO_BACK.includes(o.order_status) && (
                        <button
                          className="btn-link"
                          disabled={busy}
                          onClick={() => handleMove(o, 'back')}
                        >
                          <span className="material-symbols-outlined">undo</span> Back one step
                        </button>
                      )}
                    </div>
                  </div>
                )
              })}
            </div>
          )
        })}
      </div>
    </div>
  )
}