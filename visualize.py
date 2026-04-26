#!/usr/bin/env python3
"""
visualize.py
Generates interactive Plotly charts from the local MAMP MySQL database.
Saves HTML files and opens them in the browser automatically.

Usage:
    pip3 install mysql-connector-python plotly pandas
    python3 visualize.py
"""

import mysql.connector
import plotly.express as px
import pandas as pd
import webbrowser
import os

# ── Connection ────────────────────────────────────────────────────────────────
db = mysql.connector.connect(
    host="127.0.0.1",
    port=8889,
    user="root",
    password="root",
    database="diabetestrialfinder"
)

def q(sql):
    return pd.read_sql(sql, db)

OUT = os.path.dirname(os.path.abspath(__file__))

# ── Queries ───────────────────────────────────────────────────────────────────

status_df = q("""
    SELECT overall_status AS Status, COUNT(*) AS Trials
    FROM trials GROUP BY overall_status ORDER BY Trials DESC
""")
status_df['Status'] = status_df['Status'].str.replace('_', ' ')

phase_df = q("""
    SELECT phase AS Phase, COUNT(*) AS Trials
    FROM trials GROUP BY phase ORDER BY Trials DESC
""")
phase_df['Phase'] = phase_df['Phase'].str.replace('_', ' ')

iv_df = q("""
    SELECT i.`type` AS Type, COUNT(DISTINCT ti.trial_id) AS Trials
    FROM interventions i
    JOIN trial_interventions ti ON ti.intervention_id = i.intervention_id
    GROUP BY i.`type` ORDER BY Trials DESC LIMIT 10
""")
iv_df['Type'] = iv_df['Type'].fillna('Unknown')

sponsor_df = q("""
    SELECT s.name AS Sponsor, COUNT(*) AS Trials
    FROM sponsors s
    JOIN trials t ON t.lead_sponsor_id = s.sponsor_id
    GROUP BY s.sponsor_id ORDER BY Trials DESC LIMIT 10
""")

map_df = q("""
    SELECT l.state AS State, COUNT(DISTINCT tl.trial_id) AS Trials
    FROM locations l
    JOIN trial_locations tl ON tl.location_id = l.location_id
    WHERE l.state != '' AND l.country = 'United States'
    GROUP BY l.state
""")

# Map full state names to 2-letter codes for choropleth
abbrev = {
    'Alabama':'AL','Alaska':'AK','Arizona':'AZ','Arkansas':'AR','California':'CA',
    'Colorado':'CO','Connecticut':'CT','Delaware':'DE','Florida':'FL','Georgia':'GA',
    'Hawaii':'HI','Idaho':'ID','Illinois':'IL','Indiana':'IN','Iowa':'IA',
    'Kansas':'KS','Kentucky':'KY','Louisiana':'LA','Maine':'ME','Maryland':'MD',
    'Massachusetts':'MA','Michigan':'MI','Minnesota':'MN','Mississippi':'MS',
    'Missouri':'MO','Montana':'MT','Nebraska':'NE','Nevada':'NV',
    'New Hampshire':'NH','New Jersey':'NJ','New Mexico':'NM','New York':'NY',
    'North Carolina':'NC','North Dakota':'ND','Ohio':'OH','Oklahoma':'OK',
    'Oregon':'OR','Pennsylvania':'PA','Rhode Island':'RI','South Carolina':'SC',
    'South Dakota':'SD','Tennessee':'TN','Texas':'TX','Utah':'UT',
    'Vermont':'VT','Virginia':'VA','Washington':'WA','West Virginia':'WV',
    'Wisconsin':'WI','Wyoming':'WY','District of Columbia':'DC',
}
map_df['Code'] = map_df['State'].map(abbrev)
map_df = map_df.dropna(subset=['Code'])

db.close()

# ── Charts ────────────────────────────────────────────────────────────────────

charts = []

# 1. US Choropleth Map
fig = px.choropleth(
    map_df, locations='Code', locationmode='USA-states',
    color='Trials', scope='usa',
    color_continuous_scale='Blues',
    title='Type 2 Diabetes Clinical Trials by US State',
    labels={'Trials': 'Trial Count'},
)
fig.update_layout(title_font_size=20, margin=dict(t=60, b=20))
path = f"{OUT}/chart_map.html"
fig.write_html(path)
charts.append(path)
print("Saved chart_map.html")

# 2. Trials by Status
fig = px.bar(
    status_df, x='Trials', y='Status', orientation='h',
    color='Trials', color_continuous_scale='Blues',
    title='Trials by Status',
)
fig.update_layout(yaxis={'categoryorder': 'total ascending'}, title_font_size=18)
path = f"{OUT}/chart_status.html"
fig.write_html(path)
charts.append(path)
print("Saved chart_status.html")

# 3. Trials by Phase
fig = px.bar(
    phase_df, x='Trials', y='Phase', orientation='h',
    color='Trials', color_continuous_scale='Purples',
    title='Trials by Phase',
)
fig.update_layout(yaxis={'categoryorder': 'total ascending'}, title_font_size=18)
path = f"{OUT}/chart_phase.html"
fig.write_html(path)
charts.append(path)
print("Saved chart_phase.html")

# 4. Top Intervention Types
fig = px.bar(
    iv_df, x='Trials', y='Type', orientation='h',
    color='Trials', color_continuous_scale='Oranges',
    title='Top Intervention Types',
)
fig.update_layout(yaxis={'categoryorder': 'total ascending'}, title_font_size=18)
path = f"{OUT}/chart_interventions.html"
fig.write_html(path)
charts.append(path)
print("Saved chart_interventions.html")

# 5. Top 10 Sponsors
fig = px.bar(
    sponsor_df, x='Trials', y='Sponsor', orientation='h',
    color='Trials', color_continuous_scale='Teal',
    title='Top 10 Lead Sponsors',
)
fig.update_layout(yaxis={'categoryorder': 'total ascending'}, title_font_size=18)
path = f"{OUT}/chart_sponsors.html"
fig.write_html(path)
charts.append(path)
print("Saved chart_sponsors.html")

print(f"\nDone! Opening {len(charts)} charts in your browser...")
for p in charts:
    webbrowser.open(f"file://{p}")
