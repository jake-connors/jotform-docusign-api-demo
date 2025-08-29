<?php
$DOCUMENT_ROOT = dirname(__FILE__, 2);

if (!isset($_SERVER["DOCUMENT_ROOT"]) || $_SERVER["DOCUMENT_ROOT"] == "") {
    $_SERVER["DOCUMENT_ROOT"] = $DOCUMENT_ROOT;
}

if (!isset($_SERVER["SERVER_NAME"]) || $_SERVER["SERVER_NAME"] == "") {
    $_SERVER["SERVER_NAME"] = basename($DOCUMENT_ROOT) . ".fwwebb.com";
}

require_once $DOCUMENT_ROOT . "/includes/sql_init.php";

class Globals
{
    private static $environment = null;

    /**
     * Returns developer's email address.
     * In test, this email acts as the new customer, the branch, the credit dept, etc.
     * @return string
     */
    public static function getDevEmail(): string
    {
        $dev_email = ""; // hidden from demo public repo
        return $dev_email;
    }

    /**
     * Returns the environment name
     * @return string
     */
    private static function getEnvironment(): string
    {
        if (self::$environment === null) {
            self::$environment = trim(file_get_contents("/var/www/environment"));
        }
        return self::$environment;
    }

    /**
     * Checks if it is the production server
     * @return bool
     */
    public static function isProd(): bool
    {
        return self::getEnvironment() === "PRODUCTION";
    }

    /**
     * Checks if it is the qa server
     * @return bool
     */
    public static function isQA(): bool
    {
        return self::getEnvironment() === "QA";
    }

    /**
     * Checks if it is the development server
     * @return bool
     */
    public static function isDev(): bool
    {
        return self::getEnvironment() === "DEVELOPMENT";
    }

    /**
     * Returns ordering server url
     * @return string
     */
    public static function getOrderingServerUrl(): string
    {
        $url = "";
        if (self::isProd()) {
            $url = ""; // removed/hidden from demo public repo
        } elseif (self::isDev() || self::isQA()) {
            $url = ""; // removed/hidden from demo public repo
        }
        return $url;
    }

    // SPX
    public static function getSpxOrderingServerUrl(): string
    {
        $url = "";
        if (self::isProd()) {
            $url = ""; // removed/hidden from demo public repo
        } elseif (self::isDev() || self::isQA()) {
            $url = ""; // removed/hidden from demo public repo
        }
        return $url;
    }

    /**
     * Returns endpoint server url
     * @return string
     */
    public static function getEndpointServerUrl(): string
    {
        $url = "";
        if (self::isProd()) {
            $url = ""; // removed/hidden from demo public repo
        } elseif (self::isQA()) {
            $url = ""; // removed/hidden from demo public repo
        } elseif (self::isDev()) {
            $url = ""; // removed/hidden from demo public repo
        }
        return $url;
    }

    /**
     * Logs data to the database
     * @param string $event
     * @param array $data
     * @param bool $return_last_insert_id
     * @return string|int
     */
    public static function logData($event, $data) {
        $sql_log = "INSERT INTO log (event, data) VALUES (?:event, ?:data)";

        $params = [
            "event" => $event,
            "data" => json_encode($data, JSON_PRETTY_PRINT)
        ];

        dbi_query($sql_log, $params);
    }

    /**
     * Inits sql connection available via sql_init.php
     * @return mysqli
     */
    public static function getSqlConnection(): mysqli
    {
        global $connection;
        return $connection;
    }
}
