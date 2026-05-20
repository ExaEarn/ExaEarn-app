<?php

return [
    'certificate_verify_url' => rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/certificate/verify',
    'reward_activity' => 'education_completion',
    'require_email_verification' => env('EDTECH_REQUIRE_EMAIL_VERIFICATION', true),
    'minimum_watch_ratio' => (float) env('EDTECH_MINIMUM_WATCH_RATIO', 0.7),
    'quiz_attempt_window_minutes' => (int) env('EDTECH_QUIZ_ATTEMPT_WINDOW_MINUTES', 30),
];
