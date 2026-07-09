(function () {
  const user = guardDashboard('Tester');
  if (!user) return;

  document.getElementById('userInfo').textContent = `Welcome · ${user.role}`;
  document.getElementById('logoutBtn').addEventListener('click', logout);

  let projects = [];
  let projectId = null;
  let tests = [];
  let prototypes = [];
  let defects = [];
  let members = [];

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

  async function loadDashboard() {
    [tests, prototypes, defects, members] = await Promise.all([
      api(`/projects/${projectId}/tests`),
      api(`/projects/${projectId}/prototypes`),
      api(`/projects/${projectId}/defects`),
      api(`/projects/${projectId}/members`),
    ]);
    document.getElementById('m-waitingPrototypes').textContent = prototypes.length;
    document.getElementById('m-totalTests').textContent = tests.length;
    document.getElementById('m-passed').textContent = tests.filter((t) => t.result === 'Pass').length;
    document.getElementById('m-failed').textContent = tests.filter((t) => t.result === 'Fail').length;
    document.getElementById('m-notRun').textContent = tests.filter((t) => t.result === 'Not Run').length;
    document.getElementById('m-retest').textContent = tests.filter((t) => t.result === 'Retest').length;
    document.getElementById('m-openDefects').textContent = defects.filter((d) => d.status === 'Open').length;
    document.getElementById('m-criticalDefects').textContent = defects.filter((d) => d.severity === 'Critical').length;
    const done = tests.filter((t) => t.result !== 'Not Run').length;
    document.getElementById('testingCompletion').textContent = tests.length ? Math.round(done / tests.length * 100) + '%' : '0%';
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

  function developerOptions() {
    return members.filter((m) => m.role_in_project === 'Developer').map((m) => ({ value: m.user_id, label: m.name }));
  }

  function testFields() {
    return [
      { name: 'scenario', label: 'Test Scenario' },
      { name: 'type', label: 'Test Type', type: 'select', options: ['Functional', 'Usability', 'Security', 'Performance', 'Regression', 'UAT'] },
      { name: 'notes', label: 'Expected Result / Actual Result', type: 'textarea' },
      { name: 'result', label: 'Test Status', type: 'select', options: ['Not Run', 'Pass', 'Fail', 'Retest'] },
      { name: 'severity', label: 'Severity', type: 'select', options: ['Low', 'Medium', 'High', 'Critical'] },
      { name: 'evidence_url', label: 'Evidence URL / Screenshot Link' },
    ];
  }

  async function loadWorkspace() {
    [tests, prototypes, members] = await Promise.all([
      api(`/projects/${projectId}/tests`),
      api(`/projects/${projectId}/prototypes`),
      api(`/projects/${projectId}/members`),
    ]);
    const protoSelect = document.getElementById('workspacePrototypeSelect');
    protoSelect.innerHTML = '<option value="">All Prototypes</option>' + prototypes.map((p) => `<option value="${p.id}">${p.version_number || p.version_label}</option>`).join('');
    protoSelect.onchange = () => { renderPrototypeInfo(); renderWorkspaceTable(); };
    renderPrototypeInfo();
    renderWorkspaceTable();
  }
  function renderPrototypeInfo() {
    const protoId = document.getElementById('workspacePrototypeSelect').value;
    const p = prototypes.find((x) => String(x.id) === protoId) || prototypes[0];
    const box = document.getElementById('prototypeInfo');
    if (!p) { box.innerHTML = '<p>No prototype available for testing yet.</p>'; return; }
    box.innerHTML = `
      <p><b>Prototype Version:</b> ${p.version_number || p.version_label} ${pill(p.status)}</p>
      ${demoUrlWarning(p.demo_url)}
      <p><b>Demo URL:</b> ${p.demo_url ? `<a href="${p.demo_url}" target="_blank" rel="noopener">${p.demo_url}</a>` : 'Prototype output not available yet.'}</p>
      ${p.demo_url ? `<button type="button" class="secondary" onclick="window.open('${p.demo_url}','_blank')">Test Prototype</button>` : ''}
    `;
  }
  function renderWorkspaceTable() {
    const protoId = document.getElementById('workspacePrototypeSelect').value;
    const rows = protoId ? tests.filter((t) => String(t.prototype_id) === protoId) : tests;
    renderTable('#workspaceTable tbody', rows, (t) => `
      <tr><td>${t.code}</td><td>${t.scenario}</td><td>${t.type}</td><td>${pill(t.result)}</td><td>${t.severity ? pill(t.severity) : '-'}</td><td>${t.notes || '-'}</td>
      <td class="row-actions">
        <button class="secondary" onclick="editTest(${t.id})">Update</button>
        ${t.result === 'Fail' ? `<button class="danger" onclick="openCreateDefect(${t.id})">Buat Defect</button>` : ''}
      </td></tr>`);
  }
  window.openCreateDefect = function (testId) {
    const t = tests.find((x) => x.id === testId);
    openModal({
      title: 'Defect Report Form',
      fields: [
        { name: 'title', label: 'Defect Title' },
        { name: 'description', label: 'Defect Description', type: 'textarea' },
        { name: 'steps_to_reproduce', label: 'Steps to Reproduce', type: 'textarea' },
        { name: 'expected_result', label: 'Expected Result', type: 'textarea' },
        { name: 'actual_result', label: 'Actual Result', type: 'textarea' },
        { name: 'severity', label: 'Severity', type: 'select', options: ['Low', 'Medium', 'High', 'Critical'] },
        { name: 'assigned_to', label: 'Assigned Developer', type: 'select', options: developerOptions() },
      ],
      initial: { title: t.scenario, description: 'Defect from failed test: ' + t.scenario, severity: t.severity || 'Medium' },
      onSubmit: async (v) => {
        await api(`/projects/${projectId}/defects`, { method: 'POST', body: { ...v, test_id: t.id } });
        await loadWorkspace();
      },
    });
  };
  document.getElementById('addTestBtn').addEventListener('click', () => {
    openModal({
      title: 'Test Case Form',
      fields: testFields(),
      initial: { type: 'Functional', result: 'Not Run', severity: 'Low' },
      onSubmit: async (v) => { await api(`/projects/${projectId}/tests`, { method: 'POST', body: v }); await loadWorkspace(); },
    });
  });
  window.editTest = function (id) {
    const t = tests.find((x) => x.id === id);
    openModal({
      title: 'Update Test',
      fields: testFields(),
      initial: t,
      onSubmit: async (v) => { await api(`/tests/${id}`, { method: 'PUT', body: v }); await loadWorkspace(); },
    });
  };

  async function loadTestCases() {
    tests = await api(`/projects/${projectId}/tests`);
    renderTable('#testCasesTable tbody', tests, (t) => `
      <tr><td>${t.code}</td><td>${t.scenario}</td><td>${t.type}</td><td>${pill(t.result)}</td>
      <td class="row-actions"><button class="secondary" onclick="editTest(${t.id})">Edit</button><button class="danger" onclick="deleteTest(${t.id})">Delete</button></td></tr>`);
  }
  window.deleteTest = async function (id) {
    if (!confirm('Delete this test case?')) return;
    await api(`/tests/${id}`, { method: 'DELETE' });
    await loadTestCases();
  };
  document.getElementById('addTestBtn2').addEventListener('click', () => {
    openModal({
      title: 'Test Case Form',
      fields: testFields(),
      initial: { type: 'Functional', result: 'Not Run', severity: 'Low' },
      onSubmit: async (v) => { await api(`/projects/${projectId}/tests`, { method: 'POST', body: v }); await loadTestCases(); },
    });
  });

  async function loadDefects() {
    [defects, members] = await Promise.all([
      api(`/projects/${projectId}/defects`),
      api(`/projects/${projectId}/members`),
    ]);
    renderTable('#defectsTable tbody', defects, (d) => `
      <tr><td>${d.title || d.description}</td><td>${pill(d.severity)}</td><td>${pill(d.status)}</td>
      <td class="row-actions">${d.status === 'Fixed' ? `<button class="secondary" onclick="verifyDefect(${d.id})">Verify</button>` : ''}</td></tr>`);
  }
  window.verifyDefect = async function (id) {
    await api(`/defects/${id}/verify`, { method: 'POST' });
    await loadDefects();
  };

  async function loadRegression() {
    tests = await api(`/projects/${projectId}/tests`);
    const regressionTests = tests.filter((t) => t.type === 'Regression');
    renderTable('#regressionTable tbody', regressionTests, (t) => `
      <tr><td>${t.code}</td><td>${t.scenario}</td><td>${pill(t.result)}</td>
      <td class="row-actions"><button class="secondary" onclick="editTest(${t.id})">Update</button></td></tr>`);
  }
  document.getElementById('addRegressionBtn').addEventListener('click', () => {
    openModal({
      title: 'Add Regression Test',
      fields: [{ name: 'scenario', label: 'Scenario' }, { name: 'result', label: 'Status', type: 'select', options: ['Not Run', 'Pass', 'Fail', 'Retest'] }],
      initial: { result: 'Not Run' },
      onSubmit: async (v) => { await api(`/projects/${projectId}/tests`, { method: 'POST', body: { ...v, type: 'Regression' } }); await loadRegression(); },
    });
  });

  async function loadReport() {
    tests = await api(`/projects/${projectId}/tests`);
    document.getElementById('rep-total').textContent = tests.length;
    document.getElementById('rep-pass').textContent = tests.filter((t) => t.result === 'Pass').length;
    document.getElementById('rep-fail').textContent = tests.filter((t) => t.result === 'Fail').length;
    const done = tests.filter((t) => t.result !== 'Not Run').length;
    document.getElementById('rep-completion').textContent = tests.length ? Math.round(done / tests.length * 100) + '%' : '0%';
  }

  const SECTION_LOADERS = {
    dashboard: loadDashboard,
    projects: () => {},
    workspace: loadWorkspace,
    'test-cases': loadTestCases,
    defects: loadDefects,
    regression: loadRegression,
    report: loadReport,
  };

  function navigateToSection(key) {
    setActiveSection(key);
    const loader = SECTION_LOADERS[key];
    if (loader) loader();
  }

  const deepLinkSection = new URLSearchParams(window.location.search).get('section');
  const initialSection = (deepLinkSection && SECTION_LOADERS[deepLinkSection]) ? deepLinkSection : 'dashboard';
  renderSidebar('Tester', initialSection, navigateToSection);

  (async function init() {
    await loadProjects();
    if (projectId) await loadDashboard();
    if (initialSection !== 'dashboard') navigateToSection(initialSection);
  })();
})();
