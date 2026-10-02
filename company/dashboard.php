<?php

session_start();
require_once "../db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Company") {
    header("Location: ../login.php?role=company");
    exit;
}

$companyName = $_SESSION["full_name"] ?? "Company";
$companyId = $_SESSION["profile_id"] ?? 0;

function count_rows(mysqli $conn, string $sql, int $param): int
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $param);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return (int) ($row[0] ?? 0);
}

$totalInternships = count_rows($conn, "SELECT COUNT(*) FROM internship WHERE CompanyID = ?", $companyId);
$activeInternships = count_rows($conn, "SELECT COUNT(*) FROM internship WHERE CompanyID = ? AND Status = 'Active'", $companyId);

$stmt = $conn->prepare("
    SELECT COUNT(*) FROM applications a
    JOIN internship i ON a.InternshipID = i.InternshipID
    WHERE i.CompanyID = ?
");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$totalApplications = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(*) FROM applications a
    JOIN internship i ON a.InternshipID = i.InternshipID
    WHERE i.CompanyID = ? AND a.Status = 'Pending'
");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$pendingApplications = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
$stmt->close();

$stmt = $conn->prepare("
    SELECT a.Status, a.ApplicationDate, s.fullName, i.Title
    FROM applications a
    JOIN internship i ON a.InternshipID = i.InternshipID
    JOIN student s ON a.StudentID = s.studentID
    WHERE i.CompanyID = ?
    ORDER BY a.ApplicationDate DESC
    LIMIT 5
");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$recentApplications = $stmt->get_result();
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
            <a href="internship.php"><i class='bx bx-briefcase'></i> My Internships</a>
            <a href="application.php"><i class='bx bx-file'></i> Applications</a>
            <a href="intern.php"><i class='bx bx-group'></i> My Interns</a>
            <a href="message.php"><i class='bx bx-envelope'></i> Messages</a>
            <span class="dash-nav-disabled"><i class='bx bx-user'></i> Profile <small>Coming soon</small></span>
        </nav>

        <a href="../logout.php" class="dash-logout"><i class='bx bx-log-out'></i> Logout</a>

    </aside>

    <div class="dash-main">

        <header class="dash-topbar">
            <button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle menu">
                <i class='bx bx-menu'></i>
            </button>
            <h1>My Dashboard</h1>
            <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($companyName); ?></div>
        </header>

        <main class="dash-content">

            <div class="stat-grid">

                <div class="stat-card">
                    <i class='bx bx-briefcase'></i>
                    <div><span><?php echo $totalInternships; ?></span><p>Internships Posted</p></div>
                </div>

                <div class="stat-card">
                    <i class='bx bx-check-circle'></i>
                    <div><span><?php echo $activeInternships; ?></span><p>Active</p></div>
                </div>

                <div class="stat-card">
                    <i class='bx bx-file'></i>
                    <div><span><?php echo $totalApplications; ?></span><p>Applications Received</p></div>
                </div>

                <div class="stat-card">
                    <i class='bx bx-hourglass'></i>
                    <div><span><?php echo $pendingApplications; ?></span><p>Pending Review</p></div>
                </div>

            </div>

            <div class="dash-panel">
                <h2>Recent Applications</h2>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Internship</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentApplications && $recentApplications->num_rows > 0): ?>
                            <?php while ($a = $recentApplications->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($a["fullName"]); ?></td>
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
                            <tr><td colspan="4">No applications yet.</td></tr>
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