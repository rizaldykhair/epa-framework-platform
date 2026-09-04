(function () {
  const user = getUser();
  if (!getToken() || !user) { window.location.href = 'index.html'; return; }

  document.getElementById('userInfo').innerHTML = renderUserChip(user);
  document.getElementById('backBtn').addEventListener('click', () => redirectToDashboard(user.role));

  const CAN_CREATE = ['Admin', 'Product Owner', 'Evaluator'].includes(user.role);
  const CAN_GENERATE = ['Admin', 'Product Owner'].includes(user.role);

  let currentRequestId = null;

  if (!CAN_CREATE) {
    document.getElementById('requestFormSection').classList.add('hidden');
  } else if (!CAN_GENERATE) {
    // Evaluator/Client: can submit an idea, but preview/generate is PO/Admin-only.
    document.getElementById('submitRequirementBtn').textContent = 'Submit App Requirement';
  }

  function collectForm() {
    return {
      application_name: document.getElementById('f-application_name').value.trim(),
      client_name: document.getElementById('f-client_name').value.trim(),
      project_type: document.getElementById('f-project_type').value,
      target_users: document.getElementById('f-target_users').value.trim(),
      business_problem: document.getElementById('f-business_problem').value.trim(),
      main_objective: document.getElementById('f-main_objective').value.trim(),
      main_features: document.getElementById('f-main_features').value.trim(),
      user_roles_needed: document.getElementById('f-user_roles_needed').value.trim(),
      data_entities: document.getElementById('f-data_entities').value.trim(),
      workflow_description: document.getElementById('f-workflow_description').value.trim(),
      reporting_needs: document.getElementById('f-reporting_needs').value.trim(),
      design_style: document.getElementById('f-design_style').value,
      primary_color: document.getElementById('f-primary_color').value,
      layout_preference: document.getElementById('f-layout_preference').value,
    };
  }

  document.getElementById('requirementForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const errorEl = document.getElementById('formError');
    errorEl.textContent = '';
    try {
      const created = await api('/ai-generator/requests', { method: 'POST', body: collectForm() });
      currentRequestId = created.id;
      if (CAN_GENERATE) {
        await previewPlan(currentRequestId);
      } else {
        alert('Requirement submitted. Awaiting review by Product Owner/Admin.');
        e.target.reset();
      }
      await loadHistory();
    } catch (err) {
      errorEl.textContent = err.message;
    }
  });

  async function previewPlan(requestId) {
    const plan = await api(`/ai-generator/requests/${requestId}/preview-plan`, { method: 'POST' });
    document.getElementById('planSection').classList.remove('hidden');
    renderTable('#planRequirements', plan.requirements, (r) => `<tr><td>${r.title}</td><td>${pill(r.priority)}</td></tr>`);
    renderTable('#planBacklog', plan.backlog, (b) => `<tr><td>${b.code}</td><td>${b.title}</td><td>${pill(b.priority)}</td></tr>`);
    document.getElementById('planSprint').textContent = `${plan.sprint1.name} — ${plan.sprint1.goal}`;
    document.getElementById('planModules').innerHTML = plan.prototype_modules.map((m) => `<li><span>${m}</span></li>`).join('');
    document.getElementById('planTests').innerHTML = plan.testing_checklist.map((t) => `<li><span>${t}</span></li>`).join('');
    document.getElementById('confirmGenerateBtn').onclick = () => confirmGenerate(requestId);
  }

  async function confirmGenerate(requestId) {
    const btn = document.getElementById('confirmGenerateBtn');
    btn.disabled = true;
    btn.textContent = 'Generating...';
    try {
      await api(`/ai-generator/requests/${requestId}/generate-project`, { method: 'POST' });
      const proto = await api(`/ai-generator/requests/${requestId}/generate-prototype`, { method: 'POST' });
      document.getElementById('planSection').classList.add('hidden');
      document.getElementById('resultSection').classList.remove('hidden');
      document.getElementById('resultSummary').textContent = 'Project, backlog, sprint, and prototype increment created successfully.';
      document.getElementById('resultDemoUrl').innerHTML = `<a href="${proto.demo_url}" target="_blank" rel="noopener">${proto.demo_url}</a>`;
      document.getElementById('openGeneratedBtn').onclick = () => window.open(proto.demo_url, '_blank');
      await loadHistory();
    } catch (err) {
      alert(err.message);
    } finally {
      btn.disabled = false;
      btn.textContent = 'Confirm & Generate Project';
    }
  }

  async function loadHistory() {
    const rows = await api('/ai-generator/requests');
    renderTable('#historyTable', rows, (r) => `
      <tr><td>${r.application_name}</td><td>${pill(r.generation_status)}</td>
      <td>${r.generated_demo_url ? `<a href="${r.generated_demo_url}" target="_blank" rel="noopener">${r.generated_demo_url}</a>` : '-'}</td>
      <td>${r.created_at}</td>
      <td class="row-actions">
        ${r.generated_demo_url ? `<button type="button" class="secondary" onclick="window.open('${r.generated_demo_url}','_blank')">Open Prototype Demo</button>` : ''}
        ${CAN_GENERATE && r.generation_status === 'Draft' ? `<button type="button" class="secondary" onclick="resumeRequest(${r.id})">Preview Plan</button>` : ''}
      </td></tr>`);
  }
  window.resumeRequest = async function (id) {
    currentRequestId = id;
    await previewPlan(id);
    document.getElementById('planSection').scrollIntoView({ behavior: 'smooth' });
  };

  loadHistory();
})();
