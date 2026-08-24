# ExaEarn Phase 15E Cancel/Replace

Cancel/replace compares a new quote plan to active bot orders. Materially unchanged quotes are kept. Obsolete quotes are cancelled through the existing Spot or Futures OMS cancellation path.

The workflow supports price and size thresholds, idempotent bot order records, and mass-cancel drills without deleting orders directly.
