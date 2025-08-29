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

$cust_name_prefill = $cust_name;
if (strtoupper(substr($cust_name, strlen($cust_name) - 6)) === " (COD)") {
    $cust_name_prefill = substr($cust_name, 0, strlen($cust_name) - 6);
}

$signer_name = $cust_name;
$account_email = $signer_email;

$customer_record = \Erp::getCustomerRecord($cust_num);

$prefill_values = [
    "Business_Name" => $cust_name_prefill,
    "Online_Account_Email" => $account_email,
    "Business_Phone" => $customer_record["phone"],
    "Business_Address" => $customer_record["address1"],
    "Business_City" => $customer_record["city"],
    "Business_State" => $customer_record["state"],
    "Business_Zip" => $customer_record["zip"],
    "Title" => "Cust# " . $cust_num . ", BR: " . $cust_br . ", Name: " . $cust_name
];

$args = [
    "type" => "cust_credit_app_existing",
    "prefill_values" => $prefill_values,
    "signer_email" => $signer_email,
    "signer_name" => $signer_name,
    "cust_num" => $cust_num,
    "client_user_id" => uniqid(),
    "document_name" => "Credit App | Cust#: " . $cust_num . ", BR: " . $cust_br . ", Name: " . $cust_name
];

$url = $Docusign->createEnvelopeNew($args);

echo json_encode([
    "success" => 1,
    "redirect_url" => $url,
]);

?>
