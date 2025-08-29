<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Globals.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/vendor/autoload.php";

class Docusign
{
    ###########################################################################
    #    UPDATE: all keys and tokens are now hidden from demo public repo     #
    ###########################################################################

    private $integration_id = ""; // both prod and dev
    private $account_id = ""; // prod account
    private $secret_key = ""; // prod account

    public $new_cust_credit_email = ""; // prod account, new customer's credit apps
    private $new_cust_credit_user_id = "";

    private $existing_cust_credit_email = ""; // prod account, existing customer's credit apps
    // private $existing_cust_credit_user_id = "";

    private $tax_exempt_email = ""; // prod account, tax exempt notify
    private $tax_exempt_user_id = "";

    private $inbox_folder_id = ""; // prod account
    private $sent_folder_id = ""; // prod account

    private $access_token;
    private $base_path;
    private $return_url;

    // Have to track because 'Manage Shared Access' is not available in the API - known limitation w/ DocuSign API
    private $credit_shared_access_user_ids = [
        "", // Jake
        "", // Ami
        "", // Art
        "", // Credit Onboarding
        "", // Doug
        "", // Erin
        "", // Credit Ordering
        "", // Jamie
        "", // John
        "", // Lori
        "", // Lukas
        "", // Michelle
        "", // Mike D.
        "", // Rita
        "", // Rob Mullen
        "", // Romina
        "", // Scott
        "", // SPX Credit Onboarding
        "", // Susan
        "", // Tax Team
        "", // SPX Tax Exempt
    ];

    public function __construct($auth_code = "")
    {
        $this->return_url = \Globals::getOrderingServerUrl() . "/docusign_api.php?mode=return"; // landing page handling

        if (!\Globals::isProd()) {
            $this->account_id = ""; // dev (demo) account
            $this->secret_key = ""; // dev (demo) account
            $this->new_cust_credit_email = \Globals::getDevEmail();
            $this->new_cust_credit_user_id = "";
            $this->existing_cust_credit_email = "";
            // $this->existing_cust_credit_user_id = "";
            $this->tax_exempt_email = "";
            $this->tax_exempt_user_id = "";
            $this->inbox_folder_id = ""; // user specific: jake
            $this->sent_folder_id = ""; // user specific: jake
        }

        if ($auth_code == "") {
            $this->access_token = $this->_getAccessToken();
        } else {
            $this->access_token = $this->_getAccessTokenLoginRequired($auth_code);
        }

        $this->base_path = $this->_getBasePath();
    }

    /**
     * Returns a URL to start a new DocuSign workflow.
     * Creates the DocuSign forms: Credit App, Tax Exempt Upload, PG App, etc.
     * @return string
     */
    public function createEnvelopeNew($args)
    {
        $envelope_api = $this->_getEnvelopeApi();

        if (!\Globals::isProd()) {
            $args["signer_email"] = \Globals::getDevEmail();
        }

        $type = $args["type"] ?? "cust_credit_app_new";
        $prefill_values = $args["prefill_values"] ?? [];

        $document_id = 1;
        if ($type === "cust_credit_app_new" || $type === "cust_credit_app_existing") {
            $content_bytes = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/templates/credit_application.pdf");
        } else if ($type === "tax_exempt") {
            $content_bytes = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/templates/upload_tax_exempt.pdf");
        } else if ($type === "file_upload") {
            $content_bytes = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/templates/upload_files.pdf");
        } else if ($type === "pg_app") {
            $content_bytes = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/templates/pg_application.pdf");
            $document_id = 999;
        }
        $base64_file_content = base64_encode($content_bytes);
        $credit_app_document = new \DocuSign\eSign\Model\Document([
            "document_base64" => $base64_file_content,
            "name" => $args["document_name"],
            "file_extension" => "pdf",
            "document_id" => $document_id
        ]);
        $documents = [$credit_app_document];

        //set to max expiration date or else envelope will become void in 30 days
        $expiration = new \DocuSign\eSign\Model\Expirations(["expire_enabled" => true, "expire_after" => 999, "expire_warn" => 0]);
        $notification = new \DocuSign\eSign\Model\Notification(["expirations" => $expiration, "use_account_defaults" => false]);
        $envelope_definition_params = [
            "documents" => $documents,
            "notification" => $notification,
            "status" => "sent",
            "enable_wet_sign" => false,
        ];

        $signer1_recipient_id = 1;
        $signer2_recipient_id = 2;
        if ($type === "cust_credit_app_new" || $type === "cust_credit_app_existing") {
            // (1) new customers registering for terms. (2) existing customers applying for terms.
            $tabs_customer = $this->_getTabs($type, $signer1_recipient_id, $prefill_values);
            $tabs_customer = new \DocuSign\eSign\Model\Tabs($tabs_customer);
            $signer1 = new \DocuSign\eSign\Model\Signer([
                "email" => $args["signer_email"],
                "name" => $args["signer_name"],
                "recipient_id" => $signer1_recipient_id,
                "routing_order" => 1,
                "client_user_id" => $args["client_user_id"],
                "role_name" => $type === "cust_credit_app_new" ? "Credit Applicant New" : "Credit Applicant Existing",
                "tabs" => $tabs_customer,
                "custom_fields" => [$args["signer_name"]]
            ]);

            $tabs_credit = $this->_getTabs($type, 2, $prefill_values);
            $tabs_credit = new \DocuSign\eSign\Model\Tabs($tabs_credit);
            $signer2 = new \DocuSign\eSign\Model\Signer([
                "name" => "Credit Onboarding",
                "email" => $this->new_cust_credit_email,
                "recipient_id" => 2,
                "routing_order" => 2,
                "role_name" => "Credit Approver",
                "tabs" => $tabs_credit,
                "client_user_id" => uniqid(),
                "custom_fields" => ["Credit Onboarding"]
            ]);

            $signer_uploader = new \DocuSign\eSign\Model\CertifiedDelivery([
                "name" => "Hidden File Uploader",
                "email" => $this->new_cust_credit_email,
                "recipient_id" => 5,
                "routing_order" => 3,
                "role_name" => "Hidden File Uploader",
                "client_user_id" => uniqid(),
                "custom_fields" => ["Hidden File Uploader"],
            ]);

            $recipients = new \DocuSign\eSign\Model\Recipients(["signers" => [$signer1, $signer2], "certified_deliveries" => [$signer_uploader]]);

            $env_email_subject = $args["document_name"];
            if (strlen($env_email_subject) >= 100) {
                $env_email_subject = substr($env_email_subject, 0, 95) . "...";
            }

            $envelope_definition_params["email_subject"] = $env_email_subject;
            $envelope_definition_params["recipients"] = $recipients;
        } else if ($type === "tax_exempt") {
            // tax exempt upload
            $tabs_signer = $this->_getTabs($type, $signer1_recipient_id, $prefill_values);
            $tabs_signer = new \DocuSign\eSign\Model\Tabs($tabs_signer);
            $signer1 = new \DocuSign\eSign\Model\Signer([
                "email" => $args["signer_email"],
                "name" => $args["signer_name"],
                "recipient_id" => $signer1_recipient_id,
                "routing_order" => 1,
                "client_user_id" => $args["client_user_id"],
                "role_name" => "Exempt Uploader",
                "tabs" => $tabs_signer,
                "custom_fields" => [$args["signer_name"]]
            ]);

            $email_subject = $args["document_name"];
            $email_body = $args["document_name"];
            if (strlen($email_subject) >= 100) {
                $email_subject = substr($email_subject, 0, 95) . "...";
            }
            $email_notification = new \DocuSign\eSign\Model\RecipientEmailNotification([
                "email_subject" => $email_subject,
                "email_body" => $email_body
            ]);
            $signer2 = new \DocuSign\eSign\Model\CarbonCopy([
                "email" => $this->tax_exempt_email,
                "name" => $this->tax_exempt_email,
                "recipient_id" => $signer2_recipient_id,
                "routing_order" => 2,
                "user_id" => $this->tax_exempt_user_id,
                "email_notification" => $email_notification,
                "role_name" => "Tax Exempt Viewer",
                "custom_fields" => [$this->tax_exempt_email]
            ]);

            $recipients = new \DocuSign\eSign\Model\Recipients(["signers" => [$signer1], "carbon_copies" => [$signer2]]);

            $envelope_definition_params["email_subject"] = $email_subject;
            $envelope_definition_params["recipients"] = $recipients;
        } else if ($type === "file_upload") {
            // upload files
            $args["signer_email"] = $this->new_cust_credit_email;
            if ($args["dontAuthenticate"]) {
                $args["client_user_id"] = $this->inbox_folder_id; // this is where the env will end up
            }

            $tabs = $this->_getTabs($type, $signer1_recipient_id, $prefill_values); // prefill is empty
            $tabs = new \DocuSign\eSign\Model\Tabs($tabs);
            $signer1 = new \DocuSign\eSign\Model\Signer([
                "email" => $this->new_cust_credit_email,
                "name" => $args["signer_name"],
                "recipient_id" => $signer1_recipient_id,
                "routing_order" => 1,
                "client_user_id" => $args["client_user_id"],
                "role_name" => "File Uploader",
                "tabs" => $tabs,
                "custom_fields" => [$args["signer_name"]]
            ]);

            $email_subject = $args["file_name"];
            $email_body = $args["file_name"];
            if (strlen($email_subject) >= 100) {
                $email_subject = substr($email_subject, 0, 95) . "...";
            }

            $recipients = new \DocuSign\eSign\Model\Recipients(["signers" => [$signer1]]);

            $envelope_definition_params["email_subject"] = $email_subject;
            $envelope_definition_params["recipients"] = $recipients;
        } else if ($type === "pg_app") {
            // personal guarantor app
            $args["unique_id"] = $args["client_user_id"]; // set for recipient view

            $tabs_signer = $this->_getTabs($type, $signer1_recipient_id, $prefill_values); // prefill is empty
            $tabs_signer = new \DocuSign\eSign\Model\Tabs($tabs_signer);
            $signer1 = new \DocuSign\eSign\Model\Signer([
                "email" => $args["signer_email"],
                "name" => $args["signer_name"],
                "recipient_id" => $signer1_recipient_id,
                "routing_order" => 1,
                "client_user_id" => $args["client_user_id"],
                "role_name" => "PG Signer",
                "tabs" => $tabs_signer,
                "custom_fields" => [$args["signer_name"]]
            ]);

            $credit_dept_email_subject = "Customer pg application";
            $credit_dept_email_body = "Please review the customer pg application.";
            $credit_dept_email_notification = new \DocuSign\eSign\Model\RecipientEmailNotification([
                "email_subject" => $credit_dept_email_subject,
                "email_body" => $credit_dept_email_body
            ]);
            $signer2 = new \DocuSign\eSign\Model\CarbonCopy([
                "email" => $this->new_cust_credit_email,
                "name" => "Credit Dept",
                "recipient_id" => $signer2_recipient_id,
                "routing_order" => 2,
                "user_id" => $this->new_cust_credit_user_id,
                "email_notification" => $credit_dept_email_notification,
                "role_name" => "Credit Notify",
                "custom_fields" => ["Credit Dept"]
            ]);

            $env_email_subject = "PG app in progress";

            $recipients = new \DocuSign\eSign\Model\Recipients(["signers" => [$signer1], "carbon_copies" => [$signer2]]);

            $workflow_step = new \DocuSign\eSign\Model\WorkflowStep(["action" => "pause_before", "trigger_on_item" => "routing_order", "item_id" => 2]);
            $workflow = new \DocuSign\eSign\Model\Workflow(["workflow_steps" => [$workflow_step]]);

            $envelope_definition_params["email_subject"] = $env_email_subject;
            $envelope_definition_params["recipients"] = $recipients;
            $envelope_definition_params["workflow"] = $workflow;
        }

        $envelope_definition = new \DocuSign\eSign\Model\EnvelopeDefinition($envelope_definition_params);
        $results = $envelope_api->createEnvelope($this->account_id, $envelope_definition);
        $envelope_id = $results->getEnvelopeId();

        if ($type === "cust_credit_app_new" || $type === "cust_credit_app_existing") {
            $sql_insert_xref = "INSERT IGNORE INTO cn_docusign_xref
                (unique_id, envelope_id, customer_number)
                VALUES (?:unique_id, ?:env_id, ?:cust_num)";
            $params_insert_xref = [
                "unique_id" => $args["client_user_id"],
                "env_id" => $envelope_id,
                "cust_num" => $args["cust_num"]
            ];
            dbi_query($sql_insert_xref, $params_insert_xref);
        }

        $return_url = $this->return_url . "&session_id=" . $envelope_id . "&recipient_id=" . $signer1_recipient_id . "&type=" . $type;
        $recipient_view_request = new \DocuSign\eSign\Model\RecipientViewRequest([
            'authentication_method' => 'None',
            'recipient_id' => $signer1_recipient_id,
            'return_url' => $return_url,
            'client_user_id' => $args["client_user_id"], // setting client_user_id sets the envelope to be "embedded signing"
            'user_name' => $args["signer_name"],
            "email" => $args["signer_email"]
        ]);

        $results = $envelope_api->createRecipientView($this->account_id, $envelope_id, $recipient_view_request);
        return $results["url"];
    }

    /**
     * Returns a URL to get back to an existing envelope.
     * DocuSign form will have saved user inputs.
     * @return string
     */
    public function createEnvelopeSession($env_id, $rec_id, $view_only = false)
    {
        $envelope_api = $this->_getEnvelopeApi();
        $recipient = $this->getEnvelopeRecipient($env_id, $rec_id);
        $signer_name = $recipient["name"] ?? "";
        $client_user_id = $recipient["clientUserId"] ?? "";
        $signer_email = $recipient["email"] ?? "";
        $recipient_status = $recipient["status"] ?? "";
        $env_type = $this->getEnvelopeType($env_id);
        $return_url = $this->return_url . "&session_id=" . $env_id . "&recipient_id=" . $rec_id . "&type=" . $env_type;

        $recipient_view_request_params = [
            'authentication_method' => 'None',
            'return_url' => $return_url,
            'recipient_id' => $rec_id,
            'name' => $signer_name,
            'user_name' => $signer_name,
            "email" => $signer_email,
        ];
        if (!$view_only && $client_user_id != "" && $recipient_status != "completed") {
            $recipient_view_request_params['client_user_id'] = $client_user_id;
        }
        $recipient_view_request = new \DocuSign\eSign\Model\RecipientViewRequest($recipient_view_request_params);

        $results = $envelope_api->createRecipientView($this->account_id, $env_id, $recipient_view_request);
        return $results["url"];
    }

    public function createEnvelopeCorrectSession($env_id)
    {
        $envelope_api = $this->_getEnvelopeApi();
        $correct_view_req = new \DocuSign\eSign\Model\CorrectViewRequest();
        $view_url_obj = $envelope_api->createCorrectView($this->account_id, $env_id, $correct_view_req);
        return $view_url_obj->getUrl();
    }

    public function prepareAndSendApprovalStage($env_id, $rec_role, $cust_num, $cust_name)
    {
        $br_num = \Erp::getCustBranchNum($cust_num);
        $cust_info_display = "Credit App | Cust#: " . $cust_num . ", BR: " . $br_num . ", Name: " . $cust_name;
        if (strlen($cust_info_display) >= 100) {
            $cust_info_display = substr($cust_info_display, 0, 95) . "...";
        }
        $email_subject = $cust_info_display;

        $dup_customers = $this->getDupCustomers($env_id);

        if ($rec_role === "Credit Applicant New") {
            $credit_dept_name = "Credit Onboarding";
            $credit_dept_email = $this->new_cust_credit_email;
        } else {
            $credit_dept_name = "Credit Ordering";
            $credit_dept_email = $this->existing_cust_credit_email;
        }

        $has_sent_approval_stage = false;
        $sql_sent_approval = "SELECT sent_approval_stage FROM cn_docusign_xref WHERE envelope_id = ?:env_id";
        $sent_approval_stage = dbi_query($sql_sent_approval, ["env_id" => $env_id]);
        if (count($sent_approval_stage) && isset($sent_approval_stage[0]["sent_approval_stage"]) && $sent_approval_stage[0]["sent_approval_stage"] == "1") {
            $has_sent_approval_stage = true;
        }

        if (!$has_sent_approval_stage) {
            require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Email.php";     
   
            $is_2nd_approval_stage = false; // hardcode for readability
            \Email::sendApprovalStage($env_id, $credit_dept_email, $credit_dept_name, $email_subject, $dup_customers, $br_num, $cust_num, $cust_name, $is_2nd_approval_stage);

            dbi_query("UPDATE cn_docusign_xref SET sent_approval_stage=1 WHERE envelope_id = ?:env_id", ["env_id" => $env_id]);
        }
    }

    public function addPG($env_id, $cust_num, $cust_name, $addr_line1, $city, $state, $zip, $job_title)
    {
        $envelope_api = $this->_getEnvelopeApi();

        $pg_base64 = base64_encode(file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/templates/pg_application.pdf"));
        $document_pg = new \DocuSign\eSign\Model\Document([
            "document_base64" => $pg_base64,
            "name" => "Guarantor Application",
            "file_extension" => "pdf",
            "document_id" => 999
        ]);
        $documents = [$document_pg];

        $envelope_definition = new \DocuSign\eSign\Model\EnvelopeDefinition([
            "documents" => $documents,
            "status" => "sent",
            "enable_wet_sign" => false
        ]);

        $rec_id_orig_signer = 1;
        $rec_id_orig_credit = 2;
        $recipient_orig = $this->getEnvelopeRecipient($env_id, $rec_id_orig_signer);
        $recipient_credit_orig = $this->getEnvelopeRecipient($env_id, $rec_id_orig_credit);

        $orig_signer_name = $recipient_orig["name"];
        $orig_signer_email = $recipient_orig["email"];
        $orig_signer_client_user_id = $recipient_orig["clientUserId"];
        $credit_dept_name = $recipient_credit_orig["name"];
        $credit_dept_email = $recipient_credit_orig["email"];
        $credit_client_user_id = $recipient_credit_orig["clientUserId"];

        $rec_id_new = 3;
        $pg_prefill = [
            "Customer#" => $cust_num,
            "Name" => $cust_name,
            "Address" => $addr_line1,
            "City" => $city,
            "State/Province" => $state,
            "ZIP_Code" => $zip,
            "Guarantor_1_Job_Title" => $job_title
        ];
        $tabs_pg = new \DocuSign\eSign\Model\Tabs($this->_getTabs("credit_app_add_pg", 3, $pg_prefill));
        $signer_pg = new \DocuSign\eSign\Model\Signer([
            "email" => $orig_signer_email,
            "name" => $orig_signer_name,
            "recipient_id" => $rec_id_new,
            "routing_order" => 3,
            "client_user_id" => $orig_signer_client_user_id,
            "tabs" => $tabs_pg,
            "role_name" => "Credit Applicant 2",
            "custom_fields" => [$orig_signer_name]
        ]);

        $rec_id_credit_new = 4;
        $tabs_credit_pg = new \DocuSign\eSign\Model\Tabs($this->_getTabs("credit_app_add_pg", 4, []));
        $signer_credit_pg = new \DocuSign\eSign\Model\Signer([
            "email" => $credit_dept_email,
            "name" => $credit_dept_name,
            "recipient_id" => $rec_id_credit_new,
            "routing_order" => 4,
            "role_name" => "Credit Approver 2",
            "tabs" => $tabs_credit_pg,
            "client_user_id" => $credit_client_user_id,
            "custom_fields" => [$credit_dept_name]
        ]);

        $new_recipients = new \DocuSign\eSign\Model\Recipients(["signers" => [$signer_pg, $signer_credit_pg]]);
        $envelope_api->updateRecipients($this->account_id, $env_id, $new_recipients);
        $envelope_api->updateDocuments($this->account_id, $env_id, $envelope_definition);
        $envelope_api->createTabs($this->account_id, $env_id, "3", $tabs_pg);
        $envelope_api->createTabs($this->account_id, $env_id, "4", $tabs_credit_pg);
        $signer_uploader = new \DocuSign\eSign\Model\CertifiedDelivery(["recipient_id" => 5, "routing_order" => 5]);
        $certified_deliveries = [$signer_uploader];
        $new_recipients = new \DocuSign\eSign\Model\Recipients(["certified_deliveries" => $certified_deliveries]);
        $envelope_api->updateRecipients($this->account_id, $env_id, $new_recipients);

        require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Email.php";
        \Email::sendPG($env_id, $orig_signer_email, $orig_signer_name);
    }

    public function removePG($env_id)
    {
        $envelope_api = $this->_getEnvelopeApi();
        $rec_id_pg_signer = "3";
        if (count($this->getEnvelopeRecipient($env_id, $rec_id_pg_signer))) {
            $envelope_api->deleteRecipient($this->account_id, $env_id, $rec_id_pg_signer);
        }
    }

    public function removeAllFileUploaders($env_id)
    {
        $envelope_api = $this->_getEnvelopeApi();
        $env_recipients = $this->getEnvelopeRecipients($env_id);
        $rec_is_active_status = ["created", "delivered", "sent"];
        foreach ($env_recipients as $rec) {
            if ($rec["roleName"] === "Hidden File Uploader" && in_array($rec["status"], $rec_is_active_status)) {
                $envelope_api->deleteRecipient($this->account_id, $env_id, strval($rec["recipientId"]));
            }
        }
        return 1;
    }

    public function addFilesToCreditApp($env_id, $files)
    {
        $envelope_api = $this->_getEnvelopeApi();

        $all_env_docs = $this->_getEnvelopeDocuments($env_id);
        $docs_count = count($all_env_docs) + 1; // start at next document id

        $documents = [];
        foreach ($files as $file) {
            $file_name = $file["name"];
            $file_contents = $file["contents"];
            $doc_base64 = base64_encode($file_contents);
            $document = new \DocuSign\eSign\Model\Document([
                "document_base64" => $doc_base64,
                "name" => $file_name,
                "document_id" => $docs_count
            ]);
            $documents[] = $document;
            $docs_count = $docs_count + 1;
        }

        $env_definition = new \DocuSign\eSign\Model\EnvelopeDefinition([
            "documents" => $documents,
            "status" => "sent",
            "enable_wet_sign" => false
        ]);
        $envelope_api->updateDocuments($this->account_id, $env_id, $env_definition);
        return 1;
    }

    public function send2ndApprovalStage($env_id, $cust_num, $cust_name, $branch)
    {
        $br_num = \Erp::getCustBranchNum($cust_num);

        $recipient = $this->getEnvelopeRecipient($env_id, 1); // get 1st recpient to see if coming from jotform (new cust) or coming from fwwebb (existing cust)
        if ($recipient["roleName"] === "Credit Applicant New") {
            $credit_dept_name = "Credit Onboarding";
            $credit_dept_email = $this->new_cust_credit_email;
            $dup_customers = $this->getDupCustomers($env_id);
        } else {
            $credit_dept_name = "Credit Ordering";
            $credit_dept_email = $this->existing_cust_credit_email;
            $dup_customers = [];
        }

        $email_subject = "Credit App | Cust#: " . $cust_num . ", BR: " . $branch . ", Name: " . $cust_name;

        require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Email.php";
        $is_2nd_approval_stage = true; // hardcode for readability
        \Email::sendApprovalStage($env_id, $credit_dept_email, $credit_dept_name, $email_subject, $dup_customers, $br_num, $cust_num, $cust_name, $is_2nd_approval_stage);
    }

    public function unpausePG($env_id, $cust_num)
    {
        $envelope_api = $this->_getEnvelopeApi();

        $cust_info_display = "PG App | Cust#: " . $cust_num;
        if (strlen($cust_info_display) >= 100) {
            $cust_info_display = substr($cust_info_display, 0, 95) . "...";
        }

        $credit_dept_email_subject = $cust_info_display;
        $credit_dept_email_body = "Please review the customer credit application.<br>";
        $credit_dept_email_body .= "Cust#: " . $cust_num;
        $credit_dept_email_notification = new \DocuSign\eSign\Model\RecipientEmailNotification(["email_subject" => $credit_dept_email_subject, "email_body" => $credit_dept_email_body]);
        $credit_notify = new \DocuSign\eSign\Model\CarbonCopy([
            "email" => $this->new_cust_credit_email,
            "name" => "Credit Dept",
            "recipient_id" => 2,
            "routing_order" => 2,
            "user_id" => $this->new_cust_credit_user_id,
            "email_notification" => $credit_dept_email_notification,
            "role_name" => "Credit Notify"
        ]);

        $recipient = new \DocuSign\eSign\Model\Recipients(["carbon_copies" => [$credit_notify]]);

        $env = new \DocuSign\eSign\Model\EnvelopeDefinition([
            "workflow" => new \DocuSign\eSign\Model\Workflow(["workflow_status" => "in_progress"]),
            "recipients" => $recipient,
            "email_subject" => $cust_info_display,
            "status" => "sent",
            "enable_wet_sign" => false
        ]);

        $env_option = new \DocuSign\eSign\Api\EnvelopesApi\UpdateOptions();
        $env_option->setAdvancedUpdate("true");
        $env_option->setResendEnvelope("true");

        $envelope_api->update($this->account_id, $env_id, $env, $env_option);
    }

    public function getEnvelopeFormData($env_id)
    {
        $url = $this->base_path . "/v2.1/accounts/$this->account_id/envelopes/$env_id/form_data";

        $headers = [
            "Authorization: Bearer $this->access_token",
            "Content-Type: application/json",
        ];

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $resp = curl_exec($curl);

        curl_close($curl);

        $json_decoded = json_decode($resp, true);
        $json_decoded = $json_decoded["formData"] ?? [];

        $ret = [];
        foreach ($json_decoded as $j) {
            $ret[$j["name"]] = $j["value"];
        }

        return $ret;
    }

    /**
     * Gets all recipients from a Docusign envelope.
     * Recipients are sorted by recipientId
     */
    public function getEnvelopeRecipients($env_id)
    {
        return $this->getEnvelopeRecipient($env_id, -1);
    }

    /**
     * Gets a single recipient from a Docusign envelope
     */
    public function getEnvelopeRecipient($env_id, $rec_id)
    {
        $url = $this->base_path . "/v2.1/accounts/$this->account_id/envelopes/$env_id/recipients";

        $headers = [
            "Authorization: Bearer $this->access_token",
            "Content-Type: application/json",
        ];

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $resp = curl_exec($curl);

        curl_close($curl);

        $recipients_full = json_decode($resp, true);
        $recipients = [];
        foreach ($recipients_full as $rec_group) {
            if (is_array($rec_group) && count($rec_group)) {
                foreach ($rec_group as $rec) {
                    if (($rec["recipientId"] == $rec_id || $rec_id == -1) && !empty($rec["customFields"])) {
                        $rec["name"] = $rec["customFields"][0];
                        unset($rec["customFields"]);
                    }
                    $recipients[] = $rec;
                }
            }
        }

        if ($rec_id == -1) {
            usort($recipients, function ($a, $b) {
                return intval($a["recipientId"]) <=> intval($b["recipientId"]);
            });
            return $recipients;
        }

        $recipient = [];
        foreach ($recipients as $rec) {
            if ($rec["recipientId"] == $rec_id) {
                $recipient = $rec;
            }
        }

        return $recipient;
    }

    /**
     * Searchs for envelopes using filters and returns an array of envelope objects containing envelope information
     */
    public function searchEnvelopes($search_filters)
    {
        $envelope_api = $this->_getEnvelopeApi();

        $search_text = $search_filters["searchText"] ?? "";
        $from_date = $search_filters["fromDate"] ?? "2024-06-27";
        $to_date = $search_filters["toDate"] ?? date("Y-m-d");
        $envelope_ids = $search_filters["envelopeIds"] ?? [];

        $search_options = new \DocuSign\eSign\Api\EnvelopesApi\ListStatusChangesOptions();
        $search_options->setFromDate($from_date);
        $search_options->setToDate($to_date);
        $search_options->setSearchText($search_text);

        $search_envs_resp = $envelope_api->listStatusChanges($this->account_id, $search_options);
        $search_envs_resp = json_decode($search_envs_resp, 1);
        $search_envs = $search_envs_resp["envelopes"] ?? [];

        $ret_envs = [];
        foreach ($search_envs as $env) {
            $env_id = $env["envelopeId"];

            if (!empty($envelope_ids) && !in_array($env_id, $envelope_ids)) {
                continue;
            }

            $env_name = $env["emailSubject"];
            $env_status = $env["status"];
            $env_is_open_status = ["created", "delivered", "sent"];
            $env_is_open = in_array($env_status, $env_is_open_status);

            if ($env_is_open) {
                $env_action = $this->_getEnvelopeAction($env_id);
            } else {
                $env_action = ucfirst($env_status) . ".";
            }

            $ret_env = [
                "envelopeId" => $env_id,
                "envelopeName" => $env_name,
                "envelopeAction" => $env_action,
                "envelopeIsOpen" => $env_is_open ? "Yes" : "No",
            ];
            $ret_envs[] = $ret_env;
        }

        $ret = ["envelopes" => $ret_envs];
        return $ret;
    }

    public function getEnvelopeType($env_id)
    {
        $env_type = "";
        $all_recipients = $this->getEnvelopeRecipients($env_id);

        foreach ($all_recipients as $recipient) {
            $roleName = $recipient["roleName"] ?? "";

            switch ($roleName) {
                case "Credit Applicant New":
                case "Credit Applicant Existing":
                case "Credit Approver":
                    $env_type = "credit_app";
                    break;

                case "Credit Applicant 2":
                case "Credit Approver 2":
                    $env_type = "credit_app_2nd_approval_stage";
                    break;

                case "Exempt Uploader":
                    $env_type = "tax_exempt";
                    break;

                case "File Uploader":
                    $env_type = "file_upload";
                    break;

                case "PG Signer":
                    $env_type = "pg_app";
                    break;
            }
        }

        return $env_type;
    }

    private function _getEnvelopeAction($env_id)
    {
        $env_action = "";
        $env_type = $this->getEnvelopeType($env_id);
        $all_recipients = $this->getEnvelopeRecipients($env_id);

        $rec_is_active_status = ["created", "delivered", "sent"];
        foreach ($all_recipients as $recipient) {
            $rec_id = $recipient["recipientId"];
            if (in_array($recipient["status"], $rec_is_active_status)) {
                if ($env_type === "credit_app" || $env_type === "credit_app_2nd_approval_stage" || $env_type === "tax_exempt" || $env_type === "pg_app") {
                    if ($rec_id == 1) {
                        $env_action = "Waiting for customer signing.";
                    } elseif ($rec_id == 2) {
                        $env_action = "Waiting for credit dept review.";
                    } elseif ($rec_id == 3) {
                        $env_action = "Waiting for customer PG signing.";
                    } elseif ($rec_id == 4) {
                        $env_action = "Waiting for credit dept review.";
                    } else {
                        if ($recipient["roleName"] === "Hidden File Uploader") {
                            $env_action = "Completed. Waiting to add files.";
                        }
                    }
                } elseif ($env_type === "file_upload") {
                    if ($rec_id == 1) {
                        $env_action = "Waiting for credit upload.";
                    }
                }
            }
        }

        return $env_action;
    }

    public function getEnvelopeStatus($env_id)
    {
        $envelope_api = $this->_getEnvelopeApi();
        $env = $envelope_api->getEnvelope($this->account_id, $env_id);
        $env_status = $env->getStatus();
        return $env_status;
    }

    private function _getEnvelopeDocuments($env_id)
    {
        $url = $this->base_path . "/v2.1/accounts/$this->account_id/envelopes/$env_id/documents";

        $headers = [
            "Authorization: Bearer $this->access_token",
            "Content-Type: application/json",
        ];

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $resp = curl_exec($curl);

        curl_close($curl);

        $docs = json_decode($resp, true)["envelopeDocuments"];
        return $docs;
    }

    /**
     * Sets account settings, some of which are hidden in DocuSign's UI.
     * Example of $account_settings_info that has been used:
     * ["allow_documents_on_signed_envelopes" => true, "allow_document_visibility" => true, "advanced_correct" => true]
     */
    public function setDocusignSettings($account_settings_info)
    {
        $config = new \DocuSign\eSign\Configuration();
        $config->setHost($this->base_path);
        $config->addDefaultHeader('Authorization', 'Bearer ' . $this->access_token);
        $api_client = new \DocuSign\eSign\Client\ApiClient($config);
        $envelope_api = new \DocuSign\eSign\Api\AccountsApi($api_client);
        $acc = new \DocuSign\eSign\Model\AccountSettingsInformation($account_settings_info);
        return $envelope_api->updateSettings($this->account_id, $acc);
    }

    /**
     * Gets account settings, some of which are hidden in DocuSign's UI
     */
    public function getDocusignAccountSettings()
    {
        $url = $this->base_path . "/v2.1/accounts/$this->account_id/settings";

        $headers = [
            "Authorization: Bearer $this->access_token",
            "Content-Type: application/json",
        ];

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $resp = curl_exec($curl);

        curl_close($curl);

        $resp = json_decode($resp, 1);
        return $resp;
    }

    /**
     * Moves Docusign envelope to a specified folder.
     * FROM DOCUSIGN API DOCUMENTATION :
     * You can use this method to delete envelopes by specifying recyclebin in the folderId parameter. Placing an in-process envelope (envelope status of sent or delivered) in the recycle bin voids the envelope.
     * You can also use this method to delete templates by specifying a template ID instead of an envelope ID in the envelopeIds property and specifying recyclebin in the folderId parameter.
     */
    public function moveEnvToFolder($env_id, $folder_id = "", $from_folder_id = "")
    {
        if ($from_folder_id === "") {
            $from_folder_id = $this->sent_folder_id;
        }

        if ($folder_id === "") {
            $folder_id = $this->inbox_folder_id;
        }

        if ($folder_id == $from_folder_id) {
            return;
        }

        $config = new \DocuSign\eSign\Configuration();
        $config->setHost($this->base_path);
        $config->addDefaultHeader('Authorization', 'Bearer ' . $this->access_token);

        $oauth = new \DocuSign\eSign\Client\Auth\OAuth();
        if (!\Globals::isProd()) {
            $oauth->setOAuthBasePath("account-d.docusign.com");
        }

        $api_client = new \DocuSign\eSign\Client\ApiClient($config, $oauth);
        $folders_api = new \DocuSign\eSign\Api\FoldersApi($api_client);
        $folder_request_params = [
            "envelope_ids" => [$env_id],
            "from_folder_id" => $from_folder_id
        ];
        $folders_request = new \DocuSign\eSign\Model\FoldersRequest($folder_request_params);
        $folders_api->moveEnvelopes($this->account_id, $folder_id, $folders_request);
    }

    public function getDupCustomers($env_id)
    {
        $sql = "SELECT dup_customers FROM cn_docusign_xref WHERE envelope_id = ?:env_id";
        $dup_customers = dbi_query($sql, ["env_id" => $env_id]);
        if (count($dup_customers) && isset($dup_customers[0]["dup_customers"])) {
            return json_decode($dup_customers[0]["dup_customers"], true);
        } else {
            return [];
        }
    }

    public function getCustomerNumber($env_id)
    {
        $cust_num = $this->popCustomerNumber($env_id, true);
        return $cust_num;
    }

    /**
     * Returns custormer_number and deletes it from xref table
     */
    public function popCustomerNumber($env_id, $dont_delete = false)
    {
        $sql_select_cn = "SELECT customer_number FROM cn_docusign_xref WHERE envelope_id = ?:env_id";
        $customer_number = dbi_query($sql_select_cn, ["env_id" => $env_id]);
        if (!$dont_delete) {
            dbi_query("DELETE FROM cn_docusign_xref WHERE envelope_id = ?:env_id", ["env_id" => $env_id]);
        }
        if (count($customer_number) && isset($customer_number[0]["customer_number"])) {
            return $customer_number[0]["customer_number"];
        } else {
            return -1;
        }
    }

    private function _getTabs($type, $recipient_id, $pre)
    {
        $path = $_SERVER["DOCUMENT_ROOT"] . "/templates/";
        $tabs_json = "";

        switch ($type) {
            case "cust_credit_app_new":
            case "cust_credit_app_existing":
                switch ($recipient_id) {
                    case 1:
                        $path .= "tabs_for_customer.json";
                        break;
                    case 2:
                        $path .= "tabs_for_credit.json";
                        break;
                }
                break;

            case "tax_exempt":
                $path .= "tabs_for_upload_tax_exempt.json";
                break;

            case "file_upload":
                $path .= "tabs_for_upload_files.json";
                break;

            case "pg_app":
                $path .= "tabs_for_pg.json";
                break;

            case "credit_app_add_pg":
                switch ($recipient_id) {
                    case 3:
                        $path .= "tabs_for_pg.json";
                        break;
                    case 4:
                        $path .= "tabs_for_credit_2nd_approval_stage.json";
                        break;
                }
                break;
        }

        $tabs_json = file_get_contents($path);
        $allTabs = json_decode($tabs_json, true);

        $ret_tabs = [];
        foreach ($allTabs as $tab_group => $tabs) {
            $tab_group_name = strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $tab_group)); // regex to turn keys to snake_case

            $temp_tabs = [];
            foreach ($tabs as $tab) {
                $temp_t = [];
                $found_prefill = false;
                $make_read_only = false;
                $which_radio_i = -1;
                foreach ($tab as $key => $value) {
                    $new_key = strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $key));
                    if ($new_key !== "locale_policy") {
                        $temp_t[$new_key] = $value;
                    }
                    if ($new_key === "tab_label") {
                        $v = "";
                        foreach ($pre as $i_key => $i_value) {
                            if (str_replace(" ", "_", $value) == $i_key) {
                                $v = $i_value;
                                $found_prefill = true;
                                if ($type === "credit_app_add_pg" && $recipient_id == 4 && $i_key == "Customer#") {
                                    $make_read_only = true;
                                }
                            }
                        }
                        if ($value === "Online Account Email" && $recipient_id == 1) {
                            $make_read_only = true;
                        }
                    }
                    if ($tab_group_name === "radio_group_tabs" && $new_key === "group_name") {
                        foreach ($pre as $i_key => $i_value) {
                            if (str_replace(" ", "_", $value) == $i_key) {
                                $i = 0;
                                foreach ($tab["radios"] as $t2) {
                                    if ($t2["value"] == $i_value) {
                                        $which_radio_i = $i;
                                        $found_prefill = true;
                                    }
                                    $i++;
                                }
                            }
                        }
                    }
                }
                if ($found_prefill) {
                    if ($tab_group_name === "radio_group_tabs") {
                        $temp_t["radios"][$which_radio_i]["selected"] = "true";
                    } else {
                        $temp_t["value"] = $v;
                        $temp_t["selected"] = $v;
                    }
                }
                if ($make_read_only) {
                    $temp_t["locked"] = "true";
                }
                // // make text inputs non-required for speedrun testing
                // if (\Globals::isDev() && $tab_group_name == "text_tabs") {
                //     $temp_t["required"] = false;
                // }
                switch ($tab_group_name) {
                    case "text_tabs":
                        $temp_t = new \DocuSign\eSign\Model\Text($temp_t);
                        break;
                    case "sign_here_tabs":
                        $temp_t = new \DocuSign\eSign\Model\SignHere($temp_t);
                        break;
                    case "signer_attachment_tabs":
                        $temp_t = new \DocuSign\eSign\Model\SignerAttachment($temp_t);
                        break;
                    case "date_signed_tabs":
                        $temp_t = new \DocuSign\eSign\Model\DateSigned($temp_t);
                        break;
                    case "checkbox_tabs":
                        $temp_t = new \DocuSign\eSign\Model\Checkbox($temp_t);
                        break;
                    case "list_tabs":
                        $temp_t = new \DocuSign\eSign\Model\ModelList($temp_t);
                        break;
                    case "note_tabs":
                        $temp_t = new \DocuSign\eSign\Model\Note($temp_t);
                        break;
                    case "email_tabs":
                        $temp_t = new \DocuSign\eSign\Model\Email($temp_t);
                        break;
                    case "zip_tabs":
                        $temp_t = new \DocuSign\eSign\Model\Zip($temp_t);
                        break;
                    case "ssn_tabs":
                        $temp_t = new \DocuSign\eSign\Model\Ssn($temp_t);
                        break;
                    case "number_tabs":
                        $temp_t = new \DocuSign\eSign\Model\Number($temp_t);
                        break;
                    case "radio_group_tabs":
                        $temp_t = new \DocuSign\eSign\Model\RadioGroup($temp_t);
                        break;
                    case "tab_groups":
                        $temp_t = new \DocuSign\eSign\Model\TabGroup($temp_t);
                        break;
                    case "approve_tabs":
                        $temp_t = new \DocuSign\eSign\Model\Approve($temp_t);
                        break;
                    case "decline_tabs":
                        $temp_t = new \DocuSign\eSign\Model\Decline($temp_t);
                        break;
                    case "initial_here_tabs":
                        $temp_t = new \DocuSign\eSign\Model\InitialHere($temp_t);
                        break;
                    case "date_tabs":
                        $temp_t = new \DocuSign\eSign\Model\Date($temp_t);
                        break;
                    default:
                        $temp_t = [];
                }

                $temp_tabs[] = $temp_t;
            }
            $ret_tabs[$tab_group_name] = $temp_tabs;
        }
        return $ret_tabs;
    }

    /**
     * Gets auth url which redirects a user to sign into Docusign.
     * After sign in, 'auth_code' param is supplied to the callback url.
     * Validate 'auth_code' by instatiating Docusign_Helper with $auth_code and $access_token is returned if $auth_code is valid
     * @return string
     */
    public function getAuthURI($state)
    {
        $callback_url = \Globals::getOrderingServerUrl() . "/docusign_api.php";

        $config = new \DocuSign\eSign\Configuration();
        $config->setHost($this->base_path);
        $config->addDefaultHeader('Authorization', 'Bearer ' . $this->access_token);

        $oauth = new \DocuSign\eSign\Client\Auth\OAuth();
        if (!\Globals::isProd()) {
            $oauth->setOAuthBasePath("account-d.docusign.com");
        }

        $authURI = new \DocuSign\eSign\Client\ApiClient($config, $oauth);
        $state["toFolderId"] = $this->inbox_folder_id; // this is where the env will end up
        $state = urlencode(http_build_query($state));

        $url = $authURI->getAuthorizationURI($this->integration_id, "signature impersonation", $callback_url, "code", $state);
        return $url;
    }

    /**
     * Fetches user info (including user_id) from DocuSign using the access token.
     *
     * @param string $access_token - The OAuth access token obtained via login
     * @return array - The decoded JSON user info, includes 'sub' (user_id)
     */
    private function _getUserInfo($access_token)
    {
        $url = \Globals::isProd()
            ? "https://account.docusign.com/oauth/userinfo"
            : "https://account-d.docusign.com/oauth/userinfo";

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $access_token",
                "Content-Type: application/json"
            ]
        ]);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($http_code !== 200 || !$response) {
            throw new \Exception("Failed to fetch user info from DocuSign: $error (HTTP $http_code)");
        }

        return json_decode($response, true);
    }

    /**
     * Gets envelope api object to make calls to Docusign's API 
     */
    private function _getEnvelopeApi()
    {
        $config = new \DocuSign\eSign\Configuration();
        $config->setHost($this->base_path);
        $config->addDefaultHeader('Authorization', 'Bearer ' . $this->access_token);
        $api_client = new \DocuSign\eSign\Client\ApiClient($config);
        $envelope_api = new \DocuSign\eSign\Api\EnvelopesApi($api_client);
        return $envelope_api;
    }

    /** 
     * For requiring a DocuSign login
     */
    private function _getAccessTokenLoginRequired($code)
    {
        $config = new \DocuSign\eSign\Configuration();
        $config->setHost($this->base_path);
        $config->addDefaultHeader('Authorization', 'Bearer ' . $this->access_token);

        $oauth = new \DocuSign\eSign\Client\Auth\OAuth();
        if (!\Globals::isProd()) {
            $oauth->setOAuthBasePath("account-d.docusign.com");
        }

        $api_client = new \DocuSign\eSign\Client\ApiClient($config, $oauth);

        $token = $api_client->generateAccessToken($this->integration_id, $this->secret_key, $code);
        $token_obj = $token[0];
        $access_token = $token_obj->getAccessToken();
        $user_info = $this->_getUserInfo($access_token);

        if (in_array($user_info["sub"], $this->credit_shared_access_user_ids)) {
            // Give valid users credit's access (this doesn't happen automatically in the API - known limitation w/ docusign API)
            $access_token = $this->_getAccessToken();
        } else {
            echo "ACCOUNT_NOT_AUTHORIZED";
            exit();
        }

        return $access_token;
    }

    private function _getAccessToken()
    {
        if (\Globals::isProd()) {
            $url = "https://account.docusign.com/oauth/token";
        } else {
            $url = "https://account-d.docusign.com/oauth/token";
        }

        $headers = ["Content-Type: application/x-www-form-urlencoded"];

        $curl = curl_init($url);

        $token = $this->_createJWT();

        $data = <<<DATA
        grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion=$token
        DATA;

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        $resp = curl_exec($curl);

        curl_close($curl);

        $access_token = json_decode($resp, true)["access_token"];
        return $access_token;
    }

    private function _getBasePath()
    {
        if (\Globals::isProd()) {
            $url = "https://account.docusign.com/oauth/userinfo";
        } else {
            $url = "https://account-d.docusign.com/oauth/userinfo";
        }

        $headers = ["Authorization: Bearer $this->access_token"];

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $resp = curl_exec($curl);

        curl_close($curl);

        $base_path = json_decode($resp, true)["accounts"][0]["base_uri"];
        $base_path .= "/restapi";

        return $base_path;
    }

    private function _createJWT()
    {
        // int_key never changes (docuSign provides in "Apps & Keys")
        // header never changes
        // payload changes depending on iat (init date) & exp (expire date)
        $header = json_encode(["alg" => "RS256", "typ" => "JWT"]);
        $header_encoded = $this->_base64url_encode($header);

        if (\Globals::isProd()) {
            $aud = "account.docusign.com";
        } else {
            $aud = "account-d.docusign.com";
        }

        $payload = json_encode([
            "iss" => $this->integration_id,
            "sub" => $this->new_cust_credit_user_id,
            "aud" => $aud,
            "iat" => time(),
            "exp" => time() + 60,
            "scope" => "signature impersonation"
        ]);
        $payload_encoded = $this->_base64url_encode($payload);

        $fp = fopen("/var/www/secure_keys/docusign_rsa.pem", "r");
        $private_key = fread($fp, 8192);
        fclose($fp);

        $rsa_private_key = openssl_get_privatekey($private_key);

        openssl_sign("$header_encoded.$payload_encoded", $signature, $rsa_private_key, 'sha256WithRSAEncryption');
        $signature_encoded = $this->_base64url_encode($signature);

        $jwt = $header_encoded . "." . $payload_encoded . "." . $signature_encoded;
        return $jwt;
    }

    private function _base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
