-- Adds assignment audit trail + soft-revoke capability to project_members,
-- used by Guard::isMember() to enforce active-membership-only project access.
ALTER TABLE project_members
  ADD COLUMN assigned_by INT NULL AFTER user_id,
  ADD COLUMN status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active' AFTER role_in_project;
