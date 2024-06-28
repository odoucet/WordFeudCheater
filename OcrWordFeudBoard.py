import os
import cv2
import numpy as np
import pytesseract
import matplotlib.pyplot as plt
from PIL import Image, ImageDraw
import regexp as re
import random
import copy
import utilities.logger as logger
import utilities.errors as errors
class OcrWordfeudBoard():
    def __init__(self):
        self.board = [['  ' for _ in range(15)] for _ in range(15)]

    def update_square(self, x, y, value):
        self.board[x][y] = value

    def read_square(self, x, y):
        return self.board[x][y]
    
    def read_boardarray(self):
        return self.board

    def detect_rack_and_board(self, image_path):
        """
        Detects the rack and board from the given image.
        """
        #logger.info("Cut image to grab rack and board...")
        image = cv2.imread(image_path)

        if image is None:
            raise ValueError("Image '"+image_path+"' not found or path is incorrect")

        # Convert the image to grayscale
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)

        # Apply a Canny edge detector to find edges
        edges = cv2.Canny(gray, 50, 150)

        # Dilate the edges to close gaps
        kernel = np.ones((10, 10), np.uint8)
        dilated_edges = cv2.dilate(edges, kernel, iterations=1)

        # Find contours
        contours, _ = cv2.findContours(dilated_edges, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

        # Sort contours by area and remove small ones
        contours = sorted(contours, key=cv2.contourArea, reverse=True)
        
        # Initialize variables to store the bounding boxes
        bottom_line_contour = None
        board_contour = None

        for contour in contours:
            x, y, w, h = cv2.boundingRect(contour)
            # Filter by size to get the bottom line (usually the smallest height and at the bottom)
            if h > 30 and y > image.shape[0] // 2:
                bottom_line_contour = contour
                break

        # Assume the largest contour is the board
        board_contour = contours[0]

        # use a special image for crop
        _, thresh = cv2.threshold(gray, 128, 255, cv2.THRESH_BINARY | cv2.THRESH_OTSU)
        #cv2.imwrite('images/debug-thresh.png', thresh)

        # Get bounding boxes
        if bottom_line_contour is not None:
            x, y, w, h = cv2.boundingRect(bottom_line_contour)
            cropped_letters_img = thresh[y:y+h, :]
        else:
            raise ValueError("Bottom line contour not found")

        if board_contour is not None:
            x, y, w, h = cv2.boundingRect(board_contour)
            plateau_game_img = image[y:y+h, x:x+w] # original image is working better for board
        else:
            raise ValueError("Board contour not found")

        # Check if the cropped regions are valid
        if cropped_letters_img.size == 0:
            raise ValueError("Cropped letters image is empty")
        if plateau_game_img.size == 0:
            raise ValueError("Plateau game image is empty")

        # Resize board game to always be 960 × 960 pixels
        plateau_game_img = cv2.resize(plateau_game_img, (960, 960))
        # convert 16-bit to 8-bit
        plateau_game_img = cv2.normalize(plateau_game_img, None, 0, 255, cv2.NORM_MINMAX, dtype=cv2.CV_8U)

        # Debug
        #cv2.imwrite('images/debug-rack.png', cropped_letters_img)
        #cv2.imwrite('images/debug-board.png', plateau_game_img)

        # now, extract the letters from the rack
        rack_letters = []
        square_size = cropped_letters_img.shape[1] // 7
        padding = 15

        for i in range(7):
            top_left_y = padding
            bottom_right_y = square_size - padding
            top_left_x = i * square_size + padding
            bottom_right_x = (i + 1) * square_size - padding * 2
            square = cropped_letters_img[top_left_y:bottom_right_y, top_left_x:bottom_right_x]
            #cv2.imwrite('images/tmp/'+str(i)+'.png', square)
            #print(f"top_left_y: {top_left_y}, bottom_right_y: {bottom_right_y}, top_left_x: {top_left_x}, bottom_right_x: {bottom_right_x}")
            
            # check if square is majority white:
            white_pixels = cv2.countNonZero(square)
            total_pixels = square.shape[0] * square.shape[1]
            if white_pixels / total_pixels > 0.5:
                # DEBUG POINT TO RECORD SQUARES THAT ARE NOT WORKING
                #if i == 2:
                #    cv2.imwrite('tests/squares/00ocr_'+str(i)+'-UNK.png', square)
                letter = self.ocr_tile(square,threshold=2, save_image=False, comments="")
                rack_letters.append(letter)

        # now, detect board game letters
        squares = self.segment_board_into_squares(plateau_game_img)
        board_letters = self.read_board(squares)

        return rack_letters, board_letters

    def ocr_tile(self, tile, threshold=5, save_image=False, comments=""):
        """
        Perform OCR (Optical Character Recognition) on a given tile image.

        Args:
            tile: A numpy array representing the tile image.

        Returns:
            The recognized letter from the tile image, or '+' if no letter is recognized.
        """
        image = Image.fromarray(tile)
        
        # psm 10: Treat the image as a single character.
        # oem 3: use best OCR engine available
        # tessedit_char_whitelist: limit characters to A-Z and space
        letter = pytesseract.image_to_string(image, config='--psm 10 --oem 3 -c tessedit_char_whitelist="ABCDEFGHIJKLMNOPQRSTUVWXYZ "').strip()

        if len(letter) == 0:
            letter = '%'
        else:
            letter = letter[0]

        if save_image:
            image.save('images/tmp/ocr_tile_'+letter+comments+'.png')
        if comments:
            print(f"OCR:{letter} {comments} ")

        return letter

    def classify_dominant_color(self, image, threshold=50):
        # Convert to HSV
        hsv = cv2.cvtColor(image, cv2.COLOR_BGR2HSV)[:5, :5]
        # Define HSV ranges for different colors
        color_ranges = {
            'white': (np.array([0, 0, 200]), np.array([180, 55, 255])),
            'yellow': (np.array([22, 30, 170]), np.array([45, 150, 255]))
        }
           # 'green': (np.array([35, 50, 50]), np.array([85, 255, 255])),
           # 'blue': (np.array([100, 50, 50]), np.array([140, 255, 255])),
           # 'red2': (np.array([160, 50, 50]), np.array([180, 255, 255])),
           # 'orange': (np.array([10, 100, 20]), np.array([25, 255, 255])),
           # 'dark gray': (np.array([30, 30, 40]), np.array([60, 70, 60]))

        max_percentage = 0
        dominant_color = False

        for color, (lower, upper) in color_ranges.items():
            # Create a mask for the current color range
            mask = cv2.inRange(hsv, lower, upper)

            # Calculate the percentage of pixels in the current color range
            percentage = (np.sum(mask > 0) / mask.size) * 100

            # Update dominant color if current percentage is higher
            if percentage > max_percentage and percentage > threshold:
                max_percentage = percentage
                dominant_color = color
                #print(f"Found {color} in image.", end="")

            #if not dominant_color:
            #    print("White/Yellow not found in image.")

        return dominant_color

    def segment_board_into_squares(self, image):
            """
            Segments the given image of a Scrabble board into individual squares.

            Args:
                image (numpy.ndarray): The image of the Scrabble board.

            Returns:
                list: A list of numpy arrays, each representing an individual square on the board.
            """
            squares = []
            square_size = image.shape[0] // 15  # Assuming a standard 15x15 Scrabble board
            padding = 8 # spaces between tiles, do not change
            for i in range(15):
                for j in range(15):
                    top_left_y = i * square_size + padding
                    bottom_right_y = (i + 1) * square_size - padding
                    top_left_x = j * square_size + padding
                    bottom_right_x = (j + 1) * square_size - padding*2 - 2
                    square = image[top_left_y:bottom_right_y, top_left_x:bottom_right_x]
                    #cv2.imwrite('images/tmp/'+str(i)+'-'+str(j)+'.png', square)
                    #print(f"top_left_y: {top_left_y}, bottom_right_y: {bottom_right_y}, top_left_x: {top_left_x}, bottom_right_x: {bottom_right_x}")
                    squares.append(square)
            return squares

    def read_board(self, squares):
        """
        Reads the WordFeud board and returns the letters on the board as a string.

        Args:
            squares (list): A list of image squares representing the WordFeud board.
            static_tiles (list): A list of image tiles representing the static tiles on the board.

        Returns:
            str: A string containing the letters on the board.

        """
        board_letters = ""
        # check if square is a static tile
        count = 0
        for square in squares:
            row = count // 15
            column = count % 15
            
            dominant_color = self.classify_dominant_color(square, threshold=5)

            # Debug not working image: add condition here to write specific tile for not working tests:
            #if row == 10 and column == 14:
            #    cv2.imwrite('tests/squares/00ocr_'+str(row)+','+str(column)+'-UNK.png', square)

            if dominant_color == 'white' or dominant_color == 'yellow':
                letter = self.ocr_tile(square, save_image=False, comments="")

                self.update_square(row, column, letter)
                board_letters = board_letters + letter + " "
            count += 1
        return board_letters

    # create function to save board to file
    def save_board_file(self, file_path):
        with open(file_path, 'w') as file:
            for row in self.board:
                file.write(','.join(row) + '\n')
        print(f"Board saved to {file_path}.")

class Square:
    def __init__(self, letter=None, modifier="Normal"):
        self.letter = letter
        self.modifier = modifier
        self.visible = True

    def __str__(self):
        if not self.visible:
            return ""
        if not self.letter:
            return "_"
        else:
            return self.letter


class WordFeudBoard:
    def __init__(self):
        self.board = []
        # variables to encode best word on a given turn
        self.word_rack = []

        # populate self.board with 15 rows of 15 Squares() columns
        self.board = [[Square() for _ in range(15)] for _ in range(15)]


    def all_board_words(self, board):
        """
        Retrieves all the words present on the game board.

        Args:
            board (list): A 2D list representing the game board.

        Returns:
            list: A list of words found on the game board.
        """
        board_words = []
        placement = []
        board_words.extend(self.check_regular_board(board, placement))
        board_words.extend(self.check_transposed_board(board, placement))
        return board_words, placement

    def check_regular_board(self, board, placement):
        board_words = []
        for row in range(15):
            temp_word = ""
            for col in range(16):
                if col == 15:
                    letter = "" # padding so that end of word is reached
                else:
                    letter = board[row][col].letter

                if letter:
                    temp_word += letter
                    #print(f"H temp_word: {temp_word}")
                else:
                    if len(temp_word) > 1:
                        board_words.append(temp_word)
                        placement.append([(row, col-len(temp_word)), temp_word, "across"])
                    temp_word = ""
        return board_words

    def check_transposed_board(self, board, placement):
        board_words = []
        for col in range(15):
            temp_word = ""
            for row in range(16):
                if row == 15:
                    letter = "" # padding so that end of word is reached
                else:
                    letter = board[row][col].letter

                if letter:
                    temp_word += letter
                    #print(f"V temp_word: {temp_word}")
                else:
                    if len(temp_word) > 1:
                        placement.append([(row-len(temp_word), col), temp_word, "down"])
                        board_words.append(temp_word)
                    temp_word = ""
        return board_words



