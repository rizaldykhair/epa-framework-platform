<?php

namespace App\Core;

/**
 * AI Project Generator engine.
 * Mode 1 (default): local, rule-based template generation - no external API needed.
 * Mode 2 (optional): callExternalAI() is a stub extension point; wired to config/ai.php.
 * Whenever Mode 2 is disabled/unavailable, generation always falls back to Mode 1.
 */
class AiGenerator
{
    public const BASE_URL = 'http://localhost/epa-framework/';

    /** Keywords that indicate a project needs a product/service/portfolio gallery -
     *  reused by detectGalleryNeed() and generateGalleryMockData(). */
    private const GALLERY_KEYWORDS = '/product|produk|katalog|catalog|marketplace|e-commerce|ecommerce|gallery|galeri|portfolio|menu|food|laundry|cuci|shoes?|sepatu|helm(et)?|hotel|travel|property|properti|service catalog|store|toko|inventory|inventaris/i';

    public static function generateProjectSlug(string $applicationName): string
    {
        $slug = strtolower(trim($applicationName));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'generated-app';
    }

    private static function parseList(?string $text): array
    {
        if (!$text) return [];
        $parts = preg_split('/[\r\n,;]+/', $text);
        $items = [];
        foreach ($parts as $part) {
            $part = trim($part, " \t\n\r\0\x0B-*.");
            if ($part !== '') $items[] = $part;
        }
        return $items;
    }

    private static function detectStatusFlow(string $haystack): array
    {
        $h = strtolower($haystack);
        if (preg_match('/konsultasi|consultation|dokter|doctor|antrian|queue|klinik|clinic/', $h)) {
            return ['Waiting', 'In Consultation', 'Completed'];
        }
        if (preg_match('/laundry|cuci/', $h)) {
            return ['Received', 'Washing', 'Ironing', 'Ready', 'Picked Up'];
        }
        if (preg_match('/booking|reservation|appointment|jadwal/', $h)) {
            return ['Requested', 'Confirmed', 'In Progress', 'Completed'];
        }
        if (preg_match('/tracking|shipment|pengiriman|resi|lacak/', $h)) {
            return ['Registered', 'In Transit', 'Arrived', 'Delivered'];
        }
        return ['Created', 'In Progress', 'Completed'];
    }

    public static function detectTemplateType(array $input): string
    {
        $haystack = strtolower(implode(' ', [
            $input['project_type'] ?? '',
            $input['main_features'] ?? '',
            $input['workflow_description'] ?? '',
            $input['layout_preference'] ?? '',
        ]));
        if (preg_match('/cart|keranjang|checkout|marketplace|produk|product|catalog|katalog|toko|store|shop/', $haystack)) {
            return 'marketplace';
        }
        if (preg_match('/konsultasi|consultation|dokter|doctor|antrian|queue|booking|reservation|appointment|jadwal|schedule/', $haystack)) {
            return 'booking';
        }
        if (preg_match('/tracking|shipment|pengiriman|resi|lacak/', $haystack)) {
            return 'tracking';
        }
        if (preg_match('/laundry|cuci|service order|jasa|payment calculation/', $haystack)) {
            return 'service_order';
        }
        return 'crud';
    }

    public static function generateEPAPlan(array $input): array
    {
        $features = self::parseList($input['main_features'] ?? '');
        if (!$features) $features = ['Data management', 'Dashboard summary'];
        $entities = self::parseList($input['data_entities'] ?? '');
        $templateType = self::detectTemplateType($input);
        $statusFlow = self::detectStatusFlow(($input['workflow_description'] ?? '') . ' ' . ($input['main_features'] ?? ''));

        $requirements = [];
        $backlog = [];
        $count = count($features);
        foreach ($features as $i => $feature) {
            $priority = $i < ceil($count / 2) ? 'Must' : ($i < ceil($count * 0.8) ? 'Should' : 'Could');
            $code = 'BL-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
            $requirements[] = [
                'title' => $feature,
                'user_story' => "As a user, I want to {$feature} so that the application supports {$input['main_objective']}.",
                'priority' => $priority,
            ];
            $backlog[] = ['code' => $code, 'title' => $feature, 'priority' => $priority];
        }

        $mustItems = array_values(array_filter($backlog, fn($b) => $b['priority'] === 'Must'));
        if (!$mustItems) $mustItems = array_slice($backlog, 0, 1);

        $testingChecklist = [];
        foreach (array_slice($features, 0, 5) as $feature) {
            $testingChecklist[] = "Verify: {$feature}";
        }

        $primaryEntity = $entities[0] ?? 'Item';
        $secondaryEntity = $entities[1] ?? null;

        return [
            'template_type' => $templateType,
            'status_flow' => $statusFlow,
            'primary_entity' => preg_replace('/\s*data$/i', '', $primaryEntity),
            'secondary_entity' => $secondaryEntity ? preg_replace('/\s*data$/i', '', $secondaryEntity) : null,
            'requirements' => $requirements,
            'backlog' => $backlog,
            'sprint1' => [
                'name' => 'Sprint 1 - Initial Prototype',
                'goal' => 'Build initial prototype for: ' . ($input['main_objective'] ?? $input['application_name']),
                'items' => array_column($mustItems, 'code'),
            ],
            'prototype_modules' => array_merge(['Dashboard Summary'], $features),
            'testing_checklist' => $testingChecklist ?: ['Verify: core workflow'],
            'evaluation_criteria' => ['Ease of Use', 'Feature Completeness', 'Interface Design', 'Performance', 'Overall Satisfaction'],
        ];
    }

    public static function callExternalAI(array $input): ?array
    {
        $config = require __DIR__ . '/../../config/ai.php';
        if (!$config['enabled'] || empty($config['api_key'])) {
            return null; // Mode 2 unavailable -> caller must fall back to Mode 1.
        }
        // Extension point for a real provider call (OpenAI/Claude/etc).
        // Intentionally not implemented yet: ship Mode 1 first, add Mode 2 once stable.
        return null;
    }

    public static function sanitizeGeneratedCode(string $code): string
    {
        $code = preg_replace('/<\?php.*?\?>/is', '', $code);
        $code = preg_replace('/<\?.*?\?>/is', '', $code);
        $code = preg_replace('/<script[^>]+src\s*=\s*["\'](?!\.\/|\.\.\/|[a-zA-Z0-9_-]+\.js)[^"\']*["\'][^>]*>\s*<\/script>/i', '', $code);
        $code = preg_replace('/\beval\s*\(/i', 'void(', $code);
        $code = preg_replace('/document\.cookie/i', 'void 0', $code);
        $code = preg_replace('/\b(api[_-]?key|secret|password)\s*[:=]\s*["\'][^"\']{6,}["\']/i', '$1 = "REDACTED"', $code);
        return $code;
    }

    private static function darken(string $hex, float $percent): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) return '#0b4a3a';
        [$r, $g, $b] = array_map('hexdec', str_split($hex, 2));
        $r = max(0, (int) ($r * (1 - $percent)));
        $g = max(0, (int) ($g * (1 - $percent)));
        $b = max(0, (int) ($b * (1 - $percent)));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    public static function generateTemplateBasedPrototype(array $input, array $plan, string $version = 'v01'): array
    {
        $ai = self::callExternalAI($input);
        if ($ai) return $ai; // Mode 2 result (not implemented yet; always null today)

        $displayVersion = preg_replace('/^v(\d)(\d+)$/', 'v$1.$2', $version);
        $shape = match ($plan['template_type']) {
            'marketplace' => self::buildMarketplaceShape($input, $plan, $displayVersion),
            default => self::buildFormStatusShape($input, $plan, $displayVersion),
        };
        return [
            'index' => self::sanitizeGeneratedCode($shape['index']),
            'style' => self::sanitizeGeneratedCode($shape['style']),
            'app' => self::sanitizeGeneratedCode($shape['app']),
        ];
    }

    private static function buildFormStatusShape(array $input, array $plan, string $displayVersion = 'v0.1'): array
    {
        $appName = htmlspecialchars($input['application_name']);
        $displayVersion = htmlspecialchars($displayVersion);
        $client = htmlspecialchars($input['client_name'] ?? '');
        $primary = htmlspecialchars($plan['primary_entity']);
        $secondary = $plan['secondary_entity'] ? htmlspecialchars($plan['secondary_entity']) : null;
        $color = preg_match('/^#[0-9a-fA-F]{3,6}$/', $input['primary_color'] ?? '') ? $input['primary_color'] : '#0b4a3a';
        $dark = self::darken($color, 0.3);
        $statusOptions = implode('', array_map(fn($s) => "<option value=\"$s\">$s</option>", $plan['status_flow']));
        $moduleList = implode('', array_map(fn($m) => '<li>' . htmlspecialchars($m) . '</li>', $plan['prototype_modules']));

        $index = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{$appName}</title>
<link rel="stylesheet" href="style.css" />
</head>
<body>
<header class="topbar">
  <div>
    <span class="badge">Prototype {$displayVersion} · AI Generated</span>
    <h1>{$appName}</h1>
    <p>{$client} · Web-Based Prototype</p>
  </div>
</header>
<main>
  <section class="grid metrics">
    <article><p>Total {$primary}</p><h3 id="m-total">0</h3></article>
    <article><p>Hari Ini</p><h3 id="m-today">0</h3></article>
    <article><p>Dalam Proses</p><h3 id="m-inprogress">0</h3></article>
    <article><p>Selesai Hari Ini</p><h3 id="m-done">0</h3></article>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Registrasi {$primary}</h2></div>
    <form id="registerForm">
      <input type="text" id="fName" placeholder="Nama {$primary}" required />
      <input type="tel" id="fPhone" placeholder="No. HP" required />
HTML;
        if ($secondary) {
            $index .= "\n      <select id=\"fSecondary\"><option value=\"\">Pilih {$secondary}</option></select>";
        }
        $index .= <<<HTML

      <textarea id="fNote" rows="2" placeholder="Catatan (opsional)"></textarea>
      <button type="submit">Daftarkan</button>
    </form>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Daftar {$primary}</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>No.</th><th>{$primary}</th><th>No. HP</th>___SECONDARY_HEADER___<th>Status</th><th>Aksi</th></tr></thead>
        <tbody id="itemTable"></tbody>
      </table>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Modul Prototype</h2></div>
    <ul class="module-list">{$moduleList}</ul>
  </section>
</main>
<footer>{$appName} · Generated by EPA Framework AI Project Generator</footer>
<script src="app.js"></script>
</body>
</html>
HTML;
        $index = str_replace('___SECONDARY_HEADER___', $secondary ? "<th>{$secondary}</th>" : '', $index);

        $style = <<<CSS
:root{--ink:#1c1c1c;--muted:#6b6b6b;--bg:#f5f7f6;--card:#fff;--line:#e2e6e4;--brand:{$color};--brand-dark:{$dark}}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--ink)}
.topbar{background:linear-gradient(135deg,var(--brand-dark),var(--brand));color:#fff;padding:24px 5vw}
.topbar h1{margin:8px 0;font-size:28px}
.badge{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35);padding:5px 10px;border-radius:999px;font-size:12px;font-weight:700}
main{padding:24px 5vw 48px}
.grid{display:grid;gap:14px}
.metrics{grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:20px}
.metrics article{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:16px}
.metrics p{color:var(--muted);margin:0 0 6px;font-size:13px}
.metrics h3{margin:0;font-size:22px;color:var(--brand-dark)}
.panel{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:18px;margin-bottom:18px}
.panel-head h2{margin:0 0 14px;color:var(--brand-dark);font-size:18px}
form{display:flex;flex-direction:column;gap:8px;max-width:420px}
input,select,textarea{border:1px solid var(--line);border-radius:10px;padding:9px 11px;font-size:14px;font-family:inherit}
button{border:0;background:var(--brand);color:#fff;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer;font-size:14px}
button.secondary{background:var(--brand-dark)}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{border-bottom:1px solid var(--line);padding:9px;text-align:left}
th{background:var(--brand-dark);color:#fff}
.pill{display:inline-block;padding:4px 9px;border-radius:999px;background:#eef3f1;color:var(--brand-dark);font-weight:700;font-size:11px}
.module-list{margin:0;padding-left:18px;color:var(--muted)}
footer{text-align:center;color:var(--muted);padding:22px;font-size:13px}
@media(max-width:900px){.metrics{grid-template-columns:repeat(2,1fr)}}
CSS;

        $secondaryJs = $secondary ? "'{$secondary} A','{$secondary} B','{$secondary} C'" : '';
        $statusJson = json_encode($plan['status_flow']);
        $app = <<<JS
const STATUS_FLOW = {$statusJson};
const SECONDARY_OPTIONS = [{$secondaryJs}];

function load(key, fallback) { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } }
function save(key, value) { localStorage.setItem(key, JSON.stringify(value)); }

let items = load('gen_items', []);

function todayStr() { return new Date().toISOString().slice(0, 10); }

function renderSecondaryOptions() {
  const sel = document.getElementById('fSecondary');
  if (!sel) return;
  sel.innerHTML = '<option value="">Pilih</option>' + SECONDARY_OPTIONS.map(o => `<option value="\${o}">\${o}</option>`).join('');
}

function renderDashboard() {
  document.getElementById('m-total').textContent = items.length;
  document.getElementById('m-today').textContent = items.filter(i => i.date === todayStr()).length;
  document.getElementById('m-inprogress').textContent = items.filter(i => i.status !== STATUS_FLOW[STATUS_FLOW.length - 1]).length;
  document.getElementById('m-done').textContent = items.filter(i => i.status === STATUS_FLOW[STATUS_FLOW.length - 1] && i.date === todayStr()).length;
}

function renderTable() {
  document.getElementById('itemTable').innerHTML = items.map((it, idx) => `
    <tr><td>\${idx + 1}</td><td>\${it.name}</td><td>\${it.phone}</td>\${SECONDARY_OPTIONS.length ? `<td>\${it.secondary || '-'}</td>` : ''}
    <td><span class="pill">\${it.status}</span></td>
    <td><select onchange="updateStatus(\${idx}, this.value)">\${STATUS_FLOW.map(s => `<option value="\${s}" \${s === it.status ? 'selected' : ''}>\${s}</option>`).join('')}</select></td></tr>`
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
JS;

        return ['index' => $index, 'style' => $style, 'app' => $app];
    }

    private static function buildMarketplaceShape(array $input, array $plan, string $displayVersion = 'v0.1'): array
    {
        $appName = htmlspecialchars($input['application_name']);
        $displayVersion = htmlspecialchars($displayVersion);
        $client = htmlspecialchars($input['client_name'] ?? '');
        $color = preg_match('/^#[0-9a-fA-F]{3,6}$/', $input['primary_color'] ?? '') ? $input['primary_color'] : '#c2540a';
        $dark = self::darken($color, 0.3);
        $entity = htmlspecialchars($plan['primary_entity'] ?? 'Produk');

        $index = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{$appName}</title>
<link rel="stylesheet" href="style.css" />
</head>
<body>
<header class="topbar">
  <div>
    <span class="badge">Prototype {$displayVersion} · AI Generated</span>
    <h1>{$appName}</h1>
    <p>{$client} · Web-Based Marketplace Prototype</p>
  </div>
  <button type="button" class="cart-btn" id="openCartBtn">Keranjang (<span id="cartCount">0</span>)</button>
</header>
<main>
  <section class="grid metrics">
    <article><p>Total {$entity}</p><h3 id="m-products">0</h3></article>
    <article><p>Item Keranjang</p><h3 id="m-cartItems">0</h3></article>
    <article><p>Total Keranjang</p><h3 id="m-cartTotal">Rp 0</h3></article>
    <article><p>Order</p><h3 id="m-orders">0</h3></article>
  </section>
  <section class="panel"><div class="panel-head"><h2>Katalog {$entity}</h2></div><div class="catalog" id="catalog"></div></section>
  <section class="panel" id="cartSection">
    <div class="panel-head"><h2>Keranjang</h2></div>
    <div class="table-wrap"><table><thead><tr><th>Item</th><th>Harga</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead><tbody id="cartTable"></tbody></table></div>
    <div class="cart-summary"><b>Subtotal: <span id="cartSubtotal">Rp 0</span></b>
      <div class="row-actions"><button type="button" class="secondary" id="clearCartBtn">Kosongkan</button><button type="button" id="goCheckoutBtn">Checkout</button></div>
    </div>
  </section>
  <section class="panel" id="checkoutSection">
    <div class="panel-head"><h2>Checkout</h2></div>
    <form id="checkoutForm">
      <input type="text" id="custName" placeholder="Nama" required />
      <input type="tel" id="custPhone" placeholder="No. HP" required />
      <input type="text" id="custAddress" placeholder="Alamat" required />
      <p id="checkoutError" class="error-text"></p>
      <button type="submit">Buat Pesanan</button>
    </form>
    <div id="orderSummary"></div>
  </section>
  <section class="panel"><div class="panel-head"><h2>Riwayat Order</h2></div><div class="table-wrap"><table><thead><tr><th>Order</th><th>Total</th><th>Status</th></tr></thead><tbody id="orderTable"></tbody></table></div></section>
</main>
<footer>{$appName} · Generated by EPA Framework AI Project Generator</footer>
<script src="app.js"></script>
</body>
</html>
HTML;

        $style = <<<CSS
:root{--ink:#241a0f;--muted:#7a6b5a;--bg:#fbf7f2;--card:#fff;--line:#ece1d3;--brand:{$color};--brand-dark:{$dark}}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--ink)}
.topbar{background:linear-gradient(135deg,var(--brand-dark),var(--brand));color:#fff;padding:24px 5vw;display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}
.topbar h1{margin:8px 0;font-size:26px}
.badge{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35);padding:5px 10px;border-radius:999px;font-size:12px;font-weight:700}
.cart-btn{border:0;background:#fff;color:var(--brand-dark);border-radius:999px;padding:10px 16px;font-weight:700;cursor:pointer}
main{padding:24px 5vw 48px}
.grid{display:grid;gap:14px}
.metrics{grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:20px}
.metrics article{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:16px}
.metrics p{color:var(--muted);margin:0 0 6px;font-size:13px}
.metrics h3{margin:0;font-size:20px;color:var(--brand-dark)}
.panel{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:18px;margin-bottom:18px}
.panel-head h2{margin:0 0 14px;color:var(--brand-dark);font-size:18px}
.catalog{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px}
.product-card{border:1px solid var(--line);border-radius:14px;padding:14px;display:flex;flex-direction:column;gap:6px}
.product-thumb{height:80px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;color:#fff}
form{display:flex;flex-direction:column;gap:8px;max-width:420px}
input,textarea{border:1px solid var(--line);border-radius:10px;padding:9px 11px;font-size:14px;font-family:inherit}
button{border:0;background:var(--brand);color:#fff;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer;font-size:14px}
button.secondary{background:var(--brand-dark)}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{border-bottom:1px solid var(--line);padding:9px;text-align:left}
th{background:var(--brand-dark);color:#fff}
.pill{display:inline-block;padding:4px 9px;border-radius:999px;background:#fbe9db;color:var(--brand-dark);font-weight:700;font-size:11px}
.qty-control{display:flex;align-items:center;gap:6px}
.qty-control button{padding:3px 9px}
.cart-summary{display:flex;justify-content:space-between;align-items:center;margin-top:12px;flex-wrap:wrap;gap:10px}
.error-text{color:#b3261e;font-size:13px;margin:0}
footer{text-align:center;color:var(--muted);padding:22px;font-size:13px}
@media(max-width:900px){.metrics{grid-template-columns:repeat(2,1fr)}.catalog{grid-template-columns:repeat(2,1fr)}}
CSS;

        $entityJson = json_encode($entity);
        $app = <<<JS
const ENTITY_NAME = {$entityJson};
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
    <div class="product-card"><div class="product-thumb" style="background:\${p.color}">\${p.name.charAt(0)}</div>
    <h4>\${p.name}</h4><span class="pill">\${formatRupiah(p.price)}</span>
    <button onclick="addToCart(\${p.id})">+ Keranjang</button></div>`).join('');
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
    return `<tr><td>\${p.name}</td><td>\${formatRupiah(p.price)}</td>
    <td class="qty-control"><button onclick="changeQty(\${p.id},-1)">-</button>\${item.qty}<button onclick="changeQty(\${p.id},1)">+</button></td>
    <td>\${formatRupiah(p.price * item.qty)}</td><td></td></tr>`;
  }).join('') || '<tr><td colspan="5">Keranjang kosong.</td></tr>';
  document.getElementById('cartSubtotal').textContent = formatRupiah(cartSubtotal());
}
function renderOrders() {
  document.getElementById('orderTable').innerHTML = orders.map((o) => `<tr><td>#\${o.id}</td><td>\${formatRupiah(o.total)}</td><td><span class="pill">\${o.status}</span></td></tr>`).join('') || '<tr><td colspan="3">Belum ada order.</td></tr>';
}
document.getElementById('checkoutForm').addEventListener('submit', (e) => {
  e.preventDefault();
  const errorEl = document.getElementById('checkoutError');
  errorEl.textContent = '';
  if (!cart.length) { errorEl.textContent = 'Keranjang masih kosong.'; return; }
  const order = { id: orders.length ? Math.max(...orders.map(o => o.id)) + 1 : 1, total: cartSubtotal(), status: 'Created' };
  orders.push(order); save('gen_orders', orders);
  cart = []; save('gen_cart', cart);
  document.getElementById('orderSummary').innerHTML = `<div class="panel"><h4>Pesanan #\${order.id} dibuat</h4><p>Total: \${formatRupiah(order.total)}</p></div>`;
  e.target.reset(); renderAll();
});
function renderAll() { renderDashboard(); renderCatalog(); renderCart(); renderOrders(); }
renderAll();
JS;

        return ['index' => $index, 'style' => $style, 'app' => $app];
    }

    public static function generatePrototypeFiles(array $input, array $plan, string $slug, string $version = 'v01'): string
    {
        // Slug and version are the only path inputs, and both are strictly whitelisted
        // (no dots, slashes, or "..") so path traversal outside prototypes/generated/ is not possible.
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new \InvalidArgumentException('Invalid slug for prototype output path.');
        }
        if (!preg_match('/^v[0-9]+$/', $version)) {
            throw new \InvalidArgumentException('Invalid version for prototype output path.');
        }
        $files = self::generateTemplateBasedPrototype($input, $plan, $version);
        $relativePath = "prototypes/generated/{$slug}-{$version}";
        $absoluteDir = __DIR__ . "/../../../{$relativePath}";
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new \RuntimeException('Could not create prototype output directory.');
        }
        file_put_contents($absoluteDir . '/index.html', $files['index']);
        file_put_contents($absoluteDir . '/style.css', $files['style']);
        file_put_contents($absoluteDir . '/app.js', $files['app']);
        return $relativePath;
    }

    public static function registerGeneratedPrototype(array $data): int
    {
        return \App\Models\Prototype::create($data);
    }

    /** Finds the next "v01"/"v02"/... folder name that doesn't exist yet for this slug, so an
     *  existing prototype version is never overwritten when continuing an old project. */
    public static function nextAvailableVersion(string $slug): string
    {
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new \InvalidArgumentException('Invalid slug for prototype output path.');
        }
        $n = 1;
        while (is_dir(__DIR__ . "/../../../prototypes/generated/{$slug}-v" . str_pad((string) $n, 2, '0', STR_PAD_LEFT))) {
            $n++;
        }
        return 'v' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }

    // ==================== AI Prototype Output Generator (v2 enhancement) ====================
    // Additive on top of everything above: reuses generateEPAPlan(), buildFormStatusShape(),
    // buildMarketplaceShape() and sanitizeGeneratedCode() rather than duplicating them. Output
    // lives under a NEW path convention (prototypes/generated/{slug}/{version}/) kept separate
    // from the older prototypes/generated/{slug}-{version}/ folders used by generatePrototypeFiles()
    // above, so none of the existing AI Generator flows (generateProject/generatePrototype/
    // continueExistingProject/generateMissingPrototype/regeneratePrototype) are touched or resized.

    /** DB-driven version numbering (v0.1 -> v0.2 -> ...), unlike nextAvailableVersion() which
     *  scans folders - this is what the spec's "getNextPrototypeVersion" asks for. */
    public static function getNextPrototypeVersion(int $projectId): string
    {
        $latest = \App\Models\Prototype::allForProject($projectId)[0] ?? null;
        $label = $latest['version_number'] ?? null;
        if ($label && preg_match('/^v(\d+)\.(\d+)$/', $label, $m)) {
            return 'v' . $m[1] . '.' . ((int) $m[2] + 1);
        }
        return 'v0.1';
    }

    private static function validateSlugVersion(string $slug, string $version): void
    {
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new \InvalidArgumentException('Invalid slug for prototype output path.');
        }
        if (!preg_match('/^v[0-9]+(\.[0-9]+)?$/', $version)) {
            throw new \InvalidArgumentException('Invalid version for prototype output path.');
        }
    }

    /** Every prototype now gets a single adaptive web preview (preview.html) covering
     *  desktop/tablet/mobile - native mobile build links are deprioritized for this stage.
     *  @return array{output_path:string,demo_url:string,preview_url:string,repository_url:string,build_output_url:string,mobile_apk_ipa_link:string,mobile_preview_url:string} */
    public static function generateOutputLinks(string $slug, string $version, string $outputType): array
    {
        self::validateSlugVersion($slug, $version);
        $relativePath = "prototypes/generated/{$slug}/{$version}";
        $previewUrl = self::BASE_URL . $relativePath . '/preview.html';
        // Mobile-leaning output types get a direct device=mobile deep link into the same
        // adaptive preview; others get the "not generated" placeholder (no native build exists).
        $wantsMobileDeepLink = in_array($outputType, ['mobile_first_web', 'hybrid_dashboard_mobile_preview', 'mobile_web_app', 'hybrid_web_mobile', 'mobile_scaffold'], true);

        return [
            'output_path' => $relativePath,
            'demo_url' => self::BASE_URL . $relativePath . '/index.html',
            'preview_url' => $previewUrl,
            'repository_url' => self::BASE_URL . 'repositories/' . $slug . '/' . $version . '/',
            'build_output_url' => self::BASE_URL . $relativePath . '/build/',
            'mobile_apk_ipa_link' => $wantsMobileDeepLink ? ($previewUrl . '?device=mobile') : 'Not generated - responsive web preview available',
            'mobile_preview_url' => $previewUrl,
        ];
    }

    /** @param array $projectData expects title/description/main_features/backlog_titles keys (any may be absent) */
    public static function detectGalleryNeed(array $projectData): bool
    {
        $haystack = strtolower(implode(' ', array_filter([
            $projectData['title'] ?? '',
            $projectData['description'] ?? '',
            $projectData['main_features'] ?? '',
            implode(' ', $projectData['backlog_titles'] ?? []),
        ])));
        return (bool) preg_match(self::GALLERY_KEYWORDS, $haystack);
    }

    /** Realistic mock gallery/product/service items with safe, key-less image URLs.
     *  image_mode 'offline' omits image_url entirely so the frontend renders a CSS
     *  gradient block instead; 'online'/'hybrid' both default to placehold.co (stable,
     *  no external rate limits, matches the EPA accent/primary palette) - the generated
     *  front-end still has a single onerror fallback to a CSS gradient block in case the
     *  device is actually offline at runtime. */
    public static function generateGalleryMockData(array $projectData, string $imageMode): array
    {
        $entity = $projectData['primary_entity'] ?? 'Item';
        $labels = $projectData['gallery_items'] ?? ["{$entity} Reguler", "{$entity} Express", "{$entity} Premium", "{$entity} Deluxe"];
        $prices = [35000, 60000, 95000, 150000, 45000, 75000];

        $items = [];
        foreach (array_values($labels) as $i => $label) {
            $query = rawurlencode($label);
            $items[] = [
                'id' => $i + 1,
                'name' => $label,
                'price' => $prices[$i % count($prices)],
                'image_url' => $imageMode === 'offline' ? null : "https://placehold.co/600x400/e8f5ef/0b4a3a?text={$query}",
                'image_query' => $query,
            ];
        }
        return $items;
    }

    /** Shared design system (CSS vars + base + real desktop/tablet/mobile breakpoints +
     *  bottom-nav) used by both buildGalleryShape() and buildModernFormShape() below, so
     *  every generated prototype - gallery or plain CRUD/status - looks consistent and
     *  responsive. Bottom nav is always in the DOM; the mobile breakpoint is what shows it. */
    private static function designSystemCss(string $primary): string
    {
        $secondary = strcasecmp($primary, '#0b4a3a') === 0 ? '#17845f' : self::darken($primary, 0.3);
        return <<<CSS
:root{--primary:{$primary};--secondary:{$secondary};--accent:#e8f5ef;--bg:#f6faf8;--surface:#ffffff;--text:#16211d;--muted:#6b7280;--border:#d9e7df;--radius:18px;--shadow:0 12px 30px rgba(15,23,42,.08)}
*{box-sizing:border-box}
html,body{max-width:100%;overflow-x:hidden}
body{margin:0;font-family:Inter,"Segoe UI",Roboto,Arial,sans-serif;background:var(--bg);color:var(--text);padding-bottom:0}
.topbar{position:sticky;top:0;z-index:10;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:22px 5vw}
.topbar h1{margin:6px 0;font-size:24px}
.topbar p{margin:0;opacity:.85;font-size:13px}
.badge{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35);padding:5px 10px;border-radius:999px;font-size:11.5px;font-weight:700}
main{padding:24px 5vw 90px}
.grid{display:grid;gap:14px}
.metrics{grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:20px}
.metrics article{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px;box-shadow:var(--shadow)}
.metrics p{color:var(--muted);margin:0 0 6px;font-size:13px}
.metrics h3{margin:0;font-size:22px;color:var(--primary)}
.panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:18px;box-shadow:var(--shadow)}
.panel-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.panel-head h2{margin:0;color:var(--primary);font-size:18px}
.panel-head input[type=search]{border:1px solid var(--border);border-radius:10px;padding:9px 12px;font-size:13px;font-family:inherit}
input,select,textarea{border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-size:14px;font-family:inherit;width:100%}
button{border:0;background:var(--primary);color:#fff;border-radius:10px;padding:11px 16px;font-weight:700;cursor:pointer;font-size:14px}
button.secondary{background:var(--accent);color:var(--primary)}
.empty-state{text-align:center;color:var(--muted);padding:28px}
.modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.45);display:flex;align-items:center;justify-content:center;padding:20px;z-index:50}
.modal-card{background:var(--surface);border-radius:var(--radius);padding:22px;max-width:380px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,.25)}
.modal-card button{margin-top:12px}
.hidden{display:none!important}
.bottom-nav{display:none;position:fixed;left:0;right:0;bottom:0;background:#fff;border-top:1px solid var(--border);justify-content:space-around;padding:10px 0 calc(10px + env(safe-area-inset-bottom));box-shadow:0 -6px 18px rgba(15,23,42,.08);z-index:20}
.bottom-nav a{color:var(--muted);text-decoration:none;font-size:11.5px;font-weight:700;text-align:center;flex:1}
.bottom-nav a.active{color:var(--primary)}
footer{text-align:center;color:var(--muted);padding:24px;font-size:13px}
@media(min-width:1024px){.gallery-grid{grid-template-columns:repeat(auto-fill,minmax(220px,1fr))}}
@media(max-width:1023px){.metrics{grid-template-columns:repeat(2,1fr)}.gallery-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){
  main{padding:16px 16px 84px}
  .metrics{grid-template-columns:1fr}
  .gallery-grid{grid-template-columns:repeat(2,1fr);gap:10px}
  .panel{padding:16px;border-radius:14px}
  form{max-width:100%!important}
  button{width:100%;padding:13px 16px;font-size:15px}
  .row-actions button,.modal-card button{width:auto}
  .bottom-nav{display:flex}
  body{padding-bottom:64px}
}
CSS;
    }

    /** The "modern" generator: same $plan input as generateTemplateBasedPrototype(), but adds
     *  a real image gallery grid (when detectGalleryNeed() is true), search/filter, and empty
     *  states - always responsive (desktop/tablet/mobile) with a bottom nav on mobile. Returns
     *  index/style/app/data/preview - a single adaptive web prototype, no native mobile scaffold. */
    public static function generateModernPrototypeFiles(array $input, array $plan, array $options = []): array
    {
        $imageMode = $options['image_mode'] ?? 'hybrid';
        $includeGallery = $options['include_gallery'] ?? 'auto'; // auto|force|none
        $needsGallery = $includeGallery === 'force' || ($includeGallery === 'auto' && self::detectGalleryNeed([
            'title' => $input['application_name'] ?? '',
            'description' => $input['business_problem'] ?? '',
            'main_features' => $input['main_features'] ?? '',
            'backlog_titles' => array_column($plan['backlog'] ?? [], 'title'),
        ]));

        $galleryItems = $needsGallery ? self::generateGalleryMockData([
            'title' => $input['application_name'] ?? '',
            'primary_entity' => $plan['primary_entity'] ?? 'Item',
            'gallery_items' => array_column($plan['backlog'] ?? [], 'title') ?: null,
        ], $imageMode) : [];

        $shape = $needsGallery
            ? self::buildGalleryShape($input, $plan, $galleryItems)
            : self::buildModernFormShape($input, $plan);

        return [
            'index' => self::sanitizeGeneratedCode($shape['index']),
            'style' => self::sanitizeGeneratedCode($shape['style']),
            'app' => self::sanitizeGeneratedCode($shape['app']),
            'data' => self::sanitizeGeneratedCode($shape['data']),
            'preview' => self::sanitizeGeneratedCode(self::generateAdaptivePreviewPage($input['application_name'] ?? 'Prototype', $options['version'] ?? 'v0.1')),
        ];
    }

    /** Modern gallery/product-grid shape - built for marketplace-style AND plain catalog/portfolio
     *  domains alike (both just need a searchable image grid with a detail modal). */
    private static function buildGalleryShape(array $input, array $plan, array $galleryItems): array
    {
        $appName = htmlspecialchars($input['application_name'] ?? 'Prototype');
        $color = preg_match('/^#[0-9a-fA-F]{3,6}$/', $input['primary_color'] ?? '') ? $input['primary_color'] : '#0b4a3a';
        $entity = htmlspecialchars($plan['primary_entity'] ?? 'Item');
        $bottomNav = "<nav class=\"bottom-nav\"><a href=\"#\" class=\"active\">🏠 Home</a><a href=\"#gallerySearch\">🔍 Cari</a><a href=\"#\">🛒 {$entity}</a><a href=\"#\">👤 Profil</a></nav>";

        $index = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{$appName}</title>
<link rel="stylesheet" href="style.css" />
</head>
<body>
<header class="topbar">
  <div><span class="badge">AI Generated · Adaptive Web Preview</span><h1>{$appName}</h1><p>Katalog {$entity}</p></div>
</header>
<main>
  <section class="grid metrics">
    <article><p>Total {$entity}</p><h3 id="m-total">0</h3></article>
    <article><p>Favorit</p><h3 id="m-fav">0</h3></article>
    <article><p>Pesanan</p><h3 id="m-orders">0</h3></article>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Galeri {$entity}</h2><input type="search" id="gallerySearch" placeholder="Cari {$entity}..." /></div>
    <div class="gallery-grid" id="galleryGrid"></div>
    <p class="empty-state hidden" id="galleryEmpty">Tidak ada {$entity} yang cocok.</p>
  </section>
</main>
<div class="modal-overlay hidden" id="detailModal"><div class="modal-card" id="detailModalBody"></div></div>
{$bottomNav}
<footer>{$appName} · Generated by EPA Framework AI Prototype Output Generator</footer>
<script src="data.js"></script>
<script src="app.js"></script>
</body>
</html>
HTML;

        $style = self::designSystemCss($color) . <<<CSS

.gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px}
.gallery-card{border:1px solid var(--border);border-radius:16px;overflow:hidden;background:var(--surface);cursor:pointer;transition:transform .15s;box-shadow:var(--shadow)}
.gallery-card:hover{transform:translateY(-3px)}
.gallery-thumb{width:100%;height:130px;object-fit:cover;display:block;background:linear-gradient(135deg,var(--primary),var(--secondary))}
.gallery-thumb-fallback{width:100%;height:130px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:26px;background:linear-gradient(135deg,var(--primary),var(--secondary))}
.gallery-info{padding:10px 12px}
.gallery-info h4{margin:0 0 4px;font-size:14px}
.gallery-info span{color:var(--primary);font-weight:700;font-size:13px}
CSS;

        $dataJs = 'const GALLERY_ITEMS = ' . json_encode($galleryItems, JSON_PRETTY_PRINT) . ';';

        $app = <<<JS
function load(key, fallback) { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } }
function save(key, value) { localStorage.setItem(key, JSON.stringify(value)); }
function formatRupiah(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }

let favorites = load('gallery_favorites', []);
let orders = load('gallery_orders', []);
let searchTerm = '';

function imgTag(item) {
  if (!item.image_url) {
    return `<div class="gallery-thumb-fallback">\${item.name.charAt(0)}</div>`;
  }
  return `<img class="gallery-thumb" src="\${item.image_url}" loading="lazy"
    onerror="this.onerror=null;this.replaceWith(Object.assign(document.createElement('div'),{className:'gallery-thumb-fallback',textContent:'\${item.name.charAt(0)}'}))" />`;
}

function renderGallery() {
  const filtered = GALLERY_ITEMS.filter((i) => i.name.toLowerCase().includes(searchTerm.toLowerCase()));
  document.getElementById('galleryGrid').innerHTML = filtered.map((item) => `
    <div class="gallery-card" onclick="openDetail(\${item.id})">
      \${imgTag(item)}
      <div class="gallery-info"><h4>\${item.name}</h4><span>\${formatRupiah(item.price)}</span></div>
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
    <h3>\${item.name}</h3><p>\${formatRupiah(item.price)}</p>
    <button onclick="addOrder(\${item.id})">Pesan Sekarang</button>
    <button class="secondary" onclick="closeDetail()">Tutup</button>`;
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
JS;

        return ['index' => $index, 'style' => $style, 'app' => $app, 'data' => $dataJs];
    }

    /** Modern (non-gallery) shape: summary cards, a registration/status form, and a
     *  status list - the same design system and responsive breakpoints as the gallery
     *  shape, for domains that don't need a product/service catalog. */
    private static function buildModernFormShape(array $input, array $plan): array
    {
        $appName = htmlspecialchars($input['application_name'] ?? 'Prototype');
        $color = preg_match('/^#[0-9a-fA-F]{3,6}$/', $input['primary_color'] ?? '') ? $input['primary_color'] : '#0b4a3a';
        $primary = htmlspecialchars($plan['primary_entity'] ?? 'Item');
        $statusOptionsHtml = implode('', array_map(fn($s) => '<option value="' . htmlspecialchars($s) . '">' . htmlspecialchars($s) . '</option>', $plan['status_flow'] ?? ['Created', 'In Progress', 'Completed']));
        $moduleListHtml = implode('', array_map(fn($m) => '<li>' . htmlspecialchars($m) . '</li>', $plan['prototype_modules'] ?? ['Dashboard Summary']));
        $statusJson = json_encode($plan['status_flow'] ?? ['Created', 'In Progress', 'Completed']);

        $index = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{$appName}</title>
<link rel="stylesheet" href="style.css" />
</head>
<body>
<header class="topbar">
  <div><span class="badge">AI Generated · Adaptive Web Preview</span><h1>{$appName}</h1><p>Prototype berbasis EPA Framework</p></div>
</header>
<main>
  <section class="grid metrics">
    <article><p>Total {$primary}</p><h3 id="m-total">0</h3></article>
    <article><p>Hari Ini</p><h3 id="m-today">0</h3></article>
    <article><p>Dalam Proses</p><h3 id="m-inprogress">0</h3></article>
    <article><p>Selesai</p><h3 id="m-done">0</h3></article>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Registrasi {$primary}</h2></div>
    <form id="registerForm">
      <input type="text" id="fName" placeholder="Nama {$primary}" required />
      <input type="tel" id="fPhone" placeholder="No. HP" required />
      <textarea id="fNote" rows="2" placeholder="Catatan (opsional)"></textarea>
      <button type="submit">Daftarkan</button>
    </form>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Daftar {$primary}</h2><input type="search" id="listSearch" placeholder="Cari..." /></div>
    <div class="gallery-grid" id="itemCards"></div>
    <p class="empty-state hidden" id="listEmpty">Belum ada data.</p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Modul Prototype</h2></div>
    <ul class="module-list">{$moduleListHtml}</ul>
  </section>
</main>
<div class="modal-overlay hidden" id="detailModal"><div class="modal-card" id="detailModalBody"></div></div>
<nav class="bottom-nav"><a href="#" class="active">🏠 Home</a><a href="#registerForm">➕ Tambah</a><a href="#">📋 {$primary}</a><a href="#">👤 Profil</a></nav>
<footer>{$appName} · Generated by EPA Framework AI Prototype Output Generator</footer>
<script src="data.js"></script>
<script src="app.js"></script>
</body>
</html>
HTML;

        $style = self::designSystemCss($color) . <<<CSS

.module-list{margin:0;padding-left:18px;color:var(--muted)}
.item-card{border:1px solid var(--border);border-radius:16px;background:var(--surface);padding:14px;box-shadow:var(--shadow);cursor:pointer;display:flex;flex-direction:column;gap:6px}
.item-card b{font-size:14px}
CSS;

        $dataJs = "const STATUS_FLOW = {$statusJson};\nconst STATUS_OPTIONS_HTML = " . json_encode($statusOptionsHtml) . ';';

        $app = <<<JS
function load(key, fallback) { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } }
function save(key, value) { localStorage.setItem(key, JSON.stringify(value)); }
function todayStr() { return new Date().toISOString().slice(0, 10); }

let items = load('form_items', []);
let searchTerm = '';

function renderDashboard() {
  document.getElementById('m-total').textContent = items.length;
  document.getElementById('m-today').textContent = items.filter((i) => i.date === todayStr()).length;
  document.getElementById('m-inprogress').textContent = items.filter((i) => i.status !== STATUS_FLOW[STATUS_FLOW.length - 1]).length;
  document.getElementById('m-done').textContent = items.filter((i) => i.status === STATUS_FLOW[STATUS_FLOW.length - 1]).length;
}

function renderList() {
  const filtered = items.filter((i) => i.name.toLowerCase().includes(searchTerm.toLowerCase()));
  document.getElementById('itemCards').innerHTML = filtered.map((it, idx) => `
    <div class="item-card" onclick="openDetail(\${idx})"><b>\${it.name}</b><span>\${it.phone}</span>
    <span class="badge" style="align-self:flex-start;background:var(--accent);color:var(--primary)">\${it.status}</span></div>`).join('');
  document.getElementById('listEmpty').classList.toggle('hidden', filtered.length > 0);
}

window.openDetail = function (idx) {
  const it = items[idx];
  document.getElementById('detailModalBody').innerHTML = `
    <h3>\${it.name}</h3><p>\${it.phone}</p><p>\${it.note || ''}</p>
    <label>Status</label>
    <select onchange="updateStatus(\${idx}, this.value)">\${STATUS_OPTIONS_HTML}</select>
    <button class="secondary" onclick="closeDetail()">Tutup</button>`;
  document.getElementById('detailModalBody').querySelector('select').value = it.status;
  document.getElementById('detailModal').classList.remove('hidden');
};
window.closeDetail = function () { document.getElementById('detailModal').classList.add('hidden'); };
window.updateStatus = function (idx, status) {
  items[idx].status = status;
  save('form_items', items);
  renderAll();
  closeDetail();
};

document.getElementById('registerForm').addEventListener('submit', (e) => {
  e.preventDefault();
  const name = document.getElementById('fName').value.trim();
  const phone = document.getElementById('fPhone').value.trim();
  if (!name || !phone) return;
  items.push({ name, phone, note: document.getElementById('fNote').value.trim(), status: STATUS_FLOW[0], date: todayStr() });
  save('form_items', items);
  e.target.reset();
  renderAll();
});
document.getElementById('listSearch').addEventListener('input', (e) => { searchTerm = e.target.value; renderList(); });
document.getElementById('detailModal').addEventListener('click', (e) => { if (e.target.id === 'detailModal') closeDetail(); });

function renderAll() { renderDashboard(); renderList(); }
renderAll();
JS;

        return ['index' => $index, 'style' => $style, 'app' => $app, 'data' => $dataJs];
    }

    /** Adaptive preview wrapper: a device-switcher (Desktop/Tablet/Mobile) around an
     *  iframe pointing at index.html - the single responsive prototype is reused for all
     *  three, nothing native/separate is generated. Reads ?device= to auto-select on load
     *  (so a "Mobile Preview" link can deep-link straight into the mobile-sized frame). */
    public static function generateAdaptivePreviewPage(string $appName, string $version): string
    {
        $appName = htmlspecialchars($appName);
        $version = htmlspecialchars($version);

        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{$appName} · Prototype Preview</title>
<style>
:root{--primary:#0b4a3a;--secondary:#17845f;--bg:#f6faf8;--surface:#fff;--text:#16211d;--muted:#6b7280;--border:#d9e7df;--radius:18px;--shadow:0 12px 30px rgba(15,23,42,.08)}
*{box-sizing:border-box}
html,body{max-width:100%;overflow-x:hidden}
body{margin:0;font-family:Inter,"Segoe UI",Roboto,Arial,sans-serif;background:var(--bg);color:var(--text)}
.preview-header{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:20px 5vw}
.preview-header h1{margin:4px 0;font-size:20px}
.preview-header p{margin:0;opacity:.85;font-size:13px}
.device-switcher{display:flex;justify-content:center;gap:8px;padding:16px;flex-wrap:wrap}
.device-switcher button{border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:999px;padding:9px 18px;font-weight:700;font-size:13px;cursor:pointer}
.device-switcher button.active{background:var(--primary);color:#fff;border-color:var(--primary)}
.device-switcher a.open-full{border:1px solid var(--border);background:var(--surface);color:var(--primary);border-radius:999px;padding:9px 18px;font-weight:700;font-size:13px;text-decoration:none}
.preview-stage{display:flex;justify-content:center;padding:0 16px 40px}
.preview-frame-wrap{background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow);padding:14px;transition:width .2s ease;overflow:hidden;width:100%;max-width:1200px}
.preview-frame-wrap.tablet{max-width:768px}
.preview-frame-wrap.mobile{max-width:390px}
iframe{width:100%;height:78vh;border:0;border-radius:12px;display:block}
</style>
</head>
<body>
<header class="preview-header">
  <span style="font-size:12px;font-weight:700;opacity:.85">PROTOTYPE PREVIEW</span>
  <h1>{$appName}</h1>
  <p>Version {$version}</p>
</header>
<div class="device-switcher">
  <button type="button" data-device="desktop" class="active">Desktop</button>
  <button type="button" data-device="tablet">Tablet</button>
  <button type="button" data-device="mobile">Mobile</button>
  <a class="open-full" href="index.html" target="_blank" rel="noopener">Open Full Prototype</a>
</div>
<div class="preview-stage">
  <div class="preview-frame-wrap" id="frameWrap"><iframe src="index.html" id="previewFrame"></iframe></div>
</div>
<script>
function setDevice(device) {
  document.getElementById('frameWrap').className = 'preview-frame-wrap' + (device === 'desktop' ? '' : ' ' + device);
  document.querySelectorAll('.device-switcher button').forEach((b) => b.classList.toggle('active', b.dataset.device === device));
}
document.querySelectorAll('.device-switcher button').forEach((b) => b.addEventListener('click', () => setDevice(b.dataset.device)));
const requestedDevice = new URLSearchParams(window.location.search).get('device');
if (requestedDevice) setDevice(requestedDevice);
</script>
</body>
</html>
HTML;
    }

    /** Kept for backward compatibility (not called by the default increment flow anymore -
     *  native/scaffold-style mobile output is deprioritized in favor of the single adaptive
     *  web preview above). Still available if a future stage wants a real native wrapper. */
    public static function generateMobileScaffold(array $input, array $plan, array $galleryItems = []): array
    {
        $appName = htmlspecialchars($input['application_name'] ?? 'Prototype');
        $entity = htmlspecialchars($plan['primary_entity'] ?? 'Item');
        $color = preg_match('/^#[0-9a-fA-F]{3,6}$/', $input['primary_color'] ?? '') ? $input['primary_color'] : '#0b4a3a';

        $cards = $galleryItems
            ? implode('', array_map(fn($i) => '<div class="m-card"><div class="m-thumb">' . htmlspecialchars(mb_substr($i['name'], 0, 1)) . '</div><b>' . htmlspecialchars($i['name']) . '</b><span>Rp ' . number_format($i['price'], 0, ',', '.') . '</span></div>', array_slice($galleryItems, 0, 6)))
            : '<div class="m-card"><b>' . $entity . ' A</b></div><div class="m-card"><b>' . $entity . ' B</b></div>';

        $mobilePreview = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{$appName} · Mobile Preview</title>
<link rel="stylesheet" href="mobile-style.css" />
</head>
<body>
<div class="m-screen" id="screen-home">
  <header class="m-topbar">{$appName}</header>
  <div class="m-list">{$cards}</div>
</div>
<nav class="bottom-nav"><a href="#" class="active">🏠 Home</a><a href="#">📋 {$entity}</a><a href="#">👤 Profil</a></nav>
<script src="mobile-app.js"></script>
</body>
</html>
HTML;

        $mobileStyle = <<<CSS
:root{--brand:{$color}}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;background:#f6faf8;padding-bottom:60px;max-width:420px;margin-inline:auto;border-inline:1px solid #d8e6df;min-height:100vh}
.m-topbar{background:var(--brand);color:#fff;padding:18px;font-weight:800;font-size:18px}
.m-list{padding:14px;display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.m-card{background:#fff;border-radius:14px;padding:12px;box-shadow:0 4px 10px rgba(11,74,58,.08);display:flex;flex-direction:column;gap:4px}
.m-thumb{width:100%;height:70px;border-radius:10px;background:linear-gradient(135deg,var(--brand),#17845f);margin-bottom:4px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:22px}
.bottom-nav{position:fixed;left:50%;transform:translateX(-50%);bottom:0;width:100%;max-width:420px;background:#fff;border-top:1px solid #d8e6df;display:flex;justify-content:space-around;padding:10px 0}
.bottom-nav a{color:#6a7671;text-decoration:none;font-size:12px;font-weight:700}
.bottom-nav a.active{color:var(--brand)}
CSS;

        $mobileApp = "// Mobile scaffold interactivity placeholder - static preview only for this stage.\nconsole.log('Mobile scaffold preview loaded.');";

        $readme = <<<MD
# Mobile Build Scaffold (deprioritized)

Not used by the default AI Prototype Output Generator flow anymore - the responsive
adaptive web preview (preview.html) replaces this for desktop/tablet/mobile demo needs.
MD;

        return [
            'mobile_preview' => self::sanitizeGeneratedCode($mobilePreview),
            'mobile_style' => self::sanitizeGeneratedCode($mobileStyle),
            'mobile_app' => self::sanitizeGeneratedCode($mobileApp),
            'readme' => $readme,
        ];
    }

    /** Writes generateModernPrototypeFiles() output under prototypes/generated/{slug}/{version}/. */
    private static function writeModernPrototypeFiles(string $slug, string $version, array $files): string
    {
        self::validateSlugVersion($slug, $version);
        $relativePath = "prototypes/generated/{$slug}/{$version}";
        $absoluteDir = __DIR__ . "/../../../{$relativePath}";
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new \RuntimeException('Could not create prototype output directory.');
        }
        file_put_contents($absoluteDir . '/index.html', $files['index']);
        file_put_contents($absoluteDir . '/style.css', $files['style']);
        file_put_contents($absoluteDir . '/app.js', $files['app']);
        file_put_contents($absoluteDir . '/data.js', $files['data']);
        file_put_contents($absoluteDir . '/preview.html', $files['preview']);
        return $relativePath;
    }

    /**
     * Main orchestrator for the AI Prototype Output Generator. Pure file-generation + a
     * prepared result package - it does NOT write to the database itself (Prototype::create,
     * Feedback::markImplemented, Backlog::update, EpaWorkflow::sync all stay in the Controller
     * layer, exactly like every other AiGenerator entry point in this codebase already does).
     *
     * @param array $options output_type, image_mode, ui_style, include_gallery, feedback_ids,
     *                        backlog_ids, defect_ids, created_by
     * @return array{version:string,output_path:string,demo_url:string,repository_url:string,
     *               build_output_url:string,mobile_apk_ipa_link:string,mobile_preview_url:?string,
     *               gallery_detected:bool,plan:array,input:array,previous_prototype_id:?int}
     */
    /** Shared by generatePrototypeIncrementFromEPAArtifacts() (writes files) and
     *  previewIncrementPlan() (read-only) - resolves sources, builds the synthetic
     *  $input/$plan, and detects gallery need, without touching the filesystem. */
    private static function prepareIncrementContext(int $projectId, array $options): array
    {
        $project = \App\Models\Project::find($projectId);
        if (!$project) {
            throw new \RuntimeException('Project not found');
        }

        $backlogIds = $options['backlog_ids'] ?? [];
        $feedbackIds = $options['feedback_ids'] ?? [];
        $defectIds = $options['defect_ids'] ?? [];

        $allBacklog = \App\Models\Backlog::allForProject($projectId);
        $sourceBacklog = $backlogIds
            ? array_values(array_filter($allBacklog, fn($b) => in_array((int) $b['id'], array_map('intval', $backlogIds), true)))
            : array_values(array_filter($allBacklog, fn($b) => in_array($b['priority'], ['Must', 'Should'], true) && $b['status'] !== 'Done'));

        $feedbackTexts = [];
        foreach ($feedbackIds as $fbId) {
            $fb = \App\Models\Feedback::find((int) $fbId);
            if ($fb) $feedbackTexts[] = $fb['finding'];
        }
        $defectTexts = [];
        foreach ($defectIds as $dId) {
            $d = \App\Models\Defect::find((int) $dId);
            if ($d) $defectTexts[] = $d['title'] ?? $d['description'];
        }

        $previous = \App\Models\Prototype::allForProject($projectId)[0] ?? null;
        $input = [
            'application_name' => $project['name'],
            'client_name' => $project['client_name'] ?? '',
            'project_type' => $project['project_type'] ?? 'Web-Based Application',
            'business_problem' => $project['description'] ?? '',
            'main_objective' => $project['description'] ?? $project['name'],
            'main_features' => implode("\n", array_merge(array_column($sourceBacklog, 'title'), $feedbackTexts, $defectTexts)) ?: implode("\n", array_column($allBacklog, 'title')),
            'workflow_description' => $previous['notes'] ?? '',
            'primary_color' => $options['primary_color'] ?? '#0b4a3a',
        ];

        $plan = self::generateEPAPlan($input);
        $outputType = $options['output_type'] ?? 'web_app';
        $galleryDetected = ($options['include_gallery'] ?? 'auto') === 'force' || (
            ($options['include_gallery'] ?? 'auto') !== 'none' && self::detectGalleryNeed([
                'title' => $input['application_name'],
                'description' => $input['business_problem'],
                'main_features' => $input['main_features'],
                'backlog_titles' => array_column($allBacklog, 'title'),
            ])
        );

        return [
            'project' => $project,
            'input' => $input,
            'plan' => $plan,
            'slug' => self::generateProjectSlug($project['name']),
            'version' => self::getNextPrototypeVersion($projectId),
            'output_type' => $outputType,
            'image_mode' => $options['image_mode'] ?? 'hybrid',
            'gallery_detected' => $galleryDetected,
            'previous_prototype_id' => $previous['id'] ?? null,
            'source_backlog_ids' => array_column($sourceBacklog, 'id'),
            'feedback_texts' => $feedbackTexts,
            'defect_texts' => $defectTexts,
        ];
    }

    /** Read-only: computes next version, resolved sources, gallery detection, and a
     *  preview of the output links WITHOUT writing any files or touching the database. */
    public static function previewIncrementPlan(int $projectId, array $options): array
    {
        $ctx = self::prepareIncrementContext($projectId, $options);
        $links = self::generateOutputLinks($ctx['slug'], $ctx['version'], $ctx['output_type']);
        return array_merge($links, [
            'version' => $ctx['version'],
            'gallery_detected' => $ctx['gallery_detected'],
            'source_backlog_ids' => $ctx['source_backlog_ids'],
            'source_backlog_count' => count($ctx['source_backlog_ids']),
            'source_feedback_count' => count($ctx['feedback_texts']),
            'source_defect_count' => count($ctx['defect_texts']),
            'previous_prototype_id' => $ctx['previous_prototype_id'],
        ]);
    }

    /**
     * Main orchestrator for the AI Prototype Output Generator. Pure file-generation + a
     * prepared result package - it does NOT write to the database itself (Prototype::create,
     * Feedback::markImplemented, Backlog::update, EpaWorkflow::sync all stay in the Controller
     * layer, exactly like every other AiGenerator entry point in this codebase already does).
     *
     * @param array $options output_type, image_mode, ui_style, include_gallery, feedback_ids,
     *                        backlog_ids, defect_ids, created_by
     * @return array{version:string,output_path:string,demo_url:string,repository_url:string,
     *               build_output_url:string,mobile_apk_ipa_link:string,mobile_preview_url:?string,
     *               gallery_detected:bool,plan:array,input:array,previous_prototype_id:?int}
     */
    public static function generatePrototypeIncrementFromEPAArtifacts(int $projectId, array $options): array
    {
        $ctx = self::prepareIncrementContext($projectId, $options);
        ['input' => $input, 'plan' => $plan, 'slug' => $slug, 'version' => $version,
            'output_type' => $outputType, 'image_mode' => $imageMode, 'gallery_detected' => $galleryDetected] = $ctx;

        $files = self::generateModernPrototypeFiles($input, $plan, [
            'output_type' => $outputType,
            'image_mode' => $imageMode,
            'include_gallery' => $galleryDetected ? 'force' : 'none',
            'version' => $version,
        ]);

        self::writeModernPrototypeFiles($slug, $version, $files);
        $links = self::generateOutputLinks($slug, $version, $outputType);

        return array_merge($links, [
            'version' => $version,
            'gallery_detected' => $galleryDetected,
            'plan' => $plan,
            'input' => $input,
            'previous_prototype_id' => $ctx['previous_prototype_id'],
            'source_backlog_ids' => $ctx['source_backlog_ids'],
        ]);
    }
}
