const ENTITY_NAME = "Item";
const PRODUCTS = [
  { id: 1, name: ENTITY_NAME + ' A', price: 50000, color: '#c2540a' },
  { id: 2, name: ENTITY_NAME + ' B', price: 120000, color: '#8f3d05' },
  { id: 3, name: ENTITY_NAME + ' C', price: 75000, color: '#2b7a5b' },
  { id: 4, name: ENTITY_NAME + ' D', price: 200000, color: '#c2540a' },
];

function load(key, fallback) { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } }
function save(key, value) { localStorage.setItem(key, JSON.stringify(value)); }
function formatRupiah(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }
function findProduct(id) { return PRODUCTS.find((p) => p.id === id); }

let cart = load('gen_cart', []);
let orders = load('gen_orders', []);

function cartSubtotal() { return cart.reduce((s, i) => s + findProduct(i.productId).price * i.qty, 0); }
function cartItemCount() { return cart.reduce((s, i) => s + i.qty, 0); }

function renderDashboard() {
  document.getElementById('m-products').textContent = PRODUCTS.length;
  document.getElementById('m-cartItems').textContent = cartItemCount();
  document.getElementById('m-cartTotal').textContent = formatRupiah(cartSubtotal());
  document.getElementById('m-orders').textContent = orders.length;
  document.getElementById('cartCount').textContent = cartItemCount();
}
function renderCatalog() {
  document.getElementById('catalog').innerHTML = PRODUCTS.map((p) => `
    <div class="product-card"><div class="product-thumb" style="background:${p.color}">${p.name.charAt(0)}</div>
    <h4>${p.name}</h4><span class="pill">${formatRupiah(p.price)}</span>
    <button onclick="addToCart(${p.id})">+ Keranjang</button></div>`).join('');
}
window.addToCart = function (id) {
  const item = cart.find((c) => c.productId === id);
  if (item) item.qty += 1; else cart.push({ productId: id, qty: 1 });
  save('gen_cart', cart); renderAll();
};
window.changeQty = function (id, delta) {
  const item = cart.find((c) => c.productId === id);
  if (!item) return;
  item.qty += delta;
  if (item.qty <= 0) cart = cart.filter((c) => c.productId !== id);
  save('gen_cart', cart); renderAll();
};
document.getElementById('clearCartBtn').addEventListener('click', () => { cart = []; save('gen_cart', cart); renderAll(); });
document.getElementById('openCartBtn').addEventListener('click', () => document.getElementById('cartSection').scrollIntoView({ behavior: 'smooth' }));
document.getElementById('goCheckoutBtn').addEventListener('click', () => document.getElementById('checkoutSection').scrollIntoView({ behavior: 'smooth' }));

function renderCart() {
  document.getElementById('cartTable').innerHTML = cart.map((item) => {
    const p = findProduct(item.productId);
    return `<tr><td>${p.name}</td><td>${formatRupiah(p.price)}</td>
    <td class="qty-control"><button onclick="changeQty(${p.id},-1)">-</button>${item.qty}<button onclick="changeQty(${p.id},1)">+</button></td>
    <td>${formatRupiah(p.price * item.qty)}</td><td></td></tr>`;
  }).join('') || '<tr><td colspan="5">Keranjang kosong.</td></tr>';
  document.getElementById('cartSubtotal').textContent = formatRupiah(cartSubtotal());
}
function renderOrders() {
  document.getElementById('orderTable').innerHTML = orders.map((o) => `<tr><td>#${o.id}</td><td>${formatRupiah(o.total)}</td><td><span class="pill">${o.status}</span></td></tr>`).join('') || '<tr><td colspan="3">Belum ada order.</td></tr>';
}
document.getElementById('checkoutForm').addEventListener('submit', (e) => {
  e.preventDefault();
  const errorEl = document.getElementById('checkoutError');
  errorEl.textContent = '';
  if (!cart.length) { errorEl.textContent = 'Keranjang masih kosong.'; return; }
  const order = { id: orders.length ? Math.max(...orders.map(o => o.id)) + 1 : 1, total: cartSubtotal(), status: 'Created' };
  orders.push(order); save('gen_orders', orders);
  cart = []; save('gen_cart', cart);
  document.getElementById('orderSummary').innerHTML = `<div class="panel"><h4>Pesanan #${order.id} dibuat</h4><p>Total: ${formatRupiah(order.total)}</p></div>`;
  e.target.reset(); renderAll();
});
function renderAll() { renderDashboard(); renderCatalog(); renderCart(); renderOrders(); }
renderAll();