<?php
// sendAlertEmail is uitgeschakeld — ntfy heeft de voorkeur boven e-mail
// function sendAlertEmail($clientNaam, $mantelzorgers)
// {
//     // $mantelzorgers is nu een array van mantelzorger objecten
//     $success = true;
//
//     foreach ($mantelzorgers as $mz) {
//         $mantelzorgerNaam = $mz['naam'];
//         $mantelzorgerEmail = $mz['email'];
//
//         $subject = "Alert: Geen check-in ontvangen van " . $clientNaam;
//         $message = "
//         <html>
//         <head>
//             <title>Mantelzorg Alert</title>
//         </head>
//         <body>
//             <h2>Check-in gemist</h2>
//             <p>Beste {$mantelzorgerNaam},</p>
//             <p><strong>{$clientNaam}</strong> heeft niet op tijd ingecheckt.</p>
//             <p>Neem contact op om te controleren of alles in orde is.</p>
//             <br>
//             <p style='color: #666; font-size: 12px;'>Dit is een automatisch gegenereerd bericht van het Mantelzorg Check-in Systeem.</p>
//         </body>
//         </html>
//         ";
//
//         $headers = "MIME-Version: 1.0" . "\r\n";
//         $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
//         $headers .= "From: noreply@stapelmann.nl" . "\r\n";
//
//         if (!mail($mantelzorgerEmail, $subject, $message, $headers)) {
//             $success = false;
//         }
//     }
//
//     return $success;
// }

function sendNtfyAlert($clientNaam, $topic)
{
    $bericht = "🚨 Alert: {$clientNaam} heeft niet op tijd ingecheckt!";

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: text/plain\r\nTitle: Mantelzorg Alert\r\nPriority: max\r\nTags: warning\r\n",
            'content' => $bericht
        ]
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents("https://ntfy.sh/{$topic}", false, $context);

    return $result !== false;
}

// sendLateCheckInEmail is uitgeschakeld — ntfy heeft de voorkeur boven e-mail
// function sendLateCheckInEmail($clientNaam, $mantelzorgers, $alertTime)
// {
//     $success = true;
//
//     foreach ($mantelzorgers as $mz) {
//         $mantelzorgerNaam = $mz['naam'];
//         $mantelzorgerEmail = $mz['email'];
//
//         $subject = "Late check-in ontvangen van " . $clientNaam;
//         $message = "
//         <html>
//         <head>
//             <title>Mantelzorg - Late Check-in</title>
//         </head>
//         <body>
//             <h2>Late check-in ontvangen</h2>
//             <p>Beste {$mantelzorgerNaam},</p>
//             <p><strong>{$clientNaam}</strong> heeft zojuist ingecheckt, maar was <strong>te laat</strong>.</p>
//             <p>De check-in had voor <strong>{$alertTime}</strong> moeten gebeuren.</p>
//             <p style='color: #ff9800;'>Let op: Dit is een late check-in melding.</p>
//             <br>
//             <p style='color: #666; font-size: 12px;'>Dit is een automatisch gegenereerd bericht.</p>
//         </body>
//         </html>
//         ";
//
//         $headers = "MIME-Version: 1.0" . "\r\n";
//         $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
//         $headers .= "From: noreply@stapelmann.nl" . "\r\n";
//
//         if (!mail($mantelzorgerEmail, $subject, $message, $headers)) {
//             $success = false;
//         }
//     }
//
//     return $success;
// }

function sendLateCheckInNtfy($clientNaam, $alertTime, $topic)
{
    $bericht = "⏰ Late check-in: {$clientNaam} heeft ingecheckt, maar was te laat. Verwacht voor {$alertTime}.";

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: text/plain\r\nTitle: Late Check-in\r\nPriority: default\r\nTags: stopwatch\r\n",
            'content' => $bericht
        ]
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents("https://ntfy.sh/{$topic}", false, $context);

    return $result !== false;
}
