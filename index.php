<?php
/* =====================================================
   CONFIGURATION
===================================================== */

$csvDir   = __DIR__ . "/csv";
$raceDate = new DateTime("2025-04-20");

/* =====================================================
   FONCTIONS
===================================================== */

function calculateAge(string $birthDate, DateTime $raceDate): int {
    return (new DateTime($birthDate))->diff($raceDate)->y;
}

function timeToSeconds(string $time): int {
    [$h, $m, $s] = array_map("intval", explode(":", $time));
    return $h * 3600 + $m * 60 + $s;
}

function secondsToTime(int $seconds): string {
    return sprintf(
        "%02d:%02d:%02d",
        floor($seconds / 3600),
        floor(($seconds % 3600) / 60),
        $seconds % 60
    );
}

/* =====================================================
   LECTURE ET FUSION DES CSV
===================================================== */

$results = [];

foreach (glob($csvDir . "/*.csv") as $csvFile) {
    if (($h = fopen($csvFile, "r")) !== false) {
        $headers = fgetcsv($h, 0, ";");
        while (($row = fgetcsv($h, 0, ";")) !== false) {
            $d = array_combine($headers, $row);
            $results[] = [
                "distance" => trim($d["Distance"]),
                "nom"      => trim($d["Prenom"] . " " . $d["Nom"]),
                "age"      => calculateAge($d["Date de naissance"], $raceDate),
                "sexe"     => strtoupper(trim($d["Sexe"])),
                "temps"    => trim($d["Chip Time"] ?: $d["Gun Time"])
            ];
        }
        fclose($h);
    }
}

/* =====================================================
   DISTANCE OBLIGATOIRE
===================================================== */

$distances = array_unique(array_column($results, "distance"));
sort($distances);
$distance = $_GET["distance"] ?? $distances[0];

/* =====================================================
   FILTRES
===================================================== */

$sexe     = $_GET["sexe"] ?? "all";
$ageRange = $_GET["age"] ?? "all";

$filtered = array_values(array_filter($results, function ($r) use ($distance, $sexe, $ageRange) {
    if ($r["distance"] !== $distance) return false;
    if ($sexe !== "all" && $r["sexe"] !== $sexe) return false;

    if ($ageRange !== "all") {
        [$min, $max] = array_map("intval", explode("-", $ageRange));
        if ($r["age"] < $min || $r["age"] > $max) return false;
    }
    return true;
}));

/* =====================================================
   TRI ET CLASSEMENT
===================================================== */

usort($filtered, fn($a, $b) => strcmp($a["temps"], $b["temps"]));

/* =====================================================
   STATISTIQUES
===================================================== */

$count = count($filtered);
$times = array_map(fn($r) => timeToSeconds($r["temps"]), $filtered);
sort($times);

if ($count > 0) {
    $meanSec = array_sum($times) / $count;

    $medianSec = ($count % 2)
        ? $times[intdiv($count, 2)]
        : ($times[$count/2 - 1] + $times[$count/2]) / 2;

    $variance = array_sum(array_map(
        fn($t) => ($t - $meanSec) ** 2,
        $times
    )) / $count;

    $stdDevSec = sqrt($variance);

    $mean   = secondsToTime((int) round($meanSec));
    $median = secondsToTime((int) round($medianSec));
    $stdDev = secondsToTime((int) round($stdDevSec));
}

/* =====================================================
   EXPORT CSV
===================================================== */

if (isset($_GET["export"]) && $_GET["export"] === "csv") {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=classement_{$distance}.csv");

    $out = fopen("php://output", "w");
    fputcsv($out, ["Rang", "Nom", "Âge", "Sexe", "Temps", "Distance"]);
    foreach ($filtered as $i => $r) {
        fputcsv($out, [$i + 1, $r["nom"], $r["age"], $r["sexe"], $r["temps"], $r["distance"]]);
    }
    fclose($out);
    exit;
}

/* =====================================================
   DONNÉES POUR LE GRAPHIQUE
===================================================== */

$hist = [];
foreach ($times as $t) {
    $bucket = floor($t / 60);
    $hist[$bucket] = ($hist[$bucket] ?? 0) + 1;
}
ksort($hist);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Classement – Viens Courir</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root {
  --vc-blue: #00A3E0;
  --vc-green: #7BCB2D;
  --vc-dark: #0B0F14;
  --vc-light: #F4F6F8;
  --vc-gray: #6B7280;
}

body {
  margin: 0;
  background: var(--vc-light);
  font-family: Inter, system-ui, sans-serif;
  color: var(--vc-dark);
}

.container {
  max-width: 1200px;
  margin: auto;
  padding: 32px 20px;
}

/* HEADER */
.header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 30px;
}

.header img {
  height: 70px;
}

.header h1 {
  margin: 0;
  font-size: 26px;
}

.subtitle {
  color: var(--vc-gray);
  font-size: 14px;
}

/* FILTRES */
.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 24px;
}

select, button {
  padding: 10px 14px;
  border-radius: 6px;
  border: 1px solid #ddd;
  font-size: 14px;
}

button {
  background: var(--vc-blue);
  color: white;
  border: none;
  cursor: pointer;
}

button.export {
  background: var(--vc-green);
}

/* STATS */
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin-bottom: 30px;
}

.stat {
  background: white;
  border-radius: 10px;
  padding: 16px;
  border-left: 6px solid var(--vc-blue);
}

.stat h3 {
  margin: 0 0 6px;
  font-size: 13px;
  color: var(--vc-gray);
}

.stat strong {
  font-size: 22px;
}

/* TABLE */
.table-box {
  background: white;
  border-radius: 12px;
  overflow: hidden;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  padding: 12px;
  border-top: 1px solid #eee;
}

th {
  background: #f9fafb;
  font-size: 12px;
  text-transform: uppercase;
  color: var(--vc-gray);
}

.rank {
  font-weight: 700;
  color: var(--vc-blue);
}

/* ============================
   TABLE ALIGNMENT FIX
============================ */

table {
  table-layout: fixed;
}

/* Alignement vertical identique */
th, td {
  vertical-align: middle;
}

/* Colonnes spécifiques */
th:nth-child(1),
td:nth-child(1) {
  text-align: center; /* Rang */
  width: 70px;
}

th:nth-child(2),
td:nth-child(2) {
  text-align: left; /* Nom */
}

th:nth-child(3),
td:nth-child(3) {
  text-align: center; /* Âge */
  width: 70px;
}

th:nth-child(4),
td:nth-child(4) {
  text-align: center; /* Sexe */
  width: 70px;
}

th:nth-child(5),
td:nth-child(5) {
  text-align: right; /* Temps */
  width: 110px;
}

</style>
</head>

<body>
<div class="container">

  <!-- HEADER -->
<div class="header">
  <img src="assets/logo-viens-courir.png" alt="Logo Viens Courir">
  <div>
    <h1>Classement par distance</h1>
    <div class="subtitle">
      Athlétisme Québec · Toutes les courses confondues
    </div>
  </div>
</div>

  <!-- FILTRES -->
  <form class="filters" method="get">
    <select name="distance">
      <?php foreach ($distances as $d): ?>
        <option value="<?= $d ?>" <?= $d === $distance ? "selected" : "" ?>><?= $d ?></option>
      <?php endforeach; ?>
    </select>

    <select name="sexe">
      <option value="all">Tous</option>
      <option value="M" <?= $sexe === "M" ? "selected" : "" ?>>Masculin</option>
      <option value="F" <?= $sexe === "F" ? "selected" : "" ?>>Féminin</option>
    </select>

    <select name="age">
      <option value="all">Tous âges</option>
      <?php for ($a = 15; $a <= 95; $a += 5): ?>
        <option value="<?= $a ?>-<?= $a+4 ?>" <?= $ageRange === "$a-".($a+4) ? "selected" : "" ?>>
          <?= $a ?>–<?= $a+4 ?>
        </option>
      <?php endfor; ?>
    </select>

    <button type="submit">Filtrer</button>

    <a href="?<?= http_build_query($_GET + ["export" => "csv"]) ?>">
      <button type="button" class="export">Exporter CSV</button>
    </a>
  </form>

  <!-- STATS -->
  <?php if ($count > 0): ?>
  <div class="stats">
    <div class="stat"><h3>Moyenne</h3><strong><?= $mean ?></strong></div>
    <div class="stat"><h3>Médiane</h3><strong><?= $median ?></strong></div>
    <div class="stat"><h3>Écart‑type</h3><strong><?= $stdDev ?></strong></div>
  </div>
  <?php endif; ?>

  <!-- GRAPHIQUE -->
  <canvas id="histogram"></canvas>

  <!-- TABLE -->
  <div class="table-box">
    <table>
      <thead>
        <tr>
          <th>Rang</th>
          <th>Nom</th>
          <th>Âge</th>
          <th>Sexe</th>
          <th>Temps</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($filtered as $i => $r): ?>
        <tr>
          <td class="rank"><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($r["nom"]) ?></td>
          <td><?= $r["age"] ?></td>
          <td><?= $r["sexe"] ?></td>
          <td><?= $r["temps"] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<script>
new Chart(document.getElementById("histogram"), {
  type: "bar",
  data: {
    labels: <?= json_encode(array_map(fn($m) => $m . " min", array_keys($hist))) ?>,
    datasets: [{
      label: "Nombre de coureurs",
      data: <?= json_encode(array_values($hist)) ?>,
      backgroundColor: "#00A3E0"
    }]
  }
});
</script>
</body>
</html>