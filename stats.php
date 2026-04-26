<?php
require_once 'db.php';

// ── Aggregate queries ────────────────────────────────────────────────────────

// Overview counts
$counts = [];
foreach (['trials','sponsors','interventions','conditions','locations'] as $tbl) {
    $counts[$tbl] = $db->query("SELECT COUNT(*) FROM `$tbl`")->fetch_row()[0];
}

// Trials by status
$by_status = $db->query("
    SELECT overall_status AS label, COUNT(*) AS n
    FROM trials GROUP BY overall_status ORDER BY n DESC")->fetch_all(MYSQLI_ASSOC);

// Trials by phase
$by_phase = $db->query("
    SELECT phase AS label, COUNT(*) AS n
    FROM trials GROUP BY phase ORDER BY n DESC")->fetch_all(MYSQLI_ASSOC);

// Trials by study type
$by_type = $db->query("
    SELECT study_type AS label, COUNT(*) AS n
    FROM trials GROUP BY study_type ORDER BY n DESC")->fetch_all(MYSQLI_ASSOC);

// Top 15 states by trial count
$by_state = $db->query("
    SELECT l.state AS label, COUNT(DISTINCT tl.trial_id) AS n
    FROM locations l
    JOIN trial_locations tl ON tl.location_id = l.location_id
    WHERE l.state != '' AND l.country = 'United States'
    GROUP BY l.state ORDER BY n DESC LIMIT 15")->fetch_all(MYSQLI_ASSOC);

// Top 10 intervention types
$by_iv_type = $db->query("
    SELECT i.`type` AS label, COUNT(DISTINCT ti.trial_id) AS n
    FROM interventions i
    JOIN trial_interventions ti ON ti.intervention_id = i.intervention_id
    GROUP BY i.`type` ORDER BY n DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Top 10 sponsors
$by_sponsor = $db->query("
    SELECT s.name AS label, COUNT(*) AS n
    FROM sponsors s
    JOIN trials t ON t.lead_sponsor_id = s.sponsor_id
    GROUP BY s.sponsor_id ORDER BY n DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Enrollment distribution buckets
$enroll_buckets = $db->query("
    SELECT
      SUM(enrollment_count < 50)                               AS 'Under 50',
      SUM(enrollment_count BETWEEN 50 AND 199)                 AS '50–199',
      SUM(enrollment_count BETWEEN 200 AND 999)                AS '200–999',
      SUM(enrollment_count BETWEEN 1000 AND 9999)              AS '1 000–9 999',
      SUM(enrollment_count >= 10000)                           AS '10 000+',
      SUM(enrollment_count IS NULL)                            AS 'Not reported'
    FROM trials")->fetch_assoc();

function bar(int $n, int $max, string $color = '#2563eb'): string {
    $pct = $max > 0 ? round($n / $max * 100) : 0;
    return "<div style='background:#e2e8f0;border-radius:4px;height:16px;width:100%'>
              <div style='background:$color;width:{$pct}%;height:100%;border-radius:4px'></div></div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Statistics — DiabetesTrialFinder</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#f1f5f9;color:#1e293b;font-size:15px}
a{color:#2563eb;text-decoration:none} a:hover{text-decoration:underline}
header{background:#1e3a5f;color:#fff;padding:14px 24px;display:flex;align-items:center;justify-content:space-between}
header h1{font-size:1.3rem;font-weight:700} header h1 span{color:#93c5fd}
nav a{color:#cbd5e1;margin-left:20px;font-size:.9rem} nav a:hover{color:#fff;text-decoration:none}
.container{max-width:1100px;margin:0 auto;padding:20px}
.page-title{font-size:1.3rem;font-weight:700;color:#1e3a5f;margin-bottom:20px}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:24px}
.kpi{background:#fff;border-radius:10px;padding:18px;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.kpi .num{font-size:2rem;font-weight:800;color:#1e3a5f}
.kpi .lbl{font-size:.8rem;color:#64748b;text-transform:uppercase;letter-spacing:.4px;margin-top:4px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
@media(max-width:700px){.grid2{grid-template-columns:1fr}}
.card{background:#fff;border-radius:10px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.card h2{font-size:.95rem;font-weight:700;color:#1e3a5f;margin-bottom:14px;padding-bottom:8px;border-bottom:2px solid #e2e8f0;text-transform:uppercase;letter-spacing:.4px}
.stat-row{display:grid;grid-template-columns:1fr 80px auto;gap:8px;align-items:center;margin-bottom:8px;font-size:.85rem}
.stat-row .bar-wrap{min-width:80px}
.stat-row .count{text-align:right;font-weight:600;color:#1e3a5f;white-space:nowrap}
.stat-row .label{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#334155}
footer{text-align:center;padding:20px;color:#94a3b8;font-size:.8rem;margin-top:10px}
</style>
</head>
<body>

<header>
  <h1>Diabetes<span>Trial</span>Finder</h1>
  <nav>
    <a href="index.php">Search</a>
    <a href="stats.php">Statistics</a>
  </nav>
</header>

<div class="container">
  <div class="page-title">Summary Statistics — Type 2 Diabetes Clinical Trials (US)</div>

  <!-- KPI cards -->
  <div class="kpi-grid">
    <div class="kpi"><div class="num"><?= number_format($counts['trials']) ?></div><div class="lbl">Trials</div></div>
    <div class="kpi"><div class="num"><?= number_format($counts['sponsors']) ?></div><div class="lbl">Sponsors</div></div>
    <div class="kpi"><div class="num"><?= number_format($counts['interventions']) ?></div><div class="lbl">Interventions</div></div>
    <div class="kpi"><div class="num"><?= number_format($counts['conditions']) ?></div><div class="lbl">Conditions</div></div>
    <div class="kpi"><div class="num"><?= number_format($counts['locations']) ?></div><div class="lbl">Sites</div></div>
  </div>

  <div class="grid2">

    <!-- Trials by status -->
    <div class="card">
      <h2>Trials by Status</h2>
      <?php $max = max(array_column($by_status,'n')); foreach ($by_status as $r): ?>
      <div class="stat-row">
        <div class="label"><?= htmlspecialchars(str_replace('_',' ',$r['label'])) ?></div>
        <div class="bar-wrap"><?= bar((int)$r['n'], $max, '#2563eb') ?></div>
        <div class="count"><?= number_format($r['n']) ?></div>
      </div>
      <?php endforeach ?>
    </div>

    <!-- Trials by phase -->
    <div class="card">
      <h2>Trials by Phase</h2>
      <?php $max = max(array_column($by_phase,'n')); foreach ($by_phase as $r): ?>
      <div class="stat-row">
        <div class="label"><?= htmlspecialchars(str_replace('_',' ',$r['label'])) ?></div>
        <div class="bar-wrap"><?= bar((int)$r['n'], $max, '#7c3aed') ?></div>
        <div class="count"><?= number_format($r['n']) ?></div>
      </div>
      <?php endforeach ?>
    </div>

    <!-- Top 15 states -->
    <div class="card">
      <h2>Top 15 States by Trial Count</h2>
      <?php $max = max(array_column($by_state,'n')); foreach ($by_state as $r): ?>
      <div class="stat-row">
        <div class="label"><?= htmlspecialchars($r['label']) ?></div>
        <div class="bar-wrap"><?= bar((int)$r['n'], $max, '#16a34a') ?></div>
        <div class="count"><?= number_format($r['n']) ?></div>
      </div>
      <?php endforeach ?>
    </div>

    <!-- Top 10 intervention types -->
    <div class="card">
      <h2>Top Intervention Types</h2>
      <?php $max = max(array_column($by_iv_type,'n')); foreach ($by_iv_type as $r): ?>
      <div class="stat-row">
        <div class="label"><?= htmlspecialchars($r['label'] ?: 'Unknown') ?></div>
        <div class="bar-wrap"><?= bar((int)$r['n'], $max, '#d97706') ?></div>
        <div class="count"><?= number_format($r['n']) ?></div>
      </div>
      <?php endforeach ?>
    </div>

    <!-- Top 10 sponsors -->
    <div class="card">
      <h2>Top 10 Sponsors</h2>
      <?php $max = max(array_column($by_sponsor,'n')); foreach ($by_sponsor as $r): ?>
      <div class="stat-row">
        <div class="label" title="<?= htmlspecialchars($r['label']) ?>"><?= htmlspecialchars($r['label']) ?></div>
        <div class="bar-wrap"><?= bar((int)$r['n'], $max, '#0891b2') ?></div>
        <div class="count"><?= number_format($r['n']) ?></div>
      </div>
      <?php endforeach ?>
    </div>

    <!-- Enrollment distribution -->
    <div class="card">
      <h2>Enrollment Size Distribution</h2>
      <?php $max = max($enroll_buckets); foreach ($enroll_buckets as $label => $n): ?>
      <div class="stat-row">
        <div class="label"><?= htmlspecialchars($label) ?></div>
        <div class="bar-wrap"><?= bar((int)$n, (int)$max, '#dc2626') ?></div>
        <div class="count"><?= number_format($n) ?></div>
      </div>
      <?php endforeach ?>
    </div>

  </div><!-- /grid2 -->

  <p style="font-size:.8rem;color:#94a3b8;text-align:center;margin-top:4px">
    Data from <a href="https://clinicaltrials.gov" target="_blank">ClinicalTrials.gov</a> public API &mdash; 1,000 Type 2 Diabetes trials, United States &mdash;
    <a href="https://docs.google.com/forms/d/e/1FAIpQLSf_placeholder/viewform" target="_blank">Report accessibility issue</a>
  </p>

</div>

<footer>DiabetesTrialFinder &mdash; BIOI 4870 / CSCI 8876 &mdash; Bryce Theobald, University of Nebraska at Omaha</footer>

</body>
</html>
