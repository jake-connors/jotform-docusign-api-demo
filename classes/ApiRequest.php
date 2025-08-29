<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Globals.php";

class ApiRequest 
{
    public $requestData = [];
    public $requestHeaders = [];

    // future: store in vault system / .env file
    private $hmacToken = ""; // hidden from demo public repo

    public function __construct()
    {
        header("Content-Type: application/json; charset=UTF-8");

        $headers = getallheaders();
        
        $this->requestHeaders = $headers;

        switch ($_SERVER["REQUEST_METHOD"]) {
            case "GET":
                $this->requestData = $this->cleanInputs($_GET);
                break;
            case "POST":
            case "PUT":
            case "DELETE":
                $json = file_get_contents("php://input");
                $this->requestData = json_decode($json, true) ?? [];
                break;
            default:
                break;
        }
    }

    public function check_hmac()
    {
        $incoming_signature = $this->requestHeaders["Registration-Signature"] ?? "";

        $sig_url = "https://" . $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];

        // $sig_data = ($_SERVER["REQUEST_METHOD"] !== "GET")
        //     ? json_encode($this->requestData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        //     : "";
        $sig_data = ($_SERVER["REQUEST_METHOD"] !== "GET") ? json_encode($this->requestData) : "";
        $sig_data = str_replace(['"', "'", "\n", " "], "", $sig_data);

        $calculated_signature = $this->computeSignature($sig_url, $sig_data);

        if ($this->compare($incoming_signature, $calculated_signature) === false) {
            echo json_encode([
                "success" => false, 
                "error" => "Unauthorized", 
                "message" => "Invalid HMAC signature."
            ]);

            http_response_code(401);
            exit();
        }
    }

    private function compare(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    private function computeSignature(string $url, string $data): string
    {
        $full_string = $url . $data;
        return \base64_encode(\hash_hmac("sha256", $full_string, $this->hmacToken, true));
    }

    private function cleanInputs($data)
    {
        $clean_input = [];
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $clean_input[$k] = $this->cleanInputs($v);
            }
        } else {
            $clean_input = trim($data);
        }
        return $clean_input;
    }
}
