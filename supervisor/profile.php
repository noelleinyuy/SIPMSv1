<?php

session_start();
require_once "../db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Supervisor") {
    header("Location: ../login.php?role=supervisor");
    exit;
}

$userId = $_SESSION["user_id"];

function flash(string $type, string $message): void
{
    $_SESSION["supervisor_flash"] = ["type" => $type, "message" => $message];
}

function get_flash(): ?array
{
    if (!empty($_SESSION["supervisor_flash"])) {
        $flash = $_SESSION["supervisor_flash"];
        unset($_SESSION["supervisor_flash"]);
        return $flash;
    }
    return null;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["form"] ?? "") === "profile") {
    $fullName = trim($_POST["fullName"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $phone = trim($_POST["phone"] ?? "");

    if ($fullName === "" || $email === "" || $department === "" || $phone === "") {
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

                $stmt = $conn->prepare("UPDATE supervisor SET FullName = ?, Department = ?, Phone = ? WHERE UserID = ?");
                $stmt->bind_param("sssi", $fullName, $department, $phone, $userId);
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
    SELECT s.FullName, s.Department, s.Phone, u.Email
    FROM supervisor s
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
            <a href="intern.php"><i class='bx bx-group'></i> My Interns</a>
            <a href="report.php"><i class='bx bx-notepad'></i> Reports</a>
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
            <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($profile["FullName"] ?? "Supervisor"); ?></div>
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
                            <input type="text" name="fullName" required value="<?php echo htmlspecialchars($profile["FullName"] ?? ""); ?>">
                        </div>
                        <div>
                            <label>Email</label>
                            <input type="email" name="email" required value="<?php echo htmlspecialchars($profile["Email"] ?? ""); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div>
                            <label>Department</label>
                            <input type="text" name="department" required value="<?php echo htmlspecialchars($profile["Department"] ?? ""); ?>">
                        </div>
                        <div>
                            <label>Phone Number</label>
                            <input type="tel" name="phone" required value="<?php echo htmlspecialchars($profile["Phone"] ?? ""); ?>">
                        </div>
                    </div>

                    <div class="row-actions">
                        <button type="submit" class="btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>

            <div class="dash-panel">
                <h2>Change Password</h2>
                <form method="POST" class="dash-form">
                    <input type="hidden" name="form" value="password">

                    <label>Current Password</label>
                    <input type="password" name="current_password" required>

                    <div class="form-row">
                        <div>
                            <label>New Password</label>
                            <input type="password" name="new_password" required>
                        </div>
                        <div>
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                    </div>

                    <div class="row-actions">
                        <button type="submit" class="btn-primary">Update Password</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</div>

<div class="dash-sidebar-backdrop"></div>
<script src="../js/dashboarsd.js"></script>
</body>

</html>
