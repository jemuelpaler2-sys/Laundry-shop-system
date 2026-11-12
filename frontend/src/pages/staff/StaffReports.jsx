import { useEffect, useState } from 'react'
import { useOutletContext } from 'react-router-dom'
import { apiRequest } from '../../services/api'
import DateRangePicker from '../../components/DateRangePicker'
import MiniBarChart from '../../components/MiniBarChart'
import { formatPeso, formatDate, formatDateTime, formatQty } from '../../utils/orderStatus'

function shiftDays(n) {
  const d = new Date()
  d.setDate(d.getDate() + n)
  const pad = (v) => String(v).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

const TABS = [
  { key: 'sales', label: 'Sales' },
  { key: 'orders', label: 'Orders' },
  { key: 'customers', label: 'Customer Transactions' },
  { key: 'payments', label: 'Payment History' },
]

function bucketLabel(bucket, group) {
  if (group === 'monthly') {
    return new Date(`${bucket}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', year: 'numeric' })
  }
  return new Date(`${bucket}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })
}

export default function StaffReports() {
  const { staff } = useOutletContext()

  const [tab, setTab] = useState('sales')
  const [start, setStart] = useState(shiftDays(-29))
  const [end, setEnd] = useState(shiftDays(0))
  const [group, setGroup] = useState('daily')

  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [customerSearch, setCustomerSearch] = useState('')

  function handleRangeChange(s, e) {
    setStart(s)
    setEnd(e)
  }

  useEffect(() => {
    if (staff.role !== 'owner') return

    let active = true
    setLoading(true)
    setError('')

    const endpoints = {
      sales: `reports/sales.php?start=${start}&end=${end}&group=${group}`,
      orders: `reports/orders.php?start=${start}&end=${end}`,
      customers: `reports/customers.php?start=${start}&end=${end}&search=${encodeURIComponent(customerSearch.trim())}`,
      payments: `reports/payments.php?start=${start}&end=${end}`,
    }

    const timer = setTimeout(async () => {
      const result = await apiRequest(endpoints[tab])
      if (!active) return
      if (result.success) {
        setData(result)
      } else {
        setError(result.message || 'Could not load the report.')
      }
      setLoading(false)
    }, tab === 'customers' ? 300 : 0)

    return () => {
      active = false
      clearTimeout(timer)
    }
  }, [tab, start, end, group, customerSearch, staff.role])

  if (staff.role !== 'owner') {
    return (
      <div className="card">
        <p className="empty">Reports are only available to the shop owner.</p>
      </div>
    )
  }

  return (
    <div>
      <h1 className="page-title">Reports</h1>
      <p className="page-sub">Sales, orders, inventory, customers and payments at a glance.</p>

      <DateRangePicker start={start} end={end} onChange={handleRangeChange} />

      <div className="tab-row">
        {TABS.map((t) => (
          <button
            key={t.key}
            className={`tab ${tab === t.key ? 'tab-active' : ''}`}
            onClick={() => {
              setTab(t.key)
              setData(null)
              setLoading(true)
            }}
          >
            {t.label}
          </button>
        ))}
      </div>

      {error && <div className="alert-error">{error}</div>}
      {loading ? (
        <p className="loading-text">Loading the report...</p>
      ) : !data ? null : (
        <>
          {tab === 'sales' && (
            <>
              <div className="stat-grid stat-grid-2">
                <div className="stat-card">
                  <div className="stat-icon"><span className="material-symbols-outlined">payments</span></div>
                  <div>
                    <div className="stat-value stat-value-money">{formatPeso(data.totals.revenue)}</div>
                    <div className="stat-label">Total Revenue Collected</div>
                  </div>
                </div>
                <div className="stat-card">
                  <div className="stat-icon"><span className="material-symbols-outlined">receipt_long</span></div>
                  <div>
                    <div className="stat-value">{data.totals.orders}</div>
                    <div className="stat-label">Orders in this Range</div>
                  </div>
                </div>
              </div>

              <div className="card">
                <div className="card-head">
                  <h2>Revenue Over Time</h2>
                  <select value={group} onChange={(e) => setGroup(e.target.value)}>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                  </select>
                </div>
                <MiniBarChart
                  data={data.series.map((s) => ({ label: bucketLabel(s.bucket, group), value: s.total }))}
                  formatValue={formatPeso}
                />
              </div>
            </>
          )}

          {tab === 'orders' && (
            <>
              <div className="stat-grid">
                <div className="stat-card">
                  <div className="stat-icon"><span className="material-symbols-outlined">receipt_long</span></div>
                  <div><div className="stat-value">{data.totals.total}</div><div className="stat-label">Total Orders</div></div>
                </div>
                <div className="stat-card">
                  <div className="stat-icon"><span className="material-symbols-outlined">check_circle</span></div>
                  <div><div className="stat-value">{data.totals.completed}</div><div className="stat-label">Completed</div></div>
                </div>
                <div className="stat-card">
                  <div className="stat-icon"><span className="material-symbols-outlined">local_laundry_service</span></div>
                  <div><div className="stat-value">{data.totals.pending}</div><div className="stat-label">Pending</div></div>
                </div>
                <div className="stat-card stat-card-alert">
                  <div className="stat-icon"><span className="material-symbols-outlined">cancel</span></div>
                  <div><div className="stat-value">{data.totals.cancelled}</div><div className="stat-label">Cancelled</div></div>
                </div>
              </div>

              <div className="card">
                <h2>Breakdown by Status</h2>
                <MiniBarChart
                  data={Object.entries(data.by_status)
                    .filter(([, v]) => v > 0)
                    .map(([k, v]) => ({ label: k.replace(/_/g, ' '), value: v }))}
                />
              </div>
            </>
          )}
          
          {tab === 'customers' && (
            <div className="card">
              <div className="field" style={{ maxWidth: 360, marginBottom: 16 }}>
                <label htmlFor="rep-cust-search">Search</label>
                <input id="rep-cust-search" type="text" value={customerSearch}
                  onChange={(e) => setCustomerSearch(e.target.value)}
                  placeholder="Name or Customer ID" />
              </div>

              {data.customers.length === 0 ? (
                <p className="empty">No customers had orders in this range.</p>
              ) : (
                <div className="table-wrap">
                  <table className="data-table">
                    <thead>
                      <tr><th>Customer</th><th>Orders</th><th>Total Billed</th><th>Total Paid</th></tr>
                    </thead>
                    <tbody>
                      {data.customers.map((c) => (
                        <tr key={c.customer_code}>
                          <td>{c.customer_name} <div className="muted">{c.customer_code}</div></td>
                          <td>{c.order_count}</td>
                          <td>{formatPeso(c.total_billed)}</td>
                          <td>{formatPeso(c.total_paid)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          )}

          {tab === 'payments' && (
            <>
              <div className="stat-grid stat-grid-3">
                {Object.entries(data.by_method).map(([method, amount]) => (
                  <div key={method} className="stat-card">
                    <div className="stat-icon"><span className="material-symbols-outlined">payments</span></div>
                    <div>
                      <div className="stat-value stat-value-money">{formatPeso(amount)}</div>
                      <div className="stat-label">{method}</div>
                    </div>
                  </div>
                ))}
              </div>

              <div className="card">
                <div className="card-head">
                  <h2>Payments</h2>
                  <span className="muted">Total: {formatPeso(data.total)}</span>
                </div>
                {data.payments.length === 0 ? (
                  <p className="empty">No payments were recorded in this range.</p>
                ) : (
                  <div className="table-wrap">
                    <table className="data-table">
                      <thead>
                        <tr><th>Date</th><th>Order</th><th>Customer</th><th>Amount</th><th>Method</th></tr>
                      </thead>
                      <tbody>
                        {data.payments.map((p, index) => (
                          <tr key={index}>
                            <td>{formatDateTime(p.payment_date)}</td>
                            <td><strong>{p.order_number}</strong></td>
                            <td>{p.customer_name} <div className="muted">{p.customer_code}</div></td>
                            <td>{formatPeso(p.amount)}</td>
                            <td>{p.payment_method}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </>
          )}
        </>
      )}
    </div>
  )
}