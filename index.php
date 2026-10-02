<?php

session_start();

// If someone is already logged in, skip the landing page and send them
// straight to their dashboard.
if (isset($_SESSION["role"])) {

    $dashboards = [
        "Student" => "student/dashboard.php",
        "Company" => "company/dashboard.php",
        "Supervisor" => "supervisor/dashboard.php",
        "Lecturer" => "supervisor/dashboard.php",
        "Admin" => "admin/admin.php",
    ];

    $role = $_SESSION["role"];

    if (isset($dashboards[$role])) {
        header("Location: " . $dashboards[$role]);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SIPMS | Smart Internship Management System</title>

    <link rel="stylesheet" href="css/boxicons.min.css">
    <link rel="stylesheet" href="css/home.css">

</head>

<body>

    <!-- ================= HEADER ================= -->

    <header class="header">

        <nav class="navbar">

            <a href="index.php" class="logo">
                <i class='bx bxs-graduation'></i>
                <span>SIPMS</span>
            </a>

            <div class="nav-menu">
                <a href="#home" class="nav-link active">Home</a>
                <a href="#about" class="nav-link">About</a>
                <a href="#features" class="nav-link">Features</a>
                <a href="#how-it-works" class="nav-link">How It Works</a>
                <a href="#roles" class="nav-link">Get Started</a>
                <a href="#contact" class="nav-link">Contact</a>
            </div>

            <!-- Role links replace the old Login/Register buttons -->
            <div class="nav-buttons">
                <a href="login.php?role=student" class="nav-role-link"><i class='bx bx-user'></i> Student</a>
                <a href="login.php?role=company" class="nav-role-link"><i class='bx bx-building'></i> Company</a>
                <a href="login.php?role=supervisor" class="nav-role-link"><i class='bx bx-chalkboard'></i> Supervisor</a>
                <a href="login.php?role=admin" class="nav-role-link nav-role-admin"><i class='bx bx-shield-quarter'></i> Admin</a>
            </div>

            <button type="button" class="menu-toggle" id="menuToggle" aria-label="Open navigation menu">
                <i class='bx bx-menu'></i>
            </button>

        </nav>

    </header>


    <!-- ================= MOBILE MENU ================= -->

    <div class="mobile-menu" id="mobileMenu">

        <div class="mobile-menu-header">
            <a href="index.php" class="mobile-logo">
                <i class='bx bxs-graduation'></i>
                SIPMS
            </a>
            <button type="button" class="menu-close" id="menuClose" aria-label="Close navigation menu">
                <i class='bx bx-x'></i>
            </button>
        </div>

        <div class="mobile-nav">
            <a href="#home" class="mobile-nav-link"><i class='bx bx-home'></i> Home</a>
            <a href="#about" class="mobile-nav-link"><i class='bx bx-info-circle'></i> About</a>
            <a href="#features" class="mobile-nav-link"><i class='bx bx-grid-alt'></i> Features</a>
            <a href="#how-it-works" class="mobile-nav-link"><i class='bx bx-list-check'></i> How It Works</a>
            <a href="#contact" class="mobile-nav-link"><i class='bx bx-phone'></i> Contact</a>

            <div class="mobile-divider"></div>

            <a href="login.php?role=student" class="mobile-role-link"><i class='bx bx-user'></i> Student</a>
            <a href="login.php?role=company" class="mobile-role-link"><i class='bx bx-building'></i> Company</a>
            <a href="login.php?role=supervisor" class="mobile-role-link"><i class='bx bx-chalkboard'></i> Supervisor</a>
            <a href="login.php?role=admin" class="mobile-role-link"><i class='bx bx-shield-quarter'></i> Admin</a>
        </div>

    </div>

    <div class="menu-overlay" id="menuOverlay"></div>


    <!-- ================= HERO ================= -->

    <main>

        <section class="hero" id="home">

            <div class="hero-content">

                <span class="hero-label">Smart Internship Management System</span>

                <h1>Connecting Students <span>With Opportunity</span></h1>

                <p>
                    SIPMS makes internship placement simple, organized,
                    and transparent by connecting students, supervisors,
                    companies, and administrators on one platform.
                </p>

                <div class="hero-buttons">
                    <a href="#roles" class="btn btn-primary">
                        <i class='bx bx-rocket'></i> Get Started
                    </a>
                    <a href="#about" class="btn btn-outline">
                        <i class='bx bx-info-circle'></i> Learn More
                    </a>
                </div>

                <div class="hero-features">
                    <div class="hero-feature"><i class='bx bx-check-circle'></i> Easy to use</div>
                    <div class="hero-feature"><i class='bx bx-check-circle'></i> Centralized management</div>
                    <div class="hero-feature"><i class='bx bx-check-circle'></i> Real-time tracking</div>
                </div>

            </div>

            <div class="hero-visual">

                <div class="dashboard-card">

                    <div class="dashboard-top">
                        <div class="dashboard-dots"><span></span><span></span><span></span></div>
                        <span>SIPMS Dashboard</span>
                    </div>

                    <div class="dashboard-body">

                        <div class="dashboard-sidebar">
                            <div class="mini-logo">SIPMS</div>
                            <div class="mini-menu active"></div>
                            <div class="mini-menu"></div>
                            <div class="mini-menu"></div>
                            <div class="mini-menu"></div>
                            <div class="mini-menu"></div>
                        </div>

                        <div class="dashboard-main">

                            <div class="welcome-box">
                                <small>Welcome back</small>
                                <strong>Student Dashboard</strong>
                            </div>

                            <div class="dashboard-stats">
                                <div class="stat-box"><i class='bx bx-briefcase'></i><strong>12</strong><small>Internships</small></div>
                                <div class="stat-box"><i class='bx bx-file'></i><strong>5</strong><small>Applications</small></div>
                                <div class="stat-box"><i class='bx bx-check-circle'></i><strong>2</strong><small>Placements</small></div>
                            </div>

                            <div class="dashboard-chart">
                                <div class="chart-heading">Application Overview</div>
                                <div class="chart-bars">
                                    <span style="height: 35%;"></span>
                                    <span style="height: 55%;"></span>
                                    <span style="height: 45%;"></span>
                                    <span style="height: 75%;"></span>
                                    <span style="height: 60%;"></span>
                                    <span style="height: 90%;"></span>
                                    <span style="height: 70%;"></span>
                                </div>
                            </div>

                        </div>

                    </div>

                </div>

                <div class="floating-card floating-one">
                    <div class="floating-icon"><i class='bx bx-user-check'></i></div>
                    <div><strong>Placement</strong><small>Successfully matched</small></div>
                </div>

                <div class="floating-card floating-two">
                    <div class="floating-icon"><i class='bx bx-building'></i></div>
                    <div><strong>Companies</strong><small>Internship opportunities</small></div>
                </div>

            </div>

        </section>


        <!-- ================= CHOOSE YOUR ROLE ================= -->

        <section class="roles-section" id="roles">

            <div class="section-heading">
                <span class="section-label">Get Started</span>
                <h2>Choose Your Role</h2>
                <p>Select how you'll be using SIPMS to continue to the right portal.</p>
            </div>

            <div class="role-grid">

                <a href="login.php?role=student" class="role-card">
                    <div class="role-icon"><i class='bx bx-user'></i></div>
                    <h3>Student</h3>
                    <p>Manage your internship, attendance, and tasks.</p>
                    <span class="role-card-cta">Continue <i class='bx bx-right-arrow-alt'></i></span>
                </a>

                <a href="login.php?role=company" class="role-card">
                    <div class="role-icon"><i class='bx bx-building'></i></div>
                    <h3>Company</h3>
                    <p>Post opportunities and manage applications.</p>
                    <span class="role-card-cta">Continue <i class='bx bx-right-arrow-alt'></i></span>
                </a>

                <a href="login.php?role=supervisor" class="role-card">
                    <div class="role-icon"><i class='bx bx-chalkboard'></i></div>
                    <h3>Supervisor</h3>
                    <p>Monitor interns and review their progress.</p>
                    <span class="role-card-cta">Continue <i class='bx bx-right-arrow-alt'></i></span>
                </a>

                <a href="login.php?role=admin" class="role-card role-card-admin">
                    <div class="role-icon"><i class='bx bx-shield-quarter'></i></div>
                    <h3>Admin</h3>
                    <p>Manage the entire system.</p>
                    <span class="role-card-cta">Continue <i class='bx bx-right-arrow-alt'></i></span>
                </a>

            </div>

        </section>


        <!-- ================= ABOUT ================= -->

        <section class="about-section" id="about">

            <div class="about-content">
                <span class="section-label">About SIPMS</span>
                <h2>One platform for the entire internship journey</h2>
                <p>
                    SIPMS replaces scattered spreadsheets and paperwork with a single
                    digital home for internship management — from application to
                    placement, daily attendance, task tracking, and final evaluation.
                    Every role gets exactly the tools and visibility they need.
                </p>

                <div class="about-points">
                    <div class="about-point"><i class='bx bx-check'></i> Centralized student, company, and supervisor records</div>
                    <div class="about-point"><i class='bx bx-check'></i> Transparent application and placement tracking</div>
                    <div class="about-point"><i class='bx bx-check'></i> Real MySQL-backed data — no spreadsheets, no guesswork</div>
                </div>
            </div>

        </section>


        <!-- ================= FEATURES ================= -->

        <section class="features-section" id="features">

            <div class="section-heading">
                <span class="section-label">Features</span>
                <h2>Everything you need, built in</h2>
            </div>

            <div class="features-grid">

                <div class="feature-card">
                    <div class="feature-icon"><i class='bx bx-briefcase'></i></div>
                    <h3>Internship Applications</h3>
                    <p>Students browse opportunities and apply; companies review and respond, all in one flow.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class='bx bx-time-five'></i></div>
                    <h3>Attendance Tracking</h3>
                    <p>Check-in and check-out records give supervisors and admins a clear attendance picture.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class='bx bx-list-check'></i></div>
                    <h3>Task Management</h3>
                    <p>Assign, track, and review internship tasks from start to completion.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class='bx bx-lock-alt'></i></div>
                    <h3>Role-Based Access</h3>
                    <p>Every user only sees and manages what's relevant to their role — securely enforced.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class='bx bx-message-dots'></i></div>
                    <h3>Built-In Messaging</h3>
                    <p>Keep internship-related communication organized and in one place.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class='bx bx-bar-chart-alt-2'></i></div>
                    <h3>Live Dashboards</h3>
                    <p>Every dashboard pulls real-time statistics straight from the database.</p>
                </div>

            </div>

        </section>


        <!-- ================= HOW IT WORKS ================= -->

        <section class="how-section" id="how-it-works">

            <div class="section-heading">
                <span class="section-label">How It Works</span>
                <h2>Three simple steps</h2>
            </div>

            <div class="how-steps">

                <div class="how-step">
                    <div class="how-step-number">1</div>
                    <h3>Choose your role</h3>
                    <p>Pick Student, Company, Supervisor, or Admin and log in or register.</p>
                </div>

                <div class="how-step">
                    <div class="how-step-number">2</div>
                    <h3>Set up your profile</h3>
                    <p>Fill in your details so the right people can find and work with you.</p>
                </div>

                <div class="how-step">
                    <div class="how-step-number">3</div>
                    <h3>Track everything in one place</h3>
                    <p>Applications, attendance, tasks, and messages — all managed from your dashboard.</p>
                </div>

            </div>

        </section>


        <!-- ================= CONTACT ================= -->

        <section class="contact-section" id="contact">

            <div class="section-heading">
                <span class="section-label">Contact</span>
                <h2>Questions about SIPMS?</h2>
                <p>Reach out and we'll get back to you.</p>
            </div>

            <a href="mailto:info@sipms.local" class="btn btn-primary contact-btn">
                <i class='bx bx-envelope'></i> info@sipms.local
            </a>

        </section>

    </main>


    <!-- ================= FOOTER ================= -->

    <footer class="footer">

        <div class="footer-container">

            <div class="footer-brand">
                <a href="index.php" class="logo">
                    <i class='bx bxs-graduation'></i>
                    SIPMS
                </a>
                <p>Smart Internship Management System — connecting students with internship opportunities.</p>
            </div>

            <div class="footer-column">
                <h3>Quick Links</h3>
                <a href="#home">Home</a>
                <a href="#about">About</a>
                <a href="#features">Features</a>
                <a href="#contact">Contact</a>
            </div>

            <div class="footer-column">
                <h3>Portals</h3>
                <a href="login.php?role=student">Student</a>
                <a href="login.php?role=company">Company</a>
                <a href="login.php?role=supervisor">Supervisor</a>
                <a href="login.php?role=admin">Admin</a>
            </div>

            <div class="footer-column">
                <h3>Follow Us</h3>
                <div class="social-links">
                    <a href="#" aria-label="Facebook"><i class='bx bxl-facebook'></i></a>
                    <a href="#" aria-label="Twitter"><i class='bx bxl-twitter'></i></a>
                    <a href="#" aria-label="Instagram"><i class='bx bxl-instagram'></i></a>
                    <a href="#" aria-label="LinkedIn"><i class='bx bxl-linkedin'></i></a>
                </div>
            </div>

        </div>

        <div class="footer-bottom">
            <p>&copy; 2026 SIPMS. All rights reserved.</p>
            <div>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms &amp; Conditions</a>
            </div>
        </div>

    </footer>


    <button type="button" class="back-to-top" id="backToTop" aria-label="Back to top">
        <i class='bx bx-chevron-up'></i>
    </button>


    <script src="js/script.js"></script>

</body>

</html>