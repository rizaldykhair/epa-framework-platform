const PRICE_PER_KG = { Regular: 7000, Express: 12000 };
const STATUS_FLOW = ['Received', 'Washing', 'Ironing', 'Ready', 'Picked Up'];

function load(key, fallback) {
  try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; }
}
function save(key, value) {
  localStorage.setItem(key, JSON.stringify(value));
}

let customers = load('sl_customers', null);
let orders = load('sl_orders', null);

if (!customers) {
  customers = [
    { id: 1, name: 'Budi Santoso', phone: '081234567890', address: 'Jl. Sisingamangaraja No. 12' },
    { id: 2, name: 'Sri Wahyuni', phone: '081298765432', address: 'Jl. Gatot Subroto No. 45' },
  ];
  save('sl_customers', customers);
}
if (!orders) {
  orders = [
    { id: 1, customerId: 1, serviceType: 'Regular', weightKg: 3, status: 'Ready', paymentStatus: 'Paid', price: 3 * PRICE_PER_KG.Regular, createdAt: new Date().toISOString() },
    { id: 2, customerId: 2, serviceType: 'Express', weightKg: 2, status: 'Washing', paymentStatus: 'Unpaid', price: 2 * PRICE_PER_KG.Express, createdAt: new Date().toISOString() },
  ];
  save('sl_orders', orders);
}

function nextId(list) {
  return list.length ? Math.max(...list.map((x) => x.id)) + 1 : 1;
}
function formatRupiah(n) {
  return 'Rp ' + Number(n).toLocaleString('id-ID');
}
function customerName(id) {
  const c = customers.find((x) => x.id === id);
  return c ? c.name : '-';
}
function waLink(phone, message) {
  const digits = phone.replace(/\D/g, '');
  const normalized = digits.startsWith('0') ? '62' + digits.slice(1) : digits;
  return `https://wa.me/${normalized}?text=${encodeURIComponent(message)}`;
}

function renderDashboard() {
  document.getElementById('m-customers').textContent = customers.length;
  document.getElementById('m-orders').textContent = orders.length;
  document.getElementById('m-inprocess').textContent = orders.filter((o) => ['Received', 'Washing', 'Ironing'].includes(o.status)).length;
  document.getElementById('m-ready').textContent = orders.filter((o) => o.status === 'Ready').length;
  const revenue = orders.filter((o) => o.paymentStatus === 'Paid').reduce((sum, o) => sum + o.price, 0);
  document.getElementById('m-revenue').textContent = formatRupiah(revenue);
}

function renderCustomers() {
  document.getElementById('customerTable').innerHTML = customers.map((c) => `
    <tr><td>${c.name}</td><td>${c.phone}</td><td>${c.address || '-'}</td>
    <td><button class="secondary" onclick="deleteCustomer(${c.id})">Hapus</button></td></tr>`).join('');
  const select = document.getElementById('orderCustomer');
  select.innerHTML = customers.map((c) => `<option value="${c.id}">${c.name}</option>`).join('');
}

function renderOrders() {
  document.getElementById('orderTable').innerHTML = orders.map((o) => {
    const c = customers.find((x) => x.id === o.customerId);
    const waBtn = c ? `<button class="wa" onclick="notifyPickup(${o.id})">WhatsApp</button>` : '';
    return `
    <tr>
      <td>${customerName(o.customerId)}</td>
      <td>${o.serviceType}</td>
      <td>${o.weightKg} kg</td>
      <td>${formatRupiah(o.price)}</td>
      <td><span class="pill">${o.status}</span></td>
      <td><span class="pill">${o.paymentStatus}</span></td>
      <td class="row-actions">
        <select onchange="updateStatus(${o.id}, this.value)">
          ${STATUS_FLOW.map((s) => `<option value="${s}" ${s === o.status ? 'selected' : ''}>${s}</option>`).join('')}
        </select>
        <button class="secondary" onclick="togglePayment(${o.id})">${o.paymentStatus === 'Paid' ? 'Tandai Unpaid' : 'Tandai Paid'}</button>
        ${waBtn}
      </td>
    </tr>`;
  }).join('');
}

function renderAll() {
  renderDashboard();
  renderCustomers();
  renderOrders();
}

document.getElementById('customerForm').addEventListener('submit', (e) => {
  e.preventDefault();
  customers.push({
    id: nextId(customers),
    name: document.getElementById('custName').value.trim(),
    phone: document.getElementById('custPhone').value.trim(),
    address: document.getElementById('custAddress').value.trim(),
  });
  save('sl_customers', customers);
  e.target.reset();
  renderAll();
});

window.deleteCustomer = function (id) {
  if (!confirm('Hapus pelanggan ini?')) return;
  customers = customers.filter((c) => c.id !== id);
  save('sl_customers', customers);
  renderAll();
};

function currentPrice() {
  const service = document.getElementById('orderService').value;
  const weight = Number(document.getElementById('orderWeight').value) || 0;
  return weight * PRICE_PER_KG[service];
}
document.getElementById('orderService').addEventListener('change', updatePricePreview);
document.getElementById('orderWeight').addEventListener('input', updatePricePreview);
function updatePricePreview() {
  document.getElementById('pricePreview').textContent = 'Estimasi: ' + formatRupiah(currentPrice());
}

document.getElementById('orderForm').addEventListener('submit', (e) => {
  e.preventDefault();
  const customerId = Number(document.getElementById('orderCustomer').value);
  const serviceType = document.getElementById('orderService').value;
  const weightKg = Number(document.getElementById('orderWeight').value);
  if (!customerId || !weightKg) return;
  orders.push({
    id: nextId(orders),
    customerId,
    serviceType,
    weightKg,
    status: 'Received',
    paymentStatus: 'Unpaid',
    price: weightKg * PRICE_PER_KG[serviceType],
    createdAt: new Date().toISOString(),
  });
  save('sl_orders', orders);
  e.target.reset();
  updatePricePreview();
  renderAll();
});

window.updateStatus = function (id, status) {
  const order = orders.find((o) => o.id === id);
  order.status = status;
  save('sl_orders', orders);
  renderAll();
};

window.togglePayment = function (id) {
  const order = orders.find((o) => o.id === id);
  order.paymentStatus = order.paymentStatus === 'Paid' ? 'Unpaid' : 'Paid';
  save('sl_orders', orders);
  renderAll();
};

window.notifyPickup = function (id) {
  const order = orders.find((o) => o.id === id);
  const c = customers.find((x) => x.id === order.customerId);
  window.open(waLink(c.phone, 'Halo, laundry Anda sudah siap diambil.'), '_blank');
};

renderAll();
updatePricePreview();
