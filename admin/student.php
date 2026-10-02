<?php

require_once "helpers.php";
require_once "../db.php";
require_admin_login();

$adminName = $_SESSION["full_name"] ?? "Admin";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "delete") {

        $userId = (int) ($_POST["user_id"] ?? 0);

        if ($userId) {

            $conn->begin_transaction();

            try {

                $stmt = $conn->prepare("DELETE FROM student WHERE UserID = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM users WHERE UserID = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                admin_flash("success", "Student deleted.");

            } catch (\mysqli_sql_exception $e) {

                $conn->rollback();
                admin_flash("error", "Could not delete student.");

            }
        }

        header("Location: student.php");
        exit;

    }

    if ($action === "add" || $action === "update") {

        $userId = (int) ($_POST["user_id"] ?? 0);
        $fullName = trim($_POST["fullName"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $matricule = trim($_POST["matricule"] ?? "");
        $department = trim($_POST["department"] ?? "");
        $level = trim($_POST["level"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($fullName === "" || $email === "" || $matricule === "" || $department === "" || $level === "") {

            admin_flash("error", "Please fill in all fields.");

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            admin_flash("error", "Please enter a valid email address.");

        } elseif ($action === "add" && strlen($password) < 8) {

            admin_flash("error", "Temporary password must be at least 8 characters.");

        } else {

            // Uniqueness checks, excluding the current record when editing.
            $stmt = $conn->prepare("SELECT UserID FROM users WHERE Email = ? AND UserID != ?");
            $stmt->bind_param("si", $email, $userId);
            $stmt->execute();
            $emailTaken = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            $stmt = $conn->prepare("SELECT studentID FROM student WHERE matricule = ? AND UserID != ?");
            $stmt->bind_param("si", $matricule, $userId);
            $stmt->execute();
            $matriculeTaken = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($emailTaken) {

                admin_flash("error", "That email is already in use by another account.");

            } elseif ($matriculeTaken) {

                admin_flash("error", "That matricule is already in use.");

            } elseif ($action === "add") {

                $conn->begin_transaction();

                try {

                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("INSERT INTO users (Email, Password, Role) VALUES (?, ?, 'Student')");
                    $stmt->bind_param("ss", $email, $passwordHash);
                    $stmt->execute();
                    $newUserId = $stmt->insert_id;
                    $stmt->close();

                    $stmt = $conn->prepare("
                        INSERT INTO student (UserID, fullName, matricule, Department, Level)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("issss", $newUserId, $fullName, $matricule, $department, $level);
                    $stmt->execute();
                    $stmt->close();

                    $conn->commit();
                    admin_flash("success", "Student added.");

                } catch (\mysqli_sql_exception $e) {

                    $conn->rollback();
                    admin_flash("error", "Could not add student.");

                }

            } else {

                // update
                $conn->begin_transaction();

                try {

                    $stmt = $conn->prepare("UPDATE users SET Email = ? WHERE UserID = ?");
                    $stmt->bind_param("si", $email, $userId);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare("
                        UPDATE student SET fullName = ?, matricule = ?, Department = ?, Level = ?
                        WHERE UserID = ?
                    ");
                    $stmt->bind_param("ssssi", $fullName, $matricule, $department, $level, $userId);
                    $stmt->execute();
                    $stmt->close();

                    $conn->commit();
                    admin_flash("success", "Student updated.");

                } catch (\mysqli_sql_exception $e) {

                    $conn->rollback();
                    admin_flash("error", "Could not update student.");

                }
            }
        }

        header("Location: student.php");
        exit;
    }
}

$editId = (int) ($_GET["edit"] ?? 0);
$editing = null;

if ($editId) {
    $stmt = $conn->prepare("
        SELECT s.UserID, s.fullName, s.matricule, s.Department, s.Level, u.Email
        FROM student s
        JOIN users u ON s.UserID = u.UserID
        WHERE s.UserID = ?
    ");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$students = $conn->query("
    SELECT s.studentID, s.UserID, s.fullName, s.matricule, s.Department, s.Level, u.Email, u.CreatedAt
    FROM student s
    JOIN users u ON s.UserID = u.UserID
    ORDER BY s.studentID DESC
");

$flash = admin_get_flash();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Students | SIPMS Admin</title>

    <link rel="stylesheet" href="../css/boxicons.min.css">
    <link rel="stylesheet" href="../css/admin.css">

</head>

<body>

<div class="admin-shell">

    <aside class="admin-sidebar" id="adminSidebar">

        <div class="admin-logo"><i class='bx bxs-graduation'></i> SIPMS</div>

        <nav class="admin-nav">
            <a href="admin.php"><i class='bx bx-grid-alt'></i> Dashboard</a>
            <a href="student.php" class="active"><i class='bx bx-user'></i> Students</a>
            <a href="company.php"><i class='bx bx-building'></i> Companies</a>
            <a href="supervisor.php"><i class='bx bx-chalkboard'></i> Supervisors</a>
            <a href="internship.php"><i class='bx bx-briefcase'></i> Internships</a>
            <a href="application.php"><i class='bx bx-file'></i> Applications</a>
            <a href="attendance.php"><i class='bx bx-time-five'></i> Attendance</a>
            <a href="task.php"><i class='bx bx-list-check'></i> Tasks</a>
            <a href="message.php"><i class='bx bx-envelope'></i> Messages</a>
            <a href="setting.php"><i class='bx bx-cog'></i> Settings</a>
        </nav>

        <a href="../logout.php" class="admin-logout"><i class='bx bx-log-out'></i> Logout</a>

    </aside>

    <div class="admin-main">

        <header class="admin-topbar">
            <button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle menu">
                <i class='bx bx-menu'></i>
            </button>
            <h1>Students</h1>
            <div class="admin-user"><i class='bx bx-user-circle'></i> <?php echo e($adminName); ?></div>
        </header>

        <main class="admin-content">

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?php echo e($flash["type"]); ?>"><?php echo e($flash["message"]); ?></div>
            <?php endif; ?>

            <div class="admin-panel">
                <h2><?php echo $editing ? "Edit Student" : "Add a Student"; ?></h2>

                <form method="POST" class="admin-form">
                    <input type="hidden" name="action" value="<?php echo $editing ? "update" : "add"; ?>">
                    <?php if ($editing): ?>
                        <input type="hidden" name="user_id" value="<?php echo (int) $editing["UserID"]; ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div>
                            <label>Full Name</label>
                            <input type="text" name="fullName" required value="<?php echo e($editing["fullName"] ?? ""); ?>">
                        </div>
                        <div>
                            <label>Email</label>
                            <input type="email" name="email" required value="<?php echo e($editing["Email"] ?? ""); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div>
                            <label>Matricule</label>
                            <input type="text" name="matricule" required value="<?php echo e($editing["matricule"] ?? ""); ?>">
                        </div>
                        <div>
                            <label>Department</label>
                            <input type="text" name="department" required value="<?php echo e($editing["Department"] ?? ""); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div>
                            <label>Level</label>
                            <input type="text" name="level" required value="<?php echo e($editing["Level"] ?? ""); ?>">
                        </div>
                        <?php if (!$editing): ?>
                            <div>
                                <label>Temporary Password</label>
                                <input type="password" name="password" minlength="8" required>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="row-actions">
                        <button type="submit" class="btn-primary"><?php echo $editing ? "Save Changes" : "Add Student"; ?></button>
                        <?php if ($editing): ?>
                            <a href="student.php" class="btn-small">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="admin-panel">
                <h2>All Students</h2>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Matricule</th>
                            <th>Department</th>
                            <th>Level</th>
                            <th>Joined</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students && $students->num_rows > 0): ?>
                            <?php while ($s = $students->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo e($s["fullName"]); ?></td>
                                    <td class="muted"><?php echo e($s["Email"]); ?></td>
                                    <td><?php echo e($s["matricule"]); ?></td>
                                    <td><?php echo e($s["Department"]); ?></td>
                                    <td><?php echo e($s["Level"]); ?></td>
                                    <td class="muted"><?php echo e(date("M j, Y", strtotime($s["CreatedAt"]))); ?></td>
                                    <td class="row-actions">
                                        <a href="student.php?edit=<?php echo (int) $s["UserID"]; ?>" class="btn-small">Edit</a>
                                        <form method="POST" onsubmit="return confirm('Delete this student? This also removes their login account.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $s["UserID"]; ?>">
                                            <button type="submit" class="btn-small btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7">No students yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>

    </div>

</div>

<script src="../js/admin.js"></script>

</body>

</html>