// Simple bar chart drawn with plain CSS (no chart library needed).
// data: [{ label, value }]
export default function MiniBarChart({ data, formatValue }) {
  const max = Math.max(1, ...data.map((d) => d.value))

  if (data.length === 0) {
    return <p className="empty">No data for this range.</p>
  }

  return (
    <div className="bar-chart">
      {data.map((d, index) => (
        <div key={index} className="bar-row">
          <span className="bar-label">{d.label}</span>
          <div className="bar-track">
            <div
              className="bar-fill"
              style={{ width: `${Math.max(2, (d.value / max) * 100)}%` }}
            ></div>
          </div>
          <span className="bar-value">{formatValue ? formatValue(d.value) : d.value}</span>
        </div>
      ))}
    </div>
  )
}