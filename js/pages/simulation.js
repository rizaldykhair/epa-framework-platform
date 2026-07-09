(function () {
  const user = getUser();
  if (!getToken() || !user) { window.location.href = 'index.html'; return; }
  document.getElementById('userInfo').textContent = `${user.name} · ${user.role}`;
  document.getElementById('backBtn').addEventListener('click', () => redirectToDashboard(user.role));

  (async function init() {
    const projects = await api('/projects');
    const project = projects.find((p) => p.project_code === 'SL-01') || projects.find((p) => p.name === 'Smart Laundry Mobile App');
    if (!project) {
      document.getElementById('projectIdentity').textContent = 'Smart Laundry Mobile App is not available for this account. Run backend/database/seed_simulation.php and make sure you are registered as a project member.';
      return;
    }

    const [phases, backlog, feedback, tests, defects, sprints, prototypes, members] = await Promise.all([
      api(`/projects/${project.id}/phases`),
      api(`/projects/${project.id}/backlog`),
      api(`/projects/${project.id}/feedback`),
      api(`/projects/${project.id}/tests`).catch(() => []),
      api(`/projects/${project.id}/defects`).catch(() => []),
      api(`/projects/${project.id}/sprints`).catch(() => []),
      api(`/projects/${project.id}/prototypes`),
      api(`/projects/${project.id}/members`),
    ]);

    document.getElementById('projectIdentity').textContent =
      `${project.client_name || ''} · ${project.project_type || ''} · ${project.description || ''}`;
    document.getElementById('projectStatus').textContent = project.status;
    const currentPhase = phases.find((p) => p.status === 'In Progress') || phases[phases.length - 1];
    document.getElementById('currentPhase').textContent = currentPhase ? currentPhase.name : project.current_epa_phase || '-';

    const initialBacklog = backlog.filter((b) => !b.source_feedback_id);
    renderTable('#phase1Table tbody', initialBacklog, (b) => `
      <tr><td>${b.code}</td><td>${b.title}</td><td>${b.user_story || '-'}</td><td>${pill(b.priority)}</td><td>${pill(b.status)}</td></tr>`);

    document.getElementById('sprintName').textContent = sprints.length ? sprints[0].name : '-';
    const sprintBacklogCodes = ['BL-001', 'BL-002', 'BL-003'];
    renderTable('#sprintItemsTable tbody', backlog.filter((b) => sprintBacklogCodes.includes(b.code)), (b) => `<tr><td>${b.code} - ${b.title}</td><td>${pill(b.status)}</td></tr>`);

    const proto = prototypes.find((p) => p.version_number === 'v0.1') || prototypes[0];
    const protoCard = document.getElementById('prototypeCard');
    protoCard.innerHTML = proto ? `
      <div class="panel">
        <h4>${proto.prototype_name || 'Prototype'} — ${proto.version_number} ${pill(proto.status)}</h4>
        <p><b>Demo URL:</b> ${proto.demo_url ? `<a href="${proto.demo_url}" target="_blank" rel="noopener">${proto.demo_url}</a>` : '-'}</p>
        <p><b>Repository URL:</b> ${proto.repository_url ? `<a href="${proto.repository_url}" target="_blank" rel="noopener">${proto.repository_url}</a>` : '-'}</p>
        <p><b>Build Output URL:</b> ${proto.build_output_url ? `<a href="${proto.build_output_url}" target="_blank" rel="noopener">${proto.build_output_url}</a>` : '-'}</p>
      </div>` : '<p>No prototype increment yet.</p>';

    renderTable('#feedbackFlowTable tbody', feedback, (f) => {
      const created = backlog.find((b) => b.source_feedback_id === f.id);
      return `<tr><td>${f.finding}</td><td>${pill(f.decision)}</td><td>${f.reason || '-'}</td><td>${created ? `${created.code} - ${created.title}` : '-'}</td></tr>`;
    });

    renderTable('#testResultTable tbody', tests, (t) => `<tr><td>${t.code}</td><td>${t.scenario}</td><td>${pill(t.result)}</td></tr>`);
    renderTable('#defectListTable tbody', defects, (d) => `<tr><td>${d.title || d.description}</td><td>${pill(d.severity)}</td><td>${pill(d.status)}</td></tr>`);
    renderTable('#updatedBacklogTable tbody', backlog.filter((b) => b.source_feedback_id), (b) => `<tr><td>${b.code}</td><td>${b.title}</td><td>${pill(b.priority)}</td><td>${pill(b.status)}</td></tr>`);

    const byRole = {};
    members.forEach((m) => { byRole[m.role_in_project] = m.user_id; });
    const roleActivity = [
      ['Admin', 'Created project, assigned members, approved milestones'],
      ['Product Owner', `Defined ${initialBacklog.length} requirements, reviewed ${feedback.length} feedback items, converted ${feedback.filter((f) => f.decision === 'Converted').length} to backlog`],
      ['Developer', `Built prototype ${proto ? proto.version_number : '-'}, ${backlog.filter((b) => b.assigned_to === byRole['Developer']).length} backlog items assigned`],
      ['Tester', `Executed ${tests.length} test cases, reported ${defects.length} defects`],
      ['Evaluator', `Submitted ${feedback.filter((f) => f.submitted_by === byRole['Evaluator']).length} feedback items on prototype ${proto ? proto.version_number : '-'}`],
    ];
    renderTable('#roleActivityTable tbody', roleActivity, ([role, activity]) => `<tr><td>${pill(role)}</td><td>${activity}</td></tr>`);

    document.getElementById('m-backlog').textContent = backlog.length;
    document.getElementById('m-converted').textContent = feedback.filter((f) => f.decision === 'Converted').length;
    document.getElementById('m-failed').textContent = tests.filter((t) => t.result === 'Fail').length;
    document.getElementById('m-defects').textContent = defects.filter((d) => d.status === 'Open').length;
  })();
})();
