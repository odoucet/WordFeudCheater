WordFeudCheater
===============
Forked from https://github.com/Cars-10/WordFeudCheater that was based on [astralcai](https://github.com/astralcai/scrabbler/tree/master).

[WordFeud](https://wordfeud.com/) is a scrabble game for your mobile. My Fiance has been killing me in almost every game.

_What's a hacker to do but write some code that can make it a more even fight!_

WordFeudCheater will propose the next move in a wordfeud.

It does this using OCR to identify the tiles placed on the board and in the rack.

## Dictionary build
Building a dictionary requires lots of memory, that's why they are provided in the resources/dictionaries folder.
If you modify a dictionary, remove the .p file and run the script again.

## Requirements
* tesseract

On Rocky9:
```bash
sudo dnf install  https://dl.rockylinux.org/pub/rocky/9/AppStream/x86_64/os/Packages/t/tesseract-4.1.1-7.el9.x86_64.rpm https://dl.rockylinux.org/pub/rocky/9/AppStream/x86_64/os/Packages/l/leptonica-1.80.0-4.el9.1.x86_64.rpm https://dl.rockylinux.org/pub/rocky/9/AppStream/x86_64/os/Packages/t/tesseract-langpack-eng-4.1.0-3.el9.noarch.rpm         https://dl.rockylinux.org/pub/rocky/9/AppStream/x86_64/os/Packages/t/tesseract-tessdata-doc-4.1.0-3.el9.noarch.rpm
```

## What's new in this fork?
[*] pip requirements for easy install
[*] web interface to post grid to Scrabulizer
[*] handle other languages than english
[*] handle multiple screen resolution (tested on 2 devices: Iphone 13 and Pixel 7 )
[*] unit tests to improve ocr and so

## TODO
* move build_dictionary in a separate script
* unit tests ^^