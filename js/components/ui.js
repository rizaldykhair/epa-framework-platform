function pill(value) {
  return `<span class="pill">${value ?? '-'}</span>`;
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
  return `<article><p>${label}</p><h3>${value}</h3></article>`;
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
