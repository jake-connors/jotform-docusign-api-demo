<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Globals.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/classes/Curl.php";

class Crm
{
    /**
     * Converts a prospect customer in CRM. Goes to trunk/api/accounts/prospectConvert.php
     * Really it deletes the prospect customer and this server is already creating a new customer with the same data.
    */
    public static function convertProspectCustomer($prospectCustomerData) {
        $url = \Globals::getEndpointServerUrl() . "customer-registration/v1/prospect";
        $post_fields = [
            "mode" => "customerRegistrationConvertProspect",
            "cust" => $prospectCustomerData,
        ];
        \Curl::doPost($url, $post_fields);
    }
    
    /**
     * Creates a CONTACT via CRM. First creates a barebones customer in CRM.
    */
    public static function createContact(
        $cust_num,
        $first,
        $last,
        $email,
        $title,
        $primary_phone,
        $secondary_phone,
        $addr_line1,
        $addr_line2,
        $city,
        $state,
        $zip
    ) {
        $url = \Globals::getEndpointServerUrl() . "customer-registration/v1/customer";
        $post_fields = [
            "key" => $cust_num,
            "customer_registration" => true
        ];
        \Curl::doPost($url, $post_fields);

        $url = \Globals::getEndpointServerUrl() . "customer-registration/v1/contact";
        $post_fields = self::_buildContactPostFields(
            "create",
            $cust_num,
            $first,
            $last,
            $email,
            $title,
            $primary_phone,
            $secondary_phone,
            "",
            $addr_line1,
            $addr_line2,
            $city,
            $state,
            $zip
        );
        \Curl::doPost($url, $post_fields);
    }

    /**
     *  Creates a CONTACT via CRM
    */
    public static function createContactFromCreditApp($cust_num, $first, $last, $email, $title, $primary_phone)
    {
        $url = \Globals::getEndpointServerUrl() . "customer-registration/v1/contact";
        $post_fields = self::_buildContactPostFields(
            "createFromCreditApp",
            $cust_num,
            $first,
            $last,
            $email,
            $title,
            $primary_phone
        );
        \Curl::doPost($url, $post_fields);
    }

    /**
     * Updates an existing CONTACT via CRM
    */
    public static function updateContact(
        $cust_num,
        $is_primary,
        $first,
        $last,
        $email,
        $title,
        $primary_phone,
        $secondary_phone = "",
        $fax = "",
        $addr_line1 = "",
        $addr_line2 = "",
        $city = "",
        $state = "",
        $zip = ""
    ) {
        $url = \Globals::getEndpointServerUrl() . "customer-registration/v1/contact";
        $post_fields = self::_buildContactPostFields(
            $is_primary ? "updatePrimary" : "updateNonPrimary",
            $cust_num,
            $first,
            $last,
            $email,
            $title,
            $primary_phone,
            $secondary_phone,
            $fax,
            $addr_line1,
            $addr_line2,
            $city,
            $state,
            $zip
        );
        \Curl::doPost($url, $post_fields);
    }

    /** 
     * Creates a CRM profile
    */
    public static function createProfile($cust_num, $company_products)
    {
        $url = \Globals::getEndpointServerUrl() . "customer-registration/v1/profile";
        $post_fields = self::_buildProfilePostFields("create", $cust_num, $company_products);
        \Curl::doPost($url, $post_fields);
    }

    /** 
     * Updates a CRM profile
    */
    public static function updateProfile($cust_num, $company_products, $bus_url, $suppliers)
    {
        $url = \Globals::getEndpointServerUrl() . "customer-registration/v1/profile";
        $post_fields = self::_buildProfilePostFields("update", $cust_num, $company_products, $bus_url, $suppliers);
        \Curl::doPost($url, $post_fields);
    }

    private static function _buildContactPostFields(
        $type,
        $cust_num,
        $first,
        $last,
        $email,
        $title,
        $primary_phone,
        $secondary_phone = "",
        $fax = "",
        $addr_line1 = "",
        $addr_line2 = "",
        $city = "",
        $state = "",
        $zip = "",
    ) {
        $post_fields = [
            "cusNumber" => $cust_num,
            "contactType" => "contact",
            "firstName" => $first,
            "lastName" => $last,
            "email" => $email,
            "title" => $title,
            "sequence" => "",
            "customer_registration" => true
        ];

        if ($type === "create" || $type === "updatePrimary") {
            $phone = [
                [
                    "phone_type" => "work",
                    "phone" => $primary_phone,
                ],
                [
                    "phone_type" => "mobile",
                    "phone" => $secondary_phone,
                ],
            ];
            $post_fields["phone"] = $phone;
            $post_fields["address1"] = $addr_line1;
            $post_fields["address2"] = $addr_line2;
            $post_fields["city"] = $city;
            $post_fields["state"] = $state;
            $post_fields["zip"] = $zip;
        }

        if ($type === "updatePrimary") {
            $post_fields["update_contact_from_credit_app"] = "primary";
            $post_fields["fax"] = $fax;
        } elseif ($type === "updateNonPrimary") {
            $phone = [
                [
                    "phone_type" => "work",
                    "phone" => $primary_phone,
                ]
            ];
            $post_fields["phone"] = $phone;
            $post_fields["update_contact_from_credit_app"] = "by name";
        }

        if ($type === "createFromCreditApp") {
            $phone = [
                [
                    "phone_type" => "work",
                    "phone" => $primary_phone,
                ]
            ];
            $post_fields["phone"] = $phone;
        }
        
        return $post_fields;
    }

    private static function _buildProfilePostFields(
        $type,
        $cust_num,
        $company_products,
        $bus_url = "",
        $suppliers = []
    ) {
        $post_fields = [
            "mode" => "writeProfile",
            "data" => [
                "cus_id" => $cust_num,
                "cust_website" => $bus_url,
                "lines" => [],
                "suppliers" => $suppliers,
                "opportunity" => 0,
                "pref_ord_method_name" => "",
                "pref_comm_method_name" => "",
                "states_covered" => "",
                "create_by" => "CRM",
                "create_dt" => date("Y-m-d H:i:s"),
                "last_edit_by" => "",
                "last_edit_dt" => "",
            ],
            "customer_registration" => true,
        ];

        if ($type === "create") {
            $company_products_data = [];
            foreach ($company_products as $prod) {
                $temp_prod = ["category_name" => $prod];
                $company_products_data[] = $temp_prod;
            }
            $post_fields["data"]["purch_prods_checkboxes"] = $company_products_data;
        } else if ($type === "update") {
            $suppliers_data = [];
            foreach ($suppliers as $supp) {
                $suppliers_data[] = ["cus_id" => $cust_num, "supp_name" => $supp, "from_credit_app" => true];
            }
            $post_fields["data"]["suppliers"] = $suppliers_data;
        }

        return $post_fields;
    }
}

?>
