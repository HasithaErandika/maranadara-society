<footer class="footer">
    <div class="footer-container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Us</h3>
                <div class="footer-links">
                    <a href="about.php" class="footer-link">
                        <i class="fas fa-info-circle"></i>
                        About Maranadara Society
                    </a>
                    <a href="mission.php" class="footer-link">
                        <i class="fas fa-bullseye"></i>
                        Our Mission
                    </a>
                    <a href="team.php" class="footer-link">
                        <i class="fas fa-users"></i>
                        Our Team
                    </a>
                </div>
            </div>

            <div class="footer-section">
                <h3>Quick Links</h3>
                <div class="footer-links">
                    <a href="index.php" class="footer-link">
                        <i class="fas fa-home"></i>
                        Home
                    </a>
                    <a href="events.php" class="footer-link">
                        <i class="fas fa-calendar-alt"></i>
                        Events
                    </a>
                    <a href="news.php" class="footer-link">
                        <i class="fas fa-newspaper"></i>
                        News
                    </a>
                </div>
            </div>

            <div class="footer-section">
                <h3>Support</h3>
                <div class="footer-links">
                    <a href="contact.php" class="footer-link">
                        <i class="fas fa-envelope"></i>
                        Contact Us
                    </a>
                    <a href="faq.php" class="footer-link">
                        <i class="fas fa-question-circle"></i>
                        FAQ
                    </a>
                    <a href="privacy.php" class="footer-link">
                        <i class="fas fa-shield-alt"></i>
                        Privacy Policy
                    </a>
                </div>
            </div>

            <div class="footer-section">
                <h3>Connect With Us</h3>
                <div class="footer-social">
                    <a href="#" class="social-link" title="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="social-link" title="Twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="social-link" title="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="social-link" title="LinkedIn">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p class="footer-copyright">
                &copy; <?php echo date('Y'); ?> Maranadara Society. All rights reserved. 
                <a href="terms.php">Terms of Service</a>
            </p>
        </div>
    </div>
</footer>

<style>
    :root {
        --primary-color: #F97316;
        --primary-hover: #C2410C;
        --bg-color: #F9FAFB;
        --card-bg: #FFFFFF;
        --text-primary: #1F2A44;
        --text-secondary: #6B7280;
        --shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        --transition: all 0.3s ease;
    }

    .footer {
        background: var(--card-bg);
        padding: 40px 0 20px;
        margin-top: 60px;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
    }

    .footer-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .footer-content {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 40px;
        margin-bottom: 40px;
    }

    .footer-section {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .footer-section h3 {
        color: var(--text-primary);
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .footer-links {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .footer-link {
        color: var(--text-secondary);
        text-decoration: none;
        font-size: 14px;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .footer-link:hover {
        color: var(--primary-color);
        transform: translateX(4px);
    }

    .footer-link i {
        font-size: 16px;
    }

    .footer-social {
        display: flex;
        gap: 16px;
    }

    .social-link {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--bg-color);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-primary);
        text-decoration: none;
        transition: var(--transition);
    }

    .social-link:hover {
        background: var(--primary-color);
        color: #FFFFFF;
        transform: translateY(-2px);
    }

    .footer-bottom {
        padding-top: 20px;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .footer-copyright {
        color: var(--text-secondary);
        font-size: 14px;
    }

    .footer-copyright a {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 600;
    }

    .footer-copyright a:hover {
        text-decoration: underline;
    }

    @media (max-width: 1024px) {
        .footer-content {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 640px) {
        .footer {
            padding: 30px 0 20px;
        }

        .footer-content {
            grid-template-columns: 1fr;
            gap: 30px;
        }

        .footer-section h3 {
            font-size: 16px;
        }

        .footer-link {
            font-size: 13px;
        }

        .social-link {
            width: 32px;
            height: 32px;
        }
    }
</style>