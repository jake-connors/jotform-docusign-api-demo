<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/ApiRequest.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Docusign.php";

$ApiRequest = new \ApiRequest();
$ApiRequest->check_hmac();

$requestData = $ApiRequest->requestData;

$who = $requestData["who"] ?? "";
$file_name = $requestData["fileName"] ?? "";

if (empty($who) || empty($file_name)) {
    echo json_encode([
        "success" => 0,
        "error" => "Missing required params: who, fileName",
        "redirect_url" => ""
    ]);

    http_response_code(400);
    exit();
}

$Docusign = new \Docusign();

$args = [
    "type" => "file_upload",
    "signer_name" => $who,
    "file_name" => $file_name,
    "document_name" => $file_name,
    "prefill_values" => ["File_Upload_Name" => $file_name],
    "dontAuthenticate" => true
];

$url = $Docusign->createEnvelopeNew($args);

echo json_encode([
    "success" => 1,
    "redirect_url" => $url,
]);

?>