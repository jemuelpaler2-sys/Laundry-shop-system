import { useEffect, useState } from 'react'
import { apiRequest } from '../services/api'

// Pop-up form to add a customer (customer = null) or edit one (customer = object)
export default function CustomerFormModal({ customer, onClose, onSaved }) {
  const isEdit = Boolean(customer)

  const [form, setForm] = useState({
    first_name: customer?.first_name || '',
    last_name: customer?.last_name || '',
    email: customer?.email || '',
    phone: customer?.phone || '',
    address: customer?.address || '',
    password: '',
  })
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    function handleKey(e) {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', handleKey)
    return () => window.removeEventListener('keydown', handleKey)
  }, [onClose])

  function handleChange(e) {
    setForm({ ...form, [e.target.name]: e.target.value })
  }

  function validate() {
    if (!form.first_name.trim() || !form.last_name.trim()) return 'Please enter the first and last name.'
    if (!/^\S+@\S+\.\S+$/.test(form.email)) return 'Please enter a valid email address.'
    if (!/^[0-9+\-\s()]{7,20}$/.test(form.phone)) return 'Please enter a valid phone number.'
    if (!form.address.trim()) return 'Please enter the address.'
    if (!isEdit && form.password.length < 8) return 'Temporary password must be at least 8 characters.'
    if (isEdit && form.password !== '' && form.password.length < 8) {
      return 'New password must be at least 8 characters.'
    }
    return ''
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')

    const problem = validate()
    if (problem) {
      setError(problem)
      return
    }

    setSaving(true)
    const result = await apiRequest('customers/save.php', {
      method: 'POST',
      body: { ...form, id: customer ? customer.id : 0 },
    })
    setSaving(false)

    if (result.success) {
      onSaved(result.message)
    } else {
      setError(result.message || 'Could not save the customer.')
    }
  }

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div
        className="modal modal-wide"
        role="dialog"
        aria-modal="true"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="modal-head">
          <h2>{isEdit ? `Edit ${customer.customer_code}` : 'Add Customer'}</h2>
          <button className="modal-close" onClick={onClose} aria-label="Close">
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>

        {error && <div className="alert-error">{error}</div>}

        <form onSubmit={handleSubmit}>
          <div className="field-row">
            <div className="field">
              <label htmlFor="cf_first_name">First Name</label>
              <input id="cf_first_name" name="first_name" type="text"
                value={form.first_name} onChange={handleChange} />
            </div>
            <div className="field">
              <label htmlFor="cf_last_name">Last Name</label>
              <input id="cf_last_name" name="last_name" type="text"
                value={form.last_name} onChange={handleChange} />
            </div>
          </div>

          <div className="field">
            <label htmlFor="cf_email">Email</label>
            <input id="cf_email" name="email" type="email"
              value={form.email} onChange={handleChange} />
          </div>

          <div className="field">
            <label htmlFor="cf_phone">Phone Number</label>
            <input id="cf_phone" name="phone" type="tel"
              value={form.phone} onChange={handleChange} />
          </div>

          <div className="field">
            <label htmlFor="cf_address">Address</label>
            <input id="cf_address" name="address" type="text"
              value={form.address} onChange={handleChange} />
          </div>

          <div className="field">
            <label htmlFor="cf_password">
              {isEdit ? 'New Password (leave blank to keep the current one)' : 'Temporary Password'}
            </label>
            <input id="cf_password" name="password" type="password"
              value={form.password} onChange={handleChange}
              autoComplete="new-password" placeholder="At least 8 characters" />
          </div>

          <div className="modal-actions">
            <button type="submit" className="btn btn-primary" disabled={saving}>
              {saving ? 'Saving...' : isEdit ? 'Save Changes' : 'Add Customer'}
            </button>
            <button type="button" className="btn btn-outline" onClick={onClose}>
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}