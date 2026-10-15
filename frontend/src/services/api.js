// Base URL of the PHP backend (served by XAMPP Apache)
export const API_BASE = 'http://localhost/laundry-shop-system/backend'

// Helper used by every page to call the PHP APIs
export async function apiRequest(path, options = {}) {
  const { method = 'GET', body } = options

  try {
    const response = await fetch(`${API_BASE}/${path}`, {
      method,
      credentials: 'include', // sends the login session cookie
      headers: body ? { 'Content-Type': 'application/json' } : {},
      body: body ? JSON.stringify(body) : undefined,
    })
    const data = await response.json()
    return { ok: response.ok, status: response.status, ...data }
  } catch (error) {
    return {
      ok: false,
      success: false,
      message: 'Cannot reach the server. Is Apache running?',
    }
  }
}