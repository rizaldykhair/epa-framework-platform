USE epa_framework;

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

ALTER TABLE prototypes
  ADD COLUMN generated_by_ai TINYINT(1) NOT NULL DEFAULT 0 AFTER build_output_url,
  ADD COLUMN generation_request_id INT NULL AFTER generated_by_ai,
  ADD COLUMN output_path VARCHAR(255) NULL AFTER generation_request_id,
  ADD COLUMN source_type ENUM('manually_registered','ai_generated','improved_from_feedback') DEFAULT 'manually_registered' AFTER output_path,
  ADD CONSTRAINT fk_prototypes_generation_request FOREIGN KEY (generation_request_id) REFERENCES ai_generation_requests(id) ON DELETE SET NULL;
