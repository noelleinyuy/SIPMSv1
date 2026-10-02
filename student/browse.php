<?php

session_start();
require_once "../db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Student") {
    header("Location: ../login.php?role=student");
    exit;
}

$studentName = $_SESSION["full_name"] ?? "Student";
$studentId = $_SESSION["profile_id"] ?? 0;

$stmt = $conn->prepare("
    SELECT
        i.InternshipID, i.Title, i.Category, i.Industry, i.Location, i.Skills,
        i.Duration, i.ApplicationDeadline, i.Requirements, i.Description,
        i.StartDate, i.EndDate,
        c.Name AS CompanyName
    FROM internship i
    LEFT JOIN company c ON i.CompanyID = c.CompanyID
    WHERE i.Status IN ('Pending', 'Active')
      AND (i.ApplicationDeadline IS NULL OR i.ApplicationDeadline >= CURDATE())
    ORDER BY i.ApplicationDeadline ASC
");
$stmt->execute();
$internships = $stmt->get_result();
$stmt->close();

$stmt = $conn->prepare("SELECT InternshipID FROM applications WHERE StudentID = ?");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$appliedIds = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), "InternshipID");
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Browse Internships | SIPMS</title>

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
            <a href="browse.php" class="active"><i class='bx bx-search'></i> Browse Internships</a>
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
            <h1>Browse Internships</h1>
            <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($studentName); ?></div>
        </header>

        <main class="dash-content">

            <?php if (!$internships || $internships->num_rows === 0): ?>
                <div class="dash-panel">
                    <p class="muted">No open internships available right now — check back later.</p>
                </div>
            <?php else: ?>
                <?php while ($i = $internships->fetch_assoc()): ?>
                    <?php $alreadyApplied = in_array($i["InternshipID"], $appliedIds); ?>
                    <div class="dash-panel">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1.4rem; flex-wrap: wrap;">
                            <div>
                                <h2 style="margin-bottom: 0.3rem;"><?php echo htmlspecialchars($i["Title"]); ?></h2>
                                <p class="muted"><?php echo htmlspecialchars($i["CompanyName"] ?? "Unknown company"); ?></p>
                            </div>
                            <div>
                                <?php if ($alreadyApplied): ?>
                                    <span class="badge badge-blue">Already Applied</span>
                                <?php else: ?>
                                    <a href="application.php?apply=<?php echo (int) $i["InternshipID"]; ?>#apply" class="btn-primary">Apply Now</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-row" style="margin: 1.4rem 0;">
                            <div>
                                <p class="muted">Location</p>
                                <p><?php echo htmlspecialchars($i["Location"] ?: "Not specified"); ?></p>
                            </div>
                            <div>
                                <p class="muted">Duration</p>
                                <p><?php echo htmlspecialchars($i["Duration"] ?: "Not specified"); ?></p>
                            </div>
                        </div>

                        <div class="form-row" style="margin-bottom: 1.4rem;">
                            <div>
                                <p class="muted">Category / Industry</p>
                                <p><?php echo htmlspecialchars(trim(($i["Category"] ?: "") . ($i["Category"] && $i["Industry"] ? " / " : "") . ($i["Industry"] ?: "")) ?: "Not specified"); ?></p>
                            </div>
                            <div>
                                <p class="muted">Application Deadline</p>
                                <p><?php echo $i["ApplicationDeadline"] ? htmlspecialchars(date("M j, Y", strtotime($i["ApplicationDeadline"]))) : "No deadline set"; ?></p>
                            </div>
                        </div>

                        <?php if ($i["Skills"]): ?>
                            <p class="muted">Skills</p>
                            <p style="margin-bottom: 1.2rem;"><?php echo htmlspecialchars($i["Skills"]); ?></p>
                        <?php endif; ?>

                        <?php if ($i["Description"]): ?>
                            <p class="muted">Description</p>
                            <p style="margin-bottom: 1.2rem;"><?php echo nl2br(htmlspecialchars($i["Description"])); ?></p>
                        <?php endif; ?>

                        <?php if ($i["Requirements"]): ?>
                            <p class="muted">Requirements</p>
                            <p><?php echo nl2br(htmlspecialchars($i["Requirements"])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>

        </main>

    </div>

</div>

<div class="dash-sidebar-backdrop"></div>
<script src="../js/dashboarsd.js"></script>

</body>

</html>