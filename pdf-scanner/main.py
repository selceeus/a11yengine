"""
PDF Accessibility Scanner — HTTP microservice.

POST /scan
  Request:  { "url": "https://example.com/document.pdf" }
  Response: { "violations": [ ... ] }

Each violation:
  {
    "rule_key":       string,          # e.g. "pdf/untagged"
    "severity":       string,          # "critical" | "serious" | "moderate" | "minor"
    "wcag_criteria":  string | null,   # e.g. "1.3.1"
    "description":    string,
    "element_context": string | null,
    "page_number":    int | null
  }

Checks performed:
  pdf/untagged          — 1.3.1  No tagged document structure
  pdf/no-title          — 2.4.2  Missing document title in metadata
  pdf/no-language       — 3.1.1  Missing document language
  pdf/image-only        — 1.1.1  Scanned image with no text layer
  pdf/no-bookmarks      — 2.4.5  Multi-page document without bookmark outline
  pdf/no-display-title  — 2.4.2  ViewerPreferences.DisplayDocTitle not set
  pdf/suspect-tags      — 1.3.1  MarkInfo Suspects flag indicates unreliable tagging
  pdf/figure-no-alt     — 1.1.1  Annotation/figure missing alternative text (per page)
  pdf/form-field-no-label — 4.1.2  Form field missing accessible label/tooltip (per page)
  pdf/link-no-alt       — 2.4.4  Hyperlink annotation missing description (per page)
"""

from __future__ import annotations

import io
import logging
from typing import Optional

import httpx
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, HttpUrl
from pypdf import PdfReader
from pypdf.errors import PdfReadError

logger = logging.getLogger("pdf_scanner")
logging.basicConfig(level=logging.INFO)

app = FastAPI(title="PDF Accessibility Scanner")


# ── Request / Response models ─────────────────────────────────────────────────


class ScanRequest(BaseModel):
    url: HttpUrl


class Violation(BaseModel):
    rule_key: str
    severity: str
    wcag_criteria: Optional[str] = None
    description: str
    element_context: Optional[str] = None
    page_number: Optional[int] = None


class ScanResponse(BaseModel):
    violations: list[Violation]


# ── Accessibility checks ──────────────────────────────────────────────────────


def _check_tagged(reader: PdfReader) -> Optional[Violation]:
    """WCAG 1.3.1 — PDF must contain tagged document structure."""
    catalog = reader.trailer.get("/Root", {})
    mark_info = catalog.get("/MarkInfo", {})
    is_marked = bool(mark_info.get("/Marked", False))

    if not is_marked:
        return Violation(
            rule_key="pdf/untagged",
            severity="critical",
            wcag_criteria="1.3.1",
            description=(
                "PDF has no tagged document structure. "
                "Screen readers cannot determine reading order or identify headings, lists, or tables."
            ),
        )
    return None


def _check_title(reader: PdfReader) -> Optional[Violation]:
    """WCAG 2.4.2 — PDF must have a document title in its metadata."""
    metadata = reader.metadata or {}
    title = metadata.get("/Title", "")

    if not title or not str(title).strip():
        return Violation(
            rule_key="pdf/no-title",
            severity="serious",
            wcag_criteria="2.4.2",
            description=(
                "PDF metadata does not include a document title. "
                "Assistive technologies use the title to identify the document to users."
            ),
        )
    return None


def _check_language(reader: PdfReader) -> Optional[Violation]:
    """WCAG 3.1.1 — PDF must specify a document language."""
    catalog = reader.trailer.get("/Root", {})
    lang = catalog.get("/Lang", "")

    if not lang or not str(lang).strip():
        return Violation(
            rule_key="pdf/no-language",
            severity="serious",
            wcag_criteria="3.1.1",
            description=(
                "PDF does not specify a document language. "
                "Screen readers rely on the language attribute to use correct pronunciation rules."
            ),
        )
    return None


def _check_image_only(reader: PdfReader) -> Optional[Violation]:
    """WCAG 1.1.1 — PDF must not be image-only (scanned without text layer)."""
    page_count = len(reader.pages)
    if page_count == 0:
        return None

    pages_with_text = 0
    sample = min(page_count, 5)

    for i in range(sample):
        text = reader.pages[i].extract_text() or ""
        if text.strip():
            pages_with_text += 1

    if pages_with_text == 0:
        return Violation(
            rule_key="pdf/image-only",
            severity="critical",
            wcag_criteria="1.1.1",
            description=(
                "PDF appears to contain only scanned images with no searchable text layer. "
                "Screen readers cannot read image-only PDFs. Run OCR to add a text layer."
            ),
        )
    return None


def _check_bookmarks(reader: PdfReader) -> Optional[Violation]:
    """WCAG 2.4.5 — Multi-page PDFs should include a bookmark outline."""
    page_count = len(reader.pages)

    if page_count < 2:
        return None

    outlines = reader.outline

    if not outlines:
        return Violation(
            rule_key="pdf/no-bookmarks",
            severity="moderate",
            wcag_criteria="2.4.5",
            description=(
                f"PDF has {page_count} pages but no bookmark outline. "
                "Bookmarks help users navigate long documents, especially with assistive technologies."
            ),
        )
    return None


def _check_figures(reader: PdfReader) -> list[Violation]:
    """WCAG 1.1.1 — Figure annotations must include alternative text."""
    violations: list[Violation] = []

    for page_num, page in enumerate(reader.pages, start=1):
        page_obj = page.get_object()
        struct_tree_root = reader.trailer.get("/Root", {}).get("/StructTreeRoot", {})
        if not struct_tree_root:
            break

        annotations = page_obj.get("/Annots", [])
        for annot_ref in annotations:
            try:
                annot = annot_ref.get_object() if hasattr(annot_ref, "get_object") else annot_ref
                if annot.get("/Subtype") == "/Widget":
                    continue
                alt_text = annot.get("/Contents", "") or annot.get("/TU", "")
                if not alt_text:
                    violations.append(
                        Violation(
                            rule_key="pdf/figure-no-alt",
                            severity="serious",
                            wcag_criteria="1.1.1",
                            description="An annotation or figure on this page is missing alternative text.",
                            page_number=page_num,
                        )
                    )
            except Exception:
                pass

    return violations


def _check_display_title(reader: PdfReader) -> Optional[Violation]:
    """WCAG 2.4.2 — ViewerPreferences should set DisplayDocTitle to true."""
    catalog = reader.trailer.get("/Root", {})
    viewer_prefs = catalog.get("/ViewerPreferences", {})
    display_title = bool(viewer_prefs.get("/DisplayDocTitle", False))

    if not display_title:
        return Violation(
            rule_key="pdf/no-display-title",
            severity="moderate",
            wcag_criteria="2.4.2",
            description=(
                "PDF viewer preferences do not set DisplayDocTitle to true. "
                "The document title, not the filename, should be displayed in the viewer title bar "
                "so assistive technologies can identify the document correctly."
            ),
        )
    return None


def _check_suspect_tags(reader: PdfReader) -> Optional[Violation]:
    """WCAG 1.3.1 — MarkInfo Suspects flag indicates unreliable tagging."""
    catalog = reader.trailer.get("/Root", {})
    mark_info = catalog.get("/MarkInfo", {})
    suspects = bool(mark_info.get("/Suspects", False))

    if suspects:
        return Violation(
            rule_key="pdf/suspect-tags",
            severity="moderate",
            wcag_criteria="1.3.1",
            description=(
                "PDF MarkInfo indicates Suspects=true, meaning the document structure tags "
                "may be incomplete or unreliable. Review and correct the tagging to ensure "
                "screen readers can accurately interpret the reading order."
            ),
        )
    return None


def _check_form_fields(reader: PdfReader) -> list[Violation]:
    """WCAG 4.1.2 — Interactive form fields must have an accessible label (tooltip)."""
    violations: list[Violation] = []

    for page_num, page in enumerate(reader.pages, start=1):
        page_obj = page.get_object()
        annotations = page_obj.get("/Annots", [])

        for annot_ref in annotations:
            try:
                annot = annot_ref.get_object() if hasattr(annot_ref, "get_object") else annot_ref
                if annot.get("/Subtype") != "/Widget":
                    continue

                # Skip push-buttons — they carry their label via /CA (caption) instead.
                if annot.get("/FT") == "/Btn" and (int(annot.get("/Ff", 0)) & (1 << 16)):
                    continue

                tooltip = annot.get("/TU", "")
                if not tooltip or not str(tooltip).strip():
                    violations.append(
                        Violation(
                            rule_key="pdf/form-field-no-label",
                            severity="serious",
                            wcag_criteria="4.1.2",
                            description=(
                                "A form field is missing an accessible label (TU tooltip entry). "
                                "Screen readers use the tooltip as the field's accessible name; "
                                "without it users cannot determine the field's purpose."
                            ),
                            page_number=page_num,
                        )
                    )
            except Exception:
                pass

    return violations


def _check_links(reader: PdfReader) -> list[Violation]:
    """WCAG 2.4.4 — Link annotations should carry a Contents description."""
    violations: list[Violation] = []

    for page_num, page in enumerate(reader.pages, start=1):
        page_obj = page.get_object()
        annotations = page_obj.get("/Annots", [])

        for annot_ref in annotations:
            try:
                annot = annot_ref.get_object() if hasattr(annot_ref, "get_object") else annot_ref
                if annot.get("/Subtype") != "/Link":
                    continue

                contents = annot.get("/Contents", "")
                if not contents or not str(contents).strip():
                    violations.append(
                        Violation(
                            rule_key="pdf/link-no-alt",
                            severity="moderate",
                            wcag_criteria="2.4.4",
                            description=(
                                "A hyperlink annotation is missing a description (Contents entry). "
                                "Screen readers cannot convey link purpose without a descriptive label."
                            ),
                            page_number=page_num,
                        )
                    )
            except Exception:
                pass

    return violations


# ── Endpoint ──────────────────────────────────────────────────────────────────


@app.post("/scan", response_model=ScanResponse)
async def scan(request: ScanRequest) -> ScanResponse:
    url = str(request.url)
    logger.info("Scanning PDF: %s", url)

    try:
        async with httpx.AsyncClient(timeout=30, follow_redirects=True) as client:
            response = await client.get(url)
            response.raise_for_status()
    except httpx.HTTPStatusError as exc:
        raise HTTPException(
            status_code=422,
            detail=f"Cannot download PDF: HTTP {exc.response.status_code}",
        ) from exc
    except httpx.RequestError as exc:
        raise HTTPException(
            status_code=422,
            detail=f"Cannot download PDF: {exc}",
        ) from exc

    try:
        reader = PdfReader(io.BytesIO(response.content))
    except PdfReadError as exc:
        raise HTTPException(
            status_code=422,
            detail=f"Cannot parse PDF: {exc}",
        ) from exc

    violations: list[Violation] = []

    for check in (
        _check_tagged,
        _check_title,
        _check_language,
        _check_image_only,
        _check_bookmarks,
        _check_display_title,
        _check_suspect_tags,
    ):
        result = check(reader)
        if result is not None:
            violations.append(result)

    violations.extend(_check_figures(reader))
    violations.extend(_check_form_fields(reader))
    violations.extend(_check_links(reader))

    logger.info("Found %d violation(s) in %s", len(violations), url)
    return ScanResponse(violations=violations)


@app.get("/health")
async def health() -> dict[str, str]:
    return {"status": "ok"}
