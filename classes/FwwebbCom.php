<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Globals.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Erp.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Curl.php";

class FwwebbCom {
    /** 
     * Creates online account on fwwebb.com
    */
    public static function createOnlineAccount($wccn, $email, $f_name, $l_name, $ip_address)
    {
        $url = \Globals::getOrderingServerUrl();
        $url .= ""; // removed/hidden from demo public repo
        $url .= "+JUNK=J&@EMAIL=" . urlencode($email) . "&@WCCN=" . $wccn;
        $url .= "&FNAME=" . urlencode($f_name) . "&LNAME=" . urlencode($l_name);
        $url .= "&WO.NEWUSER.IP=" . urlencode($ip_address);

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
        $url = \Globals::getOrderingServerUrl();
        $url .= ""; // removed/hidden from demo public repo
        $url .= "+JUNK=J&@EMAIL=" . urlencode(strtoupper($email));

        $headers = []; // removed/hidden from demo public repo
        
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $url);

        $result = curl_exec($curl);

        $split_str = "<!-- WK split: WOcustRegister.htm NewCustomerPW.send -->"; // the split comment is sometimes included ?
        $result = str_replace($split_str, "", trim($result));

        curl_close($curl);

        if (trim($result) == "NOTFOUND" || trim($result) == "NO.PW") {
            // NOTFOUND means there is no Online Account w/ this email (no WO.REMAUTH record)
            // NO.PW means there IS an Online Account but the password field is blank
            return trim($result);
        }

        require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Erp.php";
        $decrypted_password = \Erp::W4INTSUBENCRYPT(trim($result), 1);

        return $decrypted_password;
    }

    /**
     * Gets coordinates from zip code
    */
    public static function getCoords($zip)
    {
        $url = "https://www.fwwebb.com/wo-bin/f.wk?WO.API.ZIPCODE+zipcode=" . $zip;

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $url);

        $user_coords_result = curl_exec($curl);
        $user_coords_result = json_decode($user_coords_result, true);

        $result_lat = $result_lng = $result_state = "";
        if (isset($user_coords_result["zipcode"])) {
            $result_lat = floatval($user_coords_result["zipcode"]["latitude"]);
            $result_lng = floatval($user_coords_result["zipcode"]["longitude"]);
            $result_state = $user_coords_result["zipcode"]["state"];
        }

        if ($result_lat === "" || $result_lng === "" || $result_state === "") {
            // fwwebb endpoint is down sometimes, so fallback to zippopotam.us (public API)
            $zippopotam_result = self::_getCoordsFromZippopotam($zip);

            if (isset($zippopotam_result["latitude"])) {
                $result_lat = $zippopotam_result["latitude"];
            }

            if (isset($zippopotam_result["longitude"])) {
                $result_lng = $zippopotam_result["longitude"];
            }

            if (isset($zippopotam_result["state"])) {
                $result_state = $zippopotam_result["state"];
            }
        }

        $ret = [
            "latitude" => $result_lat,
            "longitude" => $result_lng,
            "state" => $result_state
        ];

        return $ret;
    }

    /**
     * Gets coordinates from zippopotam.us (public API)
    */
    private static function _getCoordsFromZippopotam($zip) {
        $url = "https://api.zippopotam.us/us/" . urlencode($zip);

        $rawResp = \Curl::doGet($url);

        $respData = json_decode($rawResp, true);

        $lat = $lng = $state = "";
        if (isset($respData["places"][0])) {
            $place = $respData["places"][0];
            $lat = $place["latitude"] ?? "";
            $lng = $place["longitude"] ?? "";
            $state = $place["state abbreviation"] ?? "";
        }

        return ["latitude" => $lat, "longitude" => $lng, "state" => $state];
    }
}
?>
