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
