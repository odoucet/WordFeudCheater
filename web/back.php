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
    $imagePath = __DIR__ . '/' . $uniqueID . '.png.large';

    if (file_exists($resultFile)) {
        // if file empty, run python script
        $json = @json_decode(file_get_contents($resultFile), true);

        if (filesize($resultFile) == 0 || !isset($resultFile['status']) || $resultFile['status'] == 'created') {
            $command = escapeshellcmd("python3 ".__DIR__."/../wordfeud2json.py --language ".$json['language']." --image $imagePath");
            $output = shell_exec($command);

            $result = @json_decode($output, true);
            if (isset($result['rack']) && isset($result['board'])) {
                // write result to file
                $output = json_encode($result);
                file_put_contents($resultFile, $output);
                echo $output;
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Processing failed.', 'debugResult' => $result]);
            }
        } else {
            if (!isset($json['progress'])) {
                http_response_code(204);
                exit;
            } else {
                // finished! return result
                http_response_code(200);
                echo file_get_contents($resultFile);
            }
        }

    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Token not found.']);
    }
} elseif (isset($_GET['action']) && $_GET['action'] == 'scrabulizer' && isset($_GET['token'])) {
    // sanitize token (a-z0-9_)
    $uniqueID = preg_replace('/[^a-z0-9_]/', '', $_GET['token']);
    $resultFile = __DIR__ . '/token_' . $uniqueID . '.json';

    if (!file_exists($resultFile)) {
        http_response_code(404);
        echo json_encode(['error' => 'Token not found.']);
    }

    // read data
    $json = @json_decode(file_get_contents($resultFile), true);

    if (!isset($json['status']) || $json['status'] != 'success') {
        http_response_code(400);
        echo json_encode(['error' => 'Token not ready.']);
    }

    // call scrabulizer with curl
    $url = 'https://www.scrabulizer.com/solver/results';
    $method = 'POST';

    // missing: x-js-version

    // letters and score. 
    // TODO: adapt between languages
    $args='b_0_0=3L&b_1_0=&b_2_0=&b_3_0=&b_4_0=3W&b_5_0=&b_6_0=&b_7_0=2L&b_8_0=&b_9_0=&b_10_0=3W&b_11_0=&b_12_0=&b_13_0=&'.
        'b_14_0=3L&b_0_1=&b_1_1=2L&b_2_1=&b_3_1=&b_4_1=&b_5_1=3L&b_6_1=&b_7_1=&b_8_1=&b_9_1=3L&b_10_1=&b_11_1=&b_12_1=&b_13_1=2L'.
        '&b_14_1=&b_0_2=&b_1_2=&b_2_2=2W&b_3_2=&b_4_2=&b_5_2=&b_6_2=2L&b_7_2=&b_8_2=2L&b_9_2=&b_10_2=&b_11_2=&b_12_2=2W&b_13_2='.
        '&b_14_2=&b_0_3=&b_1_3=&b_2_3=&b_3_3=3L&b_4_3=&b_5_3=&b_6_3=&b_7_3=2W&b_8_3=&b_9_3=&b_10_3=&b_11_3=3L&b_12_3=&b_13_3=&b_14_3='.
        '&b_0_4=3W&b_1_4=&b_2_4=&b_3_4=&b_4_4=2W&b_5_4=&b_6_4=2L&b_7_4=&b_8_4=2L&b_9_4=&b_10_4=2W&b_11_4=&b_12_4=&b_13_4=&b_14_4=3W'.
        '&b_0_5=&b_1_5=3L&b_2_5=&b_3_5=&b_4_5=&b_5_5=3L&b_6_5=&b_7_5=&b_8_5=&b_9_5=3L&b_10_5=&b_11_5=&b_12_5=&b_13_5=3L&b_14_5=&b_0_6='.
        '&b_1_6=&b_2_6=2L&b_3_6=&b_4_6=2L&b_5_6=&b_6_6=&b_7_6=&b_8_6=&b_9_6=&b_10_6=2L&b_11_6=&b_12_6=2L&b_13_6=&b_14_6=&b_0_7=2L&b_1_7='.
        '&b_2_7=&b_3_7=2W&b_4_7=&b_5_7=&b_6_7=&b_7_7=&b_8_7=&b_9_7=&b_10_7=&b_11_7=2W&b_12_7=&b_13_7=&b_14_7=2L&b_0_8=&b_1_8=&b_2_8=2L'.
        '&b_3_8=&b_4_8=2L&b_5_8=&b_6_8=&b_7_8=&b_8_8=&b_9_8=&b_10_8=2L&b_11_8=&b_12_8=2L&b_13_8=&b_14_8=&b_0_9=&b_1_9=3L&b_2_9=&b_3_9=&b_4_9='.
        '&b_5_9=3L&b_6_9=&b_7_9=&b_8_9=&b_9_9=3L&b_10_9=&b_11_9=&b_12_9=&b_13_9=3L&b_14_9=&b_0_10=3W&b_1_10=&b_2_10=&b_3_10=&b_4_10=2W&b_5_10='.
        '&b_6_10=2L&b_7_10=&b_8_10=2L&b_9_10=&b_10_10=2W&b_11_10=&b_12_10=&b_13_10=&b_14_10=3W&b_0_11=&b_1_11=&b_2_11=&b_3_11=3L&b_4_11=&b_5_11='.
        '&b_6_11=&b_7_11=2W&b_8_11=&b_9_11=&b_10_11=&b_11_11=3L&b_12_11=&b_13_11=&b_14_11=&b_0_12=&b_1_12=&b_2_12=2W&b_3_12=&b_4_12=&b_5_12='.
        '&b_6_12=2L&b_7_12=&b_8_12=2L&b_9_12=&b_10_12=&b_11_12=&b_12_12=2W&b_13_12=&b_14_12=&b_0_13=&b_1_13=2L&b_2_13=&b_3_13=&b_4_13=&b_5_13=3L'.
        '&b_6_13=&b_7_13=&b_8_13=&b_9_13=3L&b_10_13=&b_11_13=&b_12_13=&b_13_13=2L&b_14_13=&b_0_14=3L&b_1_14=&b_2_14=&b_3_14=&b_4_14=3W&b_5_14='.
        '&b_6_14=&b_7_14=2L&b_8_14=&b_9_14=&b_10_14=3W&b_11_14=&b_12_14=&b_13_14=&b_14_14=3L&tcA=10&tsA=1&tcH=2&tsH=4&tcO=6&tsO=1&tcV=2&tsV=5'.
        '&tcB=2&tsB=3&tcI=9&tsI=1&tcP=2&tsP=3&tcW=1&tsW=10&tcC=2&tsC=3&tcJ=1&tsJ=8&tcQ=1&tsQ=8&tcX=1&tsX=10&tcD=3&tsD=2&tcK=1&tsK=10&tcR=6'.
        '&tsR=1&tcY=1&tsY=10&tcE=14&tsE=1&tcL=5&tsL=2&tcS=6&tsS=1&tcZ=1&tsZ=10&tcF=2&tsF=4&tcM=3&tsM=2&tcT=6&tsT=1&tc_=2&ts_=0&tcG=3&tsG=2&tcN=6&tsN=1&tcU=6&tsU=1';

    $payload = [
        'rackLength' => 7,
        'boardHeight' => 15,
        'boardWidth' => 15,
        'bingo1' => 0,
        'bingo2' => 0,
        'bingo3' => 0,
        'bingo4' => 0,
        'bingo5' => 0,
        'bingo6' => 0,
        'bingo7' => 40,
        'bingo8' => 50,
        'rack' => $json['rack'],
        'dictionary' => 21, // French ODS9
        'opponent_count' => 1,
        'design' => 'wordfeud',
        'sort_by' => 0, // score
    ];

    // loop over result.board on x/y
    for ($x = 0; $x < 15; $x++) {
        for ($y = 0; $y < 15; $y++) {
            $payload['s_'.$y.'_'.$x] = $json['board'][$x][$y];
        }
    }

    // parse "args" and add it to payload:
    $args = explode('&', $args);
    foreach ($args as $arg) {
        $arg = explode('=', $arg);
        $payload[$arg[0]] = $arg[1];
    }

    // make a first request to scrabulizer homepage and grab csrf-token:
    $ch = curl_init('https://www.scrabulizer.com/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3');
    $response = curl_exec($ch);
    // extract <meta name="csrf-token" content="zAD4YkLmZ5b6ebacTS+9rQy4i7ogt+tV7JdeIqq5lGdLqQz33nCKF+/Grs1jPQupcoPF/tdkPYdzYpDZb87ZKQ==" />
    preg_match('/<meta name="csrf-token" content="([^"]+)"/', $response, $matches);
    $csrfToken = $matches[1];

    if (!$csrfToken) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get CSRF token.']);
        exit;
    }

    $payload['x-csrf-token'] = $csrfToken;
    $payload['x-js-version'] = 4;


    // send with curl
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    // add application/json, text/javascript
    /*curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'authority: www.scrabulizer.com',
        'accept: application/json',
        'accept-language: fr,en-US;q=0.9,en;q=0.8,eu;q=0.7',
        'cache-control: no-cache',
        'content-type: application/x-www-form-urlencoded; charset=UTF-8',
        'cookie: https=1; _scrabulizer_session=xxxxxxxxx%3D--0bf44a79ce5bc529582be91bcb7bf455db4b739b',
        'origin: https://www.scrabulizer.com',
        'pragma: no-cache',
        'referer: https://www.scrabulizer.com/',
        'sec-ch-ua: "Google Chrome";v="117", "Not;A=Brand";v="8", "Chromium";v="117"',
        'sec-ch-ua-mobile: ?1',
        'sec-ch-ua-platform: "Android"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/12'
    ]);*/

    // fake user agent
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3');
    $response = curl_exec($ch);

    // check response
    if ($response === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Curl error: ' . curl_error($ch)]);
    } else {
        http_response_code(200);
        echo $response;
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
    $newWidth = 120;
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

/**
 * curl 'https://www.scrabulizer.com/solver/results' \
  -H 'authority: www.scrabulizer.com' \
  -H 'accept: application/json, text/javascript, ; q=0.01' \
  -H 'accept-language: fr,en-US;q=0.9,en;q=0.8,eu;q=0.7' \
  -H 'cache-control: no-cache' \
  -H 'content-type: application/x-www-form-urlencoded; charset=UTF-8' \
  -H 'cookie: https=1; _scrabulizer_session=xxxxxxxxx%3D--0bf44a79ce5bc529582be91bcb7bf455db4b739b' \
  -H 'origin: https://www.scrabulizer.com' \
  -H 'pragma: no-cache' \
  -H 'referer: https://www.scrabulizer.com/' \
  -H 'sec-ch-ua: "Google Chrome";v="117", "Not;A=Brand";v="8", "Chromium";v="117"' \
  -H 'sec-ch-ua-mobile: ?1' \
  -H 'sec-ch-ua-platform: "Android"' \
  -H 'sec-fetch-dest: empty' \
  -H 'sec-fetch-mode: cors' \
  -H 'sec-fetch-site: same-origin' \
  -H 'user-agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Mobile Safari/537.36' \
  -H 'x-csrf-token: VVx3M3UZMW8S96J60spUQ80KphczeWtmCTVmrNrHrxrS9YOm6Y/c7gdIuiv82OJHszHoU8SqvbSWwKhXH7DiVA==' \
  -H 'x-js-version: 4' \
  -H 'x-requested-with: XMLHttpRequest' \
  --data-raw 's_0_0=&s_1_0=&s_2_0=&s_3_0=&s_4_0=&s_5_0=&s_6_0=&s_7_0=&s_8_0=&s_9_0=&s_10_0=&s_11_0=&s_12_0=&s_13_0=&s_14_0=&s_0_1=&s_1_1=&s_2_1=&s_3_1=&s_4_1=&s_5_1=&s_6_1=&s_7_1=&s_8_1=&s_9_1=&s_10_1=&s_11_1=&s_12_1=&s_13_1=&s_14_1=&s_0_2=&s_1_2=&s_2_2=&s_3_2=&s_4_2=&s_5_2=&s_6_2=&s_7_2=C&s_8_2=H&s_9_2=E&s_10_2=N&s_11_2=U&s_12_2=E&s_13_2=&s_14_2=&s_0_3=&s_1_3=&s_2_3=&s_3_3=&s_4_3=&s_5_3=&s_6_3=&s_7_3=O&s_8_3=&s_9_3=&s_10_3=&s_11_3=&s_12_3=&s_13_3=&s_14_3=&s_0_4=&s_1_4=&s_2_4=&s_3_4=&s_4_4=&s_5_4=&s_6_4=&s_7_4=U&s_8_4=&s_9_4=&s_10_4=&s_11_4=&s_12_4=&s_13_4=&s_14_4=&s_0_5=&s_1_5=&s_2_5=&s_3_5=&s_4_5=&s_5_5=&s_6_5=&s_7_5=R&s_8_5=&s_9_5=&s_10_5=&s_11_5=&s_12_5=&s_13_5=&s_14_5=&s_0_6=&s_1_6=&s_2_6=&s_3_6=&s_4_6=&s_5_6=&s_6_6=&s_7_6=E&s_8_6=&s_9_6=&s_10_6=&s_11_6=&s_12_6=&s_13_6=&s_14_6=&s_0_7=&s_1_7=&s_2_7=&s_3_7=&s_4_7=&s_5_7=&s_6_7=&s_7_7=Z&s_8_7=O&s_9_7=U&s_10_7=K&s_11_7=A&s_12_7=S&s_13_7=&s_14_7=&s_0_8=&s_1_8=&s_2_8=&s_3_8=&s_4_8=&s_5_8=&s_6_8=&s_7_8=&s_8_8=&s_9_8=&s_10_8=A&s_11_8=&s_12_8=&s_13_8=&s_14_8=&s_0_9=&s_1_9=&s_2_9=&s_3_9=&s_4_9=&s_5_9=&s_6_9=&s_7_9=&s_8_9=&s_9_9=&s_10_9=W&s_11_9=&s_12_9=&s_13_9=&s_14_9=&s_0_10=&s_1_10=&s_2_10=&s_3_10=&s_4_10=&s_5_10=&s_6_10=&s_7_10=&s_8_10=&s_9_10=&s_10_10=A&s_11_10=&s_12_10=&s_13_10=&s_14_10=&s_0_11=&s_1_11=&s_2_11=&s_3_11=&s_4_11=&s_5_11=&s_6_11=&s_7_11=&s_8_11=&s_9_11=&s_10_11=&s_11_11=&s_12_11=&s_13_11=&s_14_11=&s_0_12=&s_1_12=&s_2_12=&s_3_12=&s_4_12=&s_5_12=&s_6_12=&s_7_12=&s_8_12=&s_9_12=&s_10_12=&s_11_12=&s_12_12=&s_13_12=&s_14_12=&s_0_13=&s_1_13=&s_2_13=&s_3_13=&s_4_13=&s_5_13=&s_6_13=&s_7_13=&s_8_13=&s_9_13=&s_10_13=&s_11_13=&s_12_13=&s_13_13=&s_14_13=&s_0_14=&s_1_14=&s_2_14=&s_3_14=&s_4_14=&s_5_14=&s_6_14=&s_7_14=&s_8_14=&s_9_14=&s_10_14=&s_11_14=&s_12_14=&s_13_14=&s_14_14=&rack=GGORTIE&dictionary=4&opponent_count=1&design=standard&sort_by=0&boardWidth=15&boardHeight=15&rackLength=7' \
  --compressed
 */