<?php
/**
 * Seeds the "Smart Laundry Mobile App" EPA simulation project (Phase 1 -> Phase 3).
 * Safe to re-run: looks up existing rows by natural key instead of hardcoding IDs.
 */

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/autoload.php';

use App\Core\Database;

$pdo = Database::connection();

function findOrInsert(\PDO $pdo, string $select, array $selectParams, string $insert, array $insertParams): int
{
    $stmt = $pdo->prepare($select);
    $stmt->execute($selectParams);
    $row = $stmt->fetch();
    if ($row) {
        return (int) $row['id'];
    }
    $pdo->prepare($insert)->execute($insertParams);
    return (int) $pdo->lastInsertId();
}

$userIds = [];
foreach ($pdo->query('SELECT id, email FROM users') as $row) {
    $userIds[$row['email']] = (int) $row['id'];
}

$projectId = findOrInsert(
    $pdo,
    'SELECT id FROM projects WHERE project_code = ?',
    ['SL-01'],
    'INSERT INTO projects (name, project_code, description, client_name, project_type, current_epa_phase, owner_id, created_by, approved_by, status, start_date, target_release_date)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [
        'Smart Laundry Mobile App',
        'SL-01',
        'The laundry business still records orders manually, causing lost receipts, slow order tracking, and difficulty notifying customers when orders are ready.',
        'Laundry Berkah Medan',
        'Web + Mobile',
        'Demo Prototype, User Evaluation & Sprint Review',
        $userIds['po@epa.local'],
        $userIds['admin@epa.local'],
        $userIds['admin@epa.local'],
        'Active',
        date('Y-m-d', strtotime('-21 days')),
        date('Y-m-d', strtotime('+30 days')),
    ]
);

foreach ([
    ['admin@epa.local', 'Admin'],
    ['po@epa.local', 'Product Owner'],
    ['dev@epa.local', 'Developer'],
    ['tester@epa.local', 'Tester'],
    ['evaluator@epa.local', 'Evaluator'],
] as [$email, $roleInProject]) {
    $pdo->prepare('INSERT IGNORE INTO project_members (project_id, user_id, role_in_project) VALUES (?, ?, ?)')
        ->execute([$projectId, $userIds[$email], $roleInProject]);
}

// PHASE 1 -- Requirements Elicitation & Project Adaptive Backlog
$phases = [
    ['Requirements Elicitation & Project Adaptive Backlog', 'Initial Phase', 'Done'],
    ['Initial Prototype Design & Sprint Backlog', 'Initial/Core', 'Done'],
    ['Demo Prototype', 'Dev & Testing', 'Done'],
    ['User Evaluation & Sprint Review', 'Dev & Testing', 'In Progress'],
    ['Update Backlog', 'Dev & Testing', 'In Progress'],
    ['Prototype-Driven Increment Delivery', 'Dev & Testing', 'Pending'],
    ['Evolutionary Iterative Refinement', 'Dev & Testing', 'Pending'],
    ['User Testing', 'Dev & Testing', 'In Progress'],
    ['Product Release', 'Release Phase', 'Pending'],
    ['Final Deployment & Continuous Improvement', 'Release Phase', 'Pending'],
    ['Product Retrospective', 'Release Phase', 'Pending'],
];
foreach ($phases as $i => [$name, $stage, $status]) {
    $exists = $pdo->prepare('SELECT id FROM epa_phases WHERE project_id = ? AND name = ?');
    $exists->execute([$projectId, $name]);
    if (!$exists->fetch()) {
        $pdo->prepare('INSERT INTO epa_phases (project_id, name, stage_label, sequence, status) VALUES (?, ?, ?, ?, ?)')
            ->execute([$projectId, $name, $stage, $i + 1, $status]);
    }
}

// Initial requirements / backlog (BL-001..BL-006)
$initialBacklog = [
    ['BL-001', 'Login and registration', 'As a customer, I want to register and log in so that I can access my orders and history.', 'Given a valid email and password, the user can create an account and log in successfully.', 'High', 'Must', 'Done'],
    ['BL-002', 'Customer management', 'As an admin, I want to manage customer records so that staff can find customer info quickly.', 'Admin can create, view, and update customer records.', 'High', 'Must', 'Done'],
    ['BL-003', 'Laundry order input', 'As an admin, I want to input laundry orders so that every order is recorded digitally.', 'Admin can create an order with customer, items, and service type.', 'High', 'Must', 'Done'],
    ['BL-004', 'Order status tracking', 'As a customer, I want to track my order status so I know when it will be ready.', 'Customer sees current status of their order in real time.', 'Medium', 'Should', 'In Progress'],
    ['BL-005', 'Payment calculation', 'As an admin, I want the system to calculate payment so totals are always accurate.', 'System calculates total based on weight/item and service type.', 'Medium', 'Should', 'To Do'],
    ['BL-006', 'Pickup notification', 'As a customer, I want to be notified when my laundry is ready for pickup.', 'Customer receives a notification when order status becomes Ready.', 'Medium', 'Could', 'To Do'],
];
$backlogIds = [];
foreach ($initialBacklog as [$code, $title, $story, $ac, $value, $priority, $status]) {
    $exists = $pdo->prepare('SELECT id FROM backlogs WHERE project_id = ? AND code = ?');
    $exists->execute([$projectId, $code]);
    $row = $exists->fetch();
    if ($row) {
        $backlogIds[$code] = (int) $row['id'];
        continue;
    }
    $pdo->prepare('INSERT INTO backlogs (project_id, code, title, user_story, acceptance_criteria, business_value, priority, status, created_by, assigned_to)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$projectId, $code, $title, $story, $ac, $value, $priority, $status, $userIds['po@epa.local'], $userIds['dev@epa.local']]);
    $backlogIds[$code] = (int) $pdo->lastInsertId();
}

// PHASE 2 -- Initial Prototype Design & Sprint Backlog
$sprintId = findOrInsert(
    $pdo,
    'SELECT id FROM sprints WHERE project_id = ? AND name = ?',
    [$projectId, 'Sprint 1 - Initial Prototype'],
    'INSERT INTO sprints (project_id, name, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)',
    [$projectId, 'Sprint 1 - Initial Prototype', date('Y-m-d', strtotime('-14 days')), date('Y-m-d', strtotime('-1 day')), 'Active']
);
foreach (['BL-001', 'BL-002', 'BL-003'] as $code) {
    $pdo->prepare('INSERT IGNORE INTO sprint_items (sprint_id, backlog_id, assigned_to, status) VALUES (?, ?, ?, ?)')
        ->execute([$sprintId, $backlogIds[$code], $userIds['dev@epa.local'], 'Done']);
}

$prototypeId = findOrInsert(
    $pdo,
    'SELECT id FROM prototypes WHERE project_id = ? AND version_number = ?',
    [$projectId, 'v0.1'],
    'INSERT INTO prototypes (project_id, sprint_id, version_label, version_number, prototype_name, development_type, demo_url, repository_url, build_output_url, notes, implemented_feedback_summary, status, demo_date, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [
        $projectId,
        $sprintId,
        'v0.1',
        'v0.1',
        'Smart Laundry Initial Web Prototype',
        'New Feature',
        'http://localhost/epa-framework/prototypes/smart-laundry-v01/index.html',
        'https://github.com/example/smart-laundry-demo',
        'http://localhost/epa-framework/prototypes/smart-laundry-v01/index.html',
        'Initial clickable prototype covering login, customer management, and laundry order input.',
        'Simplified customer registration form, improved order status labels, added WhatsApp notification button, added express service price calculation, added dashboard summary cards.',
        'Ready for Testing',
        date('Y-m-d', strtotime('-7 days')),
        $userIds['dev@epa.local'],
    ]
);

// PHASE 3 -- Demo, Evaluation, Feedback, Testing, Backlog update
$evalExists = $pdo->prepare('SELECT id FROM evaluations WHERE prototype_id = ? AND evaluator_id = ?');
$evalExists->execute([$prototypeId, $userIds['evaluator@epa.local']]);
if (!$evalExists->fetch()) {
    $pdo->prepare('INSERT INTO evaluations (prototype_id, project_id, evaluator_id, ease_of_use_rating, feature_completeness_rating, interface_design_rating, performance_rating, overall_satisfaction_rating, what_works_well, problems_found, improvement_suggestions, feedback_priority, submitted_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
        ->execute([
            $prototypeId, $projectId, $userIds['evaluator@epa.local'],
            3, 3, 3, 4, 3,
            'Order input and login flow are simple to follow.',
            'Registration form is too long; order status labels are confusing; no WhatsApp notification.',
            'Simplify registration, use simple status labels, add WhatsApp button, support express service pricing, show a daily order summary on the admin dashboard.',
            'Should',
        ]);
}

$feedbackItems = [
    ['FB-001', 'Customer registration form is too long.', 'Converted', 'Closed', 'Simplify to essential fields only.'],
    ['FB-002', 'Order status should use simple labels: Received, Washing, Ironing, Ready, Picked Up.', 'Converted', 'Closed', 'Improves clarity for customers and staff.'],
    ['FB-003', 'Add WhatsApp notification button.', 'Converted', 'Closed', 'High customer value, low implementation effort.'],
    ['FB-004', 'Payment calculation should support express service.', 'Clarify', 'Deferred', 'Requires payment logic redesign, scheduled for Sprint 2.'],
    ['FB-005', 'Admin dashboard should show today\'s orders.', 'Converted', 'Closed', 'Useful for daily operations.'],
];
$feedbackIds = [];
foreach ($feedbackItems as [$code, $finding, $decision, $status, $reason]) {
    $exists = $pdo->prepare('SELECT id FROM feedback WHERE project_id = ? AND code = ?');
    $exists->execute([$projectId, $code]);
    $row = $exists->fetch();
    if ($row) {
        $feedbackIds[$code] = (int) $row['id'];
        continue;
    }
    $pdo->prepare('INSERT INTO feedback (project_id, prototype_id, code, finding, decision, reason, status, submitted_by, assigned_to)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$projectId, $prototypeId, $code, $finding, $decision, $reason, $status, $userIds['evaluator@epa.local'], $userIds['dev@epa.local']]);
    $feedbackIds[$code] = (int) $pdo->lastInsertId();
}

// New backlog created from converted feedback (BL-007..BL-010)
$feedbackBacklog = [
    ['BL-007', 'Simplify customer registration form', 'FB-001', 'Should'],
    ['BL-008', 'Improve order status labels', 'FB-002', 'Must'],
    ['BL-009', 'Add WhatsApp notification button', 'FB-003', 'Should'],
    ['BL-010', 'Add today\'s order summary in admin dashboard', 'FB-005', 'Could'],
];
foreach ($feedbackBacklog as [$code, $title, $fbCode, $priority]) {
    $exists = $pdo->prepare('SELECT id FROM backlogs WHERE project_id = ? AND code = ?');
    $exists->execute([$projectId, $code]);
    if ($exists->fetch()) {
        continue;
    }
    $pdo->prepare('INSERT INTO backlogs (project_id, code, title, priority, status, source_feedback_id, created_by, assigned_to)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$projectId, $code, $title, $priority, 'To Do', $feedbackIds[$fbCode], $userIds['po@epa.local'], $userIds['dev@epa.local']]);
}

// Tester test cases (TC-001..TC-005)
$testCases = [
    ['TC-001', 'Login with valid user', 'Functional', 'Pass'],
    ['TC-002', 'Add new customer', 'Functional', 'Pass'],
    ['TC-003', 'Create laundry order', 'Functional', 'Pass'],
    ['TC-004', 'Update order status', 'Functional', 'Fail'],
    ['TC-005', 'Calculate payment', 'Functional', 'Fail'],
];
$testIds = [];
foreach ($testCases as [$code, $scenario, $type, $result]) {
    $exists = $pdo->prepare('SELECT id FROM tests WHERE project_id = ? AND code = ?');
    $exists->execute([$projectId, $code]);
    $row = $exists->fetch();
    if ($row) {
        $testIds[$code] = (int) $row['id'];
        continue;
    }
    $pdo->prepare('INSERT INTO tests (project_id, created_by, prototype_id, code, scenario, type, result, executed_by, executed_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$projectId, $userIds['tester@epa.local'], $prototypeId, $code, $scenario, $type, $result, $userIds['tester@epa.local']]);
    $testIds[$code] = (int) $pdo->lastInsertId();
}

$defects = [
    ['TC-004', 'DF-001: Order status update not saved correctly', 'Order status update not saved correctly.', 'High'],
    ['TC-005', 'DF-002: Express service price not calculated', 'Express service price not calculated.', 'Medium'],
];
foreach ($defects as [$tcCode, $title, $description, $severity]) {
    $exists = $pdo->prepare('SELECT id FROM defects WHERE test_id = ?');
    $exists->execute([$testIds[$tcCode]]);
    if ($exists->fetch()) {
        continue;
    }
    $pdo->prepare('INSERT INTO defects (test_id, project_id, title, description, severity, status, reported_by, assigned_to)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$testIds[$tcCode], $projectId, $title, $description, $severity, 'Open', $userIds['tester@epa.local'], $userIds['dev@epa.local']]);
}

$releaseChecklist = [
    ['All Must backlog items completed', 'No'],
    ['No critical defects open', 'No'],
    ['User testing completed', 'No'],
    ['Release note prepared', 'No'],
    ['Deployment target prepared', 'Yes'],
];
foreach ($releaseChecklist as [$item, $status]) {
    $exists = $pdo->prepare('SELECT id FROM release_checklists WHERE project_id = ? AND item = ?');
    $exists->execute([$projectId, $item]);
    if (!$exists->fetch()) {
        $pdo->prepare('INSERT INTO release_checklists (project_id, item, status, verified_by) VALUES (?, ?, ?, ?)')
            ->execute([$projectId, $item, $status, $userIds['po@epa.local']]);
    }
}

echo "Smart Laundry Mobile App simulation seeded. project_id=$projectId, prototype_id=$prototypeId, sprint_id=$sprintId\n";
