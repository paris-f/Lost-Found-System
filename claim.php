<?php
include_once 'includes/config.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_GET['item_id'])){
    header("location: index.php");
    exit;
}

$item_id = $conn->real_escape_string($_GET['item_id']);
$user_id = $_SESSION['user_id'];
$message = "";
$message_type = "success";
// 1. Fetch item details
$sql_item = "SELECT * FROM items WHERE item_id = ?";
if($stmt_item = $conn->prepare($sql_item)){
    $stmt_item->bind_param("i", $item_id);
    $stmt_item->execute();
    $result_item = $stmt_item->get_result();
    $item = $result_item->fetch_assoc();
    $stmt_item->close();
} else {
    die("Error fetching item details.");
}

if (!$item) {
    die("Item not found.");
}

// 2. Handle claim submission
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_claim'])){
    $claim_details = trim($conn->real_escape_string($_POST['claim_details'] ?? '')); // Trim input

    // --- NEW: Server-side validation for minimum length ---
    if (strlen($claim_details) < 10) {
        $message = "<p class='error'>Claim details must be at least 10 characters long to provide sufficient proof.</p>";
    }
    // --- END NEW VALIDATION ---
    
    // Check if the user is attempting to claim their own item
    else if ($item['user_id'] == $user_id) {
        $message = "<p class='error'>You cannot claim an item you reported.</p>";
    } else {
        // Simple security: Check for existing pending claim by this user
        $sql_check = "SELECT claim_id FROM claims WHERE item_id = ? AND claimer_id = ? AND status = 'Pending'";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ii", $item_id, $user_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $message = "<p class='error'>You already have a pending claim for this item.</p>";
        } else {
            // Insert new claim
            $sql_insert = "INSERT INTO claims (item_id, claimer_id, claim_details) VALUES (?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("iis", $item_id, $user_id, $claim_details);
            
            if($stmt_insert->execute()){
                $message = "<p class='success'>Claim submitted successfully! The administration will review your claim shortly. Check the Admin Panel for approval.</p>";
                log_action($user_id, "Submitted claim for item ID: " . $item_id);
                // Notification/OTP Placeholder: A real system would trigger an admin notification.
                $_POST = []; // Clear post on success
            } else {
                $message = "<p class='error'>Error submitting claim.</p>";
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}

include 'includes/header.php';
?>

<div class="form-container">
    <h2>Claim Item: <?php echo htmlspecialchars($item['item_name']); ?></h2>
    <p><strong>Item ID:</strong> <?php echo $item['item_id']; ?></p>
    <p><strong>Type:</strong> <span class="<?php echo strtolower($item['item_type']); ?>"><?php echo htmlspecialchars($item['item_type']); ?></span></p>
    <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
    <?php if (!empty($item['item_image'])): ?>
        <p><strong>Image:</strong> <br><img src="images/<?php echo htmlspecialchars($item['item_image']); ?>" alt="Item Image" style="max-width: 300px; height: auto;"></p>
    <?php endif; ?>
    <hr>
    
    <?php echo $message; ?>

    <h3>Submit Your Claim Details</h3>
    <form action="claim.php?item_id=<?php echo $item_id; ?>" method="post">
        <label for="claim_details">
            Provide detailed proof of ownership (minimum 10 characters).<BR>
            Your claim must include specific, non-public details that only the true owner would know, such as:<br>
            1.  Unique Markings: Hidden names, stickers, or scratches.<br>
            2.  Specific Contents: Exact items inside a wallet or bag (e.g., "a blue library card")<br>
            3.  Serial/ID Numbers: IMEI, Service Tag, or unique product ID.<br>
            4.  Exact Loss Location/Time: The precise spot and minute it was last seen.<br>
        </label>
        <textarea
            id="claim_details"
            name="claim_details"
            rows="6"
            required
            minlength="10" ><?php echo isset($_POST['claim_details']) && $message_type != 'success' ? htmlspecialchars($_POST['claim_details']) : ''; ?></textarea>
        
     
        
        <button type="submit" name="submit_claim">Submit Claim</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>