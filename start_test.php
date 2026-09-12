<?php
session_start();

if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

$studentName = $_SESSION['user_name'] ?? 'Student';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Monitor AI - Start Test
    </title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {

            font-family: Arial, sans-serif;

            min-height: 100vh;

            display: flex;
            justify-content: center;
            align-items: center;

            background:
                linear-gradient(
                    135deg,
                    #0f172a,
                    #1e3a8a
                );

            color: white;
        }

        .start-card {

            width: 90%;
            max-width: 550px;

            background: white;
            color: #1f2937;

            padding: 40px;

            border-radius: 15px;

            box-shadow:
                0 15px 40px
                rgba(0,0,0,0.3);

            text-align: center;
        }

        .start-card h1 {

            color: #1e3a8a;

            margin-bottom: 15px;
        }

        .welcome {

            font-size: 18px;

            margin-bottom: 25px;
        }

        .instructions {

            text-align: left;

            background: #f3f4f6;

            padding: 20px;

            border-radius: 10px;

            margin-bottom: 25px;

            line-height: 1.7;
        }

        .instructions h3 {

            margin-bottom: 10px;

            color: #111827;
        }

        .instructions ul {

            padding-left: 20px;
        }

        .start-btn {

            display: inline-block;

            padding: 14px 35px;

            border: none;

            border-radius: 8px;

            background: #2563eb;

            color: white;

            font-size: 17px;

            font-weight: bold;

            cursor: pointer;

            text-decoration: none;

            transition: 0.2s;
        }

        .start-btn:hover {

            background: #1d4ed8;

            transform:
                translateY(-1px);
        }

        .note {

            margin-top: 15px;

            color: #6b7280;

            font-size: 13px;
        }

    </style>
</head>

<body>

    <div class="start-card">

        <h1>
            Monitor AI
        </h1>

        <div class="welcome">

            Welcome,
            <strong>
                <?php echo htmlspecialchars($studentName); ?>
            </strong>

        </div>

        <div class="instructions">

            <h3>
                Test Instructions
            </h3>

            <ul>

                <li>
                    Keep your webcam enabled during the test.
                </li>

                <li>
                    Make sure your face is clearly visible.
                </li>

                <li>
                    Do not switch browser tabs.
                </li>

                <li>
                    Do not copy, paste or use right-click.
                </li>

                <li>
                    Multiple persons should not appear in the camera.
                </li>

                <li>
                    Monitor AI will monitor the test session.
                </li>

            </ul>

        </div>

        <a
            href="exam.php"
            class="start-btn"
        >
            Start Test
        </a>

        <div class="note">

            Camera monitoring will begin
            after you start the test.

        </div>

    </div>

</body>

</html>