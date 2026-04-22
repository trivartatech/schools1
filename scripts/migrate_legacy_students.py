"""
Legacy student data cleaning pass.

Reads 8 HTML-disguised-as-xls files under `importent file/students data/`,
dedups students on (dob, father_mobile), and emits clean CSVs under
`storage/app/migrations/` for the Laravel artisan command to consume.

Outputs:
  students_clean.csv    -- one row per unique student (canonical profile)
  enrollments_clean.csv -- one row per (student, year) pair
  dedup_review.csv      -- rows that could not be confidently deduped
  summary.json          -- row counts and totals for verification
"""

import json
import re
import sys
from collections import defaultdict
from pathlib import Path

import pandas as pd

ROOT = Path(__file__).resolve().parent.parent
SRC_DIR = ROOT / "importent file" / "students data"
OUT_DIR = ROOT / "storage" / "app" / "migrations"
OUT_DIR.mkdir(parents=True, exist_ok=True)

FILES = [
    ("2022-23", "ADMITTED",     "2022-23 ADMITTED.xls",     "rich"),
    ("2022-23", "NOT_ADMITTED", "2022-23 NOT ADMITTED.xls", "rich"),
    ("2023-24", "ADMITTED",     "23-24 ADMITTED.xls",       "rich"),
    ("2023-24", "NOT_ADMITTED", "23-24 NOT ADMITTED.xls",   "rich"),
    ("2024-25", "ADMITTED",     "24-25 ADMITTED DATA.xls",  "rich"),
    ("2024-25", "NOT_ADMITTED", "24-25 NOT ADMITTED.xls",   "rich"),
    ("2025-26", "ADMITTED",     "25-26 ADMITTED.xls",       "lean"),
    ("2025-26", "NOT_ADMITTED", "25-26 NOT ADMITTED.xls",   "lean_na"),
]

# Column positions in the rich schema (22-23, 23-24, 24-25 + their NA files)
RICH_COLS = {
    "name":            1,
    "birth_place":     2,
    "dob":             3,
    "gender":          4,
    "blood_group":     5,
    "aadhaar_no":      6,
    "sats":            7,
    "mother_tongue":   8,
    "scholarship_no":  9,
    "height":         10,
    "weight":         11,
    "doa":            12,
    "admission_no":   13,
    "category":       14,
    "cast":           15,
    "tc_no":          16,
    "village":        17,
    "taluk":          18,
    "district":       19,
    "state":          20,
    "religion":       21,
    "guardian":       22,
    "guardian_mobile":23,
    "guardian_addr":  24,
    "father":         25,
    "father_mobile":  26,
    "father_occ":     27,
    "res_addr":       28,
    "perm_addr":      29,
    "mother":         30,
    "mother_mobile":  31,
    "mother_occ":     32,
    "father_blood":   33,
    "mother_blood":   34,
    "email":          35,
    "father_aadhaar": 36,
    "mother_aadhaar": 37,
    "income":         38,
    "class":          39,
    "section":        40,
    "previous_class": 41,
    "medium":         42,
    "roll_no":        43,
    "admission_fee":  44,
    "old_balance":    45,
    "stationery_fee": 46,
    "other_fee":      47,
    "hostel_fee":     48,
    "route_name":     49,
    "route_fee":      50,
    "disc_amount":    51,
    "disc_type":      52,
    "total_fee":      53,
    "total_paid":     54,
    "total_balance":  55,
    "new_admission":  56,
    "extra_fee":      57,
    "extra_paid":     58,
    "extra_balance":  59,
}

# Column positions in the lean 25-26 ADMITTED schema
LEAN_COLS = {
    "name":          1,
    "birth_place":   2,
    "dob":           3,
    "gender":        4,
    "sats":          5,
    "admission_no":  6,
    "village":       7,
    "taluk":         8,
    "district":      9,
    "religion":     10,
    "father":       11,
    "father_mobile":12,
    "res_addr":     13,
    "perm_addr":    14,
    "mother":       15,
    "mother_mobile":16,
    "class":        17,
    "section":      18,
    "old_balance":  19,
    "total_fee":    20,
    "total_paid":   21,
    "total_balance":22,
    "new_admission":23,
    "extra_fee":    24,
    "extra_paid":   25,
    "extra_balance":26,
}

# Column positions in the 25-26 NOT ADMITTED schema
LEAN_NA_COLS = {
    "name":          1,
    "birth_place":   2,
    "dob":           3,
    "gender":        4,
    "admission_no":  5,
    "village":       6,
    "taluk":         7,
    "district":      8,
    "religion":      9,
    "father":       10,
    "father_mobile":11,
    "perm_addr":    12,
    "mother":       13,
    "mother_mobile":14,
    "email":        15,
    "father_aadhaar":16,
    "class":        17,
    "section":      18,
    "old_balance":  19,
    "total_fee":    20,
    "total_paid":   21,
    "total_balance":22,
    "extra_fee":    23,
    "extra_paid":   24,
    "extra_balance":25,
}

SCHEMA_MAP = {"rich": RICH_COLS, "lean": LEAN_COLS, "lean_na": LEAN_NA_COLS}


def norm_text(v):
    if pd.isna(v):
        return ""
    return re.sub(r"\s+", " ", str(v).strip().upper())


def norm_phone(v):
    if pd.isna(v):
        return ""
    digits = re.sub(r"\D", "", str(v))
    # Indian 10-digit, strip leading 91 if 12 digits
    if len(digits) == 12 and digits.startswith("91"):
        digits = digits[2:]
    return digits


def norm_date(v):
    if pd.isna(v):
        return ""
    s = str(v).strip()
    # Already ISO
    m = re.match(r"^(\d{4}-\d{2}-\d{2})", s)
    if m:
        return m.group(1)
    # DD/MM/YYYY or DD-MM-YYYY
    m = re.match(r"^(\d{1,2})[/-](\d{1,2})[/-](\d{2,4})$", s)
    if m:
        d, mo, y = m.groups()
        if len(y) == 2:
            y = "20" + y
        return f"{y}-{mo.zfill(2)}-{d.zfill(2)}"
    return ""


def num(v):
    if pd.isna(v):
        return 0
    s = str(v).strip().replace(",", "")
    if not s:
        return 0
    try:
        return int(float(s))
    except ValueError:
        return 0


def text_or_none(v):
    if pd.isna(v):
        return ""
    s = str(v).strip()
    return "" if s.lower() in ("nan", "none", "null") else s


# Canonical class labels. The source Excel uses variants like "9TH" vs "9 TH" —
# collapse to a single canonical spelling so we don't create duplicate classes.
CLASS_CANONICAL = {
    "JKG":         "JKG",
    "LKG":         "LKG",
    "UKG":         "UKG",
    "1STD":        "1 STD",
    "1 STD":       "1 STD",
    "2ND":         "2 ND",
    "2 ND":        "2 ND",
    "3RD":         "3 RD",
    "3 RD":        "3 RD",
    "4TH":         "4 TH",
    "4 TH":        "4 TH",
    "5TH":         "5 TH",
    "5 TH":        "5 TH",
    "6TH":         "6 TH",
    "6 TH":        "6 TH",
    "7TH":         "7 TH",
    "7 TH":        "7 TH",
    "8TH":         "8 TH",
    "8 TH":        "8 TH",
    "9TH":         "9 TH",
    "9 TH":        "9 TH",
    "10TH":        "10 TH",
    "10 TH":       "10 TH",
    "10TH STATE":  "10 TH STATE",
    "10 TH STATE": "10 TH STATE",
}


def normalize_class_name(raw: str) -> str:
    if not raw:
        return ""
    key = re.sub(r"\s+", " ", str(raw).strip().upper())
    return CLASS_CANONICAL.get(key, key)


def read_file(path: Path, schema: str) -> pd.DataFrame:
    """Read an HTML-as-xls file and return a clean DataFrame keyed by logical column names."""
    tables = pd.read_html(path)
    df = tables[0]
    data = df.iloc[2:].reset_index(drop=True)
    cols = SCHEMA_MAP[schema]
    out = {}
    for logical, col_idx in cols.items():
        if col_idx < data.shape[1]:
            out[logical] = data.iloc[:, col_idx]
        else:
            out[logical] = pd.Series([None] * len(data))
    result = pd.DataFrame(out)
    # Filter to rows with a name
    result = result[result["name"].apply(lambda x: text_or_none(x) != "")].reset_index(drop=True)
    return result


def main():
    all_rows = []  # list of dicts, one per source row
    file_summaries = []

    for year, src_type, fname, schema in FILES:
        path = SRC_DIR / fname
        if not path.exists():
            print(f"WARN: missing {path}", file=sys.stderr)
            continue
        df = read_file(path, schema)
        for _, r in df.iterrows():
            rec = {k: r.get(k) for k in r.index}
            rec["_year"] = year
            rec["_src_type"] = src_type
            rec["_src_file"] = fname
            rec["_schema"] = schema
            rec["_dob_iso"] = norm_date(rec.get("dob"))
            rec["_father_phone"] = norm_phone(rec.get("father_mobile"))
            rec["_name_norm"] = norm_text(rec.get("name"))
            rec["_father_norm"] = norm_text(rec.get("father"))
            all_rows.append(rec)
        file_summaries.append({
            "file": fname, "year": year, "src_type": src_type, "rows": len(df),
        })
        print(f"  read {fname}: {len(df)} rows")

    print(f"\nTotal source rows: {len(all_rows)}")

    # Dedup on (dob_iso, father_phone). Rows with either missing are parked in review.
    groups = defaultdict(list)
    review = []
    for rec in all_rows:
        if not rec["_dob_iso"] or not rec["_father_phone"]:
            review.append(rec)
            continue
        key = (rec["_dob_iso"], rec["_father_phone"])
        groups[key].append(rec)

    # Within groups, detect likely sibling collisions: two different students with
    # same DOB + same father phone but clearly different first names. Park these.
    clean_groups = {}
    for key, recs in groups.items():
        # Compute distinct first-name-word set
        first_names = {r["_name_norm"].split()[0] if r["_name_norm"] else "" for r in recs}
        first_names.discard("")
        if len(first_names) > 1:
            # Heterogeneous group — likely siblings sharing father's phone.
            # Split by first-word of name.
            sub = defaultdict(list)
            for r in recs:
                fw = r["_name_norm"].split()[0] if r["_name_norm"] else ""
                sub[(key[0], key[1], fw)].append(r)
            for subkey, subrecs in sub.items():
                clean_groups[subkey] = subrecs
        else:
            clean_groups[key] = recs

    print(f"Unique students after dedup: {len(clean_groups)}")
    print(f"Rows parked for manual review: {len(review)}")

    # Build canonical student profile from most recent year's row
    def year_order(r):
        # 2025-26 > 2024-25 > 2023-24 > 2022-23; ADMITTED preferred over NOT_ADMITTED
        yr_rank = {"2022-23": 0, "2023-24": 1, "2024-25": 2, "2025-26": 3}
        return (yr_rank.get(r["_year"], -1), 1 if r["_src_type"] == "ADMITTED" else 0)

    def non_empty(*vals):
        for v in vals:
            t = text_or_none(v)
            if t:
                return t
        return ""

    def split_name(full):
        parts = [p for p in full.split() if p]
        if len(parts) <= 1:
            return (full, "")
        return (" ".join(parts[:-1]), parts[-1])

    students = []
    enrollments = []
    student_seq = 1

    for key, recs in sorted(clean_groups.items(), key=lambda kv: min(r["_year"] for r in kv[1])):
        # Sort recs by year for deterministic canonical selection
        recs_sorted = sorted(recs, key=year_order, reverse=True)
        canonical = recs_sorted[0]
        first_year = min(r["_year"] for r in recs)
        admission_no = f"SGIS/{first_year}/{student_seq:04d}"

        # Derive canonical fields, preferring most-recent non-empty
        def pick(field):
            for r in recs_sorted:
                v = r.get(field)
                if text_or_none(v):
                    return text_or_none(v)
            return ""

        full_name = pick("name").title() if pick("name") else ""
        first_name, last_name = split_name(full_name)
        gender_raw = pick("gender").strip().lower()
        gender = {"male": "Male", "female": "Female"}.get(gender_raw, gender_raw.title() if gender_raw else "")

        student_row = {
            "student_seq": student_seq,
            "admission_no": admission_no,
            "first_year": first_year,
            "first_name": first_name,
            "last_name": last_name,
            "full_name_raw": pick("name"),
            "dob": canonical["_dob_iso"],
            "gender": gender,
            "blood_group": pick("blood_group"),
            "religion": pick("religion").title() if pick("religion") else "",
            "category": pick("category"),
            "cast": pick("cast"),
            "aadhaar_no": pick("aadhaar_no"),
            "mother_tongue": pick("mother_tongue"),
            "sats": pick("sats"),
            "birth_place": pick("birth_place"),
            "address": non_empty(pick("perm_addr"), pick("res_addr")),
            "village": pick("village"),
            "taluk": pick("taluk"),
            "district": pick("district"),
            "state": pick("state"),
            "father_name": pick("father").title() if pick("father") else "",
            "father_phone": canonical["_father_phone"],
            "father_occupation": pick("father_occ"),
            "mother_name": pick("mother").title() if pick("mother") else "",
            "mother_phone": norm_phone(pick("mother_mobile")),
            "mother_occupation": pick("mother_occ"),
            "guardian_name": pick("guardian"),
            "guardian_phone": norm_phone(pick("guardian_mobile")),
            "email": pick("email"),
            "income": pick("income"),
        }
        students.append(student_row)

        # Build enrollment rows
        for r in recs_sorted:
            # Only create an enrollment row when the student has class info for that year
            class_name = text_or_none(r.get("class"))
            if not class_name:
                continue
            enroll = {
                "student_seq": student_seq,
                "admission_no": admission_no,
                "year": r["_year"],
                "src_type": r["_src_type"],
                "src_file": r["_src_file"],
                "class_name": normalize_class_name(class_name),
                "section_name": text_or_none(r.get("section")),
                "previous_class": text_or_none(r.get("previous_class")),
                "medium": text_or_none(r.get("medium")),
                "roll_no": text_or_none(r.get("roll_no")),
                "new_admission": num(r.get("new_admission")),
                "admission_date": norm_date(r.get("doa")),
                # Fee columns
                "admission_fee":   num(r.get("admission_fee")),
                "stationery_fee":  num(r.get("stationery_fee")),
                "hostel_fee":      num(r.get("hostel_fee")),
                "route_name":      text_or_none(r.get("route_name")),
                "route_fee":       num(r.get("route_fee")),
                "other_fee":       num(r.get("other_fee")),
                "total_fee":       num(r.get("total_fee")),
                "total_paid":      num(r.get("total_paid")),
                "total_balance":   num(r.get("total_balance")),
                "extra_fee":       num(r.get("extra_fee")),
                "extra_paid":      num(r.get("extra_paid")),
                "extra_balance":   num(r.get("extra_balance")),
                "old_balance":     num(r.get("old_balance")),
                "disc_amount":     num(r.get("disc_amount")),
                "disc_type":       text_or_none(r.get("disc_type")),
            }
            enrollments.append(enroll)

        student_seq += 1

    # Also process review rows — assign them to students if (dob, father_phone) missing
    # isn't catastrophic; we still want them in the export.
    for rec in review:
        admission_no = f"SGIS/{rec['_year']}/REVIEW-{len(students)+1:04d}"
        full_name = text_or_none(rec.get("name")).title()
        first_name, last_name = split_name(full_name)
        students.append({
            "student_seq": len(students) + 1,
            "admission_no": admission_no,
            "first_year": rec["_year"],
            "first_name": first_name,
            "last_name": last_name,
            "full_name_raw": text_or_none(rec.get("name")),
            "dob": rec["_dob_iso"],
            "gender": text_or_none(rec.get("gender")).title(),
            "blood_group": text_or_none(rec.get("blood_group")),
            "religion": text_or_none(rec.get("religion")).title() if text_or_none(rec.get("religion")) else "",
            "category": text_or_none(rec.get("category")),
            "cast": text_or_none(rec.get("cast")),
            "aadhaar_no": text_or_none(rec.get("aadhaar_no")),
            "mother_tongue": text_or_none(rec.get("mother_tongue")),
            "sats": text_or_none(rec.get("sats")),
            "birth_place": text_or_none(rec.get("birth_place")),
            "address": non_empty(text_or_none(rec.get("perm_addr")), text_or_none(rec.get("res_addr"))),
            "village": text_or_none(rec.get("village")),
            "taluk": text_or_none(rec.get("taluk")),
            "district": text_or_none(rec.get("district")),
            "state": text_or_none(rec.get("state")),
            "father_name": text_or_none(rec.get("father")).title() if text_or_none(rec.get("father")) else "",
            "father_phone": rec["_father_phone"],
            "father_occupation": text_or_none(rec.get("father_occ")),
            "mother_name": text_or_none(rec.get("mother")).title() if text_or_none(rec.get("mother")) else "",
            "mother_phone": norm_phone(rec.get("mother_mobile")),
            "mother_occupation": text_or_none(rec.get("mother_occ")),
            "guardian_name": text_or_none(rec.get("guardian")),
            "guardian_phone": norm_phone(rec.get("guardian_mobile")),
            "email": text_or_none(rec.get("email")),
            "income": text_or_none(rec.get("income")),
        })
        class_name = text_or_none(rec.get("class"))
        if class_name:
            enrollments.append({
                "student_seq": students[-1]["student_seq"],
                "admission_no": admission_no,
                "year": rec["_year"],
                "src_type": rec["_src_type"],
                "src_file": rec["_src_file"],
                "class_name": normalize_class_name(class_name),
                "section_name": text_or_none(rec.get("section")),
                "previous_class": text_or_none(rec.get("previous_class")),
                "medium": text_or_none(rec.get("medium")),
                "roll_no": text_or_none(rec.get("roll_no")),
                "new_admission": num(rec.get("new_admission")),
                "admission_date": norm_date(rec.get("doa")),
                "admission_fee":   num(rec.get("admission_fee")),
                "stationery_fee":  num(rec.get("stationery_fee")),
                "hostel_fee":      num(rec.get("hostel_fee")),
                "route_name":      text_or_none(rec.get("route_name")),
                "route_fee":       num(rec.get("route_fee")),
                "other_fee":       num(rec.get("other_fee")),
                "total_fee":       num(rec.get("total_fee")),
                "total_paid":      num(rec.get("total_paid")),
                "total_balance":   num(rec.get("total_balance")),
                "extra_fee":       num(rec.get("extra_fee")),
                "extra_paid":      num(rec.get("extra_paid")),
                "extra_balance":   num(rec.get("extra_balance")),
                "old_balance":     num(rec.get("old_balance")),
                "disc_amount":     num(rec.get("disc_amount")),
                "disc_type":       text_or_none(rec.get("disc_type")),
            })

    # Write CSVs
    students_df = pd.DataFrame(students)
    enroll_df = pd.DataFrame(enrollments)
    review_df = pd.DataFrame(review)

    students_df.to_csv(OUT_DIR / "students_clean.csv", index=False)
    enroll_df.to_csv(OUT_DIR / "enrollments_clean.csv", index=False)
    if not review_df.empty:
        # Flatten review (contains nested keys)
        review_flat = review_df.copy()
        for col in review_flat.columns:
            if review_flat[col].dtype == object:
                review_flat[col] = review_flat[col].astype(str)
        review_flat.to_csv(OUT_DIR / "dedup_review.csv", index=False)

    # Summary
    summary = {
        "sources": file_summaries,
        "total_source_rows": len(all_rows),
        "unique_students": len(students),
        "unique_students_deduped": len(clean_groups),
        "review_rows": len(review),
        "total_enrollments": len(enrollments),
        "enrollments_per_year": (
            enroll_df.groupby("year").size().to_dict() if not enroll_df.empty else {}
        ),
        "fee_totals_per_year": {
            yr: {
                "tuition_due":  int(g["total_fee"].sum()),
                "tuition_paid": int(g["total_paid"].sum()),
                "extra_due":    int(g["extra_fee"].sum()),
                "extra_paid":   int(g["extra_paid"].sum()),
            }
            for yr, g in (enroll_df.groupby("year") if not enroll_df.empty else [])
        },
        "distinct_classes": sorted(enroll_df["class_name"].dropna().unique().tolist()) if not enroll_df.empty else [],
        "distinct_sections_per_class": {
            c: sorted([s for s in enroll_df[enroll_df["class_name"] == c]["section_name"].dropna().unique() if s])
            for c in sorted(enroll_df["class_name"].dropna().unique())
        } if not enroll_df.empty else {},
    }
    (OUT_DIR / "summary.json").write_text(json.dumps(summary, indent=2, default=str))

    print(f"\nWrote:")
    print(f"  {OUT_DIR/'students_clean.csv'} ({len(students)} rows)")
    print(f"  {OUT_DIR/'enrollments_clean.csv'} ({len(enrollments)} rows)")
    if not review_df.empty:
        print(f"  {OUT_DIR/'dedup_review.csv'} ({len(review)} rows)")
    print(f"  {OUT_DIR/'summary.json'}")
    print()
    print(f"Unique students: {len(students)}")
    print(f"Enrollments: {len(enrollments)}")
    print(f"Per year: {summary['enrollments_per_year']}")


if __name__ == "__main__":
    main()
