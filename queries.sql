-- ============================================================
-- DiabetesTrialFinder — Stakeholder-Driven SQL Queries
-- BIOI 4870 / CSCI 8876 — Bryce Theobald, UNO
-- Database: diabetestrialfinder
-- ============================================================

-- ============================================================
-- QUERY 1 — Multi-table join (JOIN-DEPENDENT REQUIREMENT)
-- Stakeholder question: Which sponsors are running the most
-- trials, and what type of organization are they?
-- Why it matters: Helps researchers and patients understand
-- whether trials are industry-driven (pharmaceutical companies)
-- or led by academic/government institutions, which affects
-- how results may be interpreted or funded.
-- Demonstrates: 2-table JOIN, COUNT aggregation, GROUP BY, ORDER BY
-- ============================================================

SELECT
    s.name              AS sponsor_name,
    s.class             AS sponsor_type,
    COUNT(t.trial_id)   AS trial_count
FROM sponsors s
JOIN trials t ON t.lead_sponsor_id = s.sponsor_id
GROUP BY s.sponsor_id, s.name, s.class
ORDER BY trial_count DESC
LIMIT 15;


-- ============================================================
-- QUERY 2 — Multi-table join across 3 tables
-- Stakeholder question: Which states have the most active
-- Type 2 Diabetes clinical trial sites?
-- Why it matters: Patients and clinicians can identify where
-- trials are geographically concentrated, helping patients
-- find nearby opportunities to participate.
-- Demonstrates: 3-table JOIN (trials → trial_locations → locations),
-- COUNT, GROUP BY, ORDER BY
-- ============================================================

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


-- ============================================================
-- QUERY 3 — Aggregation with GROUP BY
-- Stakeholder question: What is the average enrollment size
-- for trials in each study phase?
-- Why it matters: Phase 3 trials are expected to be larger
-- than Phase 1. This query confirms whether enrollment sizes
-- align with standard clinical trial design expectations,
-- and helps researchers set realistic recruitment targets.
-- Demonstrates: AVG aggregation, GROUP BY, ORDER BY
-- ============================================================

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


-- ============================================================
-- QUERY 4 — Filtered analysis with WHERE clause
-- Stakeholder question: Which trials are currently recruiting
-- participants, and how large are they?
-- Why it matters: Patients looking to join a trial need to
-- find actively recruiting studies. This query surfaces only
-- open trials sorted by enrollment size so patients can
-- identify high-visibility studies first.
-- Demonstrates: WHERE filter, JOIN, ORDER BY
-- ============================================================

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


-- ============================================================
-- QUERY 5 — Multi-table join with aggregation (4 tables)
-- Stakeholder question: Which intervention types appear most
-- often in currently recruiting trials?
-- Why it matters: Public health researchers and clinicians
-- want to know whether the active trial pipeline is focused
-- on drugs, behavioral changes, devices, or other approaches.
-- This shapes funding priorities and treatment development.
-- Demonstrates: 4-table JOIN (trials → trial_interventions →
-- interventions, filtered by status), COUNT, GROUP BY
-- ============================================================

SELECT
    i.`type`                        AS intervention_type,
    COUNT(DISTINCT t.trial_id)      AS recruiting_trials
FROM trials t
JOIN trial_interventions ti ON ti.trial_id        = t.trial_id
JOIN interventions i        ON i.intervention_id  = ti.intervention_id
WHERE t.overall_status = 'RECRUITING'
GROUP BY i.`type`
ORDER BY recruiting_trials DESC;


-- ============================================================
-- QUERY 6 — Grouped comparison with GROUP BY
-- Stakeholder question: How do trial counts and average
-- enrollment differ between industry-sponsored trials and
-- those led by academic or government institutions?
-- Why it matters: Industry trials tend to test proprietary
-- drugs and may have different enrollment targets than NIH or
-- university-led studies. This comparison helps administrators
-- and policymakers understand the sponsor landscape and
-- potential conflicts of interest.
-- Demonstrates: JOIN, GROUP BY on categorical variable,
-- COUNT + AVG aggregation, comparative analysis
-- ============================================================

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


-- ============================================================
-- QUERY 7 — Cross-source analysis (3 data sources)
-- Stakeholder question: Which states have the highest diabetes
-- prevalence but the fewest clinical trials per million people?
-- Why it matters: Identifies geographic gaps where clinical
-- research investment does not match the disease burden —
-- pointing to underserved populations that may benefit most
-- from new treatments but have the least access to trials.
-- Demonstrates: 3-source JOIN (ClinicalTrials.gov + CDC + Census),
-- LEFT JOIN, aggregate, calculated per-capita column
-- ============================================================

SELECT
    cdp.state_name,
    cdp.state_abbrev,
    cdp.prevalence_pct                                          AS diabetes_prevalence_pct,
    csp.population,
    COUNT(DISTINCT tl.trial_id)                                 AS trial_site_count,
    ROUND(COUNT(DISTINCT tl.trial_id)
          / (csp.population / 1000000.0), 2)                   AS trials_per_million
FROM cdc_diabetes_prevalence cdp
JOIN  census_state_population csp ON csp.state_abbrev = cdp.state_abbrev
LEFT JOIN locations l              ON l.state          = cdp.state_name
LEFT JOIN trial_locations tl       ON tl.location_id   = l.location_id
WHERE cdp.year = 2021
  AND csp.census_year = 2021
GROUP BY cdp.state_name, cdp.state_abbrev, cdp.prevalence_pct, csp.population
ORDER BY cdp.prevalence_pct DESC
LIMIT 15;


-- ============================================================
-- QUERY 8 — Cross-source ranking
-- Stakeholder question: Among the 10 most populous states,
-- how does trial volume compare to diabetes burden?
-- Why it matters: Large-population states should in theory
-- have more trial sites. This query checks whether that holds
-- and surfaces any large states that are underrepresented.
-- Demonstrates: Multi-source JOIN, ORDER BY population,
-- per-capita trial density, LIMIT
-- ============================================================

SELECT
    csp.state_name,
    csp.population,
    cdp.prevalence_pct                                          AS diabetes_prevalence_pct,
    COUNT(DISTINCT tl.trial_id)                                 AS trial_site_count,
    ROUND(COUNT(DISTINCT tl.trial_id)
          / (csp.population / 1000000.0), 2)                   AS trials_per_million
FROM census_state_population csp
JOIN  cdc_diabetes_prevalence cdp ON cdp.state_abbrev = csp.state_abbrev
                                  AND cdp.year = 2021
LEFT JOIN locations l              ON l.state          = csp.state_name
LEFT JOIN trial_locations tl       ON tl.location_id   = l.location_id
WHERE csp.census_year = 2021
GROUP BY csp.state_name, csp.population, cdp.prevalence_pct
ORDER BY csp.population DESC
LIMIT 10;
