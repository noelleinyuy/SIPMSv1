<?php

session_start();
require_once "../db.php";

if (!isset($_SESSION["role"]) || !in_array($_SESSION["role"] ?? "", ["Supervisor", "Lecturer"], true)) {
    header("Location: ../login.php?role=supervisor");
    exit;
}

$supervisorName = $_SESSION["full_name"] ?? "Supervisor";
$supervisorId = $_SESSION["profile_id"] ?? 0;

function count_scoped(mysqli $conn, string $sql, int $param): int
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $param);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return (int) ($row[0] ?? 0);
}

$totalInternships = count_scoped($conn, "SELECT COUNT(*) FROM internship WHERE SupervisorID = ?", $supervisorId);
$activeInterns = count_scoped($conn, "SELECT COUNT(*) FROM internship WHERE SupervisorID = ? AND StudentID IS NOT NULL", $supervisorId);

$pendingReports = count_scoped($conn, "
    SELECT COUNT(*) FROM dailyreport d
    JOIN internship i ON d.StudentID = i.StudentID
    WHERE i.SupervisorID = ? AND d.Status = 'Submitted'
", $supervisorId);

$openTasks = count_scoped($conn, "
    SELECT COUNT(*) FROM task t
    JOIN internship i ON t.StudentID = i.StudentID
    WHERE i.SupervisorID = ? AND t.Status <> 'Completed'
", $supervisorId);

$stmt = $conn->prepare("
    SELECT i.InternshipID, i.Title, i.Status, s.fullName, s.matricule, c.Name AS CompanyName
    FROM internship i
    JOIN student s ON i.StudentID = s.studentID
    LEFT JOIN company c ON i.CompanyID = c.CompanyID
    WHERE i.SupervisorID = ?
    ORDER BY i.InternshipID DESC
");
$stmt->bind_param("i", $supervisorId);
$stmt->execute();
$interns = $stmt->get_result();
$stmt->close();

$stmt = $conn->prepare("
    SELECT d.ReportDate, d.Status, s.fullName
    FROM dailyreport d
    JOIN internship i ON d.StudentID = i.StudentID
    JOIN student s ON d.StudentID = s.studentID
    WHERE i.SupervisorID = ? AND d.Status <> 'Draft'
    ORDER BY d.ReportDate DESC
    LIMIT 5
");
$stmt->bind_param("i", $supervisorId);
$stmt->execute();
$recentReports = $stmt->get_result();
$stmt->close();

$internshipBadge = [
    "Pending" => "badge-gray",
    "Active" => "badge-green",
    "Completed" => "badge-blue",
    "Cancelled" => "badge-danger",
];

$reportBadge = [
    "Draft" => "badge-gray",
    "Submitted" => "badge-blue",
    "Reviewed" => "badge-blue",
    "Approved" => "badge-green",
    "Rejected" => "badge-danger",
];

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Dashboard | SIPMS</title>

    <link rel="stylesheet" href="../css/boxicons.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">

</head>

<body>

<div class="dash-shell">

    <aside class="dash-sidebar" id="dashSidebar">

        <div class="dash-logo"><i class='bx bxs-graduation'></i> SIPMS</div>

        <nav class="dash-nav">
            <a href="dashboard.php" class="active"><i class='bx bx-grid-alt'></i> Dashboard</a>
            <a href="intern.php"><i class='bx bx-group'></i> My Interns</a>
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
            <h1>My Dashboard</h1>
            <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($supervisorName); ?></div>
        </header>

        <main class="dash-content">

            <div class="stat-grid">

                <div class="stat-card">
                    <i class='bx bx-briefcase'></i>
                    <div><span><?php echo $totalInternships; ?></span><p>Assigned Internships</p></div>
                </div>

                <div class="stat-card">
                    <i class='bx bx-group'></i>
                    <div><span><?php echo $activeInterns; ?></span><p>Placed Interns</p></div>
                </div>

                <div class="stat-card">
                    <i class='bx bx-notepad'></i>
                    <div><span><?php echo $pendingReports; ?></span><p>Reports Awaiting Review</p></div>
                </div>

                <div class="stat-card">
                    <i class='bx bx-list-check'></i>
                    <div><span><?php echo $openTasks; ?></span><p>Open Tasks</p></div>
                </div>

            </div>

            <div class="dash-panel">
                <h2>My Interns</h2>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Internship</th>
                            <th>Company</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($interns && $interns->num_rows > 0): ?>
                            <?php while ($i = $interns->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($i["fullName"]); ?> <span class="muted">(<?php echo htmlspecialchars($i["matricule"]); ?>)</span></td>
                                    <td><?php echo htmlspecialchars($i["Title"]); ?></td>
                                    <td class="muted"><?php echo htmlspecialchars($i["CompanyName"] ?? "—"); ?></td>
                                    <td>
                                        <span class="badge <?php echo $internshipBadge[$i["Status"]] ?? "badge-gray"; ?>">
                                            <?php echo htmlspecialchars($i["Status"]); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4">No interns assigned to you yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="dash-panel">
                <h2>Recent Reports</h2>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentReports && $recentReports->num_rows > 0): ?>
                            <?php while ($r = $recentReports->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r["fullName"]); ?></td>
                                    <td class="muted"><?php echo htmlspecialchars(date("M j, Y", strtotime($r["ReportDate"]))); ?></td>
                                    <td>
                                        <span class="badge <?php echo $reportBadge[$r["Status"]] ?? "badge-gray"; ?>">
                                            <?php echo htmlspecialchars($r["Status"]); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3">No reports submitted yet.</td></tr>
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
