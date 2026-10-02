<?php

require_once "helpers.php";
require_once "../db.php";
require_admin_login();

$userId = $_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["form"] ?? "") === "profile") {

    $fullName = trim($_POST["fullName"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $organisation = trim($_POST["organisation"] ?? "");
    $phone = trim($_POST["phone"] ?? "");

    if ($fullName === "" || $email === "") {

        admin_flash("error", "Full name and email are required.");

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        admin_flash("error", "Please enter a valid email address.");

    } else {

        $stmt = $conn->prepare("SELECT UserID FROM users WHERE Email = ? AND UserID != ?");
        $stmt->bind_param("si", $email, $userId);
        $stmt->execute();
        $emailTaken = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($emailTaken) {

            admin_flash("error", "That email is already in use by another account.");

        } else {

            $conn->begin_transaction();

            try {

                $stmt = $conn->prepare("UPDATE users SET Email = ? WHERE UserID = ?");
                $stmt->bind_param("si", $email, $userId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE admin SET FullName = ?, Organisation = ?, Phone = ? WHERE UserID = ?");
                $stmt->bind_param("sssi", $fullName, $organisation, $phone, $userId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                $_SESSION["full_name"] = $fullName;
                $_SESSION["email"] = $email;

                admin_flash("success", "Profile updated.");

            } catch (\mysqli_sql_exception $e) {

                $conn->rollback();
                admin_flash("error", "Could not update profile.");

            }
        }
    }

    header("Location: setting.php");
    exit;

}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["form"] ?? "") === "password") {

    $current = $_POST["current_password"] ?? "";
    $new = $_POST["new_password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";

    $stmt = $conn->prepare("SELECT Password FROM users WHERE UserID = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($current, $row["Password"])) {

        admin_flash("error", "Current password is incorrect.");

    } elseif (strlen($new) < 8) {

        admin_flash("error", "New password must be at least 8 characters.");

    } elseif ($new !== $confirm) {

        admin_flash("error", "New passwords do not match.");

    } else {

        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET Password = ? WHERE UserID = ?");
        $stmt->bind_param("si", $hash, $userId);
        $stmt->execute();
        $stmt->close();

        admin_flash("success", "Password updated.");

    }

    header("Location: setting.php");
    exit;

}

$stmt = $conn->prepare("
    SELECT a.FullName, a.Organisation, a.Phone, u.Email
    FROM admin a
    JOIN users u ON a.UserID = u.UserID
    WHERE a.UserID = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

$flash = admin_get_flash();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Settings | SIPMS Admin</title>

    <link rel="stylesheet" href="../css/boxicons.min.css">
    <link rel="stylesheet" href="../css/admin.css">

</head>

<body>

<div class="admin-shell">

    <aside class="admin-sidebar" id="adminSidebar">

        <div class="admin-logo"><i class='bx bxs-graduation'></i> SIPMS</div>

        <nav class="admin-nav">
            <a href="admin.php"><i class='bx bx-grid-alt'></i> Dashboard</a>
            <a href="student.php"><i class='bx bx-user'></i> Students</a>
            <a href="company.php"><i class='bx bx-building'></i> Companies</a>
            <a href="supervisor.php"><i class='bx bx-chalkboard'></i> Supervisors</a>
            <a href="internship.php"><i class='bx bx-briefcase'></i> Internships</a>
            <a href="application.php"><i class='bx bx-file'></i> Applications</a>
            <a href="attendance.php"><i class='bx bx-time-five'></i> Attendance</a>
            <a href="task.php"><i class='bx bx-list-check'></i> Tasks</a>
            <a href="message.php"><i class='bx bx-envelope'></i> Messages</a>
            <a href="setting.php" class="active"><i class='bx bx-cog'></i> Settings</a>
        </nav>

        <a href="../logout.php" class="admin-logout"><i class='bx bx-log-out'></i> Logout</a>

    </aside>

    <div class="admin-main">

        <header class="admin-topbar">
            <button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle menu">
                <i class='bx bx-menu'></i>
            </button>
            <h1>Settings</h1>
            <div class="admin-user"><i class='bx bx-user-circle'></i> <?php echo e($profile["FullName"] ?? "Admin"); ?></div>
        </header>

        <main class="admin-content">

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?php echo e($flash["type"]); ?>"><?php echo e($flash["message"]); ?></div>
            <?php endif; ?>

            <div class="admin-panel">
                <h2>Your Profile</h2>

                <form method="POST" class="admin-form">
                    <input type="hidden" name="form" value="profile">

                    <div class="form-row">
                        <div>
                            <label>Full Name</label>
                            <input type="text" name="fullName" required value="<?php echo e($profile["FullName"] ?? ""); ?>">
                        </div>
                        <div>
                            <label>Email</label>
                            <input type="email" name="email" required value="<?php echo e($profile["Email"] ?? ""); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div>
                            <label>Organisation</label>
                            <input type="text" name="organisation" value="<?php echo e($profile["Organisation"] ?? ""); ?>">
                        </div>
                        <div>
                            <label>Phone</label>
                            <input type="tel" name="phone" value="<?php echo e($profile["Phone"] ?? ""); ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">Save Profile</button>
                </form>
            </div>

            <div class="admin-panel">
                <h2>Change Password</h2>

                <form method="POST" class="admin-form">
                    <input type="hidden" name="form" value="password">

                    <label>Current Password</label>
                    <input type="password" name="current_password" required>

                    <label>New Password</label>
                    <input type="password" name="new_password" required minlength="8">

                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="8">

                    <button type="submit" class="btn-primary">Update Password</button>
                </form>
            </div>

        </main>

    </div>

</div>

<script src="../js/admin.js"></script>

</body>

</html>