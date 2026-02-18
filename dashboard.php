<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

include "db.php";

$role = $_SESSION['role'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <style>
        table {
            border-collapse: collapse;
            width: 80%;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #0d3b66;
            color: white;
        }
        a {
            color: red;
            text-decoration: none;
            font-weight: bold;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<h2>Welcome, <?php echo $_SESSION['user']; ?> 🎉 (<?php echo strtoupper($role); ?>)</h2>

<hr>

<?php if ($role == 'owner') { ?>
    <p><a href="register.php">+ Add Manager Account</a></p>
    <h3>Manage Users</h3>

    <?php
    $sql = "SELECT id, name, email, role FROM users";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        echo "<table>";
        echo "
    <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Action</th>
    </tr>";

        while ($row = $result->fetch_assoc()) {
            $delete_button = ($row['id'] != $_SESSION['user_id'])
                ? "<a href='delete_user.php?id=".$row['id']."' onclick=\"return confirm('Are you sure you want to delete this user?')\">Delete</a>"
                : "—";

            echo "<tr>
                    <td>".$row['name']."</td>
                    <td>".$row['email']."</td>
                    <td>".$row['role']."</td>
                    <td>".$delete_button."</td>
                </tr>";
        }

        echo "</table>";
    } else {
        echo "<p>No users found.</p>";
    }
    ?>

<?php } ?>

<br>
<a href="logout.php">Logout</a>

</body>
</html>
</html>
