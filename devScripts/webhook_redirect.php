<?php

require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/globals.php";

$raw_data = file_get_contents('php://input');

error_log("Received raw data: " . $raw_data);

$data = json_decode($raw_data, true);

if (!$data) {
    die("No Data Received");
}

error_log("Decoded data: " . print_r($data, true));

$envelopeId = isset($data['data']['envelopeId']) ? trim($data['data']['envelopeId']) : null;

error_log("Extracted Envelope ID: $envelopeId");

if (!$envelopeId) {
    error_log("Error: Missing required params: envelopeId.");
    echo "Error. Missing required params: envelopeId.";
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
    error_log("Failed to locate envelope id: $envelopeId");
    echo "Failed to locate envelope id.";
    exit();
}

if ($is_spx) {
    include('spx_docusign_webhook.php');
} else {
    include('docusign_webhook.php');
}




