import { useEffect, useState } from 'react'
import { useOutletContext } from 'react-router-dom'
import { apiRequest } from '../../services/api'
import CustomerFormModal from '../../components/CustomerFormModal'
import CustomerDetailsModal from '../../components/CustomerDetailsModal'
import CustomerDeleteModal from '../../components/CustomerDeleteModal'

export default function StaffCustomers() {
  const { staff } = useOutletContext()
  const isOwner = staff.role === 'owner'

  const [customers, setCustomers] = useState([])
  const [total, setTotal] = useState(0)
  const [totalPages, setTotalPages] = useState(1)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState({ type: '', text: '' })
  const [reloadKey, setReloadKey] = useState(0)

  const [viewId, setViewId] = useState(null)
  // undefined = form closed, null = adding a customer, object = editing that customer
  const [formCustomer, setFormCustomer] = useState(undefined)
  // undefined = delete dialog closed, object = customer awaiting confirmation
  const [deleteCustomer, setDeleteCustomer] = useState(undefined)

  // Load one page of customers (waits 300 ms while the user is still typing)
  useEffect(() => {
    const timer = setTimeout(async () => {
      const params = new URLSearchParams({
        search: search.trim(),
        status: statusFilter,
        page: String(page),
      })
      const result = await apiRequest(`customers/list.php?${params.toString()}`)
      if (result.success) {
        setCustomers(result.customers)
        setTotal(result.total)
        setTotalPages(result.total_pages)
        setError('')
      } else {
        setError(result.message || 'Could not load customers.')
      }
      setLoading(false)
    }, 300)

    return () => clearTimeout(timer)
  }, [search, statusFilter, page, reloadKey])

  function handleSearchChange(e) {
    setSearch(e.target.value)
    setPage(1)
  }

  function handleStatusChange(e) {
    setStatusFilter(e.target.value)
    setPage(1)
  }

  function clearFilters() {
    setSearch('')
    setStatusFilter('all')
    setPage(1)
  }

  function handleSaved(message) {
    setFormCustomer(undefined)
    setNotice({ type: 'success', text: message })
    setReloadKey((k) => k + 1)
  }

  async function handleToggleStatus(c) {
    const newStatus = c.status === 'active' ? 'inactive' : 'active'
    const question =
      newStatus === 'inactive'
        ? `Deactivate ${c.first_name} ${c.last_name}? They will not be able to log in.`
        : `Activate ${c.first_name} ${c.last_name}?`

    if (!window.confirm(question)) return

    const result = await apiRequest('customers/status.php', {
      method: 'POST',
      body: { id: c.id, status: newStatus },
    })

    if (result.success) {
      setNotice({ type: 'success', text: result.message })
      setReloadKey((k) => k + 1)
    } else {
      setNotice({ type: 'error', text: result.message || 'Could not update the customer.' })
    }
  }

  function handleDeleted(message) {
    setDeleteCustomer(undefined)
    setNotice({ type: 'success', text: message })
    // If that was the only row on a later page, step back a page; otherwise
    // just reload the current one. Either way the table refreshes on its own.
    if (customers.length === 1 && page > 1) {
      setPage((p) => p - 1)
    } else {
      setReloadKey((k) => k + 1)
    }
  }

  return (
    <div>
      <h1 className="page-title">Customer Management</h1>
      <p className="page-sub">
        {isOwner
          ? 'View, add, edit and deactivate customers.'
          : 'Look up customer details.'}
      </p>

      {notice.text && (
        <div className={notice.type === 'success' ? 'alert-success' : 'alert-error'}>
          {notice.text}
        </div>
      )}
      {error && <div className="alert-error">{error}</div>}

      {/* Search, filter and add */}
      <div className="card">
        <div className="finder finder-3">
          <div>
            <label htmlFor="cust-search">Search</label>
            <input
              id="cust-search"
              type="text"
              value={search}
              onChange={handleSearchChange}
              placeholder="Name, email, phone or Customer ID"
            />
          </div>

          <div>
            <label htmlFor="cust-status">Status</label>
            <select id="cust-status" value={statusFilter} onChange={handleStatusChange}>
              <option value="all">All customers</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>

          {isOwner ? (
            <button className="btn btn-primary" onClick={() => setFormCustomer(null)}>
              + Add Customer
            </button>
          ) : (
            <button className="btn btn-outline" onClick={clearFilters}>Clear</button>
          )}
        </div>
        {isOwner && (search || statusFilter !== 'all') && (
          <button className="btn-link" onClick={clearFilters}>Clear filters</button>
        )}
      </div>

      {/* Customers table */}
      <div className="card">
        {loading ? (
          <p className="loading-text">Loading customers...</p>
        ) : (
          <>
            <p className="muted result-count">
              {total} {total === 1 ? 'customer' : 'customers'} found
            </p>

            {customers.length === 0 ? (
              <p className="empty">No customers match your search.</p>
            ) : (
              <div className="table-wrap">
                <table className="data-table cust-table">
                  <thead>
                    <tr>
                      <th>Customer ID</th>
                      <th>Name</th>
                      <th>Email</th>
                      <th>Phone</th>
                      <th>Orders</th>
                      <th>Status</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {customers.map((c) => (
                      <tr key={c.id}>
                        <td><strong>{c.customer_code}</strong></td>
                        <td>{c.first_name} {c.last_name}</td>
                        <td>{c.email}</td>
                        <td>{c.phone}</td>
                        <td>{c.order_count}</td>
                        <td>
                          <span className={`badge badge-${c.status}`}>
                            {c.status === 'active' ? 'Active' : 'Inactive'}
                          </span>
                        </td>
                        <td>
                          <div className="row-actions">
                            <button className="btn-link" onClick={() => setViewId(c.id)}>
                              View
                            </button>
                            {isOwner && (
                              <>
                                <button className="btn-link" onClick={() => setFormCustomer(c)}>
                                  Edit
                                </button>
                                <button
                                  className={`btn-link ${c.status === 'active' ? 'btn-link-danger' : ''}`}
                                  onClick={() => handleToggleStatus(c)}
                                >
                                  {c.status === 'active' ? 'Deactivate' : 'Activate'}
                                </button>
                                <button
                                  className="btn-link btn-link-danger"
                                  onClick={() => setDeleteCustomer(c)}
                                >
                                  Delete
                                </button>
                              </>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {totalPages > 1 && (
              <div className="pager">
                <button onClick={() => setPage(page - 1)} disabled={page <= 1}>
                  ← Previous
                </button>
                <span>Page {page} of {totalPages}</span>
                <button onClick={() => setPage(page + 1)} disabled={page >= totalPages}>
                  Next →
                </button>
              </div>
            )}
          </>
        )}
      </div>

      {viewId && (
        <CustomerDetailsModal customerId={viewId} onClose={() => setViewId(null)} />
      )}

      {formCustomer !== undefined && (
        <CustomerFormModal
          customer={formCustomer}
          onClose={() => setFormCustomer(undefined)}
          onSaved={handleSaved}
        />
      )}

      {deleteCustomer !== undefined && (
        <CustomerDeleteModal
          customer={deleteCustomer}
          onClose={() => setDeleteCustomer(undefined)}
          onDeleted={handleDeleted}
        />
      )}
    </div>
  )
}