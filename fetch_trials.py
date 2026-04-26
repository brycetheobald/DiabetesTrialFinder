#!/usr/bin/env python3
"""
fetch_trials.py
Pulls Type 2 Diabetes clinical trials from ClinicalTrials.gov API v2
and generates data_insert.sql for import into the Odin MariaDB database.

Usage:
    python3 fetch_trials.py
Then copy data_insert.sql to Odin and run:
    /Applications/MAMP/Library/bin/mysql -u root -proot diabetestrialfinder < data_insert.sql
"""

import requests
import json
import sys
from datetime import datetime

API_BASE   = "https://clinicaltrials.gov/api/v2/studies"
OUTPUT_SQL = "data_insert.sql"
MAX_TRIALS = 1000   # target record count (actual may be lower)
PAGE_SIZE  = 100    # max allowed by the API

# ─────────────────────────────────────────────
# 1. Fetch from API
# ─────────────────────────────────────────────

def fetch_trials():
    params = {
        "query.cond": "Type 2 Diabetes",
        "filter.geo": "distance(39.8,-98.5,2500mi)",  # continental US
        "pageSize":   PAGE_SIZE,
        "format":     "json",
    }
    studies    = []
    page_token = None

    while len(studies) < MAX_TRIALS:
        if page_token:
            params["pageToken"] = page_token

        try:
            resp = requests.get(API_BASE, params=params, timeout=30)
            resp.raise_for_status()
        except requests.RequestException as e:
            print(f"API error: {e}", file=sys.stderr)
            break

        data  = resp.json()
        batch = data.get("studies", [])
        if not batch:
            break

        studies.extend(batch)
        print(f"  Fetched {len(studies)} trials...")

        page_token = data.get("nextPageToken")
        if not page_token:
            break

    return studies[:MAX_TRIALS]

# ─────────────────────────────────────────────
# 2. SQL helpers
# ─────────────────────────────────────────────

def esc(value):
    """Wrap a value in SQL-safe single quotes, or return NULL."""
    if value is None or value == "":
        return "NULL"
    value = str(value).replace("\\", "\\\\").replace("'", "\\'")
    return f"'{value}'"

def parse_date(date_str):
    """Accept YYYY-MM-DD or YYYY-MM; return quoted SQL date or NULL."""
    if not date_str:
        return "NULL"
    try:
        if len(date_str) == 7:          # YYYY-MM — pad to first of month
            date_str += "-01"
        datetime.strptime(date_str, "%Y-%m-%d")
        return f"'{date_str}'"
    except ValueError:
        return "NULL"

def chunked(lst, n):
    """Split a list into sublists of length n."""
    for i in range(0, len(lst), n):
        yield lst[i:i + n]

# ─────────────────────────────────────────────
# 3. Parse studies → SQL rows
# ─────────────────────────────────────────────

def build_sql(studies):
    sponsors      = {}  # name -> sponsor_id
    interventions = {}  # (type, name) -> intervention_id
    conditions    = {}  # name -> condition_id
    locations     = {}  # (facility, city, state) -> location_id

    sponsor_rows           = []
    trial_rows             = []
    intervention_rows      = []
    trial_intervention_rows = []
    condition_rows         = []
    trial_condition_rows   = []
    location_rows          = []
    trial_location_rows    = []

    s_id = t_id = iv_id = c_id = l_id = 1

    for study in studies:
        proto = study.get("protocolSection", {})

        # Identification
        id_mod        = proto.get("identificationModule", {})
        nct_id        = id_mod.get("nctId", "")
        brief_title   = id_mod.get("briefTitle", "")
        official_title = id_mod.get("officialTitle", "")

        # Status
        stat          = proto.get("statusModule", {})
        overall_status = stat.get("overallStatus", "")
        start_date    = parse_date(stat.get("startDateStruct",            {}).get("date"))
        prim_compl    = parse_date(stat.get("primaryCompletionDateStruct", {}).get("date"))
        compl_date    = parse_date(stat.get("completionDateStruct",        {}).get("date"))

        # Sponsor
        sc_mod     = proto.get("sponsorCollaboratorsModule", {})
        lead       = sc_mod.get("leadSponsor", {})
        lead_name  = lead.get("name",  "Unknown") or "Unknown"
        lead_class = lead.get("class", "")
        if lead_name not in sponsors:
            sponsors[lead_name] = s_id
            sponsor_rows.append(f"({s_id}, {esc(lead_name)}, {esc(lead_class)})")
            s_id += 1

        # Oversight
        ov        = proto.get("oversightModule", {})
        is_drug   = 1 if ov.get("isFdaRegulatedDrug")   else 0
        is_device = 1 if ov.get("isFdaRegulatedDevice") else 0

        # Description
        brief_summary = proto.get("descriptionModule", {}).get("briefSummary", "")

        # Design
        design     = proto.get("designModule", {})
        study_type = design.get("studyType", "")
        phases     = design.get("phases", [])
        phase      = phases[0] if phases else "N/A"
        enroll     = design.get("enrollmentInfo", {}).get("count")
        enroll_val = str(enroll) if enroll is not None else "NULL"

        # Eligibility
        elig     = proto.get("eligibilityModule", {})
        elig_txt = elig.get("eligibilityCriteria", "")
        min_age  = elig.get("minimumAge", "")
        max_age  = elig.get("maximumAge", "")
        sex      = elig.get("sex", "ALL")

        # Trial row
        trial_rows.append(
            f"({t_id}, {esc(nct_id)}, {esc(brief_title)}, {esc(official_title)}, "
            f"{esc(overall_status)}, {esc(study_type)}, {esc(phase)}, {enroll_val}, "
            f"{start_date}, {prim_compl}, {compl_date}, {esc(brief_summary)}, "
            f"{is_drug}, {is_device}, {esc(elig_txt)}, {esc(min_age)}, {esc(max_age)}, "
            f"{esc(sex)}, {sponsors[lead_name]})"
        )

        # Interventions
        for iv in proto.get("armsInterventionsModule", {}).get("interventions", []):
            iv_type = iv.get("type", "").strip()
            iv_name = iv.get("name", "").strip()
            key = (iv_type.lower(), iv_name.lower())   # normalize for dedup
            if key not in interventions:
                interventions[key] = iv_id
                intervention_rows.append(f"({iv_id}, {esc(iv_type)}, {esc(iv_name)})")
                iv_id += 1
            trial_intervention_rows.append(f"({t_id}, {interventions[key]})")

        # Conditions
        for cond in proto.get("conditionsModule", {}).get("conditions", []):
            cond_key = cond.strip().lower()
            if cond_key not in conditions:
                conditions[cond_key] = c_id
                condition_rows.append(f"({c_id}, {esc(cond.strip())})")
                c_id += 1
            trial_condition_rows.append(f"({t_id}, {conditions[cond_key]})")

        # Locations (US only)
        for loc in proto.get("contactsLocationsModule", {}).get("locations", []):
            if loc.get("country") != "United States":
                continue
            facility = loc.get("facility", "").strip()
            city     = loc.get("city",     "").strip()
            state    = loc.get("state",    "").strip()
            key      = (facility.lower(), city.lower(), state.lower())
            if key not in locations:
                locations[key] = l_id
                location_rows.append(
                    f"({l_id}, {esc(facility)}, {esc(city)}, {esc(state)}, 'United States')"
                )
                l_id += 1
            trial_location_rows.append(f"({t_id}, {locations[key]})")

        t_id += 1

    # ── Assemble SQL file ──────────────────────────────────────────────
    lines = [
        "-- DiabetesTrialFinder data import",
        f"-- Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}",
        f"-- Trials: {len(studies)}",
        "CREATE DATABASE IF NOT EXISTS diabetestrialfinder;",
        "USE diabetestrialfinder;",
        "SET FOREIGN_KEY_CHECKS=0;",
        "",
    ]

    def write_inserts(header, sql, rows, chunk_size=50, ignore=False):
        if not rows:
            return
        verb = "INSERT IGNORE INTO" if ignore else "INSERT INTO"
        lines.append(f"-- {header}")
        for chunk in chunked(rows, chunk_size):
            lines.append(f"{verb} {sql} VALUES")
            lines.append(",\n".join(chunk) + ";")
        lines.append("")

    write_inserts(
        "Sponsors",
        "sponsors (sponsor_id, name, class)",
        sponsor_rows,
        ignore=True,
    )
    write_inserts(
        "Trials",
        "trials (trial_id, nct_id, brief_title, official_title, overall_status, "
        "study_type, phase, enrollment_count, start_date, primary_completion_date, "
        "completion_date, brief_summary, is_fda_regulated_drug, is_fda_regulated_device, "
        "eligibility_criteria, min_age, max_age, sex, lead_sponsor_id)",
        trial_rows,
        chunk_size=20,
    )
    write_inserts(
        "Interventions",
        "interventions (intervention_id, type, name)",
        intervention_rows,
        ignore=True,
    )
    write_inserts(
        "Trial-Intervention links",
        "trial_interventions (trial_id, intervention_id)",
        trial_intervention_rows,
        chunk_size=100,
        ignore=True,
    )
    write_inserts(
        "Conditions",
        "conditions (condition_id, name)",
        condition_rows,
        ignore=True,
    )
    write_inserts(
        "Trial-Condition links",
        "trial_conditions (trial_id, condition_id)",
        trial_condition_rows,
        chunk_size=100,
        ignore=True,
    )
    write_inserts(
        "Locations",
        "locations (location_id, facility_name, city, state, country)",
        location_rows,
        ignore=True,
    )
    write_inserts(
        "Trial-Location links",
        "trial_locations (trial_id, location_id)",
        trial_location_rows,
        chunk_size=100,
        ignore=True,
    )

    lines.append("SET FOREIGN_KEY_CHECKS=1;")
    lines.append(f"-- Done. {len(studies)} trials imported.")
    return "\n".join(lines)

# ─────────────────────────────────────────────
# 4. Main
# ─────────────────────────────────────────────

if __name__ == "__main__":
    print("Fetching trials from ClinicalTrials.gov...")
    studies = fetch_trials()
    print(f"Total fetched: {len(studies)} trials\n")

    print("Building SQL...")
    sql = build_sql(studies)

    with open(OUTPUT_SQL, "w", encoding="utf-8") as f:
        f.write(sql)

    print(f"Done! Wrote {OUTPUT_SQL}")
    print(f"  Lines: {sql.count(chr(10)):,}")
    print(f"\nNext step on Odin:")
    print(f"  /Applications/MAMP/Library/bin/mysql -u root -proot diabetestrialfinder < {OUTPUT_SQL}")
