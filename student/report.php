<?php

session_start();
require_once "../db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Student") {
    header("Location: ../login.php?role=student");
    exit;
}

$studentName = $_SESSION["full_name"] ?? "Student";
$studentId = $_SESSION["profile_id"] ?? 0;

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

    $action = $_POST["action"] ?? "";
    $reportId = (int) ($_POST["report_id"] ?? 0);

    if ($action === "delete" && $reportId) {

        // Only their own report, and only while it's still a Draft.
        $stmt = $conn->prepare("DELETE FROM dailyreport WHERE ReportID = ? AND StudentID = ? AND Status = 'Draft'");
        $stmt->bind_param("ii", $reportId, $studentId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        flash($affected > 0 ? "success" : "error", $affected > 0 ? "Report deleted." : "Could not delete this report.");
        header("Location: report.php");
        exit;

    }

    if ($action === "save") {

        $reportDate = trim($_POST["reportDate"] ?? "");
        $activities = trim($_POST["activities"] ?? "");
        $attachment = trim($_POST["attachment"] ?? "");
        $status = ($_POST["submitType"] ?? "draft") === "submit" ? "Submitted" : "Draft";

        if ($reportDate === "" || $activities === "") {

            flash("error", "Please fill in the date and your activities.");

        } elseif ($reportId) {

            // Editing — only allowed while it's still a Draft, and only your own.
            $stmt = $conn->prepare("
                UPDATE dailyreport
                SET ReportDate = ?, Activities = ?, Attachment = ?, Status = ?
                WHERE ReportID = ? AND StudentID = ? AND Status = 'Draft'
            ");
            $stmt->bind_param("ssssii", $reportDate, $activities, $attachment, $status, $reportId, $studentId);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            flash($affected > 0 ? "success" : "error", $affected > 0 ? "Report saved." : "Could not update this report.");

        } else {

            $stmt = $conn->prepare("
                INSERT INTO dailyreport (ReportDate, Activities, Attachment, Status, StudentID)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssssi", $reportDate, $activities, $attachment, $status, $studentId);
            $stmt->execute();
            $stmt->close();

            flash("success", $status === "Submitted" ? "Report submitted." : "Draft saved.");

        }

        header("Location: report.php");
        exit;

    }
}

$editId = (int) ($_GET["edit"] ?? 0);
$editing = null;

if ($editId) {
    $stmt = $conn->prepare("SELECT * FROM dailyreport WHERE ReportID = ? AND StudentID = ? AND Status = 'Draft'");
    $stmt->bind_param("ii", $editId, $studentId);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$stmt = $conn->prepare("SELECT * FROM dailyreport WHERE StudentID = ? ORDER BY ReportDate DESC");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$reports = $stmt->get_result();
$stmt->close();

$statusBadge = [
    "Draft" => "badge-gray",
    "Submitted" => "badge-blue",
    "Reviewed" => "badge-blue",
    "Approved" => "badge-green",
    "Rejected" => "badge-danger",
];

$flash = get_flash();
$today = date("Y-m-d");

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Submit Report | SIPMS</title>

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
            <a href="report.php" class="active"><i class='bx bx-notepad'></i> Submit Report</a>
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
            <h1>Submit Report</h1>
            <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($studentName); ?></div>
        </header>

        <main class="dash-content">

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
            <?php endif; ?>

            <div class="dash-panel">
                <h2><?php echo $editing ? "Edit Draft" : "New Daily Report"; ?></h2>

                <form method="POST" class="dash-form">
                    <input type="hidden" name="action" value="save">
                    <?php if ($editing): ?>
                        <input type="hidden" name="report_id" value="<?php echo (int) $editing["ReportID"]; ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div>
                            <label>Date</label>
                            <input type="date" name="reportDate" required value="<?php echo htmlspecialchars($editing["ReportDate"] ?? $today); ?>">
                        </div>
                        <div>
                            <label>Attachment link (optional)</label>
                            <input type="text" name="attachment" placeholder="Link to a file, if any" value="<?php echo htmlspecialchars($editing["Attachment"] ?? ""); ?>">
                        </div>
                    </div>

                    <label>Activities</label>
                    <textarea name="activities" rows="5" required placeholder="What did you work on today?"><?php echo htmlspecialchars($editing["Activities"] ?? ""); ?></textarea>

                    <div class="row-actions">
                        <button type="submit" name="submitType" value="draft" class="btn-small">Save as Draft</button>
                        <button type="submit" name="submitType" value="submit" class="btn-primary">Submit Report</button>
                        <?php if ($editing): ?>
                            <a href="report.php" class="btn-small">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="dash-panel">
                <h2>My Reports</h2>
                <table class="dash-table">
                    <thead>
                        <tr>
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
                                    <td class="muted"><?php echo htmlspecialchars(date("M j, Y", strtotime($r["ReportDate"]))); ?></td>
                                    <td class="truncate"><?php echo htmlspecialchars($r["Activities"]); ?></td>
                                    <td>
                                        <span class="badge <?php echo $statusBadge[$r["Status"]] ?? "badge-gray"; ?>">
                                            <?php echo htmlspecialchars($r["Status"]); ?>
                                        </span>
                                    </td>
                                    <td class="row-actions">
                                        <?php if ($r["Status"] === "Draft"): ?>
                                            <a href="report.php?edit=<?php echo (int) $r["ReportID"]; ?>" class="btn-small">Edit</a>
                                            <form method="POST" onsubmit="return confirm('Delete this draft?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="report_id" value="<?php echo (int) $r["ReportID"]; ?>">
                                                <button type="submit" class="btn-small btn-danger">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="muted">Locked</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4">No reports yet.</td></tr>
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