<?php
require_once 'db.php';

// ── Collect filter inputs ────────────────────────────────────────────────────
$keyword    = trim($_GET['keyword']    ?? '');
$status     = trim($_GET['status']     ?? '');
$phase      = trim($_GET['phase']      ?? '');
$state      = trim($_GET['state']      ?? '');
$min_enroll = trim($_GET['min_enroll'] ?? '');
$max_enroll = trim($_GET['max_enroll'] ?? '');
$sort       = trim($_GET['sort']       ?? 'date_desc');
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 25;
$offset     = ($page - 1) * $per_page;

// ── Populate filter dropdowns from actual DB values ──────────────────────────
$statuses = $db->query("SELECT DISTINCT overall_status FROM trials WHERE overall_status != '' ORDER BY overall_status")->fetch_all(MYSQLI_ASSOC);
$phases   = $db->query("SELECT DISTINCT phase FROM trials WHERE phase != '' ORDER BY phase")->fetch_all(MYSQLI_ASSOC);
$states   = $db->query("SELECT DISTINCT state FROM locations WHERE state != '' ORDER BY state")->fetch_all(MYSQLI_ASSOC);

// ── Build dynamic WHERE clause ───────────────────────────────────────────────
$where  = ['1=1'];
$types  = '';
$values = [];

if ($keyword !== '') {
    $where[]  = '(t.brief_title LIKE ? OR t.official_title LIKE ?)';
    $kw       = "%$keyword%";
    $types   .= 'ss';
    $values[] = $kw;
    $values[] = $kw;
}
if ($status !== '') {
    $where[]  = 't.overall_status = ?';
    $types   .= 's';
    $values[] = $status;
}
if ($phase !== '') {
    $where[]  = 't.phase = ?';
    $types   .= 's';
    $values[] = $phase;
}
if ($state !== '') {
    $where[]  = 'EXISTS (SELECT 1 FROM trial_locations tl JOIN locations l ON tl.location_id = l.location_id WHERE tl.trial_id = t.trial_id AND l.state = ?)';
    $types   .= 's';
    $values[] = $state;
}
if ($min_enroll !== '') {
    $where[]  = 't.enrollment_count >= ?';
    $types   .= 'i';
    $values[] = (int)$min_enroll;
}
if ($max_enroll !== '') {
    $where[]  = 't.enrollment_count <= ?';
    $types   .= 'i';
    $values[] = (int)$max_enroll;
}

$where_sql = implode(' AND ', $where);

$order_map = [
    'date_desc'    => 't.start_date DESC',
    'date_asc'     => 't.start_date ASC',
    'enroll_desc'  => 't.enrollment_count DESC',
    'enroll_asc'   => 't.enrollment_count ASC',
    'title_asc'    => 't.brief_title ASC',
];
$order_sql = $order_map[$sort] ?? 't.start_date DESC';

// ── Count total results ──────────────────────────────────────────────────────
$count_stmt = $db->prepare("SELECT COUNT(*) FROM trials t WHERE $where_sql");
if ($types) $count_stmt->bind_param($types, ...$values);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_row()[0];
$total_pages = max(1, ceil($total / $per_page));

// ── Fetch result page ────────────────────────────────────────────────────────
$data_sql = "
    SELECT t.trial_id, t.nct_id, t.brief_title, t.overall_status,
           t.phase, t.study_type, t.enrollment_count,
           t.start_date, t.completion_date, s.name AS sponsor
    FROM trials t
    LEFT JOIN sponsors s ON t.lead_sponsor_id = s.sponsor_id
    WHERE $where_sql
    ORDER BY $order_sql
    LIMIT ? OFFSET ?";

$stmt = $db->prepare($data_sql);
$page_types  = $types . 'ii';
$page_values = array_merge($values, [$per_page, $offset]);
$stmt->bind_param($page_types, ...$page_values);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Status badge colour map ──────────────────────────────────────────────────
$badge = [
    'RECRUITING'              => '#16a34a',
    'COMPLETED'               => '#2563eb',
    'ACTIVE_NOT_RECRUITING'   => '#d97706',
    'TERMINATED'              => '#dc2626',
    'WITHDRAWN'               => '#6b7280',
    'SUSPENDED'               => '#7c3aed',
    'NOT_YET_RECRUITING'      => '#0891b2',
];

// ── Query-string helper (preserves all filters except page) ─────────────────
function qs(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, fn($v) => $v !== ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DiabetesTrialFinder</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#f1f5f9;color:#1e293b;font-size:15px}
a{color:#2563eb;text-decoration:none}
a:hover{text-decoration:underline}

header{background:#1e3a5f;color:#fff;padding:14px 24px;display:flex;align-items:center;justify-content:space-between}
header h1{font-size:1.3rem;font-weight:700;letter-spacing:.3px}
header h1 span{color:#93c5fd}
nav a{color:#cbd5e1;margin-left:20px;font-size:.9rem}
nav a:hover{color:#fff;text-decoration:none}

.container{max-width:1200px;margin:0 auto;padding:20px}

.search-card{background:#fff;border-radius:10px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-bottom:20px}
.search-card h2{font-size:1rem;color:#475569;margin-bottom:14px;font-weight:600}
.filter-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;align-items:end}
.filter-group label{display:block;font-size:.78rem;font-weight:600;color:#64748b;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px}
.filter-group input,.filter-group select{width:100%;padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:.88rem;background:#f8fafc}
.filter-group input:focus,.filter-group select:focus{outline:2px solid #2563eb;border-color:#2563eb}
.btn{padding:8px 18px;background:#2563eb;color:#fff;border:none;border-radius:6px;font-size:.9rem;font-weight:600;cursor:pointer}
.btn:hover{background:#1d4ed8}
.btn-outline{background:#fff;color:#2563eb;border:1px solid #2563eb}
.btn-outline:hover{background:#eff6ff}

.result-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.result-count{font-size:.9rem;color:#64748b}

table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)}
th{background:#1e3a5f;color:#e2e8f0;padding:11px 14px;text-align:left;font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;font-weight:600}
td{padding:11px 14px;border-bottom:1px solid #f1f5f9;font-size:.88rem;vertical-align:top}
tr:last-child td{border-bottom:none}
tr:hover td{background:#f8fafc}

.badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:.75rem;font-weight:600;color:#fff}
.trial-title{font-weight:500;color:#1e293b}
.ext-link{font-size:.75rem;color:#64748b}

.pagination{margin-top:16px;display:flex;gap:8px;align-items:center;justify-content:center;flex-wrap:wrap}
.pagination a,.pagination span{padding:6px 12px;border-radius:6px;font-size:.85rem;border:1px solid #cbd5e1;background:#fff;color:#374151}
.pagination a:hover{background:#eff6ff;border-color:#2563eb;text-decoration:none}
.pagination .active{background:#2563eb;color:#fff;border-color:#2563eb}

footer{text-align:center;padding:20px;color:#94a3b8;font-size:.8rem;margin-top:20px}
footer a{color:#94a3b8}
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

  <!-- Search form -->
  <div class="search-card">
    <h2>Search Type 2 Diabetes Clinical Trials (United States)</h2>
    <form method="get" action="index.php">
      <div class="filter-grid">
        <div class="filter-group" style="grid-column:span 2">
          <label>Keyword</label>
          <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" placeholder="e.g. insulin, metformin, lifestyle...">
        </div>
        <div class="filter-group">
          <label>Status</label>
          <select name="status">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $r): ?>
              <option value="<?= htmlspecialchars($r['overall_status']) ?>" <?= $status === $r['overall_status'] ? 'selected' : '' ?>>
                <?= htmlspecialchars(str_replace('_', ' ', $r['overall_status'])) ?>
              </option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="filter-group">
          <label>Phase</label>
          <select name="phase">
            <option value="">All phases</option>
            <?php foreach ($phases as $r): ?>
              <option value="<?= htmlspecialchars($r['phase']) ?>" <?= $phase === $r['phase'] ? 'selected' : '' ?>>
                <?= htmlspecialchars(str_replace('_', ' ', $r['phase'])) ?>
              </option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="filter-group">
          <label>State</label>
          <select name="state">
            <option value="">All states</option>
            <?php foreach ($states as $r): ?>
              <option value="<?= htmlspecialchars($r['state']) ?>" <?= $state === $r['state'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['state']) ?>
              </option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="filter-group">
          <label>Min Enrollment</label>
          <input type="number" name="min_enroll" value="<?= htmlspecialchars($min_enroll) ?>" placeholder="e.g. 100" min="0">
        </div>
        <div class="filter-group">
          <label>Max Enrollment</label>
          <input type="number" name="max_enroll" value="<?= htmlspecialchars($max_enroll) ?>" placeholder="e.g. 5000" min="0">
        </div>
        <div class="filter-group">
          <label>Sort By</label>
          <select name="sort">
            <option value="date_desc"   <?= $sort==='date_desc'   ? 'selected':'' ?>>Newest first</option>
            <option value="date_asc"    <?= $sort==='date_asc'    ? 'selected':'' ?>>Oldest first</option>
            <option value="enroll_desc" <?= $sort==='enroll_desc' ? 'selected':'' ?>>Largest enrollment</option>
            <option value="enroll_asc"  <?= $sort==='enroll_asc'  ? 'selected':'' ?>>Smallest enrollment</option>
            <option value="title_asc"   <?= $sort==='title_asc'   ? 'selected':'' ?>>Title A–Z</option>
          </select>
        </div>
        <div class="filter-group" style="display:flex;gap:8px;align-items:flex-end">
          <button class="btn" type="submit">Search</button>
          <a class="btn btn-outline" href="index.php">Reset</a>
        </div>
      </div>
    </form>
  </div>

  <!-- Results -->
  <div class="result-bar">
    <span class="result-count">
      <?= number_format($total) ?> trial<?= $total !== 1 ? 's' : '' ?> found
      <?= $keyword ? ' for "' . htmlspecialchars($keyword) . '"' : '' ?>
    </span>
    <span style="font-size:.8rem;color:#94a3b8">Page <?= $page ?> of <?= $total_pages ?></span>
  </div>

  <?php if (empty($results)): ?>
    <p style="text-align:center;color:#64748b;padding:40px">No trials match your search. Try different filters.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th style="width:38%">Trial Title</th>
        <th>Status</th>
        <th>Phase</th>
        <th>Sponsor</th>
        <th>Enrollment</th>
        <th>Start Date</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($results as $r): ?>
      <?php $color = $badge[$r['overall_status']] ?? '#6b7280'; ?>
      <tr>
        <td>
          <a class="trial-title" href="trial.php?id=<?= $r['trial_id'] ?>">
            <?= htmlspecialchars($r['brief_title']) ?>
          </a><br>
          <a class="ext-link" href="https://clinicaltrials.gov/study/<?= htmlspecialchars($r['nct_id']) ?>" target="_blank" rel="noopener">
            ↗ <?= htmlspecialchars($r['nct_id']) ?>
          </a>
        </td>
        <td><span class="badge" style="background:<?= $color ?>"><?= htmlspecialchars(str_replace('_',' ',$r['overall_status'])) ?></span></td>
        <td><?= htmlspecialchars(str_replace('_',' ',$r['phase'] ?? 'N/A')) ?></td>
        <td><?= htmlspecialchars($r['sponsor'] ?? '—') ?></td>
        <td><?= $r['enrollment_count'] !== null ? number_format($r['enrollment_count']) : '—' ?></td>
        <td><?= $r['start_date'] ? date('M Y', strtotime($r['start_date'])) : '—' ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="<?= qs(['page' => $page - 1]) ?>">← Prev</a>
    <?php endif ?>
    <?php for ($p = max(1, $page-2); $p <= min($total_pages, $page+2); $p++): ?>
      <?php if ($p === $page): ?>
        <span class="active"><?= $p ?></span>
      <?php else: ?>
        <a href="<?= qs(['page' => $p]) ?>"><?= $p ?></a>
      <?php endif ?>
    <?php endfor ?>
    <?php if ($page < $total_pages): ?>
      <a href="<?= qs(['page' => $page + 1]) ?>">Next →</a>
    <?php endif ?>
  </div>
  <?php endif ?>
  <?php endif ?>

</div>

<footer>
  <p>DiabetesTrialFinder &mdash; BIOI 4870 / CSCI 8876 &mdash; Bryce Theobald, University of Nebraska at Omaha</p>
  <p style="margin-top:6px">Data sourced from <a href="https://clinicaltrials.gov" target="_blank">ClinicalTrials.gov</a> (public API) &mdash;
  <a href="https://docs.google.com/forms/d/e/1FAIpQLSf_placeholder/viewform" target="_blank">Report an accessibility issue</a></p>
</footer>

</body>
</html>
