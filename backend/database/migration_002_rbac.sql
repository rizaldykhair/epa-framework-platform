USE epa_framework;

-- users
ALTER TABLE users
  ADD COLUMN status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active' AFTER role_id;

-- projects
ALTER TABLE projects
  ADD COLUMN created_by INT NULL AFTER owner_id,
  ADD CONSTRAINT fk_projects_created_by FOREIGN KEY (created_by) REFERENCES users(id);

-- project_members: controls which project a non-Admin user can access
CREATE TABLE project_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  user_id INT NOT NULL,
  role_in_project VARCHAR(50) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_project_user (project_id, user_id)
) ENGINE=InnoDB;

-- backlogs: assignment to a developer
ALTER TABLE backlogs
  ADD COLUMN assigned_to INT NULL AFTER created_by,
  ADD CONSTRAINT fk_backlogs_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id);

-- sprint_items: assignment to a developer
ALTER TABLE sprint_items
  ADD COLUMN assigned_to INT NULL AFTER backlog_id,
  ADD CONSTRAINT fk_sprint_items_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id);

-- prototypes: direct project link (sprint becomes optional), version/demo fields
ALTER TABLE prototypes
  ADD COLUMN project_id INT NULL AFTER sprint_id;

UPDATE prototypes p
  JOIN sprints s ON s.id = p.sprint_id
  SET p.project_id = s.project_id
  WHERE p.project_id IS NULL;

ALTER TABLE prototypes
  MODIFY COLUMN sprint_id INT NULL,
  MODIFY COLUMN project_id INT NOT NULL,
  ADD CONSTRAINT fk_prototypes_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  ADD COLUMN version_number VARCHAR(50) NULL AFTER version_label,
  ADD COLUMN demo_url VARCHAR(255) NULL AFTER version_number;

-- feedback: assignment to a developer + implementation tracking
ALTER TABLE feedback
  ADD COLUMN assigned_to INT NULL AFTER submitted_by,
  ADD COLUMN implemented_at TIMESTAMP NULL AFTER assigned_to,
  ADD CONSTRAINT fk_feedback_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id);

-- tests: author of the test scenario (separate from whoever last executed it),
-- plus Regression/UAT test types and Retest result required by the Tester workspace
ALTER TABLE tests
  ADD COLUMN created_by INT NULL AFTER project_id,
  MODIFY COLUMN type ENUM('Functional','Usability','Security','Performance','Regression','UAT') DEFAULT 'Functional',
  MODIFY COLUMN result ENUM('Pass','Fail','Not Run','Retest') DEFAULT 'Not Run',
  ADD COLUMN notes TEXT NULL AFTER result,
  ADD CONSTRAINT fk_tests_created_by FOREIGN KEY (created_by) REFERENCES users(id);

-- defects: assignment to a developer + tester verification step
ALTER TABLE defects
  ADD COLUMN assigned_to INT NULL AFTER reported_by,
  ADD COLUMN verified_at TIMESTAMP NULL AFTER assigned_to,
  MODIFY COLUMN status ENUM('Open','Fixed','Verified','Closed') DEFAULT 'Open',
  ADD CONSTRAINT fk_defects_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id);

-- evaluations: evaluator ratings/feedback tied to a specific prototype
CREATE TABLE evaluations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  prototype_id INT NOT NULL,
  evaluator_id INT NOT NULL,
  rating TINYINT NOT NULL,
  usability_feedback TEXT,
  functional_feedback TEXT,
  design_feedback TEXT,
  performance_feedback TEXT,
  suggestion TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (prototype_id) REFERENCES prototypes(id) ON DELETE CASCADE,
  FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
