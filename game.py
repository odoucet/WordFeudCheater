from OcrWordFeudBoard import Square, OcrWordfeudBoard, WordFeudBoard
import sys
import random
import scrabbler as sc
import os
import shutil
import time
import re
import utilities.logger as logger
import utilities.errors as errors

def remaining_letters(game, played_letters):
    # A list to collect keys to remove after iteration
    keys_to_remove = []
    formatted_list = []
    tiles_left = 0
    # Reducing the tile count based on played letters
    for letter in played_letters:
        if letter in game.tiles_count:
            #print(f"Letter: {letter} Count: {game.tiles_count[letter]}")
            game.tiles_count[letter] -= 1
            # Check if the count is 0 to mark for removal
            if game.tiles_count[letter] == 0:
                keys_to_remove.append(letter)

    # Removing entries with 0 count
    for key in keys_to_remove:
        del game.tiles_count[key]

    sorted_tile_count = dict(sorted(game.tiles_count.items()))
    # Formatting and printing the sorted tile counts
    for letter, count in sorted_tile_count.items():
        formatted_list.append(f"{letter}={count} ")
        tiles_left += count
    if tiles_left <= 7:
        tiles_left = game.tiles_max - tiles_left - 1
    else:
        tiles_left -= 7

    return tiles_left, ''.join(formatted_list)

def find_move(image_path, ocr, wf, game):
    rack, played_letters = ocr.detect_rack_and_board(image_path)

    # Transfer OCR'd board to WordFeudBoard
    for i in range(15):
        for j in range(15):
            if ocr.read_square(i,j) != '  ':
                wf.board[i][j].letter = ocr.read_square(i,j).strip()
                print(f"({i},{j}): {wf.board[i][j].letter}")
    _ , placement = wf.all_board_words(wf.board)

    # Play each word on the board with game object
    for i in placement:
        game.play(i[0], i[1], i[2])

    print("")

    # Show the board
    game.show()
    rack = ''.join(rack)
    print(f"\nRack: {rack}\n")
    count, letter = remaining_letters(game, played_letters+rack)

    print(f"Bag Tiles: ({count})\nTiles Left: {letter}\n")
    print("\nPossible Moves:")

    print("DEBUG MODE, EXITING HERE")
    sys.exit(0)

    options = game.find_best_moves(rack)
    op_max = len(options)-1
    if options:
        user_input = input(f"\nEnter option to play from 0-{op_max} ^C^C to quit: ")

        if user_input.isdigit() and int(user_input) in range(op_max):
            move = options[int(user_input)]

            pattern = r'game.play\(\((.*?)\),"([^"]*)","([^"]*)"\)'
            # Searching for the pattern
            match = re.search(pattern, str(move))

            # Extracting the groups
            tuple_str = match.group(1)  # This will be a string that looks like a tuple
            first_string = match.group(2)
            second_string = match.group(3)

            # Converting the tuple string to an actual tuple
            tuple_values = tuple(map(int, tuple_str.split(', ')))

            game.play(tuple_values, first_string, second_string)
            game.show()
            print(f"\nPlayed: {first_string} at {tuple_values} with {second_string}\n\n")

if __name__ == "__main__":
    image_path = "tests/test2.jpg"
    ocr = OcrWordfeudBoard(image_path)
    wf = WordFeudBoard()
    game = sc.Game(board="wordfeud", language="fr")

    find_move(image_path, ocr, wf, game)
    ocr = OcrWordfeudBoard(image_path)
    wf = WordFeudBoard()
    game = sc.Game(board="wordfeud", language="fr", dict=game.dictionary)

# TODO create a list of killer small words to not make available to the opponent
# TODO check if tiles left over calculation is accurate