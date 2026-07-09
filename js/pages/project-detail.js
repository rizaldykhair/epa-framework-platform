(function () {
  const user = getUser();
  if (!getToken() || !user) { window.location.href = 'index.html'; return; }
  document.getElementById('userInfo').textContent = `${user.name} · ${user.role}`;
  document.getElementById('backBtn').addEventListener('click', () => redirectToDashboard(user.role));

  const params = new URLSearchParams(window.location.search);
  const projectId = Number(params.get('id'));
  if (!projectId) {
    alert('Project ID tidak ditemukan di URL.');
    redirectToDashboard(user.role);
    return;
  }

  let project = null;
  let workflow = [];
  let status = null;
  let prototypes = [];

  async function load() {
    [project, workflow, status, prototypes] = await Promise.all([
      api(`/projects/${projectId}`),
      api('/epa/workflow'),
      api(`/projects/${projectId}/epa/status`),
      api(`/projects/${projectId}/prototypes`),
    ]);
    render();
  }

  function currentStepDef() {
    return workflow.flatMap((p) => p.steps).find((s) => s.step_code === status.current_step);
  }

  function render() {
    document.getElementById('projectName').textContent = project.name;
    document.getElementById('projectMeta').textContent = `${project.project_code || 'No code'} · ${project.project_type || '-'} · ${pill(project.status)}`;
    document.getElementById('completionPct').textContent = status.completion_percentage + '%';
    document.getElementById('epaStatusPill').innerHTML = pill(status.current_phase);
    document.getElementById('currentStepLabel').textContent = `${status.current_step_name} (${status.current_phase})`;
    document.getElementById('missingArtifacts').textContent = status.missing_artifacts.length ? 'Missing: ' + status.missing_artifacts.join(', ') : 'No missing artifacts for the current step.';
    document.getElementById('nextAction').textContent = status.next_required_action;

    const stepOrderMap = {};
    workflow.forEach((phase) => phase.steps.forEach((s) => { stepOrderMap[s.step_code] = s.step_order; }));
    const currentOrder = stepOrderMap[status.current_step];

    document.getElementById('phaseCards').innerHTML = workflow.map((phase) => `
      <div class="panel">
        <div class="section-head"><h3>${phase.phase_name}</h3></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>#</th><th>Step</th><th>Status</th><th>Responsible Role</th><th>Required Artifacts</th></tr></thead>
            <tbody>
              ${phase.steps.map((s) => {
                let stepStatus;
                if (s.step_order < currentOrder) stepStatus = 'Completed';
                else if (s.step_order > currentOrder) stepStatus = 'Locked';
                else stepStatus = status.missing_artifacts.length ? 'Blocked' : 'Available';
                return `<tr>
                  <td>${s.step_order}</td><td>${s.step_name}</td><td>${pill(stepStatus)}</td>
                  <td>${s.responsible_roles}</td><td class="small">${s.required_artifacts}</td>
                </tr>`;
              }).join('')}
            </tbody>
          </table>
        </div>
      </div>`).join('');

    renderActionButtons();
  }

  function renderActionButtons() {
    const step = currentStepDef();
    const allowedRoles = (step.responsible_roles || '').split(',').map((r) => r.trim());
    const canAct = user.role === 'Admin' || allowedRoles.includes(user.role);
    const hasNext = !!step.next_step_code;

    const moveBtn = document.getElementById('moveNextBtn');
    if (canAct && hasNext) {
      moveBtn.classList.remove('hidden');
      moveBtn.disabled = !status.can_move_next;
    } else {
      moveBtn.classList.add('hidden');
    }

    const latestPrototype = prototypes[0];
    let html = '';
    if (latestPrototype && latestPrototype.demo_url) {
      html += `<button type="button" class="secondary" onclick="window.open('${latestPrototype.demo_url}','_blank')">Open Prototype Demo</button>`;
    }
    if (['Admin', 'Product Owner'].includes(user.role)) {
      html += `<button type="button" class="secondary" onclick="continueWithAi()">Continue with AI Generator</button>`;
    }
    if (['Admin', 'Product Owner', 'Developer'].includes(user.role)) {
      html += `<button type="button" class="secondary" onclick="generateMissingPrototype()">Generate Missing Prototype</button>`;
    }
    document.getElementById('epaActionButtons').innerHTML = html;
  }

  document.getElementById('syncBtn').addEventListener('click', async () => {
    status = await api(`/projects/${projectId}/epa/sync`, { method: 'POST' });
    render();
  });
  document.getElementById('moveNextBtn').addEventListener('click', async () => {
    try {
      const result = await api(`/projects/${projectId}/epa/move-next`, { method: 'POST' });
      status = result.status;
      alert(`Moved to next EPA step: ${result.next_step || 'all steps complete'}.`);
      render();
    } catch (err) {
      alert(err.message);
    }
  });

  window.continueWithAi = async function () {
    let inspect;
    try {
      inspect = await api(`/projects/${projectId}/ai-continuation/inspect`);
    } catch (err) {
      alert(err.message);
      return;
    }
    const missingLabel = inspect.missing_items.length ? inspect.missing_items.join(', ') : 'none (already complete)';
    openModal({
      title: `Continue "${project.name}" with AI Generator — ${inspect.completion_percentage}% complete, missing: ${missingLabel}`,
      fields: [
        { name: 'business_problem', label: 'Business Problem', type: 'textarea' },
        { name: 'target_users', label: 'Target Users' },
        { name: 'main_objective', label: 'Main Objective', type: 'textarea' },
        { name: 'main_features', label: 'Main Features Needed (satu per baris)', type: 'textarea' },
        { name: 'user_roles_needed', label: 'User Roles Needed' },
        { name: 'data_entities', label: 'Data to be Managed (comma-separated)' },
        { name: 'workflow_description', label: 'Workflow Description', type: 'textarea' },
        { name: 'reporting_needs', label: 'Reporting Needs', type: 'textarea' },
        { name: 'design_style', label: 'Design Style', type: 'select', options: ['Simple', 'Modern', 'Corporate', 'Marketplace', 'Dashboard'] },
        { name: 'primary_color', label: 'Primary Color', type: 'color' },
        { name: 'layout_preference', label: 'Layout Preference', type: 'select', options: ['Dashboard', 'CRUD System', 'Marketplace Catalog', 'Booking System', 'Tracking System'] },
      ],
      initial: { design_style: 'Modern', primary_color: '#0b4a3a', layout_preference: 'Dashboard' },
      onSubmit: async (v) => {
        const result = await api(`/projects/${projectId}/ai-continuation/continue`, { method: 'POST', body: v });
        alert(`Generated: ${result.generated_requirements_count} requirement(s), ${result.generated_backlog_count} backlog item(s), ${result.generated_sprint_count} sprint.\nDemo URL: ${result.demo_url || '(prototype already existed)'}`);
        await load();
      },
    });
  };

  window.generateMissingPrototype = async function () {
    openModal({
      title: `Generate Missing Prototype — ${project.name}`,
      fields: [
        { name: 'prototype_name', label: 'Prototype Name' },
        { name: 'main_features', label: 'Main Features (optional, defaults to existing backlog)', type: 'textarea' },
        { name: 'design_style', label: 'Design Style', type: 'select', options: ['Simple', 'Modern', 'Corporate', 'Marketplace', 'Dashboard'] },
        { name: 'primary_color', label: 'Primary Color', type: 'color' },
      ],
      initial: { design_style: 'Modern', primary_color: '#0b4a3a' },
      onSubmit: async (v) => {
        const result = await api(`/projects/${projectId}/ai-continuation/generate-prototype`, { method: 'POST', body: v });
        alert(`Prototype created successfully.\nDemo URL: ${result.demo_url}`);
        window.open(result.demo_url, '_blank');
        await load();
      },
    });
  };

  load();
})();
