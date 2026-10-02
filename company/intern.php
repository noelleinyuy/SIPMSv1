<?php

session_start();
require_once "../db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Company") {
    header("Location: ../login.php?role=company");
    exit;
}

$companyName = $_SESSION["full_name"] ?? "Company";
$companyId = $_SESSION["profile_id"] ?? 0;

function flash(string $type, string $message): void
{
    $_SESSION["company_flash"] = ["type" => $type, "message" => $message];
}

function get_flash(): ?array
{
    if (!empty($_SESSION["company_flash"])) {
        $f = $_SESSION["company_flash"];
        unset($_SESSION["company_flash"]);
        return $f;
    }
    return null;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";
    $internshipId = (int) ($_POST["internship_id"] ?? 0);
    $studentId = (int) ($_POST["student_id"] ?? 0);

    if ($action === "place" && $internshipId && $studentId) {

        $stmt = $conn->prepare("UPDATE internship SET StudentID = ? WHERE InternshipID = ? AND CompanyID = ? AND StudentID IS NULL");
        $stmt->bind_param("iii", $studentId, $internshipId, $companyId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        flash($affected > 0 ? "success" : "error", $affected > 0 ? "Student placed as intern." : "Could not place this student.");

    } elseif ($action === "remove" && $internshipId) {

        $stmt = $conn->prepare("UPDATE internship SET StudentID = NULL WHERE InternshipID = ? AND CompanyID = ?");
        $stmt->bind_param("ii", $internshipId, $companyId);
        $stmt->execute();
        $stmt->close();

        flash("success", "Intern removed from this internship.");

    }

    header("Location: intern.php");
    exit;

}

// Accepted applicants not yet formally placed into their internship.
$stmt = $conn->prepare("
    SELECT a.ApplicationID, i.InternshipID, i.Title, s.studentID, s.fullName, s.matricule
    FROM applications a
    JOIN internship i ON a.InternshipID = i.InternshipID
    JOIN student s ON a.StudentID = s.studentID
    WHERE i.CompanyID = ? AND a.Status = 'Accepted' AND i.StudentID IS NULL
    ORDER BY a.ApplicationDate DESC
");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$readyToPlace = $stmt->get_result();
$stmt->close();

// Currently placed interns.
$stmt = $conn->prepare("
    SELECT i.InternshipID, i.Title, i.Status, i.StartDate, i.EndDate, s.fullName, s.matricule
    FROM internship i
    JOIN student s ON i.StudentID = s.studentID
    WHERE i.CompanyID = ? AND i.StudentID IS NOT NULL
    ORDER BY i.StartDate DESC
");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$currentInterns = $stmt->get_result();
$stmt->close();

$flash = get_flash();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Interns | SIPMS</title>

    <link rel="stylesheet" href="../css/boxicons.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">

</head>

<body>

<div class="dash-shell">

    <aside class="dash-sidebar" id="dashSidebar">

        <div class="dash-logo"><i class='bx bxs-graduation'></i> SIPMS</div>

        <nav class="dash-nav">
            <a href="dashboard.php"><i class='bx bx-grid-alt'></i> Dashboard</a>
            <a href="internship.php"><i class='bx bx-briefcase'></i> My Internships</a>
            <a href="application.php"><i class='bx bx-file'></i> Applications</a>
            <a href="intern.php" class="active"><i class='bx bx-group'></i> My Interns</a>
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
            <h1>My Interns</h1>
            <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($companyName); ?></div>
        </header>

        <main class="dash-content">

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
            <?php endif; ?>

            <?php if ($readyToPlace && $readyToPlace->num_rows > 0): ?>
                <div class="dash-panel">
                    <h2>Ready to Place</h2>
                    <p class="muted" style="margin-bottom: 1.2rem;">These students were Accepted but haven't been formally placed into the internship yet.</p>
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Internship</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($r = $readyToPlace->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r["fullName"]); ?> <span class="muted">(<?php echo htmlspecialchars($r["matricule"]); ?>)</span></td>
                                    <td><?php echo htmlspecialchars($r["Title"]); ?></td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="place">
                                            <input type="hidden" name="internship_id" value="<?php echo (int) $r["InternshipID"]; ?>">
                                            <input type="hidden" name="student_id" value="<?php echo (int) $r["studentID"]; ?>">
                                            <button type="submit" class="btn-primary">Place as Intern</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="dash-panel">
                <h2>Current Interns</h2>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Internship</th>
                            <th>Start</th>
                            <th>End</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($currentInterns && $currentInterns->num_rows > 0): ?>
                            <?php while ($i = $currentInterns->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($i["fullName"]); ?> <span class="muted">(<?php echo htmlspecialchars($i["matricule"]); ?>)</span></td>
                                    <td><?php echo htmlspecialchars($i["Title"]); ?></td>
                                    <td class="muted"><?php echo $i["StartDate"] ? htmlspecialchars(date("M j, Y", strtotime($i["StartDate"]))) : "—"; ?></td>
                                    <td class="muted"><?php echo $i["EndDate"] ? htmlspecialchars(date("M j, Y", strtotime($i["EndDate"]))) : "—"; ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Remove this intern from the internship?');">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="internship_id" value="<?php echo (int) $i["InternshipID"]; ?>">
                                            <button type="submit" class="btn-small btn-danger">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No interns placed yet.</td></tr>
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