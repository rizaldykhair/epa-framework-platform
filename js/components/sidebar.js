const ROLE_MENUS = {
  Admin: [
    { key: 'dashboard', label: 'Dashboard' },
    { key: 'users', label: 'Users' },
    { key: 'roles', label: 'Roles' },
    { key: 'projects', label: 'Projects' },
    { key: 'phases', label: 'EPA Phases' },
    { key: 'backlog', label: 'Backlog' },
    { key: 'sprints', label: 'Sprints' },
    { key: 'prototypes', label: 'Prototypes' },
    { key: 'feedback', label: 'Feedback' },
    { key: 'testing', label: 'Testing' },
    { key: 'defects', label: 'Defects' },
    { key: 'release', label: 'Release' },
    { key: 'retrospective', label: 'Retrospective' },
    { key: 'reports', label: 'Reports' },
    { key: 'audit', label: 'Audit Logs' },
    { key: 'settings', label: 'Settings' },
    { href: 'ai-project-generator.html', label: 'AI Project Generator' },
    { href: 'ai-project-generator.html#history', label: 'AI Generation History' },
    { href: 'epa-workboard.html', label: 'EPA Workboard' },
  ],
  'Product Owner': [
    { key: 'dashboard', label: 'Dashboard' },
    { key: 'projects', label: 'My Projects' },
    { key: 'requirements', label: 'Requirements' },
    { key: 'backlog', label: 'Backlog' },
    { key: 'sprint-backlog', label: 'Sprint Backlog' },
    { key: 'feedback-review', label: 'Feedback Review' },
    { key: 'prototype-review', label: 'Prototype Review' },
    { key: 'release', label: 'Release Checklist' },
    { key: 'retrospective', label: 'Retrospective' },
    { key: 'reports', label: 'Reports' },
    { href: 'ai-project-generator.html', label: 'AI Project Generator' },
    { href: 'ai-project-generator.html#history', label: 'Generated Project Proposal' },
    { href: 'epa-workboard.html', label: 'EPA Workboard' },
  ],
  Developer: [
    { key: 'dashboard', label: 'Dashboard' },
    { key: 'projects', label: 'My Projects' },
    { key: 'sprint-tasks', label: 'Sprint Tasks' },
    { key: 'workspace', label: 'Development Workspace' },
    { key: 'feedback-impl', label: 'Feedback Implementation' },
    { key: 'prototypes', label: 'Prototype Increment' },
    { key: 'defect-fixing', label: 'Defect Fixing' },
    { key: 'notes', label: 'Technical Notes' },
    { href: 'ai-project-generator.html#history', label: 'AI Generated Prototype' },
    { href: 'epa-workboard.html', label: 'My Workboard' },
  ],
  Tester: [
    { key: 'dashboard', label: 'Dashboard' },
    { key: 'projects', label: 'My Projects' },
    { key: 'workspace', label: 'Testing Workspace' },
    { key: 'test-cases', label: 'Test Cases' },
    { key: 'defects', label: 'Defect Reports' },
    { key: 'regression', label: 'Regression Testing' },
    { key: 'report', label: 'Testing Report' },
    { href: 'ai-project-generator.html#history', label: 'AI Generated Test Cases' },
    { href: 'epa-workboard.html', label: 'Testing Board' },
  ],
  Evaluator: [
    { key: 'dashboard', label: 'Dashboard' },
    { key: 'demo', label: 'Prototype Demo' },
    { key: 'evaluation', label: 'Evaluation Form' },
    { key: 'feedback', label: 'Submit Feedback' },
    { key: 'history', label: 'Feedback History' },
    { href: 'ai-project-generator.html', label: 'Submit App Requirement' },
    { href: 'ai-project-generator.html#history', label: 'My Generated Prototype' },
    { href: 'epa-workboard.html', label: 'My Evaluation Tasks' },
  ],
};

/** Renders the role's nav into #sidebar and wires navigation.
 *  onNavigate(key) is called whenever the user clicks an in-page (data-key) menu item.
 *  Items with an `href` navigate to a real page instead of toggling an in-page section. */
function renderSidebar(role, activeKey, onNavigate) {
  const menu = ROLE_MENUS[role] || [];
  const sidebar = document.getElementById('sidebar');
  sidebar.innerHTML = `
    <div class="brand">EPA Framework<span class="role-label">${role}</span></div>
    <nav>${menu.map((item) => item.href
    ? `<a href="${item.href}">${item.label}</a>`
    : `<a href="#" data-key="${item.key}" class="${item.key === activeKey ? 'active' : ''}">${item.label}</a>`
  ).join('')}</nav>
  `;
  sidebar.querySelectorAll('nav a[data-key]').forEach((a) => {
    a.addEventListener('click', (e) => {
      const key = a.dataset.key;
      // If this page has no matching <section id="section-KEY"> (e.g. we're on a
      // standalone page like epa-workboard.html, not the role's own dashboard),
      // onNavigate has nothing to toggle - go back to the dashboard with a deep
      // link instead of silently doing nothing.
      if (!document.getElementById('section-' + key)) {
        const dashboard = (typeof ROLE_DASHBOARD !== 'undefined' && ROLE_DASHBOARD[role]) || 'index.html';
        window.location.href = `${dashboard}?section=${key}`;
        return;
      }
      e.preventDefault();
      sidebar.querySelectorAll('nav a[data-key]').forEach((x) => x.classList.remove('active'));
      a.classList.add('active');
      onNavigate(key);
    });
  });
}

function setActiveSection(key) {
  document.querySelectorAll('.page-section').forEach((s) => s.classList.add('hidden'));
  const target = document.getElementById('section-' + key);
  if (target) target.classList.remove('hidden');
}
