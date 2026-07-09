-- Enables manually-created work items (To Do / Add Work Item) alongside the existing
-- sync-from-artifact rows. Manually created items have no backing artifact row, so
-- artifact_id becomes nullable (MySQL allows multiple NULLs in a UNIQUE KEY, so the
-- existing UNIQUE(artifact_type, artifact_id) sync-dedup guarantee for synced rows is
-- unaffected). artifact_type/status enums are widened to the values the EPA Workboard
-- create-work-item form needs; no other tables/columns touched.

ALTER TABLE work_items MODIFY artifact_id INT NULL;

ALTER TABLE work_items MODIFY artifact_type ENUM(
  'requirement','backlog','sprint_item','prototype_task','feedback_implementation',
  'test_case','defect','release_task','retrospective_action','evaluation_task','app_requirement'
) NOT NULL;

ALTER TABLE work_items MODIFY status ENUM(
  'To Do','In Progress','Review','Ready for Testing','Testing','Done','Deferred','Blocked'
) NOT NULL DEFAULT 'To Do';
