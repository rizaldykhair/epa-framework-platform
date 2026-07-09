-- Deprioritizes native mobile scaffold output in favor of a single responsive web
-- prototype with an adaptive desktop/tablet/mobile preview.html wrapper. Widens the
-- output_type enums (keeping old values so already-generated rows stay valid) - no new
-- columns needed, since "Preview URL"/"Mobile Preview Link" reuse the existing
-- mobile_preview_url/mobile_build_url columns already added in migration_013.

ALTER TABLE prototypes
  MODIFY output_type ENUM(
    'web_app','mobile_web_app','hybrid_web_mobile','mobile_scaffold',
    'adaptive_web_preview','mobile_first_web','hybrid_dashboard_mobile_preview'
  ) NULL;

ALTER TABLE ai_generation_requests
  MODIFY output_type ENUM(
    'web_app','mobile_web_app','hybrid_web_mobile','mobile_scaffold',
    'adaptive_web_preview','mobile_first_web','hybrid_dashboard_mobile_preview'
  ) NULL;
