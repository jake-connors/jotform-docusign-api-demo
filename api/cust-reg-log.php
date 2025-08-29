<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Globals.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/ApiRequest.php";

$ApiRequest = new \ApiRequest();
$ApiRequest->check_hmac();

$requestData = $ApiRequest->requestData;

$cust_num = $requestData["custNum"] ?? "";

if (empty($cust_num)) {
    echo json_encode([
        "success" => 0,
        "error" => "Missing required param: custNum",
    ]);

    http_response_code(400);
    exit();
}

$sql_cust_reg_log = "SELECT * FROM cust_reg_log WHERE customer_number = ?:cust_num";

$params_cust_reg_log = [
    "cust_num" => $cust_num
];

$cust_reg_log = dbi_query($sql_cust_reg_log, $params_cust_reg_log);

$return_data = [
    "success" => 1,
    "log_id" => "",
    "cust_num" => "",
    "auto_select_br" => "",
    "first_br" => "",
    "assigned_br" => "",
]; 

if (count($cust_reg_log)) {
    $return_data["log_id"] = $cust_reg_log[0]["cust_reg_log_id"];
    $return_data["cust_num"] = $cust_reg_log[0]["customer_number"];
    $return_data["auto_select_br"] = $cust_reg_log[0]["auto_select_br"];
    $return_data["first_br"] = $cust_reg_log[0]["first_br"];
    $return_data["assigned_br"] = $cust_reg_log[0]["assigned_br"];
}

echo json_encode($return_data);

?>