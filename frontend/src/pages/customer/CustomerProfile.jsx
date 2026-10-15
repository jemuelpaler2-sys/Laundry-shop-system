import { useState } from 'react'
import { useOutletContext } from 'react-router-dom'
import { apiRequest } from '../../services/api'

function Message({ msg }) {
  if (!msg.text) return null
  return (
    <div className={msg.type === 'success' ? 'alert-success' : 'alert-error'}>
      {msg.text}
    </div>
  )
}

export default function CustomerProfile() {
  const { customer, setCustomer } = useOutletContext()

  // ----- Personal information form -----
  const [form, setForm] = useState({
    first_name: customer.first_name,
    last_name: customer.last_name,
    email: customer.email,
    phone: customer.phone,
    address: customer.address,
  })
  const [profileMsg, setProfileMsg] = useState({ type: '', text: '' })
  const [savingProfile, setSavingProfile] = useState(false)

  function handleFormChange(e) {
    setForm({ ...form, [e.target.name]: e.target.value })
  }

  function validateProfile() {
    if (!form.first_name.trim() || !form.last_name.trim()) return 'Please enter your first and last name.'
    if (!/^\S+@\S+\.\S+$/.test(form.email)) return 'Please enter a valid email address.'
    if (!/^[0-9+\-\s()]{7,20}$/.test(form.phone)) return 'Please enter a valid phone number.'
    if (!form.address.trim()) return 'Please enter your address.'
    return ''
  }

  async function handleProfileSubmit(e) {
    e.preventDefault()
    setProfileMsg({ type: '', text: '' })

    const problem = validateProfile()
    if (problem) {
      setProfileMsg({ type: 'error', text: problem })
      return
    }

    setSavingProfile(true)
    const result = await apiRequest('customer/update-profile.php', {
      method: 'POST',
      body: form,
    })
    setSavingProfile(false)

    if (result.success) {
      setCustomer(result.customer) // updates the name everywhere in the customer area
      setForm({
        first_name: result.customer.first_name,
        last_name: result.customer.last_name,
        email: result.customer.email,
        phone: result.customer.phone,
        address: result.customer.address,
      })
      setProfileMsg({ type: 'success', text: result.message })
    } else {
      setProfileMsg({ type: 'error', text: result.message || 'Could not update your profile.' })
    }
  }

  // ----- Change password form -----
  const [pw, setPw] = useState({
    current_password: '',
    new_password: '',
    confirm_password: '',
  })
  const [showPw, setShowPw] = useState(false)
  const [pwMsg, setPwMsg] = useState({ type: '', text: '' })
  const [savingPw, setSavingPw] = useState(false)

  function handlePwChange(e) {
    setPw({ ...pw, [e.target.name]: e.target.value })
  }

  async function handlePasswordSubmit(e) {
    e.preventDefault()
    setPwMsg({ type: '', text: '' })

    if (!pw.current_password || !pw.new_password || !pw.confirm_password) {
      setPwMsg({ type: 'error', text: 'Please fill in all password fields.' })
      return
    }
    if (pw.new_password.length < 8) {
      setPwMsg({ type: 'error', text: 'New password must be at least 8 characters.' })
      return
    }
    if (pw.new_password !== pw.confirm_password) {
      setPwMsg({ type: 'error', text: 'New passwords do not match.' })
      return
    }
    if (pw.new_password === pw.current_password) {
      setPwMsg({ type: 'error', text: 'New password must be different from the current one.' })
      return
    }

    setSavingPw(true)
    const result = await apiRequest('customer/change-password.php', {
      method: 'POST',
      body: pw,
    })
    setSavingPw(false)

    if (result.success) {
      setPw({ current_password: '', new_password: '', confirm_password: '' })
      setPwMsg({ type: 'success', text: result.message })
    } else {
      setPwMsg({ type: 'error', text: result.message || 'Could not change your password.' })
    }
  }

  return (
    <div>
      <h1 className="page-title">My Profile</h1>
      <p className="page-sub">Keep your details up to date.</p>

      {/* Personal information */}
      <div className="card form-card">
        <h2>Personal Information</h2>
        <Message msg={profileMsg} />

        <form onSubmit={handleProfileSubmit}>
          <div className="field">
            <label htmlFor="customer_code">Customer ID</label>
            <input id="customer_code" type="text" value={customer.customer_code} readOnly />
          </div>

          <div className="field-row">
            <div className="field">
              <label htmlFor="first_name">First Name</label>
              <input id="first_name" name="first_name" type="text"
                value={form.first_name} onChange={handleFormChange} />
            </div>
            <div className="field">
              <label htmlFor="last_name">Last Name</label>
              <input id="last_name" name="last_name" type="text"
                value={form.last_name} onChange={handleFormChange} />
            </div>
          </div>

          <div className="field">
            <label htmlFor="email">Email</label>
            <input id="email" name="email" type="email"
              value={form.email} onChange={handleFormChange} />
          </div>

          <div className="field">
            <label htmlFor="phone">Phone Number</label>
            <input id="phone" name="phone" type="tel"
              value={form.phone} onChange={handleFormChange} />
          </div>

          <div className="field">
            <label htmlFor="address">Address</label>
            <input id="address" name="address" type="text"
              value={form.address} onChange={handleFormChange} />
          </div>

          <button type="submit" className="btn btn-primary" disabled={savingProfile}>
            {savingProfile ? 'Saving...' : 'Save Changes'}
          </button>
        </form>
      </div>

      {/* Change password */}
      <div className="card form-card">
        <h2>Change Password</h2>
        <Message msg={pwMsg} />

        <form onSubmit={handlePasswordSubmit}>
          <div className="field">
            <label htmlFor="current_password">Current Password</label>
            <div className="password-wrap">
              <input id="current_password" name="current_password"
                type={showPw ? 'text' : 'password'}
                value={pw.current_password} onChange={handlePwChange}
                autoComplete="current-password" />
              <button type="button" className="toggle-password"
                onClick={() => setShowPw(!showPw)}>
                {showPw ? 'Hide' : 'Show'}
              </button>
            </div>
          </div>

          <div className="field">
            <label htmlFor="new_password">New Password</label>
            <input id="new_password" name="new_password"
              type={showPw ? 'text' : 'password'}
              value={pw.new_password} onChange={handlePwChange}
              autoComplete="new-password" placeholder="At least 8 characters" />
          </div>

          <div className="field">
            <label htmlFor="confirm_password">Confirm New Password</label>
            <input id="confirm_password" name="confirm_password"
              type={showPw ? 'text' : 'password'}
              value={pw.confirm_password} onChange={handlePwChange}
              autoComplete="new-password" />
          </div>

          <button type="submit" className="btn btn-primary" disabled={savingPw}>
            {savingPw ? 'Changing...' : 'Change Password'}
          </button>
        </form>
      </div>
    </div>
  )
}