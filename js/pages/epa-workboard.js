(function () {
  const ALLOWED_ROLES = ['Admin', 'Product Owner', 'Developer', 'Tester', 'Evaluator'];
  const STATUSES = ['To Do', 'In Progress', 'Review', 'Ready for Testing', 'Testing', 'Done', 'Deferred', 'Blocked'];
  const TYPE_LABEL = {
    requirement: 'Requirement', backlog: 'Backlog', sprint_item: 'Sprint Task', prototype_task: 'Prototype',
    feedback_implementation: 'Feedback Impl.', test_case: 'Test Case', defect: 'Defect', release_task: 'Release',
    retrospective_action: 'Retrospective Action', evaluation_task: 'Evaluation Task', app_requirement: 'App Requirement',
  };
  // Mirrors backend WorkboardController::CREATE_PERMISSIONS - kept in sync manually
  // since this only controls which options are offered, the backend still enforces it.
  const ARTIFACT_TYPE_OPTIONS = {
    Admin: ['requirement', 'backlog', 'sprint_item', 'prototype_task', 'feedback_implementation', 'test_case', 'defect', 'release_task', 'retrospective_action', 'evaluation_task', 'app_requirement'],
    'Product Owner': ['requirement', 'backlog', 'sprint_item', 'feedback_implementation', 'release_task'],
    Developer: ['prototype_task', 'feedback_implementation', 'defect'],
    Tester: ['test_case', 'defect'],
    Evaluator: ['evaluation_task', 'feedback_implementation', 'app_requirement'],
  };
  const EPA_STEPS = [
    ['Initial Phase', 'REQ_BACKLOG'],
    ['Dev & Testing Phase', 'PROTOTYPE_DESIGN_SPRINT'], ['Dev & Testing Phase', 'DEMO_PROTOTYPE'],
    ['Dev & Testing Phase', 'USER_EVALUATION_REVIEW'], ['Dev & Testing Phase', 'UPDATE_BACKLOG'],
    ['Dev & Testing Phase', 'INCREMENT_DELIVERY'], ['Dev & Testing Phase', 'ITERATIVE_REFINEMENT'],
    ['Dev & Testing Phase', 'USER_TESTING'],
    ['Release Phase', 'PRODUCT_RELEASE'], ['Release Phase', 'FINAL_DEPLOYMENT'], ['Release Phase', 'PRODUCT_RETROSPECTIVE'],
  ];

  const user = getUser();
  if (!getToken() || !user) { window.location.href = 'index.html'; return; }
  if (!ALLOWED_ROLES.includes(user.role)) { redirectToDashboard(user.role); return; }

  document.getElementById('userInfo').innerHTML = renderUserChip(user);
  document.getElementById('logoutBtn').addEventListener('click', logout);
  renderSidebar(user.role, null, () => {});
  if (!['Admin', 'Product Owner'].includes(user.role)) {
    document.getElementById('wbSyncBtn').classList.add('hidden');
  }

  let projects = [];
  let items = [];
  let members = [];
  let currentProjectId = null;
  let draggedItemId = null;

  async function loadProjects() {
    projects = await api('/projects');
    const select = document.getElementById('wbProjectSelect');
    select.innerHTML = projects.map((p) => `<option value="${p.id}">${p.name}</option>`).join('');
    const remembered = Number(localStorage.getItem('epa_workboard_project_id'));
    currentProjectId = projects.find((p) => p.id === remembered) ? remembered : (projects[0] ? projects[0].id : null);
    if (currentProjectId) select.value = currentProjectId;
  }

  async function loadMembers() {
    members = currentProjectId ? await api(`/projects/${currentProjectId}/members`) : [];
  }

  async function loadItems() {
    if (!currentProjectId) { items = []; renderAll(); return; }
    const filters = {};
    const artifactType = document.getElementById('wbFilterArtifact').value;
    const priority = document.getElementById('wbFilterPriority').value;
    const assignee = document.getElementById('wbFilterAssignee').value;
    const search = document.getElementById('wbSearch').value.trim();
    if (artifactType) filters.artifact_type = artifactType;
    if (priority) filters.priority = priority;
    if (assignee) filters.assignee_id = assignee;
    if (search) filters.search = search;
    const qs = new URLSearchParams(filters).toString();
    items = await api(`/projects/${currentProjectId}/workboard${qs ? '?' + qs : ''}`);
    renderAll();
  }

  function renderAll() {
    renderSummary();
    renderAssigneeFilter();
    renderBoard();
  }

  function renderSummary() {
    const counts = { total: items.length };
    STATUSES.forEach((s) => { counts[s] = items.filter((i) => i.status === s).length; });
    const cards = [
      ['Total Tasks', counts.total],
      ['In Progress', counts['In Progress']],
      ['Ready for Testing', counts['Ready for Testing'] + counts['Testing']],
      ['Review / Blocked', counts['Review']],
      ['Done', counts['Done']],
    ];
    document.getElementById('wbSummary').innerHTML = cards.map(([label, val]) => metricCard(label, val)).join('');
  }

  function renderAssigneeFilter() {
    const select = document.getElementById('wbFilterAssignee');
    const current = select.value;
    const seen = new Map();
    items.forEach((i) => { if (i.assignee_id) seen.set(i.assignee_id, i.assignee_name || `User #${i.assignee_id}`); });
    select.innerHTML = '<option value="">All Assignees</option>' +
      Array.from(seen.entries()).map(([id, name]) => `<option value="${id}">${name}</option>`).join('');
    select.value = current;
  }

  function cardHtml(item) {
    const typeTag = `<span class="wb-tag type-${item.artifact_type}">${TYPE_LABEL[item.artifact_type] || item.artifact_type}</span>`;
    const priorityTag = item.priority ? `<span class="wb-tag priority-${item.priority}">${item.priority}</span>` : '';
    return `
      <div class="wb-card" draggable="true" data-id="${item.id}">
        <div class="wb-card-title">${item.title}</div>
        <div class="wb-card-meta">${typeTag}${priorityTag}</div>
        ${item.assignee_name ? `<div class="wb-card-assignee">👤 ${item.assignee_name}</div>` : ''}
      </div>`;
  }

  function renderBoard() {
    const board = document.getElementById('wbBoard');
    if (!items.length) {
      board.innerHTML = '<p class="wb-empty-state">No work items yet. Click <b>+ Add Work Item</b> to create a new To Do.</p>';
      return;
    }
    board.innerHTML = STATUSES.map((status) => {
      const cards = items.filter((i) => i.status === status);
      return `
        <div class="wb-column" data-status="${status}">
          <div class="wb-column-head"><span>${status}</span><span class="wb-column-count">${cards.length}</span></div>
          <div class="wb-cards">${cards.map(cardHtml).join('')}</div>
        </div>`;
    }).join('');

    board.querySelectorAll('.wb-card').forEach((card) => {
      card.addEventListener('click', () => openDrawer(Number(card.dataset.id)));
      card.addEventListener('dragstart', () => {
        draggedItemId = Number(card.dataset.id);
        card.classList.add('dragging');
      });
      card.addEventListener('dragend', () => card.classList.remove('dragging'));
    });

    board.querySelectorAll('.wb-column').forEach((col) => {
      col.addEventListener('dragover', (e) => { e.preventDefault(); col.classList.add('drag-over'); });
      col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
      col.addEventListener('drop', async (e) => {
        e.preventDefault();
        col.classList.remove('drag-over');
        const newStatus = col.dataset.status;
        if (draggedItemId == null) return;
        await handleDrop(draggedItemId, newStatus);
        draggedItemId = null;
      });
    });
  }

  async function handleDrop(itemId, newStatus) {
    const item = items.find((i) => i.id === itemId);
    if (!item || item.status === newStatus) return;
    try {
      const updated = await api(`/work-items/${itemId}/status`, { method: 'PUT', body: { new_status: newStatus } });
      Object.assign(item, updated);
      renderAll();
    } catch (err) {
      // Revert: board stays as-is since we only mutate `items` after success -
      // just re-render to snap the card back to its original column.
      renderAll();
      alert(`Failed to move task: ${err.message}`);
    }
  }

  async function openDrawer(itemId) {
    const overlay = document.getElementById('wbDrawerOverlay');
    const body = document.getElementById('wbDrawerBody');
    overlay.classList.remove('hidden');
    body.innerHTML = '<p>Memuat...</p>';

    let detail;
    try {
      detail = await api(`/work-items/${itemId}`);
    } catch (err) {
      body.innerHTML = `<p class="error-text">${err.message}</p>`;
      return;
    }
    const wi = detail.work_item;
    document.getElementById('wbDrawerTitle').textContent = wi.title;

    body.innerHTML = `
      <div class="wb-drawer-section">
        <div class="wb-drawer-fields">
          <div><b>Type</b>${TYPE_LABEL[wi.artifact_type] || wi.artifact_type}</div>
          <div><b>Priority</b>${wi.priority || '-'}</div>
          <div><b>EPA Phase</b>${wi.epa_phase || '-'}</div>
          <div><b>EPA Step</b>${wi.epa_step || '-'}</div>
          <div><b>Assignee</b>${wi.assignee_name || '-'}</div>
          <div><b>Due Date</b>${wi.due_date || '-'}</div>
        </div>
        ${wi.description ? `<p style="margin-top:10px;font-size:13px">${wi.description}</p>` : ''}
      </div>

      <div class="wb-drawer-section">
        <h4>Status</h4>
        <div id="wbDrawerError"></div>
        <div class="wb-status-actions">
          ${STATUSES.map((s) => `<button type="button" class="wb-status-btn ${s === wi.status ? 'active-status' : ''}" data-status="${s}">${s}</button>`).join('')}
        </div>
      </div>

      <div class="wb-drawer-section">
        <h4>Comments</h4>
        <div id="wbCommentList">${detail.comments.map((c) => `
          <div class="wb-comment-item"><b>${c.user_name}</b>: ${c.comment_text}<span>${c.created_at}</span></div>
        `).join('') || '<p class="small">No comments yet.</p>'}</div>
        <div class="wb-comment-form">
          <textarea id="wbNewComment" placeholder="Tulis komentar..."></textarea>
          <button type="button" id="wbAddCommentBtn">Kirim</button>
        </div>
      </div>

      <div class="wb-drawer-section">
        <h4>Activity Log</h4>
        <div>${detail.activity_log.map((a) => `
          <div class="wb-activity-item">${a.user_name || 'System'} - ${a.description || a.action_type} <span>${a.created_at}</span></div>
        `).join('') || '<p class="small">No activity yet.</p>'}</div>
      </div>
    `;

    body.querySelectorAll('.wb-status-btn').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const newStatus = btn.dataset.status;
        if (newStatus === wi.status) return;
        try {
          document.getElementById('wbDrawerError').innerHTML = '';
          await api(`/work-items/${itemId}/status`, { method: 'PUT', body: { new_status: newStatus } });
          await loadItems();
          await openDrawer(itemId);
        } catch (err) {
          document.getElementById('wbDrawerError').innerHTML = `<div class="wb-error-banner">${err.message}</div>`;
        }
      });
    });

    document.getElementById('wbAddCommentBtn').addEventListener('click', async () => {
      const textarea = document.getElementById('wbNewComment');
      const text = textarea.value.trim();
      if (!text) return;
      await api(`/work-items/${itemId}/comments`, { method: 'POST', body: { comment_text: text } });
      textarea.value = '';
      await openDrawer(itemId);
    });
  }

  document.getElementById('wbDrawerClose').addEventListener('click', () => {
    document.getElementById('wbDrawerOverlay').classList.add('hidden');
  });
  document.getElementById('wbDrawerOverlay').addEventListener('click', (e) => {
    if (e.target.id === 'wbDrawerOverlay') e.target.classList.add('hidden');
  });

  document.getElementById('wbProjectSelect').addEventListener('change', async (e) => {
    currentProjectId = Number(e.target.value);
    localStorage.setItem('epa_workboard_project_id', String(currentProjectId));
    await loadMembers();
    await loadItems();
  });

  function openCreateModal() {
    if (!currentProjectId) { alert('Please select a project first.'); return; }
    const typeOptions = ARTIFACT_TYPE_OPTIONS[user.role] || [];
    const memberOptions = members.map((m) => ({ value: m.user_id, label: m.name }));
    const isSelfDefault = ['Developer', 'Tester', 'Evaluator'].includes(user.role);

    openModal({
      title: 'Add Work Item',
      fields: [
        { name: 'project_id', label: 'Project', type: 'select', options: projects.map((p) => ({ value: p.id, label: p.name })) },
        { name: 'title', label: 'Title' },
        { name: 'description', label: 'Description', type: 'textarea' },
        { name: 'artifact_type', label: 'Artifact Type', type: 'select', options: typeOptions.map((t) => ({ value: t, label: TYPE_LABEL[t] || t })) },
        { name: 'epa_step', label: 'EPA Step', type: 'select', options: EPA_STEPS.map(([phase, step]) => ({ value: step, label: `[${phase}] ${step}` })) },
        { name: 'status', label: 'Status', type: 'select', options: STATUSES },
        { name: 'priority', label: 'Priority', type: 'select', options: [{ value: '', label: '(none)' }, 'Must', 'Should', 'Could', 'Wont'] },
        { name: 'assignee_id', label: 'Assignee', type: 'select', options: [{ value: '', label: '- Unassigned -' }, ...memberOptions] },
        { name: 'due_date', label: 'Due Date', type: 'date' },
      ],
      initial: {
        project_id: currentProjectId,
        status: 'To Do',
        assignee_id: isSelfDefault ? user.id : '',
      },
      onSubmit: async (v) => {
        const epaPhase = (EPA_STEPS.find(([, step]) => step === v.epa_step) || [null])[0];
        await api(`/projects/${v.project_id}/workboard`, {
          method: 'POST',
          body: {
            title: v.title,
            description: v.description || null,
            artifact_type: v.artifact_type,
            epa_phase: epaPhase,
            epa_step: v.epa_step,
            status: v.status,
            priority: v.priority || null,
            assignee_id: v.assignee_id || null,
            due_date: v.due_date || null,
          },
        });
        if (Number(v.project_id) !== currentProjectId) {
          currentProjectId = Number(v.project_id);
          document.getElementById('wbProjectSelect').value = currentProjectId;
          localStorage.setItem('epa_workboard_project_id', String(currentProjectId));
          await loadMembers();
        }
        await loadItems();
        alert('Work item created successfully.');
      },
    });
  }

  document.getElementById('wbAddWorkItemBtn').addEventListener('click', openCreateModal);

  document.getElementById('wbSyncBtn').addEventListener('click', async () => {
    if (!currentProjectId) return;
    const result = await api(`/projects/${currentProjectId}/workboard/sync`, { method: 'POST' });
    alert(`Sync selesai: ${result.synced} artefak disinkronkan ke board.`);
    await loadItems();
  });

  ['wbFilterArtifact', 'wbFilterPriority', 'wbFilterAssignee'].forEach((id) => {
    document.getElementById(id).addEventListener('change', loadItems);
  });
  document.getElementById('wbSearch').addEventListener('input', () => {
    clearTimeout(window._wbSearchDebounce);
    window._wbSearchDebounce = setTimeout(loadItems, 300);
  });
  document.getElementById('wbClearFilters').addEventListener('click', () => {
    document.getElementById('wbFilterArtifact').value = '';
    document.getElementById('wbFilterPriority').value = '';
    document.getElementById('wbFilterAssignee').value = '';
    document.getElementById('wbSearch').value = '';
    loadItems();
  });

  (async function init() {
    await loadProjects();
    await loadMembers();
    await loadItems();
  })();
})();
