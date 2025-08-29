<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Globals.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Curl.php";

class Erp {
    /**
     * Subroutine to create an FW Webb Customer.
     * Creates CUSTOMER record. 
     * Creates xref records.
     * Returns result, duplicate info, and branch info.
     * @return array
    */
    public static function W4INTSUBCUSTREG($data_in)
    {
        $url = \Globals::getEndpointServerUrl() . "customer-registration/v1/registration";
        $resp = \Curl::doPost($url, $data_in);
        $resp = json_decode($resp, 1);
        return $resp;
    }

	/**
     * Subroutine to create a SPX Customer.
     * Creates CUSTOMER record. 
     * Creates xref records.
     * Returns result, duplicate info, and branch info.
     * @return array
    */
    public static function W4INTSUBCUSTREGSPX($data_in)
    {
        $url = \Globals::getEndpointServerUrl() . "customer-registration/v1/spx-registration";
        $resp = \Curl::doPost($url, $data_in);
        $resp = json_decode($resp, 1);
        return $resp;
    }

    /**
     * Subroutine to upgrade an FW Webb Customer for credit terms.
     * Updates CUSTOMER record. 
     * Returns result and contact info, and branch info.
     * @return array
    */
    public static function W4INTSUBCUSTCREDITAPPROVE($data_in)
    {
        $url = \Globals::getEndpointServerUrl() . "customer-registration/v1/credit";
        $resp = \Curl::doPost($url, $data_in);
        $resp = json_decode($resp, 1);
        return $resp;
    }

    /**
     * Subroutine to encrypt or decrypt data.
     * ARG4 is 0 - ENCRYPT
     * 
     * ARG4 is 1 - DECRYPT
     * @return string
    */
    public static function W4INTSUBENCRYPT($data, $decrypt = 0)
    {
        $url = \Globals::getEndpointServerUrl() . "customer-registration/v1/encryption";
        $data_in = [
            "data" => $data,
            "arg4" => $decrypt,
        ];
        $resp = \Curl::doPost($url, $data_in);
        return $resp;
    }

    /**
     * Gets BRANCH record
     * @return array
    */
    public static function getBranchRecord($br_num)
    {
        $url = \Globals::getEndpointServerUrl() . "erp-files/v1/branch/" . $br_num ."/";
        $resp = \Curl::doGet($url);
        $branch_record = json_decode($resp, 1);
        return $branch_record;
    }

    /**
     * Gets CUSTOMER record
     * @return array
    */
    public static function getCustomerRecord($cust_num)
    {
        $url = \Globals::getEndpointServerUrl() . "erp-files/v1/customer/" . $cust_num ."/";
        $resp = \Curl::doGet($url);
        $customer_record = json_decode($resp, 1);
        return $customer_record;
    }

    /**
     * Gets branch number for a CUSTOMER
     * @return string
    */
    public static function getCustBranchNum($cust_num)
    {
        $customer_record = self::getCustomerRecord($cust_num);
        $branch_num = $customer_record["branch"];
        return $branch_num;
    }

    /**
     * Gets SAVE.CUSTOMER records
     * @return array
    */
    public static function getSaveCustomerRecords($notSubmitted)
    {
        $url = \Globals::getEndpointServerUrl() . "erp-files/v1/save-customer/?filter=F9=";
        if ($notSubmitted) {
            $url .= "1";
        } else {
            $url .= '""';
        }
        $resp = \Curl::doGet($url);
        $save_customer_records = json_decode($resp, 1);
        return $save_customer_records;
    }

    /**
     * Deletes SAVE.CUSTOMER records
     * @return string|bool
    */
    public static function deleteSaveCustomerRecord($key)
    {
        $url = \Globals::getEndpointServerUrl() . "erp-files/v1/save-customer/" . $key;
        $resp = \Curl::doDelete($url);
        return $resp;
    }

    /**
     * Writes the NOT.SUBMITTED flag in SAVE.CUSTOMER records
     * @return string|bool
    */
    public static function writeSaveCustomerRecord($key, $notSubmitted)
    {
        $url = \Globals::getEndpointServerUrl() . "erp-files/v1/save-customer/" . $key . "/";
        $request_data = [
            "email" => $key,
            "notSubmitted" => $notSubmitted ? "1" : "0",
        ];
        $resp = \Curl::doPut($url, $request_data);
        return $resp;
    }

	/**
     * Gets SAVE.CUSTOMER records
     * @return array
    */
    public static function getSPXSaveCustomerRecords($notSubmitted)
    {
        $url = \Globals::getEndpointServerUrl() . "erp-files/v1/spx-save-customer/?filter=F9=";
        if ($notSubmitted) {
            $url .= "1";
        } else {
            $url .= '""';
        }
        $resp = \Curl::doGet($url);
        $save_customer_records = json_decode($resp, 1);
        return $save_customer_records;
    }

    /**
     * Deletes SAVE.CUSTOMER records
     * @return string|bool
    */
    public static function deleteSPXSaveCustomerRecord($key)
    {
        $url = \Globals::getEndpointServerUrl() . "erp-files/v1/spx-save-customer/" . $key;
        $resp = \Curl::doDelete($url);
        return $resp;
    }

    /**
     * Writes the NOT.SUBMITTED flag in SAVE.CUSTOMER records
     * @return string|bool
    */
    public static function writeSPXSaveCustomerRecord($key, $notSubmitted)
    {
        $url = \Globals::getEndpointServerUrl() . "erp-files/v1/spx-save-customer/" . $key . "/";
        $request_data = [
            "email" => $key,
            "notSubmitted" => $notSubmitted ? "1" : "0",
        ];
        $resp = \Curl::doPut($url, $request_data);
        return $resp;
    }

    /**
     * Formats a phone number.
     * @return string
    */
    public static function phoneConvert($var_num, $no_hyphens = false)
    {
        $var_num = trim($var_num);
        $var_num = str_replace("(","",$var_num);
        $var_num = str_replace(")","",$var_num);
        $var_num = str_replace("-","",$var_num);
        $var_num = str_replace(" ","",$var_num);
        $var_num = str_replace(".","",$var_num);
        $var_num = substr($var_num, -10);
        $var_area_code = substr($var_num, 0, -7);
        $var_exchange = substr($var_num, 3, -4);
        $var_extention = substr($var_num, -4);
        $var_return = "{$var_area_code}-{$var_exchange}-{$var_extention}";
        if ($var_return == "--") {
            $var_return = "";
        }
        if ($no_hyphens) {
            $var_return = "{$var_area_code}{$var_exchange}{$var_extention}";
        }
        return $var_return;
    }    
}

?>
