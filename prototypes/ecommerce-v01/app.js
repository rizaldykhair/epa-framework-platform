const PRODUCTS = [
  { id: 1, name: 'Kaos Polos Premium', category: 'Fashion', price: 85000, stock: 24, description: 'Kaos katun combed 30s, nyaman dipakai harian.', color: '#c2540a' },
  { id: 2, name: 'Sepatu Sneakers Urban', category: 'Fashion', price: 320000, stock: 12, description: 'Sneakers ringan dengan sol anti-slip.', color: '#8f3d05' },
  { id: 3, name: 'Tas Ransel Laptop', category: 'Aksesoris', price: 210000, stock: 8, description: 'Ransel tahan air, muat laptop 14 inci.', color: '#2b7a5b' },
  { id: 4, name: 'Jam Tangan Analog', category: 'Aksesoris', price: 450000, stock: 5, description: 'Jam tangan casual dengan tali kulit.', color: '#c2540a' },
  { id: 5, name: 'Headset Bluetooth', category: 'Elektronik', price: 275000, stock: 15, description: 'Headset wireless dengan noise cancelling.', color: '#8f3d05' },
  { id: 6, name: 'Botol Minum 1L', category: 'Rumah Tangga', price: 65000, stock: 30, description: 'Botol minum stainless steel, menjaga suhu 12 jam.', color: '#2b7a5b' },
];
const STATUS_FLOW = ['Created', 'Packed', 'Shipped', 'Completed'];

function load(key, fallback) {
  try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; }
}
function save(key, value) {
  localStorage.setItem(key, JSON.stringify(value));
}
function formatRupiah(n) {
  return 'Rp ' + Number(n).toLocaleString('id-ID');
}
function findProduct(id) {
  return PRODUCTS.find((p) => p.id === id);
}

let cart = load('ecom_cart', []);
let orders = load('ecom_orders', []);

function cartSubtotal() {
  return cart.reduce((sum, item) => sum + findProduct(item.productId).price * item.qty, 0);
}
function cartItemCount() {
  return cart.reduce((sum, item) => sum + item.qty, 0);
}

function renderDashboard() {
  document.getElementById('m-products').textContent = PRODUCTS.length;
  document.getElementById('m-cartItems').textContent = cartItemCount();
  document.getElementById('m-cartTotal').textContent = formatRupiah(cartSubtotal());
  document.getElementById('m-orders').textContent = orders.length;
  document.getElementById('cartCount').textContent = cartItemCount();
}

function renderCatalog() {
  document.getElementById('catalog').innerHTML = PRODUCTS.map((p) => `
    <div class="product-card">
      <div class="product-thumb" style="background:${p.color}">${p.name.charAt(0)}</div>
      <h4 onclick="openDetail(${p.id})">${p.name}</h4>
      <span class="pill">${p.category}</span>
      <span class="price">${formatRupiah(p.price)}</span>
      <span class="stock">Stok: ${p.stock}</span>
      <button class="add-cart" onclick="addToCart(${p.id})">+ Keranjang</button>
    </div>`).join('');
}

window.openDetail = function (id) {
  const p = findProduct(id);
  document.getElementById('detailBody').innerHTML = `
    <div class="product-thumb" style="background:${p.color};height:120px;font-size:44px">${p.name.charAt(0)}</div>
    <h3>${p.name}</h3>
    <span class="pill">${p.category}</span>
    <p>${p.description}</p>
    <p><b>${formatRupiah(p.price)}</b> · Stok: ${p.stock}</p>
    <button class="add-cart" onclick="addToCart(${p.id}); closeDetail();">+ Keranjang</button>`;
  document.getElementById('detailOverlay').classList.remove('hidden');
};
window.closeDetail = function () {
  document.getElementById('detailOverlay').classList.add('hidden');
};
document.getElementById('closeDetailBtn').addEventListener('click', closeDetail);

window.addToCart = function (id) {
  const item = cart.find((c) => c.productId === id);
  if (item) item.qty += 1;
  else cart.push({ productId: id, qty: 1 });
  save('ecom_cart', cart);
  renderAll();
};
window.changeQty = function (id, delta) {
  const item = cart.find((c) => c.productId === id);
  if (!item) return;
  item.qty += delta;
  if (item.qty <= 0) cart = cart.filter((c) => c.productId !== id);
  save('ecom_cart', cart);
  renderAll();
};
window.removeFromCart = function (id) {
  cart = cart.filter((c) => c.productId !== id);
  save('ecom_cart', cart);
  renderAll();
};
document.getElementById('clearCartBtn').addEventListener('click', () => {
  cart = [];
  save('ecom_cart', cart);
  renderAll();
});
document.getElementById('openCartBtn').addEventListener('click', () => {
  document.getElementById('cartSection').scrollIntoView({ behavior: 'smooth' });
});
document.getElementById('goCheckoutBtn').addEventListener('click', () => {
  document.getElementById('checkoutSection').scrollIntoView({ behavior: 'smooth' });
});

function renderCart() {
  document.getElementById('cartTable').innerHTML = cart.map((item) => {
    const p = findProduct(item.productId);
    return `
    <tr><td>${p.name}</td><td>${formatRupiah(p.price)}</td>
    <td class="qty-control"><button onclick="changeQty(${p.id},-1)">-</button>${item.qty}<button onclick="changeQty(${p.id},1)">+</button></td>
    <td>${formatRupiah(p.price * item.qty)}</td>
    <td><button class="secondary" onclick="removeFromCart(${p.id})">Hapus</button></td></tr>`;
  }).join('') || '<tr><td colspan="5">Keranjang masih kosong.</td></tr>';
  document.getElementById('cartSubtotal').textContent = formatRupiah(cartSubtotal());
}

function renderOrders() {
  document.getElementById('orderTable').innerHTML = orders.map((o) => `
    <tr><td>#${o.id} (${o.items.length} item)</td><td>${formatRupiah(o.total)}</td><td>${o.paymentMethod}</td>
    <td><span class="pill">${o.status}</span></td>
    <td>${o.status !== 'Completed' ? `<button class="secondary" onclick="advanceStatus(${o.id})">Update Status</button>` : '-'}</td></tr>`).join('')
    || '<tr><td colspan="5">Belum ada order.</td></tr>';
}
window.advanceStatus = function (id) {
  const order = orders.find((o) => o.id === id);
  const idx = STATUS_FLOW.indexOf(order.status);
  order.status = STATUS_FLOW[Math.min(idx + 1, STATUS_FLOW.length - 1)];
  save('ecom_orders', orders);
  renderAll();
};

document.getElementById('checkoutForm').addEventListener('submit', (e) => {
  e.preventDefault();
  const errorEl = document.getElementById('checkoutError');
  errorEl.textContent = '';
  if (!cart.length) { errorEl.textContent = 'Keranjang masih kosong.'; return; }
  const items = cart.map((c) => ({ productId: c.productId, name: findProduct(c.productId).name, price: findProduct(c.productId).price, qty: c.qty }));
  const order = {
    id: orders.length ? Math.max(...orders.map((o) => o.id)) + 1 : 1,
    items,
    customer: {
      name: document.getElementById('custName').value.trim(),
      phone: document.getElementById('custPhone').value.trim(),
      address: document.getElementById('custAddress').value.trim(),
    },
    paymentMethod: document.getElementById('paymentMethod').value,
    note: document.getElementById('orderNote').value.trim(),
    total: cartSubtotal(),
    status: 'Created',
    createdAt: new Date().toISOString(),
  };
  orders.push(order);
  save('ecom_orders', orders);
  cart = [];
  save('ecom_cart', cart);
  document.getElementById('orderSummary').innerHTML = `
    <div class="panel">
      <h4>Pesanan #${order.id} dibuat</h4>
      <p>${items.map((i) => `${i.name} x${i.qty}`).join(', ')}</p>
      <p><b>Total: ${formatRupiah(order.total)}</b> · ${order.paymentMethod}</p>
    </div>`;
  e.target.reset();
  renderAll();
});

function renderAll() {
  renderDashboard();
  renderCatalog();
  renderCart();
  renderOrders();
}
renderAll();
