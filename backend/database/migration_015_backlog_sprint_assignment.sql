-- Semi-automatic Adaptive Backlog -> Sprint Backlog assignment (Product Owner controlled,
-- not auto-move). sprint_items already exists and serves as the sprint backlog table -
-- reused as-is, no new table. Only additive changes: two new backlog status gate/result
-- values, and target_sprint_id so a backlog row remembers which sprint it was assigned to.

ALTER TABLE backlogs
  MODIFY status ENUM('Draft','Approved','Rejected','Revised','Ready for Sprint','In Sprint','To Do','In Progress','Done','Deferred') DEFAULT 'To Do';

ALTER TABLE backlogs
  ADD COLUMN target_sprint_id INT NULL AFTER target_prototype_version,
  ADD CONSTRAINT fk_backlogs_target_sprint FOREIGN KEY (target_sprint_id) REFERENCES sprints(id) ON DELETE SET NULL;
