<?php

session_start();
require_once "../db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Company") {
    header("Location: ../login.php?role=company");
    exit;
}

$companyName = $_SESSION["full_name"] ?? "Company";
$companyId = $_SESSION["profile_id"] ?? 0;

$statusOptions = ["Pending", "Shortlisted", "Accepted", "Rejected"];

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

    $applicationId = (int) ($_POST["application_id"] ?? 0);
    $status = $_POST["status"] ?? "";

    if (!$applicationId || !in_array($status, $statusOptions, true)) {

        flash("error", "Invalid update.");

    } else {

        // Only applications on THIS company's own internships can be touched —
        // enforced with a join, not just trusting the posted ApplicationID.
        $stmt = $conn->prepare("
            UPDATE applications a
            JOIN internship i ON a.InternshipID = i.InternshipID
            SET a.Status = ?
            WHERE a.ApplicationID = ? AND i.CompanyID = ?
        ");
        $stmt->bind_param("sii", $status, $applicationId, $companyId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        flash($affected > 0 ? "success" : "error", $affected > 0 ? "Application updated." : "Could not update this application.");

    }

    header("Location: application.php");
    exit;

}

$reviewId = (int) ($_GET["review"] ?? 0);
$reviewing = null;

if ($reviewId) {
    $stmt = $conn->prepare("
        SELECT a.ApplicationID, a.ApplicationDate, a.Status, a.CoverLetter,
               s.fullName, s.matricule, i.Title
        FROM applications a
        JOIN internship i ON a.InternshipID = i.InternshipID
        JOIN student s ON a.StudentID = s.studentID
        WHERE a.ApplicationID = ? AND i.CompanyID = ?
    ");
    $stmt->bind_param("ii", $reviewId, $companyId);
    $stmt->execute();
    $reviewing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$stmt = $conn->prepare("
    SELECT a.ApplicationID, a.Status, a.ApplicationDate, s.fullName, i.Title
    FROM applications a
    JOIN internship i ON a.InternshipID = i.InternshipID
    JOIN student s ON a.StudentID = s.studentID
    WHERE i.CompanyID = ?
    ORDER BY a.ApplicationDate DESC
");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$applications = $stmt->get_result();
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

    <title>Applications | SIPMS</title>

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
            <a href="application.php" class="active"><i class='bx bx-file'></i> Applications</a>
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
            <h1>Applications</h1>
            <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($companyName); ?></div>
        </header>

        <main class="dash-content">

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
            <?php endif; ?>

            <?php if ($reviewing): ?>

                <div class="dash-panel">
                    <h2>Review Application</h2>

                    <div class="form-row">
                        <div>
                            <label>Student</label>
                            <p><?php echo htmlspecialchars($reviewing["fullName"]); ?> (<?php echo htmlspecialchars($reviewing["matricule"]); ?>)</p>
                        </div>
                        <div>
                            <label>Internship</label>
                            <p><?php echo htmlspecialchars($reviewing["Title"]); ?></p>
                        </div>
                    </div>

                    <label>Cover Letter</label>
                    <p class="muted"><?php echo nl2br(htmlspecialchars($reviewing["CoverLetter"] ?: "No cover letter submitted.")); ?></p>

                    <form method="POST" class="dash-form" style="margin-top: 1.6rem;">
                        <input type="hidden" name="application_id" value="<?php echo (int) $reviewing["ApplicationID"]; ?>">

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
                            <a href="application.php" class="btn-small">Cancel</a>
                        </div>
                    </form>
                </div>

            <?php endif; ?>

            <div class="dash-panel">
                <h2>Applications to My Internships</h2>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Internship</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($applications && $applications->num_rows > 0): ?>
                            <?php while ($a = $applications->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($a["fullName"]); ?></td>
                                    <td><?php echo htmlspecialchars($a["Title"]); ?></td>
                                    <td class="muted"><?php echo htmlspecialchars(date("M j, Y", strtotime($a["ApplicationDate"]))); ?></td>
                                    <td>
                                        <span class="badge <?php echo $statusBadge[$a["Status"]] ?? "badge-gray"; ?>">
                                            <?php echo htmlspecialchars($a["Status"]); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="application.php?review=<?php echo (int) $a["ApplicationID"]; ?>" class="btn-small">Review</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No applications yet.</td></tr>
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