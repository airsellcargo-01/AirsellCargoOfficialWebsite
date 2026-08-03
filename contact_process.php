<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // -------------------------------------------------
    // CONFIGURATION
    // -------------------------------------------------
    $to = "info@airsellcargo.com"; // Delivery email address (update if needed)

    // -------------------------------------------------
    // INPUT COLLECTION & SANITIZATION
    // -------------------------------------------------
    // Fetch variables; use null coalescence to avoid undefined index warnings
    $name    = isset($_POST["name"])         ? strip_tags(trim($_POST["name"]))           : '';
    $email   = isset($_POST["email"])        ? filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL) : '';
    $phone   = isset($_POST["phone"])        ? strip_tags(trim($_POST["phone"]))           : '';
    $service = isset($_POST["service_type"]) ? strip_tags(trim($_POST["service_type"]))    : '';
    $subject = isset($_POST["subject"])      ? strip_tags(trim($_POST["subject"]))         : '';
    $details = isset($_POST["details"])      ? htmlspecialchars(trim($_POST["details"]))   : '';

    // -------------------------------------------------
    // VALIDATION (BASIC, expand as needed)
    // -------------------------------------------------
    $errors = [];
    if (empty($name))    $errors[] = "Name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
    if (empty($phone))   $errors[] = "Phone is required.";
    if (empty($service)) $errors[] = "Service type is required.";
    if (empty($subject)) $errors[] = "Subject is required.";
    if (empty($details)) $errors[] = "Cargo details are required.";

    if (!empty($errors)) {
        // Join errors by line break
        echo "Please fix these errors:\n" . implode("\n", $errors);
        exit;
    }

    // -------------------------------------------------
    // BUILD HEADERS & MESSAGE
    // -------------------------------------------------
    $email_subject = "Airsell Portal Inquiry: $subject";
    $headers  = "From: info@airsellcargo.com\r\n";   // Prefer your real domain email for SPF
    $headers .= "Reply-To: $email\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $body  = "New message from Airsell Cargo Website Form:\n\n";
    $body .= "Customer Name: $name\n";
    $body .= "Customer Email: $email\n";
    $body .= "Phone Number: $phone\n";
    $body .= "Service Required: $service\n";
    $body .= "Subject: $subject\n\n";
    $body .= "Cargo Details:\n$details\n\n";
    $body .= "--- End of Message ---";

    // -------------------------------------------------
    // SEND EMAIL
    // -------------------------------------------------
    if (mail($to, $email_subject, $body, $headers)) {
        echo "Thank you! Your inquiry has been sent to the Airsell Cargo team.";
    } else {
        error_log("Mail failed: " . print_r(error_get_last(), true));
        echo "Error: Please email us directly at sales@airsellcargo.com";
    }
} else {
    echo "Access Denied.";
}
?>
