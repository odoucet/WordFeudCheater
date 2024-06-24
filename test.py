import unittest
from OcrWordFeudBoard import OcrWordfeudBoard

tests = [
    {
        "image_path": "tests/test1.jpg",
        "rack": "GGORTIE",
        "played_letters": {
            'CHENUE', 'OUREZ', 'OUKAS', 'AWA'
        }
    }
]

class TestOcr(unittest.TestCase):
    def test_detect_rack_and_board(self):
        for test in tests:
            image_path = test["image_path"]
            ocr = OcrWordfeudBoard(image_path)

            rack, played_letters = ocr.detect_rack_and_board(image_path)

            # compare
            self.assertEqual(''.join(rack), test["rack"])

            # played letters:
            played_letters_sorted = ''.join(sorted(played_letters)).strip()
            expected_played_letters_sorted = ''.join(sorted(test["played_letters"]))
            

            self.assertEqual(played_letters_sorted, expected_played_letters_sorted)


if __name__ == '__main__':
    unittest.main()