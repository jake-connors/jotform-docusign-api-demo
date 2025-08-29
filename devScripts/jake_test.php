<?php
if (php_sapi_name() === 'cli') {
    $_SERVER["DOCUMENT_ROOT"] = dirname(__FILE__, 2);
}
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Globals.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Erp.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Docusign.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Crm.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Curl.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/FwwebbCom.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Email.php";

echo "Start!<br>\n";

$start_time = microtime(true);
function done()
{
    $end_time = microtime(true);
    global $start_time;
    $runtime = number_format($end_time - $start_time, 2);
    echo "\n<br>Program completed in : " . $runtime . " seconds<br>\n";
    echo "end!<br>\n";
    exit();
}

function testEndpointDev($endpoint, $postData) {
    $url = \Globals::getEndpointServerUrl() . $endpoint;
    $resp = \Curl::doPost($url, $postData);
    return $resp;
}

function computeHash($secret, $payload)
{
    $hexHash = hash_hmac('sha256', $payload, $secret);
    $base64Hash = base64_encode(hex2bin($hexHash));
    return $base64Hash;
}

function hashIsValid($secret, $payload, $verify)
{
    return hash_equals($verify, computeHash($secret, $payload));
}


function _prepareData($data) 
{
    $json = json_encode($data);
    return str_replace(['"', "'", "\n", " "], '', $json);
}

$key = "JAKE.TEST";
$notSubmitted = false;
$url = \Globals::getEndpointServerUrl() . "erp-files/v1/save-customer/" . $key . "/";
$email = "jakec@fwwebb.com";
$request_data = [
    "email" => $email,
    "notSubmitted" => $notSubmitted ? "1" : "0",
];
$resp = \Curl::doPut($url, $request_data);
echo $resp;
echo "<br>\n";

done();


$requestData = [
    "custNum" => "20773",
    "custEmail" => "jake.connors@fwwebb.com",
    "custName" => "Test Contracting Company",
    "custBr" => "83",
];

$requestData = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/devScripts/jake_test_data.json");
$requestData = json_decode($requestData, true);

$url = "https://endpoint-dev.fwwebb.com/customer-registration/v1/jotform-webhook";

$hmac_token = "VT7ikBT6lsvXH58iFfJLcaiW";
$sig_data = _prepareData($requestData);
echo "Sig data: " . $sig_data . "<br>\n";
// done();
$sig = computeHash($hmac_token, $url . $sig_data);
$headers = [
    "Registration-Signature: " . $sig,
    'Content-Type: application/json',
];

$curl = curl_init();
$options = [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode($requestData),
    CURLOPT_HTTPHEADER => $headers
];
curl_setopt_array($curl, $options);
$resp = curl_exec($curl);
curl_close($curl);

echo $resp;
echo "<br>\n";

done();

$Docusign = new Docusign();

testTaxExemptEnv();
done();

// test send contact
$contactData = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/devScripts/jake_test_data.json");
$contactData = json_decode($contactData, true);

$cusNumber = $contactData["cusNumber"] ?? "";
$firstName = $contactData["firstName"] ?? "";
$lastName = $contactData["lastName"] ?? "";
$email = $contactData["email"] ?? "";
$title = $contactData["title"] ?? "";
$phone = $contactData["phone"] ?? "";
$primaryPhone = $phone["phone"] ?? "";
$primaryPhone = str_replace(["(", ")", "-", " "], "", $primaryPhone);

// test get env session
$env_id = "d8180aa4-813a-41a2-9d03-f3475669f9ad";
$response = $Docusign->createEnvelopeSession($env_id, "1");
echo $response;
done();

$cust_id = "242555";
$test_br = "4";

$prefill_values = [
    "Business_Name" => "321 EASY",
    "Online_Account_Email" => "tester21322@test2.com",
    "Business_Phone" => "6172832432",
    "Business_Address" => "70 morse st",
    "Business_City" => "watertown",
    "Business_State" => "ma",
    "Business_Zip" => "02462",
    "Title" => "Cust# " . $cust_id . ", BR: " . $test_br . ", Name: " . "test"
];
$args = [
    "type" => "cust_credit_app_existing",
    "prefill_values" => $prefill_values,
    "signer_email" => "tester2132@test2.com",
    "signer_name" => "test",
    "cust_num" => $cust_id,
    "client_user_id" => uniqid(), // this become "client_user_id" (recipient specific)
    "document_name" => "Credit App | Cust#: " . $cust_id . ", BR: " . $test_br . ", Name: " . "31 EASY INC."
];
$response = $Docusign->createEnvelopeNew($args);
echo $response;
exit();

function testTaxExemptEnv() {
    global $Docusign;
    $cust_num = "123456";
    $branch = "1";
    $company_name = "Test Company";
    $unique_id = uniqid();
    $first = "John";
    $last = "Doe";
    $email = "johndoe@test.com";
    
    $args = [
        "type" => "tax_exempt",
        "signer_email" => $email,
        "signer_name" => $first . " " . $last,
        "client_user_id" => $unique_id,
        "document_name" => "Tax Exempt | Cust#: " . $cust_num . ", BR: " . $branch . ", Name: " . $company_name,
        "prefill_values" => ["Title" => "Tax Exempt | Cust#: " . $cust_num . ", BR: " . $branch . ", Name: " . $company_name]
    ];

    $response = $Docusign->createEnvelopeNew($args);
    print_r($response);
}