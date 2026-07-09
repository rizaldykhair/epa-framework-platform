-- AI Prototype Output Generator v2: adds only the columns genuinely missing for
-- output_type (web/mobile/hybrid/scaffold), gallery/image_mode, and increment
-- traceability. Reuses implemented_feedback_ids/implemented_backlog_ids/fixed_defect_ids
-- (already on prototypes) instead of duplicating "generated_from_*_ids" columns, and
-- reuses ai_generation_requests.design_style as the ui_style concept (widened enum)
-- instead of adding a duplicate ui_style column.

ALTER TABLE prototypes
  ADD COLUMN output_type ENUM('web_app','mobile_web_app','hybrid_web_mobile','mobile_scaffold') NULL AFTER source_type,
  ADD COLUMN mobile_preview_url VARCHAR(255) NULL AFTER mobile_build_url,
  ADD COLUMN image_mode ENUM('online','offline','hybrid') NULL DEFAULT 'hybrid' AFTER output_type,
  ADD COLUMN ui_style VARCHAR(30) NULL AFTER image_mode;

ALTER TABLE ai_generation_requests
  MODIFY design_style ENUM('Simple','Modern','Corporate','Marketplace','Dashboard','Minimal','Mobile App') DEFAULT 'Modern',
  ADD COLUMN output_type ENUM('web_app','mobile_web_app','hybrid_web_mobile','mobile_scaffold') NULL AFTER layout_preference,
  ADD COLUMN image_mode ENUM('online','offline','hybrid') NULL DEFAULT 'hybrid' AFTER output_type,
  ADD COLUMN source_type VARCHAR(40) NULL AFTER image_mode,
  ADD COLUMN source_ids TEXT NULL AFTER source_type;
