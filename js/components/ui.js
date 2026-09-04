/** Keyword -> color variant map so every existing pill(value) call across the app
 *  auto-colors by its actual status/priority/severity text, with no call-site changes. */
const PILL_COLOR_MAP = {
  active: 'success', approved: 'success', done: 'success', completed: 'success', released: 'success',
  pass: 'success', passed: 'success', verified: 'success', closed: 'success', converted: 'success',
  implemented: 'success', generated: 'success', available: 'success', yes: 'success',
  'ready for testing': 'success', 'ready for sprint': 'success',
  rejected: 'danger', blocked: 'danger', fail: 'danger', failed: 'danger', critical: 'danger', open: 'danger',
  pending: 'warning', 'in progress': 'warning', 'to do': 'warning', planned: 'warning', 'not run': 'warning',
  retest: 'warning', draft: 'warning', previewed: 'warning', generating: 'warning', revised: 'warning',
  'need clarification': 'warning', high: 'warning', must: 'warning', 'not started': 'warning',
  locked: 'info', medium: 'info', should: 'info', 'on hold': 'info', proposed: 'info',
  wont: 'muted', "won't": 'muted', archived: 'muted', no: 'muted', clarify: 'muted', inactive: 'muted',
  could: 'muted', low: 'muted', deferred: 'muted', unknown: 'muted',
};

function pill(value, variant) {
  const text = value ?? '-';
  const cls = variant || PILL_COLOR_MAP[String(text).trim().toLowerCase()] || '';
  return `<span class="pill${cls ? ' pill-' + cls : ''}">${text}</span>`;
}

/** ---------- Lightweight inline-SVG icon set (no external/paid icon library) ----------
 *  Small, generic pictograms shared across sidebar links, metric cards and the topbar. */
const ICON_PATHS = {
  grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
  users: '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.3"/><path d="M21 20c0-2.6-1.8-4.8-4.2-5.5"/>',
  shield: '<path d="M12 3l7 3v5c0 5-3 8.5-7 10-4-1.5-7-5-7-10V6l7-3z"/>',
  shieldCheck: '<path d="M12 3l7 3v5c0 5-3 8.5-7 10-4-1.5-7-5-7-10V6l7-3z"/><path d="M9 12l2 2 4-4"/>',
  folder: '<path d="M3 6a1 1 0 0 1 1-1h5l2 2h9a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6z"/>',
  calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
  listCheck: '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/>',
  monitor: '<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20h8M12 16v4"/>',
  message: '<path d="M4 5h16v11H8l-4 4V5z"/>',
  bug: '<circle cx="12" cy="13" r="6"/><path d="M12 7V4M9 4l1.5 2M15 4l-1.5 2M5 13H2M22 13h-3M6 9l-2-2M18 9l2-2M6 17l-2 2M18 17l2 2"/>',
  rocket: '<path d="M12 2c3 1 5 4 5 8 0 3-1.5 6-5 10-3.5-4-5-7-5-10 0-4 2-7 5-8z"/><circle cx="12" cy="9" r="1.6"/><path d="M9 17l-3 4M15 17l3 4"/>',
  refresh: '<path d="M4 12a8 8 0 0 1 14-5.3M20 12a8 8 0 0 1-14 5.3"/><path d="M18 3v4h-4M6 21v-4h4"/>',
  chart: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
  clock: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.5 2"/>',
  gear: '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
  doc: '<path d="M6 3h8l4 4v14H6V3z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
  laptop: '<rect x="3" y="4" width="18" height="11" rx="1.5"/><path d="M2 19h20"/>',
  checkCircle: '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
  sparkles: '<path d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3z"/><path d="M19 15l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7z"/>',
  kanban: '<rect x="3" y="4" width="5" height="16" rx="1"/><rect x="10" y="4" width="5" height="10" rx="1"/><rect x="17" y="4" width="4" height="13" rx="1"/>',
  logout: '<path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4"/><path d="M15 8l4 4-4 4M19 12H9"/>',
  bell: '<path d="M6 8a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6z"/><path d="M10 21a2 2 0 0 0 4 0"/>',
  search: '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
};

function svgIcon(name, cls) {
  const path = ICON_PATHS[name] || ICON_PATHS.grid;
  return `<svg class="icon${cls ? ' ' + cls : ''}" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${path}</svg>`;
}

/** Best-effort icon pick for a metric card based on its visible label text. */
function iconForLabel(label) {
  const l = (label || '').toLowerCase();
  if (l.includes('user')) return 'users';
  if (l.includes('project')) return 'folder';
  if (l.includes('feedback')) return 'message';
  if (l.includes('defect') || l.includes('bug')) return 'bug';
  if (l.includes('retest') || l.includes('regression')) return 'refresh';
  if (l.includes('test')) return 'shieldCheck';
  if (l.includes('sprint')) return 'calendar';
  if (l.includes('prototype') || l.includes('version')) return 'monitor';
  if (l.includes('backlog') || l.includes('task') || l.includes('work item')) return 'listCheck';
  if (l.includes('release') || l.includes('ready')) return 'rocket';
  if (l.includes('evaluat')) return 'checkCircle';
  return 'chart';
}

/** Injects a .metric-icon into every metric card that doesn't already have one -
 *  purely presentational, never touches the dynamic id="m-..." value elements. */
function enhanceMetricCards() {
  document.querySelectorAll('.metrics article, .metric-card').forEach((card) => {
    if (card.querySelector('.metric-icon')) return;
    const label = card.querySelector('p');
    if (!label) return;
    const icon = document.createElement('div');
    icon.className = 'metric-icon';
    icon.innerHTML = svgIcon(iconForLabel(label.textContent));
    card.insertBefore(icon, card.firstChild);
  });
}
document.addEventListener('DOMContentLoaded', enhanceMetricCards);

function initialsOf(name) {
  const parts = (name || '').trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return '?';
  return parts.slice(0, 2).map((w) => w[0].toUpperCase()).join('');
}

/** Renders the topbar profile chip content (avatar + name + role) for #userInfo.
 *  Replaces the old plain-text "Welcome · Role" with a richer, identical-data chip. */
function renderUserChip(user) {
  return `<span class="avatar">${initialsOf(user.name)}</span>` +
    `<span class="user-meta"><b>${user.name}</b><small>${user.role}</small></span>`;
}

/** Returns a warning banner HTML if the URL is an unusable placeholder, else ''. */
function demoUrlWarning(url) {
  if (url && url.includes('example.com')) {
    return '<p class="error-text">Invalid demo URL. Please replace with real prototype output.</p>';
  }
  return '';
}

/** Returns the HTML for one metric card article. */
function metricCard(label, value) {
  return `<article><div class="metric-icon">${svgIcon(iconForLabel(label))}</div><p>${label}</p><h3>${value}</h3></article>`;
}

/** Renders `rows` into the tbody matched by `selector` using `rowFn(row) => trHTML`. */
function renderTable(selector, rows, rowFn) {
  document.querySelector(selector).innerHTML = rows.map(rowFn).join('');
}

function openModal({ title, fields, initial = {}, onSubmit }) {
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalError').textContent = '';
  const body = document.getElementById('modalBody');
  body.innerHTML = fields.map((f) => {
    const value = initial[f.name] ?? '';
    if (f.type === 'select') {
      const opts = f.options.map((o) => {
        const optValue = typeof o === 'object' ? o.value : o;
        const optLabel = typeof o === 'object' ? o.label : o;
        return `<option value="${optValue}" ${String(optValue) === String(value) ? 'selected' : ''}>${optLabel}</option>`;
      }).join('');
      return `<div class="form-group"><label for="field_${f.name}">${f.label}</label><select id="field_${f.name}">${opts}</select></div>`;
    }
    if (f.type === 'textarea') {
      return `<div class="form-group"><label for="field_${f.name}">${f.label}</label><textarea id="field_${f.name}" rows="3">${value}</textarea></div>`;
    }
    return `<div class="form-group"><label for="field_${f.name}">${f.label}</label><input type="${f.type || 'text'}" id="field_${f.name}" value="${value}" /></div>`;
  }).join('');

  const overlay = document.getElementById('modalOverlay');
  overlay.classList.remove('hidden');

  const submitBtn = document.getElementById('modalSubmit');
  const cancelBtn = document.getElementById('modalCancel');

  const cleanup = () => {
    overlay.classList.add('hidden');
    submitBtn.onclick = null;
    cancelBtn.onclick = null;
  };

  cancelBtn.onclick = cleanup;
  submitBtn.onclick = async () => {
    const values = {};
    fields.forEach((f) => { values[f.name] = document.getElementById(`field_${f.name}`).value; });
    try {
      await onSubmit(values);
      cleanup();
    } catch (err) {
      document.getElementById('modalError').textContent = err.message;
    }
  };
}
