-- Attività di promozione dell'immobile ("dove viene promossa la tua casa")
CREATE TABLE IF NOT EXISTS marketing_activities (
    id BIGSERIAL PRIMARY KEY,
    agency_id BIGINT NOT NULL REFERENCES agencies(id),
    property_id BIGINT NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
    channel VARCHAR(30) NOT NULL CHECK (channel IN ('immobiliare_it', 'idealista', 'facebook', 'instagram', 'linkedin', 'vetrina', 'portale_tecnocasa', 'altro')),
    activity_type VARCHAR(20) NOT NULL DEFAULT 'pubblicazione' CHECK (activity_type IN ('pubblicazione', 'campagna', 'post', 'open_house', 'altro')),
    title VARCHAR(255) NOT NULL,
    url VARCHAR(500),
    published_at TIMESTAMPTZ,
    stats JSONB NOT NULL DEFAULT '{}'::jsonb,
    visible_to_owner BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_marketing_agency ON marketing_activities(agency_id);
CREATE INDEX IF NOT EXISTS idx_marketing_property ON marketing_activities(property_id);
CREATE INDEX IF NOT EXISTS idx_marketing_channel ON marketing_activities(channel);
CREATE INDEX IF NOT EXISTS idx_marketing_published ON marketing_activities(published_at);
