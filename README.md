# Zabiss — Portail Parent d'Élève

Portail **Angular 21 + PHP 8 + MySQL 8** permettant à un parent de gérer **plusieurs enfants** via un seul compte.

**Flow :**
1. Parent crée son compte (`/register`) → passe en `espace contrôle` (compte validé auto)
2. Ajoute chaque élève par **matricule + login + mot de passe élève** (vérification côté établissement)
3. Accède pour chaque enfant : **moyennes / bulletins, présences, historique paiements, infos établissement**

---

## Stack
- Frontend: Angular 21 (standalone, lazy, signals), SCSS design system teal/amber
- Backend: PHP 8 API REST (PDO), JWT-like token (sessions table), SQLite fallback dev
- BDD: MySQL 8 (ou MariaDB), schema + seed
- Infra: Docker Compose, Nginx proxy, Apache PHP

## Structure
```
zabiss/
  src/app/
    core/ (api, auth.service, auth.guard, eleve.service)
    shared/layout (sidebar + topbar)
    features/ auth / dashboard / enfants / eleve-detail / paiements / infos
  backend/
    config/database.php
    api/ (index.php, auth.php, eleves.php, notes.php, presences.php, paiements.php, infos.php)
    sql/ (schema.sql, seed.sql, schema.sqlite.sql)
  deploy/ (Dockerfiles, nginx.conf, oracle-setup.sh)
  docker-compose.yml
```

## Démarrage local (sans Docker, SQLite)

```bash
# Backend PHP (SQLite, pas besoin de MySQL)
cd backend
DB_DRIVER=sqlite php -S 127.0.0.1:8080 router.php

# Frontend
npm install
npm start
# Ouvre http://localhost:4200 (proxy vers http://localhost:8080/api)
# Ou build prod: npm run build -> dist/zabiss
```

**Comptes de test (après seed) :**
- Parent : crée-toi un compte (ex: parent@test.ci / parent123)
- Élèves à lier :
  - `MAT-2025-001 / koffi.aya / eleve123` — Aya KOFFI (6e A)
  - `MAT-2025-002 / koffi.moussa / eleve123` — Moussa KOFFI (3e B)
  - `MAT-2025-003 / traore.fatou / eleve123` — Fatou TRAORE (Tle C)

## Docker (recommandé)

```bash
docker compose up --build -d
# Frontend: http://localhost/  (Nginx)
# API direct: http://localhost:8080/api/health
# MySQL: localhost:3306 (zabiss / zabiss_secret)
docker compose logs -f
```

## Déploiement Oracle Cloud Free Tier (Always Free)

Oracle offre **2 VM Always Free** (E2.1.Micro 1GB ou Ampere A1 4OCPU/24GB) + IP publique.

### 1. Créer le VPS
- console.oracle.com → Compute → Create Instance
- **Image:** Ubuntu 22.04 ou Oracle Linux 8
- **Shape:** VM.Standard.E2.1.Micro (Free-Tier) ou VM.Standard.A1.Flex (4 OCPU, 24GB - gratuit)
- **VCN:** crée un VCN avec Internet Gateway
- **SSH:** ajoute ta clé publique

### 2. Ouvrir les ports (TRÈS IMPORTANT Oracle)
- Compute → Instance → VNIC → Subnet → Security List → Add Ingress Rules
  - Source 0.0.0.0/0, TCP, port 80, 443, 8080
- Sans ça, le site reste inaccessible même si Docker tourne.

### 3. Installer sur le VPS

```bash
ssh ubuntu@<IP_PUBLIQUE>
# Option A : script auto
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker ubuntu && newgrp docker
git clone <TON_REPO_GIT> zabiss
cd zabiss
sudo bash deploy/oracle-setup.sh
docker compose up --build -d
curl http://localhost/api/health  # doit renvoyer {"status":"ok"}
# Ouvre http://<IP_PUBLIQUE>/
```

### 4. IP fixe + HTTPS (optionnel gratuit)
- Networking → Public IP → Reserved Public IP → assigne-la à ton instance (gratuite si attachée)
- HTTPS Let's Encrypt:
```bash
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d ton-domaine.duckdns.org  # utilise DuckDNS gratuit si pas de domaine
```

### Astuce Oracle Free
- Ne **jamais** laisser une Reserved IP détachée (facturée)
- Snapshot régulier via Boot Volume Backup (gratuit 200GB)
- Si "Out of capacity" sur E2.Micro, tente une autre région (ex: Frankfurt, Paris) ou Ampere A1.

## API Endpoints
```
POST /api/auth/register {nom,prenom,email,telephone,password}
POST /api/auth/login {email,password}
GET  /api/auth/me (Bearer)
POST /api/parent/eleves/link {matricule,login,password,lien}
GET  /api/parent/eleves
DELETE /api/parent/eleves/{id}
GET  /api/eleves/{id}
GET  /api/eleves/{id}/notes?periode=T1
GET  /api/eleves/{id}/moyennes
GET  /api/eleves/{id}/presences
GET  /api/eleves/{id}/paiements
GET  /api/parent/paiements
GET  /api/infos
```

## Sécurité espace contrôle
- Lien parent-élève vérifie **triplet matricule+login+mdp** (password_verify côté PHP)
- Token Bearer 7 jours, stockage sessions
- Contrainte UNIQUE(parent_id, eleve_id) anti-doublon
- Toutes les routes élève vérifient `parent_eleve` avant de livrer données

## TODO prod
- [ ] Rate limit login
- [ ] SMTP vérif email parent
- [ ] Import CSV élèves par admin établissement
- [ ] Paiement Mobile Money réel
