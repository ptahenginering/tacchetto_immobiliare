-- Immobili in gestione
CREATE TABLE IF NOT EXISTS properties (
    id BIGSERIAL PRIMARY KEY,
    agency_id BIGINT NOT NULL REFERENCES agencies(id),
    owner_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    address VARCHAR(255),
    city VARCHAR(100),
    province VARCHAR(10),
    type VARCHAR(20) NOT NULL DEFAULT 'appartamento' CHECK (type IN ('appartamento', 'casa', 'villa', 'terreno', 'commerciale', 'altro')),
    surface_sqm INTEGER,
    rooms INTEGER,
    price NUMERIC(12,2),
    status VARCHAR(20) NOT NULL DEFAULT 'valutazione' CHECK (status IN ('valutazione', 'in_vendita', 'in_trattativa', 'venduto', 'sospeso')),
    cover_image_url VARCHAR(500),
    description TEXT,
    mandate_start DATE,
    mandate_end DATE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_properties_agency ON properties(agency_id);
CREATE INDEX IF NOT EXISTS idx_properties_owner ON properties(owner_user_id);
CREATE INDEX IF NOT EXISTS idx_properties_status ON properties(status);
CREATE INDEX IF NOT EXISTS idx_properties_city ON properties(city);

-- FK differita dal lead convertito all'immobile
ALTER TABLE leads DROP CONSTRAINT IF EXISTS fk_leads_converted_property;
ALTER TABLE leads
    ADD CONSTRAINT fk_leads_converted_property
    FOREIGN KEY (converted_property_id) REFERENCES properties(id) ON DELETE SET NULL;

-- Immagini immobile
CREATE TABLE IF NOT EXISTS property_images (
    id BIGSERIAL PRIMARY KEY,
    agency_id BIGINT NOT NULL REFERENCES agencies(id),
    property_id BIGINT NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
    url VARCHAR(500) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_ai_generated BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_property_images_property ON property_images(property_id);
