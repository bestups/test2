<?php
// Formularz kontaktowy - malowanekadry.pl
// ZMIEŃ na swój adres e-mail w domenie hostingu (seohost.pl):
$odbiorca = 'kontakt_strona@malowanekadry.pl';
$nadawca  = 'kontakt_strona@malowanekadry.pl';


header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

function czysc($v, $limit = 200) {
    $v = trim((string) $v);
    return str_replace(["\r", "\n"], ' ', mb_substr($v, 0, $limit));
}

function pole($klucz, $limit = 200) {
    return czysc($_POST[$klucz] ?? '', $limit);
}

// --- ochrona antyspamowa ---------------------------------------------------
// 1. pułapka: pole ukryte, wypełniane tylko przez roboty
if (czysc($_POST['website'] ?? '') !== '') {
    echo json_encode(['ok' => true]); // udajemy sukces, nic nie wysyłamy
    exit;
}
// 2. formularz wysłany szybciej niż w 3 sekundy = bot
$elapsed = (int) ($_POST['elapsed'] ?? 0);
if ($elapsed > 0 && $elapsed < 3) {
    echo json_encode(['ok' => true]);
    exit;
}

$imie      = pole('name', 80);
$email     = pole('email', 120);
$telefon   = pole('phone', 30);
$usluga    = pole('serviceName') !== '' ? pole('serviceName') : pole('service');
$skad      = pole('source');
$wiadomosc = mb_substr(trim($_POST['message'] ?? ''), 0, 4000);

$zgoda = pole('consent', 10);

if ($zgoda === '' || $imie === '' || $wiadomosc === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'walidacja']);
    exit;
}

// 3. proste odsiewanie spamu po treści
if (preg_match('/\b(viagra|casino|crypto|bitcoin|seo services|backlink)\b/i', $wiadomosc)) {
    echo json_encode(['ok' => true]);
    exit;
}

$temat = '=?UTF-8?B?' . base64_encode('Zapytanie ze strony' . ($usluga !== '' ? ' - ' . $usluga : '') . ' - ' . $imie) . '?=';

$brak  = '(nie podano)';
$tresc = "Imię: $imie\n"
       . "E-mail: $email\n"
       . "Telefon: " . ($telefon !== '' ? $telefon : $brak) . "\n"
       . "Usługa: " . ($usluga !== '' ? $usluga : $brak) . "\n";

// pola szczegółowe zależne od wybranej usługi: detail[Etykieta] = wartość
if (isset($_POST['detail']) && is_array($_POST['detail'])) {
    $tresc .= "\n--- Szczegóły ---\n";
    foreach ($_POST['detail'] as $etykieta => $wartosc) {
        $wartosc = czysc($wartosc);
        if ($wartosc === '') {
            continue;
        }
        $tresc .= czysc($etykieta, 80) . ": " . $wartosc . "\n";
    }
}

$tresc .= "\nZgoda na przetwarzanie danych: tak\n"
       . "Skąd o mnie wie: " . ($skad !== '' ? $skad : $brak) . "\n\n"
       . "Wiadomość:\n$wiadomosc\n";

$naglowki = [
    'From: Formularz malowanekadry.pl <' . $nadawca . '>',
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
    'MIME-Version: 1.0',
];

$wyslano = @mail($odbiorca, $temat, $tresc, implode("\r\n", $naglowki));

if (!$wyslano) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'mail']);
    exit;
}

echo json_encode(['ok' => true]);
