import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { apiRequest } from '../../services/api'

export default function CustomerRegister() {
  const navigate = useNavigate()
  const [form, setForm] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    address: '',
    password: '',
    confirm_password: '',
  })
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  function handleChange(e) {
    setForm({ ...form, [e.target.name]: e.target.value })
  }

  function validate() {
    if (!form.first_name.trim() || !form.last_name.trim()) return 'Please enter your first and last name.'
    if (!/^\S+@\S+\.\S+$/.test(form.email)) return 'Please enter a valid email address.'
    if (!/^[0-9+\-\s()]{7,20}$/.test(form.phone)) return 'Please enter a valid phone number.'
    if (!form.address.trim()) return 'Please enter your address.'
    if (form.password.length < 8) return 'Password must be at least 8 characters.'
    if (form.password !== form.confirm_password) return 'Passwords do not match.'
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

    setLoading(true)
    const result = await apiRequest('customer/register.php', {
      method: 'POST',
      body: form,
    })
    setLoading(false)

    if (result.success) {
      navigate('/customer-login', {
        state: { registered: true, customerCode: result.customer_code },
      })
    } else {
      setError(result.message || 'Registration failed.')
    }
  }

  return (
    <div className="auth-page">
      <div className="auth-card auth-card-wide">
        <div className="auth-brand">
          <div className="logo"></div>
          <h1>Create Account</h1>
          <p>Join us and track your laundry easily</p>
        </div>

        {error && <div className="alert-error">{error}</div>}

        <form onSubmit={handleSubmit}>
          <div className="field-row">
            <div className="field">
              <label htmlFor="first_name">First Name</label>
              <input id="first_name" name="first_name" type="text"
                value={form.first_name} onChange={handleChange} placeholder="Juan" />
            </div>
            <div className="field">
              <label htmlFor="last_name">Last Name</label>
              <input id="last_name" name="last_name" type="text"
                value={form.last_name} onChange={handleChange} placeholder="Dela Cruz" />
            </div>
          </div>

          <div className="field">
            <label htmlFor="email">Email</label>
            <input id="email" name="email" type="email"
              value={form.email} onChange={handleChange} placeholder="juan@email.com" />
          </div>

          <div className="field">
            <label htmlFor="phone">Phone Number</label>
            <input id="phone" name="phone" type="tel"
              value={form.phone} onChange={handleChange} placeholder="09XX XXX XXXX" />
          </div>

          <div className="field">
            <label htmlFor="address">Address</label>
            <input id="address" name="address" type="text"
              value={form.address} onChange={handleChange} placeholder="Street, Barangay, City" />
          </div>

          <div className="field">
            <label htmlFor="password">Password</label>
            <div className="password-wrap">
              <input id="password" name="password"
                type={showPassword ? 'text' : 'password'}
                value={form.password} onChange={handleChange}
                autoComplete="new-password" placeholder="At least 8 characters" />
              <button type="button" className="toggle-password"
                onClick={() => setShowPassword(!showPassword)}>
                {showPassword ? 'Hide' : 'Show'}
              </button>
            </div>
          </div>

          <div className="field">
            <label htmlFor="confirm_password">Confirm Password</label>
            <input id="confirm_password" name="confirm_password"
              type={showPassword ? 'text' : 'password'}
              value={form.confirm_password} onChange={handleChange}
              autoComplete="new-password" placeholder="Re-enter your password" />
          </div>

          <button type="submit" className="btn btn-primary" disabled={loading}>
            {loading ? 'Creating account...' : 'Register'}
          </button>
        </form>

        <Link to="/customer-login" className="btn btn-outline">
          Back to Login
        </Link>
      </div>
    </div>
  )
}