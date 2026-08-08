-- 017: aggiunge 'perizia' ai tipi di richiesta ammessi sui lead
ALTER TABLE leads DROP CONSTRAINT IF EXISTS leads_request_type_check;
ALTER TABLE leads ADD CONSTRAINT leads_request_type_check
    CHECK (request_type IN ('vendere', 'ereditato', 'perizia', 'altro'));
