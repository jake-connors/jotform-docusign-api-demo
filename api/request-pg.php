<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Globals.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/ApiRequest.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Docusign.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Erp.php";

$ApiRequest = new \ApiRequest();
$ApiRequest->check_hmac();

$Docusign = new \Docusign();

$signer_name = "fullname";
$signer_email = "noreply@fwwebb.com"; // won't ever send it an email

$args = [
    "type" => "pg_app",
    "signer_name" => $signer_name,
    "signer_email" => $signer_email,
    "client_user_id" => uniqid(),
    "document_name" => "PG App |" // this will have cust_num appended when cust submits
];

$url = $Docusign->createEnvelopeNew($args);

echo json_encode([
    "success" => 1,
    "redirect_url" => $url,
]);

?>
