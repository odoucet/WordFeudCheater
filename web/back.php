<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['image']) && isset($_POST['language'])) {
        $image = $_FILES['image'];
        $language = $_POST['language'];

        // Validate image type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($image['type'], $allowedTypes)) {
            // Save the image with a unique ID
            $uniqueID = 'img_'.substr(md5(file_get_contents($image['tmp_name'])), 0, 12);

            
            $imagePath = __DIR__ . '/' . $uniqueID . '.png';
            $imagePathTmp = $imagePath . '.large';
            $resultFile = __DIR__ . '/token_' . $uniqueID . '.json';

            // check if file was already uploaded previously
            if (file_exists($resultFile)) {
                $json = @json_decode(file_get_contents($resultFile), true);
                if (!isset($json['status']) || $json['status'] == 'created') {
                    http_response_code(200);
                    echo json_encode(['token' => $uniqueID, 'imagePath' => basename($imagePath)]);
                } else {
                    http_response_code(200);
                    echo json_encode($json);
                    
                }
                // stop here!
                exit;
            }

            if (move_uploaded_file($image['tmp_name'], $imagePathTmp)) {
                // return the token
                touch($resultFile);

                // resize image with gd and 300px width
                resizeAndConvert($imagePathTmp, $imagePath);

                file_put_contents($resultFile, json_encode(['status' => 'created', 'language' => $language]));

                echo json_encode(['token' => $uniqueID, 'imagePath' => basename($imagePath)]);
                
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save image.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid image type.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameters.']);
    }
} elseif (isset($_GET['action']) && $_GET['action'] == 'getresult' && isset($_GET['token'])) {
    // sanitize token (a-z0-9_)
    $uniqueID = preg_replace('/[^a-z0-9_]/', '', $_GET['token']);

    // check if is already running
    $resultFile = __DIR__ . '/token_' . $uniqueID . '.json';
    $imagePath = __DIR__ . '/' . $uniqueID . '.png';
    $imagePathTmp = $imagePath . '.large';

    if (file_exists($resultFile)) {
        // if file empty, run python script
        $json = @json_decode(file_get_contents($resultFile), true);

        if (filesize($resultFile) == 0 || !isset($resultFile['status']) || $resultFile['status'] == 'created') {
            $cmd = "python3 ".__DIR__."/../wordfeud2json.py --language ".$json['language']." --image $imagePathTmp";
            file_put_contents(__DIR__.'/../log.txt', date('[Ymd His]').' run '.$cmd."\n", FILE_APPEND);
            $command = escapeshellcmd($cmd);
            $output = shell_exec($command);

            $result = @json_decode($output, true);
            if (isset($result['rack']) && isset($result['board'])) {
                // write result to file
                // add token and imagePath to json
                $result['token'] = $uniqueID;
                $result['imagePath'] = basename($imagePath);
                $output = json_encode($result);
                file_put_contents($resultFile, $output);
                echo $output;
                exit;
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Processing failed.', 'debugResult' => $result, 'output' => $output]);
                exit;
            }
        } else {
            if (!isset($json['progress'])) {
                http_response_code(204);
                exit;
            } else {
                // finished! return result
                http_response_code(200);
                echo file_get_contents($resultFile);
                exit;
            }
        }

    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Token not found.']);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'URI not found.']);
}

function resizeAndConvert($imagePath, $outputPath) {
    // Load the original image
    $image = imagecreatefromstring(file_get_contents($imagePath));
    if (!$image) {
        die('Failed to load image');
    }

    // Get the original dimensions
    $originalWidth = imagesx($image);
    $originalHeight = imagesy($image);

    // Calculate the new dimensions
    $newWidth = 200;
    $newHeight = ($originalHeight / $originalWidth) * $newWidth;

    // Create a new true color image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // Copy and resize the original image into the new image
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

    // Convert the true color image to a palette image with 16 colors
    imagetruecolortopalette($newImage, false, 16);

    // Save the new image
    imagepng($newImage, $outputPath);

    // Free up memory
    imagedestroy($image);
    imagedestroy($newImage);
}
