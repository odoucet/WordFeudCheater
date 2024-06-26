var localResponse = {}

document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();

    var formData = new FormData(this);
    fetch('back.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('errorDiv').innerHTML = ''; // Clear previous error

        if (data.status == 'success') {
            // hide form-container
            document.getElementById('uploadForm').style.display = 'none';
            document.getElementById('progressBar').style.display = 'none';
            
            // show image
            document.getElementById('uploadedImage').src = data.imagePath;

            // show resultContainer
            document.getElementById('resultContainer').style.display = 'block';
            displayResult(data);
        } else if (data.token) {
            document.getElementById('resultContainer').style.display = 'block';
            document.getElementById('uploadedImage').src = data.imagePath;
            startProgressBar(data.token);
        } else {
            // write error to div errorDiv
            document.getElementById('errorDiv').innerHTML = 'upload error: '+data.error;
        }
    })
    .catch(error => document.getElementById('errorDiv').innerHTML = 'upload error caught:'+error);
});


function startProgressBar(token) {
    var progressBarFill = document.getElementById('progressBarFill');
    var width = 0;

    // one test is enough to launch process
    checkResult(token);

    var interval = setInterval(function() {
        if (width >= 10) {
            clearInterval(interval);
        } else {
            width++;
            progressBarFill.style.width = width*10 + '%';
            progressBarFill.textContent = width*10 + '%';
        }
    }, 1000);
}

function checkResult(token) {
    fetch('back.php?action=getresult&token=' + token)
    .then(response => {
        if (response.status === 200) {
            // hide progress bar
            document.getElementById('progressBar').style.display = 'none';
            return response.json();
        } else {
            document.getElementById('errorDiv').innerHTML = 'Processing failed';
            throw new Error('Processing failed: '+response.status);
        }
    })
    .then(data => {
        if (data) {
            // hide form-container
            document.getElementById('uploadForm').style.display = 'none';
            displayResult(data);
        }
    })
    .catch(error =>  document.getElementById('errorDiv').innerHTML = error);
}

function displayResult(result) {
    document.getElementById('rackInput').style.display = 'block';
    document.getElementById('sendToScrabulizer').style.display = 'block';

    var boardGrid = document.getElementById('boardGrid');
    boardGrid.innerHTML = ''; // Clear previous grid

    if (!result.board || !Array.isArray(result.board)) {
        document.getElementById('errorDiv').innerHTML = 'Error: board is not an array';
        return;
    }

    result.board.forEach(row => {
        row.forEach(cell => {
            var input = document.createElement('input');
            input.maxLength = 1;
            input.value = cell;
            // add id as x_y
            input.id = result.board.indexOf(row) + '_' + row.indexOf(cell);

            boardGrid.appendChild(input);
        });
    });
    document.getElementById('rackInput').value = result.rack.join('');

    localResponse = result;
}

function sendToScrabulizer() {
    url = 'https://www.scrabulizer.com/';

    // convert localResponse.board as a string with _ for empty cells
    scrabBoard = '';
    for (var i = 0; i < 15; i++) {
        for (var j = 0; j < 15; j++) {
            if (!document.getElementById(i+'_'+j)) {
                scrabBoard += '_';
            } else {
                value = document.getElementById(i+'_'+j).value.trim();
                if (value === '') {
                    scrabBoard += '_';
                } else if (value === '%') {
                    scrabBoard += '_';  // we cannot have % (wildcard) in the board
                } else {
                    scrabBoard += value;
                }
            }
        }
    }

    // replace space with _ in rack because we cannot have space in the rack
    rack = document.getElementById('rackInput').value.replace(/%/g, '_');

    args = {
        r: rack,
        de: 'wordfeud',
        d: 18, // 12=ODS6, 15=ODS7, 18=ODS8, 19=ODS9
        an: 'sig',
        rl: 7, // rack length
        b: scrabBoard,
    };

    // redirect
    window.location.href = url + '?' + serialize(args);
}


function serialize(obj) {
    var str = [];
    for(var p in obj)
        str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
    return str.join("&");
}

