"""
Build a designed PDF from docs/SOP.md.

Output: docs/SOP.pdf

Run: py docs/build_sop_pdf.py
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

import fitz  # PyMuPDF
from markdown_pdf import MarkdownPdf, Section


# ── Paths ────────────────────────────────────────────────────────────────────

ROOT = Path(__file__).resolve().parent
SRC = ROOT / "SOP.md"
OUT = ROOT / "SOP.pdf"


# ── Brand ────────────────────────────────────────────────────────────────────

BRAND_PRIMARY = "#1e3a8a"   # deep indigo
BRAND_ACCENT = "#d97706"    # amber
INK = "#111827"
SUBTLE = "#6b7280"
RULE = "#e5e7eb"
CODE_BG = "#f3f4f6"
TABLE_HEAD_BG = "#1e3a8a"
TABLE_ALT_ROW = "#f9fafb"


# ── Cover content ────────────────────────────────────────────────────────────

COVER_MD = """\
# School's 1

## School Admin Operating Manual

---

**A Trivartha Tech Pvt Ltd product**

**Version 1.0 — 2026-04-29**

**For: School Admin**

---

> A practical, module-by-module reference for the school admin who runs
> School's 1 day-to-day — covering first-time setup, every menu in the
> sidebar, daily / weekly / monthly / annual operating rhythm, and
> troubleshooting.

*© Trivartha Tech Pvt Ltd*
"""

COVER_CSS = f"""
body {{ font-family: 'Segoe UI', Arial, sans-serif; color: {INK}; text-align: center; }}
h1 {{ font-size: 48pt; color: {BRAND_PRIMARY}; margin-top: 120pt; font-weight: 800; }}
h2 {{ font-size: 22pt; color: {INK}; font-weight: 500; margin-top: 12pt; }}
hr {{ border: none; border-top: 2pt solid {BRAND_ACCENT}; margin: 30pt 80pt; }}
p {{ font-size: 12pt; margin: 8pt 0; }}
strong {{ color: {BRAND_PRIMARY}; }}
blockquote {{ border: none; color: {SUBTLE}; font-style: italic; font-size: 11pt; margin: 30pt 50pt; padding: 0; }}
em {{ color: {SUBTLE}; font-size: 9pt; }}
"""


# ── Body CSS — kept compact (long CSS strings can hang markdown-pdf) ────────

BODY_CSS = f"""
body {{ font-family: 'Segoe UI', Arial, sans-serif; color: {INK}; line-height: 1.5; font-size: 10.5pt; }}
h1 {{ font-size: 22pt; color: {BRAND_PRIMARY}; border-bottom: 3pt solid {BRAND_PRIMARY}; padding-bottom: 4pt; margin-top: 24pt; font-weight: 700; }}
h2 {{ font-size: 15pt; color: {BRAND_PRIMARY}; border-bottom: 1pt solid {RULE}; padding-bottom: 3pt; margin-top: 18pt; font-weight: 700; }}
h3 {{ font-size: 12pt; color: {INK}; margin-top: 14pt; font-weight: 600; }}
h4 {{ font-size: 11pt; color: {BRAND_PRIMARY}; margin-top: 10pt; font-weight: 600; }}
p {{ margin: 0 0 8pt 0; }}
ul, ol {{ margin: 4pt 0 8pt 0; padding-left: 22pt; }}
li {{ margin: 2pt 0; }}
code {{ background: {CODE_BG}; color: {BRAND_ACCENT}; padding: 1pt 4pt; font-family: Consolas, monospace; font-size: 9.5pt; }}
pre {{ background: {CODE_BG}; border-left: 3pt solid {BRAND_PRIMARY}; padding: 8pt 12pt; font-family: Consolas, monospace; font-size: 9pt; }}
table {{ border-collapse: collapse; width: 100%; font-size: 9.5pt; margin: 10pt 0; }}
th {{ background: {TABLE_HEAD_BG}; color: white; padding: 6pt 8pt; text-align: left; font-weight: 600; }}
td {{ padding: 5pt 8pt; border: 1pt solid {RULE}; vertical-align: top; }}
tr:nth-child(even) td {{ background: {TABLE_ALT_ROW}; }}
blockquote {{ border-left: 3pt solid {BRAND_ACCENT}; background: #fffbeb; padding: 8pt 14pt; margin: 10pt 0; }}
hr {{ border: none; border-top: 1pt solid {RULE}; margin: 14pt 0; }}
strong {{ color: {INK}; font-weight: 600; }}
a {{ color: {BRAND_PRIMARY}; text-decoration: none; }}
"""


# ── Source preparation ──────────────────────────────────────────────────────

def load_and_clean_source() -> str:
    text = SRC.read_text(encoding="utf-8")

    # Strip the original H1 title (cover replaces it)
    text = re.sub(
        r"\A# School's 1.*?(?=^## Who This Manual Is For)",
        "",
        text,
        count=1,
        flags=re.DOTALL | re.MULTILINE,
    )

    # Strip the manual TOC (markdown-pdf builds one)
    text = re.sub(
        r"^## Table of Contents.*?(?=^## Glossary)",
        "",
        text,
        count=1,
        flags=re.DOTALL | re.MULTILINE,
    )

    # markdown-pdf chokes on unresolvable [text](#anchor) links
    text = re.sub(r"\[([^\]]+)\]\(#[^)]+\)", r"\1", text)

    # The first heading must be H1 for PyMuPDF's set_toc
    text = "# Introduction\n\n" + text.lstrip()

    return text


# ── Build ────────────────────────────────────────────────────────────────────

def build_pdf() -> None:
    body = load_and_clean_source()

    pdf = MarkdownPdf(toc_level=2, mode="commonmark")
    pdf.meta["title"] = "School's 1 — School Admin Operating Manual"
    pdf.meta["author"] = "Trivartha Tech Pvt Ltd"
    pdf.meta["subject"] = "School Admin Operating Manual for School's 1 ERP"
    pdf.meta["keywords"] = "School's 1, ERP, SOP, Trivartha Tech, school admin"

    # Use the same border tuple for both sections — mismatched borders
    # have caused markdown-pdf to hang on this content.
    BORDERS = (54, 60, -54, -54)

    # Cover — no TOC entry
    print("  [1/3] rendering cover...")
    pdf.add_section(
        Section(COVER_MD, toc=False, paper_size="A4", borders=BORDERS),
        user_css=COVER_CSS,
    )

    # Body — auto-TOC at H1+H2
    print("  [2/3] rendering body...")
    pdf.add_section(
        Section(body, toc=True, paper_size="A4", borders=BORDERS),
        user_css=BODY_CSS,
    )

    print("  [3/3] saving...")
    pdf.save(str(OUT))


# ── Post-process: page numbers + running header ─────────────────────────────

def add_headers_and_footers() -> None:
    doc = fitz.open(str(OUT))
    page_count = doc.page_count

    HEADER_TEXT = "School's 1 — School Admin Operating Manual"
    FOOTER_LEFT = "Trivartha Tech Pvt Ltd"

    def rgb(hex_str):
        h = hex_str.lstrip("#")
        return tuple(int(h[i:i+2], 16) / 255.0 for i in (0, 2, 4))

    primary_rgb = rgb(BRAND_PRIMARY)
    subtle_rgb = rgb(SUBTLE)
    rule_rgb = rgb(RULE)

    for i, page in enumerate(doc):
        if i == 0:
            continue  # skip cover

        rect = page.rect
        w, h = rect.width, rect.height

        # Header
        page.draw_line(p1=(54, 36), p2=(w - 54, 36), color=rule_rgb, width=0.5)
        page.insert_text(point=(54, 30), text=HEADER_TEXT, fontsize=8,
                         fontname="helv", color=primary_rgb)

        # Footer
        page.draw_line(p1=(54, h - 36), p2=(w - 54, h - 36),
                       color=rule_rgb, width=0.5)
        page.insert_text(point=(54, h - 24), text=FOOTER_LEFT, fontsize=8,
                         fontname="helv", color=subtle_rgb)
        page_label = f"Page {i + 1} of {page_count}"
        approx_width = len(page_label) * 4
        page.insert_text(point=(w - 54 - approx_width, h - 24),
                         text=page_label, fontsize=8, fontname="helv",
                         color=subtle_rgb)

    doc.save(str(OUT), incremental=True, encryption=fitz.PDF_ENCRYPT_KEEP)
    doc.close()


def main() -> int:
    if not SRC.exists():
        print(f"ERROR: source not found: {SRC}", file=sys.stderr)
        return 1

    print(f"Building {OUT.name} from {SRC.name}...")
    build_pdf()
    print("  adding headers/footers...")
    add_headers_and_footers()

    size_kb = OUT.stat().st_size / 1024
    print(f"\nDone. {OUT} - {size_kb:.0f} KB")
    return 0


if __name__ == "__main__":
    sys.exit(main())
