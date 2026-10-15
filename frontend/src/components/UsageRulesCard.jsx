import { useEffect, useState } from 'react'
import { apiRequest } from '../services/api'
import { formatQty } from '../utils/orderStatus'

const TYPE_LABELS = {
  none: 'No automatic deduction',
  per_kg: 'Per kg of laundry',
  per_order: 'Per order',
}

const EXAMPLE_KG = 3.5

// Card that shows (and lets the owner edit) how much of each supply is used automatically
export default function UsageRulesCard({ isOwner }) {
  const [rules, setRules] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [msg, setMsg] = useState({ type: '', text: '' })
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    let active = true
    apiRequest('inventory/rules.php').then((result) => {
      if (!active) return
      if (result.success) {
        setRules(
          result.rules.map((r) => ({
            ...r,
            quantity_used: r.usage_type === 'none' ? '' : String(r.quantity_used),
          }))
        )
      } else {
        setError(result.message || 'Could not load the deduction rules.')
      }
      setLoading(false)
    })
    return () => {
      active = false
    }
  }, [])

  function update(index, field, value) {
    setRules(rules.map((r, i) => (i === index ? { ...r, [field]: value } : r)))
  }

  function example(r) {
    const q = Number(r.quantity_used)
    if (r.usage_type === 'none' || !q) return '—'
    if (r.usage_type === 'per_kg') {
      return `${formatQty(q * EXAMPLE_KG)} ${r.unit} for a ${EXAMPLE_KG} kg load`
    }
    return `${formatQty(q)} ${r.unit} for every order`
  }

  async function handleSave() {
    setMsg({ type: '', text: '' })

    const payload = []
    for (const r of rules) {
      if (r.usage_type === 'none') {
        payload.push({ inventory_id: r.inventory_id, usage_type: 'none', quantity_used: 0 })
        continue
      }
      const q = Number(r.quantity_used)
      if (r.quantity_used === '' || Number.isNaN(q) || q <= 0) {
        setMsg({ type: 'error', text: `Enter an amount greater than 0 for ${r.item_name}.` })
        return
      }
      payload.push({ inventory_id: r.inventory_id, usage_type: r.usage_type, quantity_used: q })
    }

    setSaving(true)
    const result = await apiRequest('inventory/rules.php', {
      method: 'POST',
      body: { rules: payload },
    })
    setSaving(false)

    setMsg({
      type: result.success ? 'success' : 'error',
      text: result.message || 'Could not save the rules.',
    })
  }

  return (
    <div className="card">
      <h2>Automatic Deduction Rules</h2>
      <p className="card-intro">
        When a load moves from <strong>Folding</strong> to <strong>Ready for Pickup</strong>,
        these supplies are deducted automatically and recorded in each item&apos;s history.
        {isOwner ? ' Change the amounts below and click Save Rules.' : ' Only the shop owner can change them.'}
      </p>

      {error && <div className="alert-error">{error}</div>}
      {msg.text && (
        <div className={msg.type === 'success' ? 'alert-success' : 'alert-error'}>{msg.text}</div>
      )}

      {loading ? (
        <p className="loading-text">Loading the rules...</p>
      ) : rules.length === 0 ? (
        <p className="empty">Add inventory items first.</p>
      ) : (
        <>
          <div className="table-wrap">
            <table className="data-table rules-table">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Rule</th>
                  <th>Amount</th>
                  <th>Example</th>
                </tr>
              </thead>
              <tbody>
                {rules.map((r, index) => (
                  <tr key={r.inventory_id}>
                    <td><strong>{r.item_name}</strong></td>
                    <td>
                      {isOwner ? (
                        <select
                          value={r.usage_type}
                          onChange={(e) => update(index, 'usage_type', e.target.value)}
                        >
                          {Object.entries(TYPE_LABELS).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                          ))}
                        </select>
                      ) : (
                        TYPE_LABELS[r.usage_type]
                      )}
                    </td>
                    <td>
                      {r.usage_type === 'none' ? (
                        '—'
                      ) : isOwner ? (
                        <>
                          <input
                            type="number"
                            step="0.0001"
                            min="0"
                            value={r.quantity_used}
                            onChange={(e) => update(index, 'quantity_used', e.target.value)}
                          />
                          {r.unit}
                        </>
                      ) : (
                        `${formatQty(r.quantity_used)} ${r.unit}`
                      )}
                    </td>
                    <td className="muted">{example(r)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {isOwner && (
            <div className="rules-actions">
              <button className="btn btn-primary" onClick={handleSave} disabled={saving}>
                {saving ? 'Saving...' : 'Save Rules'}
              </button>
            </div>
          )}
        </>
      )}
    </div>
  )
}