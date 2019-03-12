<?php

namespace Guilty\Poweroffice\Services;


use Guilty\Poweroffice\Exceptions\InvalidClientException;
use Guilty\Poweroffice\Exceptions\TooManyRequestsException;
use Guilty\Poweroffice\Exceptions\UnauthorizedException;
use Guilty\Poweroffice\Interfaces\SessionInterface;
use GuzzleHttp\Client;


class PowerofficeService
{
    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * @var \Guilty\Poweroffice\Interfaces\SessionInterface
     */
    protected $session;

    protected $apiBaseUrl;
    protected $authBaseUrl;

    protected $applicationKey;
    protected $clientKey;

    protected $accessTokenPath = "/OAuth/Token";
    protected $testMode;

    /**
     * @param \GuzzleHttp\Client $client
     * @param \Guilty\Poweroffice\Interfaces\SessionInterface $session
     * @param string $applicationKey The application key
     * @param string $clientKey The client key
     * @param bool $testMode Should the service hit the test api or the live api, defaults to test mode (true)
     */
    public function __construct(Client $client, SessionInterface $session, $applicationKey, $clientKey, $testMode = true)
    {
        $this->client = $client;
        $this->session = $session;
        $this->applicationKey = $applicationKey;
        $this->clientKey = $clientKey;

        $testMode
            ? $this->useTestMode()
            : $this->useLiveMode();
    }

    public function isTestMode()
    {
        return $this->testMode;
    }

    protected function useLiveMode()
    {
        $this->testMode = false;
        $this->apiBaseUrl = "https://api.poweroffice.net";
        $this->authBaseUrl = "https://go.poweroffice.net";
    }

    protected function useTestMode()
    {
        $this->testMode = true;
        $this->apiBaseUrl = "https://api-demo.poweroffice.net";
        $this->authBaseUrl = "https://godemo.poweroffice.net";
    }

    protected function getAccessTokenUrl()
    {
        return $this->authBaseUrl . $this->accessTokenPath;
    }

    protected function getApiUrl($path)
    {
        return $this->apiBaseUrl . "/" . trim($path, "/");
    }

    protected function getAuthenticationCredentials()
    {
        return [$this->applicationKey, $this->clientKey];
    }

    protected function getAuthorizationHeader()
    {
        return [
            'Authorization' => 'Bearer ' . $this->session->getAccessToken(),
        ];
    }

    public function refreshIfExpired()
    {
        if ($this->session->hasExpired() && $this->session->canRefresh()) {
            $this->refreshAccessCode();
        }
    }

    /**
     * @throws \Guilty\Poweroffice\Exceptions\InvalidClientException
     */
    public function refreshAccessCode()
    {
        $this->performAuthenticationRequest([
            "grant_type" => "refresh_token",
            "refresh_token" => $this->session->getRefreshToken(),
        ]);
    }

    /**
     * @throws \Guilty\Poweroffice\Exceptions\InvalidClientException
     */
    public function getAccessToken()
    {
        $this->performAuthenticationRequest([
            "grant_type" => "client_credentials",
        ]);
    }

    /**
     * @param array $params
     * @throws \Guilty\Poweroffice\Exceptions\InvalidClientException
     */
    public function performAuthenticationRequest($params)
    {
        $request = $this->client->post($this->getAccessTokenUrl(), [
            'http_errors' => false,
            'Accept' => 'application/json',
            "auth" => $this->getAuthenticationCredentials(),
            "form_params" => $params,
        ]);

        $response = json_decode($request->getBody(), true);

        if ($request->getStatusCode() === 400 && $response["error"] == "invalid_client") {
            throw new InvalidClientException("The client is invalid");
        }

        $this->session->setFromResponse($response);
    }

    /**
     * @param string $method
     * @param string $path
     * @param array|null $params
     * @throws \Guilty\PowerOffice\Exceptions\UnauthorizedException
     * @throws \Guilty\PowerOffice\Exceptions\TooManyRequestsException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @return array
     */
    public function performRequest($method, $path, $params = [])
    {
        $options = array_merge([
            'headers' => $this->getAuthorizationHeader(),
            'Accept' => 'application/json',
            'http_errors' => false,
        ], $params);

        /** @var \GuzzleHttp\Psr7\Response $request */
        $request = $this->client->requestAsync($method, $this->getApiUrl($path), $options);
        $response = json_decode($request->getBody(), true);

        if ($request->getStatusCode() == 401) {
            throw new UnauthorizedException("The request was denied because you were not authorized");
        }

        if ($request->getStatusCode() == 429) {
            throw new TooManyRequestsException("Too many requests");

        }

        return $response;
    }

    // Customer
    //--------------------------------------------------------------------------------------------------
    public function createCustomer($params)
    {
        return $this->performRequest("post", "/Customer", $params);
    }

    public function getCustomers($params = [])
    {
        return $this->performRequest("get", "/Customer", $params);
    }

    public function getCustomer($id)
    {
        return $this->performRequest("get", "/Customer/$id");
    }

    public function deleteCustomer($id)
    {
        return $this->performRequest("delete", "/Customer/$id");
    }

    // Outgoing Invoice
    //--------------------------------------------------------------------------------------------------
    public function createOutgoingInvoice($params)
    {
        return $this->performRequest("post", "/OutgoingInvoice", $params);
    }

    public function getOutgoingInvoices($params = [])
    {
        return $this->performRequest("get", "/OutgoingInvoice/list", $params);
    }

    public function deleteOutgoingInvoice($id)
    {
        return $this->performRequest("delete", "/OutgoingInvoice/{$id}");
    }

    public function getOutgoingInvoice($id)
    {
        return $this->performRequest("get", "/OutgoingInvoice/$id");
    }

    // Product
    //--------------------------------------------------------------------------------------------------
    public function getProducts($params = [])
    {
        return $this->performRequest("get", "/Product", $params);
    }

    public function getProduct($id)
    {
        return $this->performRequest("get", "/Product/$id");
    }

    public function createProduct($params)
    {
        return $this->performRequest("post", "/Product", $params);
    }

    public function deleteProduct($id)
    {
        return $this->performRequest("delete", "/Product/$id");
    }

    // Product Group
    //--------------------------------------------------------------------------------------------------
    public function getProductGroups($params = [])
    {
        return $this->performRequest("get", "/ProductGroup", $params);
    }

    public function getProductGroup($id)
    {
        return $this->performRequest("get", "/ProductGroup/$id");
    }

    public function createProductGroup($params)
    {
        return $this->performRequest("post", "/ProductGroup", $params);
    }

    public function deleteProductGroup($id)
    {
        return $this->performRequest("delete", "/ProductGroup/$id");
    }

    // Contact Group
    //--------------------------------------------------------------------------------------------------
    public function getContactGroups($params = [])
    {
        return $this->performRequest("get", "/ContactGroup", $params);
    }

    public function getContactGroup($id)
    {
        return $this->performRequest("get", "/ContactGroup/$id");
    }

    public function createContactGroup($params)
    {
        return $this->performRequest("post", "/ContactGroup", $params);
    }

    public function deleteContactGroup($id)
    {
        return $this->performRequest("delete", "/ContactGroup/$id");
    }

    // Vat Code
    //--------------------------------------------------------------------------------------------------
    public function getVatCodes($params = [])
    {
        return $this->performRequest("get", "/VatCode", $params);
    }

    public function getVatCode($id)
    {
        return $this->performRequest("get", "/VatCode/{$id}");
    }

    public function getVatCodeChartOfAccount($vatCode, $params = [])
    {
        return $this->performRequest("get", "VatCode/chartofaccount/{$vatCode}", $params);
    }

    // General Account Ledger
    //--------------------------------------------------------------------------------------------------
    public function getGeneralLedgerAccounts($params = [])
    {
        return $this->performRequest("get", "/GeneralLedgerAccount", $params);
    }

    public function createGeneralLedgerAccount($params = [])
    {
        return $this->performRequest("post", "/GeneralLedgerAccount", $params);
    }

    public function getGeneralLedgerAccount($id)
    {
        return $this->performRequest("get", "/GeneralLedgerAccount/{$id}");
    }

    public function deleteGeneralLedgerAccount($id)
    {
        return $this->performRequest("delete", "/GeneralLedgerAccount/{$id}");
    }

    // Bank Transfer
    //--------------------------------------------------------------------------------------------------
    public function getBankTransfers($params = [])
    {
        return $this->performRequest("get", "/Bank/BankTransfer", $params);
    }

    public function getBankTransfer($id)
    {
        return $this->performRequest("get", "/Bank/BankTransfer/{$id}");
    }

    public function createBankTransfer($params = [])
    {
        return $this->performRequest("post", "/Bank/BankTransfer", $params);
    }

    public function deleteBankTransfers($id)
    {
        return $this->performRequest("delete", "/Bank/BankTransfer/{$id}");
    }

    // Department
    //--------------------------------------------------------------------------------------------------
    public function getDepartments($params = [])
    {
        return $this->performRequest("get", "/Department", $params);
    }

    public function getDepartment($id)
    {
        return $this->performRequest("get", "/Department/{$id}");
    }

    public function createDepartment($params = [])
    {
        return $this->performRequest("post", "/Department", $params);
    }

    public function deleteDepartments($id)
    {
        return $this->performRequest("delete", "/Department/{$id}");
    }

    // Project Activity
    //--------------------------------------------------------------------------------------------------
    public function getProjectActivities($params = [])
    {
        return $this->performRequest("get", "/ProjectActivity", $params);
    }

    public function getProjectActivity($id)
    {
        return $this->performRequest("get", "/ProjectActivity/{$id}");
    }

    // Project Team Member
    //--------------------------------------------------------------------------------------------------
    public function getProjectTeamMembers($params = [])
    {
        return $this->performRequest("get", "/ProjectTeamMember", $params);
    }

    public function getProjectTeamMember($id)
    {
        return $this->performRequest("get", "/ProjectTeamMember/{$id}");
    }

    // Location
    //--------------------------------------------------------------------------------------------------
    public function getLocations($params = [])
    {
        return $this->performRequest("get", "/Location", $params);
    }

    public function getLocation($id)
    {
        return $this->performRequest("get", "/Location/{$id}");
    }

    public function createLocation($params = [])
    {
        return $this->performRequest("post", "/Location", $params);
    }

    public function deleteLocations($id)
    {
        return $this->performRequest("delete", "/Location/{$id}");
    }

    // Subledger Number Serie
    //--------------------------------------------------------------------------------------------------
    public function getSubledgerNumberSeries($params = [])
    {
        return $this->performRequest("delete", "/SubledgerNumberSeries", $params);
    }

    public function getSubledgerNumberSerie($id)
    {
        return $this->performRequest("delete", "/SubledgerNumberSeries/{$id}");
    }


    // Misc
    //--------------------------------------------------------------------------------------------------
    public function getInvoiceDeliveryTypes()
    {
        return [
            [
                "name" => "None",
                "value" => 0,
                "description" => "No delivery type - error report value only",
            ],
            [
                "name" => "PdfByEmail",
                "value" => 1,
                "description" => "Invoice will be delivered as email with PDF as attachment",
            ],
            [
                "name" => "Print",
                "value" => 2,
                "description" => "Invoice will be printed",
            ],
            [
                "name" => "EHF",
                "value" => 3,
                "description" => "Invoice will be delivered over EHF",
            ],
            [
                "name" => "AvtaleGiro",
                "value" => 4,
                "description" => "The will be delivered over AvtaleGiro",
            ],
            [
                "name" => "External",
                "value" => 5,
                "description" => "The will be delivered over an external third party integration",
            ],
        ];
    }
}