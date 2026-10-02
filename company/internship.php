<?php

session_start();
require_once "../db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Company") {
    header("Location: ../login.php?role=company");
    exit;
}

$companyName = $_SESSION["full_name"] ?? "Company";
$companyId = $_SESSION["profile_id"] ?? 0;

$statusOptions = ["Pending", "Active", "Completed", "Cancelled"];

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

    if ($action === "delete" && $internshipId) {

        $stmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE InternshipID = ?");
        $stmt->bind_param("i", $internshipId);
        $stmt->execute();
        $hasApplications = (int) $stmt->get_result()->fetch_row()[0] > 0;
        $stmt->close();

        if ($hasApplications) {

            flash("error", "Can't delete — this internship already has applications. Set its status to Cancelled instead.");

        } else {

            $stmt = $conn->prepare("DELETE FROM internship WHERE InternshipID = ? AND CompanyID = ?");
            $stmt->bind_param("ii", $internshipId, $companyId);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            flash($affected > 0 ? "success" : "error", $affected > 0 ? "Internship deleted." : "Could not delete this internship.");

        }

        header("Location: internship.php");
        exit;

    }

    if ($action === "add" || $action === "update") {

        $title = trim($_POST["title"] ?? "");
        $category = trim($_POST["category"] ?? "");
        $industry = trim($_POST["industry"] ?? "");
        $location = trim($_POST["location"] ?? "");
        $skills = trim($_POST["skills"] ?? "");
        $duration = trim($_POST["duration"] ?? "");
        $deadline = trim($_POST["deadline"] ?? "") ?: null;
        $startDate = trim($_POST["startDate"] ?? "") ?: null;
        $endDate = trim($_POST["endDate"] ?? "") ?: null;
        $requirements = trim($_POST["requirements"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $status = in_array($_POST["status"] ?? "", $statusOptions, true) ? $_POST["status"] : "Active";

        if ($title === "") {

            flash("error", "Please enter a title.");

        } elseif ($action === "add") {

            $stmt = $conn->prepare("
                INSERT INTO internship
                    (Title, Category, Industry, Location, Skills, Duration, ApplicationDeadline, Requirements, Description, StartDate, EndDate, CompanyID, Status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "sssssssssssis",
                $title, $category, $industry, $location, $skills, $duration,
                $deadline, $requirements, $description, $startDate, $endDate,
                $companyId, $status
            );
            $stmt->execute();
            $stmt->close();

            flash("success", "Internship posted.");

        } else {

            $stmt = $conn->prepare("
                UPDATE internship
                SET Title=?, Category=?, Industry=?, Location=?, Skills=?, Duration=?,
                    ApplicationDeadline=?, Requirements=?, Description=?, StartDate=?, EndDate=?, Status=?
                WHERE InternshipID = ? AND CompanyID = ?
            ");
            $stmt->bind_param(
                "ssssssssssssii",
                $title, $category, $industry, $location, $skills, $duration,
                $deadline, $requirements, $description, $startDate, $endDate, $status,
                $internshipId, $companyId
            );
            $stmt->execute();
            $stmt->close();

            flash("success", "Internship updated.");

        }

        header("Location: internship.php");
        exit;

    }
}

$editId = (int) ($_GET["edit"] ?? 0);
$editing = null;

if ($editId) {
    $stmt = $conn->prepare("SELECT * FROM internship WHERE InternshipID = ? AND CompanyID = ?");
    $stmt->bind_param("ii", $editId, $companyId);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$stmt = $conn->prepare("
    SELECT i.*, (SELECT COUNT(*) FROM applications WHERE InternshipID = i.InternshipID) AS ApplicationCount
    FROM internship i
    WHERE i.CompanyID = ?
    ORDER BY i.InternshipID DESC
");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$internships = $stmt->get_result();
$stmt->close();

$statusBadge = [
    "Pending" => "badge-gray",
    "Active" => "badge-green",
    "Completed" => "badge-blue",
    "Cancelled" => "badge-danger",
];

$flash = get_flash();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Internships | SIPMS</title>

    <link rel="stylesheet" href="../css/boxicons.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">

</head>

<body>

<div class="dash-shell">

    <aside class="dash-sidebar" id="dashSidebar">

        <div class="dash-logo"><i class='bx bxs-graduation'></i> SIPMS</div>

        <nav class="dash-nav">
            <a href="dashboard.php"><i class='bx bx-grid-alt'></i> Dashboard</a>
            <a href="internship.php" class="active"><i class='bx bx-briefcase'></i> My Internships</a>
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
            <h1>My Internships</h1>
            <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($companyName); ?></div>
        </header>

        <main class="dash-content">

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
            <?php endif; ?>

            <div class="dash-panel">
                <h2><?php echo $editing ? "Edit Internship" : "Post a New Internship"; ?></h2>

                <form method="POST" class="dash-form">
                    <input type="hidden" name="action" value="<?php echo $editing ? "update" : "add"; ?>">
                    <?php if ($editing): ?>
                        <input type="hidden" name="internship_id" value="<?php echo (int) $editing["InternshipID"]; ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div>
                            <label>Title</label>
                            <input type="text" name="title" required value="<?php echo htmlspecialchars($editing["Title"] ?? ""); ?>">
                        </div>
                        <div>
                            <label>Status</label>
                            <select name="status">
                                <?php foreach ($statusOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (($editing["Status"] ?? "Active") === $option) ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div>
                            <label>Category</label>
                            <input type="text" name="category" value="<?php echo htmlspecialchars($editing["Category"] ?? ""); ?>">
                        </div>
                        <div>
                            <label>Industry</label>
                            <input type="text" name="industry" value="<?php echo htmlspecialchars($editing["Industry"] ?? ""); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div>
                            <label>Location</label>
                            <input type="text" name="location" value="<?php echo htmlspecialchars($editing["Location"] ?? ""); ?>">
                        </div>
                        <div>
                            <label>Duration</label>
                            <input type="text" name="duration" placeholder="e.g. 3 months" value="<?php echo htmlspecialchars($editing["Duration"] ?? ""); ?>">
                        </div>
                    </div>

                    <label>Skills</label>
                    <input type="text" name="skills" placeholder="e.g. PHP, MySQL, Communication" value="<?php echo htmlspecialchars($editing["Skills"] ?? ""); ?>">

                    <div class="form-row">
                        <div>
                            <label>Application Deadline</label>
                            <input type="date" name="deadline" value="<?php echo htmlspecialchars($editing["ApplicationDeadline"] ?? ""); ?>">
                        </div>
                        <div></div>
                    </div>

                    <div class="form-row">
                        <div>
                            <label>Start Date</label>
                            <input type="date" name="startDate" value="<?php echo htmlspecialchars($editing["StartDate"] ?? ""); ?>">
                        </div>
                        <div>
                            <label>End Date</label>
                            <input type="date" name="endDate" value="<?php echo htmlspecialchars($editing["EndDate"] ?? ""); ?>">
                        </div>
                    </div>

                    <label>Requirements</label>
                    <textarea name="requirements" rows="3"><?php echo htmlspecialchars($editing["Requirements"] ?? ""); ?></textarea>

                    <label>Description</label>
                    <textarea name="description" rows="4"><?php echo htmlspecialchars($editing["Description"] ?? ""); ?></textarea>

                    <div class="row-actions">
                        <button type="submit" class="btn-primary"><?php echo $editing ? "Save Changes" : "Post Internship"; ?></button>
                        <?php if ($editing): ?>
                            <a href="internship.php" class="btn-small">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="dash-panel">
                <h2>All My Internships</h2>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Location</th>
                            <th>Applications</th>
                            <th>Status</th>
                            <th>Deadline</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($internships && $internships->num_rows > 0): ?>
                            <?php while ($i = $internships->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($i["Title"]); ?></td>
                                    <td class="muted"><?php echo htmlspecialchars($i["Location"] ?: "—"); ?></td>
                                    <td><?php echo (int) $i["ApplicationCount"]; ?></td>
                                    <td>
                                        <span class="badge <?php echo $statusBadge[$i["Status"]] ?? "badge-gray"; ?>">
                                            <?php echo htmlspecialchars($i["Status"]); ?>
                                        </span>
                                    </td>
                                    <td class="muted"><?php echo $i["ApplicationDeadline"] ? htmlspecialchars(date("M j, Y", strtotime($i["ApplicationDeadline"]))) : "—"; ?></td>
                                    <td class="row-actions">
                                        <a href="internship.php?edit=<?php echo (int) $i["InternshipID"]; ?>" class="btn-small">Edit</a>
                                        <form method="POST" onsubmit="return confirm('Delete this internship?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="internship_id" value="<?php echo (int) $i["InternshipID"]; ?>">
                                            <button type="submit" class="btn-small btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No internships posted yet.</td></tr>
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