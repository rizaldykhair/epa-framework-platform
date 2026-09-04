(function () {
  const user = guardDashboard('Product Owner');
  if (!user) return;

  document.getElementById('userInfo').innerHTML = renderUserChip(user);
  document.getElementById('logoutBtn').addEventListener('click', logout);

  let projects = [];
  let projectId = null;
  let backlog = [];
  let feedback = [];
  let sprints = [];
  let projectMembers = [];
  const selectedBacklogIds = new Set();
  const ASSIGNABLE_BACKLOG_STATUSES = ['Approved', 'Ready for Sprint'];

  function setProject(id) {
    projectId = id;
    const p = projects.find((x) => x.id === id);
    document.getElementById('projectLabel').textContent = p ? p.name : '-';
  }

  async function loadDashboard() {
    const [metrics, bl, fb] = await Promise.all([
      api(`/projects/${projectId}/dashboard`),
      api(`/projects/${projectId}/backlog`),
      api(`/projects/${projectId}/feedback`),
    ]);
    document.getElementById('releaseScore').textContent = metrics.releaseScore + '%';
    document.getElementById('m-totalBacklog').textContent = metrics.totalBacklog;
    document.getElementById('m-feedbackRate').textContent = metrics.feedbackRate + '%';
    document.getElementById('m-taskSuccess').textContent = metrics.taskSuccess + '%';
    document.getElementById('m-openDefects').textContent = metrics.openDefects;

    const byPriority = { Must: 0, Should: 0, Could: 0, Wont: 0 };
    bl.forEach((b) => { byPriority[b.priority] = (byPriority[b.priority] || 0) + 1; });
    renderTable('#priorityTable tbody', Object.entries(byPriority), ([k, v]) => `<tr><td>${k}</td><td>${v}</td></tr>`);

    const pending = fb.filter((f) => f.decision === 'Clarify');
    renderTable('#pendingTable tbody', pending, (f) => `<tr><td>${f.finding}</td><td>${pill(f.status)}</td></tr>`);
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

  async function loadProjects() {
    projects = await api('/projects');
    renderTable('#projectsTable tbody', projects, (p) => `
      <tr><td>${p.name}</td><td>${pill(p.status)}</td><td>${pill(p.epa_completion_status || 'Unknown')}</td>
      <td class="row-actions">
        <button class="secondary" onclick="editProjectInfo(${p.id})">Edit Project</button>
        <button class="secondary" onclick="window.location.href='project-detail.html?id=${p.id}'">View EPA Workflow</button>
      </td></tr>`);
    const select = document.getElementById('dashboardProjectSelect');
    select.innerHTML = projects.map((p) => `<option value="${p.id}">${p.name}</option>`).join('');
    select.onchange = () => { setProject(Number(select.value)); loadDashboard(); };
    if (projects.length) { setProject(projects[0].id); }
  }
  window.editProjectInfo = function (id) {
    const p = projects.find((x) => x.id === id);
    openModal({
      title: 'Edit Project (Functional Info)',
      fields: [
        { name: 'name', label: 'Product/Application Name' },
        { name: 'description', label: 'Business Problem', type: 'textarea' },
        { name: 'current_epa_phase', label: 'Current EPA Phase' },
      ],
      initial: p,
      onSubmit: async (v) => { await api(`/projects/${id}`, { method: 'PUT', body: v }); await loadProjects(); },
    });
  };
  // Continue with AI Generator now lives only on the project-detail (View EPA Workflow)
  // page, where the missing-artifact context makes the action meaningful.
  document.getElementById('addProposalBtn').addEventListener('click', () => {
    openModal({
      title: 'New Project Proposal',
      fields: [
        { name: 'name', label: 'Product/Application Name' },
        { name: 'description', label: 'Business Problem', type: 'textarea' },
        { name: 'client_name', label: 'Target Users' },
        { name: 'current_epa_phase', label: 'Main Objective' },
        { name: 'project_type', label: 'Project Type', type: 'select', options: ['Web App', 'Mobile App', 'Web + Mobile'] },
        { name: 'target_release_date', label: 'Proposed Timeline', type: 'date' },
      ],
      initial: { project_type: 'Web App' },
      onSubmit: async (v) => { await api('/projects', { method: 'POST', body: v }); await loadProjects(); },
    });
  });

  async function loadRequirements() {
    backlog = await api(`/projects/${projectId}/backlog`);
    const order = { Must: 0, Should: 1, Could: 2, Wont: 3 };
    const sorted = [...backlog].sort((a, b) => (order[a.priority] ?? 9) - (order[b.priority] ?? 9));
    renderTable('#requirementsTable tbody', sorted, (r) => `
      <tr><td>${r.title}</td><td>${r.acceptance_criteria || '-'}</td><td>${r.business_value || '-'}</td><td>${pill(r.priority)}</td><td>${pill(r.status)}</td>
      <td class="row-actions"><button class="secondary" onclick="editRequirement(${r.id})">Edit</button></td></tr>`);
  }
  function requirementFields(initial = {}) {
    return {
      fields: [
        { name: 'title', label: 'Requirement Title' },
        { name: 'user_story', label: 'User Story', type: 'textarea' },
        { name: 'acceptance_criteria', label: 'Acceptance Criteria', type: 'textarea' },
        { name: 'priority', label: 'Priority', type: 'select', options: ['Must', 'Should', 'Could', 'Wont'] },
        { name: 'business_value', label: 'Business Value' },
        { name: 'status', label: 'Status', type: 'select', options: ['Draft', 'Approved', 'Rejected', 'Revised'] },
      ],
      initial: { priority: 'Should', status: 'Draft', ...initial },
    };
  }
  document.getElementById('addRequirementBtn').addEventListener('click', () => {
    const { fields, initial } = requirementFields();
    openModal({
      title: 'Requirement Form',
      fields,
      initial,
      onSubmit: async (v) => { await api(`/projects/${projectId}/backlog`, { method: 'POST', body: v }); await loadRequirements(); },
    });
  });
  window.editRequirement = function (id) {
    const r = backlog.find((x) => x.id === id);
    const { fields } = requirementFields(r);
    openModal({
      title: 'Edit Requirement',
      fields,
      initial: r,
      onSubmit: async (v) => { await api(`/backlog/${id}`, { method: 'PUT', body: v }); await loadRequirements(); },
    });
  };

  function sprintNameFor(sprintId) {
    const s = sprints.find((x) => x.id === sprintId);
    return s ? s.name : '-';
  }

  function renderBacklogSelectionBar() {
    document.getElementById('backlogSelectionCount').textContent = `${selectedBacklogIds.size} item(s) selected`;
    document.getElementById('assignToSprintBtn').disabled = selectedBacklogIds.size === 0;
  }

  async function loadBacklog() {
    [backlog, sprints] = await Promise.all([
      api(`/projects/${projectId}/backlog`),
      api(`/projects/${projectId}/sprints`),
    ]);
    selectedBacklogIds.clear();
    renderBacklogSelectionBar();
    renderTable('#backlogTable tbody', backlog, (r) => {
      const assignable = ASSIGNABLE_BACKLOG_STATUSES.includes(r.status);
      return `
      <tr>
        <td><input type="checkbox" class="backlog-select-check" value="${r.id}" ${assignable ? '' : 'disabled title="Only Approved/Ready for Sprint items can be assigned"'} /></td>
        <td>${r.code}</td><td>${r.title}</td><td>${pill(r.priority)}</td><td>${pill(r.status)}</td>
        <td>${r.target_sprint_id ? sprintNameFor(r.target_sprint_id) : '-'}</td>
        <td class="row-actions"><button class="secondary" onclick="editBacklog(${r.id})">Edit</button><button class="danger" onclick="deleteBacklog(${r.id})">Delete</button></td>
      </tr>`;
    });
    document.querySelectorAll('.backlog-select-check').forEach((cb) => {
      cb.addEventListener('change', () => {
        const id = Number(cb.value);
        if (cb.checked) selectedBacklogIds.add(id); else selectedBacklogIds.delete(id);
        renderBacklogSelectionBar();
      });
    });
  }
  window.editBacklog = function (id) {
    const b = backlog.find((x) => x.id === id);
    openModal({
      title: 'Edit Backlog',
      fields: [
        { name: 'title', label: 'User Story' },
        { name: 'priority', label: 'Prioritas', type: 'select', options: ['Must', 'Should', 'Could', 'Wont'] },
        { name: 'status', label: 'Status', type: 'select', options: ['Draft', 'Approved', 'Rejected', 'Revised', 'Ready for Sprint', 'To Do', 'In Progress', 'Done', 'Deferred'] },
      ],
      initial: b,
      onSubmit: async (v) => { await api(`/backlog/${id}`, { method: 'PUT', body: v }); await loadBacklog(); },
    });
  };
  window.deleteBacklog = async function (id) {
    if (!confirm('Delete this backlog item?')) return;
    await api(`/backlog/${id}`, { method: 'DELETE' });
    await loadBacklog();
  };
  document.getElementById('addBacklogBtn').addEventListener('click', () => {
    openModal({
      title: 'Add Backlog',
      fields: [{ name: 'title', label: 'User Story' }, { name: 'priority', label: 'Prioritas', type: 'select', options: ['Must', 'Should', 'Could', 'Wont'] }],
      onSubmit: async (v) => { await api(`/projects/${projectId}/backlog`, { method: 'POST', body: v }); await loadBacklog(); },
    });
  });

  document.getElementById('assignToSprintBtn').addEventListener('click', async () => {
    if (!selectedBacklogIds.size) return;
    projectMembers = await api(`/projects/${projectId}/members`);
    const developers = projectMembers.filter((m) => m.role_in_project === 'Developer');
    const activeSprints = sprints.filter((s) => s.status !== 'Completed');

    openModal({
      title: `Assign to Sprint (${selectedBacklogIds.size} backlog item(s) selected)`,
      fields: [
        { name: 'sprint_id', label: 'Existing Sprint (leave blank to create new)', type: 'select', options: [{ value: '', label: '- Create New Sprint -' }, ...activeSprints.map((s) => ({ value: s.id, label: s.name }))] },
        { name: 'sprint_name', label: 'New Sprint Name (if creating new)' },
        { name: 'start_date', label: 'Start Date', type: 'date' },
        { name: 'end_date', label: 'End Date', type: 'date' },
        { name: 'assigned_developer', label: 'Assigned Developer', type: 'select', options: [{ value: '', label: '- Unassigned -' }, ...developers.map((m) => ({ value: m.user_id, label: m.name }))] },
        { name: 'target_prototype_version', label: 'Target Prototype Version' },
      ],
      onSubmit: async (v) => {
        const body = {
          backlog_ids: Array.from(selectedBacklogIds),
          assigned_developer: v.assigned_developer || null,
          target_prototype_version: v.target_prototype_version || null,
        };
        if (v.sprint_id) {
          body.sprint_id = Number(v.sprint_id);
          body.create_new_sprint = false;
        } else {
          if (!v.sprint_name) throw new Error('New Sprint Name is required when not selecting an existing sprint.');
          body.create_new_sprint = true;
          body.sprint_name = v.sprint_name;
          body.start_date = v.start_date || null;
          body.end_date = v.end_date || null;
        }
        const result = await api(`/projects/${projectId}/backlog/assign-to-sprint`, { method: 'POST', body });
        await loadBacklog();
        await loadSprints();
        const skippedMsg = result.skipped.length ? `\n${result.skipped.length} item(s) skipped (status is not Approved/Ready for Sprint).` : '';
        alert(`${result.created_sprint_backlog.length} backlog item(s) successfully assigned to the sprint.${skippedMsg}`);
      },
    });
  });

  async function loadSprints() {
    sprints = await api(`/projects/${projectId}/sprints`);
    renderTable('#sprintsTable tbody', sprints, (s) => `<tr><td>${s.name}</td><td>${pill(s.status)}</td></tr>`);
  }
  document.getElementById('addSprintBtn').addEventListener('click', () => {
    openModal({
      title: 'Add Sprint',
      fields: [{ name: 'name', label: 'Nama Sprint' }, { name: 'status', label: 'Status', type: 'select', options: ['Planned', 'Active', 'Completed'] }],
      onSubmit: async (v) => { await api(`/projects/${projectId}/sprints`, { method: 'POST', body: v }); await loadSprints(); },
    });
  });

  async function loadFeedback() {
    feedback = await api(`/projects/${projectId}/feedback`);
    renderTable('#feedbackTable tbody', feedback, (f) => `
      <tr><td>${f.code}</td><td>${f.finding}</td><td>${pill(f.decision)}</td><td>${f.reason || '-'}</td><td>${pill(f.status)}</td>
      <td class="row-actions">
        <button class="secondary" onclick="reviewFeedback(${f.id})">Review</button>
        ${f.decision !== 'Converted' ? `<button class="secondary" onclick="convertFeedback(${f.id})">Convert</button>` : ''}
      </td></tr>`);
  }
  window.reviewFeedback = function (id) {
    const f = feedback.find((x) => x.id === id);
    openModal({
      title: 'Feedback Review',
      fields: [
        { name: 'decision', label: 'Decision', type: 'select', options: ['Clarify', 'Converted', 'Rejected'] },
        { name: 'status', label: 'Status', type: 'select', options: ['Open', 'Deferred', 'Closed'] },
        { name: 'reason', label: 'Reason', type: 'textarea' },
      ],
      initial: f,
      onSubmit: async (v) => { await api(`/feedback/${id}`, { method: 'PUT', body: { ...f, ...v } }); await loadFeedback(); },
    });
  };
  window.convertFeedback = function (id) {
    const f = feedback.find((x) => x.id === id);
    openModal({
      title: 'Convert to Backlog',
      fields: [{ name: 'title', label: 'Judul Backlog' }, { name: 'priority', label: 'Prioritas', type: 'select', options: ['Must', 'Should', 'Could', 'Wont'] }],
      initial: { title: f.finding, priority: 'Should' },
      onSubmit: async (v) => { await api(`/feedback/${id}/convert`, { method: 'POST', body: v }); await loadFeedback(); },
    });
  };

  let poPrototypes = [];
  async function loadPrototypes() {
    poPrototypes = await api(`/projects/${projectId}/prototypes`);
    renderTable('#prototypesTable tbody', poPrototypes, (p) => `
      <tr><td>${p.version_number || p.version_label}</td><td>${pill(p.status)}</td>
      <td>${p.demo_url ? `<a href="${p.demo_url}" target="_blank" rel="noopener">${p.demo_url}</a>` : 'Prototype output not available yet.'}${demoUrlWarning(p.demo_url)}</td>
      <td>${p.implemented_feedback_summary || '-'}</td>
      <td class="row-actions">
        ${p.demo_url ? `<button class="secondary" onclick="window.open('${p.demo_url}','_blank')">Open Prototype Demo</button>` : ''}
        <button class="secondary" onclick="reviewPrototype(${p.id})">Review Prototype</button>
      </td></tr>`);
  }
  window.reviewPrototype = function (id) {
    const p = poPrototypes.find((x) => x.id === id);
    openModal({
      title: `Review Prototype ${p.version_number || ''}`,
      fields: [
        { name: 'summary', label: 'Implemented Feedback Summary', type: 'textarea' },
        { name: 'decision', label: 'Keputusan', type: 'select', options: ['Approved for Next Phase', 'Rejected - Needs Revision'] },
      ],
      initial: { summary: p.implemented_feedback_summary || '-' },
      onSubmit: async (v) => {
        await api(`/projects/${projectId}/release-checklist`, {
          method: 'POST',
          body: { item: `Prototype ${p.version_number} (${p.prototype_name || ''}) review`, status: v.decision.startsWith('Approved') ? 'Yes' : 'No' },
        });
      },
    });
  };

  let release = [];
  async function loadRelease() {
    release = await api(`/projects/${projectId}/release-checklist`);
    document.getElementById('releaseList').innerHTML = release.map((r) => `
      <li><span>${r.item}</span><span class="row-actions"><b>${r.status}</b>
      <button class="secondary" onclick="toggleChecklist(${r.id})">Toggle</button></span></li>`).join('');
  }
  window.toggleChecklist = async function (id) {
    const item = release.find((x) => x.id === id);
    await api(`/release-checklist/${id}`, { method: 'PUT', body: { item: item.item, status: item.status === 'Yes' ? 'No' : 'Yes' } });
    await loadRelease();
  };
  document.getElementById('addChecklistBtn').addEventListener('click', () => {
    openModal({
      title: 'Add Checklist Item',
      fields: [{ name: 'item', label: 'Item' }, { name: 'status', label: 'Status', type: 'select', options: ['No', 'Yes'] }],
      onSubmit: async (v) => { await api(`/projects/${projectId}/release-checklist`, { method: 'POST', body: v }); await loadRelease(); },
    });
  });

  async function loadRetro() {
    const rows = await api(`/projects/${projectId}/retrospectives`);
    document.getElementById('retroGrid').innerHTML = rows.map((r) => `
      <div><h4>Went well</h4><p>${r.went_well || '-'}</p><h4>Needs improvement</h4><p>${r.needs_improvement || '-'}</p><h4>Action item</h4><p>${r.action_item || '-'}</p></div>`).join('');
  }
  document.getElementById('addRetroBtn').addEventListener('click', () => {
    openModal({
      title: 'Add Retrospective',
      fields: [
        { name: 'went_well', label: 'What went well', type: 'textarea' },
        { name: 'needs_improvement', label: 'What needs improvement', type: 'textarea' },
        { name: 'action_item', label: 'Action item', type: 'textarea' },
      ],
      onSubmit: async (v) => { await api(`/projects/${projectId}/retrospectives`, { method: 'POST', body: v }); await loadRetro(); },
    });
  });

  async function loadReports() {
    const m = await api(`/projects/${projectId}/dashboard`);
    document.getElementById('r-totalBacklog').textContent = m.totalBacklog;
    document.getElementById('r-feedbackRate').textContent = m.feedbackRate + '%';
    document.getElementById('r-taskSuccess').textContent = m.taskSuccess + '%';
    document.getElementById('r-releaseScore').textContent = m.releaseScore + '%';
  }

  const SECTION_LOADERS = {
    dashboard: loadDashboard,
    projects: () => {},
    requirements: loadRequirements,
    backlog: loadBacklog,
    'sprint-backlog': loadSprints,
    'feedback-review': loadFeedback,
    'prototype-review': loadPrototypes,
    release: loadRelease,
    retrospective: loadRetro,
    reports: loadReports,
  };

  function navigateToSection(key) {
    setActiveSection(key);
    const loader = SECTION_LOADERS[key];
    if (loader) loader();
  }

  const deepLinkSection = new URLSearchParams(window.location.search).get('section');
  const initialSection = (deepLinkSection && SECTION_LOADERS[deepLinkSection]) ? deepLinkSection : 'dashboard';
  renderSidebar('Product Owner', initialSection, navigateToSection);

  (async function init() {
    await loadProjects();
    if (projectId) await loadDashboard();
    if (initialSection !== 'dashboard') navigateToSection(initialSection);
  })();
})();
