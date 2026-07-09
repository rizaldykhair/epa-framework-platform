-- Supports "Continue Existing Project with AI Generator" / "Generate Prototype for
-- Existing Project": lets Admin/PO complete EPA artifacts (backlog, sprint, prototype,
-- test cases, evaluation form) for projects that were created manually, before the
-- AI Generator existed, and only have a name/basic info.
ALTER TABLE projects
  ADD COLUMN epa_completion_status VARCHAR(50) NULL DEFAULT NULL AFTER status,
  ADD COLUMN generated_by_ai TINYINT(1) NOT NULL DEFAULT 0 AFTER epa_completion_status,
  ADD COLUMN continued_by_ai TINYINT(1) NOT NULL DEFAULT 0 AFTER generated_by_ai;

-- Reuses the existing source_type field instead of adding a parallel boolean column.
ALTER TABLE prototypes
  MODIFY COLUMN source_type ENUM('manually_registered','ai_generated','improved_from_feedback','continued_from_existing_project') NOT NULL DEFAULT 'manually_registered';

-- Continuation requests reuse ai_generation_requests/generated_artifacts as their own
-- log (generated_project_id already points at the pre-existing project), so an artifact
-- can now be created before the parent request row is fully populated.
ALTER TABLE generated_artifacts
  MODIFY COLUMN generation_request_id INT NULL;
