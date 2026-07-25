-- Predisposizione fase social: account collegati e post programmati
CREATE TABLE IF NOT EXISTS social_accounts (
    id BIGSERIAL PRIMARY KEY,
    agency_id BIGINT NOT NULL REFERENCES agencies(id),
    channel VARCHAR(30) NOT NULL,
    account_name VARCHAR(150),
    credentials JSONB NOT NULL DEFAULT '{}'::jsonb,
    is_active BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_social_accounts_agency ON social_accounts(agency_id);

CREATE TABLE IF NOT EXISTS scheduled_posts (
    id BIGSERIAL PRIMARY KEY,
    agency_id BIGINT NOT NULL REFERENCES agencies(id),
    property_id BIGINT REFERENCES properties(id) ON DELETE CASCADE,
    channel VARCHAR(30) NOT NULL,
    content TEXT NOT NULL,
    image_url VARCHAR(500),
    scheduled_at TIMESTAMPTZ,
    status VARCHAR(20) NOT NULL DEFAULT 'bozza' CHECK (status IN ('bozza', 'programmato', 'pubblicato', 'errore')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_scheduled_posts_agency ON scheduled_posts(agency_id);
CREATE INDEX IF NOT EXISTS idx_scheduled_posts_property ON scheduled_posts(property_id);
CREATE INDEX IF NOT EXISTS idx_scheduled_posts_status ON scheduled_posts(status);
