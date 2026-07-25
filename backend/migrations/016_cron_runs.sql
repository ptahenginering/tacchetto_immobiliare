-- Esecuzioni del cron (idempotenza dei job)
CREATE TABLE IF NOT EXISTS cron_runs (
    id BIGSERIAL PRIMARY KEY,
    job_key VARCHAR(100) NOT NULL UNIQUE,
    ran_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_cron_runs_ran ON cron_runs(ran_at);
