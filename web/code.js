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

    // check progress every second
    checkResult(token);

    var interval = setInterval(function() {
        if (width >= 10) {
            clearInterval(interval);
            checkResult(token);
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
        } else if (response.status === 204) {
            setTimeout(() => checkResult(token), 1000); // Retry after 1 second
        } else {
            document.getElementById('errorDiv').innerHTML = 'Processing failed';
            throw new Error('Processing failed');
        }
    })
    .then(data => {
        if (data) {
            // hide form-container
            document.getElementById('uploadForm').style.display = 'none';
            displayResult(data.result);
        }
    })
    .catch(error =>  document.getElementById('errorDiv').innerHTML = error);
}

function displayResult(result) {
    var boardGrid = document.getElementById('boardGrid');
    boardGrid.innerHTML = ''; // Clear previous grid
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
    

}
