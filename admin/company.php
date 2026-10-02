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

                $stmt = $conn->prepare("DELETE FROM company WHERE UserID = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM users WHERE UserID = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                admin_flash("success", "Company deleted.");

            } catch (\mysqli_sql_exception $e) {

                $conn->rollback();
                admin_flash("error", "Could not delete company.");

            }
        }

        header("Location: company.php");
        exit;

    }

    if ($action === "add" || $action === "update") {

        $userId = (int) ($_POST["user_id"] ?? 0);
        $name = trim($_POST["name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $address = trim($_POST["address"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $companyType = trim($_POST["companyType"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($name === "" || $email === "" || $phone === "" || $address === "" || $description === "" || $companyType === "") {

            admin_flash("error", "Please fill in all fields.");

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            admin_flash("error", "Please enter a valid email address.");

        } elseif ($action === "add" && strlen($password) < 8) {

            admin_flash("error", "Temporary password must be at least 8 characters.");

        } else {

            $stmt = $conn->prepare("SELECT UserID FROM users WHERE Email = ? AND UserID != ?");
            $stmt->bind_param("si", $email, $userId);
            $stmt->execute();
            $emailTaken = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($emailTaken) {

                admin_flash("error", "That email is already in use by another account.");

            } elseif ($action === "add") {

                $conn->begin_transaction();

                try {

                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("INSERT INTO users (Email, Password, Role) VALUES (?, ?, 'Company')");
                    $stmt->bind_param("ss", $email, $passwordHash);
                    $stmt->execute();
                    $newUserId = $stmt->insert_id;
                    $stmt->close();

                    $stmt = $conn->prepare("
                        INSERT INTO company (UserID, Name, Address, Description, Type, Phone)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("isssss", $newUserId, $name, $address, $description, $companyType, $phone);
                    $stmt->execute();
                    $stmt->close();

                    $conn->commit();
                    admin_flash("success", "Company added.");

                } catch (\mysqli_sql_exception $e) {

                    $conn->rollback();
                    admin_flash("error", "Could not add company.");

                }

            } else {

                $conn->begin_transaction();

                try {

                    $stmt = $conn->prepare("UPDATE users SET Email = ? WHERE UserID = ?");
                    $stmt->bind_param("si", $email, $userId);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare("
                        UPDATE company SET Name = ?, Address = ?, Description = ?, Type = ?, Phone = ?
                        WHERE UserID = ?
                    ");
                    $stmt->bind_param("sssssi", $name, $address, $description, $companyType, $phone, $userId);
                    $stmt->execute();
                    $stmt->close();

                    $conn->commit();
                    admin_flash("success", "Company updated.");

                } catch (\mysqli_sql_exception $e) {

                    $conn->rollback();
                    admin_flash("error", "Could not update company.");

                }
            }
        }

        header("Location: company.php");
        exit;
    }
}

$editId = (int) ($_GET["edit"] ?? 0);
$editing = null;

if ($editId) {
    $stmt = $conn->prepare("
        SELECT c.UserID, c.Name, c.Address, c.Description, c.Type, c.Phone, u.Email
        FROM company c
        JOIN users u ON c.UserID = u.UserID
        WHERE c.UserID = ?
    ");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$companies = $conn->query("
    SELECT c.CompanyID, c.UserID, c.Name, c.Address, c.Description, c.Type, c.Phone, u.Email, u.CreatedAt
    FROM company c
    JOIN users u ON c.UserID = u.UserID
    ORDER BY c.CompanyID DESC
");

$flash = admin_get_flash();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Companies | SIPMS Admin</title>

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
            <a href="company.php" class="active"><i class='bx bx-building'></i> Companies</a>
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
            <h1>Companies</h1>
            <div class="admin-user"><i class='bx bx-user-circle'></i> <?php echo e($adminName); ?></div>
        </header>

        <main class="admin-content">

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?php echo e($flash["type"]); ?>"><?php echo e($flash["message"]); ?></div>
            <?php endif; ?>

            <div class="admin-panel">
                <h2><?php echo $editing ? "Edit Company" : "Add a Company"; ?></h2>

                <form method="POST" class="admin-form">
                    <input type="hidden" name="action" value="<?php echo $editing ? "update" : "add"; ?>">
                    <?php if ($editing): ?>
                        <input type="hidden" name="user_id" value="<?php echo (int) $editing["UserID"]; ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div>
                            <label>Company Name</label>
                            <input type="text" name="name" required value="<?php echo e($editing["Name"] ?? ""); ?>">
                        </div>
                        <div>
                            <label>Email</label>
                            <input type="email" name="email" required value="<?php echo e($editing["Email"] ?? ""); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div>
                            <label>Phone</label>
                            <input type="tel" name="phone" required value="<?php echo e($editing["Phone"] ?? ""); ?>">
                        </div>
                        <div>
                            <label>Type</label>
                            <input type="text" name="companyType" placeholder="e.g. Technology, Finance" required value="<?php echo e($editing["Type"] ?? ""); ?>">
                        </div>
                    </div>

                    <label>Address</label>
                    <input type="text" name="address" required value="<?php echo e($editing["Address"] ?? ""); ?>">

                    <label>Description</label>
                    <textarea name="description" rows="3" required><?php echo e($editing["Description"] ?? ""); ?></textarea>

                    <?php if (!$editing): ?>
                        <label>Temporary Password</label>
                        <input type="password" name="password" minlength="8" required>
                    <?php endif; ?>

                    <div class="row-actions">
                        <button type="submit" class="btn-primary"><?php echo $editing ? "Save Changes" : "Add Company"; ?></button>
                        <?php if ($editing): ?>
                            <a href="company.php" class="btn-small">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="admin-panel">
                <h2>All Companies</h2>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Type</th>
                            <th>Joined</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($companies && $companies->num_rows > 0): ?>
                            <?php while ($c = $companies->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo e($c["Name"]); ?></td>
                                    <td class="muted"><?php echo e($c["Email"]); ?></td>
                                    <td><?php echo e($c["Phone"]); ?></td>
                                    <td><?php echo e($c["Type"]); ?></td>
                                    <td class="muted"><?php echo e(date("M j, Y", strtotime($c["CreatedAt"]))); ?></td>
                                    <td class="row-actions">
                                        <a href="company.php?edit=<?php echo (int) $c["UserID"]; ?>" class="btn-small">Edit</a>
                                        <form method="POST" onsubmit="return confirm('Delete this company? This also removes their login account.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $c["UserID"]; ?>">
                                            <button type="submit" class="btn-small btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No companies yet.</td></tr>
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