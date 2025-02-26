<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}
require_once '../../classes/Member.php';
$member = new Member();
$members = $member->getMembers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Maranadhara Samithi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f3f4f6;
            --text-color: #1f2937;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --btn-bg: #d35400; /* Admin orange */
            --btn-hover: #b84500;
            --border-color: #d1d5db;
        }
        [data-theme="dark"] {
            --bg-color: #1f2937;
            --text-color: #f3f4f6;
            --card-bg: #374151;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            --btn-bg: #e67e22;
            --btn-hover: #f39c12;
            --border-color: #4b5563;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s ease, color 0.3s ease;
            font-family: 'Noto Sans', sans-serif;
        }
        .card {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .btn-admin {
            background-color: var(--btn-bg);
            transition: all 0.3s ease;
        }
        .btn-admin:hover {
            background-color: var(--btn-hover);
            transform: scale(1.05);
        }
        .table-hover tbody tr:hover {
            background-color: #fef5e7; /* Light saffron */
        }
        .sidebar {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
        }
    </style>
</head>
<body class="bg-gray-100">
<!-- Navbar -->
<nav class="shadow-lg fixed w-full z-10 top-0 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-orange-600 flex items-center">
            <i class="fas fa-hands-helping mr-2"></i>Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-6">
            <span class="text-gray-700 dark:text-gray-300">Welcome, <?php echo $_SESSION['user']; ?></span>
            <a href="../login.php?logout=1" class="text-white px-5 py-2 rounded-lg btn-admin">Logout</a>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="flex min-h-screen pt-20">
    <!-- Sidebar -->
    <aside class="w-64 sidebar p-6 fixed h-full">
        <h3 class="text-xl font-bold mb-6 text-orange-600">Admin Menu</h3>
        <ul class="space-y-4">
            <li><a href="add_member.php" class="text-gray-700 dark:text-gray-300 hover:text-orange-600 flex items-center"><i class="fas fa-user-plus mr-2"></i>Add Member</a></li>
            <li><a href="incidents.php" class="text-gray-700 dark:text-gray-300 hover:text-orange-600 flex items-center"><i class="fas fa-file-alt mr-2"></i>Record Incident</a></li>
            <li><a href="payments.php" class="text-gray-700 dark:text-gray-300 hover:text-orange-600 flex items-center"><i class="fas fa-money-bill mr-2"></i>Manage Payments</a></li>
            <li><a href="loans.php" class="text-gray-700 dark:text-gray-300 hover:text-orange-600 flex items-center"><i class="fas fa-hand-holding-usd mr-2"></i>Manage Loans</a></li>
        </ul>
    </aside>

    <!-- Dashboard Content -->
    <main class="flex-1 p-6 ml-64">
        <h1 class="text-3xl font-bold mb-6 text-orange-600">Admin Dashboard</h1>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Welcome Card -->
            <div class="card p-6 rounded-xl">
                <h2 class="text-xl font-semibold mb-2">Welcome, <?php echo $_SESSION['user']; ?>!</h2>
                <p class="text-gray-600 dark:text-gray-400">Manage funeral aid and community support for Maranadhara Samithi.</p>
                <a href="add_member.php" class="mt-4 inline-block text-white px-4 py-2 rounded-lg btn-admin">Add New Member</a>
            </div>

            <!-- Quick Stats Card -->
            <div class="card p-6 rounded-xl">
                <h2 class="text-xl font-semibold mb-2">Quick Stats</h2>
                <p class="text-gray-600 dark:text-gray-400">Total Members: <?php echo count($members); ?></p>
                <p class="text-gray-600 dark:text-gray-400">More stats coming soon...</p>
            </div>
        </div>

        <!-- Members Table -->
        <div class="mt-6 card p-6 rounded-xl">
            <h2 class="text-xl font-semibold mb-4">Members</h2>
            <div class="overflow-x-auto">
                <table class="w-full table-hover">
                    <thead>
                    <tr class="border-b dark:border-gray-600">
                        <th class="py-2 px-4 text-left">Name</th>
                        <th class="py-2 px-4 text-left">Contact</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($members as $m): ?>
                        <tr class="border-b dark:border-gray-600">
                            <td class="py-2 px-4"><?php echo htmlspecialchars($m['name']); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($m['contact']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="2" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No members yet.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Community Updates Placeholder -->
        <div class="mt-6 card p-6 rounded-xl">
            <h2 class="text-xl font-semibold mb-2">Community Updates</h2>
            <p class="text-gray-600 dark:text-gray-400">Coming soon: Announcements and community events.</p>
        </div>
    </main>
</div>

<!-- Footer -->
<footer class="py-8 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6">
        <p class="text-center text-gray-600 dark:text-gray-400">© 2025 Maranadhara Samithi. All rights reserved.</p>
    </div>
</footer>
</body>
</html>