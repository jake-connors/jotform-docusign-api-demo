<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Globals.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/ApiRequest.php";

function getRedirectURL($unique_id) {
    $sql_get_url = "SELECT redirect_url FROM redirect_url WHERE unique_id = ?:unique_id";
    $query_result = dbi_query($sql_get_url, ["unique_id" => $unique_id]);
    if (count($query_result)) {
        return $query_result[0]["redirect_url"] ?? "";
    } else {
        return "";
    }
}

$ApiRequest = new \ApiRequest();
$ApiRequest->check_hmac();

$requestData = $ApiRequest->requestData;

$unique_id = $requestData["id"] ?? "";

if (empty($unique_id)) {
    
    echo json_encode([
        "success" => 0,
        "error" => "Missing required param: id",
        "redirect_url" => ""
    ]);

    http_response_code(400);
    exit();
}

$redirect_url = getRedirectURL($unique_id);

if ($redirect_url == "") {
    for ($i = 0; $i < 100; $i++) {
        usleep(250000); // --> sleep(0.25)
        $redirect_url = getRedirectURL($unique_id);
    }
}

if ($redirect_url == "") {

    echo json_encode([
        "success" => 0,
        "error" => "Redirect URL not found for the provided ID.",
        "redirect_url" => ""
    ]);

    \Globals::logData("api/redirect-registration.php", [
        "unique_id" => $unique_id,
        "error" => "Redirect URL not found for the provided ID."
    ]);

    // http_response_code(400);
    exit();
}

echo json_encode([
    "success" => 1, 
    "redirect_url" => $redirect_url
]);

?>
