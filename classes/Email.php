<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Globals.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;

class Email
{
    /** 
     * Sends an email. 
     * In test server, "To" Address will use Globals's developer email.
     * Logs if PHPMailer fails to send.
     */
    private static function _sendEmail($to, $to_display, $subject, $add_body, $button_url, $button_text, $ccArray, $bccArray, $error_log_message)
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer();
        $mail->CharSet = "utf-8";
        $mail->Encoding = "base64";

        $from = "noreply@fwwebb.com";
        $from_display = "F.W. Webb";

        // Email header
        $body = '<body style="font-family: Helvetica, Arial, Sans Serif; margin: 0; padding: 0; background-color: #ffffff;">';
        $body .= '<table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff;">';
        $body .= '<tr><td align="center" style="padding: 10px; font-size: 18px; color: #E10600;font-weight: bold;text-decoration: none;">';
        $body .= '<img src="cid:webb_logo" alt="F.W. Webb" style="max-width: 300px; height: auto;">';
        $body .= '</td></tr>';
        $body .= '</table>';

        // Email body
        $body .= '<table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; color: #000000; padding: 20px; margin-left: auto;
    margin-right: auto;">';
        $body .= '<tr><td style="text-align: left; font-size: 16px; line-height: 1.5;">';
        $body .= $add_body;
        $body .= '</td></tr>';
        $body .= '</table>';


        // Optional button
        if (!empty($button_url) && !empty($button_text)) {
            $body .= '<table width="100%" border="0" cellspacing="0" cellpadding="0" style="text-align: left; padding-left: 20px;">';
            $body .= '<tr><td>';
            $body .= '<a href="' . $button_url . '" style="color: #E10600; font-weight: bold; text-decoration: none; cursor: pointer;
    ">' . $button_text . '</a>';
            $body .= '</td></tr>';
            $body .= '</table>';
            if ($button_text == "Sign In") {
                $body .= '<table width="100%" border="0" cellspacing="0" cellpadding="0" style="text-align: left; padding-left: 20px;">';
                $body .= '<tr><td>';
                $body .= "<br>Thank you for choosing F.W. Webb!";
                $body .= '</td></tr>';
                $body .= '</table>';
            }
            if ($button_text == "Review Document") {
                $body .= '<table width="100%" border="0" cellspacing="0" cellpadding="0" style="text-align: left; padding-left: 20px;">';
                $body .= '<tr><td>';
                $body .= "<br>If you have any questions or need further assistance, feel free to contact us.";
                $body .= "<br><br>Best regards,<br>F.W. Webb Company";
                $body .= '</td></tr>';
                $body .= '</table>';
            }
            if ($button_text == "Sign Now") {
                $body .= '<table width="100%" border="0" cellspacing="0" cellpadding="0" style="text-align: left; padding-left: 20px;">';
                $body .= '<tr><td>';
                $body .= "<br>If you would prefer to maintain a COD account, no further action is required.";
                $body .= "<br><br>We appreciate your business and look forward to serving you.<br><br>Best regards,<br>F.W. Webb Company Credit Department";
                $body .= '</td></tr>';
                $body .= '</table>';
            }
        }

        if (!\Globals::isProd()) {
            $body .= '<table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; padding: 20px;">';
            $body .= '<tr><td>';
            $body .= "<br><b>TESTING - </b>Emails would have gone to:";
            if (is_array($to)) {
                $body .= "<br>TO: ";
                foreach ($to as $t) {
                    $body .= $t . ", ";
                }
                $body = substr($body, 0, strlen($body) - 2);
            } else {
                $body .= "<br>TO: " . $to;
            }
            if (count($ccArray) > 0) {
                $body .= "<br>CC: ";
                foreach ($ccArray as $cc) {
                    $body .= $cc . ", ";
                }
                $body = substr($body, 0, strlen($body) - 2);
            }
            if (count($bccArray) > 0) {
                $body .= "<br>BCC: ";
                foreach ($bccArray as $bcc) {
                    $body .= $bcc . ", ";
                }
                $body = substr($body, 0, strlen($body) - 2);
            }
            $body .= ")";
            $body .= '</td></tr>';
            $body .= '</table>';
        }

        // Footer
        $body .= '<table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; padding: 20px;">';
        $body .= '<tr><td align="center" style="font-size: 18px; color: #E10600;font-weight: bold;text-decoration: none;">';
        $body .= '<a href="https://www.fwwebb.com/?utm_source=registration&utm_medium=email&utm_campaign=customer%20registration&utm_content=fwwebb.com" style="color: #E10600; text-decoration: none;">Home |</a>';
        $body .= '<a href="https://www.fwwebb.com/locations/" style="color: #E10600; text-decoration: none; margin-right: 7px; margin-left: 7px;">Locations</a>';
        $body .= '<a href="https://www.fwwebb.com/help/" style="text-decoration: none; color: #E10600;">| Support</a>';
        $body .= '</td></tr>';
        $body .= '</table>';

        $body .= '<table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #000000; padding: 20px;">';
        $body .= '<tr><td align="center" style="font-size: 18px; color: #ffffff;font-weight: bold;text-decoration: none;">';
        $body .= '<a href="https://www.fwwebb.com/?utm_source=registration&utm_medium=email&utm_campaign=customer%20registration&utm_content=fwwebb.com" style="color: #ffffff; text-decoration: none;">www.fwwebb.com</a>';
        $body .= '</td></tr>';
        $body .= '</table>';

        $body .= '</body>';

        $mail->addEmbeddedImage($_SERVER["DOCUMENT_ROOT"] . "/images/fwwebb_icon_hd.png", "webb_logo");
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_sign' => true
            )
        );

        if (!\Globals::isProd()) {
            $to = \Globals::getDevEmail();
        }

        $mail->From = $from;
        $mail->FromName = $from_display;

        if (is_array($to)) {
            foreach ($to as $t) {
                $mail->addAddress($t, $t);
            }
        } else {
            if ($to_display == "") {
                $to_display = $to;
            }
            $mail->addAddress($to, $to_display);
        }

        if (\Globals::isProd()) {
            foreach ($ccArray as $cc) {
                $mail->addCC($cc);
            }
            foreach ($bccArray as $bcc) {
                $mail->addBCC($bcc);
            }
        }

        $mail->addReplyTo($from, $from_display);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isSMTP();
        $mail->isHTML();
        $mail->Host = 'mail.fwwebb.com';
        $mail->SMTPAuth = false;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->SMTPAutoTLS = false;

        if (!$mail->send()) {
            $sql_log = "INSERT INTO log (event, data) VALUES (?:event, ?:data)";
            $params_log = [
                "event" => "mail send error",
                "data" => $error_log_message
            ];
            dbi_query($sql_log, $params_log);
        }
    }

    public static function sendErrorEmail($subject, $data)
    {
        if (gettype($data) !== "string") {
            $data = json_encode($data);
        }
        $subject = "Error on: " . $subject;
        $body = "There has been some error in the system.<br><br>Data:<br>" . $data;
        $to = $to_name = \Globals::getDevEmail();
        $error_log_message = json_encode(["description" => "this is an email to indicate an error", "data" => $data]);
        self::_sendEmail($to, $to_name, $subject, $body, "", "", [], [], $error_log_message);
    }

    public static function sendFinishLater($to, $to_name, $env_id, $recipient_id, $customer_num, $br_num, $is_session_timeout, $is_file_upload)
    {
        $subject = "Action Required: Complete Your Credit Application";
        if ($is_file_upload) {
            $subject = "Action Required: DocuSign File Upload";
        }
        if ($is_session_timeout) {
            $subject .= " - Session Timeout";
        } else {
            $subject .= " - Finish Later";
        }
        $body = "";
        if ($to != $to_name) {
            $body .= "<b>Customer #: </b>" . $customer_num . "<br><br>";
            $body .= 'Dear ' . $to_name . ',<br><br>';
        }
        if ($is_file_upload) {
            $body .= "Click the link below to return to your file upload.";
        } else {
            $body .= "Click the link below to return to your F.W. Webb credit application:";
        }
        $url = \Globals::getOrderingServerUrl() . "/docusign_api.php?mode=redirectReturn&session_id=" . $env_id . "&recipient_id=" . $recipient_id . "&event=finish_later";
        $button_text = "Review Document";
        $ccArray = [];
        $bccArray = ["br" . $br_num . "@fwwebb.com"];
        $error_log_message = json_encode(["to" => $to, "env_id" => $env_id]);
        self::_sendEmail($to, $to_name, $subject, $body, $url, $button_text, $ccArray, $bccArray, $error_log_message);
    }

    public static function sendAccountCreated($to, $to_name, $cust_num, $password, $branch_name, $branch_phone, $is_applying_for_credit)
    {
        $subject = "Welcome to F.W. Webb – Your Account is Ready!";
        $body = 'Dear ' . $to_name . ',<br><br>';
        $body .= "We’re pleased to inform you that your account on fwwebb.com has been created!";
        if ($is_applying_for_credit) {
            $body .= "<br>Your credit application is in process.";
        }
        $body .= "<br><br><b>Your Customer Number: </b>" . $cust_num . "<br>";
        $body .= "<b>Your Assigned Store: </b>" . $branch_name . "<br><b>Store Phone Number: </b>" . $branch_phone . "<br><br>";
        $body .= "<b>Your Login Information:</b><br><ul><li><b>Email: </b><a href=" . $to . "text-decoration: none;>" . $to . "</a></li><li><b>\tTemporary Password: </b>" . $password . "</li></ul>";
        $body .= 'Start shopping now:';
        $fwwebb_url = \Globals::getOrderingServerUrl() . "/wobf/login.gen";
        $button_text = "Sign In";
        $ccArray = [];
        $bccArray = []; // removed/hidden from demo public repo
        $error_log_message = json_encode(["to" => $to, "cust_num" => $cust_num]);
        self::_sendEmail($to, $to_name, $subject, $body, $fwwebb_url, $button_text, $ccArray, $bccArray, $error_log_message);
    }

    public static function sendAccountDenied($to, $to_name, $cust_num, $decline_reason, $br_addr1, $br_addr2, $br_phone, $br_manager_emails)
    {
        $subject = "Credit Application Status Update: Denied";
        $body = 'Dear ' . $to_name . ',<br><br>';
        $body .= "We regret to inform you that your F.W. Webb credit application has been denied.<br><br>";
        if ($decline_reason != "") {
            $body .= "<b>Reason for decline: </b>" . $decline_reason . "<br><br>";
        }
        $body .= "You are still welcome to make COD purchases.<br><br> If you have any questions or need further assistance, please contact us.<br>";
        $body .= "<br><br><b>Customer Number: </b>" . $cust_num . "<br>";
        $body .= "<b>Your Assigned Store: </b>" . $br_addr1 . " " . $br_addr2 . "<br><b>Store Phone Number: </b>" . $br_phone . "<br><br>";
        $fwwebb_url = \Globals::getOrderingServerUrl() . "/wobf/login.gen";
        $button_text = "Sign In";
        $ccArray = [];
        $bccArray = []; // removed/hidden from demo public repo
        foreach ($br_manager_emails as $email_addr) {
            $bccArray[] = $email_addr;
        }
        $error_log_message = json_encode(["to" => $to, "cust_num" => $cust_num]);
        self::_sendEmail($to, $to_name, $subject, $body, $fwwebb_url, $button_text, $ccArray, $bccArray, $error_log_message);
    }

    public static function sendAccountApproved($to, $to_name, $branch_name, $branch_phone, $credit_limit, $cust_num, $using_einvoice_email, $using_einvoice_estatement, $br_addr1, $br_addr2)
    {
        $subject = "Credit Application Status Update: Approved";
        $body = 'Dear ' . $to_name . ',<br><br>';
        $body .= "We’re pleased to inform you that your F.W. Webb credit application has been approved! You are now eligible for credit terms.<br><br>";
        $body .= "<b>Credit Limit: </b>$" . $credit_limit . "<br><b>Customer Number: </b>" . $cust_num . "<br><br><b>Your Assigned Store: </b>" . $br_addr1 . " " . $br_addr2 . "<br><b>Store Phone Number: </b>" . $branch_phone . "<br><br>";
        if ($using_einvoice_email && $using_einvoice_estatement) {
            $body .= "<br><b>Note:</b> You will receive e-invoices and e-statements via email";
        } else if ($using_einvoice_email) {
            $body .= "<br><b>Note:</b> You will receive e-invoices via email";
        } else if ($using_einvoice_estatement) {
            $body .= "<br><b>Note:</b> You will receive e-statements via email";
        }
        $body .= 'Start shopping now:';
        $ccArray = [];
        $bccArray = []; // removed/hidden from demo public repo
        $fwwebb_url = \Globals::getOrderingServerUrl() . "/wobf/login.gen";
        $button_text = "Sign In";
        $error_log_message = json_encode(["to" => $to, "branch_name" => $branch_name]);
        self::_sendEmail($to, $to_name, $subject, $body, $fwwebb_url, $button_text, $ccArray, $bccArray, $error_log_message);
    }

    public static function sendBranchAccountCreated($branch_email, $branch_and_name, $cust_num, $cust_name, $first, $last, $primary_phone, $secondary_phone, $email, $is_applying_for_credit, $webb_rewards, $dup_customers, $account_type, $products)
    {
        $subject = "Action Needed: New COD Customer Created";
        $body = 'Dear ' . $branch_and_name . ',<br><br>';
        $body .= "A new COD customer has been created in your branch:<br><br><b>Customer #: </b>" . $cust_num . "<br><b>Customer Name: </b>" . $cust_name . "<br><b>Name: </b>" . $first . " " . $last . "<br><b>Primary Phone: </b>" . $primary_phone . "<br><b>Secondary Phone: </b>" . $secondary_phone . "<br><b>Email: </b>" . $email . "<br><b>Assigned Price Class: </b> 004";
        $body .= "<br><br><h4>Notes:</h4><ul>";

        if ($products != "") {
            $body .= "<li>Purchases products for: <b>" . $products . "</b></li>";
        }
        if ($webb_rewards) {
            $body .= "<li>Interest in Webb Rewards: <b>Yes</b></li>";
        }
        $body .= "<li>Registered account type: <b>" . strtolower($account_type) . "</b></li>";
        if ($is_applying_for_credit) {
            $body .= "<li>Customer is applying for credit terms. You will be notified if they are approved for credit in your branch.</li>";
        }
        $body .= "</ul><br>";
        if (count($dup_customers) > 0) {
            $body .= "<br><b>Duplicate Account Information:</b><ul>";
            $count = 1;
            foreach ($dup_customers as $dup) {
                $body .= "<li><b>Match (" . $count . ")</b> " . $dup["type"] . " matches with Customer #" . $dup["num"] . " - " . $dup["name"] . "</b></li>";
                $count++;
            }
        }
        $body .= "</ul><br>Please take the necessary steps to review and assign the appropriate salesperson using the M7100 program.";
        $to = $branch_email;
        $to_name = $to;
        $url = $button_text = "";
        $ccArray = [];
        $bccArray = []; // removed/hidden from demo public repo
        $error_log_message = json_encode(["br_email" => $branch_email, "branch_and_name" => $branch_and_name, "cust_num" => $cust_num]);
        self::_sendEmail($to, $to_name, $subject, $body, $url, $button_text, $ccArray, $bccArray, $error_log_message);
    }

    public static function sendBranchAccountApproved($branch_email, $branch_and_name, $cust_num, $cust_name)
    {
        $subject = "Notification: Customer Credit Approval - #" . $cust_num . " - " . $cust_name;
        $body = 'Dear ' . $branch_and_name . ' Team,<br><br>';
        $body .= "A customer in your branch has been approved for credit:<br><br><b>Customer #: </b>" . $cust_num . "<br><b>Customer Name: </b>" . $cust_name . "<br><br>";
        $to = $branch_email;
        $to_name = $to;
        $url = $button_text = "";
        $ccArray = [];
        $bccArray = []; // removed/hidden from demo public repo
        $error_log_message = json_encode(["br_email" => $branch_email, "branch_and_name" => $branch_and_name, "cust_num" => $cust_num]);
        self::_sendEmail($to, $to_name, $subject, $body, $url, $button_text, $ccArray, $bccArray, $error_log_message);
    }

    public static function sendPG($env_id, $to, $to_name)
    {
        $subject = "Action Needed: Complete Your Credit Application for F.W. Webb";
        $body = 'Dear ' . $to_name . ',<br><br>';
        $body .= "Thank you for submitting your Credit Application to F.W. Webb Company. We have completed our initial review and determined that a Personal Guarantee is needed to proceed with your request for an open credit account.";
        $body .= "<br><br>To continue the process, please click the link below to sign our electronic agreement:";
        $rec_id_pg_signer = 3;
        $url = \Globals::getOrderingServerUrl() . "/docusign_api.php?mode=redirectReturn&session_id=" . $env_id . "&recipient_id=" . $rec_id_pg_signer;
        $button_text = "Sign Now";
        $ccArray = [];
        $bccArray = []; // removed/hidden from demo public repo
        $error_log_message = json_encode(["to" => $to, "env_id" => $env_id]);
        self::_sendEmail($to, $to_name, $subject, $body, $url, $button_text, $ccArray, $bccArray, $error_log_message);
    }

    public static function sendApprovalStage($env_id, $credit_dept_email, $credit_dept_name, $email_subject, $dup_customers, $branch, $cust_num, $cust_name, $is_2nd_approval_stage)
    {
        $subject = $email_subject;
        $body = 'Dear ' . $credit_dept_name .  ' Team,<br><br>';
        $body .= "Please review the following customer credit application.";
        if ($is_2nd_approval_stage) {
            $body .= ' The customer has now attached a PG form.<br><br><b>Branch: </b>' . $branch . '<br><b>Customer Number: </b>' . $cust_num . '<br><b>Customer Name: </b>' . $cust_name . '<br>';
        } else {
            $body .= '<br><br><b>Branch: </b>' . $branch . '<br><b>Customer Number: </b>' . $cust_num . '<br><b>Customer Name: </b>' . $cust_name . '<br>';
        }
        $to = $credit_dept_email;
        $to_name = $credit_dept_name;
        $credit_rec_id = $is_2nd_approval_stage ? 4 : 2;
        if (count($dup_customers) > 0) {
            $body .= "<br><b>Duplicate Account Information:</b><ul>";
            $count = 1;
            foreach ($dup_customers as $dup) {
                $body .= "<li><b>Match (" . $count . ")</b> " . $dup["type"] . " matches with Customer #" . $dup["num"] . " - " . $dup["name"] . "</b></li>";
                $count++;
            }
        }
        $url = \Globals::getOrderingServerUrl() . "/docusign_api.php?mode=redirectReturn&session_id=" . $env_id . "&recipient_id=" . $credit_rec_id;
        $button_text = "REVIEW DOCUMENT";
        $ccArray = [];
        $bccArray = []; // removed/hidden from demo public repo
        $error_log_message = json_encode(["env_id" => $env_id, "email_subject" => $email_subject]);
        self::_sendEmail($to, $to_name, $subject, $body, $url, $button_text, $ccArray, $bccArray, $error_log_message);
    }

    public static function sendCompletedPG($env_id, $to)
    {
        $subject = "Completed: Personal Guarantor";
        $to_name = "Credit Onboarding";
        $body = 'Dear ' . $to_name . ',<br><br>';
        $body .= "A Personal Guarantor form has been submitted.";
        $body .= "<br><br>The form was sent out and completed in a form separate from the credit application. The customer has specified their customer number and business name themself.";
        $credit_rec_id = 2;
        $url = \Globals::getOrderingServerUrl() . "/docusign_api.php?mode=redirectReturn&session_id=" . $env_id . "&recipient_id=" . $credit_rec_id;
        $button_text = "REVIEW DOCUMENT";
        $ccArray = [];
        $bccArray = []; // removed/hidden from demo public repo
        $error_log_message = json_encode(["to" => $to, "env_id" => $env_id]);
        self::_sendEmail($to, $to_name, $subject, $body, $url, $button_text, $ccArray, $bccArray, $error_log_message);
    }
}
