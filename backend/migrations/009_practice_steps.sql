-- Template step burocrazia (seed alla creazione immobile)
CREATE TABLE IF NOT EXISTS practice_step_templates (
    id BIGSERIAL PRIMARY KEY,
    step_key VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(255) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    only_inherited BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO practice_step_templates (step_key, label, sort_order, only_inherited) VALUES
    ('documenti_catastali',        'Documenti catastali (visura e planimetria)', 10, false),
    ('ape',                        'Attestato di Prestazione Energetica (APE)',  20, false),
    ('conformita_urbanistica',     'Verifica conformità urbanistica',            30, false),
    ('atto_provenienza',           'Atto di provenienza',                        40, false),
    ('dichiarazione_successione',  'Dichiarazione di successione',               50, true),
    ('accettazione_eredita',       'Accettazione di eredità',                    60, true),
    ('volture',                    'Volture catastali',                          70, true),
    ('proposta_preliminare',       'Proposta d''acquisto / preliminare',         80, false),
    ('rogito',                     'Rogito notarile',                            90, false)
ON CONFLICT (step_key) DO NOTHING;

-- Checklist burocrazia per immobile
CREATE TABLE IF NOT EXISTS practice_steps (
    id BIGSERIAL PRIMARY KEY,
    agency_id BIGINT NOT NULL REFERENCES agencies(id),
    property_id BIGINT NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
    step_key VARCHAR(50) NOT NULL,
    label VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'da_fare' CHECK (status IN ('da_fare', 'in_corso', 'completato')),
    sort_order INTEGER NOT NULL DEFAULT 0,
    completed_at TIMESTAMPTZ,
    visible_to_owner BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (property_id, step_key)
);

CREATE INDEX IF NOT EXISTS idx_practice_steps_agency ON practice_steps(agency_id);
CREATE INDEX IF NOT EXISTS idx_practice_steps_property ON practice_steps(property_id);
CREATE INDEX IF NOT EXISTS idx_practice_steps_status ON practice_steps(status);
