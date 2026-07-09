function load(key, fallback) { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } }
function save(key, value) { localStorage.setItem(key, JSON.stringify(value)); }
function formatRupiah(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }

let favorites = load('gallery_favorites', []);
let orders = load('gallery_orders', []);
let searchTerm = '';

function imgTag(item) {
  if (!item.image_url) {
    return `<div class="gallery-thumb-fallback">${item.name.charAt(0)}</div>`;
  }
  return `<img class="gallery-thumb" src="${item.image_url}" loading="lazy"
    onerror="this.onerror=null;this.src='https://placehold.co/600x400?text=${item.image_query}';this.onerror=function(){this.replaceWith(Object.assign(document.createElement('div'),{className:'gallery-thumb-fallback',textContent:'${item.name.charAt(0)}'}))}" />`;
}

function renderGallery() {
  const filtered = GALLERY_ITEMS.filter((i) => i.name.toLowerCase().includes(searchTerm.toLowerCase()));
  document.getElementById('galleryGrid').innerHTML = filtered.map((item) => `
    <div class="gallery-card" onclick="openDetail(${item.id})">
      ${imgTag(item)}
      <div class="gallery-info"><h4>${item.name}</h4><span>${formatRupiah(item.price)}</span></div>
    </div>`).join('');
  document.getElementById('galleryEmpty').classList.toggle('hidden', filtered.length > 0);
  document.getElementById('m-total').textContent = GALLERY_ITEMS.length;
  document.getElementById('m-fav').textContent = favorites.length;
  document.getElementById('m-orders').textContent = orders.length;
}

window.openDetail = function (id) {
  const item = GALLERY_ITEMS.find((i) => i.id === id);
  if (!item) return;
  document.getElementById('detailModalBody').innerHTML = `
    <h3>${item.name}</h3><p>${formatRupiah(item.price)}</p>
    <button onclick="addOrder(${item.id})">Pesan Sekarang</button>
    <button onclick="closeDetail()" style="background:#eee;color:#333;margin-left:8px">Tutup</button>`;
  document.getElementById('detailModal').classList.remove('hidden');
};
window.closeDetail = function () { document.getElementById('detailModal').classList.add('hidden'); };
window.addOrder = function (id) {
  orders.push({ id: orders.length + 1, item_id: id, date: new Date().toISOString().slice(0, 10), status: 'Created' });
  save('gallery_orders', orders);
  closeDetail();
  renderGallery();
};

document.getElementById('gallerySearch').addEventListener('input', (e) => { searchTerm = e.target.value; renderGallery(); });
document.getElementById('detailModal').addEventListener('click', (e) => { if (e.target.id === 'detailModal') closeDetail(); });
renderGallery();