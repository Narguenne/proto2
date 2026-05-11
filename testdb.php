<?php
/* =========================================================
   CONFIG
   ========================================================= */
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* =========================================================
   CONNEXION BD
   ========================================================= */
$pdo = new PDO(
    "mysql:host=localhost;dbname=Classement VC;charset=utf8mb4",
    "root",
    "",
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

/* =========================================================
   FONCTIONS
   ========================================================= */
function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function timeToSeconds(?string $time): int {
    if (!$time || !preg_match('/^\d{2}:\d{2}:\d{2}/', $time)) return 0;
    [$h,$m,$s] = array_map('intval', explode(':', substr($time,0,8)));
    return $h*3600 + $m*60 + $s;
}

function secondsToTime(float $sec): string {
    return sprintf(
        "%02d:%02d:%02d",
        floor($sec/3600),
        floor(($sec%3600)/60),
        floor($sec%60)
    );
}

function addFilter(&$sql,&$params,$field,$value){
    if($value!=='all'){
        $sql.=" AND $field = :$field";
        $params[$field]=$value;
    }
}

/* =========================================================
   FILTRES + PAGINATION
   ========================================================= */
$epreuve=$_GET['epreuve'] ?? 'all';
$course =$_GET['course']  ?? 'all';
$sexe   =$_GET['sexe']    ?? 'all';
$age    =$_GET['age']     ?? 'all';
$nom    =$_GET['nom']     ?? '';

$page  = max(1,(int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page-1)*$limit;

/* =========================================================
   TABLES & UNION
   ========================================================= */
$tables=$pdo->query("
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema='Classement VC'
")->fetchAll(PDO::FETCH_COLUMN);

$selects=[];
foreach($tables as $t){
    $selects[]="
        SELECT
            Distance   AS Epreuve,
            First_Name AS Prenom,
            Last_Name  AS Nom,
            Sex        AS Sexe,
            Age        AS Age,
            Chip_Time  AS chip_time,
            Race       AS Course
        FROM `$t`
        WHERE Chip_Time IS NOT NULL
          AND First_Name IS NOT NULL
    ";
}
$baseSql=implode(" UNION ALL ",$selects);

$epreuves=$pdo->query("
    SELECT DISTINCT Epreuve
    FROM ($baseSql) a
    ORDER BY Epreuve
")->fetchAll(PDO::FETCH_COLUMN);
if($epreuve==='all' || !in_array($epreuve,$epreuves,true)){
    $epreuve = $epreuves[0] ?? 'all';
}

/* =========================================================
   FILTRES SQL
   ========================================================= */
$where=" WHERE 1=1 ";
$params=[];

addFilter($where,$params,'Epreuve',$epreuve);
addFilter($where,$params,'Course',$course);
addFilter($where,$params,'Sexe',$sexe);

if($age!=='all'){
    [$min,$max]=array_map('intval',explode('-',$age));
    $where.=" AND Age BETWEEN :min AND :max";
    $params['min']=$min;
    $params['max']=$max;
}

$params_base = $params; // params for stats and inner query

if($nom){
    $params['nom'] = '%' . $nom . '%';
    $name_condition = " AND (Prenom LIKE :nom OR Nom LIKE :nom)";
} else {
    $name_condition = "";
}

if(isset($_GET['export']) && $_GET['export'] === 'csv'){
    $exportStmt = $pdo->prepare("
        SELECT *
        FROM (
            SELECT *, ROW_NUMBER() OVER (ORDER BY chip_time ASC) as rank
            FROM ($baseSql) x
            $where
        ) y
        WHERE 1=1 $name_condition
        ORDER BY rank ASC
    ");
    foreach($params as $k=>$v) {
        $exportStmt->bindValue(":$k", $v);
    }
    $exportStmt->execute();
    $exportRows = $exportStmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="resultats.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['#', 'Prénom', 'Nom', 'Sexe', 'Âge', 'Temps', 'Épreuve', 'Course']);
    foreach($exportRows as $r){
        fputcsv($output, [$r['rank'], $r['Prenom'], $r['Nom'], $r['Sexe'], $r['Age'], substr($r['chip_time'],0,8), $r['Epreuve'], $r['Course']]);
    }
    fclose($output);
    exit;
}

/* =========================================================
   STATISTIQUES SQL (GLOBAL)
   ========================================================= */
$stats=$pdo->prepare("
    SELECT
        COUNT(*) AS total,
        AVG(TIME_TO_SEC(chip_time)) AS mean_sec,
        STDDEV_POP(TIME_TO_SEC(chip_time)) AS std_sec
    FROM ($baseSql) x
    $where
");
$stats->execute($params_base);
$s=$stats->fetch();

$totalRows=(int)$s['total'];
$totalPages=max(1,ceil($totalRows/$limit));

$mean=secondsToTime(round($s['mean_sec'] ?? 0));
$std =secondsToTime(round($s['std_sec']  ?? 0));

/* =========================================================
   DONNÉES PAGINÉES (TABLEAU)
   ========================================================= */
$stmt=$pdo->prepare("
    SELECT *
    FROM (
        SELECT *, ROW_NUMBER() OVER (ORDER BY chip_time ASC) as rank
        FROM ($baseSql) x
        $where
    ) y
    WHERE 1=1 $name_condition
    ORDER BY rank ASC
    " . ($nom ? "" : "LIMIT :limit OFFSET :offset")
);
foreach($params as $k=>$v) $stmt->bindValue(":$k",$v);
if(!$nom){
    $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
    $stmt->bindValue(':offset',$offset,PDO::PARAM_INT);
}
$stmt->execute();
$rows=$stmt->fetchAll();

/* =========================================================
   DONNÉES GLOBALES POUR LE GRAPHIQUE
   ========================================================= */
$chartStmt=$pdo->prepare("
    SELECT chip_time
    FROM ($baseSql) x
    $where
");
$chartStmt->execute($params_base);
$chartTimes=$chartStmt->fetchAll(PDO::FETCH_COLUMN);

/* =========================================================
   MÉDIANE + HISTOGRAMME (GLOBAL)
   ========================================================= */
$times=[];
foreach($chartTimes as $t){
    $sec=timeToSeconds($t);
    if($sec>0) $times[]=$sec;
}
sort($times);
$n=count($times);

$median=$n
?($n%2?$times[intdiv($n,2)]
          :($times[$n/2-1]+$times[$n/2])/2)
:0;
$median=secondsToTime($median);

$hist=[];
foreach($times as $t){
    $b=floor($t/60);
    $hist[$b]=($hist[$b]??0)+1;
}
ksort($hist);

/* =========================================================
   LISTES FILTRES
   ========================================================= */
$epreuves=$pdo->query("
    SELECT DISTINCT Epreuve FROM ($baseSql) a ORDER BY Epreuve
")->fetchAll(PDO::FETCH_COLUMN);

$courses=$pdo->query("
    SELECT DISTINCT Course FROM ($baseSql) b ORDER BY Course
")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Classement – Viens Courir</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body{background:#f4f6f8;font-family:system-ui;margin:0;padding:30px}
.container{max-width:1200px;margin:auto}
.header{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;margin-bottom:30px;text-align:center}
.header img{height:84px;display:block}
.header h1{margin:0;font-size:2.6rem;color:#00A3E0;letter-spacing:0.03em}
.header .subtitle{color:#4f5f70;font-size:1rem}
.filters{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin-bottom:20px}
select,button{padding:8px 14px;border-radius:6px}
button{background:#00A3E0;color:#fff;border:none}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden}
th,td{padding:10px;text-align:center;border-bottom:1px solid #eee}
th{background:#f9fafb}
.rank{font-weight:bold;color:#00A3E0}
.pagination {
    display: flex;
    flex-wrap: wrap;           /* ← empêche le dépassement */
    justify-content: center;
    gap: 6px;
    margin: 20px 0;
}

.pagination a,
.pagination strong,
.pagination span {
    padding: 6px 10px;
    border-radius: 6px;
    background: #fff;
    border: 1px solid #ddd;
    min-width: 36px;
    text-align: center;
    text-decoration: none;
    color: #333;
}

.pagination strong {
    background: #00A3E0;
    color: #fff;
    border-color: #00A3E0;
}

.pagination span {
    border: none;
    background: transparent;
}

</style>
</head>

<body>
<div class="container">

<div class="header">
    <img src="assets/logo-viens-courir.png" alt="Viens Courir">
    <div>
        <h1>Classement VC</h1>
        <div class="subtitle">Toutes les courses confondues</div>
        <p><a href="admin/upload.php">Ajouter un nouveau fichier CSV</a></p>
    </div>
</div>

<form method="get" class="filters">
<select name="epreuve">
<?php foreach($epreuves as $e): ?>
<option <?= $e===$epreuve?'selected':'' ?>><?= h($e) ?></option>
<?php endforeach ?>
</select>

<select name="course">
<option value="all" <?= $course==='all' ? 'selected' : '' ?>>Toutes les courses</option>
<?php foreach($courses as $c): ?>
<option <?= $c===$course?'selected':'' ?>><?= h($c) ?></option>
<?php endforeach ?>
</select>

<select name="sexe">
<option value="all">Tous</option>
<option value="m" <?= $sexe==='m'?'selected':'' ?>>Masculin</option>
<option value="f" <?= $sexe==='f'?'selected':'' ?>>Féminin</option>
</select>

<select name="age">
<option value="all">Tous âges</option>
<?php for($a=15;$a<=95;$a+=5):
$r="$a-".($a+4); ?>
<option <?= $age===$r?'selected':'' ?>><?= $r ?></option>
<?php endfor ?>
</select>

<input type="text" name="nom" value="<?= h($nom) ?>" placeholder="Rechercher par nom">

<button>Filtrer</button>
<button name="export" value="csv">Exporter CSV</button>
</form>

<p style="text-align:center">
<strong>Moyenne :</strong> <?= $mean ?> |
<strong>Médiane :</strong> <?= $median ?> |
<strong>Écart‑type :</strong> <?= $std ?>
</p>

<canvas id="histogram"></canvas>

<table>
<thead>
<tr>
<th>#</th><th>Prénom</th><th>Nom</th><th>Sexe</th>
<th>Âge</th><th>Temps</th><th>Épreuve</th><th>Course</th>
</tr>
</thead>
<tbody>
<?php foreach($rows as $i=>$r): ?>
<tr>
<td class="rank"><?= $r['rank'] ?></td>
<td><?= h($r['Prenom']) ?></td>
<td><?= h($r['Nom']) ?></td>
<td><?= h($r['Sexe']) ?></td>
<td><?= h($r['Age']) ?></td>
<td><?= substr(h($r['chip_time']),0,8) ?></td>
<td><?= h($r['Epreuve']) ?></td>
<td><?= h($r['Course']) ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>

<?php if(!$nom): ?>
<div class="pagination">
<?php
$range = 2; // ← nombre de pages affichées autour de la page courante

$start = max(1, $page - $range);
$end   = min($totalPages, $page + $range);

function pageLink($p, $current) {
    if ($p == $current) {
        return "<strong>$p</strong>";
    }
    $q = http_build_query(array_merge($_GET, ['page' => $p]));
    return "<a href=\"?$q\">$p</a>";
}

// Première page
if ($start > 1) {
    echo pageLink(1, $page);
    if ($start > 2) echo "<span>…</span>";
}

// Pages centrales
for ($p = $start; $p <= $end; $p++) {
    echo pageLink($p, $page);
}

// Dernière page
if ($end < $totalPages) {
    if ($end < $totalPages - 1) echo "<span>…</span>";
    echo pageLink($totalPages, $page);
}
?>
</div>
<?php endif; ?>

</div>

<script>
new Chart(document.getElementById("histogram"),{
 type:"bar",
 data:{
  labels:<?= json_encode(array_map(fn($m)=>"$m min",array_keys($hist))) ?>,
  datasets:[{
    label:"Nombre de coureurs",
    data:<?= json_encode(array_values($hist)) ?>,
    backgroundColor:"#00A3E0"
  }]
 }
});
</script>

</body>
</html>