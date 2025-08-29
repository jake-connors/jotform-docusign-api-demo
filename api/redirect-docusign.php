<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Globals.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/ApiRequest.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Docusign.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Erp.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Email.php";

$ApiRequest = new \ApiRequest();
$ApiRequest->check_hmac();

$requestData = $ApiRequest->requestData;

$mode = $requestData["mode"] ?? "";
$env_id = $requestData["session_id"] ?? "";
$rec_id = $requestData["recipient_id"] ?? "";
$event = $requestData["event"] ?? "other";
$env_type = $requestData["type"] ?? "";

if (empty($env_id) || empty($rec_id)) {
    echo json_encode([
        "success" => 0,
        "error" => "Missing required params: session_id, recipient_id",
        "redirect_url" => ""
    ]);

    http_response_code(400);
    exit();
}

$Docusign = new \Docusign();

$signer_email = $signer_name = $status = "";

$recipient = $Docusign->getEnvelopeRecipient($env_id, $rec_id);
$recipient_type = $recipient["recipientType"] ?? "";
$signer_name = $recipient["name"] ?? "";
$client_user_id = $recipient["clientUserId"] ?? "";
$signer_email = $recipient["email"] ?? "";
$recipient_status = $recipient["status"] ?? "";

$send_finish_later_on_timeout = true;
$valid_view_env_session_types = ["signer", "carboncopy"];
$url_query_params["event"] = $event;
$url_query_params["session_id"] = $env_id;
$url_query_params["recipient_id"] = $rec_id;
$url_query_params["email"] = $signer_email;
$url_query_params["type"] = $env_type;

$redirect_url = \Globals::getOrderingServerUrl() . "/docusign_return.html";

if ($mode === "requestSession" || $mode === "redirectReturn" || $mode === "requestAuthSession") {
    // this event is coming from the landing page on fwwebb.com to return back to their docusign form
    $recipient_role = $recipient["roleName"] ?? "";
    $oldMode = $_REQUEST["oldMode"] ?? "";
    if ($mode !== "requestAuthSession" && !in_array($recipient_role, ["Credit Applicant New", "Credit Applicant Existing", "Exempt Uploader", "Credit Applicant 2"])) {
        $state = $_REQUEST;
        $state["oldMode"] = $mode;
        $state["mode"] = "requestAuthSession";
        $authCode = $Docusign->getAuthURI($state);
        $redirect_url = $authCode;
    } else {
        $redirect_url = $Docusign->createEnvelopeSession($env_id, $rec_id);
    }
} else {
    if ($event === "session_timeout" && in_array($recipient_status, ["sent", "delivered"])) {
        $is_session_timeout = true;
        $is_file_upload = $rec_id == 4 || $rec_id == 5;
        if ($send_finish_later_on_timeout) {
            $customer_num = $Docusign->getCustomerNumber($env_id);
            $br_num = \Erp::getCustBranchNum($customer_num);
            \Email::sendFinishLater($signer_email, $signer_name, $env_id, $rec_id, $customer_num, $br_num, $is_session_timeout, $is_file_upload);
        }
    }
    if ($event === "cancel") {
        $url_query_params["event"] = "finish_later";
    }
    $url_params = http_build_query($url_query_params);
    $redirect_url .= "?" . $url_params;

    $valid_event = in_array($event, ["signing_complete", "viewing_complete", "finish_later", "cancel", "decline"]) 
        || ($event === "session_timeout" && in_array($recipient_status, ["sent", "delivered"]));

    if (!$valid_event) {
        $data_to_log = [
            "error" => "unexpected return event",
            "request" => $_REQUEST
        ];
        \Globals::logData("api/redirect-docusign", $data_to_log);
    }
}

echo json_encode([
    "success" => 1, 
    "redirect_url" => $redirect_url,
    "status" => $recipient_status,
]);

?>
