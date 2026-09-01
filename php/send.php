<?php
/**
 * Vemovent – kontaktskjema (send.php)
 *
 * Fungerer på vanlig webhotell (cPanel/directadmin) med PHP 7.4+ og mail().
 * Skjemaet sender med fetch (AJAX) og forventer JSON-svar.
 */

header('Content-Type: application/json; charset=utf-8');

// ---------- Konfigurasjon ----------
$recipient = 'post@vemovent.no';          // Mottaker av henvendelsen
$subject_prefix = 'Forespørsel fra vemovent.no';
$max_file_size = 8 * 1024 * 1024;         // 8 MB
$allowed_file_types = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];
$upload_dir = __DIR__ . '/uploads';       // Midlertidig mappe for vedlegg

function respond($ok, $message, $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- Kun POST ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Ugyldig forespørsel.', 405);
}

// ---------- Honeypot (spam-beskyttelse) ----------
if (!empty($_POST['hjemmeside'])) {
    respond(true, 'Takk! Meldingen din er sendt.');
}

// ---------- Hent og rens felt ----------
$clean = function ($key) {
    $val = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
    return strip_tags($val);
};

$fornavn       = $clean('fornavn');
$etternavn     = $clean('etternavn');
$adresse       = $clean('adresse');
$telefon       = $clean('telefon');
$epost         = $clean('epost');
$tekst         = $clean('tekst');
$aggregattype  = $clean('aggregattype');

// ---------- Validering ----------
$errors = [];

if ($fornavn === '')      { $errors[] = 'Fornavn er påkrevd.'; }
if ($telefon === '')      { $errors[] = 'Telefon er påkrevd.'; }
if ($tekst === '')        { $errors[] = 'Tekst er påkrevd.'; }
if ($aggregattype === '') { $errors[] = 'Velg aggregattype.'; }
if ($epost !== '' && !filter_var($epost, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'E-postadressen er ikke gyldig.';
}

if ($errors) {
    respond(false, 'Sjekk skjemaet: ' . implode(' ', $errors), 422);
}

// ---------- Vedlegg ----------
$attachment_path = null;
$attachment_name = '';

if (isset($_FILES['fil']) && $_FILES['fil']['error'] === UPLOAD_ERR_OK) {
    if ($_FILES['fil']['size'] > $max_file_size) {
        respond(false, 'Filen er for stor. Maks størrelse er 8 MB.', 422);
    }

    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }

    $ext = strtolower(pathinfo($_FILES['fil']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_file_types, true)) {
        respond(false, 'Filtypen er ikke tillatt. Tillatt: jpg, png, webp, pdf, doc.', 422);
    }

    $safe_name = 'vedlegg_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $attachment_path = $upload_dir . '/' . $safe_name;
    $attachment_name = preg_replace('/[^A-Za-z0-9._-]/', '_', $_FILES['fil']['name']);

    if (!move_uploaded_file($_FILES['fil']['tmp_name'], $attachment_path)) {
        $attachment_path = null;
    }
}

// ---------- Bygg e-post ----------
$to      = $recipient;
$subject = $subject_prefix . ' – ' . $fornavn . ($aggregattype !== '' ? ' (' . $aggregattype . ')' : '');

$lines = [];
$lines[] = 'Ny henvendelse fra kontaktskjemaet på vemovent.no';
$lines[] = str_repeat('-', 40);
$lines[] = 'Navn: ' . $fornavn . ($etternavn !== '' ? ' ' . $etternavn : '');
if ($adresse !== '')      { $lines[] = 'Adresse: ' . $adresse; }
$lines[] = 'Telefon: ' . $telefon;
if ($epost !== '')        { $lines[] = 'E-post: ' . $epost; }
$lines[] = 'Aggregattype: ' . $aggregattype;
$lines[] = str_repeat('-', 40);
$lines[] = 'Melding:';
$lines[] = $tekst;
$lines[] = str_repeat('-', 40);
$lines[] = 'Sendt: ' . date('d.m.Y H:i');
$lines[] = 'IP: ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'ukjent');

$body = implode("\r\n", $lines);

$from = 'webmaster@' . (isset($_SERVER['HTTP_HOST']) ? preg_replace('/^www\./', '', $_SERVER['HTTP_HOST']) : 'vemovent.no');
$headers = [
    'From: Vemovent nettside <' . $from . '>',
    'Reply-To: ' . ($epost !== '' ? $epost : $from),
    'X-Mailer: PHP/' . phpversion(),
    'MIME-Version: 1.0',
];

// Enkel tekst-epost (vedlegg sendes ikke med i mail() uten MIME – lagres i uploads/)
$headers[] = 'Content-Type: text/plain; charset=UTF-8';

$sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));

// Rydd opp midlertidig vedlegg
if ($attachment_path && file_exists($attachment_path)) {
    @unlink($attachment_path);
}

if ($sent) {
    respond(true, 'Takk! Meldingen din er sendt. Vi tar kontakt så snart som mulig – vanligvis samme virkedag.');
}

respond(false, 'Beklager, e-posten kunne ikke sendes. Ring oss i stedet på +47 45 65 52 92.', 500);
