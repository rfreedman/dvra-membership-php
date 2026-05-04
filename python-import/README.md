# Python spreadsheet import (subtree)

This directory is a **frozen copy** of the Python roster import used with the same SQLite schema as the PHP app. It exists **only** for:

- **One-off imports** during development, and  
- **Production initialization** (loading an initial roster before day-to-day use of the PHP UI).

It is **not** a supported runtime dependency of the PHP application on shared hosting. Do not assume this tree is installed or executed in normal production operation after the database is populated.

## Requirements

- Python **3.10+**

## Setup

From this directory (`python-import/`):

```bash
python3 -m venv .venv
source .venv/bin/activate   # Windows: .venv\Scripts\activate
pip install -r requirements.txt
```

## Run

Use the **same database file** as PHP (default PHP path: `var/dvra_membership.sqlite` under the repo root). SQLAlchemy URL example (absolute path):

```bash
cd python-import
python -m scripts.import_from_spreadsheet \
  --spreadsheet /path/to/roster.xlsx \
  --database-url "sqlite:////absolute/path/to/dvra-membership-php/var/dvra_membership.sqlite"
```

See `python -m scripts.import_from_spreadsheet --help` for flags (`--replace`, roster mode, etc.). Alternate entry: `python -m scripts.import_members` (same `main`).

Project root for `sys.path` is the parent of the `scripts` package (this `python-import/` folder), so always run modules as shown from `python-import/` or ensure that directory is on `PYTHONPATH`.
