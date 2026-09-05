// Local dev (this repo's XAMPP setup) keeps its explicit localhost path; any other
// host (e.g. a production domain) assumes the backend is deployed alongside the
// frontend at <same origin>/backend/public - no per-deploy edit needed.
const API_BASE = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
  ? 'http://localhost/epa-framework/backend/public/api/v1'
  : window.location.origin + '/backend/public/api/v1';

async function api(path, { method = 'GET', body } = {}) {
  const headers = { 'Content-Type': 'application/json' };
  const token = localStorage.getItem('epa_token');
  if (token) headers['Authorization'] = 'Bearer ' + token;

  let res;
  try {
    res = await fetch(API_BASE + path, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw new Error(`Tidak dapat terhubung ke backend API di ${API_BASE}. Pastikan server PHP sudah berjalan.`);
  }

  const json = await res.json().catch(() => ({}));
  // A 401 on the login page itself just means "wrong credentials" - let it fall
  // through to the throw below so the login form can show the message. A 401
  // anywhere else means the session expired, so clear it and bounce to login.
  const onLoginPage = /(^|\/)index\.html$/.test(window.location.pathname) || window.location.pathname === '/';
  if (res.status === 401 && !onLoginPage) {
    localStorage.removeItem('epa_token');
    localStorage.removeItem('epa_user');
    window.location.href = 'index.html';
    return;
  }
  if (!res.ok) {
    throw new Error(json.message || json.error || 'Request failed');
  }
  return json.data;
}
