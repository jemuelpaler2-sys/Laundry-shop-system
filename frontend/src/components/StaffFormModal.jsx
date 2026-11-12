import { useEffect, useState } from 'react'
import { apiRequest } from '../services/api'

// Pop-up form to add a staff account (member = null) or edit one (member = object)
export default function StaffFormModal({ member, onClose, onSaved }) {
  const isEdit = Boolean(member)

  const [form, setForm] = useState({
    username: member?.username || '',
    first_name: member?.first_name || '',
    last_name: member?.last_name || '',
    role: member?.role || 'staff',
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
    if (!/^[a-zA-Z0-9_.]{3,50}$/.test(form.username)) {
      return 'Username must be 3-50 characters (letters, numbers, dot, underscore only).'
    }
    if (!form.first_name.trim() || !form.last_name.trim()) return 'Please enter the first and last name.'
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
    const result = await apiRequest('staff/save.php', {
      method: 'POST',
      body: { ...form, id: member ? member.id : 0 },
    })
    setSaving(false)

    if (result.success) {
      onSaved(result.message)
    } else {
      setError(result.message || 'Could not save the staff account.')
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
          <h2>{isEdit ? `Edit ${member.username}` : 'Add Staff Account'}</h2>
          <button className="modal-close" onClick={onClose} aria-label="Close">
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>

        {error && <div className="alert-error">{error}</div>}

        <form onSubmit={handleSubmit}>
          <div className="field">
            <label htmlFor="sf_username">Username</label>
            <input id="sf_username" name="username" type="text"
              value={form.username} onChange={handleChange} autoComplete="off" />
          </div>

          <div className="field-row">
            <div className="field">
              <label htmlFor="sf_first_name">First Name</label>
              <input id="sf_first_name" name="first_name" type="text"
                value={form.first_name} onChange={handleChange} />
            </div>
            <div className="field">
              <label htmlFor="sf_last_name">Last Name</label>
              <input id="sf_last_name" name="last_name" type="text"
                value={form.last_name} onChange={handleChange} />
            </div>
          </div>

          <div className="field">
            <label htmlFor="sf_role">Role</label>
            <select id="sf_role" name="role" value={form.role} onChange={handleChange}>
              <option value="staff">Laundry Staff</option>
              <option value="owner">Shop Owner</option>
            </select>
          </div>

          <div className="field">
            <label htmlFor="sf_password">
              {isEdit ? 'New Password (leave blank to keep the current one)' : 'Temporary Password'}
            </label>
            <input id="sf_password" name="password" type="password"
              value={form.password} onChange={handleChange}
              autoComplete="new-password" placeholder="At least 8 characters" />
          </div>

          <div className="modal-actions">
            <button type="submit" className="btn btn-primary" disabled={saving}>
              {saving ? 'Saving...' : isEdit ? 'Save Changes' : 'Add Account'}
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