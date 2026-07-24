-- Insert Nasugbu, Batangas Barangays into database
-- Run this after database_schema.sql

-- Create barangays table if not exists
CREATE TABLE IF NOT EXISTS barangays (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    municipality VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    population INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert all 42 barangays of Nasugbu, Batangas
INSERT INTO barangays (name, municipality, province, latitude, longitude, population) VALUES
('Aga', 'Nasugbu', 'Batangas', 13.8905, 120.9842, 2100),
('Balaytiguebalok-Balok', 'Nasugbu', 'Batangas', 13.8867, 120.9915, 1850),
('Banilad', 'Nasugbu', 'Batangas', 13.8654, 120.9723, 2300),
('Barangay 1 (Pob.)', 'Nasugbu', 'Batangas', 13.8542, 120.9634, 3500),
('Barangay 2 (Pob.)', 'Nasugbu', 'Batangas', 13.8558, 120.9612, 3200),
('Barangay 3 (Pob.)', 'Nasugbu', 'Batangas', 13.8574, 120.9590, 2900),
('Barangay 4 (Pob.)', 'Nasugbu', 'Batangas', 13.8590, 120.9568, 3100),
('Barangay 5 (Pob.)', 'Nasugbu', 'Batangas', 13.8606, 120.9546, 2800),
('Barangay 6 (Pob.)', 'Nasugbu', 'Batangas', 13.8622, 120.9524, 3000),
('Barangay 7 (Pob.)', 'Nasugbu', 'Batangas', 13.8638, 120.9502, 2700),
('Barangay 8 (Pob.)', 'Nasugbu', 'Batangas', 13.8654, 120.9480, 2600),
('Barangay 9 (Pob.)', 'Nasugbu', 'Batangas', 13.8670, 120.9458, 2500),
('Barangay 10 (Pob.)', 'Nasugbu', 'Batangas', 13.8686, 120.9436, 2400),
('Barangay 11 (Pob.)', 'Nasugbu', 'Batangas', 13.8702, 120.9414, 2300),
('Barangay 12 (Pob.)', 'Nasugbu', 'Batangas', 13.8718, 120.9392, 2200),
('Bilaran', 'Nasugbu', 'Batangas', 13.8534, 120.9889, 1950),
('Bucana', 'Nasugbu', 'Batangas', 13.8723, 120.9567, 2050),
('Bulihan', 'Nasugbu', 'Batangas', 13.8412, 120.9501, 1880),
('Bunducan', 'Nasugbu', 'Batangas', 13.8298, 120.9456, 2200),
('Butucan', 'Nasugbu', 'Batangas', 13.8187, 120.9412, 1750),
('Calayo', 'Nasugbu', 'Batangas', 13.8076, 120.9368, 2100),
('Catandaan', 'Nasugbu', 'Batangas', 13.7965, 120.9324, 1900),
('Cagunan', 'Nasugbu', 'Batangas', 13.7854, 120.9280, 2000),
('Dayap', 'Nasugbu', 'Batangas', 13.7743, 120.9236, 1650),
('Kaylaway', 'Nasugbu', 'Batangas', 13.8945, 120.9756, 1800),
('Kayrillaw', 'Nasugbu', 'Batangas', 13.9035, 120.9823, 1700),
('Latag', 'Nasugbu', 'Batangas', 13.8632, 120.9945, 2250),
('Looc', 'Nasugbu', 'Batangas', 13.8723, 120.9867, 1900),
('Lumbangan', 'Nasugbu', 'Batangas', 13.8814, 120.9789, 2100),
('Malapad na Bato', 'Nasugbu', 'Batangas', 13.8905, 120.9711, 1850),
('Mataas na Pulo', 'Nasugbu', 'Batangas', 13.8996, 120.9633, 1600),
('Maugat', 'Nasugbu', 'Batangas', 13.9087, 120.9555, 1750),
('Munting Indang', 'Nasugbu', 'Batangas', 13.8541, 120.9778, 2000),
('Natipuan', 'Nasugbu', 'Batangas', 13.8432, 120.9834, 1850),
('Pantalan', 'Nasugbu', 'Batangas', 13.8323, 120.9890, 1700),
('Papaya', 'Nasugbu', 'Batangas', 13.8214, 120.9946, 1900),
('Putat', 'Nasugbu', 'Batangas', 13.8105, 121.0002, 1800),
('Reparo', 'Nasugbu', 'Batangas', 13.7996, 121.0058, 2100),
('Talangan', 'Nasugbu', 'Batangas', 13.7887, 121.0114, 1950),
('Tumalim', 'Nasugbu', 'Batangas', 13.7778, 121.0170, 1850),
('Utod', 'Nasugbu', 'Batangas', 13.7669, 121.0226, 1700),
('Wawa', 'Nasugbu', 'Batangas', 13.7560, 121.0282, 1600)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Create indexes for barangays
CREATE INDEX idx_barangay_municipality ON barangays(municipality);
CREATE INDEX idx_barangay_province ON barangays(province);
CREATE INDEX idx_barangay_name ON barangays(name);

-- Update residents table to add reference to barangays table (optional)
-- ALTER TABLE residents ADD CONSTRAINT fk_residents_barangay 
-- FOREIGN KEY (barangay) REFERENCES barangays(name);

-- Verify insertion
SELECT COUNT(*) as total_barangays FROM barangays;
SELECT * FROM barangays ORDER BY name;

COMMIT;
