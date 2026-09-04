(function () {
  const user = guardDashboard('Evaluator');
  if (!user) return;

  document.getElementById('userInfo').innerHTML = renderUserChip(user);
  document.getElementById('logoutBtn').addEventListener('click', logout);
  document.getElementById('viewEpaWorkflowBtn').addEventListener('click', () => {
    if (projectId) window.location.href = `project-detail.html?id=${projectId}`;
  });

  let projects = [];
  let projectId = null;
  let prototypes = [];
  let feedback = [];

  function setProject(id) {
    projectId = id;
    const p = projects.find((x) => x.id === id);
    document.getElementById('projectLabel').textContent = p ? p.name : '-';
    document.getElementById('evalProjectName').textContent = p ? p.name : '-';
    const select = document.getElementById('dashboardProjectSelect');
    if (select) select.value = String(id);
  }

  async function loadProjects() {
    projects = await api('/projects');
    const select = document.getElementById('dashboardProjectSelect');
    select.innerHTML = projects.map((p) => `<option value="${p.id}">${p.name}</option>`).join('');
    select.onchange = () => { setProject(Number(select.value)); loadDashboard(); };
    if (projects.length) setProject(projects[0].id);
  }

  async function loadDashboard() {
    const [phases, protos, fb, myEvals] = await Promise.all([
      api(`/projects/${projectId}/phases`),
      api(`/projects/${projectId}/prototypes`),
      api(`/projects/${projectId}/feedback`),
      api('/my/evaluations'),
    ]);
    prototypes = protos;
    feedback = fb;
    const currentPhase = phases.find((p) => p.status === 'In Progress') || phases[phases.length - 1];
    document.getElementById('currentPhase').textContent = currentPhase ? currentPhase.name : '-';

    const latestProto = prototypes[0];
    document.getElementById('m-version').textContent = latestProto ? (latestProto.version_number || latestProto.version_label) : '-';
    const evaluated = latestProto && myEvals.some((e) => e.prototype_id === latestProto.id);
    document.getElementById('m-evalStatus').textContent = latestProto ? (evaluated ? 'Evaluated' : 'Not Evaluated') : '-';
    document.getElementById('m-pending').textContent = latestProto && !evaluated ? 'Yes' : 'No';
    document.getElementById('m-submittedFeedback').textContent = feedback.length;

    const byDecision = { Clarify: 0, Converted: 0, Rejected: 0 };
    feedback.forEach((f) => { byDecision[f.decision] = (byDecision[f.decision] || 0) + 1; });
    renderTable('#decisionTable tbody', Object.entries(byDecision), ([k, v]) => `<tr><td>${k}</td><td>${v}</td></tr>`);
    await loadEpaOverview();
  }

  // ---------- EPA Phase Overview ----------
  async function loadEpaOverview() {
    const d = await api('/epa/phase-dashboard');
    const phases2 = d.by_phase || {};
    document.getElementById('epa-initial').textContent = phases2['Initial Phase'] || 0;
    document.getElementById('epa-devtest').textContent = phases2['Dev & Testing Phase'] || 0;
    document.getElementById('epa-release').textContent = phases2['Release Phase'] || 0;
    document.getElementById('epa-aligned').textContent = Math.max(0, d.total_projects - d.incomplete_projects.length);
    renderTable('#epaIncompleteTable tbody', d.incomplete_projects, (p) => `
      <tr><td><a href="project-detail.html?id=${p.project_id}">${p.project_name}</a></td><td>${p.current_step || '-'}</td>
      <td class="small">${(p.missing_artifacts || []).join(', ')}</td></tr>`);
    renderTable('#epaReadyTable tbody', d.ready_for_next_step, (p) => `
      <tr><td><a href="project-detail.html?id=${p.project_id}">${p.project_name}</a></td><td>${p.current_step || '-'}</td></tr>`);
  }

  async function loadDemo() {
    prototypes = await api(`/projects/${projectId}/prototypes`);
    renderTable('#demoTable tbody', prototypes, (p) => `
      <tr><td>${p.version_number || p.version_label}</td>
      <td>${p.demo_url ? `<a href="${p.demo_url}" target="_blank" rel="noopener">${p.demo_url}</a>` : 'Prototype output is not available yet.'}${demoUrlWarning(p.demo_url)}</td>
      <td>${p.notes || '-'}</td>
      <td>${p.demo_url ? `<button type="button" class="secondary" onclick="window.open('${p.demo_url}','_blank')">Open Prototype Demo</button>` : ''}</td></tr>`);
  }

  async function loadEvaluationPage() {
    prototypes = await api(`/projects/${projectId}/prototypes`);
    document.getElementById('evalDate').textContent = new Date().toISOString().slice(0, 10);
    const select = document.getElementById('evalPrototypeSelect');
    select.innerHTML = prototypes.map((p) => `<option value="${p.id}">${p.version_number || p.version_label}</option>`).join('');
    const updateDemo = () => {
      const p = prototypes.find((x) => x.id === Number(select.value));
      document.getElementById('evalDemoUrl').innerHTML = p && p.demo_url ? `<a href="${p.demo_url}" target="_blank">${p.demo_url}</a>` : '-';
    };
    select.onchange = updateDemo;
    updateDemo();
    await loadEvalHistory();
  }
  async function loadEvalHistory() {
    const history = await api('/my/evaluations');
    renderTable('#evalHistoryTable tbody', history, (e) => `<tr><td>${e.version_number || e.version_label}</td><td>${e.overall_satisfaction_rating}/5</td><td>${pill(e.feedback_priority)}</td><td>${e.improvement_suggestions || '-'}</td><td>${e.submitted_at || e.created_at}</td></tr>`);
  }
  document.getElementById('submitEvalBtn').addEventListener('click', async () => {
    const errorEl = document.getElementById('evalError');
    errorEl.textContent = '';
    const prototypeId = document.getElementById('evalPrototypeSelect').value;
    try {
      await api(`/prototypes/${prototypeId}/evaluations`, {
        method: 'POST',
        body: {
          ease_of_use_rating: document.getElementById('evalEaseOfUse').value,
          feature_completeness_rating: document.getElementById('evalFeatureCompleteness').value,
          interface_design_rating: document.getElementById('evalInterfaceDesign').value,
          performance_rating: document.getElementById('evalPerformance').value,
          overall_satisfaction_rating: document.getElementById('evalOverall').value,
          what_works_well: document.getElementById('evalWorksWell').value,
          problems_found: document.getElementById('evalProblems').value,
          improvement_suggestions: document.getElementById('evalSuggestion').value,
          feedback_priority: document.getElementById('evalPriority').value,
        },
      });
      await loadEvalHistory();
    } catch (err) {
      errorEl.textContent = err.message;
    }
  });

  document.getElementById('submitFeedbackBtn').addEventListener('click', async () => {
    const errorEl = document.getElementById('fbError');
    errorEl.textContent = '';
    const finding = document.getElementById('fbFinding').value.trim();
    if (!finding) { errorEl.textContent = 'Finding cannot be empty'; return; }
    try {
      await api(`/projects/${projectId}/feedback`, { method: 'POST', body: { finding } });
      document.getElementById('fbFinding').value = '';
    } catch (err) {
      errorEl.textContent = err.message;
    }
  });

  async function loadHistory() {
    feedback = await api(`/projects/${projectId}/feedback`);
    renderTable('#historyTable tbody', feedback, (f) => `
      <tr><td>${f.finding}</td><td>${pill(f.decision)}</td><td>${pill(f.status)}</td>
      <td>${f.implemented_at ? pill('Implemented') : pill('Pending')}</td></tr>`);
  }

  const SECTION_LOADERS = {
    dashboard: loadDashboard,
    demo: loadDemo,
    evaluation: loadEvaluationPage,
    feedback: () => {},
    history: loadHistory,
  };

  function navigateToSection(key) {
    setActiveSection(key);
    const loader = SECTION_LOADERS[key];
    if (loader) loader();
  }

  const deepLinkSection = new URLSearchParams(window.location.search).get('section');
  const initialSection = (deepLinkSection && SECTION_LOADERS[deepLinkSection]) ? deepLinkSection : 'dashboard';
  renderSidebar('Evaluator', initialSection, navigateToSection);

  (async function init() {
    await loadProjects();
    if (projectId) await loadDashboard();
    if (initialSection !== 'dashboard') navigateToSection(initialSection);
  })();
})();
