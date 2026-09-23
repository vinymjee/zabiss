-- Zabiss v2 - Ecoles + dossiers JSON + admin
-- Clés primaires uniques :
--   eleve_dossiers : cle_unique = "{id_eleve}" ex: "12" (identité JSON)
--   notes_moyennes_dossiers / presence_dossiers / paiements_dossiers :
--     cle_unique = "{id_eleve}|{annee_scolaire}" ex: "12|2025-2026",
--     une ligne par élève et par année, données en JSON (donnees_json)

CREATE TABLE IF NOT EXISTS ecoles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  nom VARCHAR(200) NOT NULL,
  adresse TEXT,
  telephone VARCHAR(30),
  email VARCHAR(200),
  api_key VARCHAR(64) NOT NULL UNIQUE,
  actif TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  email VARCHAR(200) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(30) DEFAULT 'admin',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  token VARCHAR(128) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dossier ELEVE : 1 ligne par élève (cle_unique = id eleve), identité en JSON
CREATE TABLE IF NOT EXISTS eleve_dossiers (
  cle_unique VARCHAR(120) PRIMARY KEY,
  eleve_id INT NOT NULL,
  ecole_id INT NOT NULL,
  annee_scolaire VARCHAR(20) NOT NULL,
  donnees_json MEDIUMTEXT NOT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_eleve_dossiers_eleve (eleve_id),
  INDEX idx_eleve_dossiers_ecole (ecole_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dossier NOTES + MOYENNES : 1 ligne par élève et par année (cle_unique = id|annee), JSON = {notes:[...], moyennes:{...}}
CREATE TABLE IF NOT EXISTS notes_moyennes_dossiers (
  cle_unique VARCHAR(120) PRIMARY KEY,
  eleve_id INT NOT NULL,
  ecole_id INT NOT NULL,
  annee_scolaire VARCHAR(20) NOT NULL,
  donnees_json MEDIUMTEXT NOT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notes_dossiers_eleve (eleve_id),
  INDEX idx_notes_dossiers_ecole (ecole_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dossier PRESENCE : 1 ligne par élève et par année (cle_unique = id|annee), JSON = {lignes:[...], stats:{...}}
CREATE TABLE IF NOT EXISTS presence_dossiers (
  cle_unique VARCHAR(120) PRIMARY KEY,
  eleve_id INT NOT NULL,
  ecole_id INT NOT NULL,
  annee_scolaire VARCHAR(20) NOT NULL,
  donnees_json MEDIUMTEXT NOT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_presence_dossiers_eleve (eleve_id),
  INDEX idx_presence_dossiers_ecole (ecole_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dossier PAIEMENTS : 1 ligne par élève et par année (cle_unique = id|annee), JSON = {paiements:[...], stats:{...}}
CREATE TABLE IF NOT EXISTS paiements_dossiers (
  cle_unique VARCHAR(120) PRIMARY KEY,
  eleve_id INT NOT NULL,
  ecole_id INT NOT NULL,
  annee_scolaire VARCHAR(20) NOT NULL,
  donnees_json MEDIUMTEXT NOT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_paiements_dossiers_eleve (eleve_id),
  INDEX idx_paiements_dossiers_ecole (ecole_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Colonnes de liaison JSON sur tables existantes (idempotentes : ignorer erreur si déjà là)
-- MySQL 8 : pas de ADD COLUMN IF NOT EXISTS -> la migration PHP attrape l'exception
-- ALTER TABLE eleves ADD COLUMN ecole_id INT NULL;
-- ALTER TABLE eleves ADD COLUMN annee_scolaire VARCHAR(20) DEFAULT '2025-2026';
-- ALTER TABLE eleves ADD COLUMN cle_unique VARCHAR(120) NULL UNIQUE;
-- ALTER TABLE eleves ADD COLUMN donnees_json MEDIUMTEXT NULL;
-- ALTER TABLE infos_etablissement ADD COLUMN ecole_id INT NULL;
