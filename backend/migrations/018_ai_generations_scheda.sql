-- 018: aggiunge 'scheda' (scheda di presentazione immobile) ai kind AI ammessi
ALTER TABLE ai_generations DROP CONSTRAINT IF EXISTS ai_generations_kind_check;
ALTER TABLE ai_generations ADD CONSTRAINT ai_generations_kind_check
    CHECK (kind IN ('annuncio', 'post_social', 'descrizione', 'email', 'scheda'));
