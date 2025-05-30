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
