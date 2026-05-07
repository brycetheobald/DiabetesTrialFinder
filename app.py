#!/usr/bin/env python3
"""
app.py

Purpose:
    Flask web interface for DiabetesTrialFinder. Lets users search and
    filter Type 2 Diabetes clinical trials pulled from ClinicalTrials.gov.

Usage:
    python3 app.py
    Then open http://localhost:5000 in your browser.

Notes:
    - MAMP needs to be running for the MySQL connection to work
    - Make sure flask is installed: pip install flask
"""

from flask import Flask, render_template, request
import mysql.connector

app = Flask(__name__)

# ── Database ──────────────────────────────────────────────────────────────────

DB_CONFIG = {
    "host":     "127.0.0.1",
    "port":     8889,
    "user":     "root",
    "password": "root",
    "database": "diabetestrialfinder",
}

def get_db():
    return mysql.connector.connect(**DB_CONFIG)

# ── Helpers ───────────────────────────────────────────────────────────────────

PER_PAGE = 25

ORDER_OPTIONS = {
    "date_desc":   "t.start_date DESC",
    "date_asc":    "t.start_date ASC",
    "enroll_desc": "t.enrollment_count DESC",
    "enroll_asc":  "t.enrollment_count ASC",
    "title_asc":   "t.brief_title ASC",
}

# ── Routes ────────────────────────────────────────────────────────────────────

@app.route("/")
def index():
    db  = get_db()
    cur = db.cursor(dictionary=True)

    # populate dropdowns from actual data
    cur.execute("SELECT DISTINCT overall_status FROM trials WHERE overall_status != '' ORDER BY overall_status")
    statuses = [r["overall_status"] for r in cur.fetchall()]

    cur.execute("SELECT DISTINCT phase FROM trials WHERE phase != '' ORDER BY phase")
    phases = [r["phase"] for r in cur.fetchall()]

    cur.execute("SELECT DISTINCT state FROM locations WHERE state != '' ORDER BY state")
    states = [r["state"] for r in cur.fetchall()]

    # read filters from query string
    keyword    = request.args.get("keyword",    "").strip()
    status     = request.args.get("status",     "").strip()
    phase      = request.args.get("phase",      "").strip()
    state      = request.args.get("state",      "").strip()
    min_enroll = request.args.get("min_enroll", "").strip()
    max_enroll = request.args.get("max_enroll", "").strip()
    sort       = request.args.get("sort",       "date_desc").strip()
    page       = max(1, int(request.args.get("page", 1)))
    offset     = (page - 1) * PER_PAGE

    # build WHERE clause dynamically
    conditions = ["1=1"]
    params     = []

    if keyword:
        conditions.append("(t.brief_title LIKE %s OR t.official_title LIKE %s)")
        params += [f"%{keyword}%", f"%{keyword}%"]
    if status:
        conditions.append("t.overall_status = %s")
        params.append(status)
    if phase:
        conditions.append("t.phase = %s")
        params.append(phase)
    if state:
        conditions.append("""
            EXISTS (
                SELECT 1 FROM trial_locations tl
                JOIN locations l ON tl.location_id = l.location_id
                WHERE tl.trial_id = t.trial_id AND l.state = %s
            )
        """)
        params.append(state)
    if min_enroll:
        conditions.append("t.enrollment_count >= %s")
        params.append(int(min_enroll))
    if max_enroll:
        conditions.append("t.enrollment_count <= %s")
        params.append(int(max_enroll))

    where = " AND ".join(conditions)
    order = ORDER_OPTIONS.get(sort, "t.start_date DESC")

    # count total matches
    cur.execute(f"SELECT COUNT(*) AS n FROM trials t WHERE {where}", params)
    total       = cur.fetchone()["n"]
    total_pages = max(1, -(-total // PER_PAGE))  # ceiling division

    # fetch current page
    cur.execute(f"""
        SELECT t.trial_id, t.nct_id, t.brief_title, t.overall_status,
               t.phase, t.enrollment_count, t.start_date, s.name AS sponsor
        FROM trials t
        LEFT JOIN sponsors s ON t.lead_sponsor_id = s.sponsor_id
        WHERE {where}
        ORDER BY {order}
        LIMIT %s OFFSET %s
    """, params + [PER_PAGE, offset])
    trials = cur.fetchall()

    cur.close()
    db.close()

    return render_template("index.html",
        trials=trials, total=total, page=page, total_pages=total_pages,
        statuses=statuses, phases=phases, states=states,
        keyword=keyword, status=status, phase=phase, state=state,
        min_enroll=min_enroll, max_enroll=max_enroll, sort=sort,
    )


@app.route("/trial/<int:trial_id>")
def trial(trial_id):
    db  = get_db()
    cur = db.cursor(dictionary=True)

    cur.execute("""
        SELECT t.*, s.name AS sponsor, s.class AS sponsor_class
        FROM trials t
        LEFT JOIN sponsors s ON t.lead_sponsor_id = s.sponsor_id
        WHERE t.trial_id = %s
    """, (trial_id,))
    trial = cur.fetchone()

    if not trial:
        cur.close(); db.close()
        return "Trial not found", 404

    # locations
    cur.execute("""
        SELECT l.facility_name, l.city, l.state
        FROM locations l
        JOIN trial_locations tl ON tl.location_id = l.location_id
        WHERE tl.trial_id = %s
        ORDER BY l.state, l.city
    """, (trial_id,))
    locations = cur.fetchall()

    # interventions
    cur.execute("""
        SELECT i.type, i.name
        FROM interventions i
        JOIN trial_interventions ti ON ti.intervention_id = i.intervention_id
        WHERE ti.trial_id = %s
    """, (trial_id,))
    interventions = cur.fetchall()

    cur.close()
    db.close()

    return render_template("trial.html",
        trial=trial, locations=locations, interventions=interventions
    )


# ── Run ───────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    app.run(debug=True)
