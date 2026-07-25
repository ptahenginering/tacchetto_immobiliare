-- Lead dal sito vetrina, QR, social, referral
CREATE TABLE IF NOT EXISTS leads (
    id BIGSERIAL PRIMARY KEY,
    agency_id BIGINT NOT NULL REFERENCES agencies(id),
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    request_type VARCHAR(20) NOT NULL DEFAULT 'vendere' CHECK (request_type IN ('vendere', 'ereditato', 'altro')),
    message TEXT,
    source VARCHAR(20) NOT NULL DEFAULT 'sito' CHECK (source IN ('sito', 'qr', 'social', 'referral', 'altro')),
    status VARCHAR(20) NOT NULL DEFAULT 'nuovo' CHECK (status IN ('nuovo', 'contattato', 'appuntamento', 'incarico', 'perso')),
    assigned_to BIGINT REFERENCES users(id) ON DELETE SET NULL,
    notes TEXT,
    converted_property_id BIGINT,
    lost_reason TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_leads_agency ON leads(agency_id);
CREATE INDEX IF NOT EXISTS idx_leads_status ON leads(status);
CREATE INDEX IF NOT EXISTS idx_leads_source ON leads(source);
CREATE INDEX IF NOT EXISTS idx_leads_assigned ON leads(assigned_to);
CREATE INDEX IF NOT EXISTS idx_leads_created ON leads(created_at);
CREATE INDEX IF NOT EXISTS idx_leads_email ON leads(email);
