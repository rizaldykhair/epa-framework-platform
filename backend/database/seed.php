<?php

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/autoload.php';

use App\Core\Auth;
use App\Core\Database;

$pdo = Database::connection();

$roles = ['Admin', 'Product Owner', 'Developer', 'Tester', 'Evaluator'];
foreach ($roles as $role) {
    $pdo->prepare('INSERT IGNORE INTO roles (name) VALUES (?)')->execute([$role]);
}
$roleIds = [];
foreach ($pdo->query('SELECT id, name FROM roles') as $row) {
    $roleIds[$row['name']] = $row['id'];
}

$demoPassword = 'password123';
$demoUsers = [
    ['Admin User', 'admin@epa.local', 'Admin'],
    ['Product Owner Demo', 'po@epa.local', 'Product Owner'],
    ['Developer Demo', 'dev@epa.local', 'Developer'],
    ['Tester Demo', 'tester@epa.local', 'Tester'],
    ['Evaluator Demo', 'evaluator@epa.local', 'Evaluator'],
];
foreach ($demoUsers as [$name, $email, $roleName]) {
    $pdo->prepare('INSERT IGNORE INTO users (name, email, password_hash, role_id) VALUES (?, ?, ?, ?)')
        ->execute([$name, $email, Auth::hashPassword($demoPassword), $roleIds[$roleName]]);
}
$userIds = [];
foreach ($pdo->query('SELECT id, email FROM users') as $row) {
    $userIds[$row['email']] = $row['id'];
}

$pdo->prepare('INSERT IGNORE INTO projects (id, name, project_code, description, client_name, project_type, current_epa_phase, owner_id, created_by, status) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([
        'E-Commerce Prototype',
        'EPA-ECOM-001',
        'Dissertation demo project for EPA Framework Dashboard',
        'Demo Retail Client',
        'Web-Based Application',
        'Demo Prototype, User Evaluation & Sprint Review',
        $userIds['po@epa.local'],
        $userIds['admin@epa.local'],
        'Active',
    ]);
$pdo->prepare("UPDATE projects SET project_code = 'EPA-ECOM-001', client_name = 'Demo Retail Client', project_type = 'Web-Based Application' WHERE id = 1 AND project_code IS NULL")->execute();
$projectId = 1;

foreach ($demoUsers as [$name, $email, $roleName]) {
    $pdo->prepare('INSERT IGNORE INTO project_members (project_id, user_id, role_in_project) VALUES (?, ?, ?)')
        ->execute([$projectId, $userIds[$email], $roleName]);
}

$phases = [
    ['Requirements Elicitation & Project Adaptive Backlog', 'Initial Phase'],
    ['Initial Prototype Design & Sprint Backlog', 'Initial/Core'],
    ['Demo Prototype', 'Dev & Testing'],
    ['User Evaluation & Sprint Review', 'Dev & Testing'],
    ['Update Backlog', 'Dev & Testing'],
    ['Prototype-Driven Increment Delivery', 'Dev & Testing'],
    ['Evolutionary Iterative Refinement', 'Dev & Testing'],
    ['User Testing', 'Dev & Testing'],
    ['Product Release', 'Release Phase'],
    ['Final Deployment & Continuous Improvement', 'Release Phase'],
    ['Product Retrospective', 'Release Phase'],
];
foreach ($phases as $i => [$name, $stage]) {
    $status = $i < 3 ? 'Done' : ($i < 8 ? 'In Progress' : 'Pending');
    $pdo->prepare('INSERT IGNORE INTO epa_phases (project_id, name, stage_label, sequence, status) VALUES (?, ?, ?, ?, ?)')
        ->execute([$projectId, $name, $stage, $i + 1, $status]);
}

$backlog = [
    ['BL-01', 'Login pengguna untuk akses fitur personal', 'Must', 'Done'],
    ['BL-02', 'Katalog produk responsif', 'Must', 'Done'],
    ['BL-03', 'Detail produk lengkap', 'Must', 'Done'],
    ['BL-04', 'CTA tambah ke keranjang lebih menonjol', 'Should', 'In Progress'],
    ['BL-05', 'Keranjang belanja dan subtotal otomatis', 'Must', 'In Progress'],
    ['BL-06', 'Checkout simulatif', 'Should', 'To Do'],
    ['BL-07', 'Filter kategori produk', 'Could', 'Deferred'],
    ['BL-08', 'Dashboard admin ringkas', 'Should', 'To Do'],
];
foreach ($backlog as [$code, $title, $priority, $status]) {
    $pdo->prepare('INSERT IGNORE INTO backlogs (project_id, code, title, priority, status, created_by, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$projectId, $code, $title, $priority, $status, $userIds['po@epa.local'], $userIds['dev@epa.local']]);
}

$feedback = [
    ['FB-01', 'Tombol keranjang kurang menonjol', 'Converted', 'Open'],
    ['FB-02', 'Butuh filter kategori produk', 'Converted', 'Deferred'],
    ['FB-03', 'Detail perlu menampilkan stok', 'Converted', 'Closed'],
    ['FB-04', 'Dashboard perlu ringkasan order', 'Converted', 'Open'],
    ['FB-05', 'Checkout terlalu banyak langkah', 'Converted', 'Open'],
];
foreach ($feedback as [$code, $finding, $decision, $status]) {
    $pdo->prepare('INSERT IGNORE INTO feedback (project_id, code, finding, decision, status, submitted_by, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$projectId, $code, $finding, $decision, $status, $userIds['evaluator@epa.local'], $userIds['dev@epa.local']]);
}

$tests = [
    ['UT-01', 'Login dengan email valid', 'Functional', 'Pass'],
    ['UT-02', 'Lihat katalog produk', 'Functional', 'Pass'],
    ['UT-03', 'Lihat detail produk', 'Functional', 'Fail'],
    ['UT-04', 'Tambah produk ke keranjang', 'Usability', 'Fail'],
    ['UT-05', 'Subtotal keranjang otomatis', 'Functional', 'Not Run'],
    ['SEC-01', 'Input password salah', 'Security', 'Pass'],
];
foreach ($tests as [$code, $scenario, $type, $result]) {
    $pdo->prepare('INSERT IGNORE INTO tests (project_id, created_by, code, scenario, type, result, executed_by, executed_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$projectId, $userIds['tester@epa.local'], $code, $scenario, $type, $result, $userIds['tester@epa.local']]);
}

$failedTests = $pdo->prepare("SELECT id, scenario FROM tests WHERE project_id = ? AND result = 'Fail'");
$failedTests->execute([$projectId]);
foreach ($failedTests as $row) {
    $exists = $pdo->prepare('SELECT id FROM defects WHERE test_id = ?');
    $exists->execute([$row['id']]);
    if (!$exists->fetch()) {
        $pdo->prepare('INSERT INTO defects (test_id, project_id, description, severity, status, reported_by, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$row['id'], $projectId, 'Defect from failed test: ' . $row['scenario'], 'Medium', 'Open', $userIds['tester@epa.local'], $userIds['dev@epa.local']]);
    }
}

$release = [
    ['All Must backlog items completed', 'Yes'],
    ['No critical defects open', 'Yes'],
    ['User testing completed', 'No'],
    ['Release note prepared', 'No'],
    ['Deployment target prepared', 'Yes'],
    ['Product Owner sign-off', 'No'],
];
foreach ($release as [$item, $status]) {
    $pdo->prepare('INSERT IGNORE INTO release_checklists (project_id, item, status, verified_by) VALUES (?, ?, ?, ?)')
        ->execute([$projectId, $item, $status, $userIds['po@epa.local']]);
}

$pdo->prepare('INSERT IGNORE INTO retrospectives (project_id, went_well, needs_improvement, action_item, created_by) VALUES (?, ?, ?, ?, ?)')
    ->execute([
        $projectId,
        'Demo prototype membuat pengguna lebih cepat memahami alur sistem.',
        'Beberapa feedback masih perlu dipertegas dengan pertanyaan yang lebih operasional.',
        'Perbaiki rubrik evaluasi pengguna dan tetapkan batas perubahan backlog per sprint.',
        $userIds['po@epa.local'],
    ]);

$pdo->prepare('UPDATE backlogs SET assigned_to = ? WHERE project_id = ? AND assigned_to IS NULL')
    ->execute([$userIds['dev@epa.local'], $projectId]);
$pdo->prepare('UPDATE feedback SET assigned_to = ? WHERE project_id = ? AND assigned_to IS NULL')
    ->execute([$userIds['dev@epa.local'], $projectId]);
$pdo->prepare('UPDATE defects SET assigned_to = ? WHERE project_id = ? AND assigned_to IS NULL')
    ->execute([$userIds['dev@epa.local'], $projectId]);
$pdo->prepare('UPDATE tests SET created_by = ? WHERE project_id = ? AND created_by IS NULL')
    ->execute([$userIds['tester@epa.local'], $projectId]);

$pdo->prepare('INSERT IGNORE INTO sprints (id, project_id, name, status) VALUES (1, ?, ?, ?)')
    ->execute([$projectId, 'Sprint 1', 'Active']);
$sprintId = 1;

$pdo->prepare('INSERT IGNORE INTO prototypes (id, project_id, sprint_id, version_label, version_number, prototype_name, demo_url, build_output_url, notes, implemented_feedback_summary, status, demo_date, created_by) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)')
    ->execute([
        $projectId,
        $sprintId,
        'v0.1',
        'v0.1',
        'E-Commerce Initial Web Prototype',
        'http://localhost/epa-framework/prototypes/ecommerce-v01/index.html',
        'http://localhost/epa-framework/prototypes/ecommerce-v01/index.html',
        'Initial clickable prototype covering login, catalog, and cart.',
        'Responsive product catalog, clearer add-to-cart button, automatic cart subtotal, simplified checkout, product stock display, store dashboard summary.',
        'Ready for Testing',
        $userIds['dev@epa.local'],
    ]);
$prototypeId = 1;
// Fix any previously-seeded row that still has the old placeholder URL.
$pdo->prepare("UPDATE prototypes SET
    prototype_name = 'E-Commerce Initial Web Prototype',
    demo_url = 'http://localhost/epa-framework/prototypes/ecommerce-v01/index.html',
    build_output_url = 'http://localhost/epa-framework/prototypes/ecommerce-v01/index.html',
    implemented_feedback_summary = 'Responsive product catalog, clearer add-to-cart button, automatic cart subtotal, simplified checkout, product stock display, store dashboard summary.',
    status = 'Ready for Testing'
    WHERE id = ? AND demo_url LIKE '%example.com%'")->execute([$prototypeId]);

$pdo->prepare('INSERT IGNORE INTO evaluations (id, prototype_id, evaluator_id, rating, usability_feedback, functional_feedback, design_feedback, performance_feedback, suggestion) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([
        $prototypeId,
        $userIds['evaluator@epa.local'],
        4,
        'Navigasi cukup mudah diikuti.',
        'Fitur utama sudah berjalan sesuai skenario.',
        'Kontras warna tombol keranjang kurang menonjol.',
        'Waktu muat halaman katalog cukup cepat.',
        'Tambahkan filter kategori produk pada katalog.',
    ]);

$ecomTests = [
    ['TC-ECOM-001', 'View product catalog'],
    ['TC-ECOM-002', 'Add product to cart'],
    ['TC-ECOM-003', 'Update cart quantity'],
    ['TC-ECOM-004', 'Checkout order'],
    ['TC-ECOM-005', 'Verify localStorage order history'],
];
foreach ($ecomTests as [$code, $scenario]) {
    $pdo->prepare('INSERT IGNORE INTO tests (project_id, created_by, prototype_id, code, scenario, type, result, executed_by, executed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$projectId, $userIds['tester@epa.local'], $prototypeId, $code, $scenario, 'Functional', 'Pass', $userIds['tester@epa.local']]);
}

echo "Seed complete.\n";
echo "Demo login (all roles share password '$demoPassword'):\n";
foreach ($demoUsers as [$name, $email, $roleName]) {
    echo "  $roleName: $email\n";
}
