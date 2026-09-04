(function () {
  const user = guardDashboard('Developer');
  if (!user) return;

  document.getElementById('userInfo').innerHTML = renderUserChip(user);
  document.getElementById('logoutBtn').addEventListener('click', logout);

  let projects = [];
  let projectId = null;
  let myBacklog = [];
  let myFeedback = [];
  let myDefects = [];
  let mySprintItems = [];
  let prototypes = [];
  let sprints = [];
  let selectedPrototypeId = null;

  function setProject(id) {
    projectId = id;
    const p = projects.find((x) => x.id === id);
    document.getElementById('projectLabel').textContent = p ? p.name : '-';
    ['dashboardProjectSelect', 'workspaceProjectSelect'].forEach((selId) => {
      const el = document.getElementById(selId);
      if (el) el.value = String(id);
    });
  }

  async function loadProjects() {
    projects = await api('/projects');
    renderTable('#projectsTable tbody', projects, (p) => `
      <tr><td>${p.name}</td><td>${pill(p.status)}</td>
      <td class="row-actions"><button class="secondary" onclick="window.location.href='project-detail.html?id=${p.id}'">View EPA Workflow</button></td></tr>`);
    const opts = projects.map((p) => `<option value="${p.id}">${p.name}</option>`).join('');
    ['dashboardProjectSelect', 'workspaceProjectSelect'].forEach((selId) => {
      const el = document.getElementById(selId);
      if (!el) return;
      el.innerHTML = opts;
      el.onchange = () => { setProject(Number(el.value)); loadDashboard(); loadWorkspace(); };
    });
    if (projects.length) setProject(projects[0].id);
  }

  async function refreshMine() {
    [myBacklog, myFeedback, myDefects, mySprintItems] = await Promise.all([
      api('/my/backlog'),
      api('/my/feedback'),
      api('/my/defects'),
      api('/my/sprint-items'),
    ]);
  }

  async function loadDashboard() {
    await refreshMine();
    const projectBacklog = myBacklog.filter((b) => b.project_id === projectId);
    document.getElementById('m-assignedTasks').textContent = projectBacklog.length;
    document.getElementById('m-inProgress').textContent = projectBacklog.filter((b) => b.status === 'In Progress').length;
    document.getElementById('m-completed').textContent = projectBacklog.filter((b) => b.status === 'Done').length;
    document.getElementById('m-feedbackToImplement').textContent = myFeedback.filter((f) => !f.implemented_at).length;
    document.getElementById('m-openDefects').textContent = myDefects.filter((d) => d.status === 'Open').length;
    const done = projectBacklog.filter((b) => b.status === 'Done').length;
    document.getElementById('sprintCompletion').textContent = projectBacklog.length ? Math.round(done / projectBacklog.length * 100) + '%' : '0%';
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

  function backlogStatusOptions() {
    return ['To Do', 'In Progress', 'Review', 'Done'];
  }

  async function loadSprintTasks() {
    await refreshMine();
    renderTable('#sprintTasksTable tbody', mySprintItems, (s) => `
      <tr><td>${s.title} (${s.code})</td><td>${s.sprint_name}</td><td>${pill(s.status)}</td>
      <td class="row-actions"><button class="secondary" onclick="updateSprintItemStatus(${s.id})">Update Status</button></td></tr>`);
    renderTable('#myBacklogTable tbody', myBacklog, (b) => `
      <tr><td>${b.code}</td><td>${b.title}</td><td>${pill(b.status)}</td>
      <td class="row-actions"><button class="secondary" onclick="updateBacklogStatus(${b.id})">Update Status</button></td></tr>`);
  }
  window.updateSprintItemStatus = function (id) {
    const item = mySprintItems.find((x) => x.id === id);
    openModal({
      title: 'Update Status Sprint Task',
      fields: [
        { name: 'status', label: 'Task Status', type: 'select', options: ['Planned', 'In Progress', 'Done'] },
        { name: 'implementation_notes', label: 'Implementation Notes', type: 'textarea' },
        { name: 'blocker_notes', label: 'Blocker Notes', type: 'textarea' },
      ],
      initial: item,
      onSubmit: async (v) => { await api(`/sprint-items/${id}`, { method: 'PUT', body: { assigned_to: user.id, status: v.status } }); await loadSprintTasks(); },
    });
  };
  window.updateBacklogStatus = function (id) {
    const b = myBacklog.find((x) => x.id === id);
    openModal({
      title: 'Development Task Update',
      fields: [{ name: 'status', label: 'Task Status', type: 'select', options: backlogStatusOptions() }],
      initial: b,
      onSubmit: async (v) => { await api(`/backlog/${id}`, { method: 'PUT', body: { ...b, status: v.status } }); await loadSprintTasks(); await loadWorkspace(); },
    });
  };

  function linkRow(label, url) {
    return `<p><b>${label}:</b> ${url ? `<a href="${url}" target="_blank" rel="noopener">${url}</a>` : '<span class="pill">Not available yet</span>'}</p>`;
  }

  function renderPrototypeOutput(p) {
    const box = document.getElementById('prototypeOutput');
    if (!p) { box.innerHTML = '<p>No prototype increment yet.</p>'; return; }
    const feedbackForThis = myFeedback.filter((f) => f.implemented_at && f.project_id === p.project_id);
    box.innerHTML = `
      <div class="panel">
        <h3>${p.prototype_name || 'Prototype'} — ${p.version_number || p.version_label} ${pill(p.status)}</h3>
        <p class="small">Last updated: ${p.updated_at || p.demo_date || '-'}</p>
        ${demoUrlWarning(p.demo_url)}
        ${linkRow('Demo URL', p.demo_url)}
        ${linkRow('Repository URL', p.repository_url)}
        ${linkRow('Build Output URL', p.build_output_url)}
        ${p.mobile_build_url ? linkRow('Mobile Build (APK/IPA)', p.mobile_build_url) : ''}
        <h4>Implemented Feedback</h4>
        ${p.implemented_feedback_summary ? `<p>${p.implemented_feedback_summary}</p>` : ''}
        <ul class="checklist">${feedbackForThis.length ? feedbackForThis.map((f) => `<li><span>${f.finding}</span></li>`).join('') : '<li><span>No feedback implemented yet.</span></li>'}</ul>
        <h4>Preview</h4>
        <div id="protoPreviewWrap">
          ${p.demo_url ? `<iframe class="prototype-preview" src="${p.demo_url}"></iframe>` : '<p>Prototype output not available yet.</p>'}
          <button type="button" onclick="window.open('${p.demo_url || ''}', '_blank')" ${p.demo_url ? '' : 'disabled'}>Open Prototype Demo</button>
        </div>
      </div>`;
  }
  window.viewPrototypeOutput = function (id) {
    selectedPrototypeId = id;
    const p = prototypes.find((x) => x.id === id);
    renderPrototypeOutput(p);
    if (p && p.demo_url) window.open(p.demo_url, '_blank');
  };

  async function loadWorkspace() {
    await refreshMine();
    const projectFeedback = myFeedback.filter((f) => f.project_id === projectId);
    const projectBacklog = myBacklog.filter((b) => b.project_id === projectId);
    const projectSprintItems = mySprintItems.filter((s) => s.project_id === projectId);
    [prototypes, sprints] = await Promise.all([
      api(`/projects/${projectId}/prototypes`),
      api(`/projects/${projectId}/sprints`),
    ]);

    renderTable('#wsSprintTable tbody', projectSprintItems, (s) => `<tr><td>${s.title} (${s.code})</td><td>${pill(s.status)}</td></tr>`);
    renderTable('#wsFeedbackTable tbody', projectFeedback, (f) => `
      <tr><td>${f.finding}</td><td>${f.implemented_at ? pill('Implemented') : pill(f.status)}</td>
      <td class="row-actions">${!f.implemented_at ? `<button class="secondary" onclick="openImplementFeedback(${f.id})">Implement</button>` : ''}</td></tr>`);
    renderTable('#wsTaskBoard tbody', projectBacklog, (b) => `
      <tr><td>${b.code}</td><td>${b.title}</td><td>${pill(b.status)}</td>
      <td class="row-actions"><button class="secondary" onclick="updateBacklogStatus(${b.id})">Update</button></td></tr>`);
    renderTable('#wsPrototypeTable tbody', prototypes, (p) => `
      <tr><td>${p.version_number || p.version_label}</td><td>${p.prototype_name || '-'}</td><td>${pill(p.status)}</td>
      <td class="row-actions">
        <button class="secondary" onclick="viewPrototypeOutput(${p.id})">Lihat Output</button>
        <button class="secondary" onclick="editPrototype(${p.id})">Edit</button>
        ${Number(p.generated_by_ai) ? `<button class="secondary" onclick="openRegenerateModal(${p.id})">Regenerate from Feedback</button>` : ''}
      </td></tr>`);

    const target = prototypes.find((x) => x.id === selectedPrototypeId) || prototypes[0];
    selectedPrototypeId = target ? target.id : null;
    renderPrototypeOutput(target);
    renderIncSourceList();
  }

  // ---------- AI Prototype Increment Generator ----------
  let incSourceType = 'feedback';
  const incSelectedIds = { feedback: [], backlog: [], defect: [] };

  function incSourceItems(type) {
    if (type === 'feedback') return myFeedback.filter((f) => f.project_id === projectId && !f.implemented_at && f.decision === 'Converted');
    if (type === 'backlog') return myBacklog.filter((b) => b.project_id === projectId && b.status !== 'Done');
    return myDefects.filter((d) => d.project_id === projectId && d.status !== 'Verified' && d.status !== 'Closed');
  }
  function incItemLabel(type, item) {
    if (type === 'feedback') return item.finding;
    if (type === 'backlog') return `${item.code} - ${item.title}`;
    return item.title || item.description;
  }

  function renderIncSourceList() {
    const items = incSourceItems(incSourceType);
    document.getElementById('incSourceList').innerHTML = items.length
      ? items.map((item) => `
        <label><input type="checkbox" class="inc-source-check" value="${item.id}" ${incSelectedIds[incSourceType].includes(item.id) ? 'checked' : ''} /> ${incItemLabel(incSourceType, item)}</label>
      `).join('')
      : '<p>No items in this category for this project.</p>';
    document.querySelectorAll('.inc-source-check').forEach((cb) => {
      cb.addEventListener('change', () => {
        const id = Number(cb.value);
        const list = incSelectedIds[incSourceType];
        const idx = list.indexOf(id);
        if (cb.checked && idx === -1) list.push(id);
        if (!cb.checked && idx !== -1) list.splice(idx, 1);
      });
    });
  }
  document.querySelectorAll('.inc-source-tab').forEach((tab) => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.inc-source-tab').forEach((t) => t.classList.remove('active'));
      tab.classList.add('active');
      incSourceType = tab.dataset.source;
      renderIncSourceList();
    });
  });

  function incOptionsFromForm() {
    return {
      output_type: document.getElementById('incOutputType').value,
      ui_style: document.getElementById('incUiStyle').value,
      image_mode: document.getElementById('incImageMode').value,
      include_gallery: document.getElementById('incGallery').value,
      feedback_ids: incSelectedIds.feedback,
      backlog_ids: incSelectedIds.backlog,
      defect_ids: incSelectedIds.defect,
      source_type: 'feedback_backlog',
    };
  }

  function fillIncrementFields(result) {
    document.getElementById('incNextVersion').value = result.version || '';
    document.getElementById('incDemoUrl').value = result.demo_url || '';
    document.getElementById('incRepoUrl').value = result.repository_url || '';
    document.getElementById('incBuildUrl').value = result.build_output_url || '';
    document.getElementById('incMobileLink').value = result.mobile_apk_ipa_link || 'Not generated - responsive web preview available';
    document.getElementById('incMobilePreviewUrl').value = result.mobile_preview_url || '';
  }

  document.getElementById('incPreviewBtn').addEventListener('click', async () => {
    const result = await api(`/projects/${projectId}/ai-generator/preview-increment-plan`, { method: 'POST', body: incOptionsFromForm() });
    fillIncrementFields(result);
    const box = document.getElementById('incPreviewResult');
    box.classList.remove('hidden');
    box.innerHTML = `<div class="inc-preview-box">
      <p><b>Next version:</b> ${result.version}</p>
      <p><b>Source:</b> ${result.source_backlog_count} backlog, ${result.source_feedback_count} feedback, ${result.source_defect_count} defect</p>
      <p><b>Gallery/Product Display detected:</b> ${result.gallery_detected ? 'Yes' : 'No'}</p>
    </div>`;
  });

  document.getElementById('incGenerateBtn').addEventListener('click', async () => {
    const btn = document.getElementById('incGenerateBtn');
    btn.disabled = true;
    btn.textContent = 'Generating...';
    try {
      const result = await api(`/projects/${projectId}/ai-generator/generate-increment`, { method: 'POST', body: incOptionsFromForm() });
      fillIncrementFields(result);
      document.getElementById('incOpenDemoBtn').disabled = false;
      document.getElementById('incOpenPreviewBtn').disabled = false;
      document.getElementById('incOpenMobileBtn').disabled = !/^https?:\/\//.test(result.mobile_apk_ipa_link || '');
      document.getElementById('incCopyLinksBtn').disabled = false;
      incSelectedIds.feedback = []; incSelectedIds.backlog = []; incSelectedIds.defect = [];
      await loadWorkspace();
      window.open(result.preview_url || result.demo_url, '_blank');
    } catch (err) {
      alert(`Failed to generate prototype increment: ${err.message}`);
    } finally {
      btn.disabled = false;
      btn.textContent = 'Generate Prototype Increment';
    }
  });

  document.getElementById('incOpenDemoBtn').addEventListener('click', () => window.open(document.getElementById('incDemoUrl').value, '_blank'));
  document.getElementById('incOpenPreviewBtn').addEventListener('click', () => window.open(document.getElementById('incMobilePreviewUrl').value, '_blank'));
  document.getElementById('incOpenMobileBtn').addEventListener('click', () => window.open(document.getElementById('incMobileLink').value, '_blank'));
  document.getElementById('incCopyLinksBtn').addEventListener('click', () => {
    const links = ['incNextVersion', 'incDemoUrl', 'incRepoUrl', 'incBuildUrl', 'incMobileLink', 'incMobilePreviewUrl']
      .map((id) => document.getElementById(id).value).join('\n');
    navigator.clipboard?.writeText(links);
    alert('Links copied to clipboard.');
  });

  window.openImplementFeedback = function (feedbackId) {
    const f = myFeedback.find((x) => x.id === feedbackId);
    openModal({
      title: 'Feedback Implementation',
      fields: [
        { name: 'implementation_action', label: 'Implementation Action' },
        { name: 'before_change_description', label: 'Before Change Description', type: 'textarea' },
        { name: 'after_change_description', label: 'After Change Description', type: 'textarea' },
        { name: 'developer_notes', label: 'Developer Notes', type: 'textarea' },
        { name: 'status', label: 'Status', type: 'select', options: ['Not Started', 'In Progress', 'Implemented', 'Need Clarification'] },
      ],
      initial: { implementation_action: f.finding, status: 'Implemented' },
      onSubmit: async (v) => {
        await api(`/feedback/${feedbackId}/implementations`, { method: 'POST', body: v });
        if (v.status === 'Implemented') await api(`/feedback/${feedbackId}/implement`, { method: 'POST' });
        await loadWorkspace();
      },
    });
  };

  function prototypeFormFields(initial = {}) {
    return {
      fields: [
        { name: 'sprint_id', label: 'Sprint', type: 'select', options: sprints.map((s) => ({ value: s.id, label: s.name })) },
        { name: 'backlog_id', label: 'Backlog Item', type: 'select', options: myBacklog.filter((b) => b.project_id === projectId).map((b) => ({ value: b.id, label: `${b.code} - ${b.title}` })) },
        { name: 'version_number', label: 'Prototype Version Number' },
        { name: 'prototype_name', label: 'Prototype Name' },
        { name: 'development_type', label: 'Development Type', type: 'select', options: ['New Feature', 'Improvement', 'Bug Fix', 'UI Revision', 'Integration'] },
        { name: 'demo_url', label: 'Demo URL' },
        { name: 'repository_url', label: 'Repository URL' },
        { name: 'build_output_url', label: 'Build Output URL' },
        { name: 'mobile_build_url', label: 'Mobile APK/IPA Link' },
        { name: 'notes', label: 'Technical Notes', type: 'textarea' },
        { name: 'implemented_feedback_summary', label: 'Implemented Feedback Summary', type: 'textarea' },
        { name: 'status', label: 'Status', type: 'select', options: ['Planned', 'In Progress', 'Ready for Testing', 'Revised', 'Completed'] },
      ],
      initial: { development_type: 'New Feature', status: 'Planned', ...initial },
    };
  }
  document.getElementById('addPrototypeBtn').addEventListener('click', () => {
    const { fields, initial } = prototypeFormFields();
    openModal({
      title: 'Create Prototype Increment',
      fields,
      initial,
      onSubmit: async (v) => { await api(`/projects/${projectId}/prototypes`, { method: 'POST', body: v }); await loadWorkspace(); },
    });
  });
  document.getElementById('generateMissingPrototypeBtn').addEventListener('click', () => {
    openModal({
      title: 'Generate Prototype Output (AI) — for assigned project with existing backlog',
      fields: [
        { name: 'prototype_name', label: 'Prototype Name' },
        { name: 'design_style', label: 'Design Style', type: 'select', options: ['Simple', 'Modern', 'Corporate', 'Marketplace', 'Dashboard'] },
        { name: 'primary_color', label: 'Primary Color', type: 'color' },
      ],
      initial: { design_style: 'Modern', primary_color: '#0b4a3a' },
      onSubmit: async (v) => {
        const result = await api(`/projects/${projectId}/ai-continuation/generate-prototype`, { method: 'POST', body: v });
        await loadWorkspace();
        window.open(result.demo_url, '_blank');
      },
    });
  });
  window.editPrototype = function (id) {
    const p = prototypes.find((x) => x.id === id);
    const { fields } = prototypeFormFields(p);
    openModal({
      title: 'Edit Prototype Increment',
      fields,
      initial: p,
      onSubmit: async (v) => { await api(`/prototypes/${id}`, { method: 'PUT', body: v }); await loadWorkspace(); },
    });
  };

  window.openRegenerateModal = async function (prototypeId) {
    const allFeedback = await api(`/projects/${projectId}/feedback`);
    const projectFeedback = allFeedback.filter((f) => !f.implemented_at);
    document.getElementById('modalTitle').textContent = 'Regenerate Prototype from Feedback';
    document.getElementById('modalError').textContent = '';
    document.getElementById('modalBody').innerHTML = projectFeedback.length
      ? projectFeedback.map((f) => `
        <label class="form-group" style="flex-direction:row;align-items:center;gap:8px">
          <input type="checkbox" value="${f.id}" class="regen-feedback-check" /> ${f.finding}
        </label>`).join('')
      : '<p>No unimplemented feedback for this project.</p>';
    const overlay = document.getElementById('modalOverlay');
    overlay.classList.remove('hidden');
    const submitBtn = document.getElementById('modalSubmit');
    const cancelBtn = document.getElementById('modalCancel');
    const cleanup = () => { overlay.classList.add('hidden'); submitBtn.onclick = null; cancelBtn.onclick = null; };
    cancelBtn.onclick = cleanup;
    submitBtn.onclick = async () => {
      const feedbackIds = [...document.querySelectorAll('.regen-feedback-check:checked')].map((c) => Number(c.value));
      if (!feedbackIds.length) { document.getElementById('modalError').textContent = 'Select at least one feedback item.'; return; }
      try {
        const result = await api('/ai-generator/regenerate', { method: 'POST', body: { project_id: projectId, prototype_id: prototypeId, feedback_ids: feedbackIds } });
        cleanup();
        await loadWorkspace();
        window.open(result.demo_url, '_blank');
      } catch (err) {
        document.getElementById('modalError').textContent = err.message;
      }
    };
  };

  async function loadFeedbackImpl() {
    await refreshMine();
    renderTable('#feedbackImplTable tbody', myFeedback, (f) => `
      <tr><td>${f.finding}</td><td>${f.implemented_at ? pill('Implemented') : pill(f.status)}</td>
      <td class="row-actions">${!f.implemented_at ? `<button class="secondary" onclick="openImplementFeedback(${f.id})">Implement</button>` : ''}</td></tr>`);
  }

  async function loadPrototypesSection() {
    prototypes = await api(`/projects/${projectId}/prototypes`);
    renderTable('#prototypesTable tbody', prototypes, (p) => `
      <tr><td>${p.version_number || p.version_label}</td>
      <td>${p.demo_url ? `<a href="${p.demo_url}" target="_blank" rel="noopener">${p.demo_url}</a>` : '-'}</td>
      <td>${p.notes || '-'}</td></tr>`);
  }

  async function loadDefectFixing() {
    await refreshMine();
    renderTable('#defectsTable tbody', myDefects, (d) => `
      <tr><td>${d.title || d.description}</td><td>${pill(d.severity)}</td><td>${pill(d.status)}</td>
      <td class="row-actions">${d.status === 'Open' ? `<button class="secondary" onclick="markDefectFixed(${d.id})">Tandai Fixed</button>` : ''}</td></tr>`);
  }
  window.markDefectFixed = async function (id) {
    await api(`/defects/${id}`, { method: 'PUT', body: { assigned_to: user.id, status: 'Fixed' } });
    await loadDefectFixing();
  };

  async function loadNotes() {
    prototypes = await api(`/projects/${projectId}/prototypes`);
    renderTable('#notesTable tbody', prototypes, (p) => `
      <tr><td>${p.version_number || p.version_label}</td><td>${p.notes || '-'}</td>
      <td class="row-actions"><button class="secondary" onclick="editPrototype(${p.id})">Edit</button></td></tr>`);
  }

  const SECTION_LOADERS = {
    dashboard: loadDashboard,
    projects: () => {},
    'sprint-tasks': loadSprintTasks,
    workspace: loadWorkspace,
    'feedback-impl': loadFeedbackImpl,
    prototypes: loadPrototypesSection,
    'defect-fixing': loadDefectFixing,
    notes: loadNotes,
  };

  function navigateToSection(key) {
    setActiveSection(key);
    const loader = SECTION_LOADERS[key];
    if (loader) loader();
  }

  const deepLinkSection = new URLSearchParams(window.location.search).get('section');
  const initialSection = (deepLinkSection && SECTION_LOADERS[deepLinkSection]) ? deepLinkSection : 'dashboard';
  renderSidebar('Developer', initialSection, navigateToSection);

  (async function init() {
    await loadProjects();
    if (projectId) await loadDashboard();
    if (initialSection !== 'dashboard') navigateToSection(initialSection);
  })();
})();
