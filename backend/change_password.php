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
