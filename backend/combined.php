
add_product.php

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

add_user.php

<?php
include 'auth.php';
include 'db_connect.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        $sql = "INSERT INTO users (username, password) VALUES ('$username', '$password')";
        if ($conn->query($sql) === TRUE) {
            $message = "User added successfully!";
        } else {
            $message = "Error: " . $conn->error;
        }
    } else {
        $message = "All fields are required!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add User</title>
    <style>
        <?php include 'sidebar.css'; ?>
        .container {
            width: 300px;
            margin: 100px auto;
            padding: 20px;
            background: #1e1e2d;
            color: white;
            border-radius: 8px;
            text-align: center;
        }
        input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: none;
            border-radius: 4px;
            background: #282842;
            color: white;
        }
        button {
            width: 100%;
            padding: 10px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: 0.3s;
        }
        button:hover {
            background: #45a049;
        }
        .message {
            margin-top: 10px;
            color: #FFD700;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="content">
        <div class="container">
            <h2>Add User</h2>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Add User</button>
            </form>
            <p class="message"><?php echo $message; ?></p>
        </div>
    </div>
</body>
</html>

auth.php

<?php
session_start();
include 'db_connect.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
?>

change_password.php

<?php
include 'auth.php';
include 'db_connect.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_SESSION['user'];
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];

    $result = $conn->query("SELECT * FROM users WHERE username='$username' AND password='$old_pass'");

    if ($result->num_rows > 0) {
        $conn->query("UPDATE users SET password='$new_pass' WHERE username='$username'");
        $message = "Password changed successfully!";
    } else {
        $message = "Old password is incorrect.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Change Password</title>
    <style>
        <?php include 'sidebar.css'; ?>
        .container {
            width: 300px;
            margin: 100px auto;
            padding: 20px;
            background: #1e1e2d;
            color: white;
            border-radius: 8px;
            text-align: center;
        }
        input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: none;
            border-radius: 4px;
            background: #282842;
            color: white;
        }
        button {
            width: 100%;
            padding: 10px;
            background: #ff4c4c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: 0.3s;
        }
        button:hover {
            background: #ff6666;
        }
        .message {
            margin-top: 10px;
            color: #ff4c4c;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="content">
        <div class="container">
            <h2>Change Password</h2>
            <form method="POST">
                <input type="password" name="old_password" placeholder="Old Password" required>
                <input type="password" name="new_password" placeholder="New Password" required>
                <button type="submit">Change Password</button>
            </form>
            <p class="message"><?php echo $message; ?></p>
        </div>
    </div>
</body>
</html>

check_barcode.php

<?php
include 'db_connect.php';

if (isset($_POST['barcode_id'])) {
    $barcode_id = $_POST['barcode_id'];
    $query = "SELECT * FROM products WHERE barcode_id = '$barcode_id'";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        echo "exists";
    } else {
        echo "available";
    }
}
?>

connect_scanner.php

<?php
session_start();
include 'sidebar.php'; // Sidebar for navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Connect Scanner</title>
  <style>
    .container {
      margin-left: 250px;
      padding: 20px;
      font-family: Arial, sans-serif;
    }
    .form-section {
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0px 0px 10px rgba(0,0,0,0.1);
      max-width: 500px;
      margin-bottom: 20px;
    }
    label {
      font-weight: bold;
      display: block;
      margin-top: 10px;
    }
    input[type="text"] {
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
      margin-top: 10px;
    }
    #log {
      margin-top: 20px;
      padding: 10px;
      background: #f9f9f9;
      border: 1px solid #ddd;
      border-radius: 5px;
      min-height: 100px;
      max-width: 500px;
      word-wrap: break-word;
    }
  </style>
</head>
<body>
<div class="container">
  <h1>Connect Scanner</h1>
  <div class="form-section">
    <p>Enter the IP address and Port of the Android device running the Barcode Scanner:</p>
    <label for="ip">IP:</label>
    <input type="text" id="ip" placeholder="172.16.100.24">
    <label for="port">Port:</label>
    <input type="text" id="port" placeholder="9999">
    <button id="connect">Connect</button>
  </div>
  <div id="log"></div>
</div>

<script>
// Log function to display messages on screen
function log(s) {
  var logDiv = document.getElementById('log');
  logDiv.innerHTML += s + "<br />";
}

var TIMEOUT = 5000; // 5 seconds timeout
var wsUri;
var websocket;
var open = false;

function init() {
  // Load saved IP and Port from session storage
  if (sessionStorage.getItem("scanner_ip")) {
    document.getElementById("ip").value = sessionStorage.getItem("scanner_ip");
  }
  if (sessionStorage.getItem("scanner_port")) {
    document.getElementById("port").value = sessionStorage.getItem("scanner_port");
  }

  document.getElementById('connect').addEventListener("click", connect);
}

function connect() {
  var sIP = document.getElementById('ip').value;
  var sPort = document.getElementById('port').value;
  
  if (sIP === "" || sPort === "") {
    log("Please enter IP address and port number.");
  } else {
    wsUri = "ws://" + sIP + ":" + sPort + "/";
    log("Connecting to " + wsUri + "...");
    websocket = new WebSocket(wsUri);
    websocket.onopen = onopen;
    websocket.onerror = onerror;
    websocket.onclose = onclose;
    
    // Avoid endless connection retries
    window.setTimeout(onConnectionTimeout, TIMEOUT);
  }
}

function onConnectionTimeout() {
  if (!open) {
    log("Connection to " + wsUri + " timed out.");
    websocket.close();
  }
}

function onopen() {
  open = true;
  log("Connection to " + wsUri + " opened successfully!");

  // Save IP and Port to session storage
  var sIP = document.getElementById('ip').value;
  var sPort = document.getElementById('port').value;
  sessionStorage.setItem("scanner_ip", sIP);
  sessionStorage.setItem("scanner_port", sPort);
}

function onerror(event) {
  log("Connection error. Please check the IP and Port.");
  console.error("WebSocket error:", event);
}

function onclose(event) {
  log("Connection closed.");
  open = false;
}

window.addEventListener("load", init, false);
</script>
</body>
</html>

db_connect.php

<?php
$servername = "localhost";
$username = "root"; // Change if needed
$password = ""; // Change if needed
$database = "inventory_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

delete_product.php

<?php
include 'db_connect.php'; // Database Connection

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $query = "DELETE FROM products WHERE id = $id";
    
    if ($conn->query($query)) {
        echo "<script>alert('Product deleted successfully.'); window.location='edit_product.php';</script>";
    } else {
        echo "<script>alert('Error deleting product.'); window.location='edit_product.php';</script>";
    }
} else {
    header("Location: edit_product.php");
}
?>

edit_product.php

<?php
session_start();
include 'sidebar.php'; // Include Sidebar
include 'db_connect.php'; // Database Connection

// Handle search and pagination
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : "";
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$queryBase = "SELECT * FROM products";
$where = ($search !== "") ? " WHERE name LIKE '%$search%' OR barcode_id LIKE '%$search%'" : "";
$queryTotal = $queryBase . $where;
$totalResult = $conn->query($queryTotal);
$totalEntries = $totalResult ? $totalResult->num_rows : 0;
$totalPages = ($totalEntries > 0) ? ceil($totalEntries / $limit) : 1;

$query = $queryBase . $where . " LIMIT $limit OFFSET $offset";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Product</title>
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
      form.search-form {
          background: white;
          padding: 15px;
          border-radius: 8px;
          box-shadow: 0px 0px 10px rgba(0,0,0,0.1);
          max-width: 600px;
          margin-bottom: 20px;
          display: flex;
          gap: 10px;
          align-items: center;
      }
      form.search-form input[type="text"] {
          flex-grow: 1;
          padding: 10px;
          border: 1px solid #ddd;
          border-radius: 5px;
      }
      form.search-form button {
          background: #1e1e2d;
          color: white;
          padding: 10px 15px;
          border: none;
          border-radius: 5px;
          cursor: pointer;
      }
      table {
          width: 100%;
          max-width: 800px;
          border-collapse: collapse;
          margin-bottom: 20px;
      }
      table, th, td {
          border: 1px solid #ddd;
      }
      th, td {
          padding: 10px;
          text-align: left;
      }
      th {
          background: #f4f4f4;
      }
      .pagination a {
          padding: 8px 12px;
          margin: 0 2px;
          background: #1e1e2d;
          color: #fff;
          text-decoration: none;
          border-radius: 3px;
      }
      .pagination a.active {
          background: #4a4a6a;
      }
      .action-links a {
          margin-right: 10px;
          text-decoration: none;
          padding: 5px 10px;
          border-radius: 5px;
          color: white;
      }
      .edit-link {
          background: #3498db;
      }
      .delete-link {
          background: #e74c3c;
      }
      /* Barcode scan log styling */
      #log {
          margin-top: 20px;
          padding: 10px;
          background: #f9f9f9;
          border: 1px solid #ddd;
          border-radius: 5px;
          max-width: 800px;
          height: 150px;
          overflow-y: auto;
          white-space: pre-wrap;
      }
  </style>
</head>
<body>
  <div class="container">
      <h1>Edit Product</h1>
      
      <!-- Search Form -->
      <form class="search-form" action="edit_product.php" method="GET">
          <input type="text" id="search" name="search" placeholder="Search by product name or barcode" value="<?php echo htmlspecialchars($search); ?>" required>
          <button type="submit">Search</button>
      </form>
      
      <!-- Products Table -->
      <?php if ($result && $result->num_rows > 0): ?>
      <table>
          <thead>
              <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Quantity</th>
                  <th>Price (₹)</th>
                  <th>Barcode</th>
                  <th>Action</th>
              </tr>
          </thead>
          <tbody>
              <?php while($row = $result->fetch_assoc()): ?>
              <tr>
                  <td><?php echo $row['id']; ?></td>
                  <td><?php echo htmlspecialchars($row['name']); ?></td>
                  <td><?php echo $row['quantity']; ?></td>
                  <td><?php echo $row['rupees']; ?></td>
                  <td><?php echo htmlspecialchars($row['barcode_id']); ?></td>
                  <td class="action-links">
                      <a class="edit-link" href="edit_product_form.php?id=<?php echo $row['id']; ?>">Edit</a>
                      <a class="delete-link" href="delete_product.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                  </td>
              </tr>
              <?php endwhile; ?>
          </tbody>
      </table>
      <?php else: ?>
          <p>No products found.</p>
      <?php endif; ?>
      
      <!-- Pagination Links -->
      <div class="pagination">
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
              <a class="<?php echo ($p == $page) ? 'active' : ''; ?>" href="edit_product.php?search=<?php echo urlencode($search); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
          <?php endfor; ?>
      </div>

      <!-- Log Div for Barcode Scanning Status -->
      <div id="log"></div>
  </div>

  <script>
  // Barcode scanning functionality
  function logMessage(s) {
      var logDiv = document.getElementById('log');
      logDiv.innerHTML += s + "<br />";
      logDiv.scrollTop = logDiv.scrollHeight;
  }

  var TIMEOUT = 5000;
  var wsUri;
  var websocket;
  var open = false;

  function initScanner() {
      var sIP = sessionStorage.getItem("scanner_ip");
      var sPort = sessionStorage.getItem("scanner_port");

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
      var data = event.data;
      // Check if the data is a Blob, then convert to text
      if (data instanceof Blob) {
          var reader = new FileReader();
          reader.onload = function(e) {
              handleScannedBarcode(e.target.result);
          };
          reader.readAsText(data);
      } else {
          handleScannedBarcode(data);
      }
  }

  // When a barcode is scanned, auto-populate the search field and submit the form
  function handleScannedBarcode(barcode) {
      logMessage("Scanned Barcode: " + barcode);
      document.getElementById("search").value = barcode;
      document.querySelector("form.search-form").submit();
  }

  function onclose() {
      logMessage("Scanner disconnected.");
      open = false;
  }

  window.addEventListener("load", initScanner);
  </script>
</body>
</html>

edit_product_form.php

<?php
session_start();
include 'sidebar.php'; // Include Sidebar
include 'db_connect.php'; // Database Connection

$message = "";
$product = null;

// Retrieve the product using the provided ID
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $result = $conn->query("SELECT * FROM products WHERE id = $id");
    if ($result && $result->num_rows == 1) {
        $product = $result->fetch_assoc();
    } else {
        $message = "Product not found.";
    }
}

// Process form submission to update product details
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $quantity = $_POST['quantity'];
    $rupees = $_POST['rupees'];
    // Barcode remains unchanged
    $update_query = "UPDATE products SET name='$name', quantity='$quantity', rupees='$rupees' WHERE id='$id'";
    if ($conn->query($update_query) === TRUE) {
        $message = "Product updated successfully!";
        // Reload product details after update
        $result = $conn->query("SELECT * FROM products WHERE id = $id");
        if ($result && $result->num_rows == 1) {
            $product = $result->fetch_assoc();
        }
    } else {
        $message = "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product Form</title>
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
        input[type="text"],
        input[type="number"] {
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
            color: <?php echo (isset($message) && strpos($message, 'Error') !== false) ? 'red' : 'green'; ?>;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit Product</h1>
        <?php if ($message) echo "<div class='message'>$message</div>"; ?>
        
        <?php if ($product) { ?>
        <form action="edit_product_form.php?id=<?php echo $product['id']; ?>" method="POST">
            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
            
            <label for="name">Product Name:</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
            
            <label for="quantity">Quantity:</label>
            <input type="number" id="quantity" name="quantity" value="<?php echo $product['quantity']; ?>" required>
            
            <label for="rupees">Price (₹):</label>
            <input type="number" id="rupees" name="rupees" value="<?php echo $product['rupees']; ?>" required>
            
            <label for="barcode_id">Barcode ID:</label>
            <input type="text" id="barcode_id" name="barcode_id" value="<?php echo htmlspecialchars($product['barcode_id']); ?>" readonly>
            
            <button type="submit" name="update">Update Product</button>
        </form>
        <?php } ?>
    </div>
</body>
</html>

index.php

<?php
include 'sidebar.php'; // Include Sidebar
include 'db_connect.php'; // Database Connection

// Fetch total number of registered items
$query = "SELECT COUNT(*) AS total_items FROM products";
$result = $conn->query($query);
$row = $result->fetch_assoc();
$total_items = $row['total_items'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
        .dashboard-container {
            margin-left: 250px;
            padding: 20px;
            font-family: Arial, sans-serif;
        }

        .dashboard-container h1 {
            font-size: 28px;
            margin-bottom: 20px;
        }

        .card {
            background: #1e1e2d;
            color: white;
            padding: 20px;
            border-radius: 10px;
            width: 300px;
            text-align: center;
        }

        .card h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .card p {
            font-size: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h1>Welcome to the Admin Dashboard</h1>
        <div class="card">
            <h2>Total Registered Items</h2>
            <p><?php echo $total_items; ?></p>
        </div>
    </div>
</body>
</html>

insert_product.php

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

login.php

<?php
session_start();
include 'db_connect.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username='$username' AND password='$password'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $_SESSION['user'] = $username;
        header("Location: index.php");
        exit();
    } else {
        $error = "Invalid username or password.";
        #$_SESSION['user'] = $username;
        #header("Location: index.php");
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <style>
        <?php include 'sidebar.css'; ?>
        .login-container {
            width: 300px;
            margin: 100px auto;
            padding: 20px;
            background: #1e1e2d;
            color: white;
            border-radius: 8px;
            text-align: center;
        }
        input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
        }
        button {
            width: 100%;
            padding: 10px;
            background: #282842;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background: #3a3a52;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Admin Login</h2>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <?php if ($error): ?>
            <p style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>
    </div>
</body>
</html>

logout.php

<?php
session_start();
session_destroy();
header("Location: login.php");
exit();
?>

manage_user.php

<?php
include 'auth.php';
include 'db_connect.php';

$result = $conn->query("SELECT * FROM users");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <style>
        <?php include 'sidebar.css'; ?>
        .container {
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #1e1e2d;
            color: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        th {
            background: #282842;
        }
        tr:hover {
            background: #3a3a52;
        }
        .anc {
            color: #ff4c4c;
            text-decoration: none;
            padding: 5px 10px;
            border: 1px solid #ff4c4c;
            border-radius: 4px;
            transition: 0.3s;
        }
        .anc:hover {
            background: #ff4c4c;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="content">
        <div class="container">
            <h2>Manage Users</h2>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Action</th>
                </tr>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo $row['username']; ?></td>
                        <td><a class="anc" href="delete_user.php?id=<?php echo $row['id']; ?>">Delete</a></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html>

product_list.php

<?php
include 'sidebar.php'; // Include Sidebar
include 'db_connect.php'; // Database Connection

// Get search term from query parameter, if any
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : "";

// Set pagination parameters
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build base query and apply search filter if needed
$queryBase = "FROM products";
$where = ($search !== "") ? " WHERE name LIKE '%$search%' OR barcode_id LIKE '%$search%'" : "";
$queryTotal = "SELECT COUNT(*) as total " . $queryBase . $where;
$totalResult = $conn->query($queryTotal);
$totalRow = $totalResult->fetch_assoc();
$totalEntries = $totalRow['total'];
$totalPages = ($totalEntries > 0) ? ceil($totalEntries / $limit) : 1;

$query = "SELECT * " . $queryBase . $where . " LIMIT $limit OFFSET $offset";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product List</title>
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
        form.search-form {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        form.search-form input[type="text"] {
            flex-grow: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        form.search-form button {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            background: #1e1e2d;
            color: white;
            cursor: pointer;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background: #1e1e2d;
            color: white;
        }
        tr:nth-child(even) {
            background: #f2f2f2;
        }
        .pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 20px; /* Adds space between table and pagination */
}

.pagination a {
    padding: 8px 12px;
    margin: 0 2px;
    background: #1e1e2d;
    color: #fff;
    text-decoration: none;
    border-radius: 3px;
}

.pagination a.active {
    background: #4a4a6a;
}

        /* Barcode scan log styling */
        #log {
            margin-top: 20px;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            max-width: 600px;
            height: 150px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Product List</h1>
        
        <!-- Search Form -->
        <form class="search-form" action="product_list.php" method="GET">
            <input type="text" id="search" name="search" placeholder="Search by product name or barcode" value="<?php echo htmlspecialchars($search); ?>" required>
            <button type="submit">Search</button>
        </form>
        
        <!-- Products Table -->
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Quantity</th>
                    <th>Price (₹)</th>
                    <th>Barcode ID</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo $row['quantity']; ?></td>
                            <td><?php echo $row['rupees']; ?></td>
                            <td><?php echo htmlspecialchars($row['barcode_id']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No products found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Pagination Links -->
        <div class="pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a class="<?php echo ($p == $page) ? 'active' : ''; ?>" href="product_list.php?search=<?php echo urlencode($search); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
        </div>
        
        <!-- Log Div for Barcode Scanning Status -->
        <div id="log"></div>
    </div>
    
    <script>
    // Barcode scanning functionality using WebSocket
    function logMessage(s) {
        var logDiv = document.getElementById('log');
        logDiv.innerHTML += s + "<br />";
        logDiv.scrollTop = logDiv.scrollHeight;
    }
    
    var TIMEOUT = 5000;
    var wsUri;
    var websocket;
    var open = false;
    
    function initScanner() {
        var sIP = sessionStorage.getItem("scanner_ip");
        var sPort = sessionStorage.getItem("scanner_port");
    
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
        var data = event.data;
        if (data instanceof Blob) {
            var reader = new FileReader();
            reader.onload = function(e) {
                handleScannedBarcode(e.target.result);
            };
            reader.readAsText(data);
        } else {
            handleScannedBarcode(data);
        }
    }
    
    // When a barcode is scanned, auto-populate the search field and submit the search form
    function handleScannedBarcode(barcode) {
        logMessage("Scanned Barcode: " + barcode);
        document.getElementById("search").value = barcode;
        document.querySelector("form.search-form").submit();
    }
    
    function onclose() {
        logMessage("Scanner disconnected.");
        open = false;
    }
    
    window.addEventListener("load", initScanner);
    </script>
</body>
</html>

remove_product.php

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
    $stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE barcode_id = ?");
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
    <h1>Remove Product</h1>
    <!-- Connection Log -->
    <!-- Auto Decrement Toggle -->
    <div class="auto-increment">
      <label>
        <input type="checkbox" id="autoDecrementToggle"> Auto Decrement
      </label>
    </div>
    <!-- Display product details -->
    <div id="product-info" class="hidden"></div>
    <!-- Manual quantity input form (shown if auto decrement is disabled) -->
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

    // Load auto Decrement state from sessionStorage
    const autoDecrementToggle = document.getElementById('autoDecrementToggle');
    let autoDecrementState = sessionStorage.getItem("autoDecrementEnabled");
    if(autoDecrementState === "true") {
      autoDecrementToggle.checked = true;
    }
    autoDecrementToggle.addEventListener('change', function() {
      sessionStorage.setItem("autoDecrementEnabled", autoDecrementToggle.checked);
      logMessage("Auto Decrement " + (autoDecrementToggle.checked ? "enabled" : "disabled"));
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
    fetch("remove_product.php?action=fetch&barcode=" + encodeURIComponent(barcode))
      .then(response => response.json())
      .then(data => {
        if(data.success) {
          displayProduct(data.product);
          handleautoDecrement(barcode);
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
  function handleautoDecrement(barcode) {
    const autoDecrementEnabled = sessionStorage.getItem("autoDecrementEnabled") === "true";
    if(autoDecrementEnabled) {
      updateProductQuantity(barcode, 1);
      document.getElementById('manual-input').classList.add('hidden');
    } else {
      document.getElementById('manual-input').classList.remove('hidden');
    }
  }

  // Toggle manual input form based on auto increment state
  function toggleManualInput() {
    const autoDecrementEnabled = sessionStorage.getItem("autoDecrementEnabled") === "true";
    if(!autoDecrementEnabled && currentBarcode !== "") {
      document.getElementById('manual-input').classList.remove('hidden');
    } else {
      document.getElementById('manual-input').classList.add('hidden');
    }
  }

  // AJAX request to update product quantity in the DB
  function updateProductQuantity(barcode, quantity) {
    fetch("remove_product.php?action=update&barcode=" + encodeURIComponent(barcode) + "&quantity=" + quantity)
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

sidebar.php

<?php
// Sidebar Component
include 'auth.php';
?>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: Arial, sans-serif;
    }

    .sidebar {
        width: 250px;
        height: 100vh;
        background: #1e1e2d;
        color: white;
        position: fixed;
        top: 0;
        left: 0;
        padding-top: 20px;
        transition: 0.3s;
    }

    .sidebar h2 {
        text-align: center;
        margin-bottom: 20px;
        font-size: 22px;
    }

    .sidebar ul {
        list-style: none;
        padding: 0;
    }

    .sidebar ul li {
        padding: 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        cursor: pointer;
    }

    .sidebar ul li:hover {
        background: #282842;
    }

    .sidebar ul li a {
        color: white;
        text-decoration: none;
        display: block;
    }

    .content {
        margin-left: 250px;
        padding: 20px;
    }
</style>

<div class="sidebar">
    <h2>Admin Panel</h2>
    <?php if (isset($_SESSION['user'])): ?>
    <ul>
   
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="product_list.php">Product List</a></li>
        <li><a href="insert_product.php">Insert Product</a></li>
        <li><a href="edit_product.php">Edit Product</a></li>
        <li><a href="add_product.php" >Add Product</a></li>
        <li><a href="remove_product.php" >Remove Product</a></li>
        <li><a href="connect_scanner.php">Connect Scanner</a></li>
        <li><a href="manage_user.php">Manage Users</a></li>
        <li><a href="add_user.php">Add User</a></li>
        <li><a href="change_password.php">Chanage Password</a></li>
        <li><a href="logout.php">Logout</a></li>

    </ul>
    <?php else: ?>
            <li><a href="login.php">Login</a></li>
            <?php endif; ?>
</div>
