<?php

use Proprietario\SudoMakers\core\Database;
use Proprietario\SudoMakers\core\GamificationEngine;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/GamificationEngine.php';

if(!isset($_SESSION['id_utente'])) {
    header("Location: ../auth/login.php");
    exit;
}

$pdo = Database::getInstance()->getConnection();
$engine = new GamificationEngine($pdo);
$id_utente = $_SESSION['id_utente'];
$title = "Gamification";

// Aggiorna progressi obiettivi
$engine->updateObjectiveProgress($id_utente);

// Tab attivo
$tab = $_GET['tab'] ?? 'badges';

// Recupera livello utente
$stmt = $pdo->prepare("SELECT * FROM livello_utente WHERE id_utente = :id");
$stmt->execute(['id' => $id_utente]);
$livello = $stmt->fetch();

// Recupera badge utente
$badges = $engine->getUserBadges($id_utente);

// Separa badge sbloccati e locked
$badges_sbloccati = array_filter($badges, fn($b) => !empty($b['data_ottenimento']));
$badges_locked = array_filter($badges, fn($b) => empty($b['data_ottenimento']));

// Recupera obiettivi attivi con progresso
$stmt = $pdo->prepare("
    SELECT 
        o.*,
        po.progresso_attuale,
        po.completato,
        po.data_completamento
    FROM obiettivo o
    LEFT JOIN progresso_obiettivo po ON o.id_obiettivo = po.id_obiettivo AND po.id_utente = :id_utente
    WHERE o.attivo = 1
    ORDER BY 
        CASE WHEN po.completato = 1 THEN 1 ELSE 0 END,
        o.ordine_visualizzazione
");
$stmt->execute(['id_utente' => $id_utente]);
$obiettivi = $stmt->fetchAll();

// Recupera classifica
$classifica = $engine->getLeaderboard(20);

// Trova posizione utente
$user_position = null;
foreach($classifica as $index => $user) {
    if($user['id_utente'] == $id_utente) {
        $user_position = $index + 1;
        break;
    }
}

// Funzione per mappare rarità a classe CSS
function getRarityClass($rarita) {
    return match($rarita) {
        'comune' => 'rarity-comune',
        'raro' => 'rarity-raro',
        'epico' => 'rarity-epico',
        'leggendario' => 'rarity-leggendario',
        default => 'rarity-comune'
    };
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/dashboardStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/gamificationStyle.css">
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="gamification-container">

    <!-- Header con livello -->
    <div class="gamification-header">
        <div class="level-card">
            <div class="level-info">
                <h1>Livello <?= $livello['livello'] ?></h1>
                <p class="level-title"><?= htmlspecialchars($livello['titolo']) ?></p>
            </div>

            <div class="xp-progress">
                <div class="xp-bar">
                    <div class="xp-fill" style="width: <?= ($livello['esperienza_livello_corrente'] / $livello['esperienza_prossimo_livello']) * 100 ?>%"></div>
                </div>
                <p class="xp-text">
                    <?= number_format($livello['esperienza_livello_corrente']) ?> / <?= number_format($livello['esperienza_prossimo_livello']) ?> XP
                </p>
            </div>

            <div class="level-stats">
                <div class="stat-item">
                    <span class="stat-value"><?= count($badges_sbloccati) ?></span>
                    <span class="stat-label">Badge</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= number_format($livello['esperienza_totale']) ?></span>
                    <span class="stat-label">XP Totali</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">#<?= $user_position ?? '?' ?></span>
                    <span class="stat-label">Classifica</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs
    <div class="tabs-navigation">
        <button class="tab-button <?= $tab === 'badges' ? 'active' : '' ?>" onclick="switchTab('badges')">
            Badge <span class="tab-badge"><?= count($badges_sbloccati) ?>/<?= count($badges) ?></span>
        </button>
        <button class="tab-button <?= $tab === 'obiettivi' ? 'active' : '' ?>" onclick="switchTab('obiettivi')">
            Obiettivi <span class="tab-badge"><?= count(array_filter($obiettivi, fn($o) => !empty($o['completato']))) ?>/<?= count($obiettivi) ?></span>
        </button>
        <button class="tab-button <?= $tab === 'classifica' ? 'active' : '' ?>" onclick="switchTab('classifica')">
            Classifica
        </button>
    </div>-->

    <!-- TAB BADGES -->
    <div id="tab-badges" class="tab-content <?= $tab === 'badges' ? 'active' : '' ?>">

        <?php if(!empty($badges_sbloccati)): ?>
            <h2 class="section-title">Badge Sbloccati (<?= count($badges_sbloccati) ?>)</h2>
            <div class="badges-grid">
                <?php foreach($badges_sbloccati as $badge): ?>
                    <div class="badge-card unlocked <?= getRarityClass($badge['rarita']) ?>">
                        <div class="badge-icon"><?= $badge['icona'] ?></div>
                        <h3><?= htmlspecialchars($badge['nome']) ?></h3>
                        <p class="badge-description"><?= htmlspecialchars($badge['descrizione']) ?></p>
                        <div class="badge-meta">
                            <span class="badge-rarity"><?= ucfirst($badge['rarita']) ?></span>
                            <span class="badge-xp">+<?= $badge['punti_esperienza'] ?> XP</span>
                        </div>
                        <div class="badge-date">
                            Sbloccato il <?= date('d/m/Y', strtotime($badge['data_ottenimento'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($badges_locked)): ?>
            <h2 class="section-title">Badge da Sbloccare (<?= count($badges_locked) ?>)</h2>
            <div class="badges-grid">
                <?php foreach($badges_locked as $badge): ?>
                    <div class="badge-card locked <?= getRarityClass($badge['rarita']) ?>">
                        <div class="badge-icon locked-icon">🔒</div>
                        <h3>???</h3>
                        <p class="badge-description"><?= htmlspecialchars($badge['criterio_sblocco']) ?></p>
                        <div class="badge-meta">
                            <span class="badge-rarity"><?= ucfirst($badge['rarita']) ?></span>
                            <span class="badge-xp">+<?= $badge['punti_esperienza'] ?> XP</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- TAB OBIETTIVI -->
    <div id="tab-obiettivi" class="tab-content <?= $tab === 'obiettivi' ? 'active' : '' ?>">

        <?php if(!empty($obiettivi)): ?>
            <div class="obiettivi-list">
                <?php foreach($obiettivi as $obj):
                    $progresso = $obj['progresso_attuale'] ?? 0;
                    $percentuale = min(100, ($progresso / $obj['target']) * 100);
                    $completato = !empty($obj['completato']);
                    ?>
                    <div class="obiettivo-card <?= $completato ? 'completed' : '' ?>">
                        <div class="obiettivo-header">
                            <div class="obiettivo-icon"><?= $obj['icona'] ?></div>
                            <div class="obiettivo-info">
                                <h3><?= htmlspecialchars($obj['nome']) ?></h3>
                                <p><?= htmlspecialchars($obj['descrizione']) ?></p>
                            </div>
                            <div class="obiettivo-reward">
                                <span class="xp-badge">+<?= $obj['punti_esperienza'] ?> XP</span>
                            </div>
                        </div>

                        <div class="obiettivo-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $percentuale ?>%"></div>
                            </div>
                            <div class="progress-text">
                                <?= $progresso ?> / <?= $obj['target'] ?>
                                <?php if($completato): ?>
                                    <span class="completed-badge">✓ Completato</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if($completato && $obj['data_completamento']): ?>
                            <div class="obiettivo-completed-date">
                                Completato il <?= date('d/m/Y', strtotime($obj['data_completamento'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>Nessun obiettivo attivo al momento</p>
            </div>
        <?php endif; ?>

    </div>

    <!-- TAB CLASSIFICA -->
    <div id="tab-classifica" class="tab-content <?= $tab === 'classifica' ? 'active' : '' ?>">

        <?php if(!empty($classifica)): ?>

            <!-- Podio Top 3 -->
            <div class="podium">
                <?php
                $top3 = array_slice($classifica, 0, 3);
                $order = [1, 0, 2]; // Secondo, Primo, Terzo
                ?>
                <div class="podium-container">
                    <?php foreach($order as $idx):
                        if(!isset($top3[$idx])) continue;
                        $user = $top3[$idx];
                        $position = $idx + 1;
                        $medal = ['🥇', '🥈', '🥉'][$idx];
                        ?>
                        <div class="podium-place place-<?= $position ?>">
                            <div class="podium-user">
                                <img src="<?= htmlspecialchars($user['foto']) ?>" alt="Avatar" class="podium-avatar">
                                <div class="podium-medal"><?= $medal ?></div>
                                <div class="podium-info">
                                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                                    <span class="podium-level">Liv. <?= $user['livello'] ?></span>
                                    <span class="podium-xp"><?= number_format($user['esperienza_totale']) ?> XP</span>
                                </div>
                            </div>
                            <div class="podium-base">
                                <span><?= $position ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Classifica completa -->
            <div class="leaderboard-table">
                <table>
                    <thead>
                    <tr>
                        <th>Pos.</th>
                        <th>Utente</th>
                        <th>Livello</th>
                        <th>XP</th>
                        <th>Badge</th>
                        <th>Libri</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach($classifica as $index => $user):
                        $is_current_user = $user['id_utente'] == $id_utente;
                        ?>
                        <tr class="<?= $is_current_user ? 'current-user' : '' ?>">
                            <td class="rank-cell">
                                <?php if($index < 3): ?>
                                    <span class="rank-medal"><?= ['🥇', '🥈', '🥉'][$index] ?></span>
                                <?php else: ?>
                                    <span class="rank-number">#<?= $index + 1 ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="user-cell">
                                <img src="<?= htmlspecialchars($user['foto']) ?>" alt="Avatar" class="user-avatar">
                                <div class="user-info">
                                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                                    <small><?= htmlspecialchars($user['titolo']) ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="level-badge">Liv. <?= $user['livello'] ?></span>
                            </td>
                            <td>
                                <strong><?= number_format($user['esperienza_totale']) ?></strong>
                            </td>
                            <td>
                                <span class="stat-badge badge-count"><?= $user['badge_count'] ?> 🏆</span>
                            </td>
                            <td>
                                <span class="stat-badge books-count"><?= $user['libri_letti'] ?> 📚</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <div class="empty-state">
                <p>Nessun dato in classifica</p>
            </div>
        <?php endif; ?>

    </div>

</div>

<script>
    function switchTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));

        document.getElementById('tab-' + tabName).classList.add('active');
        event.currentTarget.classList.add('active');

        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.pushState({}, '', url);
    }
</script>

</body>
</html>