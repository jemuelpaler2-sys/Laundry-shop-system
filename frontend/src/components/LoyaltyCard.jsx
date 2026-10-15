// Number shown inside an empty stamp slot
const SLOT_NUMBERS = ['①', '②', '③', '④', '⑤', '⑥', '⑦', '⑧', '⑨', '⑩']

// The digital loyalty card: 10 stamp slots, five per row.
// Filled slots are stamped in, empty ones are still waiting for a wash.
export default function LoyaltyCard({
  stamps = 0,
  stampsRequired = 10,
  title = 'Wash & Fold Loyalty Card',
  size = 'large',
  showHint = true,
}) {
  const required = stampsRequired > 0 ? stampsRequired : 10
  const earned = Math.max(0, Math.min(stamps, required))
  const remaining = Math.max(0, required - earned)
  const percent = Math.round((earned * 100) / required)
  const unlocked = earned >= required

  const washText = remaining === 1 ? 'wash' : 'washes'

  return (
    <div className={`loyalty-card ${size} ${unlocked ? 'unlocked' : ''}`}>
      <div className="loyalty-card-head">
        <span className="loyalty-card-icon">🧺</span>
        <div>
          <h3>{title}</h3>
          <small>1 completed wash = 1 stamp</small>
        </div>
      </div>

      {/* 10 stamp slots: 5 on the first row, 5 on the second */}
      <div className="loyalty-slots">
        {Array.from({ length: required }, (_, index) => {
          const filled = index < earned
          return (
            <div
              key={index}
              className={`loyalty-slot ${filled ? 'filled' : 'empty'}`}
              title={filled ? `Wash ${index + 1} completed` : `Wash ${index + 1} not yet completed`}
            >
              <span>{filled ? '✓' : SLOT_NUMBERS[index] ?? '○'}</span>
            </div>
          )
        })}
      </div>

      <div className="loyalty-count">
        <strong>
          {earned} / {required} WASHES
        </strong>
        <div className="loyalty-bar">
          <div className="loyalty-bar-fill" style={{ width: `${percent}%` }}></div>
        </div>
      </div>

      {unlocked ? (
        <p className="loyalty-hint unlocked-hint">
          🎉 Reward Unlocked! Claim your reward at the shop to start the next card.
        </p>
      ) : (
        showHint && (
          <p className="loyalty-hint">
            {remaining} more {washText} to unlock your reward!
          </p>
        )
      )}
    </div>
  )
}
