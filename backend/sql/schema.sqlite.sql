CREATE TABLE IF NOT EXISTS parents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nom TEXT NOT NULL,
  prenom TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  telephone TEXT,
  password_hash TEXT NOT NULL,
  statut TEXT DEFAULT 'valide',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS etablissements (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nom TEXT NOT NULL,
  adresse TEXT,
  telephone TEXT,
  email TEXT
);
CREATE TABLE IF NOT EXISTS classes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  etablissement_id INTEGER,
  nom TEXT NOT NULL,
  niveau TEXT,
  annee_scolaire TEXT DEFAULT '2025-2026',
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS eleves (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  matricule TEXT NOT NULL UNIQUE,
  login TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  nom TEXT NOT NULL,
  prenom TEXT NOT NULL,
  date_naissance DATE,
  sexe TEXT DEFAULT 'M',
  classe_id INTEGER,
  etablissement_id INTEGER,
  photo_url TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE SET NULL,
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS parent_eleve (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  parent_id INTEGER NOT NULL,
  eleve_id INTEGER NOT NULL,
  lien TEXT DEFAULT 'parent',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(parent_id, eleve_id),
  FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
  FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS sessions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  parent_id INTEGER NOT NULL,
  token TEXT NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS matieres (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nom TEXT NOT NULL,
  code TEXT UNIQUE,
  coefficient INTEGER DEFAULT 1
);
CREATE TABLE IF NOT EXISTS notes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  eleve_id INTEGER NOT NULL,
  matiere_id INTEGER NOT NULL,
  periode TEXT NOT NULL,
  type_eval TEXT DEFAULT 'devoir',
  note REAL NOT NULL,
  note_sur REAL DEFAULT 20,
  coefficient REAL DEFAULT 1,
  date_eval DATE,
  annee_scolaire TEXT DEFAULT '2025-2026',
  commentaire TEXT,
  FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
  FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS presences (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  eleve_id INTEGER NOT NULL,
  date_jour DATE NOT NULL,
  statut TEXT NOT NULL DEFAULT 'present',
  motif TEXT,
  justifie INTEGER DEFAULT 0,
  heure_arrivee TEXT,
  FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS paiements (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  eleve_id INTEGER NOT NULL,
  parent_id INTEGER,
  montant REAL NOT NULL,
  montant_paye REAL DEFAULT 0,
  type_paiement TEXT NOT NULL,
  statut TEXT DEFAULT 'en_attente',
  date_echeance DATE,
  date_paiement DATETIME,
  reference TEXT,
  mode_paiement TEXT,
  annee_scolaire TEXT DEFAULT '2025-2026',
  FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS infos_etablissement (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  etablissement_id INTEGER,
  titre TEXT NOT NULL,
  contenu TEXT NOT NULL,
  type_info TEXT DEFAULT 'general',
  date_publication DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_evenement DATE,
  important INTEGER DEFAULT 0,
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
);
