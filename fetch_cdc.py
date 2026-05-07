#!/usr/bin/env python3
"""
fetch_cdc.py

Purpose:
    Pull state-level diabetes prevalence data from the CDC and load it
    into the cdc_diabetes_prevalence table in MySQL. I'm using this as
    a second data source so I can cross-reference which states have high
    disease burden but not many clinical trials nearby.

How it works:
    The CDC publishes their Chronic Disease Indicators dataset through
    the data.cdc.gov Socrata API. I filter down to just the 2021
    age-adjusted prevalence numbers for all 50 states + DC, then
    do a full TRUNCATE and reload each time so I'm not accumulating
    duplicates if I run this more than once.

Data source:
    CDC Chronic Disease Indicators (CDI) — Diabetes
    https://data.cdc.gov/resource/hksd-2xuw.json
    BRFSS, age-adjusted prevalence, 2021

Usage:
    python3 fetch_cdc.py

Notes:
    - MAMP has to be running or the MySQL connection will fail
    - The Socrata API doesn't require a key for public datasets
    - I'm filtering to 2-char locationabbr values to drop the national
      totals and territories (they come through as longer strings)
"""

import requests
import sys
import mysql.connector

# ── Config ───────────────────────────────────────────────────────────────────

CDC_API = "https://data.cdc.gov/resource/hksd-2xuw.json"

DB_CONFIG = {
    "host":     "127.0.0.1",
    "port":     8889,
    "user":     "root",
    "password": "root",
    "database": "diabetestrialfinder",
}

# ── Fetch ─────────────────────────────────────────────────────────────────────

def fetch_cdc_data():
    """Hit the CDC API and return state-level records for 2021."""
    params = {
        "topic":                     "Diabetes",
        "question":                  "Diabetes among adults",
        "datavaluetypeid":           "AGEADJPREV",   # age-adjusted prevalence
        "yearend":                   "2021",
        "stratificationcategoryid1": "OVERALL",       # no demographic breakdown
        "$limit":                    60,
    }
    try:
        resp = requests.get(CDC_API, params=params, timeout=30)
        resp.raise_for_status()
        data = resp.json()

        # Drop anything that isn't an actual state — national totals and
        # territories show up with longer abbreviations like "US" or "PR"
        states = [
            r for r in data
            if r.get("locationabbr") and len(r["locationabbr"]) == 2
            and r.get("datavalue")
        ]
        print(f"  Fetched {len(states)} state records from CDC")
        return states

    except requests.RequestException as e:
        print(f"CDC API error: {e}", file=sys.stderr)
        return []

# ── Load ──────────────────────────────────────────────────────────────────────

def insert_cdc_data(records):
    """Wipe the table and reload it fresh — easier than doing upserts."""
    conn = mysql.connector.connect(**DB_CONFIG)
    cur  = conn.cursor()

    # Full reload so re-running this never creates duplicates
    cur.execute("TRUNCATE TABLE cdc_diabetes_prevalence")

    sql = """
        INSERT INTO cdc_diabetes_prevalence
            (state_name, state_abbrev, year, prevalence_pct, lower_ci, upper_ci)
        VALUES (%s, %s, %s, %s, %s, %s)
    """

    rows = []
    for r in records:
        rows.append((
            r.get("locationdesc",       ""),
            r.get("locationabbr",       ""),
            int(r.get("yearend",        0)),
            float(r["datavalue"])                if r.get("datavalue")          else None,
            float(r["lowconfidencelimit"])        if r.get("lowconfidencelimit") else None,
            float(r["highconfidencelimit"])       if r.get("highconfidencelimit") else None,
        ))

    cur.executemany(sql, rows)
    conn.commit()
    print(f"  Inserted {len(rows)} rows into cdc_diabetes_prevalence")
    cur.close()
    conn.close()

# ── Main ──────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("Fetching CDC diabetes prevalence data (BRFSS 2021)...")
    records = fetch_cdc_data()
    if records:
        insert_cdc_data(records)
        print("Done!")
    else:
        print("No records fetched — check network connection.")
        sys.exit(1)
