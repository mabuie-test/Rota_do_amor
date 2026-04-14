-- Seed incremental e idempotente para províncias e cidades de Moçambique.
-- Não altera estrutura; apenas garante cobertura mínima administrativa/urbana relevante.

INSERT INTO provinces (name)
SELECT 'Cabo Delgado' WHERE NOT EXISTS (SELECT 1 FROM provinces WHERE name = 'Cabo Delgado');
INSERT INTO provinces (name)
SELECT 'Gaza' WHERE NOT EXISTS (SELECT 1 FROM provinces WHERE name = 'Gaza');
INSERT INTO provinces (name)
SELECT 'Inhambane' WHERE NOT EXISTS (SELECT 1 FROM provinces WHERE name = 'Inhambane');
INSERT INTO provinces (name)
SELECT 'Manica' WHERE NOT EXISTS (SELECT 1 FROM provinces WHERE name = 'Manica');
INSERT INTO provinces (name)
SELECT 'Maputo Cidade' WHERE NOT EXISTS (SELECT 1 FROM provinces WHERE name = 'Maputo Cidade');
INSERT INTO provinces (name)
SELECT 'Maputo Província' WHERE NOT EXISTS (SELECT 1 FROM provinces WHERE name = 'Maputo Província');
INSERT INTO provinces (name)
SELECT 'Nampula' WHERE NOT EXISTS (SELECT 1 FROM provinces WHERE name = 'Nampula');
INSERT INTO provinces (name)
SELECT 'Niassa' WHERE NOT EXISTS (SELECT 1 FROM provinces WHERE name = 'Niassa');
INSERT INTO provinces (name)
SELECT 'Sofala' WHERE NOT EXISTS (SELECT 1 FROM provinces WHERE name = 'Sofala');
INSERT INTO provinces (name)
SELECT 'Tete' WHERE NOT EXISTS (SELECT 1 FROM provinces WHERE name = 'Tete');
INSERT INTO provinces (name)
SELECT 'Zambézia' WHERE NOT EXISTS (SELECT 1 FROM provinces WHERE name = 'Zambézia');

-- Maputo Cidade
INSERT INTO cities (province_id, name)
SELECT p.id, c.city_name
FROM provinces p
JOIN (
    SELECT 'Maputo' AS city_name
    UNION ALL SELECT 'KaMpfumo'
    UNION ALL SELECT 'KaMavota'
    UNION ALL SELECT 'KaMubukwana'
    UNION ALL SELECT 'KaTembe'
) c
LEFT JOIN cities existing ON existing.province_id = p.id AND existing.name = c.city_name
WHERE p.name = 'Maputo Cidade' AND existing.id IS NULL;

-- Maputo Província
INSERT INTO cities (province_id, name)
SELECT p.id, c.city_name
FROM provinces p
JOIN (
    SELECT 'Matola' AS city_name
    UNION ALL SELECT 'Boane'
    UNION ALL SELECT 'Marracuene'
    UNION ALL SELECT 'Moamba'
    UNION ALL SELECT 'Namaacha'
    UNION ALL SELECT 'Matutuíne'
    UNION ALL SELECT 'Manhiça'
    UNION ALL SELECT 'Magude'
) c
LEFT JOIN cities existing ON existing.province_id = p.id AND existing.name = c.city_name
WHERE p.name = 'Maputo Província' AND existing.id IS NULL;

-- Gaza
INSERT INTO cities (province_id, name)
SELECT p.id, c.city_name
FROM provinces p
JOIN (
    SELECT 'Xai-Xai' AS city_name
    UNION ALL SELECT 'Chókwè'
    UNION ALL SELECT 'Chibuto'
    UNION ALL SELECT 'Macia'
    UNION ALL SELECT 'Bilene'
) c
LEFT JOIN cities existing ON existing.province_id = p.id AND existing.name = c.city_name
WHERE p.name = 'Gaza' AND existing.id IS NULL;

-- Inhambane
INSERT INTO cities (province_id, name)
SELECT p.id, c.city_name
FROM provinces p
JOIN (
    SELECT 'Inhambane' AS city_name
    UNION ALL SELECT 'Maxixe'
    UNION ALL SELECT 'Vilankulo'
    UNION ALL SELECT 'Massinga'
    UNION ALL SELECT 'Jangamo'
) c
LEFT JOIN cities existing ON existing.province_id = p.id AND existing.name = c.city_name
WHERE p.name = 'Inhambane' AND existing.id IS NULL;

-- Sofala
INSERT INTO cities (province_id, name)
SELECT p.id, c.city_name
FROM provinces p
JOIN (
    SELECT 'Beira' AS city_name
    UNION ALL SELECT 'Dondo'
    UNION ALL SELECT 'Nhamatanda'
    UNION ALL SELECT 'Gorongosa'
    UNION ALL SELECT 'Buzi'
) c
LEFT JOIN cities existing ON existing.province_id = p.id AND existing.name = c.city_name
WHERE p.name = 'Sofala' AND existing.id IS NULL;

-- Manica
INSERT INTO cities (province_id, name)
SELECT p.id, c.city_name
FROM provinces p
JOIN (
    SELECT 'Chimoio' AS city_name
    UNION ALL SELECT 'Manica'
    UNION ALL SELECT 'Gondola'
    UNION ALL SELECT 'Sussundenga'
) c
LEFT JOIN cities existing ON existing.province_id = p.id AND existing.name = c.city_name
WHERE p.name = 'Manica' AND existing.id IS NULL;

-- Tete
INSERT INTO cities (province_id, name)
SELECT p.id, c.city_name
FROM provinces p
JOIN (
    SELECT 'Tete' AS city_name
    UNION ALL SELECT 'Moatize'
    UNION ALL SELECT 'Ulongué'
    UNION ALL SELECT 'Angónia'
) c
LEFT JOIN cities existing ON existing.province_id = p.id AND existing.name = c.city_name
WHERE p.name = 'Tete' AND existing.id IS NULL;

-- Zambézia
INSERT INTO cities (province_id, name)
SELECT p.id, c.city_name
FROM provinces p
JOIN (
    SELECT 'Quelimane' AS city_name
    UNION ALL SELECT 'Mocuba'
    UNION ALL SELECT 'Gurué'
    UNION ALL SELECT 'Milange'
    UNION ALL SELECT 'Mocubela'
) c
LEFT JOIN cities existing ON existing.province_id = p.id AND existing.name = c.city_name
WHERE p.name = 'Zambézia' AND existing.id IS NULL;

-- Nampula
INSERT INTO cities (province_id, name)
SELECT p.id, c.city_name
FROM provinces p
JOIN (
    SELECT 'Nampula' AS city_name
    UNION ALL SELECT 'Nacala'
    UNION ALL SELECT 'Ilha de Moçambique'
    UNION ALL SELECT 'Nacala-a-Velha'
    UNION ALL SELECT 'Monapo'
) c
LEFT JOIN cities existing ON existing.province_id = p.id AND existing.name = c.city_name
WHERE p.name = 'Nampula' AND existing.id IS NULL;

-- Cabo Delgado
INSERT INTO cities (province_id, name)
SELECT p.id, c.city_name
FROM provinces p
JOIN (
    SELECT 'Pemba' AS city_name
    UNION ALL SELECT 'Montepuez'
    UNION ALL SELECT 'Mocímboa da Praia'
    UNION ALL SELECT 'Mueda'
) c
LEFT JOIN cities existing ON existing.province_id = p.id AND existing.name = c.city_name
WHERE p.name = 'Cabo Delgado' AND existing.id IS NULL;

-- Niassa
INSERT INTO cities (province_id, name)
SELECT p.id, c.city_name
FROM provinces p
JOIN (
    SELECT 'Lichinga' AS city_name
    UNION ALL SELECT 'Cuamba'
    UNION ALL SELECT 'Mandimba'
    UNION ALL SELECT 'Marrupa'
) c
LEFT JOIN cities existing ON existing.province_id = p.id AND existing.name = c.city_name
WHERE p.name = 'Niassa' AND existing.id IS NULL;
