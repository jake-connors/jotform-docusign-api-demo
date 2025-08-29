<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Globals.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Erp.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Crm.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Docusign.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Email.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/ApiRequest.php";

$ApiRequest = new ApiRequest();
$ApiRequest->check_hmac();

$requestData = $ApiRequest->requestData;

if (empty($requestData)) {
    $data_to_log = [
        "error" => "no request data",
        "request" => $requestData
    ];
    \Globals::logData("webhooks/docusign", $data_to_log);

    echo json_encode([
        "success" => 0,
        "error" => "No request data received.",
        "message" => "",
    ]);
    http_response_code(400);
    exit();
}

$envelopeId = isset($requestData['data']['envelopeId']) ? trim($requestData['data']['envelopeId']) : null;

if (!$envelopeId) {
    error_log("Error: Missing required params: envelopeId.");
    echo json_encode([
        "success" => 0,
        "error" => "Missing required params: envelopeId.",
        "message" => "",
    ]);
    http_response_code(400);
    exit();
}

$params = [
    'env_id' => $envelopeId
];

$result = dbi_query("SELECT cust_reg_log.assigned_br FROM cn_docusign_xref INNER JOIN cust_reg_log ON cn_docusign_xref.customer_number = cust_reg_log.customer_number WHERE cn_docusign_xref.envelope_id = ?:env_id", $params);

$is_spx = false;

if (count($result) && isset($result[0]["assigned_br"])) {
    $is_spx = ($result[0]["assigned_br"] == 125);
} else {
    $result = dbi_query("SELECT * FROM cn_docusign_xref WHERE envelope_id = ?:env_id", $params);
    if (!count($result)) {
        error_log("Failed to locate envelope id: $envelopeId");
        echo json_encode([
            "success" => 1,
            "message" => "Webhook processed successfully. envelopeId not found.",
        ]);
        exit();
    }
}

if ($is_spx) {
    include('spx_docusign.php');
    exit(); 
} 

else {
    $event = $requestData["event"] ?? "invalid event";

    $requestData = $requestData["data"] ?? [];

    $Docusign = new Docusign();

    $env_id = $requestData["envelopeId"] ?? "";
    $env_type = $Docusign->getEnvelopeType($env_id);

    if ($env_type === "") {
        // Some credit forms do not rely on this webhook (e.g. "Job Account")
        echo json_encode([
            "success" => 1,
            "message" => "Webhook processed successfully. Envelope type not handled.",
        ]);
        exit();
    }

    if ($event === "envelope-completed") {
        if ($env_type === "tax_exempt") {
            $Docusign->moveEnvToFolder($env_id);
        } elseif ($env_type === "file_upload") {
            $uploadRecipient = $Docusign->getEnvelopeRecipient($env_id, 1);
            $clientUserId = $uploadRecipient["clientUserId"];
            $Docusign->moveEnvToFolder($env_id, $clientUserId);
        } elseif ($env_type === "pg_app") {
            \Email::sendCompletedPG($env_id, $Docusign->new_cust_credit_email);
        }

        $message = "Envelope completed.";

    } elseif ($event === "recipient-finish-later") {
        $customer_num = $Docusign->getCustomerNumber($env_id);
        $br_num = \Erp::getCustBranchNum($customer_num);

        $rec_id = $requestData["recipientId"];
        $recipient = $Docusign->getEnvelopeRecipient($env_id, $rec_id);
        $is_file_upload = $recipient["roleName"] === "File Uploader";
        $recipient_type = $recipient["recipientType"];

        $valid_finish_later_types = ["signer"];
        if (in_array($recipient_type, $valid_finish_later_types)) {
            $to = $recipient["email"];
            $to_name = $recipient["name"];
            $is_session_timeout = false;
            \Email::sendFinishLater($to, $to_name, $env_id, $rec_id, $customer_num, $br_num, $is_session_timeout, $is_file_upload,);
        }

        $message = "Recipient finish'ed later.";
        
    } elseif ($event === "recipient-completed") {
        $rec_id = $requestData["recipientId"];
        $env_data = $Docusign->getEnvelopeFormData($env_id);
        $cust_name = $zip = $state = $city = $br_num = $addr_line1 = $job_title = "";
        $cust_num = $Docusign->getCustomerNumber($env_id);
        $recipient = $Docusign->getEnvelopeRecipient($env_id, $rec_id);
        $rec_role = $recipient["roleName"];
        $cust_name = $env_data["Business Name"] ?? "";

        if ($rec_role === "Credit Applicant New" || $rec_role === "Credit Applicant Existing") {
            $Docusign->prepareAndSendApprovalStage($env_id, $rec_role, $cust_num, $cust_name);
        } elseif ($rec_role === "Credit Approver") {
            $send_pg_email = $env_data["Guarantor Email"] == "Yes";
            if ($send_pg_email) {
                $job_title = $env_data["Job Title 1"];
                if ($env_data["Billing Address Same as Above"] === "Yes") {
                    $zip = $env_data["Business Zip"];
                    $state = $env_data["Business State"];
                    $city = $env_data["Business City"];
                    $addr_line1 = $env_data["Business Address"];
                } else {
                    $zip = $env_data["Business Zip 6732cb89-c7b8-4c6e-937d-b1e7d08dd69b"];
                    $state = $env_data["Business State 80901789-468e-4d6a-a27c-70eb8af8353b"];
                    $city = $env_data["Business City 1eb7f492-573a-410b-9684-d7f2fc62092b"];
                    $addr_line1 = $env_data["Business Address 27c9a872-1c4b-4c22-a555-edecbba83343"];
                }
                $Docusign->addPG($env_id, $cust_num, $cust_name, $addr_line1, $city, $state, $zip, $job_title);
            } else {
                $Docusign->removePG($env_id);
                handleEnvelopeCompletedDocusign($env_id, false);
            }
        } elseif ($rec_role === "Credit Approver 2") {
            handleEnvelopeCompletedDocusign($env_id, true);
        } elseif ($rec_role === "Credit Applicant 2") {
            $br_num = \Erp::getCustBranchNum($cust_num);
            $Docusign->send2ndApprovalStage($env_id, $cust_num, $cust_name, $br_num);
        } elseif ($rec_role === "PG Signer") {
            $cust_num = $env_data["Customer#"];
            $Docusign->unpausePG($env_id, $cust_num);
        }

        $message = "Recipient completed.";
        
    } elseif ($event === "recipient-declined") {
        $rec_id = $requestData["recipientId"];
        if ($env_type === "credit_app" || $env_type === "credit_app_2nd_approval_stage") {
            $cust_num = $Docusign->popCustomerNumber($env_id); // this deletes the xref entry
            if ($rec_id == 2 || $rec_id == 4) {
                // this is the credit dept declining the app
                $br_num = \Erp::getCustBranchNum($cust_num);
                $branch_record = \Erp::getBranchRecord($br_num);

                $address1 = $branch_record["branchAddress"][0];
                $address2 = $branch_record["branchAddress"][1];
                $br_phone = $branch_record["branchPhone"];

                $env_data = $Docusign->getEnvelopeFormData($env_id);
                $all_recipients = $Docusign->getEnvelopeRecipients($env_id);

                $cust_recipient = [];
                $credit_recipient = [];
                foreach ($all_recipients as $rec) {
                    if ($rec["roleName"] === "Credit Applicant New" || $rec["roleName"] === "Credit Applicant Existing") {
                        $cust_recipient = $rec;
                    } elseif ($rec["roleName"] === "Credit Applicant 2") {
                        $cust_recipient = $rec;
                    } elseif ($rec["roleName"] === "Credit Approver") {
                        $credit_recipient = $rec;
                    } elseif ($rec["roleName"] === "Credit Approver 2") {
                        $credit_recipient = $rec;
                    }
                }
                $to = $cust_recipient["email"];
                $to_name = $cust_recipient["name"];
                $decline_reason = $credit_recipient["declinedReason"];

                $branchRecord = \Erp::getBranchRecord($br_num);
                $branchMotherBr = $branchRecord["branchMother"];
                $branchManagers = $branchRecord["branchManagers"];

                $isMotherBranch = false;
                if ($branchMotherBr == $br_num) {
                    $isMotherBranch = true;
                }

                $generalManager = $branchManagers["generalManager"];
                $operationManager = $branchManagers["operationsManager"];
                $br_manager_emails = [];

                if ($isMotherBranch) {
                    // add gm
                    foreach ($generalManager as $gm) {
                        if (str_contains($gm, "@")) {
                            $br_manager_emails[] = $gm;
                        }
                    }

                    // add om
                    foreach ($operationManager as $om) {
                        if (str_contains($om, "@")) {
                            $br_manager_emails[] = $om;
                        }
                    }
                } else {
                    // twig
                    $storeManager = $branchManagers["storeManager"];

                    $motherBranchRecord = \Erp::getBranchRecord($branchMotherBr);
                    $motherBranchManagers = $motherBranchRecord["branchManagers"];
                    $motherBranchGeneralManager = $motherBranchManagers["generalManager"];
                    $motherBranchOperationManager = $motherBranchManagers["operationManager"];

                    foreach ($motherBranchGeneralManager as $gm) {
                        if (str_contains($gm, "@")) {
                            $br_manager_emails[] = $gm;
                        }
                    }

                    foreach ($motherBranchOperationManager as $om) {
                        if (str_contains($om, "@")) {
                            $br_manager_emails[] = $om;
                        }
                    }

                    foreach ($storeManager as $sm) {
                        if (str_contains($sm, "@")) {
                            $br_manager_emails[] = $sm;
                        }
                    }
                }
                if (trim($decline_reason) != "" && $to != "") {
                    // sends to customer with branch managers as bcc             
                    \Email::sendAccountDenied($to, $to_name, $cust_num, $decline_reason, $address1, $address2, $br_phone, $br_manager_emails);
                } else {
                    // sends email to branch managers
                    $to = $br_manager_emails;
                    \Email::sendAccountDenied($to, $to_name, $cust_num, $decline_reason, $address1, $address2, $br_phone, []);
                }
            }
        }

        $message = "Recipient declined.";
        
    } elseif ($event === "envelope-voided") {
        // delete the xref entry (this event is either user clicks 'Void', or credit dept clicks 'Void')
        if ($env_type === "credit_app" || $env_type === "credit_app_2nd_approval_stage") {
            $sql_delete_xref = "DELETE FROM cn_docusign_xref WHERE envelope_id = ?:envelope_id";
            $params_delete_xref = [
                "envelope_id" => $env_id
            ];
            dbi_query($sql_delete_xref, $params_delete_xref);
        }

        $message = "Envelope voided.";

    } else {
        // some other event or invalid event
        $data_to_log = [
            "error" => "event not handled",
            "request" => $requestData
        ];
        \Globals::logData("webhooks/docusign", $data_to_log);

        $message = "Event not handled: " . $event;

    }
}

$retMessage = $message ?? "";
$retMessage = "Webhook processed successfully. " . $retMessage;

echo json_encode([
    "success" => 1,
    "message" => $retMessage,
]);

/**
 * Convert state code to full name
 */
function stateConvertDocusign($state)
{
    $ret_state = $state;
    $states_arr = array('AL' => "Alabama", 'AK' => "Alaska", 'AZ' => "Arizona", 'AR' => "Arkansas", 'CA' => "California", 'CO' => "Colorado", 'CT' => "Connecticut", 'DE' => "Delaware", 'FL' => "Florida", 'GA' => "Georgia", 'HI' => "Hawaii", 'ID' => "Idaho", 'IL' => "Illinois", 'IN' => "Indiana", 'IA' => "Iowa", 'KS' => "Kansas", 'KY' => "Kentucky", 'LA' => "Louisiana", 'ME' => "Maine", 'MD' => "Maryland", 'MA' => "Massachusetts", 'MI' => "Michigan", 'MN' => "Minnesota", 'MS' => "Mississippi", 'MO' => "Missouri", 'MT' => "Montana", 'NE' => "Nebraska", 'NV' => "Nevada", 'NH' => "New Hampshire", 'NJ' => "New Jersey", 'NM' => "New Mexico", 'NY' => "New York", 'NC' => "North Carolina", 'ND' => "North Dakota", 'OH' => "Ohio", 'OK' => "Oklahoma", 'OR' => "Oregon", 'PA' => "Pennsylvania", 'RI' => "Rhode Island", 'SC' => "South Carolina", 'SD' => "South Dakota", 'TN' => "Tennessee", 'TX' => "Texas", 'UT' => "Utah", 'VT' => "Vermont", 'VA' => "Virginia", 'WA' => "Washington", 'DC' => "Washington D.C.", 'DC' => "Washington DC", 'WV' => "West Virginia", 'WI' => "Wisconsin", 'WY' => "Wyoming");
    foreach ($states_arr as $key => $value) {
        $states_arr[$key] = strtoupper($value);
    }
    $states_arr = array_flip($states_arr);
    if (isset($states_arr[strtoupper($state)])) {
        $ret_state = $states_arr[strtoupper($state)];
    }
    return $ret_state;
}

/**
 * Upgrade the COD record to a "credit" CUSTOMER, create CONTACT, CRM profile, etc.
*/
function handleEnvelopeCompletedDocusign($env_id, $is_2nd_approval_stage)
{
    global $Docusign;

    $env_data = $Docusign->getEnvelopeFormData($env_id);

    $cust_num = $Docusign->popCustomerNumber($env_id); // returns cust# from xref table & deletes it from xref table
    if ($cust_num == -1) {
        $data_to_log = [
            "error" => "getCustomerNumber returned -1. See DocuSign webapp for error/retry details.",
            "env_id" => $env_id,
            "env_data" => $env_data,
        ];
        \Globals::logData("webhooks/docusign", $data_to_log);
        return;
    }

    $bus_name = $env_data["Business Name"];
    $bus_address = $env_data["Business Address"];
    $bus_city = $env_data["Business City"];
    $bus_state = $env_data["Business State"];
    $bus_zip = $env_data["Business Zip"];
    $bus_state = stateConvertDocusign($bus_state);
    $bus_phone = \Erp::phoneConvert($env_data["Business Phone"]);
    $bus_fax = $env_data["Business Fax"];
    $bus_cell = \Erp::phoneConvert($env_data["Business Cell"]);

    $usa_strings = ["USA", "US", "U.S.", "U.S.A.", "U.S", "US.", "U.S.A", "UNITED STATES", "UNITED STATES OF AMERICA", "UNITED STATES AMERICA", "AMERICA"];
    if ($env_data["Country"] == "" || in_array(trim(strtoupper($env_data["Country"])), $usa_strings)) {
        $country = "";
    } else {
        $country = $env_data["Country"];
    }

    if ($env_data["Single Email"] == "Yes") {
        $single_email_chk = 1;
    } else {
        $single_email_chk = "";
    }

    $invoice_email = [];
    $paperless_flag = 0;
    if ($env_data["Paperless Billing"] == "Yes") {
        $paperless_flag = 1;
        if ($env_data["Paperless Billing Email 1"] != "") {
            array_push($invoice_email, strtoupper($env_data["Paperless Billing Email 1"]));
        }
        if ($env_data["Paperless Billing Email 2"] != "") {
            array_push($invoice_email, strtoupper($env_data["Paperless Billing Email 2"]));
        }
        if ($env_data["Paperless Billing Email 3"] != "") {
            array_push($invoice_email, strtoupper($env_data["Paperless Billing Email 3"]));
        }
        if ($env_data["Paperless Billing Email 4"] != "") {
            array_push($invoice_email, strtoupper($env_data["Paperless Billing Email 4"]));
        }
    }

    $statement_email = [];
    $statement_flag = 0;
    if ($env_data["Monthly Statement"] == "Yes") {
        $statement_flag = 1;
        if ($env_data["Monthly Statement Email 1"] != "") {
            array_push($statement_email, strtoupper($env_data["Monthly Statement Email 1"]));
        }
        if ($env_data["Monthly Statement Email 2"] != "") {
            array_push($statement_email, strtoupper($env_data["Monthly Statement Email 2"]));
        }
        if ($env_data["Monthly Statement Email 3"] != "") {
            array_push($statement_email, strtoupper($env_data["Monthly Statement Email 3"]));
        }
        if ($env_data["Monthly Statement Email 4"] != "") {
            array_push($statement_email, strtoupper($env_data["Monthly Statement Email 4"]));
        }
    }

    // Personal Guarantee (if they sign the bottom one)
    if ($env_data["Guarantor 1 Name"] != "" || $env_data["Guarantor 2 Name"] != "") {
        $p_guar = "Y";
    } else {
        $p_guar = "";
    }

    $credit_amount_field_name = "Credit Amount";
    $cr_code_field_name = "Cr-codes";
    if ($is_2nd_approval_stage) {
        $credit_amount_field_name = "Credit Amount 2";
        $cr_code_field_name = "Cr-codes 2";
    }
    $credit_amount = str_replace(",", "", $env_data[$credit_amount_field_name]);
    $cr_code = $env_data[$cr_code_field_name] == "CU" ? "CU" : "";

    $owner1_first = trim(strtoupper($env_data["Owner 1 First Name"]));
    $owner1_last = trim(strtoupper($env_data["Owner 1 Last Name"]));
    $owner2_first = trim(strtoupper($env_data["Owner 2 First Name"]));
    $owner2_last = trim(strtoupper($env_data["Owner 2 Last Name"]));

    $recipient = $Docusign->getEnvelopeRecipient($env_id, 3);
    if (!count($recipient)) {
        $recipient = $Docusign->getEnvelopeRecipient($env_id, 1);
    }
    $send_email_to = $recipient["email"];
    $send_email_to_name = $recipient["name"];

    $data_in = [
        "cust_num" => $cust_num,
        "cust_name" => strtoupper($bus_name),
        "address" => strtoupper($bus_address),
        "city" => strtoupper($bus_city),
        "state" => strtoupper($bus_state),
        "zip" => $bus_zip,
        "country" => strtoupper($country),
        "phone" => $bus_phone,
        "cell" => $bus_cell,
        "fax" => $bus_fax,
        "credit_code" => $cr_code,
        "credit_amount" => $credit_amount,
        "email" => strtoupper($env_data["Online Account Email"]),
        "paperless_flag" => $paperless_flag,
        "invoice_email" => $invoice_email,
        "invoice_email_status" => $single_email_chk,
        "statement_flag" => $statement_flag,
        "statement_email" => $statement_email,
        "pg" => $p_guar,
        "check_primary_contact" => trim($env_data["Sales Contact"]) != "" ? "1" : "0",
        "owner1_fname" => $owner1_first,
        "owner1_lname" => $owner1_last,
        "owner2_fname" => $owner2_first,
        "owner2_lname" => $owner2_last,
        "welcome_letter_to" => $send_email_to,
    ];
    $credit_approve_result = \Erp::W4INTSUBCUSTCREDITAPPROVE($data_in);

    if ($credit_approve_result["primary_contact"]["found"] == "1") {
        $primary_contact = $credit_approve_result["primary_contact"];
        $contact_first = strtok($env_data["Sales Contact"], " ");
        $contact_last = substr($env_data["Sales Contact"], strpos($env_data["Sales Contact"], " ") + 1);
        $addr_line1 = $primary_contact["addr_line1"];
        $addr_line2 = $primary_contact["addr_line2"];
        $company_phone = $primary_contact["company_phone"];
        $email = $primary_contact["email"];
        $fax = $primary_contact["fax"];
        $city = $primary_contact["city"];
        $state = $primary_contact["state"];
        $zip = $primary_contact["zip"];
        $cell = $primary_contact["cell"];
        \Crm::updateContact($cust_num, true, strtoupper($contact_first), strtoupper($contact_last), $email, "SALES CONTACT", $company_phone, $cell, $fax, $addr_line1, $addr_line2, $city, $state, $zip);
    }

    // name/label of this field changed, handle old forms that were created before the change
    if (isset($env_data["Owner 1 Cell"])) {
        $owner_1_cell = \Erp::phoneConvert($env_data["Owner 1 Cell"]);
    } else if (isset($env_data["Owner 1 Phone"])) {
        $owner_1_cell = \Erp::phoneConvert($env_data["Owner 1 Phone"]);
    } else {
        $owner_1_cell = "";
    }
    // Owners / Officers - Create a CONTACT record
    $own_con_phone = $owner_1_cell;
    $own_con_email = strtoupper($env_data["Owner 1 Email"]);
    $own_con_title = strtoupper($env_data["Owner 1 Job Title"]) ?? "";
    $own2_con_phone = $env_data["Owner 2 Phone"];
    $own2_con_email = strtoupper($env_data["Owner 2 Email"]);
    $own2_con_title = strtoupper($env_data["Owner 2 Job Title"]) ?? "";

    if ($credit_approve_result["found_owner1_contact"] == "1") {
        \Crm::updateContact($cust_num, false, $owner1_first, $owner1_last, $own_con_email, $own_con_title, $own_con_phone);
    } else {
        \Crm::createContactFromCreditApp($cust_num, $owner1_first, $owner1_last, $own_con_email, $own_con_title, $own_con_phone);
    }

    if ($owner2_first != "" && $owner2_last != "") {
        if ($credit_approve_result["found_owner2_contact"] == "1") {
            \Crm::updateContact($cust_num, false, $owner2_first, $owner2_last, $own2_con_email, $own2_con_title, $own2_con_phone);
        } else {
            \Crm::createContactFromCreditApp($cust_num, $owner2_first, $owner2_last, $own2_con_email, $own2_con_title, $own2_con_phone);
        }
    }

    // CRM profile
    $suppliers = array();
    $suppliers[] = $env_data["Supplier Company 1"];
    // don't allow duplicate supplier names
    if (trim($env_data["Supplier Company 2"]) != "" && (trim(strtoupper($env_data["Supplier Company 2"])) != trim(strtoupper($env_data["Supplier Company 1"])) || trim(strtoupper($env_data["Supplier Company 2"])) != trim(strtoupper($env_data["Supplier Company 3"])))) {
        $suppliers[] = $env_data["Supplier Company 2"];
    }
    if (trim($env_data["Supplier Company 3"]) != "" && (trim(strtoupper($env_data["Supplier Company 3"])) != trim(strtoupper($env_data["Supplier Company 1"])) || trim(strtoupper($env_data["Supplier Company 3"])) != trim(strtoupper($env_data["Supplier Company 2"])))) {
        $suppliers[] = $env_data["Supplier Company 3"];
    }

    \Crm::updateProfile($cust_num, [], $env_data["Business URL"], $suppliers);
    $br_num = \Erp::getCustBranchNum($cust_num);
    $branch_record = \Erp::getBranchRecord($br_num);
    $br_addr1 = $branch_record["branchAddress"][0];
    $br_addr2 = $branch_record["branchAddress"][1];
    // send emails to user + branch
    $branch_info = $credit_approve_result["branch_info"];
    $branch_num = $branch_info["num"];
    $branch_email = $branch_info["email"];
    $branch_phone = $branch_info["phone"];
    $branch_name_and_state = $branch_info["name"] . ", " . $branch_info["state"];
    $branch_and_name = $branch_num . " - " . $branch_info["name"];

    $notify_using_einvoice_email = false;
    $notify_using_estatement_email = false;
    if (!$paperless_flag && $branch_num == 112) {
        $notify_using_einvoice_email = true;
    }
    if (!$statement_flag && $branch_num == 112) {
        $notify_using_estatement_email = true;
    }
    $credit_amount = number_format(floatval($credit_amount));
    \Email::sendAccountApproved($send_email_to, $send_email_to_name, $branch_name_and_state, $branch_phone, $credit_amount, $cust_num, $notify_using_einvoice_email, $notify_using_estatement_email, $br_addr1, $br_addr2);
    \Email::sendBranchAccountApproved($branch_email, $branch_and_name, $cust_num, strtoupper($bus_name));
}

