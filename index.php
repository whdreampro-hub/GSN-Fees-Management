<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$current_year = getCurrentYear($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GS Nyagisozi - Fees Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .landing-container {
            max-width: 1000px;
            width: 90%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            animation: fadeIn 0.8s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .hub-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 3rem;
            border-radius: 32px;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            color: white;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .hub-card:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.02);
            border-color: var(--accent-color);
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.5);
        }
        .hub-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            display: block;
        }
        .hub-card h2 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 1rem;
            letter-spacing: -1px;
        }
        .hub-card p {
            color: #94a3b8;
            font-size: 1rem;
            line-height: 1.6;
        }
        .system-badge {
            position: absolute;
            top: 2rem;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1.5rem;
            border-radius: 99px;
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.85rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        @media (max-width: 768px) {
            .landing-container { grid-template-columns: 1fr; }
            body { overflow-y: auto; padding: 4rem 0; }
        }
    </style>
</head>
<body>
    <div class="system-badge">
        GS Nyagisozi Finance Hub • <?php echo $current_year; ?>
    </div>

    <div class="landing-container">
        <!-- Student Portal -->
        <a href="portal.php" class="hub-card">
            <span class="hub-icon">🎓</span>
            <h2>Student Portal</h2>
            <p>Access your academic financial journey, check balances, and submit assistance requests directly to administration.</p>
        </a>

        <!-- Admin Portal -->
        <a href="login.php" class="hub-card" style="border-color: rgba(79, 70, 229, 0.2);">
            <span class="hub-icon">💼</span>
            <h2>Administrator</h2>
            <p>Secure workspace for financial management, class enrollments, fee collection tracking, and professional reporting.</p>
        </a>
    </div>

    <div style="position: absolute; bottom: 2rem; color: #475569; font-size: 0.8rem; font-weight: 500;">
        Built for Professional Academic Financial Management
    </div>
</body>
</html>
