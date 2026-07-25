-- Proposte d'acquisto
CREATE TABLE IF NOT EXISTS proposals (
    id BIGSERIAL PRIMARY KEY,
    agency_id BIGINT NOT NULL REFERENCES agencies(id),
    property_id BIGINT NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
    amount NUMERIC(12,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ricevuta' CHECK (status IN ('ricevuta', 'in_trattativa', 'accettata', 'rifiutata', 'ritirata')),
    received_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    notes TEXT,
    visible_to_owner BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_proposals_agency ON proposals(agency_id);
CREATE INDEX IF NOT EXISTS idx_proposals_property ON proposals(property_id);
CREATE INDEX IF NOT EXISTS idx_proposals_status ON proposals(status);
CREATE INDEX IF NOT EXISTS idx_proposals_received ON proposals(received_at);
