<?php

session_start();
require_once "../db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Student") {
    header("Location: ../login.php?role=student");
    exit;
}

$studentName = $_SESSION["full_name"] ?? "Student";
$studentId = $_SESSION["profile_id"] ?? 0;

$statusOptions = ["Pending", "In Progress", "Completed"];

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

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $taskId = (int) ($_POST["task_id"] ?? 0);
    $status = $_POST["status"] ?? "";

    if (!$taskId || !in_array($status, $statusOptions, true)) {

        flash("error", "Invalid status update.");
    } else {

        // Only your own assigned task can be updated.
        $stmt = $conn->prepare("UPDATE task SET Status = ? WHERE TaskID = ? AND StudentID = ?");
        $stmt->bind_param("sii", $status, $taskId, $studentId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        flash($affected > 0 ? "success" : "error", $affected > 0 ? "Task updated." : "Could not update this task.");
    }

    header("Location: task.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT TaskID, TaskTitle, Description, AssignedDate, DeadLine, Status
    FROM task
    WHERE StudentID = ?
    ORDER BY DeadLine ASC
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$tasks = $stmt->get_result();
$stmt->close();

$statusBadge = [
    "Pending" => "badge-gray",
    "In Progress" => "badge-blue",
    "Completed" => "badge-green",
];

$today = date("Y-m-d");
$flash = get_flash();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Tasks | SIPMS</title>

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
                <a href="task.php" class="active"><i class='bx bx-list-check'></i> Tasks</a>
                <a href="message.php"><i class='bx bx-envelope'></i> Messages</a>
                <a href="profile.php"><i class='bx bx-user'></i> Profile</a>
            </nav>

            <a href="../logout.php" class="dash-logout"><i class='bx bx-log-out'></i> Logout</a>

        </aside>

        <div class="dash-main">

            <header class="dash-topbar">
                <button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle menu">
                    <i class='bx bx-menu'></i>
                </button>
                <h1>My Tasks</h1>
                <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($studentName); ?></div>
            </header>

            <main class="dash-content">

                <?php if ($flash): ?>
                    <div class="admin-alert admin-alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
                <?php endif; ?>

                <div class="dash-panel">
                    <h2>Assigned Tasks</h2>
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($tasks && $tasks->num_rows > 0): ?>
                                <?php while ($t = $tasks->fetch_assoc()): ?>
                                    <?php $overdue = $t["DeadLine"] && $t["DeadLine"] < $today && $t["Status"] !== "Completed"; ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($t["TaskTitle"]); ?></strong>
                                            <?php if ($t["Description"]): ?>
                                                <br><span class="muted"><?php echo htmlspecialchars($t["Description"]); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($t["DeadLine"]): ?>
                                                <span class="muted">
                                                    <?php echo htmlspecialchars(date("M j, Y", strtotime($t["DeadLine"]))); ?>
                                                    <?php if ($overdue): ?>
                                                        <span class="badge badge-danger">Overdue</span>
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $statusBadge[$t["Status"]] ?? "badge-gray"; ?>">
                                                <?php echo htmlspecialchars($t["Status"]); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" class="row-actions">
                                                <input type="hidden" name="task_id" value="<?php echo (int) $t["TaskID"]; ?>">
                                                <select name="status" onchange="this.form.submit()">
                                                    <?php foreach ($statusOptions as $option): ?>
                                                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $t["Status"] === $option ? "selected" : ""; ?>>
                                                            <?php echo htmlspecialchars($option); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">No tasks assigned yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </main>

        </div>

    </div>

    <div class="dash-sidebar-backdrop"></div>
    <script src="../js/dashboarsd.js"></script>

</body>

</html>