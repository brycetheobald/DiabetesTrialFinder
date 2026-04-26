<?php
require_once 'db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

// Core trial data
$stmt = $db->prepare("
    SELECT t.*, s.name AS sponsor_name, s.class AS sponsor_class
    FROM trials t
    LEFT JOIN sponsors s ON t.lead_sponsor_id = s.sponsor_id
    WHERE t.trial_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$trial = $stmt->get_result()->fetch_assoc();
if (!$trial) { header('Location: index.php'); exit; }

// Interventions
$iv_res = $db->prepare("
    SELECT i.`type`, i.name FROM interventions i
    JOIN trial_interventions ti ON ti.intervention_id = i.intervention_id
    WHERE ti.trial_id = ? ORDER BY i.`type`, i.name");
$iv_res->bind_param('i', $id);
$iv_res->execute();
$interventions = $iv_res->get_result()->fetch_all(MYSQLI_ASSOC);

// Conditions
$cond_res = $db->prepare("
    SELECT c.name FROM `conditions` c
    JOIN trial_conditions tc ON tc.condition_id = c.condition_id
    WHERE tc.trial_id = ? ORDER BY c.name");
$cond_res->bind_param('i', $id);
$cond_res->execute();
$conditions = $cond_res->get_result()->fetch_all(MYSQLI_ASSOC);

// Locations grouped by state
$loc_res = $db->prepare("
    SELECT l.state, COUNT(*) as sites
    FROM locations l
    JOIN trial_locations tl ON tl.location_id = l.location_id
    WHERE tl.trial_id = ? AND l.country = 'United States' AND l.state != ''
    GROUP BY l.state ORDER BY sites DESC, l.state");
$loc_res->bind_param('i', $id);
$loc_res->execute();
$locations = $loc_res->get_result()->fetch_all(MYSQLI_ASSOC);

$badge_color = [
    'RECRUITING'            => '#16a34a',
    'COMPLETED'             => '#2563eb',
    'ACTIVE_NOT_RECRUITING' => '#d97706',
    'TERMINATED'            => '#dc2626',
    'WITHDRAWN'             => '#6b7280',
    'SUSPENDED'             => '#7c3aed',
    'NOT_YET_RECRUITING'    => '#0891b2',
][$trial['overall_status']] ?? '#6b7280';

function fmt_date($d) { return $d ? date('F j, Y', strtotime($d)) : '—'; }
function fmt_num($n)  { return $n !== null ? number_format($n) : '—'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($trial['brief_title']) ?> — DiabetesTrialFinder</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#f1f5f9;color:#1e293b;font-size:15px}
a{color:#2563eb;text-decoration:none} a:hover{text-decoration:underline}
header{background:#1e3a5f;color:#fff;padding:14px 24px;display:flex;align-items:center;justify-content:space-between}
header h1{font-size:1.3rem;font-weight:700} header h1 span{color:#93c5fd}
nav a{color:#cbd5e1;margin-left:20px;font-size:.9rem} nav a:hover{color:#fff;text-decoration:none}
.container{max-width:900px;margin:0 auto;padding:20px}
.back{font-size:.85rem;color:#64748b;margin-bottom:14px;display:block}
.card{background:#fff;border-radius:10px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-bottom:16px}
.card h2{font-size:1rem;font-weight:700;color:#1e3a5f;margin-bottom:16px;padding-bottom:8px;border-bottom:2px solid #e2e8f0}
.trial-title{font-size:1.15rem;font-weight:700;color:#0f172a;margin-bottom:8px;line-height:1.4}
.badge{display:inline-block;padding:3px 12px;border-radius:20px;font-size:.8rem;font-weight:600;color:#fff}
.meta-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-top:16px}
.meta-item label{display:block;font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:#64748b;margin-bottom:3px}
.meta-item span{font-size:.9rem;color:#1e293b}
.tag{display:inline-block;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:4px;padding:2px 8px;font-size:.78rem;margin:2px}
.tag.intervention{background:#f0fdf4;color:#15803d;border-color:#bbf7d0}
.state-grid{display:flex;flex-wrap:wrap;gap:8px}
.state-chip{background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:4px 12px;font-size:.82rem;color:#374151}
.summary{line-height:1.7;color:#334155;font-size:.9rem;max-height:200px;overflow-y:auto;padding:12px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0}
.elig{line-height:1.6;color:#334155;font-size:.85rem;max-height:220px;overflow-y:auto;white-space:pre-wrap;padding:12px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0}
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
  <a class="back" href="javascript:history.back()">← Back to results</a>

  <!-- Header card -->
  <div class="card">
    <div class="trial-title"><?= htmlspecialchars($trial['brief_title']) ?></div>
    <div style="margin-top:6px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <span class="badge" style="background:<?= $badge_color ?>"><?= htmlspecialchars(str_replace('_',' ',$trial['overall_status'])) ?></span>
      <span style="font-size:.85rem;color:#64748b"><?= htmlspecialchars($trial['nct_id']) ?></span>
      <a href="https://clinicaltrials.gov/study/<?= htmlspecialchars($trial['nct_id']) ?>" target="_blank" rel="noopener" style="font-size:.85rem">
        ↗ View on ClinicalTrials.gov
      </a>
    </div>
    <div class="meta-grid">
      <div class="meta-item"><label>Study Type</label><span><?= htmlspecialchars($trial['study_type'] ?? '—') ?></span></div>
      <div class="meta-item"><label>Phase</label><span><?= htmlspecialchars(str_replace('_',' ',$trial['phase'] ?? 'N/A')) ?></span></div>
      <div class="meta-item"><label>Enrollment</label><span><?= fmt_num($trial['enrollment_count']) ?></span></div>
      <div class="meta-item"><label>Start Date</label><span><?= fmt_date($trial['start_date']) ?></span></div>
      <div class="meta-item"><label>Primary Completion</label><span><?= fmt_date($trial['primary_completion_date']) ?></span></div>
      <div class="meta-item"><label>Completion Date</label><span><?= fmt_date($trial['completion_date']) ?></span></div>
      <div class="meta-item"><label>Lead Sponsor</label><span><?= htmlspecialchars($trial['sponsor_name'] ?? '—') ?></span></div>
      <div class="meta-item"><label>Sponsor Type</label><span><?= htmlspecialchars($trial['sponsor_class'] ?? '—') ?></span></div>
      <div class="meta-item"><label>FDA Regulated Drug</label><span><?= $trial['is_fda_regulated_drug'] ? 'Yes' : 'No' ?></span></div>
      <div class="meta-item"><label>FDA Regulated Device</label><span><?= $trial['is_fda_regulated_device'] ? 'Yes' : 'No' ?></span></div>
      <div class="meta-item"><label>Sex Eligible</label><span><?= htmlspecialchars($trial['sex'] ?? '—') ?></span></div>
      <div class="meta-item"><label>Age Range</label><span><?= htmlspecialchars(($trial['min_age'] ?? '?') . ' – ' . ($trial['max_age'] ?? '?')) ?></span></div>
    </div>
  </div>

  <!-- Summary -->
  <?php if ($trial['brief_summary']): ?>
  <div class="card">
    <h2>Brief Summary</h2>
    <div class="summary"><?= nl2br(htmlspecialchars($trial['brief_summary'])) ?></div>
  </div>
  <?php endif ?>

  <!-- Interventions -->
  <?php if ($interventions): ?>
  <div class="card">
    <h2>Interventions (<?= count($interventions) ?>)</h2>
    <?php foreach ($interventions as $iv): ?>
      <span class="tag intervention"><?= htmlspecialchars($iv['type']) ?>: <?= htmlspecialchars($iv['name']) ?></span>
    <?php endforeach ?>
  </div>
  <?php endif ?>

  <!-- Conditions -->
  <?php if ($conditions): ?>
  <div class="card">
    <h2>Conditions (<?= count($conditions) ?>)</h2>
    <?php foreach ($conditions as $c): ?>
      <span class="tag"><?= htmlspecialchars($c['name']) ?></span>
    <?php endforeach ?>
  </div>
  <?php endif ?>

  <!-- Locations -->
  <?php if ($locations): ?>
  <div class="card">
    <h2>US Locations (<?= array_sum(array_column($locations,'sites')) ?> sites across <?= count($locations) ?> states)</h2>
    <div class="state-grid">
      <?php foreach ($locations as $loc): ?>
        <div class="state-chip"><?= htmlspecialchars($loc['state']) ?> <strong>(<?= $loc['sites'] ?>)</strong></div>
      <?php endforeach ?>
    </div>
  </div>
  <?php endif ?>

  <!-- Eligibility -->
  <?php if ($trial['eligibility_criteria']): ?>
  <div class="card">
    <h2>Eligibility Criteria</h2>
    <div class="elig"><?= htmlspecialchars($trial['eligibility_criteria']) ?></div>
  </div>
  <?php endif ?>

</div>

<footer>DiabetesTrialFinder &mdash; BIOI 4870 / CSCI 8876 &mdash; Bryce Theobald, UNO</footer>

</body>
</html>
