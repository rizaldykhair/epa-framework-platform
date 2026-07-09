USE epa_framework;

ALTER TABLE projects
  MODIFY COLUMN project_type ENUM('Web App','Mobile App','Web + Mobile','Web-Based Application') DEFAULT 'Web App';
