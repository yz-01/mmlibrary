<?php
// S3 configuration
define('S3_BUCKET', 'mmlibrary');
define('S3_REGION', 'ap-south-1');
define('S3_ENDPOINT', 'mmlibrary.ap-south-1.linodeobjects.com');
define('S3_ACCESS_KEY', '9Y0LHV9GM77KQJAD89RT');
define('S3_SECRET_KEY', 'UnNgWM4jSA1i0ymN78E90t8UJmPmYURukxM9Afsx');

function createAWSSignature($region, $service, $accessKey, $secretKey, $method, $uri, $headers, $body) {
    // Date and time format
    $amzDate = gmdate('Ymd\THis\Z');
    $dateStamp = gmdate('Ymd'); // Date without time

    // Canonical request
    $canonicalHeaders = '';
    foreach ($headers as $key => $value) {
        $canonicalHeaders .= strtolower($key) . ':' . trim($value) . "\n";
    }

    $signedHeaders = implode(';', array_map('strtolower', array_keys($headers)));
    $payloadHash = hash('sha256', $body);

    $canonicalRequest = $method . "\n" . $uri . "\n" . "\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;

    // Credential scope
    $credentialScope = $dateStamp . '/' . $region . '/' . $service . '/aws4_request';

    // String to sign
    $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);

    // Generate signing key
    $kSecret = 'AWS4' . $secretKey;
    $kDate = hash_hmac('sha256', $dateStamp, $kSecret, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

    // Signature
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);

    // Authorization header
    $authorizationHeader = 'AWS4-HMAC-SHA256 ' .
        'Credential=' . $accessKey . '/' . $credentialScope . ', ' .
        'SignedHeaders=' . $signedHeaders . ', ' .
        'Signature=' . $signature;

    return [$authorizationHeader, $amzDate];
}

function uploadFileToS3($filePath, $bucket, $folder, $region, $endpoint, $accessKey, $secretKey, $customFilename = null) {
    // Read the file content
    $fileContent = file_get_contents($filePath);
    if ($fileContent === false) {
        throw new Exception("Failed to read file");
    }

    // Calculate content size and hash
    $fileSize = strlen($fileContent);
    $contentHash = hash('sha256', $fileContent);

    // Create a date for headers and credential string
    $shortDate = gmdate('Ymd');
    $longDate = gmdate('Ymd\THis\Z');
    
    // Create a scope for the request
    $service = 's3';
    $scope = "$shortDate/$region/$service/aws4_request";
    
    // Create a canonical request
    $method = 'PUT';
    $filename = $customFilename ?? basename($filePath);
    $canonicalUri = "/$bucket/$folder/$filename";
    $canonicalQueryString = '';
    $canonicalHeaders = "host:$endpoint\nx-amz-content-sha256:$contentHash\nx-amz-date:$longDate\n";
    $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
    $payloadHash = hash('sha256', $fileContent);
    $canonicalRequest = "$method\n$canonicalUri\n$canonicalQueryString\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

    // Create a string to sign
    $algorithm = 'AWS4-HMAC-SHA256';
    $stringToSign = "$algorithm\n$longDate\n$scope\n" . hash('sha256', $canonicalRequest);

    // Create the signing key
    $kSecret = 'AWS4' . $secretKey;
    $kDate = hash_hmac('sha256', $shortDate, $kSecret, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

    // Sign the string
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);

    // Create authorization header
    $authorizationHeader = "$algorithm Credential=$accessKey/$scope, SignedHeaders=$signedHeaders, Signature=$signature";

    // Correct URL construction
    $url = "https://$endpoint/$bucket/$folder/$filename";

    // Initialize cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_INFILE, fopen($filePath, 'r'));
    curl_setopt($ch, CURLOPT_INFILESIZE, $fileSize);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: $authorizationHeader",
        "x-amz-content-sha256: $contentHash",
        "x-amz-date: $longDate",
        "Content-Length: $fileSize",
        "Content-Type: application/octet-stream"
    ]);

    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Close the cURL handle
    curl_close($ch);

    if ($httpCode != 200) {
        throw new Exception("Error uploading file to S3: HTTP code $httpCode");
    }

    return "https://$endpoint/$bucket/$folder/$filename";
}

function generatePresignedUrl($objectKey, $expireSeconds = 3600) {
    $method = 'GET';
    $service = 's3';
    $host = S3_ENDPOINT;
    $region = S3_REGION;
    $accessKey = S3_ACCESS_KEY;
    $secretKey = S3_SECRET_KEY;

    // Time values
    $timestamp = time();
    $datestamp = gmdate('Ymd', $timestamp);
    $amzdate = gmdate('Ymd\THis\Z', $timestamp);
    $expirationTimestamp = $timestamp + $expireSeconds;

    // Canonical URI (must start with forward slash)
    $canonicalUri = '/' . S3_BUCKET . '/' . ltrim($objectKey, '/');

    // Query parameters for presigned URL
    $query = [
        'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential' => $accessKey . '/' . $datestamp . '/' . $region . '/' . $service . '/aws4_request',
        'X-Amz-Date' => $amzdate,
        'X-Amz-Expires' => $expireSeconds,
        'X-Amz-SignedHeaders' => 'host'
    ];

    // Build canonical query string
    ksort($query);
    $canonicalQueryString = http_build_query($query);

    // Create canonical request
    $canonicalHeaders = "host:" . $host . "\n";
    $payloadHash = 'UNSIGNED-PAYLOAD';

    $canonicalRequest = $method . "\n" .
        $canonicalUri . "\n" .
        $canonicalQueryString . "\n" .
        $canonicalHeaders . "\n" .
        'host' . "\n" .
        $payloadHash;

    // Create string to sign
    $credentialScope = $datestamp . '/' . $region . '/' . $service . '/aws4_request';
    $stringToSign = "AWS4-HMAC-SHA256\n" .
        $amzdate . "\n" .
        $credentialScope . "\n" .
        hash('sha256', $canonicalRequest);

    // Calculate signature
    $kSecret = 'AWS4' . $secretKey;
    $kDate = hash_hmac('sha256', $datestamp, $kSecret, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);

    // Add signature to query parameters
    $query['X-Amz-Signature'] = $signature;

    // Build final URL
    $presignedUrl = 'https://' . $host . $canonicalUri . '?' . http_build_query($query);

    return $presignedUrl;
}
