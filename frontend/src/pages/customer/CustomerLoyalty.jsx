import { useEffect, useState } from 'react'
import { apiRequest } from '../../services/api'
import LoyaltyCard from '../../components/LoyaltyCard'
import StatusBadge from '../../components/StatusBadge'
import { formatDate, formatDateTime } from '../../utils/orderStatus'

const REWARD_BADGES = {
  Unlocked: 'badge-reward-unlocked',
  Redeemed: 'badge-reward-redeemed',
}

const STATUS_TEXT = {
  Unlocked: 'Reward ready to claim',
  Redeemed: 'Reward claimed',
  'In Progress': 'Still collecting stamps',
}

export default function CustomerLoyalty() {
  const [data, setData] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    apiRequest('customer/loyalty.php').then((result) => {
      if (result.success) {
        setData(result)
      } else {
        setError(result.message || 'Could not load your loyalty card.')
      }
    })
  }, [])

  if (error) return <div className="alert-error">{error}</div>
  if (!data) return <p className="loading-text">Loading your loyalty card...</p>

  const { customer, card, rewards, stamps, rule } = data

  const statCards = [
    { icon: 'local_laundry_service', value: card.stamps, label: 'Current Stamps' },
    { icon: 'check_circle', value: card.total_completed_washes, label: 'Completed Washes' },
    { icon: 'card_giftcard', value: card.rewards_unlocked, label: 'Rewards Unlocked' },
    { icon: 'savings', value: card.rewards_claimed, label: 'Rewards Claimed' },
  ]

  return (
    <div>
      <h1 className="page-title">Loyalty Card</h1>
      <p className="page-sub">Collect a stamp for every finished wash and earn a reward every 10.</p>

      {/* Who the card belongs to */}
      <div className="card">
        <div className="loyalty-owner">
          <div>
            <h2>{customer.name}</h2>
            <p className="muted">Customer ID: {customer.customer_code}</p>
          </div>
          <span className={`badge ${REWARD_BADGES[card.reward_status] || 'badge-pending'}`}>
            {STATUS_TEXT[card.reward_status] || card.reward_status}
          </span>
        </div>

        {/* Progress message */}
        <div className={`info-note loyalty-message ${card.reward_status === 'Unlocked' ? 'celebrate' : ''}`}>
          {card.message}
        </div>

        <LoyaltyCard
          stamps={card.stamps}
          stampsRequired={card.stamps_required}
          title={card.card_title}
        />
      </div>

      {/* Summary cards */}
      <div className="stat-grid stat-grid-4">
        {statCards.map((s) => (
          <div key={s.label} className="stat-card">
            <div className="stat-icon">
              <span className="material-symbols-outlined">{s.icon}</span>
            </div>
            <div>
              <div className="stat-value">{s.value}</div>
              <div className="stat-label">{s.label}</div>
            </div>
          </div>
        ))}
      </div>

      {/* The reward that is waiting, if there is one */}
      {card.pending_reward && (
        <div className="card reward-panel">
          <div className="reward-panel-head">
            <span className="reward-panel-icon">
              <span className="material-symbols-outlined">celebration</span>
            </span>
            <div>
              <h2>Reward Unlocked!</h2>
              <p className="muted">
                You finished the {card.stamps_required}-wash promotion. Show this code at the counter and
                our staff will mark it as redeemed.
              </p>
            </div>
          </div>
          <div className="reward-code">{card.pending_reward.reward_code}</div>
          <dl className="detail-list">
            <div className="detail-row">
              <dt>Your reward</dt>
              <dd>{card.reward_name}</dd>
            </div>
            <div className="detail-row">
              <dt>Unlocked on</dt>
              <dd>{formatDate(card.pending_reward.unlocked_at)}</dd>
            </div>
            <div className="detail-row">
              <dt>Card progress</dt>
              <dd>
                {card.stamps} / {card.stamps_required} washes
              </dd>
            </div>
          </dl>
          <p className="muted reward-note">
            Your card stays at {card.stamps_required}/{card.stamps_required} until staff redeem your
            reward. After that it starts again from 0.
          </p>
        </div>
      )}

      {/* Reward history */}
      <div className="card">
        <h2>Reward History</h2>

        {rewards.length === 0 ? (
          <p className="empty">
            No rewards yet. You need {card.stamps_required} completed washes to unlock your first one.
          </p>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Reward Code</th>
                  <th>Reward</th>
                  <th>Cycle</th>
                  <th>Status</th>
                  <th>Unlocked</th>
                  <th>Redeemed</th>
                </tr>
              </thead>
              <tbody>
                {rewards.map((r) => (
                  <tr key={r.id}>
                    <td>
                      <strong>{r.reward_code}</strong>
                    </td>
                    <td>{card.reward_name}</td>
                    <td>#{r.cycle}</td>
                    <td>
                      <span className={`badge ${REWARD_BADGES[r.reward_status] || 'badge-pending'}`}>
                        {r.reward_status === 'Unlocked' ? 'Unlocked' : 'Redeemed'}
                      </span>
                    </td>
                    <td>{formatDate(r.unlocked_at)}</td>
                    <td>{r.redeemed_at ? formatDate(r.redeemed_at) : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Which orders earned the stamps */}
      <div className="card">
        <h2>Washes That Earned Stamps</h2>

        {stamps.length === 0 ? (
          <p className="empty">
            No stamps yet. A wash is counted when your order reaches Ready for Pickup.
          </p>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Order Number</th>
                  <th>Order Status</th>
                  <th>Stamp</th>
                  <th>Counted On</th>
                </tr>
              </thead>
              <tbody>
                {stamps.map((s) => (
                  <tr key={s.id}>
                    <td>
                      <strong>{s.order_number}</strong>
                    </td>
                    <td>
                      <StatusBadge status={s.order_status} />
                    </td>
                    <td>
                      {s.stamps_added === 1 ? (
                        <span className="text-stamp-earned">
                          <span className="material-symbols-outlined">check</span> +1 stamp
                        </span>
                      ) : (
                        <span className="muted">Counted, held</span>
                      )}
                    </td>
                    <td>{formatDateTime(s.created_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <div className="info-note">{rule}</div>
    </div>
  )
}
