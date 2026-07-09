-- EPA Workboard core module: work_items is a thin sync/mapping layer over existing
-- artifact tables (backlogs, sprint_items, prototypes, feedback_implementation, tests,
-- defects, release_checklists) - not a duplicate data store. UNIQUE(artifact_type,
-- artifact_id) lets sync use INSERT ... ON DUPLICATE KEY UPDATE safely (idempotent).

CREATE TABLE IF NOT EXISTS work_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  epa_phase VARCHAR(100) NULL,
  epa_step VARCHAR(40) NULL,
  artifact_type ENUM('backlog','sprint_item','prototype_task','feedback_implementation','test_case','defect','release_task') NOT NULL,
  artifact_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('To Do','In Progress','Review','Ready for Testing','Testing','Done','Deferred') NOT NULL DEFAULT 'To Do',
  priority ENUM('Must','Should','Could','Wont') NULL,
  assignee_id INT NULL,
  source_type VARCHAR(40) NULL,
  source_id INT NULL,
  sprint_id INT NULL,
  prototype_id INT NULL,
  due_date DATE NULL,
  created_by INT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_artifact (artifact_type, artifact_id),
  KEY idx_project (project_id),
  KEY idx_status (status),
  KEY idx_assignee (assignee_id),
  CONSTRAINT fk_work_items_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS work_item_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_item_id INT NOT NULL,
  user_id INT NOT NULL,
  comment_text TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wic_work_item FOREIGN KEY (work_item_id) REFERENCES work_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS work_item_activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_item_id INT NOT NULL,
  user_id INT NULL,
  action_type VARCHAR(60) NOT NULL,
  old_value VARCHAR(255) NULL,
  new_value VARCHAR(255) NULL,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wial_work_item FOREIGN KEY (work_item_id) REFERENCES work_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
