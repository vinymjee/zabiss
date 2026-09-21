<?php
// Init DB MySQL si tables manquantes (+ migration v2 ecoles/dossiers/admin)
require __DIR__ . '/config/database.php';
$pdo = getPDO();
try {
    $pdo->query("SELECT 1 FROM parents LIMIT 1");
    $cnt = $pdo->query("SELECT COUNT(*) FROM eleves")->fetchColumn();
    if ($cnt > 0) { echo "DB already seeded\n"; try { ensureV2Schema($pdo); echo "v2 ensured\n"; } catch (Throwable $e) {} exit(0); }
} catch(Throwable $e) {
    echo "Creating schema...\n";
    $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
    // exec multi statements
    $pdo->exec($sql);
}
try { ensureV2Schema($pdo); echo "v2 ensured\n"; } catch (Throwable $e) { echo "v2 warn: " . $e->getMessage() . "\n"; }
try {
    $cnt = $pdo->query("SELECT COUNT(*) FROM eleves")->fetchColumn();
    if ($cnt == 0) {
        echo "Seeding...\n";
        $seed = file_get_contents(__DIR__ . '/sql/seed.sql');
        $pdo->exec($seed);
        // fix hash eleve123
        $h = password_hash('eleve123', PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE eleves SET password_hash=? WHERE matricule LIKE 'MAT-%'")->execute([$h]);
        echo "Seed done\n";
    }
} catch(Throwable $e){ echo "seed error: ".$e->getMessage()."\n"; }
