<?php

require_once "helpers.php";
require_once "../db.php";
require_admin_login();

$adminName = $_SESSION["full_name"] ?? "Admin";
$adminId = $_SESSION["profile_id"] ?? null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";
    $taskId = (int) ($_POST["task_id"] ?? 0);

    if ($action === "delete" && $taskId) {

        $stmt = $conn->prepare("DELETE FROM task WHERE TaskID = ?");
        $stmt->bind_param("i", $taskId);
        $stmt->execute();
        $stmt->close();

        admin_flash("success", "Task deleted.");
        header("Location: task.php");
        exit;

    }

    if ($action === "add" || $action === "update") {

        $title = trim($_POST["taskTitle"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $studentId = (int) ($_POST["studentId"] ?? 0);
        $studentId = $studentId ?: null;
        $deadline = trim($_POST["deadline"] ?? "");
        $deadline = $deadline !== "" ? $deadline : null;

        if ($title === "") {

            admin_flash("error", "Please enter a task title.");

        } elseif ($action === "add") {

            $stmt = $conn->prepare("
                INSERT INTO task (TaskTitle, Description, StudentID, DeadLine, AdminID)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssisi", $title, $description, $studentId, $deadline, $adminId);
            $stmt->execute();
            $stmt->close();

            admin_flash("success", "Task added.");

        } else {

            $stmt = $conn->prepare("
                UPDATE task
                SET TaskTitle = ?, Description = ?, StudentID = ?, DeadLine = ?
                WHERE TaskID = ?
            ");
            $stmt->bind_param("ssisi", $title, $description, $studentId, $deadline, $taskId);
            $stmt->execute();
            $stmt->close();

            admin_flash("success", "Task updated.");

        }

        header("Location: task.php");
        exit;

    }
}

$students = $conn->query("SELECT studentID, fullName, matricule FROM student ORDER BY fullName ASC");
$studentOptions = $students ? $students->fetch_all(MYSQLI_ASSOC) : [];

$editId = (int) ($_GET["edit"] ?? 0);
$editing = null;

if ($editId) {
    $stmt = $conn->prepare("SELECT * FROM task WHERE TaskID = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$tasks = $conn->query("
    SELECT t.TaskID, t.TaskTitle, t.Description, t.Status, t.AssignedDate, t.DeadLine, s.fullName, s.matricule
    FROM task t
    LEFT JOIN student s ON t.StudentID = s.studentID
    ORDER BY t.AssignedDate DESC
");

$today = date("Y-m-d");
$flash = admin_get_flash();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tasks | SIPMS Admin</title>

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
            <a href="task.php" class="active"><i class='bx bx-list-check'></i> Tasks</a>
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
            <h1>Tasks</h1>
            <div class="admin-user"><i class='bx bx-user-circle'></i> <?php echo e($adminName); ?></div>
        </header>

        <main class="admin-content">

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?php echo e($flash["type"]); ?>"><?php echo e($flash["message"]); ?></div>
            <?php endif; ?>

            <div class="admin-panel">
                <h2><?php echo $editing ? "Edit Task" : "Add a Task"; ?></h2>

                <form method="POST" class="admin-form">
                    <input type="hidden" name="action" value="<?php echo $editing ? "update" : "add"; ?>">
                    <?php if ($editing): ?>
                        <input type="hidden" name="task_id" value="<?php echo (int) $editing["TaskID"]; ?>">
                    <?php endif; ?>

                    <label>Task Title</label>
                    <input type="text" name="taskTitle" required value="<?php echo e($editing["TaskTitle"] ?? ""); ?>">

                    <label>Description</label>
                    <textarea name="description" rows="3"><?php echo e($editing["Description"] ?? ""); ?></textarea>

                    <div class="form-row">
                        <div>
                            <label>Assign to Student (optional)</label>
                            <select name="studentId">
                                <option value="">Unassigned</option>
                                <?php foreach ($studentOptions as $s): ?>
                                    <option value="<?php echo (int) $s["studentID"]; ?>" <?php echo (isset($editing["StudentID"]) && (int) $editing["StudentID"] === (int) $s["studentID"]) ? "selected" : ""; ?>>
                                        <?php echo e($s["fullName"]); ?> (<?php echo e($s["matricule"]); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Deadline (optional)</label>
                            <input type="date" name="deadline" value="<?php echo e($editing["DeadLine"] ?? ""); ?>">
                        </div>
                    </div>

                    <div class="row-actions">
                        <button type="submit" class="btn-primary"><?php echo $editing ? "Save Changes" : "Add Task"; ?></button>
                        <?php if ($editing): ?>
                            <a href="task.php" class="btn-small">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="admin-panel">
                <h2>All Tasks</h2>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                            <th>Assigned Date</th>
                            <th>Deadline</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($tasks && $tasks->num_rows > 0): ?>
                            <?php while ($t = $tasks->fetch_assoc()): ?>
                                <?php $overdue = $t["DeadLine"] && $t["DeadLine"] < $today; ?>
                                <tr>
                                    <td><?php echo e($t["TaskTitle"]); ?></td>
                                    <td class="muted"><?php echo $t["fullName"] ? e($t["fullName"]) . " (" . e($t["matricule"]) . ")" : "Unassigned"; ?></td>
                                    <td>
                                        <span class="badge <?php echo $t["Status"] === "Completed" ? "badge-green" : ($t["Status"] === "In Progress" ? "badge-blue" : "badge-gray"); ?>">
                                            <?php echo e($t["Status"]); ?>
                                        </span>
                                    </td>
                                    <td class="muted"><?php echo $t["AssignedDate"] ? e(date("M j, Y", strtotime($t["AssignedDate"]))) : "—"; ?></td>
                                    <td>
                                        <?php if ($t["DeadLine"]): ?>
                                            <span class="badge <?php echo $overdue ? "badge-danger" : "badge-gray"; ?>">
                                                <?php echo e(date("M j, Y", strtotime($t["DeadLine"]))); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="row-actions">
                                        <a href="task.php?edit=<?php echo (int) $t["TaskID"]; ?>" class="btn-small">Edit</a>
                                        <form method="POST" onsubmit="return confirm('Delete this task?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="task_id" value="<?php echo (int) $t["TaskID"]; ?>">
                                            <button type="submit" class="btn-small btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No tasks yet.</td></tr>
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