<?php
define('APP_START', true);
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maranadhara Samithi - Funeral Aid Society</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-orange: #f97316;
            --secondary-orange: #fb923c;
            --dark-orange: #ea580c;
            --light-orange: #ffedd5;
            --bg-color: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.5);
        }
        .navbar.scrolled {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .hero-section {
            background: linear-gradient(135deg, var(--primary-orange) 0%, var(--dark-orange) 100%);
            position: relative;
            overflow: hidden;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.5;
        }
        .feature-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-orange), var(--secondary-orange));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .feature-card:hover::before {
            transform: scaleX(1);
        }
        .btn-primary {
            background: var(--primary-orange);
            color: white;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .btn-primary:hover {
            background: var(--dark-orange);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        .animate-fade-up {
            animation: fadeUp 0.6s ease-out forwards;
        }
        .animate-fade-in {
            animation: fadeIn 0.6s ease-out forwards;
        }
        .animate-slide-in {
            animation: slideIn 0.6s ease-out forwards;
        }
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        .contact-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .footer {
            background: var(--card-bg);
            border-top: 1px solid var(--border-color);
        }
    </style>
</head>
<body class="font-sans">
<!-- Navbar -->
<nav class="navbar fixed w-full z-50 transition-all duration-300">
    <div class="container mx-auto px-6 py-4">
        <div class="flex justify-between items-center">
            <a href="index.php" class="text-2xl font-bold text-orange-600 flex items-center space-x-2 hover:text-orange-700 transition-colors duration-300">
                <i class="fas fa-hands-helping"></i>
                <span>Maranadhara Samithi</span>
            </a>
            <div class="flex items-center space-x-4">
                <a href="login.php" class="btn-primary px-6 py-2 rounded-lg font-semibold flex items-center space-x-2">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Enter System</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<header class="hero-section text-white pt-32 pb-24 relative">
    <div class="container mx-auto px-6">
        <div class="max-w-4xl mx-auto text-center">
            <h1 class="text-5xl md:text-6xl font-bold mb-6 animate-fade-up">මරණාධාර සමිතිය</h1>
            <p class="text-xl md:text-2xl opacity-90 mb-8 animate-fade-up" style="animation-delay: 0.2s">
                Supporting our community in times of need with funeral aid and solidarity.
            </p>
            <div class="animate-fade-up" style="animation-delay: 0.4s">
                <a href="login.php" class="btn-primary px-8 py-3 rounded-lg font-semibold text-lg inline-flex items-center space-x-2">
                    <i class="fas fa-arrow-right"></i>
                    <span>Enter the System</span>
                </a>
            </div>
        </div>
    </div>
</header>

<!-- About Section -->
<section class="py-20 bg-white">
    <div class="container mx-auto px-6">
        <h2 class="text-4xl font-bold text-center mb-16 animate-fade-up">About Maranadhara Samithi</h2>
        <div class="grid md:grid-cols-2 gap-8 max-w-5xl mx-auto">
            <div class="feature-card p-8 rounded-xl">
                <div class="text-orange-600 text-4xl mb-6">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <h3 class="text-2xl font-semibold mb-4">Funeral Assistance</h3>
                <p class="text-gray-600 leading-relaxed">
                    We provide comprehensive funeral assistance through our collective member contributions, 
                    ensuring dignified farewells for our community members.
                </p>
            </div>
            <div class="feature-card p-8 rounded-xl">
                <div class="text-orange-600 text-4xl mb-6">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="text-2xl font-semibold mb-4">Community Hub</h3>
                <p class="text-gray-600 leading-relaxed">
                    Our society serves as a vital community hub, fostering strong bonds through regular 
                    gatherings and mutual support initiatives.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section class="py-20 bg-gray-50">
    <div class="container mx-auto px-6">
        <h2 class="text-4xl font-bold text-center mb-16 animate-fade-up">Get in Touch</h2>
        <div class="max-w-lg mx-auto">
            <div class="contact-card p-8 rounded-xl">
                <div class="space-y-6">
                    <div class="flex items-center space-x-4">
                        <div class="text-orange-600 text-xl">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold">Our Location</h3>
                            <p class="text-gray-600">101 Webada Rd, Sri Lanka</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-orange-600 text-xl">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold">Phone Number</h3>
                            <p class="text-gray-600">+94 123 456 789</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-orange-600 text-xl">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold">Email Address</h3>
                            <p class="text-gray-600">info@maranadhara.lk</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer py-12">
    <div class="container mx-auto px-6">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div class="mb-6 md:mb-0">
                <p class="text-gray-600">© 2025 Maranadhara Samithi. All rights reserved.</p>
            </div>
            <div class="flex space-x-8">
                <a href="#" class="text-gray-600 hover:text-orange-600 transition-colors duration-300">Privacy Policy</a>
                <a href="#" class="text-gray-600 hover:text-orange-600 transition-colors duration-300">Terms of Service</a>
                <a href="#" class="text-gray-600 hover:text-orange-600 transition-colors duration-300">Contact</a>
            </div>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Navbar scroll effect
    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // Animate elements on scroll
    const observerOptions = {
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade-up');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.feature-card, .contact-card').forEach(el => {
        observer.observe(el);
    });
});
</script>
</body>
</html>