export default function ComingSoon({ title, step }) {
  return (
    <div className="card coming-soon">
      <div className="coming-soon-icon">🚧</div>
      <h2>{title}</h2>
      <p>This page will be built in Step {step}.</p>
    </div>
  )
}