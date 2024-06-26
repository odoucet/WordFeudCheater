from OcrWordFeudBoard import Square, OcrWordfeudBoard, WordFeudBoard
import argparse
import json 

# this script is called in CLI. Read arguments:
parser = argparse.ArgumentParser(
                    prog='Wordfeud to JSON',
                    description='Convert a wordfeud screenshot to JSON')
parser.add_argument('-l', '--language')
parser.add_argument('-i', '--image', required=True)

args = parser.parse_args()
image_path = args.image

if not args.language:
    language = "en"
else:
    language = args.language

    ocr = OcrWordfeudBoard()

    # Read the board and rack
    rack, _ = ocr.detect_rack_and_board(image_path)

    board = ocr.read_boardarray()


    resultObject = {
        'status': 'success',
        'rack': rack,
        'board': board
    }

    # print dict as JSON
    print(json.dumps(resultObject, indent=4))
