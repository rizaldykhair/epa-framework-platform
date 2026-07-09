CREATE DATABASE IF NOT EXISTS epa_framework CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE epa_framework;

CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  project_code VARCHAR(30) NULL,
  description TEXT,
  client_name VARCHAR(150) NULL,
  project_type ENUM('Web App','Mobile App','Web + Mobile','Web-Based Application') DEFAULT 'Web App',
  current_epa_phase VARCHAR(100) NULL,
  owner_id INT NOT NULL,
  created_by INT NULL,
  approved_by INT NULL,
  status ENUM('Draft','Proposed','Active','On Hold','Released','Archived') DEFAULT 'Draft',
  start_date DATE NULL,
  target_release_date DATE NULL,
  risk_notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE project_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  user_id INT NOT NULL,
  role_in_project VARCHAR(50) NOT NULL,
  responsibility_notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_project_user (project_id, user_id)
) ENGINE=InnoDB;

CREATE TABLE epa_phases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  stage_label VARCHAR(100) NOT NULL,
  sequence INT NOT NULL,
  status ENUM('Pending','In Progress','Done') DEFAULT 'Pending',
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE sprints (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  start_date DATE,
  end_date DATE,
  status ENUM('Planned','Active','Completed') DEFAULT 'Planned',
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE backlogs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  code VARCHAR(20) NOT NULL,
  title VARCHAR(255) NOT NULL,
  user_story TEXT NULL,
  acceptance_criteria TEXT NULL,
  business_value VARCHAR(100) NULL,
  priority ENUM('Must','Should','Could','Wont') DEFAULT 'Should',
  status ENUM('Draft','Approved','Rejected','Revised','To Do','In Progress','Done','Deferred') DEFAULT 'To Do',
  source_feedback_id INT NULL,
  created_by INT NULL,
  assigned_to INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (assigned_to) REFERENCES users(id),
  UNIQUE KEY uniq_project_code (project_id, code)
) ENGINE=InnoDB;

CREATE TABLE sprint_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sprint_id INT NOT NULL,
  backlog_id INT NOT NULL,
  assigned_to INT NULL,
  status ENUM('Planned','In Progress','Done') DEFAULT 'Planned',
  FOREIGN KEY (sprint_id) REFERENCES sprints(id) ON DELETE CASCADE,
  FOREIGN KEY (backlog_id) REFERENCES backlogs(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE prototypes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  sprint_id INT NULL,
  backlog_id INT NULL,
  version_label VARCHAR(50) NOT NULL,
  version_number VARCHAR(50) NULL,
  prototype_name VARCHAR(150) NULL,
  development_type ENUM('New Feature','Improvement','Bug Fix','UI Revision','Integration') DEFAULT 'New Feature',
  demo_url VARCHAR(255) NULL,
  repository_url VARCHAR(255) NULL,
  build_output_url VARCHAR(255) NULL,
  mobile_build_url VARCHAR(255) NULL,
  notes TEXT,
  implemented_feedback_summary TEXT NULL,
  generated_by_ai TINYINT(1) NOT NULL DEFAULT 0,
  generation_request_id INT NULL,
  output_path VARCHAR(255) NULL,
  source_type ENUM('manually_registered','ai_generated','improved_from_feedback') DEFAULT 'manually_registered',
  status ENUM('Planned','In Progress','Ready for Testing','Revised','Completed') DEFAULT 'Planned',
  demo_date DATE,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (sprint_id) REFERENCES sprints(id) ON DELETE SET NULL,
  FOREIGN KEY (backlog_id) REFERENCES backlogs(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE ai_generation_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  requested_by INT NOT NULL,
  user_role VARCHAR(50) NOT NULL,
  application_name VARCHAR(150) NOT NULL,
  client_name VARCHAR(150) NULL,
  project_type ENUM('Web-Based Application','Mobile App','Web + Mobile') DEFAULT 'Web-Based Application',
  target_users TEXT NULL,
  business_problem TEXT NULL,
  main_objective TEXT NULL,
  main_features TEXT NULL,
  user_roles_needed TEXT NULL,
  data_entities TEXT NULL,
  workflow_description TEXT NULL,
  reporting_needs TEXT NULL,
  design_style ENUM('Simple','Modern','Corporate','Marketplace','Dashboard') DEFAULT 'Modern',
  primary_color VARCHAR(20) DEFAULT '#0b4a3a',
  layout_preference ENUM('Dashboard','Landing Page + Dashboard','CRUD System','Marketplace Catalog','Booking System','Tracking System') DEFAULT 'Dashboard',
  generation_mode ENUM('template','ai_api') DEFAULT 'template',
  generation_status ENUM('Draft','Previewed','Generating','Generated','Failed','Approved') DEFAULT 'Draft',
  generated_project_id INT NULL,
  generated_prototype_id INT NULL,
  generated_output_path VARCHAR(255) NULL,
  generated_demo_url VARCHAR(255) NULL,
  ai_raw_response LONGTEXT NULL,
  error_message TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (requested_by) REFERENCES users(id),
  FOREIGN KEY (generated_project_id) REFERENCES projects(id) ON DELETE SET NULL,
  FOREIGN KEY (generated_prototype_id) REFERENCES prototypes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE prototypes
  ADD CONSTRAINT fk_prototypes_generation_request FOREIGN KEY (generation_request_id) REFERENCES ai_generation_requests(id) ON DELETE SET NULL;

CREATE TABLE generated_artifacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  generation_request_id INT NOT NULL,
  project_id INT NULL,
  artifact_type ENUM('requirement','backlog','sprint','prototype','test_case','evaluation_form') NOT NULL,
  artifact_title VARCHAR(255) NOT NULL,
  artifact_content TEXT NULL,
  artifact_path VARCHAR(255) NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (generation_request_id) REFERENCES ai_generation_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  prototype_id INT NULL,
  code VARCHAR(20) NOT NULL,
  finding TEXT NOT NULL,
  decision ENUM('Converted','Clarify','Rejected') DEFAULT 'Clarify',
  reason TEXT NULL,
  status ENUM('Open','Deferred','Closed') DEFAULT 'Open',
  submitted_by INT NULL,
  assigned_to INT NULL,
  implemented_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (prototype_id) REFERENCES prototypes(id) ON DELETE SET NULL,
  FOREIGN KEY (submitted_by) REFERENCES users(id),
  FOREIGN KEY (assigned_to) REFERENCES users(id),
  UNIQUE KEY uniq_project_fbcode (project_id, code)
) ENGINE=InnoDB;

ALTER TABLE backlogs
  ADD CONSTRAINT fk_backlog_feedback FOREIGN KEY (source_feedback_id) REFERENCES feedback(id) ON DELETE SET NULL;

CREATE TABLE tests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  created_by INT NULL,
  backlog_id INT NULL,
  prototype_id INT NULL,
  code VARCHAR(20) NOT NULL,
  scenario VARCHAR(255) NOT NULL,
  type ENUM('Functional','Usability','Security','Performance','Regression','UAT') DEFAULT 'Functional',
  result ENUM('Pass','Fail','Not Run','Retest') DEFAULT 'Not Run',
  severity ENUM('Low','Medium','High','Critical') NULL,
  notes TEXT NULL,
  evidence_url VARCHAR(255) NULL,
  executed_by INT NULL,
  executed_at TIMESTAMP NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (backlog_id) REFERENCES backlogs(id) ON DELETE SET NULL,
  FOREIGN KEY (prototype_id) REFERENCES prototypes(id) ON DELETE SET NULL,
  FOREIGN KEY (executed_by) REFERENCES users(id),
  UNIQUE KEY uniq_project_testcode (project_id, code)
) ENGINE=InnoDB;

CREATE TABLE defects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  test_id INT NOT NULL,
  project_id INT NOT NULL,
  title VARCHAR(200) NULL,
  description TEXT NOT NULL,
  steps_to_reproduce TEXT NULL,
  expected_result TEXT NULL,
  actual_result TEXT NULL,
  severity ENUM('Low','Medium','High','Critical') DEFAULT 'Medium',
  status ENUM('Open','Fixed','Verified','Closed') DEFAULT 'Open',
  reported_by INT NULL,
  assigned_to INT NULL,
  verified_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (reported_by) REFERENCES users(id),
  FOREIGN KEY (assigned_to) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE evaluations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  prototype_id INT NOT NULL,
  project_id INT NULL,
  evaluator_id INT NOT NULL,
  ease_of_use_rating TINYINT NULL,
  feature_completeness_rating TINYINT NULL,
  interface_design_rating TINYINT NULL,
  performance_rating TINYINT NULL,
  overall_satisfaction_rating TINYINT NULL,
  what_works_well TEXT,
  problems_found TEXT,
  improvement_suggestions TEXT,
  feedback_priority ENUM('Must','Should','Could') NULL,
  rating TINYINT NULL,
  usability_feedback TEXT,
  functional_feedback TEXT,
  design_feedback TEXT,
  performance_feedback TEXT,
  suggestion TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  submitted_at TIMESTAMP NULL,
  FOREIGN KEY (prototype_id) REFERENCES prototypes(id) ON DELETE CASCADE,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE feedback_implementation (
  id INT AUTO_INCREMENT PRIMARY KEY,
  feedback_id INT NOT NULL,
  backlog_id INT NULL,
  prototype_id INT NULL,
  implementation_action VARCHAR(255) NULL,
  before_change_description TEXT NULL,
  after_change_description TEXT NULL,
  developer_notes TEXT NULL,
  status ENUM('Not Started','In Progress','Implemented','Need Clarification') DEFAULT 'Not Started',
  assigned_developer INT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE,
  FOREIGN KEY (backlog_id) REFERENCES backlogs(id) ON DELETE SET NULL,
  FOREIGN KEY (prototype_id) REFERENCES prototypes(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_developer) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE release_checklists (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  item VARCHAR(255) NOT NULL,
  status ENUM('Yes','No') DEFAULT 'No',
  verified_by INT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (verified_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE retrospectives (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  sprint_id INT NULL,
  went_well TEXT,
  needs_improvement TEXT,
  action_item TEXT,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (sprint_id) REFERENCES sprints(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(50) NOT NULL,
  entity VARCHAR(50) NOT NULL,
  entity_id INT NULL,
  meta_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
