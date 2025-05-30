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
