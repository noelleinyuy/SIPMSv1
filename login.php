<?php

session_start();
require_once "db.php";

$roleConfig = [
    "Student" => [
        "table" => "student",
        "id_column" => "studentID",
        "name_column" => "fullName",
        "dashboard" => "student/dashboard.php",
        "param" => "student",
    ],
    "Company" => [
        "table" => "company",
        "id_column" => "CompanyID",
        "name_column" => "Name",
        "dashboard" => "company/dashboard.php",
        "param" => "company",
    ],
    "Supervisor" => [
        "table" => "supervisor",
        "id_column" => "SupervisorID",
        "name_column" => "FullName",
        "dashboard" => "supervisor/dashboard.php",
        "param" => "supervisor",
    ],
    "Admin" => [
        "table" => "admin",
        "id_column" => "AdminID",
        "name_column" => "FullName",
        "dashboard" => "admin/admin.php",
        "param" => "admin",
    ],
];

// param -> real Role value stored in the database.
$paramToRole = [
    "student" => "Student",
    "company" => "Company",
    "supervisor" => "Supervisor",
    // Keep the old link value working for existing bookmarks.
    "lecturer" => "Supervisor",
    "admin" => "Admin",
];

// Real Role value -> the label shown to the user ("Lecturer" in the DB
// displays as "Supervisor" everywhere in the UI).
$roleLabels = [
    "Student" => "Student",
    "Company" => "Company",
    "Supervisor" => "Supervisor",
    "Lecturer" => "Supervisor",
    "Admin" => "Administrator",
];

// Already logged in? Skip straight to the right dashboard.
if (isset($_SESSION["role"]) && isset($roleConfig[$_SESSION["role"]])) {
    header("Location: " . $roleConfig[$_SESSION["role"]]["dashboard"]);
    exit;
}

// Pre-select a portal card if arriving with ?role=... (e.g. from index.php).
$preselectParam = strtolower($_GET["role"] ?? "");
$preselectParam = isset($paramToRole[$preselectParam]) ? $preselectParam : "";

$error = $_SESSION["login_error"] ?? "";
$success = $_SESSION["login_success"] ?? "";
$oldEmail = $_SESSION["login_email"] ?? "";
$oldRoleParam = $_SESSION["login_role_param"] ?? $preselectParam;
unset($_SESSION["login_error"], $_SESSION["login_success"], $_SESSION["login_email"], $_SESSION["login_role_param"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $selectedParam = strtolower($_POST["role"] ?? "");
    $selectedRole = $paramToRole[$selectedParam] ?? null;

    if ($email === "" || $password === "") {

        $_SESSION["login_error"] = "Please enter your email and password.";
        $_SESSION["login_email"] = $email;
        $_SESSION["login_role_param"] = $selectedParam;
        header("Location: login.php");
        exit;

    }

    if ($selectedRole === null) {

        $_SESSION["login_error"] = "Please select a portal before logging in.";
        $_SESSION["login_email"] = $email;
        header("Location: login.php");
        exit;

    }

    $stmt = $conn->prepare("SELECT UserID, Email, Password, Role FROM users WHERE Email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->num_rows === 1 ? $result->fetch_assoc() : null;
    $stmt->close();

    $storedRole = $user["Role"] ?? "";
    if ($storedRole === "Lecturer") {
        $storedRole = "Supervisor";
    }

    if (!$user) {

        $_SESSION["login_error"] = "No account was found with this email address.";
        $_SESSION["login_email"] = $email;
        $_SESSION["login_role_param"] = $selectedParam;
        header("Location: login.php");
        exit;

    }

    if (!password_verify($password, $user["Password"])) {

        $_SESSION["login_error"] = "The password is incorrect.";
        $_SESSION["login_email"] = $email;
        $_SESSION["login_role_param"] = $selectedParam;
        header("Location: login.php");
        exit;

    }

    if (!isset($roleConfig[$storedRole])) {

        $_SESSION["login_error"] = "This account has an invalid role. Please contact an administrator.";
        $_SESSION["login_email"] = $email;
        $_SESSION["login_role_param"] = $selectedParam;
        header("Location: login.php");
        exit;

    }

    // The real DB role always governs access — but if it doesn't match the
    // portal the user clicked, tell them clearly instead of silently
    // logging them into a different portal than the one they chose.
    if ($storedRole !== $selectedRole) {

        $_SESSION["login_error"] = "This account isn't registered under the " . $roleLabels[$selectedRole] . " portal. Please select the correct portal.";
        $_SESSION["login_email"] = $email;
        $_SESSION["login_role_param"] = $selectedParam;
        header("Location: login.php");
        exit;

    }

    $config = $roleConfig[$storedRole];

    $profileStmt = $conn->prepare("
        SELECT {$config['id_column']} AS ProfileID, {$config['name_column']} AS DisplayName
        FROM {$config['table']}
        WHERE UserID = ?
        LIMIT 1
    ");
    $profileStmt->bind_param("i", $user["UserID"]);
    $profileStmt->execute();
    $profile = $profileStmt->get_result()->fetch_assoc();
    $profileStmt->close();

    $_SESSION["user_id"] = $user["UserID"];
    $_SESSION["role"] = $storedRole;
    $_SESSION["email"] = $user["Email"];
    $_SESSION["profile_id"] = $profile["ProfileID"] ?? null;
    $_SESSION["full_name"] = $profile["DisplayName"] ?? "";

    header("Location: " . $config["dashboard"]);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | SIPMS</title>

    <link rel="stylesheet" href="css/boxicons.min.css">
    <link rel="stylesheet" href="css/login.css">

</head>

<body class="login-page">

    <div class="login-container">

        <div class="login-card">

            <a href="index.php" class="back-to-roles"><i class='bx bx-arrow-back'></i> Back to home</a>

            <div class="login-header">
                <i class='bx bxs-graduation'></i>
                <h2>SIPMS</h2>
                <p>Smart Internship Management System</p>
            </div>

            <?php if ($error !== ""): ?>
                <div class="auth-alert auth-alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="auth-alert auth-alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form id="loginForm" class="auth-form" method="POST" action="login.php">

                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" id="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($oldEmail); ?>" required>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" id="password" placeholder="Enter your password" required>
                </div>

                <div class="portal-selection">

                    <h3>Select Your Portal</h3>

                    <div class="portal-grid">

                        <div class="portal-card" data-role="student">
                            <i class='bx bxs-graduation'></i>
                            <span>Student</span>
                        </div>

                        <div class="portal-card" data-role="supervisor">
                            <i class='bx bxs-book-reader'></i>
                            <span>Supervisor</span>
                        </div>

                        <div class="portal-card" data-role="company">
                            <i class='bx bxs-building-house'></i>
                            <span>Company</span>
                        </div>

                        <div class="portal-card" data-role="admin">
                            <i class='bx bxs-user-badge'></i>
                            <span>Administrator</span>
                        </div>

                    </div>

                </div>

                <input type="hidden" name="role" id="role" value="<?php echo htmlspecialchars($oldRoleParam); ?>">

                <button type="submit" class="btn login-btn">
                    <i class='bx bx-log-in'></i>
                    Login
                </button>

                <div class="login-links">
                    <a href="#">Forgot Password?</a>
                    <p>
                        Don't have an account?
                        <a href="register.php">Register</a>
                    </p>
                </div>

            </form>

        </div>

    </div>

    <script src="js/login.js"></script>

</body>

</html>