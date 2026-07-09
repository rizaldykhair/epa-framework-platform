USE epa_framework;

ALTER TABLE projects
  ADD COLUMN project_code VARCHAR(30) NULL AFTER name,
  ADD COLUMN client_name VARCHAR(150) NULL AFTER description,
  ADD COLUMN project_type ENUM('Web App','Mobile App','Web + Mobile') DEFAULT 'Web App' AFTER client_name,
  ADD COLUMN current_epa_phase VARCHAR(100) NULL AFTER project_type,
  ADD COLUMN approved_by INT NULL AFTER created_by,
  ADD COLUMN start_date DATE NULL AFTER status,
  ADD COLUMN target_release_date DATE NULL AFTER start_date,
  ADD COLUMN risk_notes TEXT NULL AFTER target_release_date,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  MODIFY COLUMN status ENUM('Draft','Proposed','Active','On Hold','Released','Archived') DEFAULT 'Draft',
  ADD CONSTRAINT fk_projects_approved_by FOREIGN KEY (approved_by) REFERENCES users(id);

ALTER TABLE project_members
  ADD COLUMN responsibility_notes TEXT NULL AFTER role_in_project;

ALTER TABLE prototypes
  ADD COLUMN backlog_id INT NULL AFTER sprint_id,
  ADD COLUMN prototype_name VARCHAR(150) NULL AFTER version_number,
  ADD COLUMN development_type ENUM('New Feature','Improvement','Bug Fix','UI Revision','Integration') DEFAULT 'New Feature' AFTER prototype_name,
  ADD COLUMN repository_url VARCHAR(255) NULL AFTER demo_url,
  ADD COLUMN build_output_url VARCHAR(255) NULL AFTER repository_url,
  ADD COLUMN mobile_build_url VARCHAR(255) NULL AFTER build_output_url,
  ADD COLUMN status ENUM('Planned','In Progress','Ready for Testing','Revised','Completed') DEFAULT 'Planned' AFTER notes,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER demo_date,
  ADD CONSTRAINT fk_prototypes_backlog FOREIGN KEY (backlog_id) REFERENCES backlogs(id) ON DELETE SET NULL;

ALTER TABLE tests
  ADD COLUMN severity ENUM('Low','Medium','High','Critical') NULL AFTER result,
  ADD COLUMN evidence_url VARCHAR(255) NULL AFTER notes;

ALTER TABLE defects
  ADD COLUMN title VARCHAR(200) NULL AFTER project_id,
  ADD COLUMN steps_to_reproduce TEXT NULL AFTER description,
  ADD COLUMN expected_result TEXT NULL AFTER steps_to_reproduce,
  ADD COLUMN actual_result TEXT NULL AFTER expected_result;

ALTER TABLE backlogs
  ADD COLUMN user_story TEXT NULL AFTER title,
  ADD COLUMN acceptance_criteria TEXT NULL AFTER user_story,
  ADD COLUMN business_value VARCHAR(100) NULL AFTER acceptance_criteria,
  MODIFY COLUMN status ENUM('Draft','Approved','Rejected','Revised','To Do','In Progress','Done','Deferred') DEFAULT 'To Do';

ALTER TABLE feedback
  ADD COLUMN reason TEXT NULL AFTER decision;

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

ALTER TABLE evaluations
  MODIFY COLUMN rating TINYINT NULL,
  ADD COLUMN project_id INT NULL AFTER prototype_id,
  ADD COLUMN ease_of_use_rating TINYINT NULL AFTER evaluator_id,
  ADD COLUMN feature_completeness_rating TINYINT NULL AFTER ease_of_use_rating,
  ADD COLUMN interface_design_rating TINYINT NULL AFTER feature_completeness_rating,
  ADD COLUMN performance_rating TINYINT NULL AFTER interface_design_rating,
  ADD COLUMN overall_satisfaction_rating TINYINT NULL AFTER performance_rating,
  ADD COLUMN what_works_well TEXT NULL AFTER overall_satisfaction_rating,
  ADD COLUMN problems_found TEXT NULL AFTER what_works_well,
  ADD COLUMN improvement_suggestions TEXT NULL AFTER problems_found,
  ADD COLUMN feedback_priority ENUM('Must','Should','Could') NULL AFTER improvement_suggestions,
  ADD COLUMN submitted_at TIMESTAMP NULL AFTER created_at,
  ADD CONSTRAINT fk_evaluations_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE;

UPDATE evaluations e
  JOIN prototypes p ON p.id = e.prototype_id
  SET e.project_id = p.project_id, e.submitted_at = e.created_at
  WHERE e.project_id IS NULL;
