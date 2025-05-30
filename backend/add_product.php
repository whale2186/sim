<?php
session_start();

// Include sidebar when not processing an AJAX request
if (!isset($_GET['action']) || ($_GET['action'] != 'fetch' && $_GET['action'] != 'update') || !isset($_GET['barcode'])) {
    include 'sidebar.php';
}

include 'db_connect.php';

// AJAX endpoint: fetch product details using the scanned barcode
if (isset($_GET['action']) && $_GET['action'] == 'fetch' && isset($_GET['barcode'])) {
    $barcode = trim($_GET['barcode']);
    
    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM products WHERE barcode_id = ?");
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $product = $result->fetch_assoc();
        echo json_encode(array("success" => true, "product" => $product));
    } else {
        echo json_encode(array("success" => false, "message" => "Product not found"));
    }
    exit();
}

// AJAX endpoint: update product quantity
if (isset($_GET['action']) && $_GET['action'] == 'update' && isset($_GET['barcode'])) {
    $barcode = trim($_GET['barcode']);
    // Default quantity to add is 1 if not provided
    $quantityToAdd = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 1;
    
    // Use prepared statement for the update query
    $stmt = $conn->prepare("UPDATE products SET quantity = quantity + ? WHERE barcode_id = ?");
    $stmt->bind_param("is", $quantityToAdd, $barcode);
    
    if ($stmt->execute()) {
        // Retrieve the updated product info using a prepared statement
        $stmt = $conn->prepare("SELECT * FROM products WHERE barcode_id = ?");
        $stmt->bind_param("s", $barcode);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        echo json_encode(array("success" => true, "product" => $product));
    } else {
        echo json_encode(array("success" => false, "message" => $conn->error));
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Product</title>
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
    #log {
      margin-top: 10px;
      padding: 10px;
      background: #f9f9f9;
      border: 1px solid #ddd;
      border-radius: 5px;
      min-height: 50px;
      max-width: 400px;
      word-wrap: break-word;
    }
    #product-info {
      margin-top: 20px;
      padding: 10px;
      background: #e9e9e9;
      border: 1px solid #ccc;
      border-radius: 5px;
      max-width: 400px;
    }
    .auto-increment {
      margin-top: 10px;
    }
    .hidden {
      display: none;
    }
    button {
      background: #1e1e2d;
      color: white;
      padding: 10px 15px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      margin-top: 10px;
    }
    input[type="number"] {
      padding: 8px;
      width: 100px;
      margin-right: 10px;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Add Product</h1>
    <!-- Connection Log -->
    <!-- Auto Increment Toggle -->
    <div class="auto-increment">
      <label>
        <input type="checkbox" id="autoIncrementToggle"> Auto Increment
      </label>
    </div>
    <!-- Display product details -->
    <div id="product-info" class="hidden"></div>
    <!-- Manual quantity input form (shown if auto increment is disabled) -->
    <div id="manual-input" class="hidden">
      <input type="number" id="manualQuantity" placeholder="Quantity" min="1" value="1">
      <button id="addQuantityBtn">Add Quantity</button>
    </div>
    <div id="log"></div>
  </div>

<script>
  let wsUri;
  let websocket;
  let open = false;
  let currentBarcode = "";

  function logMessage(s) {
    let logDiv = document.getElementById('log');
    logDiv.innerHTML += s + "<br />";
  }

  const TIMEOUT = 5000;

  function init() {
    let sIP = sessionStorage.getItem("scanner_ip");
    let sPort = sessionStorage.getItem("scanner_port");
    if (!sIP || !sPort) {
      logMessage("Scanner is not connected. Please connect the scanner first.");
      return;
    }
    wsUri = "ws://" + sIP + ":" + sPort + "/";
    logMessage("Connecting to scanner at " + wsUri + "...");
    websocket = new WebSocket(wsUri);
    websocket.onopen = onopen;
    websocket.onerror = onerror;
    websocket.onmessage = onmessage;
    websocket.onclose = onclose;
    setTimeout(onConnectionTimeout, TIMEOUT);

    // Load auto increment state from sessionStorage
    const autoIncrementToggle = document.getElementById('autoIncrementToggle');
    let autoIncrementState = sessionStorage.getItem("autoIncrementEnabled");
    if(autoIncrementState === "true") {
      autoIncrementToggle.checked = true;
    }
    autoIncrementToggle.addEventListener('change', function() {
      sessionStorage.setItem("autoIncrementEnabled", autoIncrementToggle.checked);
      logMessage("Auto Increment " + (autoIncrementToggle.checked ? "enabled" : "disabled"));
      toggleManualInput();
    });
  }

  function onConnectionTimeout() {
    if (!open) {
      logMessage("Scanner is unreachable. Please check the connection.");
      websocket.close();
    }
  }

  function onopen() {
    open = true;
    logMessage("Scanner connected. Ready to scan.");
  }

  function onerror(event) {
    logMessage("Scanner connection error.");
  }

  function onmessage(event) {
    let data = event.data;
    if (data == "[object Blob]") {
      let reader = new FileReader();
      reader.addEventListener("loadend", function(e) {
        processBarcode(e.target.result);
      });
      reader.readAsText(data);
    } else {
      processBarcode(data);
    }
  }

  function onclose() {
    logMessage("Scanner disconnected.");
    open = false;
  }

  // Process the scanned barcode
  function processBarcode(barcode) {
    currentBarcode = barcode;
    logMessage("Scanned Barcode: " + barcode);
    fetchProduct(barcode);
  }

  // AJAX request to fetch product details from the DB
  function fetchProduct(barcode) {
    fetch("add_product.php?action=fetch&barcode=" + encodeURIComponent(barcode))
      .then(response => response.json())
      .then(data => {
        if(data.success) {
          displayProduct(data.product);
          handleAutoIncrement(barcode);
        } else {
          logMessage("Product not found for barcode: " + barcode);
          document.getElementById('product-info').classList.add('hidden');
          document.getElementById('manual-input').classList.add('hidden');
        }
      })
      .catch(err => {
        logMessage("Error fetching product: " + err);
      });
  }

  // Display product details on the page
  function displayProduct(product) {
    let productDiv = document.getElementById('product-info');
    productDiv.innerHTML = "<strong>Product Name:</strong> " + product.name + "<br>" +
                           "<strong>Current Quantity:</strong> " + product.quantity + "<br>" +
                           "<strong>Price (₹):</strong> " + product.rupees + "<br>" +
                           "<strong>Barcode:</strong> " + product.barcode_id;
    productDiv.classList.remove('hidden');
  }

  // Handle the auto increment logic (if enabled, update quantity automatically)
  function handleAutoIncrement(barcode) {
    const autoIncrementEnabled = sessionStorage.getItem("autoIncrementEnabled") === "true";
    if(autoIncrementEnabled) {
      updateProductQuantity(barcode, 1);
      document.getElementById('manual-input').classList.add('hidden');
    } else {
      document.getElementById('manual-input').classList.remove('hidden');
    }
  }

  // Toggle manual input form based on auto increment state
  function toggleManualInput() {
    const autoIncrementEnabled = sessionStorage.getItem("autoIncrementEnabled") === "true";
    if(!autoIncrementEnabled && currentBarcode !== "") {
      document.getElementById('manual-input').classList.remove('hidden');
    } else {
      document.getElementById('manual-input').classList.add('hidden');
    }
  }

  // AJAX request to update product quantity in the DB
  function updateProductQuantity(barcode, quantity) {
    fetch("add_product.php?action=update&barcode=" + encodeURIComponent(barcode) + "&quantity=" + quantity)
      .then(response => response.json())
      .then(data => {
        if(data.success) {
          displayProduct(data.product);
          logMessage("Updated quantity by " + quantity + ".");
        } else {
          logMessage("Error updating product: " + data.message);
        }
      })
      .catch(err => {
        logMessage("Error updating product: " + err);
      });
  }

  document.addEventListener("DOMContentLoaded", function() {
    init();
    document.getElementById('addQuantityBtn').addEventListener('click', function() {
      let qty = parseInt(document.getElementById('manualQuantity').value);
      if(isNaN(qty) || qty < 1) {
        logMessage("Invalid quantity entered.");
        return;
      }
      updateProductQuantity(currentBarcode, qty);
    });
  });
</script>
</body>
</html>
