<?php

/**
 * AI Project Generator configuration.
 * No API key is ever hardcoded here — everything comes from environment (.env).
 * When AI_ENABLED is false or AI_API_KEY is missing, the generator always
 * falls back to Mode 1 (local template-based generation).
 */

return [
    'enabled' => filter_var(getenv('AI_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'provider' => getenv('AI_PROVIDER') ?: 'local',
    'api_key' => getenv('AI_API_KEY') ?: null,
    'model' => getenv('AI_MODEL') ?: null,
    'fallback_to_template' => filter_var(getenv('FALLBACK_TO_TEMPLATE') ?: 'true', FILTER_VALIDATE_BOOLEAN),
];
