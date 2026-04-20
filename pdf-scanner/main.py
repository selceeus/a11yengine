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

    for check in (_check_tagged, _check_title, _check_language, _check_image_only, _check_bookmarks):
        result = check(reader)
        if result is not None:
            violations.append(result)

    violations.extend(_check_figures(reader))

    logger.info("Found %d violation(s) in %s", len(violations), url)
    return ScanResponse(violations=violations)


@app.get("/health")
async def health() -> dict[str, str]:
    return {"status": "ok"}
