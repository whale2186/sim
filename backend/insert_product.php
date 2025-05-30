<?php
session_start();
include 'sidebar.php'; // Include Sidebar
include 'db_connect.php'; // Database Connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $quantity = $_POST['quantity'];
    $rupees = $_POST['rupees'];
    $barcode_id = $_POST['barcode_id'];

    // Check if the barcode ID already exists
    $check_query = "SELECT * FROM products WHERE barcode_id = '$barcode_id'";
    $result = $conn->query($check_query);

    if ($result->num_rows > 0) {
        $message = "Error: Product with this barcode already exists!";
    } else {
        // Insert product into database
        $query = "INSERT INTO products (name, quantity, rupees, barcode_id) VALUES ('$name', '$quantity', '$rupees', '$barcode_id')";
        if ($conn->query($query) === TRUE) {
            $message = "Product inserted successfully!";
        } else {
            $message = "Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insert Product</title>
    <style>
        .container {
            margin-left: 250px;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        h1 {
            font-size: 28px;
            margin-bottom: 20px;
        }
        form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            max-width: 400px;
        }
        label {
            font-weight: bold;
        }
        input[type="text"], input[type="number"] {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        button {
            background: #1e1e2d;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .message {
            margin-top: 10px;
            font-weight: bold;
            color: <?php echo isset($message) && strpos($message, 'Error') !== false ? 'red' : 'green'; ?>;
        }
        #log {
            margin-top: 20px;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 400px;
            height: 150px;
            overflow-y: auto;
            overflow-x: hidden;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Insert Product</h1>
        
        <?php if (isset($message)) echo "<div class='message'>$message</div>"; ?>
        <form action="" method="POST">
            <label for="name">Product Name:</label>
            <input type="text" id="name" name="name" required>

            <label for="quantity">Quantity:</label>
            <input type="number" id="quantity" name="quantity" required>

            <label for="rupees">Price (₹):</label>
            <input type="number" id="rupees" name="rupees" required>

            <label for="barcode_id">Barcode ID:</label>
            <input type="text" id="barcode_id" name="barcode_id" required readonly>

            <button type="submit">Add Product</button>
        </form>
        <div id="log"></div>
    </div>

<script>
function log(s) {
    var logDiv = document.getElementById('log');
    logDiv.innerHTML += s + "<br />";
    logDiv.scrollTop = logDiv.scrollHeight; // Auto-scroll to the latest log
}

var TIMEOUT = 5000;
var wsUri;
var websocket;
var open = false;

function init() {
    var sIP = sessionStorage.getItem("scanner_ip");
    var sPort = sessionStorage.getItem("scanner_port");

    if (!sIP || !sPort) {
        log("Scanner is not connected. Please connect the scanner first.");
        return;
    }

    wsUri = "ws://" + sIP + ":" + sPort + "/";
    log("Connecting to scanner at " + wsUri + "...");

    websocket = new WebSocket(wsUri);
    websocket.onopen = onopen;
    websocket.onerror = onerror;
    websocket.onmessage = onmessage;
    websocket.onclose = onclose;

    setTimeout(onConnectionTimeout, TIMEOUT);
}

function onConnectionTimeout() {
    if (!open) {
        log("Scanner is unreachable. Please check the connection.");
        websocket.close();
    }
}

function onopen() {
    open = true;
    log("Scanner connected. Ready to scan.");
}

function onerror(event) {
    log("Scanner connection error.");
}

function onmessage(event) {
    var data = event.data;
    if (data == "[object Blob]") {
        var reader = new FileReader();
        reader.addEventListener("loadend", function(e) {
            validateBarcode(e.target.result);
        });
        reader.readAsText(data);
    } else {
        validateBarcode(data);
    }
}

function validateBarcode(barcode) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "check_barcode.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            if (xhr.responseText.trim() === "exists") {
                log("Error: This barcode already exists in the system.");
            } else {
                document.getElementById("barcode_id").value = barcode;
                log("Scanned Barcode: " + barcode);
            }
        }
    };
    xhr.send("barcode_id=" + barcode);
}

function onclose() {
    log("Scanner disconnected.");
    open = false;
}

window.addEventListener("load", init);
</script>
</body>
</html>
