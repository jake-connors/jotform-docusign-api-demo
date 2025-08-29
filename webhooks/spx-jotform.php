<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Globals.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Erp.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Crm.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/spx_classes/spx_docusign_helper.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/spx_classes/spx_com_helper.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/spx_classes/spx_email_helper.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/ApiRequest.php";

$ApiRequest = new ApiRequest();
$ApiRequest->check_hmac();

$requestData = $ApiRequest->requestData;

if (empty($requestData["rawRequest"])) {
    $data_to_log = [
        "error" => "no rawRequest",
        "request" => $_REQUEST
    ];
    logData($data_to_log);
    exit();
} 

$incoming = $requestData["rawRequest"];
$incoming = json_decode($incoming, true);
$in = $incoming;

$ip_address = $requestData["ip"];
$name = $in["q214_name"]; 
$first = unicode_decode($name["first"]);
$last = unicode_decode($name["last"]);
$email = trim($in["q201_email"]);
// Karla: added license questions
$business_license = $in["q241_doYou"];
// Karla: not sure if the below needs formatting for multiple numbers?
$license_numbers = $in["q1011_licenseNumbers"];
$account_type = $in["q236_typeOf"];
if ($account_type === "Business") {
    $company_name = unicode_decode($in["q239_companyName"]);
} else {
    $company_name = $first . " " . $last;
}
$job_title = unicode_decode($in["q263_jobTitle"]);
$primary_phone = $in["q140_primaryPhone"]["full"];
$secondary_phone = $in["q217_secondaryPhone"]["full"];
$primary_phone = \Erp::phoneConvert($primary_phone);
$secondary_phone = \Erp::phoneConvert($secondary_phone);
$addr_line1 = unicode_decode($in["q228_streetAddress"]);
$addr_line2 = unicode_decode($in["q229_streetAddress229"]); 
$city = unicode_decode($in["q230_city"]);
$province = unicode_decode($in["q231_province"]);
$state = $in["q232_state"] ?? $province;
$country = strtoupper($in["q234_country"]);
if ($country == "UNITED STATES OF AMERICA") {
	$country = "";
}
$zip = trim(strtoupper($in["q233_zip233"]));
$checkbox_options = $in["q255_typeA"] ?? $in["q255_checkboxOptions"];
$is_requesting_tax_exempt = false;
// $interested_in_webb_rewards = false; //remove
if (is_array($checkbox_options)) {
    foreach ($checkbox_options as $c) {
        if ($c === "Request Tax Exempt Status (requires additional approval)") {
            $is_requesting_tax_exempt = true;
        }
// Karla: removed
        //  else if ($c === "Interested in Webb Customer Rewards program") { //remove, there is no rewards program 
        //     $interested_in_webb_rewards = true; //remove
        // }
    }
}
// Karla: doYouNeedCredit  may need to be updated
$is_applying_for_credit = isset($in["q260_needCredit"]) ? $in["q260_needCredit"] == "Yes" : $in["q260_doYouNeedCredit"] == "Yes";

// Karla: Removed because branch will always be set to 125
// $choosen_branch_value = "";
// $is_branch_specific = false;
// $is_selecting_own_branch = isset($in["q272_autoSelect"]) ? $in["q272_autoSelect"] == "No" : (isset($in["q267_autoSelect"]) ? $in["q267_autoSelect"] == "No" : false); 
// if (isset($in["q999_branch_specific"]) && $in["q999_branch_specific"] !== "") {
//     $choosen_branch_value = $in["q999_branch_specific"];
//     $is_branch_specific = true;
// } elseif ($is_selecting_own_branch) { //shouldnt need this, will be only one branch 
//     $orig_select_branch_value = $in["q269_selectBranch"] ?? ($in["q268_selectBranch"] ?? "");
//     $choosen_branch_value = substr($orig_select_branch_value, 0, strpos($orig_select_branch_value, " "));
//     $choosen_branch_value = intval($choosen_branch_value);
//     if ($choosen_branch_value == 0) {
//         $choosen_branch_value = substr($orig_select_branch_value, strpos($orig_select_branch_value, "#") + 1);
//     }
// }
if (isset($in["q270_companyProducts"])) {
    $company_products = $in["q270_companyProducts"];
// Karla: not sure what to update the below to
} elseif (isset($in["q270_companyProducts"])) {
    $company_products = $in["q270_companyProducts"];
} else {
    $company_products = [];
}
if (!is_array($company_products)) {
    $company_products = [];
}
$company_products_string = "";
foreach ($company_products as $prod) {
    $company_products_string .= $prod . ", ";
}
$company_products_string = substr($company_products_string, 0, strlen($company_products_string) - 2); // remove the final comma
$unique_id = $in["q226_uniqueId"]; // id generated from jotform

// Karla: Is this needed? Do we still need the coords if not letting the user choose branch?
$user_coords_lat = "";
$user_coords_lng = "";
$in_branch = "125";
$display_br = $in_branch;

// Karla: wouldn't we just need to say: $in_branch = 125 ?
// Karla: or change the below to "branch" => 125 ?

$data_in = [
    "cust_name" => trim(strtoupper($company_name)),
    "branch" => $in_branch,
    "is_branch_specific" => $is_branch_specific ? "1" : "0",
    "city" => trim(strtoupper($city)),
    "state" => strtoupper($state),
    "zip" => $zip,
    "zip_lat" => $user_coords_lat,
    "zip_lng" => $user_coords_lng,
    "addr_line1" => trim(strtoupper($addr_line1)),
    "addr_line2" => trim(strtoupper($addr_line2)),
    "country" => trim($country),
    "primary_phone" => $primary_phone,
    "secondary_phone" => $secondary_phone,
    "email" => trim(strtoupper($email)),
    "account_type" => $account_type === "Business" ? "B" : "P"
];
$custreg_result = \Erp::W4INTSUBCUSTREGSPX($data_in);
$custreg_result_status = $custreg_result["result"] ?? "";
if ($custreg_result_status === "invalid_addr") {
    // city, state, zip combo are not found in ZIPREF file. redirect to "invalid display page", log, and exit
    $redirect_url = "/spxbf/new.customer.process+@WHAT=INVALID.ZIPREF"; 
    createRedirectURL($unique_id, $redirect_url);
    $data_to_log = [
        "error" => "invalid address - ZIPREF file",
        "city" => $city, 
        "state" => $state, 
        "zip" => $zip, 
        "unique_id" => $unique_id
    ];
    logData($data_to_log);
    exit();
} elseif ($custreg_result_status !== "success") {
    // redirect to "completed" screen if failed?
    $redirect_url = "/spxbf/new.customer.process+@WHAT=COMPLETE"; 
    createRedirectURL($unique_id, $redirect_url);
    $data_to_log = [
        "error" => "W4INTSUBCUSTREG error",
        "data_in" => $data_in, 
        "uoj_result" => $custreg_result,
        "unique_id" => $unique_id
    ];
    logData($data_to_log);
    exit();
}
$branch_info = $custreg_result["branch_info"];
$display_br = $branch_info["display_num"];
$branch = $branch_info["num"];
$cust_num = $custreg_result["cust_num"];
$dup_customers = $custreg_result["dups"];
$dup_customers = array_slice($dup_customers, 0, 15); // slice to first 15 dups

if ($is_applying_for_credit || $is_requesting_tax_exempt) {
    $docusign_helper = new SPX_Docusign_Helper();
    if ($is_applying_for_credit) {
        $prefill_values = [
            "Owner_1_Email" => $email,
            "Owner_1_First_Name" => $first,
            "Owner_1_Last_Name" => $last,
            "Owner_1_Job_Title" => $job_title,
            "Job_Title_1" => $job_title,
            "Business_Name" => $company_name,
            "Business_Phone" => $primary_phone,
            "Business_Cell" => $secondary_phone,
            "Online_Account_Email" => $email,
            "Business_Address" => $addr_line1,
            "Business_City" => $city,
            "Business_State" => $state,
            "Business_Province" => $province,
            "Business_Zip" => $zip,
            "Title" => "Cust# " . $cust_num . ", BR: " . $branch . ", Name: " . $company_name
        ];
        $args = [
            "type" => "cust_credit_app_new",
            "signer_email" => $email,
            "signer_name" => $first . " " . $last,
            "client_user_id" => $unique_id,
            "document_name" => "Credit App | Cust# " . $cust_num . ", BR: " . $branch . ", Name: " . $company_name,
            "prefill_values" => $prefill_values,
            "cust_num" => $cust_num
        ];
        $redirect_url = $docusign_helper->createEnvelopeNew($args); //already redirecting to spx
    } else {
        // is_requesting_tax_exempt
        $args = [
            "type" => "tax_exempt",
            "signer_email" => $email,
            "signer_name" => $first . " " . $last,
            "client_user_id" => $unique_id,
            "document_name" => "Tax Exempt | Cust#: " . $cust_num . ", BR: " . $branch . ", Name: " . $company_name,
            "prefill_values" => ["Title" => "Tax Exempt | Cust#: " . $cust_num . ", BR: " . $branch . ", Name: " . $company_name]
        ];
        $redirect_url = $docusign_helper->createEnvelopeNew($args); //already redirecting to spx_docusign_helper 
    }
} else {
    $redirect_url = "/spxbf/new.customer.process+@WHAT=COMPLETE"; 
}
createRedirectURL($unique_id, $redirect_url);
$auto_select_br = $is_selecting_own_branch ? "0" : "1";
createCustRegLog($cust_num, $email, $auto_select_br, $display_br, $branch);

if ($is_applying_for_credit) {
    addDupsToCustomerXref($unique_id, $dup_customers);
}

$title = "SALES CONTACT";
// \Crm_Helper::createContact($cust_num, strtoupper($first), strtoupper($last), $email, $title, $primary_phone, $secondary_phone, $addr_line1, $addr_line2, $city, $state, $zip);
// \Crm_Helper::createProfile($cust_num, $company_products);

\SPX_Helper::createOnlineAccount($cust_num, strtoupper($email), strtoupper($first), strtoupper($last), $ip_address);
$password = \SPX_Helper::getPassword($email);

// send emails to user + branch
$branch_name_and_state = $branch_info["name"] . ", " . $branch_info["state"];
$branch_phone = $branch_info["phone"];
$branch_email = $branch_info["email"];
$branch_and_name = $branch . " - " . $branch_info["name"];

\SPX_Email_Helper::sendAccountCreated($email, $first . " " . $last, $cust_num, $password, $branch_name_and_state, $branch_phone, $is_applying_for_credit);
$company_name = strtoupper($company_name) . " (COD)";
\SPX_Email_Helper::sendBranchAccountCreated($branch_email, $branch_and_name, $cust_num, $company_name, $first, $last, $primary_phone, $secondary_phone, $email, $is_applying_for_credit, $dup_customers, $account_type, $company_products_string); //remove interested in webb rewards 

// Functions : 

function createRedirectURL($unique_id, $redirect_url) {
    // used for polling page
    $params = [
        "unique_id" => $unique_id,
        "redirect_url" => $redirect_url
    ];
    $redirect_url = dbi_query("SELECT * FROM redirect_url WHERE unique_id = ?:unique_id", $params);
    if (count($redirect_url)) {
        $sql = "UPDATE redirect_url SET redirect_url = ?:redirect_url WHERE unique_id = ?:unique_id";
    } else {
        $sql = "INSERT INTO redirect_url (unique_id, redirect_url) VALUES (?:unique_id, ?:redirect_url)";
    }
    dbi_query($sql, $params);
}

function addDupsToCustomerXref($unique_id, $dup_customers) {
    $json_dup_customers = json_encode($dup_customers);
    $params = [
        "unique_id" => $unique_id,
        "dup_customers" => $json_dup_customers
    ];
    $dup_customers = dbi_query("SELECT * FROM cn_docusign_xref WHERE unique_id = ?:unique_id", $params);
    if (count($dup_customers)) {
        $sql = "UPDATE cn_docusign_xref SET dup_customers = ?:dup_customers WHERE unique_id = ?:unique_id";
    } else {            
        $params["data"] = json_encode($params);
        $sql = "INSERT INTO log (event, data) VALUES ('no xref : add dups to cust xref', ?:data)";
    }
    dbi_query($sql, $params);
}

function unicode_decode($string) {
    // convert first to UTF-8 , then to ASCII
    $string = mb_convert_encoding(json_decode('"' . $string . '"'), "UTF-8", "UTF-8");
    $string = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $string);
    return $string;
}

function logData($data) {
    // generic log
    $sql_log = "INSERT INTO log (event, data) VALUES (?:event, ?:data)";
    $params = [
        "event" => "spx jotform webhook",
        "data" => json_encode($data, JSON_PRETTY_PRINT)
    ];
    dbi_query($sql_log, $params);
}

function createCustRegLog($cust_num, $email, $auto_select_br, $display_br, $assigned_br) {
    // log for certain customer forms inputs that isn't saved on customer record
    $sql_insert_reg_log = "INSERT INTO cust_reg_log 
    (customer_number, auto_select_br, first_br, assigned_br, email) 
    VALUES (?:cust_num, ?:auto_select_br, ?:first_br, ?:assigned_br, ?:email);";
    $params = [
        "cust_num" => $cust_num,
        "auto_select_br" => $auto_select_br,
        "first_br" => $display_br,
        "assigned_br" => $assigned_br,
        "email" => $email
    ];
    dbi_query($sql_insert_reg_log, $params);
}

?>
