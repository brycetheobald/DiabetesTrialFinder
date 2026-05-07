-- DiabetesTrialFinder SQL Queries
-- BIOI 4870 / CSCI 8876 | Bryce Theobald | UNO
-- Database: diabetestrialfinder


-- 1. Which sponsors are running the most trials, and what kind are they?
-- Joins sponsors to trials so I can see whether industry or NIH is leading.
-- This is the join-dependent query — can't answer it from one table alone.

SELECT
    s.name              AS sponsor_name,
    s.class             AS sponsor_type,
    COUNT(t.trial_id)   AS trial_count
FROM sponsors s
JOIN trials t ON t.lead_sponsor_id = s.sponsor_id
GROUP BY s.sponsor_id, s.name, s.class
ORDER BY trial_count DESC
LIMIT 15;


-- 2. Which states have the most active Type 2 Diabetes trial sites?
-- 3-table join: trials → trial_locations → locations.
-- Useful for patients trying to find trials near them.

SELECT
    l.state                             AS state,
    COUNT(DISTINCT tl.trial_id)         AS trial_count,
    COUNT(tl.location_id)               AS total_sites
FROM locations l
JOIN trial_locations tl ON tl.location_id = l.location_id
JOIN trials t            ON t.trial_id     = tl.trial_id
WHERE l.country = 'United States'
  AND l.state   != ''
GROUP BY l.state
ORDER BY trial_count DESC
LIMIT 15;


-- 3. What is the average enrollment size by study phase?
-- Expected: Phase 3 should be the largest since it needs statistical power.

SELECT
    phase,
    COUNT(*)                                AS number_of_trials,
    ROUND(AVG(enrollment_count), 0)         AS avg_enrollment,
    MIN(enrollment_count)                   AS min_enrollment,
    MAX(enrollment_count)                   AS max_enrollment
FROM trials
WHERE enrollment_count IS NOT NULL
GROUP BY phase
ORDER BY avg_enrollment DESC;


-- 4. Which trials are currently recruiting and how big are they?
-- Filtered to RECRUITING status so patients can find open studies.

SELECT
    t.nct_id,
    t.brief_title,
    t.phase,
    t.enrollment_count,
    s.name      AS sponsor,
    s.class     AS sponsor_type
FROM trials t
JOIN sponsors s ON t.lead_sponsor_id = s.sponsor_id
WHERE t.overall_status = 'RECRUITING'
  AND t.enrollment_count IS NOT NULL
ORDER BY t.enrollment_count DESC
LIMIT 20;


-- 5. Which intervention types show up most in actively recruiting trials?
-- 4-table join through the junction table to see if drugs or behavioral
-- interventions dominate the current pipeline.

SELECT
    i.`type`                        AS intervention_type,
    COUNT(DISTINCT t.trial_id)      AS recruiting_trials
FROM trials t
JOIN trial_interventions ti ON ti.trial_id        = t.trial_id
JOIN interventions i        ON i.intervention_id  = ti.intervention_id
WHERE t.overall_status = 'RECRUITING'
GROUP BY i.`type`
ORDER BY recruiting_trials DESC;


-- 6. How do industry-sponsored trials compare to NIH/academic ones?
-- Groups by sponsor class to compare trial counts and enrollment averages.

SELECT
    s.class                             AS sponsor_type,
    COUNT(t.trial_id)                   AS total_trials,
    ROUND(AVG(t.enrollment_count), 0)   AS avg_enrollment,
    SUM(t.is_fda_regulated_drug)        AS fda_drug_trials,
    SUM(t.is_fda_regulated_device)      AS fda_device_trials
FROM trials t
JOIN sponsors s ON t.lead_sponsor_id = s.sponsor_id
WHERE t.enrollment_count IS NOT NULL
GROUP BY s.class
ORDER BY total_trials DESC;
