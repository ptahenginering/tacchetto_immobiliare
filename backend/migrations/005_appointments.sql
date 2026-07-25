-- Appuntamenti (valutazioni, visite, firme)
CREATE TABLE IF NOT EXISTS appointments (
    id BIGSERIAL PRIMARY KEY,
    agency_id BIGINT NOT NULL REFERENCES agencies(id),
    property_id BIGINT REFERENCES properties(id) ON DELETE SET NULL,
    lead_id BIGINT REFERENCES leads(id) ON DELETE SET NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'visita' CHECK (type IN ('valutazione', 'visita', 'firma', 'altro')),
    starts_at TIMESTAMPTZ NOT NULL,
    ends_at TIMESTAMPTZ,
    contact_name VARCHAR(150),
    contact_phone VARCHAR(50),
    status VARCHAR(20) NOT NULL DEFAULT 'programmato' CHECK (status IN ('programmato', 'svolto', 'annullato')),
    notes TEXT,
    reminder_sent_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_appointments_agency ON appointments(agency_id);
CREATE INDEX IF NOT EXISTS idx_appointments_property ON appointments(property_id);
CREATE INDEX IF NOT EXISTS idx_appointments_lead ON appointments(lead_id);
CREATE INDEX IF NOT EXISTS idx_appointments_starts ON appointments(starts_at);
CREATE INDEX IF NOT EXISTS idx_appointments_status ON appointments(status);
