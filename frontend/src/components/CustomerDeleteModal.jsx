import { useEffect, useState } from 'react'
import { apiRequest } from '../services/api'

// Confirmation pop-up shown before a customer is permanently deleted
export default function CustomerDeleteModal({ customer, onClose, onDeleted }) {
  const [error, setError] = useState('')
  const [deleting, setDeleting] = useState(false)

  useEffect(() => {
    function handleKey(e) {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', handleKey)
    return () => window.removeEventListener('keydown', handleKey)
  }, [onClose])

  async function handleDelete() {
    setError('')
    setDeleting(true)
    const result = await apiRequest('customers/delete.php', {
      method: 'POST',
      body: { id: customer.id },
    })
    setDeleting(false)

    if (result.success) {
      onDeleted(result.message)
    } else {
      setError(result.message || 'Could not delete the customer.')
    }
  }

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div
        className="modal confirm-modal"
        role="dialog"
        aria-modal="true"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="modal-head">
          <h2>Delete Customer</h2>
          <button className="modal-close" onClick={onClose} aria-label="Close">✕</button>
        </div>

        {error && <div className="alert-error">{error}</div>}

        <p className="confirm-text">
          Are you sure you want to permanently delete{' '}
          <strong>{customer.first_name} {customer.last_name}</strong>{' '}
          (Customer ID: <strong>{customer.customer_code}</strong>)?
        </p>
        <p className="confirm-warning">
          This action cannot be undone. Customers with existing orders or loyalty
          records cannot be deleted — deactivate them instead to keep their history.
        </p>

        <div className="modal-actions">
          <button
            type="button"
            className="btn btn-outline"
            onClick={onClose}
            disabled={deleting}
          >
            Cancel
          </button>
          <button
            type="button"
            className="btn btn-danger"
            onClick={handleDelete}
            disabled={deleting}
          >
            {deleting ? 'Deleting...' : 'Delete Permanently'}
          </button>
        </div>
      </div>
    </div>
  )
}
