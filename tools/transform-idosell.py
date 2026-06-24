#!/usr/bin/env python3
"""Transform an IdoSell/IAI product export CSV into a PIM-native, column-per-
attribute CSV the import wizard can map 1:1 by attribute code.

The IdoSell export is EAV-pivoted: every product parameter lives in two
parallel newline-lists (``/parameters/parameter@name[pol]`` and
``/parameters/parameter/value@name[pol]``), aligned by position. The PIM
importer maps one column to one attribute, so it cannot read that shape. This
script un-pivots the parameters into their own columns, maps the fixed IdoSell
fields to PIM attribute codes, and drops the ~230 IdoSell-only columns.

Usage:
    python3 tools/transform-idosell.py INPUT.csv [OUTPUT.csv]

Defaults OUTPUT to ``INPUT-pim.csv``. Multi-value cells (sizes, image URLs)
are joined with ``|`` — the importer accepts pipe and newline (#1719).
Select/multiselect VALUES stay as human labels; an import run with
"create missing options" (#1718) mints the options on the fly.
"""
from __future__ import annotations

import csv
import sys
import unicodedata
from pathlib import Path

# IdoSell parameter name (pol) -> PIM attribute code. Anything not listed is
# slugged generically and reported on stderr so the map can be extended.
PARAM_TO_CODE: dict[str, str] = {
    "Marka": "marka",
    "Kolor": "kolor",
    "Materiał": "material",
    "Materiał wkładki": "material_wkladki",
    "Wysokość obcasa (cm)": "wysokosc_obcasa",
    "Obwód cholewki (cm)": "obwod_cholewki",
    "Wysokość cholewki (cm)": "wysokosc_cholewki",
    "Symbol producenta": "symbol_producenta",
}

# Fixed output columns (header order). Parameter columns are appended after
# these, in the order their names first appear across the file.
FIXED_COLUMNS = [
    "sku",
    "name.pl",
    "short_description.pl",
    "description.pl",
    "cena",
    "rozmiar",
    "zdjecia",
    "__category__",
]


def slugify(value: str) -> str:
    """ASCII-fold + snake_case, matching how PIM attribute codes read."""
    norm = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode()
    slug = "".join(ch if ch.isalnum() else "_" for ch in norm.lower()).strip("_")
    while "__" in slug:
        slug = slug.replace("__", "_")
    return slug or "param"


def split_lines(cell: str | None) -> list[str]:
    return [part.strip() for part in (cell or "").split("\n") if part.strip()]


def join_multi(cell: str | None) -> str:
    return "|".join(split_lines(cell))


def transform(src: Path, dst: Path) -> None:
    with src.open(encoding="utf-8", newline="") as fh:
        rows = list(csv.DictReader(fh))

    unknown_params: set[str] = set()
    param_codes_order: list[str] = []
    out_rows: list[dict[str, str]] = []

    for row in rows:
        out: dict[str, str] = {
            "sku": (row.get("@code_producer") or "").strip(),
            "name.pl": (row.get("/description/name[pol]") or "").strip(),
            "short_description.pl": (row.get("/description/short_desc[pol]") or "").strip(),
            "description.pl": (row.get("/description/long_desc[pol]") or "").strip(),
            "rozmiar": join_multi(row.get("/sizes/size@name")),
            "zdjecia": join_multi(row.get("/images/large/image@url")),
            "__category__": (row.get("/category@name[pol]") or "").strip(),
        }

        gross = (row.get("/price@gross") or "").strip()
        currency = (row.get("@currency") or "").strip()
        out["cena"] = f"{gross} {currency}".strip() if gross else ""

        # Un-pivot parameters: positionally align the name-list with the value-list.
        names = split_lines(row.get("/parameters/parameter@name[pol]"))
        values = split_lines(row.get("/parameters/parameter/value@name[pol]"))
        for name, value in zip(names, values):
            code = PARAM_TO_CODE.get(name)
            if code is None:
                code = slugify(name)
                unknown_params.add(f"{name} -> {code}")
            if code not in param_codes_order and code not in FIXED_COLUMNS:
                param_codes_order.append(code)
            out[code] = value

        # Fallback: producer name fills "marka" when there is no Marka parameter.
        if not out.get("marka"):
            producer = (row.get("/producer@name") or "").strip()
            if producer:
                out["marka"] = producer
                if "marka" not in param_codes_order:
                    param_codes_order.append("marka")

        out_rows.append(out)

    header = FIXED_COLUMNS + param_codes_order
    with dst.open("w", encoding="utf-8", newline="") as fh:
        writer = csv.DictWriter(fh, fieldnames=header, extrasaction="ignore")
        writer.writeheader()
        for out in out_rows:
            writer.writerow({col: out.get(col, "") for col in header})

    print(f"Wrote {len(out_rows)} row(s) x {len(header)} columns -> {dst}")
    print("Columns:", ", ".join(header))
    if unknown_params:
        print("\nParameters not in the known map (slugged generically):", file=sys.stderr)
        for entry in sorted(unknown_params):
            print(f"  - {entry}", file=sys.stderr)


def main() -> None:
    if len(sys.argv) < 2:
        sys.exit(f"usage: {sys.argv[0]} INPUT.csv [OUTPUT.csv]")
    src = Path(sys.argv[1])
    dst = Path(sys.argv[2]) if len(sys.argv) > 2 else src.with_name(f"{src.stem}-pim.csv")
    transform(src, dst)


if __name__ == "__main__":
    main()
