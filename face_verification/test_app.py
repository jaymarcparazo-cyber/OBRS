from io import BytesIO
import unittest
from unittest.mock import patch

from PIL import Image

import app as face_app
from app import contains_government_id_evidence


class GovernmentIdEvidenceTests(unittest.TestCase):
    def test_philippine_id_text_with_identity_fields_is_eligible(self) -> None:
        text = (
            "REPUBLIC OF THE PHILIPPINES "
            "NAME JUAN DELA CRUZ DATE OF BIRTH 01 JAN 1990"
        )
        self.assertTrue(contains_government_id_evidence(text))

    def test_ocr_text_without_spaces_between_words_is_eligible(self) -> None:
        text = (
            "REPUBLICOF THEPHILIPPINES NAME:JUANDELACRUZ "
            "DATE OF BIRTH: 01 JANUARY 1990"
        )
        self.assertTrue(contains_government_id_evidence(text))

    def test_philsys_national_id_wording_and_joined_fields_are_eligible(self) -> None:
        text = (
            "PAMBANSANG PAGKAKAKILANLAN PHILIPPINE IDENTIFICATION CARD "
            "SURNAME: DELA CRUZ GIVEN NAMES: JUAN MIGUEL "
            "DATEOFBIRTH: 01 JAN 1990 PHILSYS CARD NUMBER: 123456789"
        )
        self.assertTrue(contains_government_id_evidence(text))

    def test_national_id_heading_and_joined_ocr_labels_are_eligible(self) -> None:
        text = (
            "NATIONAL ID SURNAME DELA CRUZ GIVENNAMES JUAN "
            "DATEOFBIRTH 01 JAN 1990 SEX M"
        )
        self.assertTrue(contains_government_id_evidence(text))

    def test_portrait_or_regular_photo_text_is_not_eligible(self) -> None:
        self.assertFalse(contains_government_id_evidence(""))
        self.assertFalse(contains_government_id_evidence("Juan Dela Cruz"))
        self.assertFalse(
            contains_government_id_evidence(
                "WELCOME TO MANILA NAME JUAN DELA CRUZ"
            )
        )

    def test_issuer_without_identity_fields_is_not_eligible(self) -> None:
        self.assertFalse(
            contains_government_id_evidence("REPUBLIC OF THE PHILIPPINES")
        )

    def test_unrecognized_image_cannot_reach_face_matching(self) -> None:
        image_bytes = BytesIO()
        Image.new("RGB", (160, 120), "white").save(image_bytes, format="PNG")
        image_bytes.seek(0)
        client = face_app.app.test_client()

        with (
            patch.object(face_app, "TOKEN", "test-token"),
            patch.object(face_app, "read_id_text", return_value=""),
            patch.object(face_app.DeepFace, "extract_faces") as extract_faces,
        ):
            response = client.post(
                "/verify",
                headers={"X-Verification-Token": "test-token"},
                data={
                    "id_image": (BytesIO(image_bytes.getvalue()), "id.png"),
                    "selfie": (BytesIO(image_bytes.getvalue()), "selfie.png"),
                },
                content_type="multipart/form-data",
            )

        self.assertEqual(response.get_json()["result"], "unavailable")
        self.assertEqual(response.get_json()["reason"], "id_document_unrecognized")
        extract_faces.assert_not_called()


if __name__ == "__main__":
    unittest.main()
