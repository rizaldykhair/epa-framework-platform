(function () {
  const user = guardDashboard('Admin');
  if (!user) return;

  document.getElementById('userInfo').innerHTML = renderUserChip(user);
  document.getElementById('logoutBtn').addEventListener('click', logout);

  let projects = [];
  let users = [];
  let roles = [];

  function populateProjectSelect(select, onChange) {
    select.innerHTML = projects.map((p) => `<option value="${p.id}">${p.name}</option>`).join('');
    select.onchange = () => onChange(Number(select.value));
    if (projects.length) onChange(Number(select.value));
  }

  // ---------- Dashboard ----------
  async function loadDashboard() {
    const s = await api('/reports/summary');
    document.getElementById('m-totalProjects').textContent = s.totalProjects;
    document.getElementById('m-totalUsers').textContent = s.totalUsers;
    document.getElementById('m-activeSprints').textContent = s.activeSprints;
    document.getElementById('m-totalBacklog').textContent = s.totalBacklog;
    document.getElementById('m-totalFeedback').textContent = s.totalFeedback;
    document.getElementById('m-feedbackRate').textContent = s.feedbackConversionRate + '%';
    document.getElementById('m-totalTests').textContent = s.totalTests;
    document.getElementById('m-openDefects').textContent = s.openDefects;
    document.getElementById('releaseScore').textContent = s.releaseReadiness + '%';
    renderTable('#progressTable tbody', s.projectProgress, (p) => `<tr><td>${p.name}</td><td>${p.done_backlog || 0}/${p.total_backlog}</td></tr>`);
    renderTable('#activityTable tbody', s.recentActivity, (a) => `<tr><td>${a.user_name || '-'}</td><td>${a.action}</td><td>${a.entity} #${a.entity_id ?? ''}</td><td>${a.created_at}</td></tr>`);
    renderTable('#reportProgressTable tbody', s.projectProgress, (p) => `<tr><td>${p.name}</td><td>${p.total_backlog}</td><td>${p.done_backlog || 0}</td></tr>`);
    await loadEpaOverview();
  }

  // ---------- EPA Phase Overview ----------
  async function loadEpaOverview() {
    const d = await api('/epa/phase-dashboard');
    const phases = d.by_phase || {};
    document.getElementById('epa-initial').textContent = phases['Initial Phase'] || 0;
    document.getElementById('epa-devtest').textContent = phases['Dev & Testing Phase'] || 0;
    document.getElementById('epa-release').textContent = phases['Release Phase'] || 0;
    document.getElementById('epa-aligned').textContent = Math.max(0, d.total_projects - d.incomplete_projects.length);
    renderTable('#epaIncompleteTable tbody', d.incomplete_projects, (p) => `
      <tr><td><a href="project-detail.html?id=${p.project_id}">${p.project_name}</a></td><td>${p.current_step || '-'}</td>
      <td class="small">${(p.missing_artifacts || []).join(', ')}</td></tr>`);
    renderTable('#epaReadyTable tbody', d.ready_for_next_step, (p) => `
      <tr><td><a href="project-detail.html?id=${p.project_id}">${p.project_name}</a></td><td>${p.current_step || '-'}</td></tr>`);
  }
  document.getElementById('syncAllBtn').addEventListener('click', async () => {
    await api('/epa/sync-all', { method: 'POST' });
    await loadEpaOverview();
    await loadProjects();
  });

  // ---------- Users ----------
  async function loadUsers() {
    users = await api('/users');
    renderTable('#usersTable tbody', users, (u) => `
      <tr><td>${u.name}</td><td>${u.email}</td><td>${pill(u.role_name)}</td><td>${pill(u.status)}</td>
      <td class="row-actions">
        <button class="secondary" onclick="editUser(${u.id})">Edit</button>
        <button class="danger" onclick="deleteUser(${u.id})">Delete</button>
      </td></tr>`);
  }
  window.addUser = function () {
    openModal({
      title: 'Add User',
      fields: [
        { name: 'name', label: 'Name' },
        { name: 'email', label: 'Email', type: 'email' },
        { name: 'password', label: 'Password', type: 'password' },
        { name: 'role_id', label: 'Role', type: 'select', options: roles.map((r) => ({ value: r.id, label: r.name })) },
      ],
      onSubmit: async (v) => { await api('/users', { method: 'POST', body: v }); await loadUsers(); },
    });
  };
  window.editUser = function (id) {
    const u = users.find((x) => x.id === id);
    openModal({
      title: 'Edit User',
      fields: [
        { name: 'name', label: 'Name' },
        { name: 'email', label: 'Email', type: 'email' },
        { name: 'role_id', label: 'Role', type: 'select', options: roles.map((r) => ({ value: r.id, label: r.name })) },
        { name: 'status', label: 'Status', type: 'select', options: ['Active', 'Inactive'] },
        { name: 'password', label: 'New Password (optional)', type: 'password' },
      ],
      initial: u,
      onSubmit: async (v) => { await api(`/users/${id}`, { method: 'PUT', body: v }); await loadUsers(); },
    });
  };
  window.deleteUser = async function (id) {
    if (!confirm('Delete this user?')) return;
    await api(`/users/${id}`, { method: 'DELETE' });
    await loadUsers();
  };
  document.getElementById('addUserBtn').addEventListener('click', () => window.addUser());

  // ---------- Roles ----------
  async function loadRoles() {
    roles = await api('/roles');
    renderTable('#rolesTable tbody', roles, (r) => `<tr><td>${r.id}</td><td>${r.name}</td></tr>`);
  }

  // ---------- Projects ----------
  async function loadProjects() {
    projects = await api('/projects');
    renderTable('#projectsTable tbody', projects, (p) => `
      <tr><td>${p.name}${p.project_code ? ` (${p.project_code})` : ''}</td><td>${p.project_type || '-'}</td><td>${p.current_epa_phase || '-'}</td><td>${pill(p.status)}</td>
      <td>${pill(p.epa_completion_status || 'Unknown')}</td>
      <td class="row-actions">
        ${['Draft', 'Proposed'].includes(p.status) ? `<button class="secondary" onclick="approveProject(${p.id})">Approve</button>` : ''}
        <button class="secondary" onclick="editProject(${p.id})">Edit</button>
        <button class="secondary" onclick="window.location.href='project-detail.html?id=${p.id}'">View EPA Workflow</button>
        <button class="danger" onclick="deleteProject(${p.id})">Delete</button>
      </td></tr>`);
    populateProjectSelect(document.getElementById('memberProjectSelect'), loadMembers);
    populateProjectSelect(document.getElementById('phasesProjectSelect'), loadPhases);
    populateProjectSelect(document.getElementById('backlogProjectSelect'), loadBacklog);
    populateProjectSelect(document.getElementById('sprintsProjectSelect'), loadSprints);
    populateProjectSelect(document.getElementById('prototypesProjectSelect'), loadPrototypes);
    populateProjectSelect(document.getElementById('feedbackProjectSelect'), loadFeedback);
    populateProjectSelect(document.getElementById('testingProjectSelect'), loadTesting);
    populateProjectSelect(document.getElementById('defectsProjectSelect'), loadDefects);
    populateProjectSelect(document.getElementById('releaseProjectSelect'), loadRelease);
    populateProjectSelect(document.getElementById('retroProjectSelect'), loadRetro);
  }
  window.addProject = function () {
    const owners = users.filter((u) => u.role_name === 'Product Owner');
    openModal({
      title: 'Create New Project',
      fields: [
        { name: 'name', label: 'Project Name' },
        { name: 'project_code', label: 'Project Code' },
        { name: 'description', label: 'Project Description', type: 'textarea' },
        { name: 'client_name', label: 'Client/User Organization' },
        { name: 'owner_id', label: 'Product Owner', type: 'select', options: owners.map((u) => ({ value: u.id, label: u.name })) },
        { name: 'project_type', label: 'Project Type', type: 'select', options: ['Web App', 'Mobile App', 'Web + Mobile'] },
        { name: 'current_epa_phase', label: 'EPA Current Phase' },
        { name: 'start_date', label: 'Start Date', type: 'date' },
        { name: 'target_release_date', label: 'Target Release Date', type: 'date' },
        { name: 'status', label: 'Project Status', type: 'select', options: ['Draft', 'Active', 'On Hold', 'Released', 'Archived'] },
      ],
      initial: { project_type: 'Web App', status: 'Active' },
      onSubmit: async (v) => { await api('/projects', { method: 'POST', body: v }); await loadProjects(); },
    });
  };
  window.editProject = function (id) {
    const p = projects.find((x) => x.id === id);
    openModal({
      title: 'Edit Project',
      fields: [
        { name: 'name', label: 'Project Name' },
        { name: 'project_code', label: 'Project Code' },
        { name: 'description', label: 'Project Description', type: 'textarea' },
        { name: 'client_name', label: 'Client/User Organization' },
        { name: 'project_type', label: 'Project Type', type: 'select', options: ['Web App', 'Mobile App', 'Web + Mobile'] },
        { name: 'current_epa_phase', label: 'EPA Current Phase' },
        { name: 'target_release_date', label: 'Target Release Date', type: 'date' },
        { name: 'risk_notes', label: 'Risk Notes', type: 'textarea' },
        { name: 'status', label: 'Project Status', type: 'select', options: ['Draft', 'Proposed', 'Active', 'On Hold', 'Released', 'Archived'] },
      ],
      initial: p,
      onSubmit: async (v) => { await api(`/projects/${id}`, { method: 'PUT', body: v }); await loadProjects(); },
    });
  };
  window.approveProject = async function (id) {
    if (!confirm('Approve this project and activate it?')) return;
    await api(`/projects/${id}/approve`, { method: 'POST' });
    await loadProjects();
  };
  window.deleteProject = async function (id) {
    if (!confirm('Delete this project and all its data?')) return;
    await api(`/projects/${id}`, { method: 'DELETE' });
    await loadProjects();
  };
  document.getElementById('addProjectBtn').addEventListener('click', () => window.addProject());
  // Continue with AI Generator / Generate Missing Prototype now live only on the
  // project-detail (View EPA Workflow) page, where the missing-artifact context makes
  // the action meaningful instead of a bare button on a crowded list row.

  // ---------- Project members ----------
  let currentMembersProjectId = null;
  async function loadMembers(projectId) {
    currentMembersProjectId = projectId;
    const members = await api(`/projects/${projectId}/members`);
    renderTable('#membersTable tbody', members, (m) => `
      <tr><td>${m.name}</td><td>${m.email}</td><td>${pill(m.role_in_project)}</td><td>${m.responsibility_notes || '-'}</td>
      <td class="row-actions"><button class="danger" onclick="removeMember(${m.id})">Delete</button></td></tr>`);
  }
  window.removeMember = async function (id) {
    if (!confirm('Remove this member from the project?')) return;
    await api(`/project-members/${id}`, { method: 'DELETE' });
    await loadMembers(currentMembersProjectId);
  };
  document.getElementById('addMemberBtn').addEventListener('click', () => {
    openModal({
      title: 'Assign Project Member',
      fields: [
        { name: 'user_id', label: 'User', type: 'select', options: users.map((u) => ({ value: u.id, label: `${u.name} (${u.role_name})` })) },
        { name: 'role_in_project', label: 'Role in Project' },
        { name: 'responsibility_notes', label: 'Responsibility Notes', type: 'textarea' },
      ],
      onSubmit: async (v) => { await api(`/projects/${currentMembersProjectId}/members`, { method: 'POST', body: v }); await loadMembers(currentMembersProjectId); },
    });
  });

  // ---------- EPA Phases ----------
  let currentPhasesProjectId = null;
  async function loadPhases(projectId) {
    currentPhasesProjectId = projectId;
    const phases = await api(`/projects/${projectId}/phases`);
    document.getElementById('phasesTimeline').innerHTML = phases.map((p) => `
      <div class="step"><strong>${p.sequence}</strong><h4>${p.name}</h4><span>${p.stage_label}</span>
      <div class="row-actions"><button class="secondary" onclick="editPhase(${p.id})">Edit</button><button class="danger" onclick="deletePhase(${p.id})">Delete</button></div></div>`).join('');
  }
  window.editPhase = async function (id) {
    const phases = await api(`/projects/${currentPhasesProjectId}/phases`);
    const p = phases.find((x) => x.id === id);
    openModal({
      title: 'Edit Phase',
      fields: [
        { name: 'name', label: 'Phase Name' },
        { name: 'stage_label', label: 'Stage' },
        { name: 'sequence', label: 'Urutan', type: 'number' },
        { name: 'status', label: 'Status', type: 'select', options: ['Pending', 'In Progress', 'Done'] },
      ],
      initial: p,
      onSubmit: async (v) => { await api(`/phases/${id}`, { method: 'PUT', body: v }); await loadPhases(currentPhasesProjectId); },
    });
  };
  window.deletePhase = async function (id) {
    if (!confirm('Delete this phase?')) return;
    await api(`/phases/${id}`, { method: 'DELETE' });
    await loadPhases(currentPhasesProjectId);
  };
  document.getElementById('addPhaseBtn').addEventListener('click', () => {
    openModal({
      title: 'Add Phase',
      fields: [
        { name: 'name', label: 'Phase Name' },
        { name: 'stage_label', label: 'Stage' },
        { name: 'sequence', label: 'Urutan', type: 'number' },
      ],
      onSubmit: async (v) => { await api(`/projects/${currentPhasesProjectId}/phases`, { method: 'POST', body: v }); await loadPhases(currentPhasesProjectId); },
    });
  });

  // ---------- Backlog ----------
  let currentBacklogProjectId = null;
  async function loadBacklog(projectId) {
    currentBacklogProjectId = projectId;
    const items = await api(`/projects/${projectId}/backlog`);
    renderTable('#backlogTable tbody', items, (r) => `
      <tr><td>${r.code}</td><td>${r.title}</td><td>${pill(r.priority)}</td><td>${pill(r.status)}</td><td>${userName(r.assigned_to)}</td>
      <td class="row-actions"><button class="secondary" onclick="editBacklog(${r.id})">Edit</button><button class="danger" onclick="deleteBacklog(${r.id})">Delete</button></td></tr>`);
  }
  function userName(id) {
    const u = users.find((x) => x.id === id);
    return u ? u.name : '-';
  }
  function devOptions() {
    return users.filter((u) => u.role_name === 'Developer').map((u) => ({ value: u.id, label: u.name }));
  }
  window.editBacklog = async function (id) {
    const items = await api(`/projects/${currentBacklogProjectId}/backlog`);
    const b = items.find((x) => x.id === id);
    openModal({
      title: 'Edit Backlog',
      fields: [
        { name: 'title', label: 'User Story' },
        { name: 'priority', label: 'Prioritas', type: 'select', options: ['Must', 'Should', 'Could', 'Wont'] },
        { name: 'status', label: 'Status', type: 'select', options: ['To Do', 'In Progress', 'Done', 'Deferred'] },
        { name: 'assigned_to', label: 'Assigned Developer', type: 'select', options: devOptions() },
      ],
      initial: b,
      onSubmit: async (v) => { await api(`/backlog/${id}`, { method: 'PUT', body: v }); await loadBacklog(currentBacklogProjectId); },
    });
  };
  window.deleteBacklog = async function (id) {
    if (!confirm('Delete this backlog item?')) return;
    await api(`/backlog/${id}`, { method: 'DELETE' });
    await loadBacklog(currentBacklogProjectId);
  };
  document.getElementById('addBacklogBtn').addEventListener('click', () => {
    openModal({
      title: 'Add Backlog',
      fields: [
        { name: 'title', label: 'User Story' },
        { name: 'priority', label: 'Prioritas', type: 'select', options: ['Must', 'Should', 'Could', 'Wont'] },
        { name: 'assigned_to', label: 'Assigned Developer', type: 'select', options: devOptions() },
      ],
      onSubmit: async (v) => { await api(`/projects/${currentBacklogProjectId}/backlog`, { method: 'POST', body: v }); await loadBacklog(currentBacklogProjectId); },
    });
  });

  // ---------- Sprints ----------
  let currentSprintsProjectId = null;
  async function loadSprints(projectId) {
    currentSprintsProjectId = projectId;
    const sprints = await api(`/projects/${projectId}/sprints`);
    renderTable('#sprintsTable tbody', sprints, (s) => `
      <tr><td>${s.name}</td><td>${pill(s.status)}</td>
      <td class="row-actions"><button class="danger" onclick="deleteSprint(${s.id})">Delete</button></td></tr>`);
  }
  window.deleteSprint = async function (id) {
    if (!confirm('Delete this sprint?')) return;
    await api(`/sprints/${id}`, { method: 'DELETE' });
    await loadSprints(currentSprintsProjectId);
  };
  document.getElementById('addSprintBtn').addEventListener('click', () => {
    openModal({
      title: 'Add Sprint',
      fields: [
        { name: 'name', label: 'Sprint Name' },
        { name: 'status', label: 'Status', type: 'select', options: ['Planned', 'Active', 'Completed'] },
      ],
      onSubmit: async (v) => { await api(`/projects/${currentSprintsProjectId}/sprints`, { method: 'POST', body: v }); await loadSprints(currentSprintsProjectId); },
    });
  });

  // ---------- Prototypes ----------
  let currentPrototypesProjectId = null;
  let adminPrototypes = [];
  async function loadPrototypes(projectId) {
    currentPrototypesProjectId = projectId;
    adminPrototypes = await api(`/projects/${projectId}/prototypes`);
    renderTable('#prototypesTable tbody', adminPrototypes, (p) => `
      <tr><td>${p.version_number || p.version_label}</td><td>${pill(p.status)}</td><td>${p.updated_at || '-'}</td>
      <td>${p.demo_url ? `<a href="${p.demo_url}" target="_blank" rel="noopener">${p.demo_url}</a>` : 'Prototype output not available yet.'}${demoUrlWarning(p.demo_url)}</td>
      <td class="row-actions">
        ${p.demo_url ? `<button class="secondary" onclick="window.open('${p.demo_url}','_blank')">Open Prototype Demo</button>` : ''}
        ${p.demo_url ? `<button class="secondary" onclick="previewPrototype(${p.id})">Preview</button>` : ''}
        <button class="danger" onclick="deletePrototype(${p.id})">Delete</button>
      </td></tr>`);
    document.getElementById('prototypePreviewWrap').innerHTML = '';
  }
  window.previewPrototype = function (id) {
    const p = adminPrototypes.find((x) => x.id === id);
    const wrap = document.getElementById('prototypePreviewWrap');
    wrap.innerHTML = `
      <div class="panel">
        <h4>Preview — ${p.prototype_name || p.version_number}</h4>
        <iframe class="prototype-preview" src="${p.demo_url}"></iframe>
        <button type="button" onclick="window.open('${p.demo_url}','_blank')">Open Prototype Demo</button>
      </div>`;
  };
  window.deletePrototype = async function (id) {
    if (!confirm('Delete this prototype?')) return;
    await api(`/prototypes/${id}`, { method: 'DELETE' });
    await loadPrototypes(currentPrototypesProjectId);
  };
  document.getElementById('addPrototypeBtn').addEventListener('click', () => {
    openModal({
      title: 'Add Prototype',
      fields: [
        { name: 'version_number', label: 'Version' },
        { name: 'demo_url', label: 'Demo URL' },
        { name: 'notes', label: 'Notes', type: 'textarea' },
      ],
      onSubmit: async (v) => { await api(`/projects/${currentPrototypesProjectId}/prototypes`, { method: 'POST', body: v }); await loadPrototypes(currentPrototypesProjectId); },
    });
  });

  // ---------- Feedback ----------
  let currentFeedbackProjectId = null;
  async function loadFeedback(projectId) {
    currentFeedbackProjectId = projectId;
    const rows = await api(`/projects/${projectId}/feedback`);
    renderTable('#feedbackTable tbody', rows, (r) => `
      <tr><td>${r.code}</td><td>${r.finding}</td><td>${pill(r.decision)}</td><td>${pill(r.status)}</td>
      <td class="row-actions"><button class="danger" onclick="deleteFeedback(${r.id})">Delete</button></td></tr>`);
  }
  window.deleteFeedback = async function (id) {
    if (!confirm('Delete this feedback?')) return;
    await api(`/feedback/${id}`, { method: 'DELETE' });
    await loadFeedback(currentFeedbackProjectId);
  };

  // ---------- Testing ----------
  let currentTestingProjectId = null;
  async function loadTesting(projectId) {
    currentTestingProjectId = projectId;
    const rows = await api(`/projects/${projectId}/tests`);
    renderTable('#testingTable tbody', rows, (r) => `
      <tr><td>${r.code}</td><td>${r.scenario}</td><td>${r.type}</td><td>${pill(r.result)}</td>
      <td class="row-actions"><button class="danger" onclick="deleteTest(${r.id})">Delete</button></td></tr>`);
  }
  window.deleteTest = async function (id) {
    if (!confirm('Delete this test?')) return;
    await api(`/tests/${id}`, { method: 'DELETE' });
    await loadTesting(currentTestingProjectId);
  };

  // ---------- Defects ----------
  let currentDefectsProjectId = null;
  async function loadDefects(projectId) {
    currentDefectsProjectId = projectId;
    const rows = await api(`/projects/${projectId}/defects`);
    renderTable('#defectsTable tbody', rows, (r) => `
      <tr><td>${r.description}</td><td>${pill(r.severity)}</td><td>${pill(r.status)}</td><td></td></tr>`);
  }

  // ---------- Release checklist ----------
  let currentReleaseProjectId = null;
  async function loadRelease(projectId) {
    currentReleaseProjectId = projectId;
    const rows = await api(`/projects/${projectId}/release-checklist`);
    document.getElementById('releaseList').innerHTML = rows.map((r) => `
      <li><span>${r.item}</span><span class="row-actions"><b>${r.status}</b>
      <button class="secondary" onclick="toggleChecklist(${r.id}, '${r.status}')">Toggle</button>
      <button class="danger" onclick="deleteChecklist(${r.id})">Delete</button></span></li>`).join('');
  }
  window.toggleChecklist = async function (id, status) {
    const rows = await api(`/projects/${currentReleaseProjectId}/release-checklist`);
    const item = rows.find((x) => x.id === id);
    await api(`/release-checklist/${id}`, { method: 'PUT', body: { item: item.item, status: status === 'Yes' ? 'No' : 'Yes' } });
    await loadRelease(currentReleaseProjectId);
  };
  window.deleteChecklist = async function (id) {
    if (!confirm('Delete this checklist item?')) return;
    await api(`/release-checklist/${id}`, { method: 'DELETE' });
    await loadRelease(currentReleaseProjectId);
  };
  document.getElementById('addChecklistBtn').addEventListener('click', () => {
    openModal({
      title: 'Add Checklist Item',
      fields: [{ name: 'item', label: 'Item' }, { name: 'status', label: 'Status', type: 'select', options: ['No', 'Yes'] }],
      onSubmit: async (v) => { await api(`/projects/${currentReleaseProjectId}/release-checklist`, { method: 'POST', body: v }); await loadRelease(currentReleaseProjectId); },
    });
  });

  // ---------- Retrospective ----------
  let currentRetroProjectId = null;
  async function loadRetro(projectId) {
    currentRetroProjectId = projectId;
    const rows = await api(`/projects/${projectId}/retrospectives`);
    document.getElementById('retroGrid').innerHTML = rows.map((r) => `
      <div><h4>Went well</h4><p>${r.went_well || '-'}</p>
      <h4>Needs improvement</h4><p>${r.needs_improvement || '-'}</p>
      <h4>Action item</h4><p>${r.action_item || '-'}</p></div>`).join('');
  }
  document.getElementById('addRetroBtn').addEventListener('click', () => {
    openModal({
      title: 'Add Retrospective',
      fields: [
        { name: 'went_well', label: 'What went well', type: 'textarea' },
        { name: 'needs_improvement', label: 'What needs improvement', type: 'textarea' },
        { name: 'action_item', label: 'Action item', type: 'textarea' },
      ],
      onSubmit: async (v) => { await api(`/projects/${currentRetroProjectId}/retrospectives`, { method: 'POST', body: v }); await loadRetro(currentRetroProjectId); },
    });
  });

  // ---------- Audit logs ----------
  async function loadAudit() {
    const rows = await api('/audit-logs');
    renderTable('#auditTable tbody', rows, (a) => `<tr><td>${a.user_name || '-'}</td><td>${a.action}</td><td>${a.entity} #${a.entity_id ?? ''}</td><td>${a.created_at}</td></tr>`);
  }

  // ---------- Settings ----------
  function loadSettings() {
    document.getElementById('settingsName').textContent = user.name;
    document.getElementById('settingsEmail').textContent = user.email;
    document.getElementById('settingsRole').textContent = user.role;
    document.getElementById('settingsApi').textContent = API_BASE;
  }

  const SECTION_LOADERS = {
    dashboard: loadDashboard,
    users: loadUsers,
    roles: loadRoles,
    projects: loadProjects,
    phases: () => {},
    backlog: () => {},
    sprints: () => {},
    prototypes: () => {},
    feedback: () => {},
    testing: () => {},
    defects: () => {},
    release: () => {},
    retrospective: () => {},
    reports: loadDashboard,
    audit: loadAudit,
    settings: loadSettings,
  };

  function navigateToSection(key) {
    setActiveSection(key);
    const loader = SECTION_LOADERS[key];
    if (loader) loader();
  }

  const deepLinkSection = new URLSearchParams(window.location.search).get('section');
  const initialSection = (deepLinkSection && SECTION_LOADERS[deepLinkSection]) ? deepLinkSection : 'dashboard';
  renderSidebar('Admin', initialSection, navigateToSection);

  (async function init() {
    await loadRoles();
    await loadUsers();
    await loadProjects();
    await loadDashboard();
    if (initialSection !== 'dashboard') navigateToSection(initialSection);
  })();
})();
