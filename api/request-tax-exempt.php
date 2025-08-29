<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Globals.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/ApiRequest.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Docusign.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Erp.php";

$ApiRequest = new \ApiRequest();
$ApiRequest->check_hmac();

$requestData = $ApiRequest->requestData;

$cust_num = $requestData["custNum"] ?? "";
$signer_email = $requestData["custEmail"] ?? "";
$cust_name = $requestData["custName"] ?? "";
$cust_br = $requestData["custBr"] ?? "";

if (empty($cust_num) || empty($signer_email) || empty($cust_name) || empty($cust_br)) {
    echo json_encode([
        "success" => 0,
        "error" => "Missing required params: custNum, custEmail, custName, custBr",
        "redirect_url" => ""
    ]);

    http_response_code(400);
    exit();
}

$Docusign = new \Docusign();

$signer_name = $cust_name;
$args = [
    "type" => "tax_exempt",
    "signer_name" => $signer_name,
    "signer_email" => $signer_email,
    "client_user_id" => uniqid(),
    "document_name" => "Tax Exempt | Cust#: " . $cust_num . ", BR: " . $cust_br . ", Name: " . $cust_name,
    "prefill_values" => ["Title" => "Tax Exempt | Cust#: " . $cust_num . ", BR: " . $cust_br . ", Name: " . $cust_name]
];

$url = $Docusign->createEnvelopeNew($args);

echo json_encode([
    "success" => 1,
    "redirect_url" => $url,
]);

?>
