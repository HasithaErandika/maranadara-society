<?php
define('APP_START', true);
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maranadhara Samithi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f3f4f6;
            --text-color: #1f2937;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --nav-bg: #ffffff;
            --btn-bg: #d35400;
            --btn-hover: #b84500;
            --border-color: #d1d5db;
        }
        [data-theme="dark"] {
            --bg-color: #1f2937;
            --text-color: #f3f4f6;
            --card-bg: #374151;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            --nav-bg: #111827;
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
        nav, footer {
            background-color: var(--nav-bg);
        }
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropdown:hover .dropdown-menu {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease-in-out;
            min-width: 160px;
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 0.5rem;
            z-index: 10;
        }
        .dropdown-menu a {
            display: block;
            padding: 0.75rem 1rem;
            color: var(--text-color);
            text-decoration: none;
        }
        .dropdown-menu a:hover {
            background-color: #fef5e7;
            color: #d35400;
        }
        .feature-card {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .hero-cta {
            background-color: var(--btn-bg);
            transition: all 0.3s ease;
        }
        .hero-cta:hover {
            background-color: var(--btn-hover);
            transform: scale(1.05);
        }
        .hero-section {
            background: linear-gradient(135deg, #d35400 0%, #7f3300 100%);
            clip-path: polygon(0 0, 100% 0, 100% 85%, 0 100%);
        }
    </style>
</head>
<body class="font-sans">
<!-- Navbar with Theme Toggle and Login Dropdown -->
<nav class="shadow-lg fixed w-full z-10 top-0">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="index.php" class="text-2xl font-bold text-orange-600 flex items-center">
            <i class="fas fa-hands-helping mr-2"></i>Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-6">
            <div class="dropdown">
                <button class="text-white px-5 py-2 rounded-lg hover:bg-orange-700 transition-colors duration-300 flex items-center hero-cta">
                    <i class="fas fa-user mr-2"></i>Login
                </button>
                <div class="dropdown-menu">
                    <a href="pages/login.php?role=user">Member Login</a>
                    <a href="pages/login.php?role=admin">Admin Login</a>
                </div>
            </div>
            <button id="theme-toggle" class="toggle-btn text-2xl p-2 rounded-full bg-gray-200 dark:bg-gray-700">
                <i class="fas fa-sun"></i>
            </button>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<header class="hero-section text-white text-center py-24 mt-16">
    <h1 class="text-4xl md:text-5xl font-bold mb-4 animate-fade-in">මරණාධාර සමිතිය</h1>
    <p class="text-lg md:text-xl opacity-90 max-w-2xl mx-auto">Supporting our community in times of need with funeral aid and solidarity.</p>
    <div class="mt-8 flex justify-center space-x-4">
        <a href="pages/login.php" class="text-white px-6 py-3 rounded-lg font-semibold hero-cta shadow-md">Join or Login</a>
    </div>
</header>

<!-- About Section -->
<section class="container mx-auto px-6 my-20">
    <h2 class="text-3xl md:text-4xl font-bold text-center mb-12">About Maranadhara Samithi</h2>
    <div class="grid md:grid-cols-2 gap-8">
        <div class="p-6 rounded-xl text-center feature-card bg-white dark:bg-gray-700 shadow-lg">
            <i class="fas fa-hand-holding-heart text-orange-600 text-3xl mb-4"></i>
            <h3 class="text-xl font-semibold">Funeral Assistance</h3>
            <p class="mt-2">We ease the financial burden of funerals through collective member contributions.</p>
        </div>
        <div class="p-6 rounded-xl text-center feature-card bg-white dark:bg-gray-700 shadow-lg">
            <i class="fas fa-users text-orange-600 text-3xl mb-4"></i>
            <h3 class="text-xl font-semibold">Community Hub</h3>
            <p class="mt-2">Strengthening bonds with gatherings and mutual support for all members.</p>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section class="container mx-auto px-6 my-20">
    <h2 class="text-3xl md:text-4xl font-bold text-center mb-12">Get in Touch</h2>
    <div class="max-w-lg mx-auto p-6 rounded-xl feature-card bg-white dark:bg-gray-700 shadow-lg">
        <p class="text-center mb-4">Have questions or want to join? Contact us!</p>
        <p><i class="fas fa-map-marker-alt mr-2"></i>101 Webada Rd, Sri Lanka</p>
        <p><i class="fas fa-phone mr-2"></i>+94 123 456 789</p>
        <p><i class="fas fa-envelope mr-2"></i>info@maranadhara.lk</p>
    </div>
</section>

<!-- Footer -->
<footer class="py-8">
    <div class="container mx-auto px-6">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <p class="mb-4 md:mb-0">© 2025 Maranadhara Samithi. All rights reserved.</p>
            <div class="flex space-x-6">
                <a href="#" class="hover:text-orange-400 transition-colors duration-300">Privacy Policy</a>
                <a href="#" class="hover:text-orange-400 transition-colors duration-300">Terms of Service</a>
                <a href="#" class="hover:text-orange-400 transition-colors duration-300">Contact</a>
            </div>
        </div>
    </div>
</footer>

<script>
    const toggleButton = document.getElementById('theme-toggle');
    const body = document.body;
    const sunIcon = '<i class="fas fa-sun"></i>';
    const moonIcon = '<i class="fas fa-moon"></i>';

    if (localStorage.getItem('theme') === 'dark') {
        body.setAttribute('data-theme', 'dark');
        toggleButton.innerHTML = sunIcon;
    } else {
        body.removeAttribute('data-theme');
        toggleButton.innerHTML = moonIcon;
    }

    toggleButton.addEventListener('click', () => {
        if (body.getAttribute('data-theme') === 'dark') {
            body.removeAttribute('data-theme');
            toggleButton.innerHTML = moonIcon;
            localStorage.setItem('theme', 'light');
        } else {
            body.setAttribute('data-theme', 'dark');
            toggleButton.innerHTML = sunIcon;
            localStorage.setItem('theme', 'dark');
        }
    });

    window.addEventListener('load', () => {
        document.querySelector('h1').classList.add('animate-fade-in');
    });
</script>
</body>
</html>