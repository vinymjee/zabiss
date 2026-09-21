CREATE TABLE IF NOT EXISTS ecoles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  nom TEXT NOT NULL,
  adresse TEXT,
  telephone TEXT,
  email TEXT,
  api_key TEXT NOT NULL UNIQUE,
  actif INTEGER DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS admins (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nom TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT DEFAULT 'admin',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS admin_sessions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  admin_id INTEGER NOT NULL,
  token TEXT NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS eleve_dossiers (
  cle_unique TEXT PRIMARY KEY,
  eleve_id INTEGER NOT NULL,
  ecole_id INTEGER NOT NULL,
  annee_scolaire TEXT NOT NULL,
  donnees_json TEXT NOT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS notes_moyennes_dossiers (
  cle_unique TEXT PRIMARY KEY,
  eleve_id INTEGER NOT NULL,
  ecole_id INTEGER NOT NULL,
  annee_scolaire TEXT NOT NULL,
  donnees_json TEXT NOT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS presence_dossiers (
  cle_unique TEXT PRIMARY KEY,
  eleve_id INTEGER NOT NULL,
  ecole_id INTEGER NOT NULL,
  annee_scolaire TEXT NOT NULL,
  donnees_json TEXT NOT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS paiements_dossiers (
  cle_unique TEXT PRIMARY KEY,
  eleve_id INTEGER NOT NULL,
  ecole_id INTEGER NOT NULL,
  annee_scolaire TEXT NOT NULL,
  donnees_json TEXT NOT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_eleve_dossiers_eleve ON eleve_dossiers(eleve_id);
CREATE INDEX IF NOT EXISTS idx_notes_dossiers_eleve ON notes_moyennes_dossiers(eleve_id);
CREATE INDEX IF NOT EXISTS idx_presence_dossiers_eleve ON presence_dossiers(eleve_id);
CREATE INDEX IF NOT EXISTS idx_paiements_dossiers_eleve ON paiements_dossiers(eleve_id);
