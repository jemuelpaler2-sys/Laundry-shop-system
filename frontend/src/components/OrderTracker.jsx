import {
  TRACKER_STEPS,
  STATUS_LABELS,
  STATUS_DESCRIPTIONS,
  getStepState,
} from '../utils/orderStatus'

// Vertical progress tracker with a short description for every step
export default function OrderTracker({ status }) {
  return (
    <ol className="v-tracker">
      {TRACKER_STEPS.map((step, index) => {
        const state = getStepState(status, index)
        return (
          <li key={step.key} className={state}>
            <span className="v-dot">
              {state === 'done' ? <span className="material-symbols-outlined">check</span> : ''}
            </span>
            <div>
              <strong>{STATUS_LABELS[step.key]}</strong>
              <p>{STATUS_DESCRIPTIONS[step.key]}</p>
              {state === 'current' && <span className="now-tag">Current status</span>}
            </div>
          </li>
        )
      })}
    </ol>
  )
}