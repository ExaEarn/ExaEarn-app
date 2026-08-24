# ExaEarn Phase 2B Shadow Mode Report

Phase 2B adds a structured shadow-comparison record for comparing legacy spot behavior against the new spot engine.

## Implemented Components

- Migration table: `spot_shadow_comparisons`
- Model: `App\Models\SpotShadowComparison`
- Service: `App\Services\Spot\ShadowComparisonService`

## Classifications

- `MATCH`: legacy and new engine summaries agree.
- `EXPECTED_POLICY_DIFFERENCE`: the new engine rejects or changes behavior due to stricter policy.
- `UNRESOLVED`: output differs and requires review.

## Recommended Cutover Use

Before enabling a production market in authoritative mode:

1. Run the legacy engine as production authority.
2. Feed equivalent order intents into the new engine in isolated shadow mode.
3. Record comparisons.
4. Resolve every `UNRESOLVED` classification.
5. Enable market-by-market cutover only after zero unresolved differences remain for the observation window.

## Test Coverage

`Tests\Feature\Phase2BAuthorityTest::test_shadow_comparison_records_match_and_policy_difference` verifies match and expected-policy-difference classification.

