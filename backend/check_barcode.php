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
