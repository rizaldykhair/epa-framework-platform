function getToken() {
  return localStorage.getItem('epa_token');
}

function getUser() {
  try {
    return JSON.parse(localStorage.getItem('epa_user') || 'null');
  } catch {
    return null;
  }
}

function setSession(token, user) {
  localStorage.setItem('epa_token', token);
  localStorage.setItem('epa_user', JSON.stringify(user));
}

function clearSession() {
  localStorage.removeItem('epa_token');
  localStorage.removeItem('epa_user');
}

function logout() {
  clearSession();
  window.location.href = 'index.html';
}
