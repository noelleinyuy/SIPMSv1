<?php

session_start();
require_once "../db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Supervisor") {
    header("Location: ../login.php?role=supervisor");
    exit;
}

$supervisorName = $_SESSION["full_name"] ?? "Supervisor";
$supervisorId = $_SESSION["profile_id"] ?? 0;

$stmt = $conn->prepare("
    SELECT a.AttendanceID, a.CheckInTime, a.CheckOutTime, s.fullName, s.matricule
    FROM attendance a
    JOIN student s ON a.StudentID = s.studentID
    JOIN internship i ON a.StudentID = i.StudentID
    WHERE i.SupervisorID = ?
    ORDER BY a.CheckInTime DESC
");
$stmt->bind_param("i", $supervisorId);
$stmt->execute();
$records = $stmt->get_result();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Attendance | SIPMS</title>

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
            <a href="attendance.php" class="active"><i class='bx bx-time-five'></i> Attendance</a>
            <a href="task.php"><i class='bx bx-list-check'></i> Tasks</a>
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
            <h1>Attendance</h1>
            <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($supervisorName); ?></div>
        </header>

        <main class="dash-content">

            <div class="dash-panel">
                <h2>Intern Attendance</h2>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Date</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($records && $records->num_rows > 0): ?>
                            <?php while ($r = $records->fetch_assoc()): ?>
                                <?php
                                $duration = "—";
                                if ($r["CheckInTime"] && $r["CheckOutTime"]) {
                                    $minutes = (strtotime($r["CheckOutTime"]) - strtotime($r["CheckInTime"])) / 60;
                                    $duration = floor($minutes / 60) . "h " . (int) ($minutes % 60) . "m";
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r["fullName"]); ?> <span class="muted">(<?php echo htmlspecialchars($r["matricule"]); ?>)</span></td>
                                    <td class="muted"><?php echo $r["CheckInTime"] ? htmlspecialchars(date("M j, Y", strtotime($r["CheckInTime"]))) : "—"; ?></td>
                                    <td class="muted"><?php echo $r["CheckInTime"] ? htmlspecialchars(date("g:ia", strtotime($r["CheckInTime"]))) : "—"; ?></td>
                                    <td class="muted">
                                        <?php if ($r["CheckOutTime"]): ?>
                                            <?php echo htmlspecialchars(date("g:ia", strtotime($r["CheckOutTime"]))); ?>
                                        <?php else: ?>
                                            <span class="badge badge-blue">In progress</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="muted"><?php echo $duration; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No attendance records yet.</td></tr>
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