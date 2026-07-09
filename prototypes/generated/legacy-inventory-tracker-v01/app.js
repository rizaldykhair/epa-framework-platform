const STATUS_FLOW = ["Registered","In Transit","Arrived","Delivered"];
const SECONDARY_OPTIONS = ['Stock Movement A','Stock Movement B','Stock Movement C'];

function load(key, fallback) { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } }
function save(key, value) { localStorage.setItem(key, JSON.stringify(value)); }

let items = load('gen_items', []);

function todayStr() { return new Date().toISOString().slice(0, 10); }

function renderSecondaryOptions() {
  const sel = document.getElementById('fSecondary');
  if (!sel) return;
  sel.innerHTML = '<option value="">Pilih</option>' + SECONDARY_OPTIONS.map(o => `<option value="${o}">${o}</option>`).join('');
}

function renderDashboard() {
  document.getElementById('m-total').textContent = items.length;
  document.getElementById('m-today').textContent = items.filter(i => i.date === todayStr()).length;
  document.getElementById('m-inprogress').textContent = items.filter(i => i.status !== STATUS_FLOW[STATUS_FLOW.length - 1]).length;
  document.getElementById('m-done').textContent = items.filter(i => i.status === STATUS_FLOW[STATUS_FLOW.length - 1] && i.date === todayStr()).length;
}

function renderTable() {
  document.getElementById('itemTable').innerHTML = items.map((it, idx) => `
    <tr><td>${idx + 1}</td><td>${it.name}</td><td>${it.phone}</td>${SECONDARY_OPTIONS.length ? `<td>${it.secondary || '-'}</td>` : ''}
    <td><span class="pill">${it.status}</span></td>
    <td><select onchange="updateStatus(${idx}, this.value)">${STATUS_FLOW.map(s => `<option value="${s}" ${s === it.status ? 'selected' : ''}>${s}</option>`).join('')}</select></td></tr>`
  ).join('') || '<tr><td colspan="6">Belum ada data.</td></tr>';
}

window.updateStatus = function (idx, status) {
  items[idx].status = status;
  save('gen_items', items);
  renderAll();
};

document.getElementById('registerForm').addEventListener('submit', (e) => {
  e.preventDefault();
  const name = document.getElementById('fName').value.trim();
  const phone = document.getElementById('fPhone').value.trim();
  if (!name || !phone) return;
  items.push({
    name, phone,
    secondary: document.getElementById('fSecondary') ? document.getElementById('fSecondary').value : null,
    note: document.getElementById('fNote').value.trim(),
    status: STATUS_FLOW[0],
    date: todayStr(),
  });
  save('gen_items', items);
  e.target.reset();
  renderAll();
});

function renderAll() { renderDashboard(); renderTable(); }
renderSecondaryOptions();
renderAll();