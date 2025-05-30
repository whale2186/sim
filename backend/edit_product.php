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
