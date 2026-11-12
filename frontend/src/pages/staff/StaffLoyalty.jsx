import { useEffect, useState } from 'react'
import { apiRequest } from '../../services/api'
import LoyaltyCard from '../../components/LoyaltyCard'
import { formatDate } from '../../utils/orderStatus'

const STATUS_OPTIONS = [
  { value: 'all', label: 'All customers' },
  { value: 'pending', label: 'Reward waiting to be claimed' },
  { value: 'in_progress', label: 'Collecting stamps' },
  { value: 'redeemed', label: 'Has claimed a reward' },
  { value: 'no_card', label: 'No stamps yet' },
]

const REWARD_BADGES = {
  Unlocked: 'badge-reward-unlocked',
  Redeemed: 'badge-reward-redeemed',
  'In Progress': 'badge-pending',
}

const STATUS_TEXT = {
  Unlocked: 'Ready to claim',
  Redeemed: 'Claimed before',
  'In Progress': 'Collecting stamps',
}

export default function StaffLoyalty() {
  const [customers, setCustomers] = useState([])
  const [summary, setSummary] = useState({ cards: 0, rewards_pending: 0, rewards_redeemed: 0 })
  const [required, setRequired] = useState(10)
  const [rewardName, setRewardName] = useState('Free Wash & Fold')
  const [total, setTotal] = useState(0)
  const [totalPages, setTotalPages] = useState(1)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState({ type: '', text: '' })
  const [reloadKey, setReloadKey] = useState(0)
  const [redeemingId, setRedeemingId] = useState(null)

  // Load one page of loyalty cards (waits 300 ms while the user is still typing)
  useEffect(() => {
    const timer = setTimeout(async () => {
      const params = new URLSearchParams({
        search: search.trim(),
        status: statusFilter,
        page: String(page),
      })
      const result = await apiRequest(`loyalty/list.php?${params.toString()}`)
      if (result.success) {
        setCustomers(result.customers)
        setSummary(result.summary)
        setRequired(result.required)
        setRewardName(result.reward_name)
        setTotal(result.total)
        setTotalPages(result.total_pages)
        setError('')
      } else {
        setError(result.message || 'Could not load the loyalty cards.')
      }
      setLoading(false)
    }, 300)

    return () => clearTimeout(timer)
  }, [search, statusFilter, page, reloadKey])

  function handleSearchChange(e) {
    setSearch(e.target.value)
    setPage(1)
  }

  function handleStatusChange(e) {
    setStatusFilter(e.target.value)
    setPage(1)
  }

  function clearFilters() {
    setSearch('')
    setStatusFilter('all')
    setPage(1)
  }

  async function handleRedeem(c) {
    const question =
      `Mark the reward ${c.pending_reward_code} as redeemed for ${c.first_name} ${c.last_name}? `
      + `Their card will go back to 0/${required} washes for the next cycle.`

    if (!window.confirm(question)) return

    setRedeemingId(c.id)
    const result = await apiRequest('loyalty/redeem.php', {
      method: 'POST',
      body: { reward_id: c.pending_reward_id },
    })

    if (result.success) {
      setNotice({ type: 'success', text: result.message })
      setReloadKey((k) => k + 1)
    } else {
      setNotice({ type: 'error', text: result.message || 'Could not redeem the reward.' })
      setReloadKey((k) => k + 1)
    }
    setRedeemingId(null)
  }

  const statCards = [
    { icon: 'loyalty', value: summary.cards, label: 'Loyalty Cards' },
    { icon: 'card_giftcard', value: summary.rewards_pending, label: 'Waiting To Be Claimed', alert: true },
    { icon: 'check_circle', value: summary.rewards_redeemed, label: 'Rewards Redeemed' },
  ]

  return (
    <div>
      <h1 className="page-title">Loyalty &amp; Rewards</h1>
      <p className="page-sub">
        Every finished wash earns the customer 1 stamp. {required} completed washes = 1 {rewardName}.
      </p>

      {notice.text && (
        <div className={notice.type === 'success' ? 'alert-success' : 'alert-error'}>{notice.text}</div>
      )}
      {error && <div className="alert-error">{error}</div>}

      {/* Summary cards */}
      <div className="stat-grid stat-grid-3">
        {statCards.map((card) => (
          <div key={card.label} className={`stat-card ${card.alert && card.value > 0 ? 'stat-card-alert' : ''}`}>
            <div className="stat-icon">
              <span className="material-symbols-outlined">{card.icon}</span>
            </div>
            <div>
              <div className="stat-value">{card.value}</div>
              <div className="stat-label">{card.label}</div>
            </div>
          </div>
        ))}
      </div>

      {/* Search and filter */}
      <div className="card">
        <div className="finder finder-3">
          <div>
            <label htmlFor="loyalty-search">Search</label>
            <input
              id="loyalty-search"
              type="text"
              value={search}
              onChange={handleSearchChange}
              placeholder="Name, email, phone or Customer ID"
            />
          </div>

          <div>
            <label htmlFor="loyalty-status">Reward Status</label>
            <select id="loyalty-status" value={statusFilter} onChange={handleStatusChange}>
              {STATUS_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          </div>

          <button className="btn btn-outline" onClick={clearFilters}>Clear</button>
        </div>
      </div>

      {/* Loyalty cards table */}
      <div className="card">
        {loading ? (
          <p className="loading-text">Loading loyalty cards...</p>
        ) : (
          <>
            <p className="muted result-count">
              {total} {total === 1 ? 'customer' : 'customers'} found
            </p>

            {customers.length === 0 ? (
              <p className="empty">No customers match your search.</p>
            ) : (
              <div className="table-wrap">
                <table className="data-table loyalty-table">
                  <thead>
                    <tr>
                      <th>Customer ID</th>
                      <th>Name</th>
                      <th>Completed Washes</th>
                      <th>Loyalty Progress</th>
                      <th>Rewards Unlocked</th>
                      <th>Rewards Redeemed</th>
                      <th>Unlocked On</th>
                      <th>Redeemed On</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {customers.map((c) => (
                      <tr key={c.id}>
                        <td>
                          <strong>{c.customer_code}</strong>
                        </td>
                        <td>{c.first_name} {c.last_name}</td>
                        <td>{c.total_completed_washes}</td>
                        <td>
                          <div className="loyalty-cell">
                            <strong>
                              {c.stamps} / {c.stamps_required}
                            </strong>
                            <div className="loyalty-bar small">
                              <div
                                className="loyalty-bar-fill"
                                style={{ width: `${Math.round((c.stamps * 100) / c.stamps_required)}%` }}
                              ></div>
                            </div>
                            <span className={`badge ${REWARD_BADGES[c.reward_status] || 'badge-pending'}`}>
                              {STATUS_TEXT[c.reward_status] || c.reward_status}
                            </span>
                          </div>
                        </td>
                        <td>{c.rewards_unlocked}</td>
                        <td>{c.rewards_redeemed}</td>
                        <td>{c.last_unlocked_at ? formatDate(c.last_unlocked_at) : '—'}</td>
                        <td>{c.last_redeemed_at ? formatDate(c.last_redeemed_at) : '—'}</td>
                        <td>
                          {c.pending_reward_id ? (
                            <div className="row-actions">
                              <button
                                className="btn-link"
                                onClick={() => handleRedeem(c)}
                                disabled={redeemingId === c.id}
                              >
                                {redeemingId === c.id ? 'Redeeming...' : 'Redeem Reward'}
                              </button>
                            </div>
                          ) : (
                            <span className="muted">—</span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {totalPages > 1 && (
              <div className="pager">
                <button onClick={() => setPage(page - 1)} disabled={page <= 1}>
                  <span className="material-symbols-outlined">chevron_left</span> Previous
                </button>
                <span>Page {page} of {totalPages}</span>
                <button onClick={() => setPage(page + 1)} disabled={page >= totalPages}>
                  Next <span className="material-symbols-outlined">chevron_right</span>
                </button>
              </div>
            )}
          </>
        )}
      </div>

      {/* A preview of the card the customer sees */}
      <div className="card">
        <h2>What The Customer Sees</h2>
        <p className="card-intro">
          The digital card in the customer portal, shown at 0 stamps and at {required} stamps.
        </p>
        <div className="loyalty-preview">
          <LoyaltyCard stamps={0} stampsRequired={required} size="small" showHint={false} />
          <LoyaltyCard stamps={required - 3} stampsRequired={required} size="small" showHint={false} />
          <LoyaltyCard stamps={required} stampsRequired={required} size="small" showHint={false} />
        </div>
      </div>
    </div>
  )
}
