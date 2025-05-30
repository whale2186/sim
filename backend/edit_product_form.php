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
