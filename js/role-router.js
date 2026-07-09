const ROLE_DASHBOARD = {
  Admin: 'admin-dashboard.html',
  'Product Owner': 'product-owner-dashboard.html',
  Developer: 'developer-dashboard.html',
  Tester: 'tester-dashboard.html',
  Evaluator: 'evaluator-dashboard.html',
};

function redirectToDashboard(role) {
  window.location.href = ROLE_DASHBOARD[role] || 'index.html';
}

/** Call at the top of every role dashboard page: bounces away if not logged in
 *  or logged in as a different role than the page expects. Returns the user object. */
function guardDashboard(expectedRole) {
  const user = getUser();
  if (!getToken() || !user) {
    window.location.href = 'index.html';
    return null;
  }
  if (user.role !== expectedRole) {
    redirectToDashboard(user.role);
    return null;
  }
  return user;
}
