# DiabetesTrialFinder

A relational database for exploring Type 2 Diabetes clinical trials sourced from the [ClinicalTrials.gov](https://clinicaltrials.gov) public API.

Built for **BIOI 4870 / CSCI 8876** — University of Nebraska at Omaha  
**Author:** Bryce Theobald

---

## Overview

DiabetesTrialFinder loads 1,000 Type 2 Diabetes clinical trials from ClinicalTrials.gov into a normalized MySQL database, and provides:

- A **Tableau workbook** with interactive dashboards connecting directly to the MySQL database
- **Six stakeholder-driven SQL queries** for knowledge discovery across the integrated dataset

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Database | MySQL 8 (via MAMP) |
| Data loader | Python 3 + Requests |
| Visualization | Tableau Desktop |
| Local server | MAMP (MySQL, port 8889) |

---

## Database Schema

10 normalized tables (3NF):

```
sponsors          → trials (lead_sponsor_id)
trials            ↔ interventions   (via trial_interventions)
trials            ↔ conditions      (via trial_conditions)
trials            ↔ locations       (via trial_locations)
```

---

## Setup & Usage

### 1. Prerequisites
- [MAMP](https://www.mamp.info/) running with MySQL on port 8889
- Python 3 with `requests` and `mysql-connector-python`

### 2. Create the database
```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 < schema.sql
```

### 3. Load trial data
```bash
python3 fetch_trials.py
python3 fetch_cdc.py
python3 fetch_census.py
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 diabetestrialfinder < data_insert.sql
```

### 4. Open in Tableau
Connect Tableau Desktop to MySQL (localhost:8889, database: diabetestrialfinder) or open the packaged `1 - Tableau Workbook.twbx` directly.

---

## Data Source

Data sourced from three public APIs:

- [ClinicalTrials.gov API v2](https://clinicaltrials.gov/data-api/api) — 1,000 Type 2 Diabetes trials, United States only
- [CDC BRFSS via Socrata](https://chronicdata.cdc.gov/) — state-level diabetes prevalence and obesity rates
- [US Census Bureau ACS API](https://www.census.gov/data/developers/data-sets/acs-1year.html) — state population estimates

---

## License

MIT License — see [LICENSE](LICENSE)
