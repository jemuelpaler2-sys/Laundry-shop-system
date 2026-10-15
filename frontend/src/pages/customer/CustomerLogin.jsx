import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { apiRequest } from '../../services/api'

export default function CustomerLogin() {
  const navigate = useNavigate()
  const location = useLocation()
  const registered = location.state?.registered
  const customerCode = location.state?.customerCode

  const [identifier, setIdentifier] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')

    if (!identifier.trim() || !password) {
      setError('Please enter your email or Customer ID and your password.')
      return
    }

    setLoading(true)
    const result = await apiRequest('customer/login.php', {
      method: 'POST',
      body: { identifier: identifier.trim(), password },
    })
    setLoading(false)

    if (result.success) {
      navigate('/customer/dashboard')
    } else {
      setError(result.message || 'Login failed.')
    }
  }

  return (
    <div className="auth-page">
      <div className="auth-card">
        <div className="auth-brand">
          <div className="logo"></div>
          <h1>Customer Login</h1>
          <p>Welcome back! Track your laundry easily.</p>
        </div>

        {registered && (
          <div className="alert-success">
            Registration successful! Your Customer ID is <strong>{customerCode}</strong>.
            You can log in with it or with your email.
          </div>
        )}

        {error && <div className="alert-error">{error}</div>}

        <form onSubmit={handleSubmit}>
          <div className="field">
            <label htmlFor="identifier">Email or Customer ID</label>
            <input
              id="identifier"
              type="text"
              value={identifier}
              onChange={(e) => setIdentifier(e.target.value)}
              autoComplete="username"
              placeholder="juan@email.com or CUST-1001"
            />
          </div>

          <div className="field">
            <label htmlFor="password">Password</label>
            <div className="password-wrap">
              <input
                id="password"
                type={showPassword ? 'text' : 'password'}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                autoComplete="current-password"
                placeholder="Enter your password"
              />
              <button
                type="button"
                className="toggle-password"
                onClick={() => setShowPassword(!showPassword)}
              >
                {showPassword ? 'Hide' : 'Show'}
              </button>
            </div>
          </div>

          <button type="submit" className="btn btn-primary" disabled={loading}>
            {loading ? 'Logging in...' : 'Login'}
          </button>
        </form>

        <div className="divider"><span>New here?</span></div>

        <Link to="/customer-register" className="btn btn-outline">
          Register
        </Link>
        <Link to="/" className="link-small">
          ← Back to Home
        </Link>
        
      </div>
    </div>
  )
}