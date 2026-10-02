<?php

require_once "helpers.php";
require_once "../db.php";
require_admin_login();

$adminName = $_SESSION["full_name"] ?? "Admin";

$statusOptions = ["Pending", "Shortlisted", "Accepted", "Rejected", "Withdrawn"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";
    $appId = (int) ($_POST["application_id"] ?? 0);

    if ($action === "delete" && $appId) {

        $stmt = $conn->prepare("DELETE FROM applications WHERE ApplicationID = ?");
        $stmt->bind_param("i", $appId);
        $stmt->execute();
        $stmt->close();

        admin_flash("success", "Application deleted.");
        header("Location: application.php");
        exit;

    }

    if ($action === "update" && $appId) {

        $status = $_POST["status"] ?? "";
        $adminNotes = trim($_POST["adminNotes"] ?? "");

        if (!in_array($status, $statusOptions, true)) {

            admin_flash("error", "Invalid status selected.");

        } else {

            $stmt = $conn->prepare("UPDATE applications SET Status = ?, AdminNotes = ? WHERE ApplicationID = ?");
            $stmt->bind_param("ssi", $status, $adminNotes, $appId);
            $stmt->execute();
            $stmt->close();

            admin_flash("success", "Application updated.");

        }

        header("Location: application.php");
        exit;

    }
}

$reviewId = (int) ($_GET["review"] ?? 0);
$reviewing = null;

if ($reviewId) {
    $stmt = $conn->prepare("
        SELECT
            a.ApplicationID, a.ApplicationDate, a.Status, a.CoverLetter, a.AdminNotes,
            s.fullName AS StudentName, s.matricule,
            i.Title AS InternshipTitle,
            c.Name AS CompanyName
        FROM applications a
        JOIN student s ON a.StudentID = s.studentID
        JOIN internship i ON a.InternshipID = i.InternshipID
        LEFT JOIN company c ON i.CompanyID = c.CompanyID
        WHERE a.ApplicationID = ?
    ");
    $stmt->bind_param("i", $reviewId);
    $stmt->execute();
    $reviewing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$applications = $conn->query("
    SELECT
        a.ApplicationID, a.ApplicationDate, a.Status,
        s.fullName AS StudentName,
        i.Title AS InternshipTitle,
        c.Name AS CompanyName
    FROM applications a
    JOIN student s ON a.StudentID = s.studentID
    JOIN internship i ON a.InternshipID = i.InternshipID
    LEFT JOIN company c ON i.CompanyID = c.CompanyID
    ORDER BY a.ApplicationDate DESC
");

$statusBadge = [
    "Pending" => "badge-gray",
    "Shortlisted" => "badge-blue",
    "Accepted" => "badge-green",
    "Rejected" => "badge-danger",
    "Withdrawn" => "badge-gray",
];

$flash = admin_get_flash();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Applications | SIPMS Admin</title>

    <link rel="stylesheet" href="../css/boxicons.min.css">
    <link rel="stylesheet" href="../css/admin.css">

</head>

<body>

<div class="admin-shell">

    <aside class="admin-sidebar" id="adminSidebar">

        <div class="admin-logo"><i class='bx bxs-graduation'></i> SIPMS</div>

        <nav class="admin-nav">
            <a href="admin.php"><i class='bx bx-grid-alt'></i> Dashboard</a>
            <a href="student.php"><i class='bx bx-user'></i> Students</a>
            <a href="company.php"><i class='bx bx-building'></i> Companies</a>
            <a href="supervisor.php"><i class='bx bx-chalkboard'></i> Supervisors</a>
            <a href="internship.php"><i class='bx bx-briefcase'></i> Internships</a>
            <a href="application.php" class="active"><i class='bx bx-file'></i> Applications</a>
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
            <h1>Applications</h1>
            <div class="admin-user"><i class='bx bx-user-circle'></i> <?php echo e($adminName); ?></div>
        </header>

        <main class="admin-content">

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?php echo e($flash["type"]); ?>"><?php echo e($flash["message"]); ?></div>
            <?php endif; ?>

            <?php if ($reviewing): ?>

                <div class="admin-panel">
                    <h2>Review Application</h2>

                    <div class="form-row">
                        <div>
                            <label>Student</label>
                            <p><?php echo e($reviewing["StudentName"]); ?> (<?php echo e($reviewing["matricule"]); ?>)</p>
                        </div>
                        <div>
                            <label>Internship</label>
                            <p><?php echo e($reviewing["InternshipTitle"]); ?> — <?php echo e($reviewing["CompanyName"] ?? "Unknown company"); ?></p>
                        </div>
                    </div>

                    <label>Cover Letter</label>
                    <p class="muted"><?php echo nl2br(e($reviewing["CoverLetter"] ?: "No cover letter submitted.")); ?></p>

                    <form method="POST" class="admin-form" style="margin-top: 1.6rem;">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="application_id" value="<?php echo (int) $reviewing["ApplicationID"]; ?>">

                        <label>Status</label>
                        <select name="status">
                            <?php foreach ($statusOptions as $option): ?>
                                <option value="<?php echo e($option); ?>" <?php echo $reviewing["Status"] === $option ? "selected" : ""; ?>>
                                    <?php echo e($option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label>Admin Notes</label>
                        <textarea name="adminNotes" rows="3"><?php echo e($reviewing["AdminNotes"] ?? ""); ?></textarea>

                        <div class="row-actions">
                            <button type="submit" class="btn-primary">Save Review</button>
                            <a href="application.php" class="btn-small">Cancel</a>
                        </div>
                    </form>
                </div>

            <?php endif; ?>

            <div class="admin-panel">
                <h2>All Applications</h2>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Internship</th>
                            <th>Company</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($applications && $applications->num_rows > 0): ?>
                            <?php while ($a = $applications->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo e($a["StudentName"]); ?></td>
                                    <td><?php echo e($a["InternshipTitle"]); ?></td>
                                    <td class="muted"><?php echo e($a["CompanyName"] ?? "—"); ?></td>
                                    <td class="muted"><?php echo e(date("M j, Y", strtotime($a["ApplicationDate"]))); ?></td>
                                    <td>
                                        <span class="badge <?php echo $statusBadge[$a["Status"]] ?? "badge-gray"; ?>">
                                            <?php echo e($a["Status"]); ?>
                                        </span>
                                    </td>
                                    <td class="row-actions">
                                        <a href="application.php?review=<?php echo (int) $a["ApplicationID"]; ?>" class="btn-small">Review</a>
                                        <form method="POST" onsubmit="return confirm('Delete this application?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="application_id" value="<?php echo (int) $a["ApplicationID"]; ?>">
                                            <button type="submit" class="btn-small btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No applications yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>

    </div>

</div>

<script src="../js/admin.js"></script>

</body>

</html>