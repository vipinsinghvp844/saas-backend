<?php
// config/jwt.php

define("JWT_SECRET", "GYM_SAAS_SUPER_SECRET_KEY");
define("JWT_EXPIRE_TIME", 7200); // 2 hour

function createJWT($payload) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);

    $payload['iat'] = time();
    $payload['exp'] = time() + JWT_EXPIRE_TIME;

    $base64Header = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $base64Payload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

    $signature = hash_hmac(
        'sha256',
        $base64Header . "." . $base64Payload,
        JWT_SECRET,
        true
    );

    $base64Signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

function verifyJWT($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    [$header, $payload, $signature] = $parts;

    $check = rtrim(strtr(base64_encode(
        hash_hmac('sha256', $header.".".$payload, JWT_SECRET, true)
    ), '+/', '-_'), '=');

    if ($check !== $signature) return false;

    $data = json_decode(base64_decode($payload), true);

    if ($data['exp'] < time()) return false;

    return $data;
}