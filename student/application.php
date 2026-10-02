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

    if ($action === "apply") {

        $internshipId = (int) ($_POST["internshipId"] ?? 0);
        $coverLetter = trim($_POST["coverLetter"] ?? "");

        if (!$internshipId || $coverLetter === "") {

            flash("error", "Please choose an internship and write a cover letter.");

        } else {

            $stmt = $conn->prepare("SELECT ApplicationID FROM applications WHERE InternshipID = ? AND StudentID = ?");
            $stmt->bind_param("ii", $internshipId, $studentId);
            $stmt->execute();
            $alreadyApplied = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($alreadyApplied) {

                flash("error", "You've already applied to this internship.");

            } else {

                $stmt = $conn->prepare("
                    INSERT INTO applications (InternshipID, StudentID, Status, CoverLetter)
                    VALUES (?, ?, 'Pending', ?)
                ");
                $stmt->bind_param("iis", $internshipId, $studentId, $coverLetter);
                $stmt->execute();
                $stmt->close();

                flash("success", "Application submitted.");

            }
        }

        header("Location: application.php");
        exit;

    }

    if ($action === "withdraw") {

        $applicationId = (int) ($_POST["application_id"] ?? 0);

        // Only allow withdrawing your own application, and only while it's
        // still Pending or Shortlisted (not already decided).
        $stmt = $conn->prepare("
            UPDATE applications
            SET Status = 'Withdrawn'
            WHERE ApplicationID = ? AND StudentID = ? AND Status IN ('Pending', 'Shortlisted')
        ");
        $stmt->bind_param("ii", $applicationId, $studentId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        flash($affected > 0 ? "success" : "error", $affected > 0 ? "Application withdrawn." : "Could not withdraw this application.");
        header("Location: application.php");
        exit;

    }
}

$stmt = $conn->prepare("
    SELECT i.InternshipID, i.Title, c.Name AS CompanyName
    FROM internship i
    LEFT JOIN company c ON i.CompanyID = c.CompanyID
    WHERE i.Status IN ('Pending', 'Active')
      AND (i.ApplicationDeadline IS NULL OR i.ApplicationDeadline >= CURDATE())
      AND i.InternshipID NOT IN (SELECT InternshipID FROM applications WHERE StudentID = ?)
    ORDER BY i.Title ASC
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$openInternships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT a.ApplicationID, a.Status, a.ApplicationDate, a.CoverLetter, i.Title, c.Name AS CompanyName
    FROM applications a
    JOIN internship i ON a.InternshipID = i.InternshipID
    LEFT JOIN company c ON i.CompanyID = c.CompanyID
    WHERE a.StudentID = ?
    ORDER BY a.ApplicationDate DESC
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$myApplications = $stmt->get_result();
$stmt->close();

$statusBadge = [
    "Pending" => "badge-gray",
    "Shortlisted" => "badge-blue",
    "Accepted" => "badge-green",
    "Rejected" => "badge-danger",
    "Withdrawn" => "badge-gray",
];

$flash = get_flash();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Applications | SIPMS</title>

    <link rel="stylesheet" href="../css/boxicons.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">

</head>

<body>

<div class="dash-shell">

    <aside class="dash-sidebar" id="dashSidebar">

        <div class="dash-logo"><i class='bx bxs-graduation'></i> SIPMS</div>

        <nav class="dash-nav">
            <a href="dashboard.php"><i class='bx bx-grid-alt'></i> Dashboard</a>
            <a href="application.php" class="active"><i class='bx bx-file'></i> My Applications</a>
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
            <h1>My Applications</h1>
            <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($studentName); ?></div>
        </header>

        <main class="dash-content">

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
            <?php endif; ?>

            <div class="dash-panel" id="apply">
                <h2>Apply to an Internship</h2>

                <?php if (empty($openInternships)): ?>
                    <p class="muted">No open internships available to apply to right now — either none are posted, or you've already applied to all of them.</p>
                <?php else: ?>
                    <?php $preselectId = (int) ($_GET["apply"] ?? 0); ?>
                    <form method="POST" class="dash-form">
                        <input type="hidden" name="action" value="apply">

                        <label>Internship</label>
                        <select name="internshipId" required>
                            <option value="">Select an internship</option>
                            <?php foreach ($openInternships as $opt): ?>
                                <option value="<?php echo (int) $opt["InternshipID"]; ?>" <?php echo $preselectId === (int) $opt["InternshipID"] ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($opt["Title"]); ?> — <?php echo htmlspecialchars($opt["CompanyName"] ?? "Unknown company"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label>Cover Letter</label>
                        <textarea name="coverLetter" rows="4" required placeholder="Tell them why you're a good fit..."></textarea>

                        <button type="submit" class="btn-primary">Submit Application</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="dash-panel">
                <h2>My Applications</h2>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Internship</th>
                            <th>Company</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($myApplications && $myApplications->num_rows > 0): ?>
                            <?php while ($a = $myApplications->fetch_assoc()): ?>
                                <?php $canWithdraw = in_array($a["Status"], ["Pending", "Shortlisted"], true); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($a["Title"]); ?></td>
                                    <td class="muted"><?php echo htmlspecialchars($a["CompanyName"] ?? "—"); ?></td>
                                    <td class="muted"><?php echo htmlspecialchars(date("M j, Y", strtotime($a["ApplicationDate"]))); ?></td>
                                    <td>
                                        <span class="badge <?php echo $statusBadge[$a["Status"]] ?? "badge-gray"; ?>">
                                            <?php echo htmlspecialchars($a["Status"]); ?>
                                        </span>
                                    </td>
                                    <td class="row-actions">
                                        <?php if ($canWithdraw): ?>
                                            <form method="POST" onsubmit="return confirm('Withdraw this application?');">
                                                <input type="hidden" name="action" value="withdraw">
                                                <input type="hidden" name="application_id" value="<?php echo (int) $a["ApplicationID"]; ?>">
                                                <button type="submit" class="btn-small btn-danger">Withdraw</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">You haven't applied to anything yet.</td></tr>
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