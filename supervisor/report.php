<?php

session_start();
require_once "../db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Supervisor") {
    header("Location: ../login.php?role=supervisor");
    exit;
}

$supervisorName = $_SESSION["full_name"] ?? "Supervisor";
$supervisorId = $_SESSION["profile_id"] ?? 0;

$statusOptions = ["Submitted", "Reviewed", "Approved", "Rejected"];

function flash(string $type, string $message): void
{
    $_SESSION["supervisor_flash"] = ["type" => $type, "message" => $message];
}

function get_flash(): ?array
{
    if (!empty($_SESSION["supervisor_flash"])) {
        $f = $_SESSION["supervisor_flash"];
        unset($_SESSION["supervisor_flash"]);
        return $f;
    }
    return null;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $reportId = (int) ($_POST["report_id"] ?? 0);
    $status = $_POST["status"] ?? "";

    if (!$reportId || !in_array($status, $statusOptions, true)) {

        flash("error", "Invalid update.");

    } else {

        // Only reports belonging to one of THIS supervisor's own interns
        // can be touched — enforced via the join, not just a trusted ID.
        $stmt = $conn->prepare("
            UPDATE dailyreport d
            JOIN internship i ON d.StudentID = i.StudentID
            SET d.Status = ?
            WHERE d.ReportID = ? AND i.SupervisorID = ? AND d.Status <> 'Draft'
        ");
        $stmt->bind_param("sii", $status, $reportId, $supervisorId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        flash($affected > 0 ? "success" : "error", $affected > 0 ? "Report updated." : "Could not update this report.");

    }

    header("Location: report.php");
    exit;

}

$reviewId = (int) ($_GET["review"] ?? 0);
$reviewing = null;

if ($reviewId) {
    $stmt = $conn->prepare("
        SELECT d.*, s.fullName, s.matricule
        FROM dailyreport d
        JOIN student s ON d.StudentID = s.studentID
        JOIN internship i ON d.StudentID = i.StudentID
        WHERE d.ReportID = ? AND i.SupervisorID = ?
    ");
    $stmt->bind_param("ii", $reviewId, $supervisorId);
    $stmt->execute();
    $reviewing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$stmt = $conn->prepare("
    SELECT d.ReportID, d.ReportDate, d.Status, d.Activities, s.fullName, s.matricule
    FROM dailyreport d
    JOIN student s ON d.StudentID = s.studentID
    JOIN internship i ON d.StudentID = i.StudentID
    WHERE i.SupervisorID = ? AND d.Status <> 'Draft'
    ORDER BY d.ReportDate DESC
");
$stmt->bind_param("i", $supervisorId);
$stmt->execute();
$reports = $stmt->get_result();
$stmt->close();

$reportBadge = ["Submitted" => "badge-blue", "Reviewed" => "badge-blue", "Approved" => "badge-green", "Rejected" => "badge-danger"];
$flash = get_flash();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reports | SIPMS</title>

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
            <a href="report.php" class="active"><i class='bx bx-notepad'></i> Reports</a>
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
            <h1>Reports</h1>
            <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($supervisorName); ?></div>
        </header>

        <main class="dash-content">

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
            <?php endif; ?>

            <?php if ($reviewing): ?>

                <div class="dash-panel">
                    <h2>Review Report</h2>

                    <div class="form-row">
                        <div>
                            <label>Student</label>
                            <p><?php echo htmlspecialchars($reviewing["fullName"]); ?> (<?php echo htmlspecialchars($reviewing["matricule"]); ?>)</p>
                        </div>
                        <div>
                            <label>Date</label>
                            <p><?php echo htmlspecialchars(date("M j, Y", strtotime($reviewing["ReportDate"]))); ?></p>
                        </div>
                    </div>

                    <label>Activities</label>
                    <p class="muted"><?php echo nl2br(htmlspecialchars($reviewing["Activities"])); ?></p>

                    <?php if (!empty($reviewing["Attachment"])): ?>
                        <p style="margin-top: 1rem;"><a href="<?php echo htmlspecialchars($reviewing["Attachment"]); ?>" class="btn-link" target="_blank">View Attachment ↗</a></p>
                    <?php endif; ?>

                    <form method="POST" class="dash-form" style="margin-top: 1.6rem;">
                        <input type="hidden" name="report_id" value="<?php echo (int) $reviewing["ReportID"]; ?>">

                        <label>Status</label>
                        <select name="status">
                            <?php foreach ($statusOptions as $option): ?>
                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $reviewing["Status"] === $option ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="row-actions">
                            <button type="submit" class="btn-primary">Save Decision</button>
                            <a href="report.php" class="btn-small">Cancel</a>
                        </div>
                    </form>
                </div>

            <?php endif; ?>

            <div class="dash-panel">
                <h2>Submitted Reports</h2>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Date</th>
                            <th>Activities</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reports && $reports->num_rows > 0): ?>
                            <?php while ($r = $reports->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r["fullName"]); ?> <span class="muted">(<?php echo htmlspecialchars($r["matricule"]); ?>)</span></td>
                                    <td class="muted"><?php echo htmlspecialchars(date("M j, Y", strtotime($r["ReportDate"]))); ?></td>
                                    <td class="truncate"><?php echo htmlspecialchars($r["Activities"]); ?></td>
                                    <td><span class="badge <?php echo $reportBadge[$r["Status"]] ?? "badge-gray"; ?>"><?php echo htmlspecialchars($r["Status"]); ?></span></td>
                                    <td><a href="report.php?review=<?php echo (int) $r["ReportID"]; ?>" class="btn-small">Review</a></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No submitted reports yet.</td></tr>
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