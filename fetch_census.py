#!/usr/bin/env python3
"""
fetch_census.py

Purpose:
    Pull state-level population data from the US Census Bureau and load
    it into the census_state_population table in MySQL. I need this so
    I can calculate trials-per-million for each state and see which ones
    are actually underserved relative to their population size.

How it works:
    The Census Bureau exposes their ACS 5-Year Estimates through a
    public API. I grab variable B01003_001E (total population) for every
    state, then map the FIPS codes they return to 2-letter abbreviations
    so I can join against the CDC table later.

    Same truncate-and-reload approach as fetch_cdc.py — just run it
    again if something goes wrong and it'll start clean.

Data source:
    US Census Bureau, ACS 5-Year Estimates, 2021
    Variable B01003_001E = Total Population
    https://api.census.gov/data/2021/acs/acs5

Usage:
    python3 fetch_census.py

Notes:
    - MAMP has to be running or the MySQL connection will fail
    - No API key needed for the Census Bureau public endpoint
    - The Census API returns FIPS codes for state identifiers, so I have
      to translate them to abbreviations to match the CDC data
"""

import requests
import sys
import mysql.connector

# ── Config ───────────────────────────────────────────────────────────────────

CENSUS_API = "https://api.census.gov/data/2021/acs/acs5"

DB_CONFIG = {
    "host":     "127.0.0.1",
    "port":     8889,
    "user":     "root",
    "password": "root",
    "database": "diabetestrialfinder",
}

# ── FIPS → State Abbreviation ─────────────────────────────────────────────────

# The Census API uses FIPS codes to identify states, but everything else
# in my database uses 2-letter abbreviations — so I map them here
FIPS_TO_ABBREV = {
    "01": "AL", "02": "AK", "04": "AZ", "05": "AR", "06": "CA",
    "08": "CO", "09": "CT", "10": "DE", "11": "DC", "12": "FL",
    "13": "GA", "15": "HI", "16": "ID", "17": "IL", "18": "IN",
    "19": "IA", "20": "KS", "21": "KY", "22": "LA", "23": "ME",
    "24": "MD", "25": "MA", "26": "MI", "27": "MN", "28": "MS",
    "29": "MO", "30": "MT", "31": "NE", "32": "NV", "33": "NH",
    "34": "NJ", "35": "NM", "36": "NY", "37": "NC", "38": "ND",
    "39": "OH", "40": "OK", "41": "OR", "42": "PA", "44": "RI",
    "45": "SC", "46": "SD", "47": "TN", "48": "TX", "49": "UT",
    "50": "VT", "51": "VA", "53": "WA", "54": "WV", "55": "WI",
    "56": "WY",
}

# ── Fetch ─────────────────────────────────────────────────────────────────────

def fetch_census_data():
    """Grab 2021 total population for all states from the Census ACS API."""
    params = {
        "get": "NAME,B01003_001E",  # state name + total population variable
        "for": "state:*",           # all 50 states + DC
    }
    try:
        resp = requests.get(CENSUS_API, params=params, timeout=30)
        resp.raise_for_status()
        rows = resp.json()
        data = rows[1:]  # first row is the header, skip it
        print(f"  Fetched {len(data)} state records from Census Bureau")
        return data

    except requests.RequestException as e:
        print(f"Census API error: {e}", file=sys.stderr)
        return []

# ── Load ──────────────────────────────────────────────────────────────────────

def insert_census_data(rows):
    """Wipe the table and reload — keeps things clean if I run this multiple times."""
    conn = mysql.connector.connect(**DB_CONFIG)
    cur  = conn.cursor()

    cur.execute("TRUNCATE TABLE census_state_population")

    sql = """
        INSERT INTO census_state_population
            (state_name, state_abbrev, census_year, population)
        VALUES (%s, %s, %s, %s)
    """

    records = [
        (
            row[0],                          # full state name from API
            FIPS_TO_ABBREV.get(row[2], ""),  # translate FIPS → abbreviation
            2021,
            int(row[1]) if row[1] else None  # population count
        )
        for row in rows
    ]

    cur.executemany(sql, records)
    conn.commit()
    print(f"  Inserted {len(records)} rows into census_state_population")
    cur.close()
    conn.close()

# ── Main ──────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("Fetching US Census Bureau state population data (ACS 2021)...")
    rows = fetch_census_data()
    if rows:
        insert_census_data(rows)
        print("Done!")
    else:
        print("No records fetched — check network connection.")
        sys.exit(1)
