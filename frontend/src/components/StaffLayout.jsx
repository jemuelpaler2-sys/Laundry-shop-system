import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { apiRequest } from '../services/api'

const links = [
  { to: '/staff/dashboard', label: 'Dashboard', icon: 'dashboard' },
  { to: '/staff/customers', label: 'Customers', icon: 'groups' },
  { to: '/staff/loyalty', label: 'Loyalty & Rewards', icon: 'loyalty' },
  { to: '/staff/orders', label: 'Orders', icon: 'receipt_long' },
  { to: '/staff/load-status', label: 'Load Status', icon: 'local_laundry_service' },
  { to: '/staff/pickups', label: 'Pickup Scheduling', icon: 'local_shipping' },
  { to: '/staff/payments', label: 'Payments', icon: 'payments' },
  { to: '/staff/reports', label: 'Reports', icon: 'bar_chart' },
  { to: '/staff/account', label: 'Staff Account', icon: 'person' },
]

// Wraps every staff page: checks the staff login, shows the sidebar and top bar
export default function StaffLayout() {
  const navigate = useNavigate()
  const [staff, setStaff] = useState(null)
  const [menuOpen, setMenuOpen] = useState(false)

  useEffect(() => {
    apiRequest('staff/me.php').then((result) => {
      if (result.success) {
        setStaff(result.staff)
      } else {
        navigate('/staff-login')
      }
    })
  }, [navigate])

  async function handleLogout() {
    await apiRequest('staff/logout.php', { method: 'POST' })
    navigate('/staff-login')
  }

  if (!staff) return <div className="dash-page">Loading...</div>

  const roleLabel = staff.role === 'owner' ? 'Shop Owner' : 'Laundry Staff'

  return (
    <div className="staff-shell">
      <div
        className={`sidebar-overlay ${menuOpen ? 'show' : ''}`}
        onClick={() => setMenuOpen(false)}
      ></div>

      <aside className={`staff-sidebar ${menuOpen ? 'open' : ''}`}>
        <Link to="/staff/dashboard" className="staff-brand" onClick={() => setMenuOpen(false)}>
          <span className="material-symbols-outlined brand-icon">local_laundry_service</span>
          <div>
            Laundry Shop
            <small>Staff Portal</small>
          </div>
        </Link>

        <nav className="staff-nav">
          {links.map((link) => (
            <NavLink key={link.to} to={link.to} onClick={() => setMenuOpen(false)}>
              <span className="material-symbols-outlined">{link.icon}</span> {link.label}
            </NavLink>
          ))}
        </nav>

        <div className="staff-side-footer">
          <button onClick={handleLogout}>Logout</button>
        </div>
      </aside>

      <div className="staff-content">
        <header className="staff-topbar">
          <button className="menu-btn" onClick={() => setMenuOpen(true)} aria-label="Open menu">
            <span className="material-symbols-outlined">menu</span>
          </button>
          <div className="staff-user">
            <span className="hide-mobile">
              {staff.first_name} {staff.last_name}
            </span>
            <span className="role-pill">{roleLabel}</span>
          </div>
        </header>

        <main className="staff-main">
          <Outlet context={{ staff }} />
        </main>
      </div>
    </div>
  )
}