<footer class="footer">
    <div class="footer-container">
        <div class="footer-content">
            <p class="footer-text">© <?php echo date('Y'); ?> Maranadhara Samithi. All rights reserved.</p>
            <div class="footer-links">
                <a href="../../index.php" class="footer-link">Home</a>
                <a href="#" class="footer-link">Privacy Policy</a>
                <a href="#" class="footer-link">Contact Us</a>
            </div>
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
        padding: 32px 0;
        margin-top: auto;
        font-family: 'Inter', sans-serif;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
    }

    .footer-container {
        max-width: 1280px;
        margin: 0 auto;
        padding: 0 16px;
    }

    .footer-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 16px;
    }

    .footer-text {
        color: var(--text-secondary);
        font-size: 14px;
        font-weight: 500;
    }

    .footer-links {
        display: flex;
        gap: 24px;
    }

    .footer-link {
        color: var(--primary-color);
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        transition: var(--transition);
    }

    .footer-link:hover {
        color: var(--primary-hover);
        text-decoration: underline;
    }

    @media (min-width: 768px) {
        .footer-content {
            flex-direction: row;
            justify-content: space-between;
        }
    }

    @media (max-width: 768px) {
        .footer {
            padding: 24px 0;
        }

        .footer-text,
        .footer-link {
            font-size: 13px;
        }

        .footer-links {
            gap: 16px;
        }
    }
</style>