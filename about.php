<?php
session_start();

// Handle Contact Form Submission
$form_message = '';
$form_message_type = '';

if (isset($_POST['submit_comment'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $comment = trim($_POST['comment']);

    if (!empty($name) && !empty($email) && !empty($comment) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $log_file = __DIR__ . '/comments.json';

        $comments = [];
        if (file_exists($log_file)) {
            $comments_content = file_get_contents($log_file);
            if (!empty($comments_content)) {
                $comments = json_decode($comments_content, true);
                if (!is_array($comments)) {
                    $comments = []; // Reset if content is not a valid JSON array
                }
            }
        }

        $new_comment = [
            'name' => $name,
            'email' => $email,
            'comment' => $comment,
            'timestamp' => gmdate('Y-m-d H:i:s') . ' UTC'
        ];

        $comments[] = $new_comment;

        if (file_put_contents($log_file, json_encode($comments, JSON_PRETTY_PRINT))) {
            $form_message = "Thank you for your feedback!";
            $form_message_type = 'success';
        } else {
            $form_message = "Error: Could not save your comment. Please try again later.";
            $form_message_type = 'error';
        }
    } else {
        $form_message = "Error: Please fill out all fields with valid information.";
        $form_message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>About Us</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background-color: #f0f2f5;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 900px;
            margin: 2em auto;
            padding: 2em;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.1);
        }
        h1, h2 {
            border-bottom: 1px solid #e5e5e5;
            padding-bottom: 10px;
            margin-bottom: 1em;
        }
        .content-section {
            margin-bottom: 2.5em;
        }
        
        /* FAQ Styles */
        .faq-item {
            border-bottom: 1px solid #eee;
        }
        .faq-question {
            background: none;
            border: none;
            width: 100%;
            text-align: left;
            padding: 18px 0;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            position: relative;
            outline: none;
        }
        .faq-question::after {
            content: '+';
            position: absolute;
            right: 10px;
            font-size: 1.5em;
            color: #e27979;
            transition: transform 0.2s;
        }
        .faq-question.active::after {
            transform: rotate(45deg);
        }
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            padding: 0 10px;
        }
        .faq-answer p {
            margin-top: 0;
            padding-bottom: 20px;
            color: #555;
        }

        /* Contact Form Styles */
        .form-row {
            margin-bottom: 1em;
        }
        .form-row label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .form-row input, .form-row textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1em;
            box-sizing: border-box;
        }
        .form-row textarea {
            min-height: 120px;
            resize: vertical;
        }
        .form-message {
            padding: 1em;
            border-radius: 6px;
            margin-bottom: 1em;
        }
        .form-message.success {
            background: #d4edda; color: #155724; border: 1px solid #c3e6cb;
        }
        .form-message.error {
            background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;
        }
        button { 
            background-color: #e27979; color: white; border: none; padding: 12px 25px; 
            border-radius: 6px; font-size: 1em; font-weight: 600; cursor: pointer; transition: background-color 0.2s; 
        }
        button:hover { background-color: #d66a6a; }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbarbtns">
            <div class="navbtn"><a href="home.php">home</a></div>
            <div class="navbtn"><a href="about.php">about</a></div>
            <div class="navbtn"><a href="studio3.php">studio</a></div>
            <div class="navbtn"><a href="database.php">database</a></div>
        </div>
    </div>

    <div class="container">
        <div class="content-section">
            <h1>About This Project</h1>
            <p>Welcome to the Digital Artist Database, a community-driven platform designed to showcase the work of digital artists from around the world. Our mission is to provide a space where artists can create portfolios, share their creations, and connect with a global audience.</p>
            <p>Whether you're into digital painting, 3D modeling, audio production, or any other form of digital art, this is the place to get inspired and be seen.</p>
        </div>

        <div class="content-section">
            <h2>Frequently Asked Questions</h2>
            <div class="faq-container">
                <div class="faq-item">
                    <button class="faq-question">How do I create a profile?</button>
                    <div class="faq-answer">
                        <p>You can create your own profile by visiting the <a href="register.php">Register</a> page. Once you sign up, you'll be able to log in and use the <a href="studio3.php">Studio</a> to upload your work and edit your profile details.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question">What kind of files can I upload?</button>
                    <div class="faq-answer">
                        <p>Currently, you can upload images (JPG, PNG, GIF) and audio files (MP3). We have a file size limit of 5MB per upload to ensure the platform remains fast and accessible for everyone.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question">How does the 'Collection' feature work?</button>
                    <div class="faq-answer">
                        <p>When you are logged in, you can add other artists' work to your personal collection by clicking the 'select' radio button when viewing a piece of art. This allows you to curate a gallery of your favorite pieces from across the community, which you can view in your Studio.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-section">
            <h2>Contact Us</h2>
            <p>Have questions or feedback? Fill out the form below and we'll get back to you.</p>
            <form id="contact-form" method="POST" action="about.php">
                <?php if ($form_message): ?>
                    <div class="form-message <?php echo $form_message_type; ?>">
                        <?php echo htmlspecialchars($form_message); ?>
                    </div>
                <?php endif; ?>
                <div class="form-row">
                    <label for="name">Name</label>
                    <input id="name" type="text" name="name" required>
                </div>
                <div class="form-row">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" required>
                </div>
                <div class="form-row">
                    <label for="comment">Comment</label>
                    <textarea id="comment" name="comment" required></textarea>
                </div>
                <button type="submit" name="submit_comment">Submit</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const faqQuestions = document.querySelectorAll('.faq-question');

            faqQuestions.forEach(button => {
                button.addEventListener('click', () => {
                    const answer = button.nextElementSibling;
                    button.classList.toggle('active');

                    if (button.classList.contains('active')) {
                        answer.style.maxHeight = answer.scrollHeight + 'px';
                    } else {
                        answer.style.maxHeight = '0px';
                    }
                });
            });

            // If form was submitted, prevent resubmission on refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
    </script>
</body>
</html>
