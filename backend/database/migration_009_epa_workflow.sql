-- Enforces the real EPA Framework lifecycle (Initial / Dev & Testing / Release phase,
-- 11 sequential steps) instead of letting a project jump straight to prototype/release.
--
-- Naming note: an `epa_phases` table already exists, but it stores a *per-project custom
-- timeline* (project_id, sequence, name, stage_label, status) used by PhaseController.
-- That is a different concept from the global workflow *definition* this feature needs,
-- so the new master tables are named epa_workflow_phases/epa_workflow_steps to avoid
-- colliding with (or corrupting) the existing per-project timeline feature.

CREATE TABLE IF NOT EXISTS epa_workflow_phases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  phase_code VARCHAR(40) NOT NULL UNIQUE,
  phase_name VARCHAR(100) NOT NULL,
  phase_order INT NOT NULL,
  description VARCHAR(255) NULL
);

CREATE TABLE IF NOT EXISTS epa_workflow_steps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  phase_id INT NOT NULL,
  step_code VARCHAR(40) NOT NULL UNIQUE,
  step_name VARCHAR(150) NOT NULL,
  step_order INT NOT NULL,
  description VARCHAR(255) NULL,
  required_artifacts TEXT NULL,
  responsible_roles VARCHAR(150) NULL,
  gate_rule VARCHAR(255) NULL,
  next_step_code VARCHAR(40) NULL,
  FOREIGN KEY (phase_id) REFERENCES epa_workflow_phases(id)
);

-- Audit trail of step transitions (both successful and blocked attempts). This is the
-- only new "progress" table: current status is computed live and stored directly on
-- projects (below) rather than in a second, easily-stale project_epa_progress table.
CREATE TABLE IF NOT EXISTS project_epa_step_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  step_code VARCHAR(40) NOT NULL,
  phase_code VARCHAR(40) NOT NULL,
  status ENUM('Completed','Blocked') NOT NULL,
  completed_by INT NULL,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  notes TEXT NULL,
  validation_result TINYINT(1) NOT NULL DEFAULT 0,
  missing_artifacts TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (completed_by) REFERENCES users(id)
);

ALTER TABLE projects
  ADD COLUMN current_epa_step VARCHAR(40) NOT NULL DEFAULT 'REQ_BACKLOG' AFTER current_epa_phase,
  ADD COLUMN epa_completion_percentage INT NOT NULL DEFAULT 0 AFTER epa_completion_status,
  ADD COLUMN epa_status VARCHAR(30) NOT NULL DEFAULT 'In Progress' AFTER epa_completion_percentage,
  ADD COLUMN epa_next_action VARCHAR(255) NULL AFTER epa_status,
  ADD COLUMN epa_missing_artifacts TEXT NULL AFTER epa_next_action,
  ADD COLUMN is_epa_aligned TINYINT(1) NOT NULL DEFAULT 0 AFTER epa_missing_artifacts;

INSERT INTO epa_workflow_phases (phase_code, phase_name, phase_order, description) VALUES
('INITIAL_PHASE', 'Initial Phase', 1, 'Requirements elicitation through initial sprint/prototype planning.'),
('DEV_TESTING_PHASE', 'Dev & Testing Phase', 2, 'Prototype-driven increment delivery, evaluation and user testing.'),
('RELEASE_PHASE', 'Release Phase', 3, 'Product release, deployment and retrospective.');

INSERT INTO epa_workflow_steps (phase_id, step_code, step_name, step_order, required_artifacts, responsible_roles, gate_rule, next_step_code) VALUES
((SELECT id FROM epa_workflow_phases WHERE phase_code='INITIAL_PHASE'), 'REQ_BACKLOG', 'Requirements Elicitation & Project Adaptive Backlog', 1,
  'business problem/description, requirement list, project adaptive backlog', 'Product Owner,Admin',
  'project description filled and at least 1 backlog item', 'PROTOTYPE_DESIGN_SPRINT'),
((SELECT id FROM epa_workflow_phases WHERE phase_code='INITIAL_PHASE'), 'PROTOTYPE_DESIGN_SPRINT', 'Initial Prototype Design & Sprint Backlog', 2,
  'sprint 1, sprint backlog items, assigned PO/Developer/Tester/Evaluator', 'Product Owner,Admin',
  'sprint exists with sprint items, and PO/Developer/Tester/Evaluator all assigned', 'DEMO_PROTOTYPE'),
((SELECT id FROM epa_workflow_phases WHERE phase_code='DEV_TESTING_PHASE'), 'DEMO_PROTOTYPE', 'Demo Prototype', 3,
  'prototype increment, version number, demo_url, build output', 'Developer,Admin',
  'prototype exists with demo_url and build_output_url/output_path', 'USER_EVALUATION_REVIEW'),
((SELECT id FROM epa_workflow_phases WHERE phase_code='DEV_TESTING_PHASE'), 'USER_EVALUATION_REVIEW', 'User Evaluation & Sprint Review', 4,
  'evaluation result, user feedback', 'Evaluator,Product Owner,Admin',
  'at least 1 evaluation and 1 feedback recorded', 'UPDATE_BACKLOG'),
((SELECT id FROM epa_workflow_phases WHERE phase_code='DEV_TESTING_PHASE'), 'UPDATE_BACKLOG', 'Update Backlog', 5,
  'feedback decision, converted/updated backlog items', 'Product Owner,Admin',
  'at least 1 feedback decided (Converted/Rejected/Closed)', 'INCREMENT_DELIVERY'),
((SELECT id FROM epa_workflow_phases WHERE phase_code='DEV_TESTING_PHASE'), 'INCREMENT_DELIVERY', 'Prototype-Driven Increment Delivery', 6,
  'implemented feedback or updated prototype increment', 'Developer,Admin',
  'implemented feedback exists or a 2nd+ prototype version exists', 'ITERATIVE_REFINEMENT'),
((SELECT id FROM epa_workflow_phases WHERE phase_code='DEV_TESTING_PHASE'), 'ITERATIVE_REFINEMENT', 'Evolutionary Iterative Refinement', 7,
  'refinement log, before/after description, prototype version history', 'Developer,Product Owner,Admin',
  '2+ prototype versions and a before/after change summary', 'USER_TESTING'),
((SELECT id FROM epa_workflow_phases WHERE phase_code='DEV_TESTING_PHASE'), 'USER_TESTING', 'User Testing', 8,
  'test cases, test results, defects for failed tests', 'Tester,Admin',
  'test cases exist, all executed, every Fail has a defect', 'PRODUCT_RELEASE'),
((SELECT id FROM epa_workflow_phases WHERE phase_code='RELEASE_PHASE'), 'PRODUCT_RELEASE', 'Product Release', 9,
  'release checklist, Must backlog done, no open critical defects, Admin approval', 'Product Owner,Admin',
  'release checklist complete, all Must backlog Done, no open Critical defects, project approved', 'FINAL_DEPLOYMENT'),
((SELECT id FROM epa_workflow_phases WHERE phase_code='RELEASE_PHASE'), 'FINAL_DEPLOYMENT', 'Final Deployment & Continuous Improvement', 10,
  'deployment target/notes, final released version', 'Admin,Developer,Product Owner',
  'project status = Released', 'PRODUCT_RETROSPECTIVE'),
((SELECT id FROM epa_workflow_phases WHERE phase_code='RELEASE_PHASE'), 'PRODUCT_RETROSPECTIVE', 'Product Retrospective', 11,
  'what went well, what needs improvement, action items', 'Admin,Product Owner,Developer,Tester,Evaluator',
  'retrospective recorded with action items', NULL);
