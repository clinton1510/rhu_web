-- Stores predictions generated from live RHU source records.
-- Source data remains in consultations, disease_cases, disease_types,
-- residents, medicine_inventory, and stock_transactions.

CREATE TABLE IF NOT EXISTS health_predictions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    prediction_key VARCHAR(190) NOT NULL,
    prediction_type VARCHAR(50) NOT NULL,
    target_name VARCHAR(190) NOT NULL,
    source_tables VARCHAR(255) NOT NULL,
    forecast_start DATE NOT NULL,
    forecast_end DATE NOT NULL,
    predicted_value DECIMAL(12,2) NOT NULL,
    prediction_unit VARCHAR(40) NOT NULL,
    risk_level VARCHAR(30) NULL,
    confidence_percent DECIMAL(5,2) NULL,
    evidence_count INT UNSIGNED NOT NULL DEFAULT 0,
    basis_json JSON NULL,
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_health_prediction_key (prediction_key),
    KEY idx_health_prediction_type (prediction_type),
    KEY idx_health_prediction_period (forecast_start, forecast_end),
    KEY idx_health_prediction_target (target_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

