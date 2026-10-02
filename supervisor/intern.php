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
    SELECT i.InternshipID, i.Title, i.Status, i.StartDate, i.EndDate,
           s.studentID, s.fullName, s.matricule, s.Department, s.Level,
           c.Name AS CompanyName
    FROM internship i
    JOIN student s ON i.StudentID = s.studentID
    LEFT JOIN company c ON i.CompanyID = c.CompanyID
    WHERE i.SupervisorID = ?
    ORDER BY s.fullName ASC
");
$stmt->bind_param("i", $supervisorId);
$stmt->execute();
$interns = $stmt->get_result();
$stmt->close();

// For the detail panel, if a specific student is being viewed.
$viewStudentId = (int) ($_GET["view"] ?? 0);
$detail = null;
$detailAttendance = null;
$detailTasks = null;
$detailReports = null;

if ($viewStudentId) {

    // Only allow viewing a student actually assigned to this supervisor.
    $stmt = $conn->prepare("
        SELECT i.Title, i.Status, c.Name AS CompanyName, s.fullName, s.matricule, s.Department, s.Level
        FROM internship i
        JOIN student s ON i.StudentID = s.studentID
        LEFT JOIN company c ON i.CompanyID = c.CompanyID
        WHERE i.SupervisorID = ? AND s.studentID = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $supervisorId, $viewStudentId);
    $stmt->execute();
    $detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($detail) {

        $stmt = $conn->prepare("SELECT CheckInTime, CheckOutTime FROM attendance WHERE StudentID = ? ORDER BY CheckInTime DESC LIMIT 5");
        $stmt->bind_param("i", $viewStudentId);
        $stmt->execute();
        $detailAttendance = $stmt->get_result();
        $stmt->close();

        $stmt = $conn->prepare("SELECT TaskTitle, Status, DeadLine FROM task WHERE StudentID = ? ORDER BY DeadLine ASC LIMIT 5");
        $stmt->bind_param("i", $viewStudentId);
        $stmt->execute();
        $detailTasks = $stmt->get_result();
        $stmt->close();

        $stmt = $conn->prepare("SELECT ReportDate, Status, Activities FROM dailyreport WHERE StudentID = ? ORDER BY ReportDate DESC LIMIT 5");
        $stmt->bind_param("i", $viewStudentId);
        $stmt->execute();
        $detailReports = $stmt->get_result();
        $stmt->close();

    }
}

$internshipBadge = ["Pending" => "badge-gray", "Active" => "badge-green", "Completed" => "badge-blue", "Cancelled" => "badge-danger"];
$taskBadge = ["Pending" => "badge-gray", "In Progress" => "badge-blue", "Completed" => "badge-green"];
$reportBadge = ["Draft" => "badge-gray", "Submitted" => "badge-blue", "Reviewed" => "badge-blue", "Approved" => "badge-green", "Rejected" => "badge-danger"];

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Interns | SIPMS</title>

    <link rel="stylesheet" href="../css/boxicons.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">

</head>

<body>

<div class="dash-shell">

    <aside class="dash-sidebar" id="dashSidebar">

        <div class="dash-logo"><i class='bx bxs-graduation'></i> SIPMS</div>

        <nav class="dash-nav">
            <a href="dashboard.php"><i class='bx bx-grid-alt'></i> Dashboard</a>
            <a href="intern.php" class="active"><i class='bx bx-group'></i> My Interns</a>
            <a href="report.php"><i class='bx bx-notepad'></i> Reports</a>
            <a href="attendance.php"><i class='bx bx-time-five'></i> Attendance</a>
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
            <h1>My Interns</h1>
            <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($supervisorName); ?></div>
        </header>

        <main class="dash-content">

            <?php if ($detail): ?>

                <div class="dash-panel">
                    <a href="intern.php" class="btn-small" style="margin-bottom: 1.4rem; display: inline-block;">← Back to all interns</a>

                    <h2><?php echo htmlspecialchars($detail["fullName"]); ?> <span class="muted">(<?php echo htmlspecialchars($detail["matricule"]); ?>)</span></h2>
                    <p class="muted" style="margin-bottom: 1.4rem;">
                        <?php echo htmlspecialchars($detail["Department"]); ?> — Level <?php echo htmlspecialchars($detail["Level"]); ?>
                        &nbsp;•&nbsp;
                        <?php echo htmlspecialchars($detail["Title"]); ?> at <?php echo htmlspecialchars($detail["CompanyName"] ?? "Unknown company"); ?>
                        &nbsp;
                        <span class="badge <?php echo $internshipBadge[$detail["Status"]] ?? "badge-gray"; ?>"><?php echo htmlspecialchars($detail["Status"]); ?></span>
                    </p>
                </div>

                <div class="dash-panel">
                    <h2>Recent Attendance</h2>
                    <table class="dash-table">
                        <thead><tr><th>Date</th><th>Check-in</th><th>Check-out</th></tr></thead>
                        <tbody>
                            <?php if ($detailAttendance && $detailAttendance->num_rows > 0): ?>
                                <?php while ($a = $detailAttendance->fetch_assoc()): ?>
                                    <tr>
                                        <td class="muted"><?php echo $a["CheckInTime"] ? htmlspecialchars(date("M j, Y", strtotime($a["CheckInTime"]))) : "—"; ?></td>
                                        <td class="muted"><?php echo $a["CheckInTime"] ? htmlspecialchars(date("g:ia", strtotime($a["CheckInTime"]))) : "—"; ?></td>
                                        <td class="muted"><?php echo $a["CheckOutTime"] ? htmlspecialchars(date("g:ia", strtotime($a["CheckOutTime"]))) : "In progress"; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3">No attendance records yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="dash-panel">
                    <h2>Tasks</h2>
                    <table class="dash-table">
                        <thead><tr><th>Task</th><th>Deadline</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if ($detailTasks && $detailTasks->num_rows > 0): ?>
                                <?php while ($t = $detailTasks->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($t["TaskTitle"]); ?></td>
                                        <td class="muted"><?php echo $t["DeadLine"] ? htmlspecialchars(date("M j, Y", strtotime($t["DeadLine"]))) : "—"; ?></td>
                                        <td><span class="badge <?php echo $taskBadge[$t["Status"]] ?? "badge-gray"; ?>"><?php echo htmlspecialchars($t["Status"]); ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3">No tasks assigned yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="dash-panel">
                    <h2>Daily Reports</h2>
                    <table class="dash-table">
                        <thead><tr><th>Date</th><th>Activities</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if ($detailReports && $detailReports->num_rows > 0): ?>
                                <?php while ($r = $detailReports->fetch_assoc()): ?>
                                    <tr>
                                        <td class="muted"><?php echo htmlspecialchars(date("M j, Y", strtotime($r["ReportDate"]))); ?></td>
                                        <td class="truncate"><?php echo htmlspecialchars($r["Activities"]); ?></td>
                                        <td><span class="badge <?php echo $reportBadge[$r["Status"]] ?? "badge-gray"; ?>"><?php echo htmlspecialchars($r["Status"]); ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3">No reports submitted yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <div class="dash-panel">
                    <h2>Assigned Interns</h2>
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Internship</th>
                                <th>Company</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($interns && $interns->num_rows > 0): ?>
                                <?php while ($i = $interns->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($i["fullName"]); ?> <span class="muted">(<?php echo htmlspecialchars($i["matricule"]); ?>)</span></td>
                                        <td><?php echo htmlspecialchars($i["Title"]); ?></td>
                                        <td class="muted"><?php echo htmlspecialchars($i["CompanyName"] ?? "—"); ?></td>
                                        <td><span class="badge <?php echo $internshipBadge[$i["Status"]] ?? "badge-gray"; ?>"><?php echo htmlspecialchars($i["Status"]); ?></span></td>
                                        <td><a href="intern.php?view=<?php echo (int) $i["studentID"]; ?>" class="btn-small">View Details</a></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5">No interns assigned to you yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </main>

    </div>

</div>

<div class="dash-sidebar-backdrop"></div>
<script src="../js/dashboarsd.js"></script>

</body>

</html>