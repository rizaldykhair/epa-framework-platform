-- Adds only the traceability/versioning columns actually missing from the schema
-- (Steps 1-5 of the EPA compliance pass: schema only, additive, no data loss).
-- The logic that POPULATES these columns (feedback decision -> backlog auto-creation,
-- prototype version chaining, testing/release gates, retrospective->backlog loop) is
-- explicitly deferred to a later pass (Steps 6-11), per instruction to do Steps 1-5 only.
--
-- Columns intentionally NOT added because an existing column/table already covers the
-- same information (avoiding redundant near-duplicates):
--   feedback.decision_reason   -> already covered by feedback.reason
--   feedback.implementation_status -> already tracked per-attempt in feedback_implementation.status
--   prototypes.improved_from_feedback -> already covered by prototypes.source_type = 'improved_from_feedback'
--   retrospectives.action_items -> already covered by retrospectives.action_item

ALTER TABLE backlogs
  ADD COLUMN source_type ENUM('initial_requirement','evaluator_feedback','defect','retrospective','ai_generated','manual_product_owner')
    NOT NULL DEFAULT 'manual_product_owner' AFTER source_feedback_id,
  ADD COLUMN source_id INT NULL AFTER source_type,
  ADD COLUMN source_defect_id INT NULL AFTER source_id,
  ADD COLUMN source_retrospective_id INT NULL AFTER source_defect_id,
  ADD COLUMN created_from_epa_step VARCHAR(40) NULL AFTER source_retrospective_id,
  ADD COLUMN target_prototype_version VARCHAR(50) NULL AFTER created_from_epa_step,
  ADD COLUMN decision_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' AFTER target_prototype_version;

ALTER TABLE feedback
  ADD COLUMN decision_by INT NULL AFTER decision,
  ADD COLUMN converted_backlog_id INT NULL AFTER decision_by,
  ADD COLUMN implemented_in_prototype_id INT NULL AFTER converted_backlog_id,
  ADD COLUMN implemented_in_version VARCHAR(50) NULL AFTER implemented_in_prototype_id;

ALTER TABLE prototypes
  ADD COLUMN previous_prototype_id INT NULL AFTER backlog_id,
  ADD COLUMN implemented_feedback_ids TEXT NULL AFTER implemented_feedback_summary,
  ADD COLUMN implemented_backlog_ids TEXT NULL AFTER implemented_feedback_ids,
  ADD COLUMN fixed_defect_ids TEXT NULL AFTER implemented_backlog_ids;

ALTER TABLE tests
  ADD COLUMN is_required_for_release TINYINT(1) NOT NULL DEFAULT 1 AFTER result;

ALTER TABLE defects
  ADD COLUMN verified_by INT NULL AFTER assigned_to;

ALTER TABLE retrospectives
  ADD COLUMN create_improvement_backlog TINYINT(1) NOT NULL DEFAULT 0 AFTER action_item,
  ADD COLUMN generated_backlog_id INT NULL AFTER create_improvement_backlog,
  ADD COLUMN next_iteration_recommendation TEXT NULL AFTER generated_backlog_id;
