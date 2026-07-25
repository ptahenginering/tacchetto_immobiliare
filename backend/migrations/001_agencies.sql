-- Agenzie (single-tenant oggi, multi-tenant ready)
CREATE TABLE IF NOT EXISTS agencies (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    logo_url VARCHAR(500),
    primary_color VARCHAR(20) DEFAULT '#C29B52',
    settings JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Seed agenzia RT (id fisso 1, idempotente)
INSERT INTO agencies (id, name, email, phone)
VALUES (1, 'RT — Roberto Tacchetto Real Estate Advisor', 'info@rtimmobiliare.it', '+39 345 7771822')
ON CONFLICT (id) DO NOTHING;

SELECT setval('agencies_id_seq', GREATEST((SELECT COALESCE(MAX(id), 1) FROM agencies), 1));
