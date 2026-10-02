<?php

session_start();
require_once "../db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Student") {
    header("Location: ../login.php?role=student");
    exit;
}

$studentName = $_SESSION["full_name"] ?? "Student";
$studentId = $_SESSION["profile_id"] ?? 0;

function count_rows(mysqli $conn, string $sql, int $param): int
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $param);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return (int) ($row[0] ?? 0);
}

$totalApplications = count_rows($conn, "SELECT COUNT(*) FROM applications WHERE StudentID = ?", $studentId);
$pendingApplications = count_rows($conn, "SELECT COUNT(*) FROM applications WHERE StudentID = ? AND Status = 'Pending'", $studentId);
$totalTasks = count_rows($conn, "SELECT COUNT(*) FROM task WHERE StudentID = ?", $studentId);

$stmt = $conn->prepare("SELECT COUNT(*) FROM notification WHERE RecipientRole = 'Student' AND RecipientID = ? AND Status = 'Unread'");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$unreadMessages = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
$stmt->close();

$stmt = $conn->prepare("
    SELECT i.Title, i.Status, c.Name AS CompanyName
    FROM internship i
    LEFT JOIN company c ON i.CompanyID = c.CompanyID
    WHERE i.StudentID = ?
    LIMIT 1
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$currentInternship = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("
    SELECT a.Status, a.ApplicationDate, i.Title
    FROM applications a
    JOIN internship i ON a.InternshipID = i.InternshipID
    WHERE a.StudentID = ?
    ORDER BY a.ApplicationDate DESC
    LIMIT 5
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$recentApplications = $stmt->get_result();
$stmt->close();

$stmt = $conn->prepare("
    SELECT TaskTitle, DeadLine
    FROM task
    WHERE StudentID = ?
    ORDER BY AssignedDate DESC
    LIMIT 5
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$recentTasks = $stmt->get_result();
$stmt->close();

$statusBadge = [
    "Pending" => "badge-gray",
    "Shortlisted" => "badge-blue",
    "Accepted" => "badge-green",
    "Rejected" => "badge-danger",
    "Withdrawn" => "badge-gray",
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
            <a href="application.php"><i class='bx bx-file'></i> My Applications</a>
            <a href="report.php"><i class='bx bx-notepad'></i> Submit Report</a>
            <a href="browse.php"><i class='bx bx-search'></i> Browse Internships</a>
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
            <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($studentName); ?></div>
        </header>

        <main class="dash-content">

            <div class="stat-grid">

                <div class="stat-card">
                    <i class='bx bx-file'></i>
                    <div><span><?php echo $totalApplications; ?></span><p>Applications</p></div>
                </div>

                <div class="stat-card">
                    <i class='bx bx-hourglass'></i>
                    <div><span><?php echo $pendingApplications; ?></span><p>Pending</p></div>
                </div>

                <div class="stat-card">
                    <i class='bx bx-list-check'></i>
                    <div><span><?php echo $totalTasks; ?></span><p>My Tasks</p></div>
                </div>

                <div class="stat-card">
                    <i class='bx bx-envelope'></i>
                    <div><span><?php echo $unreadMessages; ?></span><p>Unread Messages</p></div>
                </div>

            </div>

            <div class="dash-panel">
                <h2>Current Internship</h2>
                <?php if ($currentInternship): ?>
                    <p><strong><?php echo htmlspecialchars($currentInternship["Title"]); ?></strong> at <?php echo htmlspecialchars($currentInternship["CompanyName"] ?? "Unknown company"); ?></p>
                    <p class="muted">Status: <?php echo htmlspecialchars($currentInternship["Status"]); ?></p>
                <?php else: ?>
                    <p class="muted">You don't have an active internship placement yet.</p>
                <?php endif; ?>
            </div>

            <div class="dash-panel">
                <h2>Recent Applications</h2>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Internship</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentApplications && $recentApplications->num_rows > 0): ?>
                            <?php while ($a = $recentApplications->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($a["Title"]); ?></td>
                                    <td class="muted"><?php echo htmlspecialchars(date("M j, Y", strtotime($a["ApplicationDate"]))); ?></td>
                                    <td>
                                        <span class="badge <?php echo $statusBadge[$a["Status"]] ?? "badge-gray"; ?>">
                                            <?php echo htmlspecialchars($a["Status"]); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3">No applications yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="dash-panel">
                <h2>My Tasks</h2>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Deadline</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentTasks && $recentTasks->num_rows > 0): ?>
                            <?php while ($t = $recentTasks->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($t["TaskTitle"]); ?></td>
                                    <td class="muted"><?php echo $t["DeadLine"] ? htmlspecialchars(date("M j, Y", strtotime($t["DeadLine"]))) : "—"; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="2">No tasks assigned yet.</td></tr>
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