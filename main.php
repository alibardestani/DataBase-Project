<?php
$mysqli = new mysqli("localhost", "root", "", "linkdin-db");

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Create tables if not exists
$createTablesQuery = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    location VARCHAR(100),
    profile_language VARCHAR(50),
    current_company VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    intro TEXT,
    about TEXT,
    featured TEXT,
    background TEXT,
    skills TEXT,
    accomplishments TEXT,
    additional_info TEXT,
    supported_languages TEXT,
    year INT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    post_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (post_id) REFERENCES posts(id)
);

CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    post_id INT NOT NULL,
    content TEXT NOT NULL,
    parent_comment_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (post_id) REFERENCES posts(id)
);

CREATE TABLE IF NOT EXISTS comment_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    comment_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (comment_id) REFERENCES comments(id)
);

CREATE TABLE IF NOT EXISTS shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    post_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (post_id) REFERENCES posts(id)
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    contact_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (contact_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user1_id) REFERENCES users(id),
    FOREIGN KEY (user2_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    FOREIGN KEY (sender_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    inviter_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (inviter_id) REFERENCES users(id)
);
";

if (!$mysqli->multi_query($createTablesQuery)) {
    die("Error creating tables: " . $mysqli->error);
}

// Close all previous results to avoid "Commands out of sync" error
while ($mysqli->more_results() && $mysqli->next_result()) {
    // nothing to do here, just free up the result set
}

// Function to create or update a profile
function createOrUpdateProfile($mysqli, $userId) {
    $intro = readlineInput("Enter Intro: ");
    $about = readlineInput("Enter About: ");
    $featured = readlineInput("Enter Featured: ");
    $background = readlineInput("Enter Background: ");
    $skills = readlineInput("Enter Skills: ");
    $accomplishments = readlineInput("Enter Accomplishments: ");
    $additional_info = readlineInput("Enter Additional Information: ");
    $supported_languages = readlineInput("Enter Supported Languages: ");
    $year = (int)readlineInput("Enter Year: ");

    $profileQuery = "INSERT INTO profiles (user_id, intro, about, featured, background, skills, accomplishments, additional_info, supported_languages, year) VALUES 
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
    intro=VALUES(intro), about=VALUES(about), featured=VALUES(featured), background=VALUES(background), skills=VALUES(skills), accomplishments=VALUES(accomplishments), additional_info=VALUES(additional_info), supported_languages=VALUES(supported_languages), year=VALUES(year)";

    $stmt = $mysqli->prepare($profileQuery);
    $stmt->bind_param("issssssssi", $userId, $intro, $about, $featured, $background, $skills, $accomplishments, $additional_info, $supported_languages, $year);

    if ($stmt->execute()) {
        echo "Profile created or updated successfully\n";
    } else {
        echo "Error creating or updating profile: " . $stmt->error . "\n";
    }
}

// Function to get user ID based on username
function getUserId($mysqli, $username) {
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['id'];
    } else {
        $stmt->close();
        return null;
    }
}

// Function to create a post
function createPost($mysqli, $userId, $content) {
    $postQuery = "INSERT INTO posts (user_id, content) VALUES (?, ?)";
    $stmt = $mysqli->prepare($postQuery);
    $stmt->bind_param("is", $userId, $content);

    if ($stmt->execute()) {
        echo "Post created successfully\n";
    } else {
        echo "Error creating post: " . $stmt->error . "\n";
    }
}

// Function to like a post
function likePost($mysqli, $userId, $postId) {
    $likeQuery = "INSERT INTO likes (user_id, post_id) VALUES (?, ?)";
    $stmt = $mysqli->prepare($likeQuery);
    $stmt->bind_param("ii", $userId, $postId);

    if ($stmt->execute()) {
        echo "Post liked successfully\n";
    } else {
        echo "Error liking post: " . $stmt->error . "\n";
    }
}

// Function to comment on a post
function commentOnPost($mysqli, $userId, $postId, $content, $parentCommentId = null) {
    $commentQuery = "INSERT INTO comments (user_id, post_id, content, parent_comment_id) VALUES (?, ?, ?, ?)";
    $stmt = $mysqli->prepare($commentQuery);
    $stmt->bind_param("iisi", $userId, $postId, $content, $parentCommentId);

    if ($stmt->execute()) {
        echo "Comment added successfully\n";
    } else {
        echo "Error adding comment: " . $stmt->error . "\n";
    }
}

// Function to like a comment
function likeComment($mysqli, $userId, $commentId) {
    $likeQuery = "INSERT INTO comment_likes (user_id, comment_id) VALUES (?, ?)";
    $stmt = $mysqli->prepare($likeQuery);
    $stmt->bind_param("ii", $userId, $commentId);

    if ($stmt->execute()) {
        echo "Comment liked successfully\n";
    } else {
        echo "Error liking comment: " . $stmt->error . "\n";
    }
}

// Function to share a post
function sharePost($mysqli, $userId, $postId) {
    $shareQuery = "INSERT INTO shares (user_id, post_id) VALUES (?, ?)";
    $stmt = $mysqli->prepare($shareQuery);
    $stmt->bind_param("ii", $userId, $postId);

    if ($stmt->execute()) {
        echo "Post shared successfully\n";
    } else {
        echo "Error sharing post: " . $stmt->error . "\n";
    }
}

// Function to send notifications
function sendNotification($mysqli, $userId, $message) {
    $notificationQuery = "INSERT INTO notifications (user_id, message) VALUES (?, ?)";
    $stmt = $mysqli->prepare($notificationQuery);
    $stmt->bind_param("is", $userId, $message);

    if ($stmt->execute()) {
        echo "Notification sent: $message\n";
    } else {
        echo "Error sending notification: " . $stmt->error . "\n";
    }
}

// Function to get posts by contacts
function getPostsByContacts($mysqli, $userId) {
    $postsQuery = "SELECT posts.*, COUNT(likes.id) as like_count, COUNT(comments.id) as comment_count FROM posts
                    JOIN contacts ON posts.user_id = contacts.contact_id
                    LEFT JOIN likes ON posts.id = likes.post_id
                    LEFT JOIN comments ON posts.id = comments.post_id
                    WHERE contacts.user_id = ? AND contacts.status = 'accepted'
                    GROUP BY posts.id
                    ORDER BY posts.created_at DESC";

    $stmt = $mysqli->prepare($postsQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "Post ID: " . $row['id'] . "\n";
            echo "User ID: " . $row['user_id'] . "\n";
            echo "Content: " . $row['content'] . "\n";
            echo "Likes: " . $row['like_count'] . "\n";
            echo "Comments: " . $row['comment_count'] . "\n";
            echo "Created At: " . $row['created_at'] . "\n\n";
        }
    } else {
        echo "No posts found from your contacts.\n";
    }
}

// Function to show invitations
function showInvitations($mysqli, $userId) {
    $invitationsQuery = "SELECT * FROM invitations WHERE user_id = ? AND status = 'pending'";

    $stmt = $mysqli->prepare($invitationsQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "Invitation ID: " . $row['id'] . "\n";
            echo "Inviter ID: " . $row['inviter_id'] . "\n";
            echo "Status: " . $row['status'] . "\n";
            echo "Created At: " . $row['created_at'] . "\n\n";
        }
    } else {
        echo "No pending invitations.\n";
    }
}

// Function to show people you may know
function showPeopleYouMayKnow($mysqli, $userId) {
    $peopleQuery = "SELECT users.*, COUNT(DISTINCT mutual_contacts.contact_id) AS mutual_connections FROM users
                    JOIN contacts AS user_contacts ON users.id = user_contacts.contact_id
                    JOIN contacts AS mutual_contacts ON user_contacts.contact_id = mutual_contacts.contact_id
                    WHERE user_contacts.user_id = ? AND mutual_contacts.user_id != ?
                    GROUP BY users.id
                    ORDER BY mutual_connections DESC";

    $stmt = $mysqli->prepare($peopleQuery);
    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "User ID: " . $row['id'] . "\n";
            echo "Username: " . $row['username'] . "\n";
            echo "Email: " . $row['email'] . "\n";
            echo "Mutual Connections: " . $row['mutual_connections'] . "\n\n";
        }
    } else {
        echo "No people you may know found.\n";
    }
}

// Function to search users with filters
function searchUsers($mysqli, $location, $profileLanguage, $currentCompany) {
    $searchQuery = "SELECT * FROM users WHERE location LIKE ? AND profile_language LIKE ? AND current_company LIKE ?";

    $stmt = $mysqli->prepare($searchQuery);
    $location = "%$location%";
    $profileLanguage = "%$profileLanguage%";
    $currentCompany = "%$currentCompany%";
    $stmt->bind_param("sss", $location, $profileLanguage, $currentCompany);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "User ID: " . $row['id'] . "\n";
            echo "Username: " . $row['username'] . "\n";
            echo "Email: " . $row['email'] . "\n";
            echo "Location: " . $row['location'] . "\n";
            echo "Profile Language: " . $row['profile_language'] . "\n";
            echo "Current Company: " . $row['current_company'] . "\n\n";
        }
    } else {
        echo "No users found with the specified filters.\n";
    }
}

function mainMenu($mysqli) {
    echo "\n--- Main Menu ---\n";
    echo "1. Profile\n";
    echo "2. Home\n";
    echo "3. Network\n";
    echo "4. Search Users\n";
    echo "5. Exit\n";

    $choice = readlineInput("Enter your choice: ");

    return $choice;
}

function profileMenu($mysqli, $userId) {
    echo "\n--- Profile Menu ---\n";
    echo "1. Create/Update Profile\n";
    echo "2. Create Post\n";
    echo "3. Back to Main Menu\n";

    $choice = readlineInput("Enter your choice: ");

    switch ($choice) {
        case '1':
            createOrUpdateProfile($mysqli, $userId);
            break;
        case '2':
            $content = readlineInput("Enter post content: ");
            createPost($mysqli, $userId, $content);
            break;
        case '3':
            return;
        default:
            echo "Invalid choice.\n";
            break;
    }
}

function homeMenu($mysqli, $userId) {
    echo "\n--- Home Menu ---\n";
    echo "1. View Posts by Contacts\n";
    echo "2. Back to Main Menu\n";

    $choice = readlineInput("Enter your choice: ");

    switch ($choice) {
        case '1':
            getPostsByContacts($mysqli, $userId);
            break;
        case '2':
            return;
        default:
            echo "Invalid choice.\n";
            break;
    }
}

function networkMenu($mysqli, $userId) {
    echo "\n--- Network Menu ---\n";
    echo "1. View Invitations\n";
    echo "2. View People You May Know\n";
    echo "3. Back to Main Menu\n";

    $choice = readlineInput("Enter your choice: ");

    switch ($choice) {
        case '1':
            showInvitations($mysqli, $userId);
            break;
        case '2':
            showPeopleYouMayKnow($mysqli, $userId);
            break;
        case '3':
            return;
        default:
            echo "Invalid choice.\n";
            break;
    }
}

function searchUsersMenu($mysqli) {
    echo "\n--- Search Users Menu ---\n";
    $location = readlineInput("Enter location filter: ");
    $profileLanguage = readlineInput("Enter profile language filter: ");
    $currentCompany = readlineInput("Enter current company filter: ");

    searchUsers($mysqli, $location, $profileLanguage, $currentCompany);
}

// Function to get user input
function readlineInput($prompt) {
    echo $prompt;
    return trim(fgets(STDIN));
}

// Main interactive part
$username = readlineInput("Enter your username: ");
$password = readlineInput("Enter your password: "); // In a real application, handle passwords securely

$userId = getUserId($mysqli, $username);

if ($userId === null) {
    $email = readlineInput("Enter your email: ");
    $location = readlineInput("Enter your location: ");
    $profileLanguage = readlineInput("Enter your profile language: ");
    $currentCompany = readlineInput("Enter your current company: ");

    $createUserQuery = "INSERT INTO users (username, email, password, location, profile_language, current_company) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($createUserQuery);
    $stmt->bind_param("ssssss", $username, $email, $password, $location, $profileLanguage, $currentCompany);

    if ($stmt->execute()) {
        $userId = $mysqli->insert_id;
        echo "User created successfully with ID $userId\n";
    } else {
        die("Error creating user: " . $stmt->error);
    }
} else {
    echo "Welcome back, $username!\n";
}

while (true) {
    $choice = mainMenu($mysqli);

    switch ($choice) {
        case '1':
            profileMenu($mysqli, $userId);
            break;
        case '2':
            homeMenu($mysqli, $userId);
            break;
        case '3':
            networkMenu($mysqli, $userId);
            break;
        case '4':
            searchUsersMenu($mysqli);
            break;
        case '5':
            echo "Exiting the program.\n";
            $mysqli->close();
            exit;
        default:
            echo "Invalid choice.\n";
            break;
    }
}
?>
