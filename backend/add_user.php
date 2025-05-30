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
