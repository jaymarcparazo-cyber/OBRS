from __future__ import annotations

import hmac
import io
import logging
import os
import re
import threading
import warnings
from typing import Any

import numpy as np
from deepface import DeepFace
from flask import Flask, Response, jsonify, request
from PIL import Image, UnidentifiedImageError
from rapidocr_onnxruntime import RapidOCR
from waitress import serve

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("obrs-face-verification")

Image.MAX_IMAGE_PIXELS = 20_000_000
MAX_IMAGE_BYTES = 5 * 1024 * 1024
MODEL_NAME = "Facenet512"
TOKEN = os.environ.get("OBRS_FACE_SERVICE_TOKEN", "")
INFERENCE_LOCK = threading.Lock()
OCR_ENGINE: RapidOCR | None = None

GOVERNMENT_ISSUER_PATTERNS = (
    "REPUBLICOFTHEPHILIPPINES",
    "GOVERNMENTOFTHEPHILIPPINES",
    "PHILIPPINEIDENTIFICATIONSYSTEM",
    "PHILIPPINEIDENTIFICATIONCARD",
    "PAMBANSANGPAGKAKAKILANLAN",
    "NATIONALID",
    "PHILSYS",
    "LANDTRANSPORTATIONOFFICE",
    "DEPARTMENTOFFOREIGNAFFAIRS",
    "PROFESSIONALREGULATIONCOMMISSION",
    "COMMISSIONONELECTIONS",
    "SOCIALSECURITYSYSTEM",
    "GOVERNMENTSERVICEINSURANCESYSTEM",
    "PHILIPPINEHEALTHINSURANCECORPORATION",
    "PHILHEALTH",
    "PHILIPPINEPOSTALCORPORATION",
    "POSTALID",
    "UNIFIEDMULTIPURPOSEID",
    "UMID",
    "BARANGAY",
)
GOVERNMENT_ISSUER_ABBREVIATIONS = ("LTO", "DFA", "PRC", "COMELEC", "SSS", "GSIS")
IDENTITY_FIELD_GROUPS = (
    ("SURNAME", "GIVENNAME", "FIRSTNAME", "LASTNAME", "MIDDLENAME", "FULLNAME", "NAME"),
    ("DATEOFBIRTH", "BIRTHDATE", "DOB"),
    ("ADDRESS", "RESIDENCE"),
    ("IDNUMBER", "IDENTIFICATIONNUMBER", "PHILSYSCARDNUMBER", "PCN", "LICENSE"),
    ("SEX", "GENDER", "NATIONALITY"),
    ("SIGNATURE",),
)

app = Flask(__name__)
app.config["MAX_CONTENT_LENGTH"] = 2 * MAX_IMAGE_BYTES + 1024 * 1024


def read_image(data: bytes) -> np.ndarray:
    with warnings.catch_warnings():
        warnings.simplefilter("error", Image.DecompressionBombWarning)
        with Image.open(io.BytesIO(data)) as image:
            if image.format not in {"JPEG", "PNG"}:
                raise ValueError("Unsupported image format.")
            if image.width < 80 or image.height < 80:
                raise ValueError("Image is too small.")
            if image.width > 6000 or image.height > 6000:
                raise ValueError("Image dimensions are too large.")
            rgb_image = image.convert("RGB")
            return np.asarray(rgb_image)[:, :, ::-1].copy()


def contains_government_id_evidence(text: str) -> bool:
    normalized_text = re.sub(r"[^A-Z0-9]+", " ", text.upper())
    compact_text = re.sub(r"[^A-Z0-9]", "", text.upper())
    has_issuer = any(term in compact_text for term in GOVERNMENT_ISSUER_PATTERNS) or any(
        re.search(rf"\b{abbreviation}\b", normalized_text)
        for abbreviation in GOVERNMENT_ISSUER_ABBREVIATIONS
    )
    matched_fields = sum(
        any(term in compact_text for term in field_group)
        for field_group in IDENTITY_FIELD_GROUPS
    )
    return has_issuer and matched_fields >= 2


def read_id_text(image: np.ndarray) -> str:
    global OCR_ENGINE
    if OCR_ENGINE is None:
        OCR_ENGINE = RapidOCR()

    ocr_results, _ = OCR_ENGINE(image)
    return " ".join(
        str(result[1])
        for result in ocr_results or []
        if len(result) >= 3 and float(result[2]) >= 0.2
    )


def unavailable_response(reason: str) -> Response:
    return jsonify({"result": "unavailable", "reason": reason})


@app.get("/health")
def health() -> Response:
    return jsonify({"status": "ok"})


@app.post("/verify")
def verify() -> Response | tuple[Response, int]:
    if not TOKEN or not hmac.compare_digest(
        request.headers.get("X-Verification-Token", ""), TOKEN
    ):
        return jsonify({"error": "Unauthorized"}), 401

    id_upload = request.files.get("id_image")
    selfie_upload = request.files.get("selfie")
    if id_upload is None or selfie_upload is None:
        return jsonify({"error": "Both images are required."}), 400

    id_bytes = id_upload.read(MAX_IMAGE_BYTES + 1)
    selfie_bytes = selfie_upload.read(MAX_IMAGE_BYTES + 1)
    if len(id_bytes) > MAX_IMAGE_BYTES or len(selfie_bytes) > MAX_IMAGE_BYTES:
        return jsonify({"error": "Image exceeds the size limit."}), 413

    try:
        id_image = read_image(id_bytes)
        selfie_image = read_image(selfie_bytes)
    except (
        Image.DecompressionBombError,
        Image.DecompressionBombWarning,
        UnidentifiedImageError,
        OSError,
        ValueError,
    ):
        return unavailable_response("invalid_image")

    try:
        with INFERENCE_LOCK:
            id_text = read_id_text(id_image)
            if not contains_government_id_evidence(id_text):
                return unavailable_response("id_document_unrecognized")

            id_faces = DeepFace.extract_faces(
                img_path=id_image,
                detector_backend="opencv",
                enforce_detection=True,
                align=True,
            )
            selfie_faces = DeepFace.extract_faces(
                img_path=selfie_image,
                detector_backend="opencv",
                enforce_detection=True,
                align=True,
            )
            if len(id_faces) != 1 or len(selfie_faces) != 1:
                return unavailable_response("one_face_per_image_required")

            result: dict[str, Any] = DeepFace.verify(
                img1_path=id_image,
                img2_path=selfie_image,
                model_name=MODEL_NAME,
                detector_backend="opencv",
                distance_metric="cosine",
                enforce_detection=True,
                silent=True,
            )

        if result.get("verified") is True:
            return jsonify({"result": "match"})
        return jsonify({"result": "mismatch"})
    except Exception:
        logger.exception("Identity verification failed; staff review is required.")
        return unavailable_response("verification_unavailable")


@app.errorhandler(413)
def request_too_large(_error: Exception) -> tuple[Response, int]:
    return jsonify({"error": "Image exceeds the size limit."}), 413


def main() -> None:
    if not TOKEN:
        raise RuntimeError("OBRS_FACE_SERVICE_TOKEN must be configured.")

    # Load model weights before accepting requests; image data is never written to disk.
    DeepFace.build_model(MODEL_NAME)
    serve(app, host="127.0.0.1", port=8765, threads=1, max_request_body_size=11 * 1024 * 1024)


if __name__ == "__main__":
    main()
