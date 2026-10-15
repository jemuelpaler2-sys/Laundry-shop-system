// Local date (YYYY-MM-DD) n days before/after today
function shiftDays(n) {
  const d = new Date()
  d.setDate(d.getDate() + n)
  const pad = (v) => String(v).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function startOfWeek() {
  const d = new Date()
  const day = d.getDay() === 0 ? 6 : d.getDay() - 1 // Monday-based week
  d.setDate(d.getDate() - day)
  const pad = (v) => String(v).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function startOfMonth() {
  const d = new Date()
  const pad = (v) => String(v).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-01`
}

const today = () => shiftDays(0)

export default function DateRangePicker({ start, end, onChange }) {
  function setRange(s, e) {
    onChange(s, e)
  }

  return (
    <div className="card">
      <div className="date-range-fields">
        <div>
          <label htmlFor="rep-start">From</label>
          <input id="rep-start" type="date" value={start} max={end}
            onChange={(e) => onChange(e.target.value, end)} />
        </div>
        <div>
          <label htmlFor="rep-end">To</label>
          <input id="rep-end" type="date" value={end} min={start} max={today()}
            onChange={(e) => onChange(start, e.target.value)} />
        </div>
      </div>

      <div className="quick-range">
        <button type="button" className="btn-link" onClick={() => setRange(today(), today())}>Today</button>
        <button type="button" className="btn-link" onClick={() => setRange(startOfWeek(), today())}>This Week</button>
        <button type="button" className="btn-link" onClick={() => setRange(startOfMonth(), today())}>This Month</button>
        <button type="button" className="btn-link" onClick={() => setRange(shiftDays(-29), today())}>Last 30 Days</button>
      </div>
    </div>
  )
}