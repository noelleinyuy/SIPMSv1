<?php

session_start();
require_once "db.php";

// Already logged in? No need to register again.
if (isset($_SESSION["role"])) {
    header("Location: login.php");
    exit;
}

$error = $_SESSION["register_error"] ?? "";
$old = $_SESSION["register_old"] ?? [];
unset($_SESSION["register_error"], $_SESSION["register_old"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $userType = $_POST["userType"] ?? "";
    $fullname = trim($_POST["fullname"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirmPassword"] ?? "";

    // Role-specific fields — only the ones relevant to $userType matter.
    $matricule = trim($_POST["matricule"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $level = trim($_POST["level"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $companyType = trim($_POST["companyType"] ?? "");

    $old = compact(
        "userType", "fullname", "email", "phone",
        "matricule", "department", "level",
        "address", "description", "companyType"
    );

    $validRoles = ["Student", "Supervisor", "Company"];

    if (!in_array($userType, $validRoles, true)) {

        $error = "Please select what you're registering as.";

    } elseif ($fullname === "" || $email === "" || $password === "") {

        $error = "Please fill in all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } elseif ($password !== $confirmPassword) {

        $error = "Passwords do not match.";

    } elseif ($userType === "Student" && ($matricule === "" || $department === "" || $level === "")) {

        $error = "Please fill in your matricule, department, and level.";

    } elseif ($userType === "Supervisor" && ($department === "" || $phone === "")) {

        $error = "Please fill in your department and phone number.";

    } elseif ($userType === "Company" && ($phone === "" || $address === "" || $description === "" || $companyType === "")) {

        $error = "Please fill in your address, description, type, and phone number.";

    } else {

        $stmt = $conn->prepare("SELECT UserID FROM users WHERE Email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $emailExists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($emailExists) {

            $error = "An account with this email already exists.";

        } elseif ($userType === "Student") {

            $stmt = $conn->prepare("SELECT studentID FROM student WHERE matricule = ? LIMIT 1");
            $stmt->bind_param("s", $matricule);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "This matricule is already registered.";
            }
            $stmt->close();
        }

        if ($error === "") {

            $conn->begin_transaction();

            try {

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO users (Email, Password, Role) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $email, $passwordHash, $userType);
                $stmt->execute();
                $userId = $stmt->insert_id;
                $stmt->close();

                if ($userType === "Student") {

                    // Note: the student table has no Phone column, so the
                    // phone number entered isn't stored for this role.
                    $stmt = $conn->prepare("
                        INSERT INTO student (UserID, fullName, matricule, Department, Level)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("issss", $userId, $fullname, $matricule, $department, $level);

                } elseif ($userType === "Supervisor") {

                    $stmt = $conn->prepare("
                        INSERT INTO supervisor (UserID, FullName, Department, Phone)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->bind_param("isss", $userId, $fullname, $department, $phone);

                } else {

                    $stmt = $conn->prepare("
                        INSERT INTO company (UserID, Name, Address, Description, Type, Phone)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("isssss", $userId, $fullname, $address, $description, $companyType, $phone);

                }

                $stmt->execute();
                $stmt->close();

                $conn->commit();

                $roleParam = strtolower($userType);
                $_SESSION["login_success"] = "Registration successful! You can now log in.";
                $_SESSION["login_email"] = $email;
                $_SESSION["login_role_param"] = $roleParam;
                header("Location: login.php?role=" . urlencode($roleParam));
                exit;

            } catch (\mysqli_sql_exception $e) {

                $conn->rollback();
                $error = "Something went wrong while creating your account. Please try again.";

            }
        }
    }

    $_SESSION["register_error"] = $error;
    $_SESSION["register_old"] = $old;
    header("Location: register.php");
    exit;
}

$field = fn($key) => htmlspecialchars($old[$key] ?? "");

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register | SIPMS</title>

    <link rel="stylesheet" href="css/boxicons.min.css">
    <link rel="stylesheet" href="css/register.css">

</head>

<body class="auth-page">

    <section class="auth-section">

        <div class="auth-card">

            <a href="index.php" class="back-to-roles"><i class='bx bx-arrow-back'></i> Back to home</a>

            <div class="login-header">
                <i class='bx bxs-user-plus'></i>
                <h2>Create Account</h2>
                <p>Smart Internship Management System</p>
            </div>

            <?php if ($error !== ""): ?>
                <div class="auth-alert auth-alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form id="registerForm" class="auth-form" method="POST" action="register.php">

                <div class="input-group">
                    <label>Register As</label>
                    <select name="userType" id="userType" required>
                        <option value="">Select User Type</option>
                        <option value="Student" <?php echo ($old["userType"] ?? "") === "Student" ? "selected" : ""; ?>>Student</option>
                        <option value="Supervisor" <?php echo ($old["userType"] ?? "") === "Supervisor" ? "selected" : ""; ?>>Supervisor</option>
                        <option value="Company" <?php echo ($old["userType"] ?? "") === "Company" ? "selected" : ""; ?>>Company</option>
                    </select>
                </div>

                <div class="input-group">
                    <label>Full Name / Company Name</label>
                    <input type="text" name="fullname" placeholder="Enter full name" value="<?php echo $field("fullname"); ?>" required>
                </div>

                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="Enter email address" value="<?php echo $field("email"); ?>" required>
                </div>

                <div class="input-group role-field" data-for="Supervisor,Company">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" placeholder="+237..." value="<?php echo $field("phone"); ?>">
                </div>

                <!-- Student-only fields -->
                <div class="input-group role-field" data-for="Student">
                    <label>Matricule</label>
                    <input type="text" name="matricule" value="<?php echo $field("matricule"); ?>">
                </div>

                <div class="input-group role-field" data-for="Student,Supervisor">
                    <label>Department</label>
                    <input type="text" name="department" value="<?php echo $field("department"); ?>">
                </div>

                <div class="input-group role-field" data-for="Student">
                    <label>Level</label>
                    <input type="text" name="level" value="<?php echo $field("level"); ?>">
                </div>

                <!-- Company-only fields -->
                <div class="input-group role-field" data-for="Company">
                    <label>Address</label>
                    <input type="text" name="address" value="<?php echo $field("address"); ?>">
                </div>

                <div class="input-group role-field" data-for="Company">
                    <label>Description</label>
                    <input type="text" name="description" value="<?php echo $field("description"); ?>">
                </div>

                <div class="input-group role-field" data-for="Company">
                    <label>Type</label>
                    <input type="text" name="companyType" placeholder="e.g. Technology, Finance" value="<?php echo $field("companyType"); ?>">
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Create password" minlength="8" required>
                </div>

                <div class="input-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirmPassword" placeholder="Confirm password" minlength="8" required>
                </div>

                <button type="submit" class="btn btn-primary login-btn">
                    <i class='bx bx-user-plus'></i>
                    Register
                </button>

                <div class="login-links">
                    <p>Already have an account? <a href="login.php">Login Here</a></p>
                    <p><a href="index.php"><i class='bx bx-home'></i> Back to Home</a></p>
                </div>

            </form>

        </div>

    </section>

    <script src="js/register.js"></script>

</body>

</html>