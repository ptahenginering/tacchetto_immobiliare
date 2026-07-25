-- Throttling generico per endpoint pubblici (lead form, chatbot, ecc.)
CREATE TABLE IF NOT EXISTS request_throttle (
    id BIGSERIAL PRIMARY KEY,
    ip VARCHAR(64) NOT NULL,
    bucket VARCHAR(50) NOT NULL,
    requested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_request_throttle_lookup ON request_throttle(bucket, ip, requested_at);
