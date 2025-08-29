<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Globals.php";

class Curl {

    private static function _doCurl($url, $request_data, $http_method)
    {
        $parse_url = parse_url($url);
        $headers_endpoint = substr($parse_url["path"], 1);
        $headers = self::_getHeaders($headers_endpoint, $request_data);
        $curl = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $http_method,
            CURLOPT_POSTFIELDS => json_encode($request_data),
            CURLOPT_HTTPHEADER => $headers
        ];
        curl_setopt_array($curl, $options);
        $resp = curl_exec($curl);
        curl_close($curl);
        return $resp;
    }

    public static function doGet($url)
    {
        $parse_url = parse_url($url);
        $endpoint_path = isset($parse_url["path"]) ? substr($parse_url["path"], 1) : "";
        $query_params_str = isset($parse_url["query"]) ? $parse_url["query"] : "";
        parse_str($query_params_str, $query_params_ary); // puts query params into array. ex) save-customer/?filter=NOT.SUBMITTED=1 will become: Array( [filter] => NOT.SUBMITTED=1 )
        $headers_request_data = $query_params_ary;
        $headers = self::_getHeaders($endpoint_path, $headers_request_data); // pass url without query params and pass query as request data
        $curl = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => $headers
        ];
        curl_setopt_array($curl, $options);
        $resp = curl_exec($curl);
        curl_close($curl);
        return $resp;
    }
    
    public static function doPost($url, $post_fields)
    {
        return self::_doCurl($url, $post_fields, "POST");
    }

    public static function doPut($url, $request_data)
    {
        return self::_doCurl($url, $request_data, "PUT");
    }

    public static function doDelete($url)
    {
        return self::_doCurl($url, [], "DELETE");
    }

    private static function _getHeaders($endpoint, $request_data)
    {
        $headers = ["Content-Type: application/json"];

        $crm_endpoints = [
            "customer-registration/v1/customer",
            "customer-registration/v1/contact",
            "customer-registration/v1/profile",
            "customer-registration/v1/prospect",
        ];

        $hmac_token = $header_name = "";
        $endpoint_project = substr($endpoint, 0, strpos($endpoint, "/"));
        if ($endpoint_project === "customer-registration") {
            $hmac_token = ""; // hidden from demo public repo
            $header_name = "Registration-Signature";
        } else if ($endpoint_project === "erp-files") {
            $hmac_token = ""; // hidden from demo public repo
            $header_name = "Signature-Erp-Files";
        } else {
            return $headers;
        }

        // if it reaches here, it is an endpoint server request
        $sig_url = \Globals::getEndpointServerUrl() . $endpoint;
        $sig_data = self::_prepareData($request_data);
        $sig = self::_computeSignature($sig_url, $sig_data, $hmac_token);
        $headers[] = $header_name . ":" . $sig;
        if (in_array($endpoint, $crm_endpoints) && \Globals::isDev()) {
            $headers[] = "Crm-Dev-Destination-Ad-Login: jake.connors"; // endpoint server will send to crm-dev-jake dev server
        }

        return $headers;
    }

    private static function _computeSignature($url, $data, $hmac_token): string 
    {
        $full = $url . $data;
        // sha256 then base64 the url to the auth token and return the base64-ed string
        return \base64_encode(\hash_hmac("sha256", $full, $hmac_token, true));
    }
    
    private static function _prepareData($data) 
    {
        $json = json_encode($data);
        return str_replace(['"', "'", "\n", " "], '', $json);
    }
}

?>
