<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Classement – Viens Courir</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
    <div class="header">
        <div class="brand">
            <img src="assets/logo.png" alt="Logo Viens Courir">
        </div>
        <div class="header-actions">
            <a href="upload.php?<?= h($currentQuery) ?>" class="btn-link btn-primary">Espace Admin</a>
            <a href="<?= $resetQuery !== '' ? '?' . h($resetQuery) : 'testdb.php' ?>" class="btn-link btn-secondary">Réinitialiser les filtres</a>
        </div>
    </div>

    <div class="filters-card">
        <h2 class="section-title">Filtres</h2>
        <form method="get" class="filters">
            <div class="field">
                <label for="epreuve">Épreuve</label>
                <select name="epreuve" id="epreuve">
                    <?php foreach ($epreuves as $e): ?>
                        <option value="<?= h($e) ?>" <?= $e === $epreuve ? 'selected' : '' ?>><?= h($e) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="course">Course</label>
                <select name="course" id="course">
                    <option value="all" <?= $course === 'all' ? 'selected' : '' ?>>Toutes les courses</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= h($c) ?>" <?= $c === $course ? 'selected' : '' ?>><?= h($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="sexe">Genre</label>
                <select name="sexe" id="sexe">
                    <option value="all" <?= $sexe === 'all' ? 'selected' : '' ?>>Tous</option>
                    <option value="m" <?= $sexe === 'm' ? 'selected' : '' ?>>Hommes</option>
                    <option value="f" <?= $sexe === 'f' ? 'selected' : '' ?>>Femmes</option>
                </select>
            </div>

            <div class="field">
                <label for="age">Tranche d'âge</label>
                <select name="age" id="age">
                    <option value="all" <?= $age === 'all' ? 'selected' : '' ?>>Tous âges</option>
                    <?php for ($a = 15; $a <= 95; $a += 5): $range = $a . '-' . ($a + 4); ?>
                        <option value="<?= h($range) ?>" <?= $age === $range ? 'selected' : '' ?>><?= h($range) ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="field">
                <input type="text" id="nom" name="nom" value="<?= h($nom) ?>" placeholder="Rechercher par nom">
            </div>

            <div class="field filter-actions">
                <button type="submit">Filtrer</button>
                <button type="submit" name="export" value="csv">Exporter CSV</button>
            </div>
        </form>
    </div>

    <div class="stats-card">
        <h2 class="section-title">Statistiques</h2>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="label">Participants affichés</div>
                <div class="value"><?= h($totalRows) ?></div>
            </div>
            <div class="stat-box">
                <div class="label">Moyenne</div>
                <div class="value"><?= h($mean) ?></div>
            </div>
            <div class="stat-box">
                <div class="label">Médiane</div>
                <div class="value"><?= h($median) ?></div>
            </div>
            <div class="stat-box">
                <div class="label">Écart-type</div>
                <div class="value"><?= h($std) ?></div>
            </div>
        </div>
    </div>

    <div class="chart-card">
        <h2 class="section-title">Distribution des temps</h2>
        <canvas id="histogram"></canvas>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th>Prénom</th>
                        <th>Nom</th>
                        <th>Genre</th>
                        <th>Âge</th>
                        <th>Temps</th>
                        <th>Course</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="7" class="empty">Aucun résultat trouvé.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $index => $row): ?>
                            <tr>
                                <td class="rank"><?= h($offset + $index + 1) ?></td>
                                <td><?= h($row['First_Name']) ?></td>
                                <td><?= h($row['Last_Name']) ?></td>
                                <td><?= h($row['Sex']) ?></td>
                                <td><?= h($row['Age']) ?></td>
                                <td><?= h(substr((string) $row['Chip_Time'], 0, 8)) ?></td>
                                <td><?= h($row['Race']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= h('?' . http_build_query(array_merge($queryWithoutPage, ['page' => 1]))) ?>">Première</a>
                    <a href="<?= h('?' . http_build_query(array_merge($queryWithoutPage, ['page' => $page - 1]))) ?>">Précédent</a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= h('?' . http_build_query(array_merge($queryWithoutPage, ['page' => $page + 1]))) ?>">Suivant</a>
                    <a href="<?= h('?' . http_build_query(array_merge($queryWithoutPage, ['page' => $totalPages]))) ?>">Dernière</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer-note">
        Viens Courir — classement, statistiques et export des résultats.
    </div>
</div>

<script>
window.histogramData = {
    labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
    values: <?= json_encode($chartValues, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="assets/app.js"></script>
</body>
</html>