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
