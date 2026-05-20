<?php

return [
    'reward_distribution' => [
        1 => (float) env('SPORTS_REWARD_FIRST_PLACE_SHARE', 0.5),
        2 => (float) env('SPORTS_REWARD_SECOND_PLACE_SHARE', 0.3),
        3 => (float) env('SPORTS_REWARD_THIRD_PLACE_SHARE', 0.2),
    ],
    'leaderboard_limit' => (int) env('SPORTS_LEADERBOARD_LIMIT', 25),
    'manual_review_score_threshold' => env('SPORTS_MANUAL_REVIEW_SCORE_THRESHOLD', '95'),
    'max_reward_per_competition' => env('SPORTS_MAX_REWARD_PER_COMPETITION', '10000'),
];
