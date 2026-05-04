"""Backward-compatible entry point; delegates to ``import_from_spreadsheet``."""

from scripts.import_from_spreadsheet import main


if __name__ == "__main__":
    raise SystemExit(main())
