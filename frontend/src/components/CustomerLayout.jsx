import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { apiRequest } from '../services/api'

const links = [
  { to: '/customer/dashboard', label: 'Dashboard', icon: 'dashboard' },
  { to: '/customer/order-status', label: 'Order Status', icon: 'location_on' },
  { to: '/customer/loyalty', label: 'Loyalty Card', icon: 'loyalty' },
  { to: '/customer/orders', label: 'My Orders', icon: 'receipt_long' },
  { to: '/customer/payments', label: 'Payments', icon: 'payments' },
  { to: '/customer/pickup', label: 'Pickup', icon: 'local_shipping' },
  { to: '/customer/profile', label: 'Profile', icon: 'person' },
]

// Wraps every customer page: checks the login, shows the header and navigation
export default function CustomerLayout() {
  const navigate = useNavigate()
  const [customer, setCustomer] = useState(null)

  useEffect(() => {
    apiRequest('customer/me.php').then((result) => {
      if (result.success) {
        setCustomer(result.customer)
      } else {
        navigate('/customer-login')
      }
    })
  }, [navigate])

  async function handleLogout() {
    await apiRequest('customer/logout.php', { method: 'POST' })
    navigate('/customer-login')
  }

  if (!customer) return <div className="dash-page">Loading...</div>

  return (
    <div className="cust-shell">
      <header className="cust-header">
        <div className="cust-header-top">
          <Link to="/customer/dashboard" className="cust-brand">
            <span className="material-symbols-outlined brand-icon">local_laundry_service</span> Laundry Shop
          </Link>
          <div className="cust-user">
            <span className="hide-mobile">{customer.customer_code}</span>
            <button className="btn btn-outline btn-small" onClick={handleLogout}>
              Logout
            </button>
          </div>
        </div>

        <nav className="cust-nav">
          {links.map((link) => (
            <NavLink key={link.to} to={link.to}>
              <span className="material-symbols-outlined">{link.icon}</span> {link.label}
            </NavLink>
          ))}
        </nav>
      </header>

      <main className="cust-main">
        <Outlet context={{ customer, setCustomer }} />
      </main>
    </div>
  )
}