import unittest
import os
from OcrWordFeudBoard import OcrWordfeudBoard
import cv2

tests = [
    {
        "image_path": "tests/screens/test1.jpg",
        "rack": "GGORTIE",
        "played_letters": {
            'CHENUE', 'OUREZ', 'OUKAS', 'AWA'
        }
    },
    {
        "image_path": "tests/screens/test2.jpg",
        "rack": "EJECSLN",
        "played_letters": {
            'DROIT', 'TYPO', 'UEE', 'HUEZ', 'LGS', 'UNS', 'CRAME', 'FNIONS', 'LUI', 'HIT', 'BMBER', 'LSE', 'XONS', 'VDKA', 'DEWAR', 'MOQUIT', 'BUTINEES', 'EVIE'
        }
    },
    {
        "image_path": "tests/screens/test3.png",
        "rack": "FAILC",
        "played_letters": {
            'GAIE', 'TAL','RAT', 'EANS', 'DROIT','YPO', 'UEE', 'HUEZ', 'LGS', 'UNS', 'FANIOS','CRMEZ', 'LUI', 'HIT', 'JE', 'BMBER','LSE','XONS','VDKA','DEWAR','MOQIT','EVIE', 'BUTIEES'
        }
    },
]

class TestOcr(unittest.TestCase):
    def test_detect_rack_and_board(self):
        for test in tests:
            ocr = OcrWordfeudBoard()

            image_path = test["image_path"]

            rack, played_letters = ocr.detect_rack_and_board(image_path)

            played_letters_sorted = ''.join(sorted(played_letters)).strip()
            expected_played_letters_sorted = ''.join(sorted(''.join(test["played_letters"]))).strip()

            with self.subTest():

                # compare
                self.assertEqual(''.join(rack), test["rack"])

                # played letters:
                self.assertEqual(played_letters_sorted, expected_played_letters_sorted)

    def test_squares(self):
        # load available letters = files in tests/squares:

        squares = []
        for file in os.listdir("tests/squares"):
            if file.endswith(".png"):
                squares.append(file)

        ocr = OcrWordfeudBoard()

        for square in squares:
            square = "tests/squares/" + square
            img = cv2.imread(square)
            if img is None:
                raise ValueError("Image '"+img+"' not found or path is incorrect")

            letter = ocr.ocr_tile(img, save_image=False, comments="")

            with self.subTest():
                # filename is ocr_X,X-<letter>.png -> extract letter
                expected = square.split("-")[1].split(".")[0]
                self.assertEqual(letter, expected, msg=f"{square}: extracted {letter} but should be {expected}")


if __name__ == '__main__':
    unittest.main()