<?php

require_once "helpers.php";
require_once "../db.php";
require_admin_login();

$adminName = $_SESSION["full_name"] ?? "Admin";

$statusOptions = ["Pending", "Active", "Completed", "Cancelled"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";
    $internshipId = (int) ($_POST["internship_id"] ?? 0);

    if ($action === "assign" && $internshipId) {

        $supervisorId = (int) ($_POST["supervisorId"] ?? 0);
        $supervisorId = $supervisorId ?: null;

        $stmt = $conn->prepare("UPDATE internship SET SupervisorID = ? WHERE InternshipID = ?");
        $stmt->bind_param("ii", $supervisorId, $internshipId);
        $stmt->execute();
        $stmt->close();

        admin_flash("success", $supervisorId ? "Supervisor assigned." : "Supervisor unassigned.");

    } elseif ($action === "status" && $internshipId) {

        $status = $_POST["status"] ?? "";

        if (in_array($status, $statusOptions, true)) {
            $stmt = $conn->prepare("UPDATE internship SET Status = ? WHERE InternshipID = ?");
            $stmt->bind_param("si", $status, $internshipId);
            $stmt->execute();
            $stmt->close();
            admin_flash("success", "Status updated.");
        } else {
            admin_flash("error", "Invalid status.");
        }

    } elseif ($action === "delete" && $internshipId) {

        $stmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE InternshipID = ?");
        $stmt->bind_param("i", $internshipId);
        $stmt->execute();
        $hasApplications = (int) $stmt->get_result()->fetch_row()[0] > 0;
        $stmt->close();

        if ($hasApplications) {
            admin_flash("error", "Can't delete — this internship has applications. Set it to Cancelled instead.");
        } else {
            $stmt = $conn->prepare("DELETE FROM internship WHERE InternshipID = ?");
            $stmt->bind_param("i", $internshipId);
            $stmt->execute();
            $stmt->close();
            admin_flash("success", "Internship deleted.");
        }
    }

    header("Location: internship.php");
    exit;

}

$supervisors = $conn->query("SELECT SupervisorID, FullName, Department FROM supervisor ORDER BY FullName ASC");
$supervisorOptions = $supervisors ? $supervisors->fetch_all(MYSQLI_ASSOC) : [];

$internships = $conn->query("
    SELECT
        i.InternshipID, i.Title, i.Location, i.Status, i.ApplicationDeadline,
        i.SupervisorID,
        c.Name AS CompanyName,
        s.fullName AS StudentName,
        (SELECT COUNT(*) FROM applications WHERE InternshipID = i.InternshipID) AS ApplicationCount
    FROM internship i
    LEFT JOIN company c ON i.CompanyID = c.CompanyID
    LEFT JOIN student s ON i.StudentID = s.studentID
    ORDER BY i.InternshipID DESC
");

$statusBadge = [
    "Pending" => "badge-gray",
    "Active" => "badge-green",
    "Completed" => "badge-blue",
    "Cancelled" => "badge-danger",
];

$flash = admin_get_flash();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Internships | SIPMS Admin</title>

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
            <a href="internship.php" class="active"><i class='bx bx-briefcase'></i> Internships</a>
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
            <h1>Internships</h1>
            <div class="admin-user"><i class='bx bx-user-circle'></i> <?php echo e($adminName); ?></div>
        </header>

        <main class="admin-content">

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?php echo e($flash["type"]); ?>"><?php echo e($flash["message"]); ?></div>
            <?php endif; ?>

            <div class="admin-panel">
                <h2>All Internships</h2>
                <p class="muted" style="margin-bottom: 1.4rem;">
                    Internships are posted by companies. Here you can oversee them all and assign
                    a supervisor to each one.
                </p>

                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Company</th>
                            <th>Intern</th>
                            <th>Apps</th>
                            <th>Status</th>
                            <th>Supervisor</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($internships && $internships->num_rows > 0): ?>
                            <?php while ($i = $internships->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo e($i["Title"]); ?></td>
                                    <td class="muted"><?php echo e($i["CompanyName"] ?? "—"); ?></td>
                                    <td class="muted"><?php echo e($i["StudentName"] ?? "Not placed"); ?></td>
                                    <td><?php echo (int) $i["ApplicationCount"]; ?></td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="internship_id" value="<?php echo (int) $i["InternshipID"]; ?>">
                                            <select name="status" onchange="this.form.submit()">
                                                <?php foreach ($statusOptions as $option): ?>
                                                    <option value="<?php echo e($option); ?>" <?php echo $i["Status"] === $option ? "selected" : ""; ?>>
                                                        <?php echo e($option); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="assign">
                                            <input type="hidden" name="internship_id" value="<?php echo (int) $i["InternshipID"]; ?>">
                                            <select name="supervisorId" onchange="this.form.submit()">
                                                <option value="">Unassigned</option>
                                                <?php foreach ($supervisorOptions as $sv): ?>
                                                    <option value="<?php echo (int) $sv["SupervisorID"]; ?>" <?php echo ((int) ($i["SupervisorID"] ?? 0) === (int) $sv["SupervisorID"]) ? "selected" : ""; ?>>
                                                        <?php echo e($sv["FullName"]); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                    <td class="row-actions">
                                        <form method="POST" onsubmit="return confirm('Delete this internship?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="internship_id" value="<?php echo (int) $i["InternshipID"]; ?>">
                                            <button type="submit" class="btn-small btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7">No internships posted yet.</td></tr>
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