<?php

session_start();
require_once "../db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Student") {
    header("Location: ../login.php?role=student");
    exit;
}

$userId = $_SESSION["user_id"];

function flash(string $type, string $message): void
{
    $_SESSION["student_flash"] = ["type" => $type, "message" => $message];
}

function get_flash(): ?array
{
    if (!empty($_SESSION["student_flash"])) {
        $f = $_SESSION["student_flash"];
        unset($_SESSION["student_flash"]);
        return $f;
    }
    return null;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["form"] ?? "") === "profile") {

    $fullName = trim($_POST["fullName"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $level = trim($_POST["level"] ?? "");

    if ($fullName === "" || $email === "" || $department === "" || $level === "") {

        flash("error", "Please fill in all fields.");

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        flash("error", "Please enter a valid email address.");

    } else {

        $stmt = $conn->prepare("SELECT UserID FROM users WHERE Email = ? AND UserID != ?");
        $stmt->bind_param("si", $email, $userId);
        $stmt->execute();
        $emailTaken = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($emailTaken) {

            flash("error", "That email is already in use by another account.");

        } else {

            $conn->begin_transaction();

            try {

                $stmt = $conn->prepare("UPDATE users SET Email = ? WHERE UserID = ?");
                $stmt->bind_param("si", $email, $userId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE student SET fullName = ?, Department = ?, Level = ? WHERE UserID = ?");
                $stmt->bind_param("sssi", $fullName, $department, $level, $userId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                $_SESSION["full_name"] = $fullName;

                flash("success", "Profile updated.");

            } catch (\mysqli_sql_exception $e) {

                $conn->rollback();
                flash("error", "Could not update profile.");

            }
        }
    }

    header("Location: profile.php");
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

        flash("error", "Current password is incorrect.");

    } elseif (strlen($new) < 8) {

        flash("error", "New password must be at least 8 characters.");

    } elseif ($new !== $confirm) {

        flash("error", "New passwords do not match.");

    } else {

        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET Password = ? WHERE UserID = ?");
        $stmt->bind_param("si", $hash, $userId);
        $stmt->execute();
        $stmt->close();

        flash("success", "Password updated.");

    }

    header("Location: profile.php");
    exit;

}

$stmt = $conn->prepare("
    SELECT s.fullName, s.matricule, s.Department, s.Level, u.Email
    FROM student s
    JOIN users u ON s.UserID = u.UserID
    WHERE s.UserID = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

$flash = get_flash();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | SIPMS</title>

    <link rel="stylesheet" href="../css/boxicons.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">

</head>

<body>

<div class="dash-shell">

    <aside class="dash-sidebar" id="dashSidebar">

        <div class="dash-logo"><i class='bx bxs-graduation'></i> SIPMS</div>

        <nav class="dash-nav">
            <a href="dashboard.php"><i class='bx bx-grid-alt'></i> Dashboard</a>
            <a href="application.php"><i class='bx bx-file'></i> My Applications</a>
            <a href="report.php"><i class='bx bx-notepad'></i> Submit Report</a>
            <a href="browse.php"><i class='bx bx-search'></i> Browse Internships</a>
            <a href="attendance.php"><i class='bx bx-time-five'></i> Attendance</a>
            <a href="task.php"><i class='bx bx-list-check'></i> Tasks</a>
            <a href="message.php"><i class='bx bx-envelope'></i> Messages</a>
            <a href="profile.php" class="active"><i class='bx bx-user'></i> Profile</a>
        </nav>

        <a href="../logout.php" class="dash-logout"><i class='bx bx-log-out'></i> Logout</a>

    </aside>

    <div class="dash-main">

        <header class="dash-topbar">
            <button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle menu">
                <i class='bx bx-menu'></i>
            </button>
            <h1>My Profile</h1>
            <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($profile["fullName"] ?? "Student"); ?></div>
        </header>

        <main class="dash-content">

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
            <?php endif; ?>

            <div class="dash-panel">
                <h2>Your Information</h2>

                <form method="POST" class="dash-form">
                    <input type="hidden" name="form" value="profile">

                    <div class="form-row">
                        <div>
                            <label>Full Name</label>
                            <input type="text" name="fullName" required value="<?php echo htmlspecialchars($profile["fullName"] ?? ""); ?>">
                        </div>
                        <div>
                            <label>Email</label>
                            <input type="email" name="email" required value="<?php echo htmlspecialchars($profile["Email"] ?? ""); ?>">
                        </div>
                    </div>

                    <label>Matricule</label>
                    <input type="text" value="<?php echo htmlspecialchars($profile["matricule"] ?? ""); ?>" disabled style="opacity: 0.6;">

                    <div class="form-row">
                        <div>
                            <label>Department</label>
                            <input type="text" name="department" required value="<?php echo htmlspecialchars($profile["Department"] ?? ""); ?>">
                        </div>
                        <div>
                            <label>Level</label>
                            <input type="text" name="level" required value="<?php echo htmlspecialchars($profile["Level"] ?? ""); ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">Save Profile</button>
                </form>
            </div>

            <div class="dash-panel">
                <h2>Change Password</h2>

                <form method="POST" class="dash-form">
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

<div class="dash-sidebar-backdrop"></div>
<script src="../js/dashboarsd.js"></script>

</body>

</html>