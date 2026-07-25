-- Log generazioni AI (annunci, post social, descrizioni, email)
CREATE TABLE IF NOT EXISTS ai_generations (
    id BIGSERIAL PRIMARY KEY,
    agency_id BIGINT NOT NULL REFERENCES agencies(id),
    kind VARCHAR(20) NOT NULL CHECK (kind IN ('annuncio', 'post_social', 'descrizione', 'email')),
    property_id BIGINT REFERENCES properties(id) ON DELETE SET NULL,
    prompt TEXT NOT NULL,
    output TEXT NOT NULL,
    model VARCHAR(100) NOT NULL,
    accepted BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_ai_generations_agency ON ai_generations(agency_id);
CREATE INDEX IF NOT EXISTS idx_ai_generations_property ON ai_generations(property_id);
CREATE INDEX IF NOT EXISTS idx_ai_generations_kind ON ai_generations(kind);
