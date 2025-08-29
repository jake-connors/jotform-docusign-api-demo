<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/globals.php";

class SPX_Helper {
    /** 
     * Creates online account on spx
    */
    public static function createOnlineAccount($wccn, $email, $f_name, $l_name, $ip_address)
    {
        $url = \Globals::getSpxOrderingServerUrl();
        $url .= ""; // removed/hidden from demo public repo
		$url .= "@EMAIL=" . urlencode($email) . "&@WCCN=" . $wccn; 
        $url .= "&FNAME=" . urlencode($f_name) . "&LNAME=" . urlencode($l_name);
        $url .= "&SPX.NEWUSER.IP=" . urlencode($ip_address); //CHANGED

        $headers = []; // removed/hidden from demo public repo
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_exec($curl);
        curl_close($curl);
    }

    /**
     * Gets the unencrypted password for an online account on fwwebb.com
    */
    public static function getPassword($email)
    {
        $url = \Globals::getSpxOrderingServerUrl();
        $url .= ""; // removed/hidden from demo public repo 
        $url .= "+JUNK=J&@EMAIL=" . urlencode(strtoupper($email));

        $headers = []; // removed/hidden from demo public repo
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $url);
        $result = curl_exec($curl);
        
        curl_close($curl);
        if (trim($result) == "NOTFOUND" || trim($result) == "NO.PW") {
            // NOTFOUND means there is no Online Account w/ this email (no WO.REMAUTH record)
            // NO.PW means there IS an Online Account but the password field is blank
            return trim($result);
        }

        require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/erp_helper.php";
        $decrypted_password = \Erp_Helper::W4INTSUBENCRYPT(trim($result), 1);

        return $decrypted_password;
    }
}
?>