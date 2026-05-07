-- DiabetesTrialFinder Database Schema
-- BIOI 4870 / CSCI 8876 | Bryce Theobald | UNO
-- Source: ClinicalTrials.gov API v2

CREATE DATABASE IF NOT EXISTS diabetestrialfinder;
USE diabetestrialfinder;

-- ─────────────────────────────────────────────
-- 1. Sponsors (lead sponsor per trial)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sponsors (
    sponsor_id   INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(300) NOT NULL,
    class        VARCHAR(50),           -- INDUSTRY, NIH, OTHER, FED, etc.
    UNIQUE KEY uq_sponsor_name (name)
);

-- ─────────────────────────────────────────────
-- 2. Trials (core table, one row per NCT record)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS trials (
    trial_id                INT AUTO_INCREMENT PRIMARY KEY,
    nct_id                  VARCHAR(20)  NOT NULL UNIQUE,
    brief_title             VARCHAR(500),
    official_title          TEXT,
    overall_status          VARCHAR(50),  -- RECRUITING, COMPLETED, TERMINATED, etc.
    study_type              VARCHAR(50),  -- INTERVENTIONAL, OBSERVATIONAL
    phase                   VARCHAR(100), -- PHASE1, PHASE2, PHASE3, PHASE4, N/A
    enrollment_count        INT,
    start_date              DATE,
    primary_completion_date DATE,
    completion_date         DATE,
    brief_summary           TEXT,
    is_fda_regulated_drug   TINYINT(1),
    is_fda_regulated_device TINYINT(1),
    eligibility_criteria    TEXT,
    min_age                 VARCHAR(30),  -- stored as string e.g. "18 Years"
    max_age                 VARCHAR(30),
    sex                     VARCHAR(20),  -- ALL, FEMALE, MALE
    lead_sponsor_id         INT,
    FOREIGN KEY (lead_sponsor_id) REFERENCES sponsors(sponsor_id)
);

-- ─────────────────────────────────────────────
-- 3. Interventions (DRUG, DEVICE, BEHAVIORAL, etc.)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS interventions (
    intervention_id INT AUTO_INCREMENT PRIMARY KEY,
    type            VARCHAR(100),        -- DRUG, DEVICE, BEHAVIORAL, OTHER, etc.
    name            VARCHAR(500),
    UNIQUE KEY uq_intervention (type, name)
);

-- ─────────────────────────────────────────────
-- 4. Trial ↔ Intervention (many-to-many)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS trial_interventions (
    trial_id        INT NOT NULL,
    intervention_id INT NOT NULL,
    PRIMARY KEY (trial_id, intervention_id),
    FOREIGN KEY (trial_id)        REFERENCES trials(trial_id),
    FOREIGN KEY (intervention_id) REFERENCES interventions(intervention_id)
);

-- ─────────────────────────────────────────────
-- 5. Conditions (diabetes subtypes, comorbidities)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS conditions (
    condition_id INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(500) NOT NULL UNIQUE
);

-- ─────────────────────────────────────────────
-- 6. Trial ↔ Condition (many-to-many)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS trial_conditions (
    trial_id     INT NOT NULL,
    condition_id INT NOT NULL,
    PRIMARY KEY (trial_id, condition_id),
    FOREIGN KEY (trial_id)     REFERENCES trials(trial_id),
    FOREIGN KEY (condition_id) REFERENCES conditions(condition_id)
);

-- ─────────────────────────────────────────────
-- 7. Locations (facility-level, US only)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS locations (
    location_id   INT AUTO_INCREMENT PRIMARY KEY,
    facility_name VARCHAR(500),
    city          VARCHAR(100),
    state         VARCHAR(100),
    country       VARCHAR(100),
    UNIQUE KEY uq_location (facility_name, city, state)
);

-- ─────────────────────────────────────────────
-- 8. Trial ↔ Location (many-to-many)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS trial_locations (
    trial_id    INT NOT NULL,
    location_id INT NOT NULL,
    PRIMARY KEY (trial_id, location_id),
    FOREIGN KEY (trial_id)    REFERENCES trials(trial_id),
    FOREIGN KEY (location_id) REFERENCES locations(location_id)
);

-- ─────────────────────────────────────────────
-- 9. CDC Diabetes Prevalence by State
--    Source: CDC Chronic Disease Indicators (CDI)
--            U.S. BRFSS, Age-Adjusted Prevalence, 2021
--            https://data.cdc.gov/resource/hksd-2xuw.json
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cdc_diabetes_prevalence (
    state_id       INT AUTO_INCREMENT PRIMARY KEY,
    state_name     VARCHAR(100) NOT NULL,
    state_abbrev   VARCHAR(2),
    year           INT,
    prevalence_pct DECIMAL(5,2),  -- % of adults with diagnosed diabetes (age-adjusted)
    lower_ci       DECIMAL(5,2),  -- 95% confidence interval lower bound
    upper_ci       DECIMAL(5,2),  -- 95% confidence interval upper bound
    data_source    VARCHAR(200) DEFAULT 'CDC BRFSS, Chronic Disease Indicators 2021',
    UNIQUE KEY uq_state_year (state_abbrev, year)
);

-- ─────────────────────────────────────────────
-- 10. US Census Bureau State Population
--     Source: ACS 5-Year Estimates, 2021
--             Variable B01003_001E (Total Population)
--             https://api.census.gov/data/2021/acs/acs5
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS census_state_population (
    state_id     INT AUTO_INCREMENT PRIMARY KEY,
    state_name   VARCHAR(100) NOT NULL,
    state_abbrev VARCHAR(2),
    census_year  INT,
    population   INT,
    data_source  VARCHAR(200) DEFAULT 'US Census Bureau, ACS 5-Year Estimates 2021',
    UNIQUE KEY uq_state_census (state_abbrev, census_year)
);
