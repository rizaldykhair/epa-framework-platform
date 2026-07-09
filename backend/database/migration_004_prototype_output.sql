USE epa_framework;

ALTER TABLE prototypes
  ADD COLUMN implemented_feedback_summary TEXT NULL AFTER notes;
