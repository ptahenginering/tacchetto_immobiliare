-- Log di ogni invio email (o mancato invio)
CREATE TABLE IF NOT EXISTS email_log (
    id BIGSERIAL PRIMARY KEY,
    agency_id BIGINT NOT NULL REFERENCES agencies(id),
    to_email VARCHAR(255) NOT NULL,
    template_key VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'inviata' CHECK (status IN ('inviata', 'errore', 'disattivata')),
    error_text TEXT,
    related_type VARCHAR(50),
    related_id BIGINT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_email_log_agency ON email_log(agency_id);
CREATE INDEX IF NOT EXISTS idx_email_log_to ON email_log(to_email);
CREATE INDEX IF NOT EXISTS idx_email_log_template ON email_log(template_key);
CREATE INDEX IF NOT EXISTS idx_email_log_created ON email_log(created_at);
