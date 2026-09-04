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

/** Menu key -> icon name (see ICON_PATHS in ui.js). Menu items sharing a concept
 *  (e.g. every "feedback" or "testing" screen) intentionally reuse the same icon. */
const MENU_ICON = {
  dashboard: 'grid', users: 'users', roles: 'shield', projects: 'folder', phases: 'calendar',
  backlog: 'listCheck', sprints: 'calendar', prototypes: 'monitor', feedback: 'message',
  testing: 'shieldCheck', defects: 'bug', release: 'rocket', retrospective: 'refresh',
  reports: 'chart', audit: 'clock', settings: 'gear',
  requirements: 'doc', 'sprint-backlog': 'calendar', 'feedback-review': 'message',
  'prototype-review': 'monitor', 'sprint-tasks': 'calendar', workspace: 'laptop',
  'feedback-impl': 'message', 'defect-fixing': 'bug', notes: 'doc', 'test-cases': 'shieldCheck',
  regression: 'shieldCheck', report: 'chart', demo: 'monitor', evaluation: 'checkCircle', history: 'clock',
};

function iconForHref(href) {
  if (href.includes('ai-project-generator')) return 'sparkles';
  if (href.includes('epa-workboard')) return 'kanban';
  return 'grid';
}

/** Renders the role's nav into #sidebar and wires navigation.
 *  onNavigate(key) is called whenever the user clicks an in-page (data-key) menu item.
 *  Items with an `href` navigate to a real page instead of toggling an in-page section. */
function renderSidebar(role, activeKey, onNavigate) {
  const menu = ROLE_MENUS[role] || [];
  const sidebar = document.getElementById('sidebar');
  sidebar.innerHTML = `
    <div class="brand">EPA Framework<span class="role-label">${role}</span></div>
    <nav>${menu.map((item) => item.href
    ? `<a href="${item.href}">${svgIcon(iconForHref(item.href))}<span class="menu-label">${item.label}</span></a>`
    : `<a href="#" data-key="${item.key}" class="${item.key === activeKey ? 'active' : ''}">${svgIcon(MENU_ICON[item.key] || 'grid')}<span class="menu-label">${item.label}</span></a>`
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

/** Mobile/tablet off-canvas drawer for the sidebar. Desktop layout is untouched -
 *  this only toggles CSS classes; renderSidebar() may replace #sidebar's innerHTML
 *  at any time, so link-close behavior is delegated on the stable #sidebar element
 *  rather than bound to individual <a> tags. */
function initMobileSidebar() {
  const sidebar = document.getElementById('sidebar');
  const hamburger = document.getElementById('hamburgerBtn');
  const overlay = document.getElementById('sidebarOverlay');
  if (!sidebar || !hamburger || !overlay) return;

  function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('show');
    document.body.classList.add('sidebar-open');
  }
  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
    document.body.classList.remove('sidebar-open');
  }

  hamburger.addEventListener('click', openSidebar);
  overlay.addEventListener('click', closeSidebar);
  sidebar.addEventListener('click', (e) => {
    if (e.target.closest('a') && window.innerWidth <= 1023) closeSidebar();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeSidebar();
  });
}

document.addEventListener('DOMContentLoaded', initMobileSidebar);

/** Topbar quick-search: purely presentational row filter over whatever table rows
 *  are already rendered in the current visible section - no data/API calls, so it
 *  cannot get out of sync with the real data flow. */
function initTopbarSearch() {
  const input = document.getElementById('topbarSearch');
  if (!input) return;
  input.addEventListener('input', () => {
    const q = input.value.trim().toLowerCase();
    document.querySelectorAll('.page-section:not(.hidden) table tbody tr').forEach((tr) => {
      tr.style.display = !q || tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

document.addEventListener('DOMContentLoaded', initTopbarSearch);

/** Wraps the hero-card's big stat number in a CSS conic-gradient progress ring,
 *  reading whatever numeric percentage the page's own script already put there.
 *  Skips hero-cards whose stat isn't a percentage (e.g. Evaluator's phase name). */
function attachHeroProgressRing() {
  const h2 = document.querySelector('.hero-card h2');
  if (!h2 || h2.closest('.progress-ring') || !h2.textContent.includes('%')) return;
  const ring = document.createElement('div');
  ring.className = 'progress-ring';
  h2.parentNode.insertBefore(ring, h2);
  ring.appendChild(h2);
  function sync() {
    const match = h2.textContent.match(/\d+(\.\d+)?/);
    const val = match ? Math.max(0, Math.min(100, parseFloat(match[0]))) : 0;
    ring.style.setProperty('--value', val);
  }
  sync();
  new MutationObserver(sync).observe(h2, { characterData: true, childList: true, subtree: true });
}

document.addEventListener('DOMContentLoaded', attachHeroProgressRing);

/** Adds a segmented distribution bar under the EPA Phase Overview cards
 *  (#epa-initial/#epa-devtest/#epa-release/#epa-aligned), reusing the exact
 *  counts each page's own script already loads - no new API calls. */
function attachEpaPhaseDistribution() {
  const ids = ['epa-initial', 'epa-devtest', 'epa-release', 'epa-aligned'];
  const els = ids.map((id) => document.getElementById(id));
  if (els.some((el) => !el)) return;
  const container = els[0].closest('.grid.metrics');
  if (!container || container.parentNode.querySelector('.phase-distribution')) return;
  const bar = document.createElement('div');
  bar.className = 'phase-distribution';
  container.parentNode.insertBefore(bar, container.nextSibling);
  const labels = ['Initial Phase', 'Dev & Testing Phase', 'Release Phase', 'EPA Aligned'];
  const colors = ['info', 'warning', 'accent', 'success'];
  function sync() {
    const values = els.map((el) => parseInt(el.textContent, 10) || 0);
    const total = values.reduce((a, b) => a + b, 0) || 1;
    bar.innerHTML = values.map((v, i) => `<span class="phase-seg phase-${colors[i]}" style="width:${(v / total * 100).toFixed(2)}%" title="${labels[i]}: ${v}"></span>`).join('');
  }
  sync();
  const observer = new MutationObserver(sync);
  els.forEach((el) => observer.observe(el, { characterData: true, childList: true, subtree: true }));
}

document.addEventListener('DOMContentLoaded', attachEpaPhaseDistribution);
