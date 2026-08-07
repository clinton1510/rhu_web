-- Store structured obstetric history submitted by the prenatal maternal form.
ALTER TABLE pregnancies
    ADD COLUMN IF NOT EXISTS gravida INT UNSIGNED NOT NULL DEFAULT 1 AFTER resident_id,
    ADD COLUMN IF NOT EXISTS para INT UNSIGNED NOT NULL DEFAULT 0 AFTER gravida;

