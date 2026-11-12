import { Link } from 'react-router-dom'

const features = [
  {
    icon: 'shopping_basket',
    title: 'Easy Ordering',
    text: 'Drop off your laundry and we log your order right away. No paperwork, no confusion.',
  },
  {
    icon: 'location_on',
    title: 'Real-Time Order Tracking',
    text: 'See exactly where your laundry is, from washing and drying to folding and ready for pickup.',
  },
  {
    icon: 'lock',
    title: 'Secure & Reliable',
    text: 'Your account is protected, and your orders, payments and pickup schedules are always on record.',
  },
]

// Sample tracker shown in the hero card (for display only)
const sampleSteps = [
  { label: 'Drop-off', state: 'done' },
  { label: 'Washing', state: 'current' },
  { label: 'Drying', state: 'todo' },
  { label: 'Folding', state: 'todo' },
  { label: 'Ready for Pickup', state: 'todo' },
]

export default function LandingPage() {
  return (
    <div className="landing">
      {/* ---------- Top bar ---------- */}
      <header className="landing-nav">
        <Link to="/" className="landing-brand">
          <span className="material-symbols-outlined brand-icon">local_laundry_service</span> Laundry Shop
        </Link>
        <nav className="landing-nav-links">
          <a href="#features" className="hide-mobile">Features</a>
          <Link to="/customer-login" className="nav-pill"> Customer Login</Link>
        </nav>
      </header>

      {/* ---------- Hero ---------- */}
      <section className="hero">
        <span className="bubble b1"></span>
        <span className="bubble b2"></span>
        <span className="bubble b3"></span>

        <div className="hero-inner">
          <div>
            <span className="hero-tag">
              <span className="material-symbols-outlined">auto_awesome</span> Fresh. Clean. On time.
            </span>
            <h1>
              Clean Clothes, <span>Happy Lives</span>
            </h1>
            <p>
              Manage your laundry orders, track your laundry status, and keep
              your shop running smoothly in one system.
            </p>
            <div className="hero-buttons">
              <Link to="/customer-login" className="btn btn-primary btn-no-margin">
                Customer Login
              </Link>
            </div>
          </div>

          <div className="preview-card">
            <div className="preview-top">
              <div>
                <small className="preview-label">Sample order</small>
                <h3>Order #ORD-1005</h3>
                <p>Wash &amp; Fold · 3.5 kg</p>
              </div>
              <span className="status-badge">Washing</span>
            </div>

            <ul className="track">
              {sampleSteps.map((step) => (
                <li key={step.label} className={`track-step ${step.state}`}>
                  <span className="dot">
                    {step.state === 'done' ? (
                      <span className="material-symbols-outlined">check</span>
                    ) : (
                      ''
                    )}
                  </span>
                  {step.label}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </section>

      {/* ---------- Features ---------- */}
      <section id="features" className="section">
        <h2>Everything you need for your laundry</h2>
        <p className="section-sub">
          Simple for customers, organized for the shop.
        </p>

        <div className="feature-grid">
          {features.map((f) => (
            <div key={f.title} className="feature-card">
              <div className="feature-icon">
                <span className="material-symbols-outlined">{f.icon}</span>
              </div>
              <h3>{f.title}</h3>
              <p>{f.text}</p>
            </div>
          ))}
        </div>
      </section>

      {/* ---------- Call to action ---------- */}
      <section className="cta-band">
        <h2>Ready to get started?</h2>
        <p>Create a free customer account and track your next laundry order.</p>
        <Link to="/customer-register" className="btn btn-light">
          Register Now
        </Link>
      </section>

      {/* ---------- Footer ---------- */}
      <footer className="landing-footer">
        © {new Date().getFullYear()} Laundry Shop · Clean Clothes, Happy Lives
      </footer>
    </div>
  )
}