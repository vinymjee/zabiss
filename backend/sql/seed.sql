-- Seed Zabiss

INSERT INTO etablissements (id, nom, adresse, telephone, email) VALUES
(1, 'Groupe Scolaire Zabiss Excellence', 'Abidjan, Cocody', '+225 07 00 00 00 00', 'contact@zabiss.ci');

INSERT INTO classes (id, etablissement_id, nom, niveau, annee_scolaire) VALUES
(1, 1, '6e A', '6e', '2025-2026'),
(2, 1, '3e B', '3e', '2025-2026'),
(3, 1, 'Terminale C', 'Terminale', '2025-2026');

INSERT INTO matieres (id, nom, code, coefficient) VALUES
(1,'Mathématiques','MATH',4),(2,'Français','FR',3),(3,'Anglais','ANG',2),(4,'Physique-Chimie','PC',3),(5,'SVT','SVT',2),(6,'Histoire-Géo','HG',2),(7,'EPS','EPS',1);

-- Élèves de test : mdp = eleve123 (hash bcrypt)
-- Hash généré en PHP: password_hash('eleve123', PASSWORD_DEFAULT)
-- On insère avec un hash fixe pour seed
INSERT INTO eleves (id, matricule, login, password_hash, nom, prenom, date_naissance, sexe, classe_id, etablissement_id) VALUES
(1, 'MAT-2025-001', 'koffi.aya', '$2y$12$npaoUjC2FNJFOemdYV30XuW21uaV4aTb85p0pg3THChhINNBzvPq2', 'KOFFI', 'Aya', '2014-03-12', 'F', 1, 1),
(2, 'MAT-2025-002', 'koffi.moussa', '$2y$12$npaoUjC2FNJFOemdYV30XuW21uaV4aTb85p0pg3THChhINNBzvPq2', 'KOFFI', 'Moussa', '2011-08-24', 'M', 2, 1),
(3, 'MAT-2025-003', 'traore.fatou', '$2y$12$npaoUjC2FNJFOemdYV30XuW21uaV4aTb85p0pg3THChhINNBzvPq2', 'TRAORE', 'Fatou', '2008-01-05', 'F', 3, 1);

-- Notes T1
INSERT INTO notes (eleve_id, matiere_id, periode, type_eval, note, note_sur, coefficient, date_eval) VALUES
(1,1,'T1','devoir',14,20,2,'2025-10-10'),(1,1,'T1','composition',12,20,3,'2025-11-20'),(1,2,'T1','devoir',15,20,2,'2025-10-12'),(1,3,'T1','devoir',16,20,1,'2025-10-15'),
(1,4,'T1','devoir',11,20,2,'2025-11-05'),(1,5,'T1','devoir',13,20,2,'2025-11-08'),
(2,1,'T1','devoir',9,20,2,'2025-10-10'),(2,2,'T1','devoir',11,20,2,'2025-10-12'),(2,3,'T1','devoir',10,20,1,'2025-10-15'),
(3,1,'T1','composition',16,20,3,'2025-11-20'),(3,2,'T1','composition',14,20,3,'2025-11-22'),(3,4,'T1','composition',15,20,3,'2025-11-18');

-- Présences
INSERT INTO presences (eleve_id, date_jour, statut, justifie, motif) VALUES
(1,'2025-11-01','present',0,null),(1,'2025-11-04','retard',0,'Retard 10min'),(1,'2025-11-07','absent',1,'Maladie'),
(2,'2025-11-01','present',0,null),(2,'2025-11-05','absent',0,'Non justifié'),
(3,'2025-11-01','present',0,null),(3,'2025-11-06','present',0,null);

-- Paiements
INSERT INTO paiements (eleve_id, montant, montant_paye, type_paiement, statut, date_echeance, date_paiement, reference, mode_paiement) VALUES
(1, 350000, 350000, 'scolarite', 'paye', '2025-10-01', '2025-09-20 10:00:00', 'PAY-001', 'mobile_money'),
(1, 50000, 25000, 'cantine', 'partiel', '2025-11-01', '2025-10-15 09:00:00', 'PAY-002', 'espece'),
(2, 400000, 200000, 'scolarite', 'partiel', '2025-10-01', '2025-09-25 14:00:00', 'PAY-003', 'virement'),
(3, 450000, 0, 'scolarite', 'impaye', '2025-10-01', null, 'PAY-004', null);

-- Infos établissement
INSERT INTO infos_etablissement (etablissement_id, titre, contenu, type_info, important, date_evenement) VALUES
(1, 'Réunion parents - T1', 'Chers parents, la réunion de remise des bulletins T1 aura lieu le samedi 14 décembre 2025 à 9h.', 'reunion', 1, '2025-12-14'),
(1, 'Fête de fin d''année', 'La fête de fin d''année se tiendra le 20 décembre. Préparez les tenues traditionnelles !', 'evenement', 0, '2025-12-20'),
(1, 'Paiement scolarité - Rappel', 'Merci de régulariser les frais de scolarité avant le 30 novembre 2025. Contactez l''intendance.', 'general', 1, null);

