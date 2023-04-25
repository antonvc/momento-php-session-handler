<?php
require("MomentoSessionHandler.php");

//create the custom PHP handler powered by Momento!
$handler = new MomentoSessionHandler();
session_set_save_handler($handler, true);

//start the session
session_start();

// Handle form submission
if (isset($_POST['name']) && !empty($_POST['name'])) {
    $_SESSION['user'] = $_POST['name'];
    //redirect to show cache hit
    header("Location: /");
    exit;
}

// Handle logout
if (isset($_POST['logout'])) {
    unset($_SESSION['user']);
    //redirect to show cache miss
    header("Location: /");
    exit;
}

// Get container ID
$container_id = gethostname();

//get session ID
$session_id = session_id();

// Set message
$message = isset($_SESSION['user']) ? "Welcome " . $_SESSION['user'] : 'Please enter your name:';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Momento - PHP Demo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background-color: #ffffff;
            border-radius: 5px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08);
            width: 400px;
            max-width: 95%;
        }
        h3 {
            margin-top: 0;
        }
        form {
            margin-top: 1rem;
        }
        input[type="text"] {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 1rem;
            font-size: 1rem;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        /*input type submit or button*/
        input[type="submit"],input[type="button"] {
            background-color: #007bff;
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            font-size: 1rem;
            border-radius: 3px;
            cursor: pointer;
        }
        input[type="submit"]:hover,input[type="button"]:hover {
            background-color: #0056b3;
        }
        .container-id {
            margin-top: 1rem;
            font-size: 0.8rem;
            overflow: hidden;
        }
        .description {
            font-size: 0.9rem;
            text-align: justify;
            margin-bottom: 1rem;
            width: 380px;
            max-width: 90%;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>PHP sessions with Momento!</h2>
        <div class="description">
            <p>This demo demonstrates a serverless session cache using Momento as the session handler. When you enter your name, it will be saved in the session cache. The demo also showcases session stickiness when run on different containers by displaying the container ID.</p>
        </div>
        <h3><?php echo $message; ?></h3>
        <?php if (!isset($_SESSION['user'])): ?>
        <form action="" method="post">
            <input type="text" name="name" placeholder="Your name">
            <input type="submit" value="Submit">
        </form>
        <?php else: ?>
        <form action="" method="post">
            <input type="button" name="refresh" value="Refresh" onclick="location.reload();">
            <input type="submit" name="logout" value="Logout">
        </form>
        <?php endif; ?>
        <div class="container-id">
            <strong>Container Hostname:</strong> <?=$container_id?> <br />
            <strong>Session ID:</strong> <?=$session_id?>
            <p>
                <code>momento cache get --key <?=$session_id?> --cache <?=getenv('MOMENTO_SESSION_CACHE')?:"php-sessions"?></code>
            </p>
        </div>
       
    </div>
</body>
</html>