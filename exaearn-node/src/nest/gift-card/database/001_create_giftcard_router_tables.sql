CREATE TABLE IF NOT EXISTS giftcard_providers (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name varchar(40) NOT NULL UNIQUE,
  enabled boolean NOT NULL DEFAULT true,
  priority integer NOT NULL DEFAULT 100,
  success_rate_bps integer NOT NULL DEFAULT 9800,
  avg_response_time_ms integer NOT NULL DEFAULT 1000,
  max_latency_ms integer NOT NULL DEFAULT 3000,
  supports_purchase boolean NOT NULL DEFAULT true,
  supports_sell boolean NOT NULL DEFAULT true,
  metadata jsonb,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS giftcard_transactions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  idempotency_key varchar(80) NOT NULL UNIQUE,
  user_id varchar(80) NOT NULL,
  operation varchar(16) NOT NULL CHECK (operation IN ('purchase', 'sell')),
  brand varchar(80) NOT NULL,
  currency varchar(8) NOT NULL,
  card_value numeric(36, 18) NOT NULL,
  user_charge numeric(36, 18) NOT NULL DEFAULT 0,
  provider_cost numeric(36, 18) NOT NULL DEFAULT 0,
  user_payout numeric(36, 18) NOT NULL DEFAULT 0,
  profit numeric(36, 18) NOT NULL DEFAULT 0,
  provider varchar(40),
  provider_reference varchar(120),
  status varchar(32) NOT NULL DEFAULT 'pending',
  failure_reason text,
  metadata jsonb,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS giftcard_transactions_user_idx ON giftcard_transactions (user_id);
CREATE INDEX IF NOT EXISTS giftcard_transactions_provider_idx ON giftcard_transactions (provider);
CREATE INDEX IF NOT EXISTS giftcard_transactions_status_idx ON giftcard_transactions (status);

CREATE TABLE IF NOT EXISTS provider_logs (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  provider varchar(40) NOT NULL,
  transaction_id uuid,
  operation varchar(32) NOT NULL,
  success boolean NOT NULL DEFAULT false,
  response_time_ms integer NOT NULL,
  provider_cost numeric(36, 18) NOT NULL DEFAULT 0,
  profit numeric(36, 18) NOT NULL DEFAULT 0,
  error text,
  metadata jsonb,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS provider_logs_provider_idx ON provider_logs (provider);
CREATE INDEX IF NOT EXISTS provider_logs_transaction_idx ON provider_logs (transaction_id);

CREATE TABLE IF NOT EXISTS rates_cache (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  cache_key varchar(180) NOT NULL UNIQUE,
  provider varchar(40) NOT NULL,
  brand varchar(80) NOT NULL,
  currency varchar(8) NOT NULL,
  amount numeric(36, 18) NOT NULL,
  provider_cost numeric(36, 18) NOT NULL,
  provider_payout numeric(36, 18),
  payload jsonb,
  expires_at timestamptz NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS rates_cache_expires_idx ON rates_cache (expires_at);

INSERT INTO giftcard_providers (name, priority, success_rate_bps, avg_response_time_ms, max_latency_ms)
VALUES
  ('reloadly', 10, 9900, 800, 2500),
  ('gifq', 20, 9800, 1000, 3000),
  ('runa', 30, 9700, 1200, 3500)
ON CONFLICT (name) DO NOTHING;
