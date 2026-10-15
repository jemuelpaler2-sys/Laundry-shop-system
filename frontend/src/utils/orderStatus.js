// Text shown for each order status stored in the database
export const STATUS_LABELS = {
  dropoff: 'Drop-off',
  washing: 'Washing',
  drying: 'Drying',
  folding: 'Folding',
  ready_for_pickup: 'Ready for Pickup',
  completed: 'Completed',
  cancelled: 'Cancelled',
}

// Steps of the progress tracker, in order
export const TRACKER_STEPS = [
  { key: 'dropoff', label: 'Drop-off' },
  { key: 'washing', label: 'Washing' },
  { key: 'drying', label: 'Drying' },
  { key: 'folding', label: 'Folding' },
  { key: 'ready_for_pickup', label: 'Ready' },
  { key: 'completed', label: 'Pickup' },
]

// Returns 'done', 'current' or 'todo' for one step of the tracker
export function getStepState(orderStatus, stepIndex) {
  if (orderStatus === 'completed') return 'done'
  const current = TRACKER_STEPS.findIndex((s) => s.key === orderStatus)
  if (current === -1) return 'todo'
  if (stepIndex < current) return 'done'
  if (stepIndex === current) return 'current'
  return 'todo'
}

export function formatPeso(value) {
  return (
    '₱' +
    Number(value).toLocaleString('en-PH', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  )
}

export function formatDate(value) {
  if (!value) return '—'
  let text = String(value).replace(' ', 'T')
  if (text.length === 10) text += 'T00:00:00' // date-only value
  const date = new Date(text)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

// Shop ordering rules. The backend sends the real values (backend/config/ordering.php);
// these are only used before the first response arrives.
export const DEFAULT_LIMITS = {
  min_weight: 8,
  max_weight: 500,
  min_price: 160,
  max_price: 100000,
  default_service: 'Wash & Fold',
}

export function formatWeight(value) {
  return Number(value) > 0 ? `${Number(value)} kg` : 'Not weighed yet'
}

// Short explanation shown to customers for each status
export const STATUS_DESCRIPTIONS = {
  dropoff: 'We have received your laundry.',
  washing: 'Your laundry is being washed.',
  drying: 'Your laundry is being dried.',
  folding: 'Your laundry is being folded and packed.',
  ready_for_pickup: 'Your laundry is clean and waiting for you.',
  completed: 'You picked up your laundry. Thank you!',
  cancelled: 'This order was cancelled.',
}

export const STATUS_ICONS = {
  dropoff: '📥',
  washing: '🫧',
  drying: '🌬️',
  folding: '👕',
  ready_for_pickup: '📦',
  completed: '✅',
  cancelled: '❌',
}

// Payment status text for one order (needs price, balance and amount_paid)
export function getPaymentText(order) {
  if (order.price <= 0) return 'To be computed'
  if (order.balance <= 0) return 'Paid'
  if (order.amount_paid > 0) return 'Partially paid'
  return 'Unpaid'
}

// Date and time, for example "Sep 28, 2026, 9:30 AM"
export function formatDateTime(value) {
  if (!value) return '—'
  const date = new Date(String(value).replace(' ', 'T'))
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

// Quantity with up to 3 decimals and no extra zeros (0.175, 5, 12.5)
export function formatQty(value) {
  return Number(value).toLocaleString('en-PH', { maximumFractionDigits: 3 })
}