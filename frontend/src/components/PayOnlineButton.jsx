import { useState } from 'react'
import { apiRequest } from '../services/api'

// "Pay Online" button for a customer's own order with a remaining balance.
// It asks the backend to create a PayMongo checkout session and then sends the
// customer to PayMongo's hosted payment page. No payment is recorded here.
export default function PayOnlineButton({ orderNumber, disabled, onStatusChange }) {
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  async function handleClick() {
    setError('')
    setLoading(true)
    const result = await apiRequest('payments/paymongo/create-checkout.php', {
      method: 'POST',
      body: { order_number: orderNumber },
    })
    setLoading(false)

    if (result.success && result.checkout_url) {
      if (onStatusChange) onStatusChange('pending')
      window.location.href = result.checkout_url
      return
    }

    setError(result.message || 'Could not start the online payment.')
  }

  return (
    <span className="pay-online-wrap">
      <button
        className="btn btn-primary btn-small pay-online-btn"
        onClick={handleClick}
        disabled={loading || disabled}
        title="Pay your balance online via PayMongo"
      >
        {loading ? 'Opening…' : 'Pay Online'}
      </button>
      {error && <span className="pay-online-error">{error}</span>}
    </span>
  )
}
