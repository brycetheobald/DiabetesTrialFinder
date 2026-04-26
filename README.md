# DiabetesTrialFinder

A relational database and web application for exploring Type 2 Diabetes clinical trials sourced from the [ClinicalTrials.gov](https://clinicaltrials.gov) public API.

Built for **BIOI 4870 / CSCI 8876** — University of Nebraska at Omaha  
**Author:** Bryce Theobald

---

## Overview

DiabetesTrialFinder loads 1,000 Type 2 Diabetes clinical trials from ClinicalTrials.gov into a normalized MySQL database, and provides:

- A **PHP web interface** for searching, filtering, and browsing trials
- A **statistics dashboard** with CSS bar charts
- **Interactive Plotly visualizations** (US choropleth map, status/phase charts, sponsor rankings)
- A **Tableau workbook** connecting directly to the MySQL database

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Database | MySQL 8 (via MAMP) |
| Backend | PHP 8.3 with MySQLi |
| Data loader | Python 3 + Requests |
| Visualization | Python + Plotly, Tableau Desktop |
| Local server | MAMP (Apache + MySQL, ports 8888/8889) |

---

## Database Schema

8 normalized tables (3NF):

```
sponsors          → trials (lead_sponsor_id)
trials            ↔ interventions   (via trial_interventions)
trials            ↔ conditions      (via trial_conditions)
trials            ↔ locations       (via trial_locations)
```

---

## Setup & Usage

### 1. Prerequisites
- [MAMP](https://www.mamp.info/) running on ports 8888 (Apache) and 8889 (MySQL)
- Python 3 with `requests`, `mysql-connector-python`, `plotly`, `pandas`

### 2. Create the database
```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot < schema.sql
```

### 3. Load trial data
```bash
python3 fetch_trials.py
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot diabetestrialfinder < data_insert.sql
```

### 4. Deploy the web interface
Copy the PHP files to your MAMP htdocs folder:
```bash
cp db.php index.php trial.php stats.php /Applications/MAMP/htdocs/DiabetesTrialFinder/
```

Then visit: `http://localhost:8888/DiabetesTrialFinder/`

### 5. Generate Plotly charts
```bash
pip3 install mysql-connector-python plotly pandas
python3 visualize.py
```

---

## Data Source

Data sourced from [ClinicalTrials.gov API v2](https://clinicaltrials.gov/data-api/api) — 1,000 Type 2 Diabetes trials, United States only.

---

## License

MIT License — see [LICENSE](LICENSE)
