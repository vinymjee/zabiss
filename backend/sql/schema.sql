-- Zabiss - Schéma MySQL 8 / MariaDB
-- Compatible SQLite pour dev local (types génériques)

CREATE TABLE IF NOT EXISTS parents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(100) NOT NULL,
  email VARCHAR(200) NOT NULL UNIQUE,
  telephone VARCHAR(30),
  password_hash VARCHAR(255) NOT NULL,
  statut ENUM('en_attente','valide','rejete') DEFAULT 'valide',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS etablissements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(200) NOT NULL,
  adresse TEXT,
  telephone VARCHAR(30),
  email VARCHAR(200)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  etablissement_id INT,
  nom VARCHAR(100) NOT NULL, -- ex: 6e A, Terminale C
  niveau VARCHAR(50),
  annee_scolaire VARCHAR(20) DEFAULT '2025-2026',
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS eleves (
  id INT AUTO_INCREMENT PRIMARY KEY,
  matricule VARCHAR(50) NOT NULL UNIQUE,
  login VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(100) NOT NULL,
  date_naissance DATE,
  sexe ENUM('M','F') DEFAULT 'M',
  classe_id INT,
  etablissement_id INT,
  photo_url VARCHAR(500),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE SET NULL,
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS parent_eleve (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT NOT NULL,
  eleve_id INT NOT NULL,
  lien VARCHAR(50) DEFAULT 'parent', -- pere, mere, tuteur
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_parent_eleve (parent_id, eleve_id),
  FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
  FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT NOT NULL,
  token VARCHAR(128) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS matieres (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  code VARCHAR(20) UNIQUE,
  coefficient INT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  eleve_id INT NOT NULL,
  matiere_id INT NOT NULL,
  periode VARCHAR(20) NOT NULL, -- T1, T2, T3, Annuel
  type_eval VARCHAR(50) DEFAULT 'devoir', -- devoir, composition, interro
  note DECIMAL(5,2) NOT NULL, -- sur 20
  note_sur DECIMAL(5,2) DEFAULT 20,
  coefficient DECIMAL(4,2) DEFAULT 1,
  date_eval DATE,
  annee_scolaire VARCHAR(20) DEFAULT '2025-2026',
  commentaire TEXT,
  FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
  FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS presences (
  id INT AUTO_INCREMENT PRIMARY KEY,
  eleve_id INT NOT NULL,
  date_jour DATE NOT NULL,
  statut ENUM('present','absent','retard','exclu') NOT NULL DEFAULT 'present',
  motif TEXT,
  justifie TINYINT(1) DEFAULT 0,
  heure_arrivee TIME NULL,
  FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS paiements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  eleve_id INT NOT NULL,
  parent_id INT,
  montant DECIMAL(10,2) NOT NULL,
  montant_paye DECIMAL(10,2) DEFAULT 0,
  type_paiement VARCHAR(100) NOT NULL, -- scolarite, inscription, cantine, uniforme
  statut ENUM('paye','partiel','impaye','en_attente') DEFAULT 'en_attente',
  date_echeance DATE,
  date_paiement DATETIME,
  reference VARCHAR(100),
  mode_paiement VARCHAR(50), -- espece, mobile_money, virement
  annee_scolaire VARCHAR(20) DEFAULT '2025-2026',
  FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS infos_etablissement (
  id INT AUTO_INCREMENT PRIMARY KEY,
  etablissement_id INT,
  titre VARCHAR(255) NOT NULL,
  contenu TEXT NOT NULL,
  type_info VARCHAR(50) DEFAULT 'general', -- general, evenement, urgence, reunion
  date_publication DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_evenement DATE NULL,
  important TINYINT(1) DEFAULT 0,
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index perf
CREATE INDEX idx_notes_eleve ON notes(eleve_id);
CREATE INDEX idx_presences_eleve ON presences(eleve_id);
CREATE INDEX idx_paiements_eleve ON paiements(eleve_id);
