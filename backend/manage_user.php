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
