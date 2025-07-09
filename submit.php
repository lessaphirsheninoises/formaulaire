<?php
// --- CONFIGURATION ---
$recipient_email = "artandsoul.ent.off@gmail.com"; // L'adresse qui recevra les candidatures.
$subject_prefix = "Candidature DOLLZ";

// --- SÉCURITÉ ET VALIDATION INITIALE ---
// Accepte uniquement les requêtes POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée.']);
    exit;
}

// Vérifie que les champs de base sont présents.
if (empty($_POST['fullName']) || empty($_POST['email'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Les champs nom et e-mail sont obligatoires.']);
    exit;
}

// --- TRAITEMENT DES DONNÉES ET FICHIERS ---
$fullName = trim($_POST['fullName']);
$from_email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
$subject = "$subject_prefix - $fullName";

// --- GESTION DES FICHIERS UPLOADÉS ---
$attachments = [];
$upload_errors = [];
$allowed_mime_types = [
    'audio/mpeg', 'audio/wav', 'audio/x-wav',
    'video/mp4', 'video/quicktime'
];
$max_file_size = 100 * 1024 * 1024; // 100 MB

// Boucle sur chaque fichier attendu.
foreach (['chant-file', 'danse-file', 'rap-file'] as $file_key) {
    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == UPLOAD_ERR_OK) {
        // Vérification de la taille du fichier.
        if ($_FILES[$file_key]['size'] > $max_file_size) {
            $upload_errors[] = "Le fichier '{$_FILES[$file_key]['name']}' est trop volumineux (max 100 Mo).";
            continue;
        }
        // Vérification du type MIME.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES[$file_key]['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_mime_types)) {
            $upload_errors[] = "Le type du fichier '{$_FILES[$file_key]['name']}' n'est pas autorisé.";
            continue;
        }

        // Ajoute le fichier à la liste des pièces jointes.
        $attachments[] = [
            'tmp_name' => $_FILES[$file_key]['tmp_name'],
            'name' => $_FILES[$file_key]['name'],
            'type' => $mime_type
        ];
    } else {
        // Gère les erreurs d'upload ou les fichiers manquants.
        $error_code = $_FILES[$file_key]['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error_code !== UPLOAD_ERR_NO_FILE) {
            $upload_errors[] = "Erreur lors de l'upload du fichier '$file_key'. Code: $error_code";
        }
    }
}

if (!empty($upload_errors)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => implode(' ', $upload_errors)]);
    exit;
}

// --- CONSTRUCTION DU CORPS DE L'E-MAIL (HTML) ---
$email_body = "<html><body style='font-family: sans-serif; color: #333;'>";
$email_body .= "<h1 style='color: #ff77cc;'>Nouvelle Candidature DOLLZ</h1>";
$email_body .= "<p><strong>Nom :</strong> " . htmlspecialchars($fullName) . "</p>";
$email_body .= "<p><strong>Email :</strong> " . htmlspecialchars($from_email) . "</p>";
$email_body .= "<hr style='border-color: #00ccff;'>";

// Ajoute toutes les autres données du formulaire.
foreach ($_POST as $key => $value) {
    if ($key !== 'fullName' && $key !== 'email' && !empty($value)) {
        $label = htmlspecialchars(ucfirst(str_replace('-', ' ', $key)));
        $content = nl2br(htmlspecialchars($value));
        $email_body .= "<h3 style='color: #00ccff; margin-top: 20px;'>$label</h3>";
        $email_body .= "<p style='background-color: #f4f4f4; padding: 10px; border-radius: 5px;'>$content</p>";
    }
}
$email_body .= "</body></html>";

// --- CONSTRUCTION DE L'E-MAIL MULTIPART ---
$boundary = "boundary-" . md5(time());

// En-têtes
$headers = "From: Art & Soul Auditions <noreply@yourdomain.com>\r\n";
$headers .= "Reply-To: $from_email\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

// Message principal (partie texte/html)
$message = "--$boundary\r\n";
$message .= "Content-Type: text/html; charset=UTF-8\r\n";
$message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
$message .= $email_body . "\r\n";

// Ajout des pièces jointes
foreach ($attachments as $attachment) {
    $file_content = chunk_split(base64_encode(file_get_contents($attachment['tmp_name'])));
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: {$attachment['type']}; name=\"{$attachment['name']}\"\r\n";
    $message .= "Content-Disposition: attachment; filename=\"{$attachment['name']}\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $message .= $file_content . "\r\n";
}

$message .= "--$boundary--"; // Fin de l'e-mail

// --- ENVOI DE L'E-MAIL ---
if (mail($recipient_email, $subject, $message, $headers)) {
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Candidature envoyée avec succès !']);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Le serveur n\'a pas pu envoyer l\'e-mail.']);
}

exit;
?>
