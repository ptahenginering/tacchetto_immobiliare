-- Visite effettuate + feedback (visitor_label: MAI dati personali completi verso il proprietario)
CREATE TABLE IF NOT EXISTS visits (
    id BIGSERIAL PRIMARY KEY,
    agency_id BIGINT NOT NULL REFERENCES agencies(id),
    property_id BIGINT NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
    appointment_id BIGINT REFERENCES appointments(id) ON DELETE SET NULL,
    visited_at TIMESTAMPTZ NOT NULL,
    visitor_label VARCHAR(150) NOT NULL,
    qualified BOOLEAN NOT NULL DEFAULT true,
    feedback_text TEXT,
    feedback_rating SMALLINT CHECK (feedback_rating BETWEEN 1 AND 5),
    visible_to_owner BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_visits_agency ON visits(agency_id);
CREATE INDEX IF NOT EXISTS idx_visits_property ON visits(property_id);
CREATE INDEX IF NOT EXISTS idx_visits_visited ON visits(visited_at);
CREATE INDEX IF NOT EXISTS idx_visits_visible ON visits(visible_to_owner);
