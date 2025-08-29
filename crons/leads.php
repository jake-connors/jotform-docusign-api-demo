<?php
/*
    - nightly process - cron job runs at 8pm every day
    - send an email to : contact@fwwebb.com

    - Customer goes to page 2 on regi form  ==> PICK SAVE.CUSTOMER is written
    - Customer submits regi form            ==> SQL `cust_reg_log` is inserted

    ** updated to account for this scenario:
        - person goes to 2nd page at 7:59pm
        - at 8:00pm this cron job runs and sends the email list, which includes the person as they have not yet submitted
        - at 8:01pm the person submits the form but they have been put on the list
    - the 'NOT.SUBMITTED' flag is used to set a '1-day' delay for these type of scenarios
*/
$DOCUMENT_ROOT = dirname(__FILE__, 2);
require_once $DOCUMENT_ROOT . "/vendor/autoload.php"; // loads PHPMailer
require_once $DOCUMENT_ROOT . "/classes/Globals.php";
require_once $DOCUMENT_ROOT . "/classes/Erp.php";

use PHPMailer\PHPMailer\PHPMailer;

$save_customer_today = \Erp::getSaveCustomerRecords(false)["records"];
$save_customer_yesterday = \Erp::getSaveCustomerRecords(true)["records"];

$date = date("Y-m-d", strtotime("-2 days"));
$date_time_check = $date . " 19:58:00";
$registered_custs = dbi_query("SELECT * FROM cust_reg_log WHERE date_time >= ?:date_time_check", ["date_time_check" => $date_time_check]);

foreach ($save_customer_today as $cust1) {
    $found_submit = false;
    $record_key = $cust1["key"];
    foreach ($registered_custs as $cust2) {
        if (trim(strtolower($cust1["email"])) === trim(strtolower($cust2["email"]))) {
            // they are a CUSTOMER in our system so delete the erp record
            $found_submit = true;
            \Erp::deleteSaveCustomerRecord($record_key);
        }
    }
    if (!$found_submit) {
        // WRITE to field<9> of SAVE.CUSTOMER to `flag` them as `NOT.SUBMITTED`
        // flagged records are used (see next loop below), after a 1 day delay
        \Erp::writeSaveCustomerRecord($record_key, "1");
    }
}

$new_leads = [];
foreach ($save_customer_yesterday as $cust1) {
    $found_submit = false;
    $record_key = $cust1["key"];
    foreach ($registered_custs as $cust2) {
        if (trim(strtolower($cust1["email"])) === trim(strtolower($cust2["email"]))) {
            // they are a CUSTOMER in our system so delete the erp record
            $found_submit = true;
            \Erp::deleteSaveCustomerRecord($record_key);
        }
    }
    if (!$found_submit) {
        $new_lead_data = [
            "Company_Name" => $cust1["companyName"],
            "First_Name" => $cust1["firstName"],
            "Last_Name" => $cust1["lastName"],
            "Primary_Phone" => $cust1["primaryPhone"],
            "Secondary_Phone" => $cust1["secondaryPhone"],
            "Job_Title" => $cust1["jobTitle"],
            "Purch_Prods" => $cust1["prods"],
            "Email" => $cust1["email"]
        ];
        $new_leads[] = $new_lead_data;
        // DELETE THE RECORD NOW THAT IT'S BEEN ADDED TO THE LIST
        \Erp::deleteSaveCustomerRecord($record_key);
    }
}

$dt = new DateTime("now");
$dt = $dt->format('Y-m-d H:i:s');
$data_to_log = [
    "today_save_customer" => $save_customer_today, 
    "yesterday_save_customer" => $save_customer_yesterday, 
    "registered_custs" => $registered_custs, 
    "new_leads_list" => $new_leads, 
    "date_time" => $dt
];
\Globals::logData("crons/leads", $data_to_log);

if (count($new_leads) > 0) {
    if (!send_email($new_leads)) {
        $data_to_log = [
            "error" => "mail send error in crons/cron_leads.php",
            "new_leads" => $new_leads
        ];
        \Globals::logData("crons/leads", $data_to_log);   
    }
}

// Functions :

function unicode_decode($string) {
    $string = mb_convert_encoding($string, "UTF-8", "ASCII");
    $string = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $string);
    return $string;
}

function send_email($potential_leads) {
    global $DOCUMENT_ROOT;
    $mail = new \PHPMailer\PHPMailer\PHPMailer();
    $mail->CharSet = "utf-8";
    $mail->Encoding = "base64";

    $from = "noreply@fwwebb.com";
    $from_name = "F.W. Webb";

    $to_array = [
        [
            "address" => "ksd@fwwebb.com",
            "name" => "Kevin",
        ],
        [
            "address" => "kayla.johnson@fwwebb.com",
            "name" => "Kayla",
        ],
        [
            "address" => "heather.macklin@fwwebb.com",
            "name" => "Heather",
        ]
    ];

    if (\Globals::isProd()) {
        foreach ($to_array as $to) {
            $mail->addAddress($to["address"], $to["name"]);
        }
        $mail->addBCC("jake.connors@fwwebb.com", "");
    } else {
        $mail->addAddress(\Globals::getDevEmail(), "");
    }

    $subject = "Potential Leads (from fwwebb.com)";

    $body = '<body style="background-color:#EAEAEA;padding:2%;font-family:Helvetica,Arial,Sans Serif;">';
    $body .= "<table role='presentation' border='0' cellspacing='0' cellpadding='0' align='center' dir=''>";
    $body .= '<table style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;background-color:#ffffff;">';
    $body .= '<tr><td style="padding:10px 24px;"><img style="border:none;" width="300" src="cid:webb_logo" /></td></tr>';
    $body .= '<tr><td style="padding:0px 24px 30px 24px;">';
    $body .= "<table role='presentation' border='0' cellspacing='0' cellpadding='0' width='100%' align='center' style='background-color:#333;color:#ffffff;'>";
    $body .= '<tr><td style="padding:28px 36px 36px 36px;border-radius:2px;background-color:#333;color:#ffffff;font-size:16px;font-family:Helvetica,Arial,Sans Serif;width:100%;text-align:center;" align="center">';
    $body .= '<h3>Potential Leads from fwwebb.com!</h3>';
    if (!\Globals::isProd()) {
        $body .= "<p style='font-style: italic'>This is from the dev server!</p>";
    }
    $body .= 'The following are people who completed page 1 of the online customer registration form but did not submit the form.<br><br>';
    $body .= '</td></tr>';
    $body .= "<table role='presentation' border='1px' cellspacing='5' cellpadding='5' width='100%' style='border: 1px solid #ffffff; text-align: center; border-collapse: collapse'>";
    $body .= "<thead>";
    $body .= "<tr>";
    $body .= "<th>Company Name</th>";
    $body .= "<th>First Name</th>";
    $body .= "<th>Last Name</th>";
    $body .= '<th>Primary Phone</th>';
    $body .= '<th>Secondary Phone</th>';
    $body .= "<th>Job Title</th>";
    $body .= "<th>Product Areas</th>";
    $body .= "<th>Email</th>";
    $body .= "</tr>";
    $body .= "</thead>";
    $body .= "<tbody>";
    foreach ($potential_leads as $new_lead) {
        $body .= "<tr>";
        foreach ($new_lead as $value) {
            $body .= "<td>";
            $body .= $value;
            $body .= "</td>";
        }
        $body .= "</tr>";
    }
    $body .= "</tbody>";
    $body .= '</table>';
    $body .= "</table>";
    if (!\Globals::isProd()) {
        $body .= "<p>(Testing ... email would have gone to... ";
        foreach ($to_array as $to) {
            $body .= $to["name"] . " (" . $to["address"] . "), ";
        }
        $body = substr($body, 0, strlen($body) - 2);
        $body .= ")</p>";
    }
    $body .= "</td></tr>";
    $body .= "</table>";
    $body .= "</table>";
    $body .= '</body>';

    $mail->addEmbeddedImage($DOCUMENT_ROOT . "/images/fwwebb_icon_hd.png", "webb_logo");

    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_sign' => true
        )
    );
    $mail->From = $from;
    $mail->FromName = $from_name;
    $mail->addReplyTo($from, $from_name);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->isSMTP();
    $mail->isHTML();
    $mail->Host = 'mail.fwwebb.com';
    $mail->SMTPAuth = false;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $result = $mail->send();
    return $result;
}

?>