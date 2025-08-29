<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Globals.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Erp.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Crm.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Docusign.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/FwwebbCom.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Email.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/ApiRequest.php";

$ApiRequest = new ApiRequest();
$ApiRequest->check_hmac();

echo json_encode([
    "success" => 1,
    "message" => "HMAC verified successfully.",
]);
exit();

$requestData = $ApiRequest->requestData;

if (empty($requestData["rawRequest"])) {
    $data_to_log = [
        "error" => "no rawRequest",
        "request" => $requestData
    ];
    \Globals::logData("webhooks/jotform", $data_to_log);

    echo json_encode([
        "success" => 0,
        "error" => "Missing required data: rawRequest",
        "message" => "",
    ]);
    http_response_code(400);
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
$interested_in_webb_rewards = false;
if (is_array($checkbox_options)) {
    foreach ($checkbox_options as $c) {
        if ($c === "Request Tax Exempt Status (requires additional approval)") {
            $is_requesting_tax_exempt = true;
        } else if ($c === "Interested in Webb Customer Rewards program") {
            $interested_in_webb_rewards = true;
        }
    }
}

$is_applying_for_credit = isset($in["q260_doYou260"]) ? $in["q260_doYou260"] == "Yes" : $in["q260_needCredit"] == "Yes";
$choosen_branch_value = "";
$is_branch_specific = false;
$is_selecting_own_branch = isset($in["q272_autoSelect"]) ? $in["q272_autoSelect"] == "No" : (isset($in["q267_autoSelect"]) ? $in["q267_autoSelect"] == "No" : false);

if (isset($in["q999_branch_specific"]) && $in["q999_branch_specific"] !== "") {
    $choosen_branch_value = $in["q999_branch_specific"];
    $is_branch_specific = true;
} elseif ($is_selecting_own_branch) {
    $orig_select_branch_value = $in["q269_selectBranch"] ?? ($in["q268_selectBranch"] ?? "");
    $choosen_branch_value = substr($orig_select_branch_value, 0, strpos($orig_select_branch_value, " "));
    $choosen_branch_value = intval($choosen_branch_value);
    if ($choosen_branch_value == 0) {
        $choosen_branch_value = substr($orig_select_branch_value, strpos($orig_select_branch_value, "#") + 1);
    }
}

if (isset($in["q268_companyProducts"])) {
    $company_products = $in["q268_companyProducts"];
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

$user_coords_lat = "";
$user_coords_lng = "";
$in_branch = $choosen_branch_value;
if ($choosen_branch_value === "") {
    $zip5 = substr($zip, 0, 5);
    $user_coords_result = \FwwebbCom::getCoords($zip5);
    $user_coords_lat = $user_coords_result["latitude"];
    $user_coords_lng = $user_coords_result["longitude"];
    $user_coords_state = $user_coords_result["state"];
    if ($user_coords_state === "" || $user_coords_lat === "" || $user_coords_lng === "") {
        // zip code not found (consider them as out of footprint) & log the zip that wasn't found
        $redirect_url = "/wobf/new.customer.process+@WHAT=INVALID.ZIPREF";
        createRedirectURL($unique_id, $redirect_url);
        $data_to_log = [
            "error" => "invalid zip - get closest branch",
            "zip" => $zip,
            "unique_id" => $unique_id
        ];
        \Globals::logData("webhooks/jotform", $data_to_log);

        echo json_encode([
            "success" => 1,
            "message" => "Invalid zip code - get closest branch.",
            "cust_num" => "",
            "redirect_url" => $redirect_url,
        ]);
        http_response_code(400);
        exit();
    }

    $footprint_states = ["MA", "CT", "ME", "NH", "NJ", "NY", "PA", "RI", "VT"];
    if (!in_array($user_coords_state, $footprint_states)) {
        // IF OUTSIDE OF FOOTPRINT SET BR TO 112
        $in_branch = "112";
    }
}

$display_br = $in_branch;

$cust_reg_data_in = [
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

$custreg_result = \Erp::W4INTSUBCUSTREG($cust_reg_data_in);

$custreg_result_status = $custreg_result["result"] ?? "";

if ($custreg_result_status === "invalid_addr") {
    // city, state, zip combo are not found in ZIPREF file. redirect to "invalid display page", log, and exit
    $redirect_url = "/wobf/new.customer.process+@WHAT=INVALID.ZIPREF";
    createRedirectURL($unique_id, $redirect_url);

    $data_to_log = [
        "error" => "invalid address - ZIPREF file",
        "city" => $city, 
        "state" => $state, 
        "zip" => $zip, 
        "unique_id" => $unique_id
    ];
    \Globals::logData("webhooks/jotform", $data_to_log);

    echo json_encode([
        "success" => 1,
        "message" => "Invalid zip code - ZIPREF file.",
        "redirect_url" => $redirect_url,
        "cust_num" => "",
    ]);
    http_response_code(200);
    exit();
} elseif ($custreg_result_status !== "success") {
    $redirect_url = "/wobf/new.customer.process+@WHAT=INVALID.ZIPREF";
    createRedirectURL($unique_id, $redirect_url);

    $data_to_log = [
        "error" => "W4INTSUBCUSTREG error",
        "data_in" => $cust_reg_data_in, 
        "uoj_result" => $custreg_result,
        "unique_id" => $unique_id
    ];
    \Globals::logData("webhooks/jotform", $data_to_log);

    echo json_encode([
        "success" => 0,
        "error" => "Failed to register customer - W4INTSUBCUSTREG error.",
        "message" => "",
    ]);
    http_response_code(400);
    exit();
}

$branch_info = $custreg_result["branch_info"];
$display_br = $branch_info["display_num"];
$branch = $branch_info["num"];
$cust_num = $custreg_result["cust_num"];
$dup_customers = $custreg_result["dups"];
$dup_customers = array_slice($dup_customers, 0, 15); // slice to first 15 dups

if ($is_applying_for_credit || $is_requesting_tax_exempt) {
    $docusign_helper = new Docusign();
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
        $redirect_url = $docusign_helper->createEnvelopeNew($args);
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
        $redirect_url = $docusign_helper->createEnvelopeNew($args);
    }
} else {
    $redirect_url = "/wobf/new.customer.process+@WHAT=COMPLETE";
}

createRedirectURL($unique_id, $redirect_url);

$auto_select_br = $is_selecting_own_branch ? "0" : "1";
createCustRegLog($cust_num, $email, $auto_select_br, $display_br, $branch);

if ($is_applying_for_credit) {
    addDupsToCustomerXref($unique_id, $dup_customers);
}

$title = "SALES CONTACT";
\Crm::createContact($cust_num, strtoupper($first), strtoupper($last), $email, $title, $primary_phone, $secondary_phone, $addr_line1, $addr_line2, $city, $state, $zip);
\Crm::createProfile($cust_num, $company_products);

\FwwebbCom::createOnlineAccount($cust_num, strtoupper($email), strtoupper($first), strtoupper($last), $ip_address);
$password = \FwwebbCom::getPassword($email);

// send emails to user + branch
$branch_name_and_state = $branch_info["name"] . ", " . $branch_info["state"];
$branch_phone = $branch_info["phone"];
$branch_email = $branch_info["email"];
$branch_and_name = $branch . " - " . $branch_info["name"];

\Email::sendAccountCreated($email, $first . " " . $last, $cust_num, $password, $branch_name_and_state, $branch_phone, $is_applying_for_credit);

$company_name = strtoupper($company_name) . " (COD)";
\Email::sendBranchAccountCreated($branch_email, $branch_and_name, $cust_num, $company_name, $first, $last, $primary_phone, $secondary_phone, $email, $is_applying_for_credit, $interested_in_webb_rewards, $dup_customers, $account_type, $company_products_string);

// Jake 5/22/2025 - adding crm prospect customer convert
$prospectCustomerData = [
    "custNum" => $cust_num,
    "companyName" => $company_name,
    "email" => $email,
    "phone" => phoneConvertRaw($primary_phone),
    "addr1" => $addr_line1,
    "city" => $city,
    "state" => $state,
    "zip" => $zip,
];
\Crm::convertProspectCustomer($prospectCustomerData);

echo json_encode([
    "success" => 1,
    "message" => "Webhook processed successfully.",
    "cust_num" => $cust_num,
    "redirect_url" => $redirect_url
]);

/**
 * Creates or updates the redirect URL for a unique ID. Used to store the URL to redirect the user after processing.
 */
function createRedirectURL($unique_id, $redirect_url) {
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

function createCustRegLog($cust_num, $email, $auto_select_br, $display_br, $assigned_br) {
    // log for certain customer forms inputs that aren't saved on customer record, used in ERP client requests
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

/**
 * Converts phone number to raw format (digits only)
 * Clean phone number for sending to CRM
 * @param string $phone
 * @return string
 */
function phoneConvertRaw($phone)
{
    return preg_replace("/\D/", "", $phone);
}

?>
