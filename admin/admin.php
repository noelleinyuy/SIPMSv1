<?php

session_start();
require_once "../db.php";

// A user must not be able to reach this page by guessing the URL.
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Admin") {
    header("Location: ../login.php?role=admin");
    exit;
}

$adminName = $_SESSION["full_name"] ?? "Admin";

function count_rows(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_row();
    return (int) ($row[0] ?? 0);
}

$totalStudents = count_rows($conn, "SELECT COUNT(*) FROM student");
$totalCompanies = count_rows($conn, "SELECT COUNT(*) FROM company");
$totalSupervisors = count_rows($conn, "SELECT COUNT(*) FROM supervisor");
$totalApplications = count_rows($conn, "SELECT COUNT(*) FROM applications");
$totalAttendance = count_rows($conn, "SELECT COUNT(*) FROM attendance");
$totalOpenTasks = count_rows($conn, "SELECT COUNT(*) FROM task WHERE Status <> 'Completed'");
$totalUnreadMessages = count_rows($conn, "SELECT COUNT(*) FROM notification WHERE Status = 'Unread'");

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | SIPMS</title>

    <link rel="stylesheet" href="../css/boxicons.min.css">
    <link rel="stylesheet" href="../css/admin.css">

</head>

<body>

    <div class="admin-shell">

        <aside class="admin-sidebar" id="adminSidebar">

            <div class="admin-logo"><i class='bx bxs-graduation'></i> SIPMS</div>

            <nav class="admin-nav">
                <a href="admin.php" class="active"><i class='bx bx-grid-alt'></i> Dashboard</a>
                <a href="student.php"><i class='bx bx-user'></i> Students</a>
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
                <h1>Dashboard</h1>
                <div class="admin-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($adminName); ?></div>
            </header>

            <main class="admin-content">

                <div class="stat-grid">

                    <div class="stat-card">
                        <i class='bx bx-user'></i>
                        <div><span><?php echo $totalStudents; ?></span>
                            <p>Students</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <i class='bx bx-building'></i>
                        <div><span><?php echo $totalCompanies; ?></span>
                            <p>Companies</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <i class='bx bx-chalkboard'></i>
                        <div><span><?php echo $totalSupervisors; ?></span>
                            <p>Supervisors</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <i class='bx bx-file'></i>
                        <div><span><?php echo $totalApplications; ?></span>
                            <p>Applications</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <i class='bx bx-time-five'></i>
                        <div><span><?php echo $totalAttendance; ?></span>
                            <p>Attendance Records</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <i class='bx bx-list-check'></i>
                        <div><span><?php echo $totalOpenTasks; ?></span>
                            <p>Open Tasks</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <i class='bx bx-envelope'></i>
                        <div><span><?php echo $totalUnreadMessages; ?></span>
                            <p>Unread Messages</p>
                        </div>
                    </div>

                </div>

                <div class="admin-panel">
                    <p class="muted">
                        Live overview of the current internship system status across students, companies, applications, and internal records.
                    </p>
                </div>

            </main>

        </div>

    </div>

    <script src="../js/admin.js"></script>

</body>

</html>