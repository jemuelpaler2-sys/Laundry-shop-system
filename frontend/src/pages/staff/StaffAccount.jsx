import { useEffect, useState } from 'react'
import { useOutletContext } from 'react-router-dom'
import { apiRequest } from '../../services/api'
import StaffFormModal from '../../components/StaffFormModal'
import { formatDate } from '../../utils/orderStatus'

function Message({ msg }) {
  if (!msg.text) return null
  return <div className={msg.type === 'success' ? 'alert-success' : 'alert-error'}>{msg.text}</div>
}

export default function StaffAccount() {
  const { staff } = useOutletContext()
  const isOwner = staff.role === 'owner'
  const [tab, setTab] = useState('me')

  // ----- My Account -----
  const [profileForm, setProfileForm] = useState({
    first_name: staff.first_name,
    last_name: staff.last_name,
  })
  const [profileMsg, setProfileMsg] = useState({ type: '', text: '' })
  const [savingProfile, setSavingProfile] = useState(false)

  async function handleProfileSubmit(e) {
    e.preventDefault()
    setProfileMsg({ type: '', text: '' })

    if (!profileForm.first_name.trim() || !profileForm.last_name.trim()) {
      setProfileMsg({ type: 'error', text: 'Please enter your first and last name.' })
      return
    }

    setSavingProfile(true)
    const result = await apiRequest('staff/update-own-profile.php', {
      method: 'POST',
      body: profileForm,
    })
    setSavingProfile(false)

    setProfileMsg({
      type: result.success ? 'success' : 'error',
      text: result.message || 'Could not update your profile.',
    })
  }

  const [pw, setPw] = useState({ current_password: '', new_password: '', confirm_password: '' })
  const [showPw, setShowPw] = useState(false)
  const [pwMsg, setPwMsg] = useState({ type: '', text: '' })
  const [savingPw, setSavingPw] = useState(false)

  async function handlePasswordSubmit(e) {
    e.preventDefault()
    setPwMsg({ type: '', text: '' })

    if (!pw.current_password || !pw.new_password || !pw.confirm_password) {
      return setPwMsg({ type: 'error', text: 'Please fill in all password fields.' })
    }
    if (pw.new_password.length < 8) {
      return setPwMsg({ type: 'error', text: 'New password must be at least 8 characters.' })
    }
    if (pw.new_password !== pw.confirm_password) {
      return setPwMsg({ type: 'error', text: 'New passwords do not match.' })
    }

    setSavingPw(true)
    const result = await apiRequest('staff/change-own-password.php', { method: 'POST', body: pw })
    setSavingPw(false)

    if (result.success) {
      setPw({ current_password: '', new_password: '', confirm_password: '' })
    }
    setPwMsg({
      type: result.success ? 'success' : 'error',
      text: result.message || 'Could not change your password.',
    })
  }

  // ----- Manage Staff (owner only) -----
  const [staffList, setStaffList] = useState([])
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [loadingList, setLoadingList] = useState(true)
  const [listError, setListError] = useState('')
  const [listNotice, setListNotice] = useState({ type: '', text: '' })
  const [reloadKey, setReloadKey] = useState(0)
  const [formMember, setFormMember] = useState(undefined) // undefined = closed, null = new, object = edit

  useEffect(() => {
    if (!isOwner || tab !== 'manage') return
    const timer = setTimeout(async () => {
      const params = new URLSearchParams({ search: search.trim(), status: statusFilter })
      const result = await apiRequest(`staff/list.php?${params.toString()}`)
      if (result.success) {
        setStaffList(result.staff)
        setListError('')
      } else {
        setListError(result.message || 'Could not load staff accounts.')
      }
      setLoadingList(false)
    }, 300)
    return () => clearTimeout(timer)
  }, [isOwner, tab, search, statusFilter, reloadKey])

  function handleSaved(message) {
    setFormMember(undefined)
    setListNotice({ type: 'success', text: message })
    setReloadKey((k) => k + 1)
  }

  async function handleToggleStatus(member) {
    const newStatus = member.status === 'active' ? 'inactive' : 'active'
    const question =
      newStatus === 'inactive'
        ? `Deactivate ${member.first_name} ${member.last_name}? They will not be able to log in.`
        : `Activate ${member.first_name} ${member.last_name}?`
    if (!window.confirm(question)) return

    const result = await apiRequest('staff/status.php', {
      method: 'POST',
      body: { id: member.id, status: newStatus },
    })
    setListNotice({
      type: result.success ? 'success' : 'error',
      text: result.message || 'Could not update the account.',
    })
    setReloadKey((k) => k + 1)
  }

  return (
    <div>
      <h1 className="page-title">Staff Account</h1>
      <p className="page-sub">Manage your own account{isOwner ? ' and the shop\u2019s staff accounts.' : '.'}</p>

      {isOwner && (
        <div className="tab-row">
          <button className={`tab ${tab === 'me' ? 'tab-active' : ''}`} onClick={() => setTab('me')}>
            My Account
          </button>
          <button className={`tab ${tab === 'manage' ? 'tab-active' : ''}`} onClick={() => setTab('manage')}>
            Manage Staff
          </button>
        </div>
      )}

      {tab === 'me' && (
        <>
          <div className="card form-card">
            <h2>Personal Information</h2>
            <Message msg={profileMsg} />
            <form onSubmit={handleProfileSubmit}>
              <div className="field-row">
                <div className="field">
                  <label htmlFor="sa_username">Username</label>
                  <input id="sa_username" type="text" value={staff.username} readOnly />
                </div>
                <div className="field">
                  <label htmlFor="sa_role">Role</label>
                  <input id="sa_role" type="text" value={staff.role === 'owner' ? 'Shop Owner' : 'Laundry Staff'} readOnly />
                </div>
              </div>
              <div className="field-row">
                <div className="field">
                  <label htmlFor="sa_first_name">First Name</label>
                  <input id="sa_first_name" type="text" value={profileForm.first_name}
                    onChange={(e) => setProfileForm({ ...profileForm, first_name: e.target.value })} />
                </div>
                <div className="field">
                  <label htmlFor="sa_last_name">Last Name</label>
                  <input id="sa_last_name" type="text" value={profileForm.last_name}
                    onChange={(e) => setProfileForm({ ...profileForm, last_name: e.target.value })} />
                </div>
              </div>
              <button type="submit" className="btn btn-primary" disabled={savingProfile}>
                {savingProfile ? 'Saving...' : 'Save Changes'}
              </button>
            </form>
          </div>

          <div className="card form-card">
            <h2>Change Password</h2>
            <Message msg={pwMsg} />
            <form onSubmit={handlePasswordSubmit}>
              <div className="field">
                <label htmlFor="sa_current_password">Current Password</label>
                <div className="password-wrap">
                  <input id="sa_current_password" type={showPw ? 'text' : 'password'}
                    value={pw.current_password}
                    onChange={(e) => setPw({ ...pw, current_password: e.target.value })}
                    autoComplete="current-password" />
                  <button type="button" className="toggle-password" onClick={() => setShowPw(!showPw)}>
                    {showPw ? 'Hide' : 'Show'}
                  </button>
                </div>
              </div>
              <div className="field">
                <label htmlFor="sa_new_password">New Password</label>
                <input id="sa_new_password" type={showPw ? 'text' : 'password'}
                  value={pw.new_password} onChange={(e) => setPw({ ...pw, new_password: e.target.value })}
                  autoComplete="new-password" placeholder="At least 8 characters" />
              </div>
              <div className="field">
                <label htmlFor="sa_confirm_password">Confirm New Password</label>
                <input id="sa_confirm_password" type={showPw ? 'text' : 'password'}
                  value={pw.confirm_password} onChange={(e) => setPw({ ...pw, confirm_password: e.target.value })}
                  autoComplete="new-password" />
              </div>
              <button type="submit" className="btn btn-primary" disabled={savingPw}>
                {savingPw ? 'Changing...' : 'Change Password'}
              </button>
            </form>
          </div>
        </>
      )}

      {tab === 'manage' && isOwner && (
        <>
          {listNotice.text && (
            <div className={listNotice.type === 'success' ? 'alert-success' : 'alert-error'}>
              {listNotice.text}
            </div>
          )}
          {listError && <div className="alert-error">{listError}</div>}

          <div className="card">
            <div className="finder finder-3">
              <div>
                <label htmlFor="staff-search">Search</label>
                <input id="staff-search" type="text" value={search}
                  onChange={(e) => setSearch(e.target.value)} placeholder="Username or name" />
              </div>
              <div>
                <label htmlFor="staff-status">Status</label>
                <select id="staff-status" value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
                  <option value="all">All accounts</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
              <button className="btn btn-primary" onClick={() => setFormMember(null)}>
                + Add Staff Account
              </button>
            </div>
          </div>

          <div className="card">
            {loadingList ? (
              <p className="loading-text">Loading staff accounts...</p>
            ) : staffList.length === 0 ? (
              <p className="empty">No staff accounts match your search.</p>
            ) : (
              <div className="table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>Username</th><th>Name</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {staffList.map((m) => (
                      <tr key={m.id}>
                        <td><strong>{m.username}</strong></td>
                        <td>{m.first_name} {m.last_name}</td>
                        <td>{m.role === 'owner' ? 'Shop Owner' : 'Laundry Staff'}</td>
                        <td>
                          <span className={`badge badge-${m.status}`}>
                            {m.status === 'active' ? 'Active' : 'Inactive'}
                          </span>
                        </td>
                        <td>{formatDate(m.created_at)}</td>
                        <td>
                          <div className="row-actions">
                            <button className="btn-link" onClick={() => setFormMember(m)}>Edit</button>
                            {m.id !== staff.id && (
                              <button
                                className={`btn-link ${m.status === 'active' ? 'btn-link-danger' : ''}`}
                                onClick={() => handleToggleStatus(m)}
                              >
                                {m.status === 'active' ? 'Deactivate' : 'Activate'}
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      )}

      {formMember !== undefined && (
        <StaffFormModal
          member={formMember}
          onClose={() => setFormMember(undefined)}
          onSaved={handleSaved}
        />
      )}
    </div>
  )
}