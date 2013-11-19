<?php

/**
 * This class acts as an easy to use gateway for the paymill phph wrapper.
 * @version    1.0.0
 * @category   PayIntelligent
 * @copyright  Copyright (c) 2013 PayIntelligent GmbH (http://payintelligent.de)
 */
class Services_Paymill_PaymentProcessor
{

    //Options: Variables needed to create Paymill Lib Components
    private $_libBase;
    private $_privateKey;
    private $_apiUrl;
    //Objects: Objects used by the methods
    private $_transactionsObject;
    private $_preauthObject;
    private $_clientsObject;
    private $_paymentsObject;
    private $_logger;               //Only this object can be set using a set function.
    //Process Payment relevant
    private $_token;                //Token generated for the Transaction
    private $_amount;               //Current Amount
    private $_preAuthAmount;
    private $_currency;             //Currency (of both amounts)
    private $_name;                 //Customername
    private $_email;                //Customer Email Adress
    private $_description;
    private $_lastResponse;
    private $_transactionId;        //Transaction Id generated by the createTransaction function.
    private $_preauthId;
    //Fast Checkout Variables
    private $_clientId = null;
    private $_paymentId = null;
    //Source
    private $_source;
    private $_errorCode;

    /**
     * Creates an object of the PaymentProcessor class.
     *
     * @param String                            $privateKey
     * @param String                            $apiUrl
     * @param String                            $libBase
     * @param array                             $params
     * @param Services_Paymill_LoggingInterface $loggingClassInstance Instance of Object implementing the Services_Paymill_PaymentProcessorInterface. If not set, there will be no logging.
     */
    public function __construct($privateKey = null, $apiUrl = null, $libBase = null, $params = null, Services_Paymill_LoggingInterface $loggingClassInstance = null)
    {
        $this->setPrivateKey($privateKey);
        $this->setApiUrl($apiUrl);
        $this->setLibBase($libBase);
        $this->_token = $params['token'];
        $this->_amount = $params['amount'];
        $this->_currency = $params['currency'];
        $this->_name = $params['name'];
        $this->_email = $params['email'];
        $this->_description = $params['description'];
        $this->setLogger($loggingClassInstance);
    }

    /**
     * Creates a Paymill-Client with the given Data
     *
     * @internal param array $params
     * @return boolean
*/
    private function _createClient()
    {
        if (isset($this->_clientId)) {
            $this->_log("Client using: " . $this->_clientId);
        } else {
            $client = $this->_clientsObject->create(
                    array(
                        'email' => $this->_email,
                        'description' => $this->_description
                    )
            );

            $this->_validateResult($client, 'Client');

            $this->_clientId = $client['id'];
        }
        return $this->_clientId;
    }

    /**
     * Creates a Paymill-Payment with the given Data
     *
     * @internal param array $params
     * @return boolean
*/
    private function _createPayment()
    {
        if (isset($this->_paymentId)) {
            $this->_log("Payment using: " . $this->_paymentId);
        } else {
            $payment = $this->_paymentsObject->create(
                    array(
                        'token' => $this->_token,
                        'client' => $this->_clientId
                    )
            );

            $this->_validateResult($payment, 'Payment');

            $this->_paymentId = $payment['id'];
        }
        return true;
    }

    /**
     * Creates a Paymill-Transaction with the given Data
     *
     * @internal param array $params
     * @return boolean
*/
    private function _createTransaction()
    {
        $parameter = array(
            'amount' => $this->_amount,
            'currency' => $this->_currency,
            'description' => $this->_description,
            'preauthorization' => $this->_preauthId,
            'source' => $this->_source
        );
        $this->_preauthId != null ? $parameter['preauthorization'] = $this->_preauthId : $parameter['payment'] = $this->_paymentId;
        $transaction = $this->_transactionsObject->create($parameter);
        $this->_validateResult($transaction, 'Transaction');

        $this->_transactionId = $transaction['id'];
        return true;
    }

    /**
     * Creates a Paymill-Transaction with the given Data
     *
     * @internal param array $params
     * @return boolean
*/
    private function _createPreauthorization()
    {
        $preAuth = $this->_preauthObject->create(
                array(
                    'amount' => $this->_preAuthAmount,
                    'currency' => $this->_currency,
                    'description' => $this->_description,
                    'payment' => $this->_paymentId,
                    'client' => $this->_clientId,
                )
        );
        $this->_validateResult($preAuth, 'Preauthorization');
        $this->_preauthId = $preAuth['preauthorization']['id'];
        return true;
    }

    /**
     * Load the PhpWrapper-Classes and creates an instance for each class.
     */
    private function _initiatePhpWrapperClasses()
    {
        require_once $this->_libBase . 'Transactions.php';
        require_once $this->_libBase . 'Preauthorizations.php';
        require_once $this->_libBase . 'Clients.php';
        require_once $this->_libBase . 'Payments.php';

        $this->_transactionsObject = new Services_Paymill_Transactions($this->_privateKey, $this->_apiUrl);
        $this->_preauthObject = new Services_Paymill_Preauthorizations($this->_privateKey, $this->_apiUrl);
        $this->_clientsObject = new Services_Paymill_Clients($this->_privateKey, $this->_apiUrl);
        $this->_paymentsObject = new Services_Paymill_Payments($this->_privateKey, $this->_apiUrl);
    }

    /**
     * Calls the log() function of the logger object if the object has been set.
     *
     * @param string $message
     * @param string $debugInfo
     */
    private function _log($message, $debugInfo = null)
    {
        if (isset($this->_logger)) {
            $this->_logger->log($message, $debugInfo);
        }
    }

    /**
     * Validates the array passed as an argument to be processPayment() compliant
     * @internal param mixed $parameter
     * @return boolean
*/
    private function _validateParameter()
    {
        if ($this->_preAuthAmount == null) {
            $this->_preAuthAmount = $this->_amount;
        }

        $validation = true;
        $parameter = array(
            "token" => $this->_token,
            "amount" => $this->_amount,
            "currency" => $this->_currency,
            "name" => $this->_name,
            "email" => $this->_email,
            "description" => $this->_description);

        $arrayMask = array(
            "token" => 'string',
            "amount" => 'integer',
            "currency" => 'string',
            "name" => 'string',
            "email" => 'string',
            "description" => 'string');

        foreach ($arrayMask as $mask => $type) {
            if (is_null($parameter[$mask])) {
                $validation = false;
                $this->_log("The Parameter $mask is missing.", var_export($parameter, true));
            } else {
                switch ($type) {
                    case 'string':
                        if (!is_string($parameter[$mask])) {
                            $this->_log("The Parameter $mask is not a string.", var_export($parameter, true));
                            $validation = false;
                        }
                        break;
                    case 'integer':
                        if (!is_integer($parameter[$mask])) {
                            $this->_log("The Parameter $mask is not an integer.", var_export($parameter, true));
                            $validation = false;
                        }
                        break;
                }
            }

            if (!$validation) {
                break;
            }
        }
        return $validation;
    }

    /**
     * Validates the created Paymill-Objects
     *
     * @param        $paymillObject
     * @param string $type
     *
     * @throws Exception
     * @internal param array $transaction
     * @return boolean
*/
    private function _validateResult($paymillObject, $type)
    {
        $this->_lastResponse = $paymillObject;
        if (isset($paymillObject['data']['response_code']) && $paymillObject['data']['response_code'] !== 20000) {
            $this->_log("An Error occured: " . $paymillObject['data']['response_code'], var_export($paymillObject, true));
            if (empty($paymillObject['data']['response_code'])) {
                $paymillObject['data']['response_code'] = 0;
            }
            
            throw new Exception("Invalid Result Exception: Invalid ResponseCode", $paymillObject['data']['response_code']);
        }
        
        if (isset($paymillObject['response_code']) && $paymillObject['response_code'] !== 20000) {
            $this->_log("An Error occured: " . $paymillObject['response_code'], var_export($paymillObject, true));
            if (empty($paymillObject['response_code'])) {
                $paymillObject['response_code'] = 0;
            }
            
            throw new Exception("Invalid Result Exception: Invalid ResponseCode", $paymillObject['response_code']);
        }

        if (!isset($paymillObject['id']) && !isset($paymillObject['data']['id'])) {
            $this->_log("No $type created.", var_export($paymillObject, true));
            throw new Exception("Invalid Result Exception: Invalid Id");
        } else {
            $this->_log("$type created.", isset($paymillObject['id']) ? $paymillObject['id'] : $paymillObject['data']['id']);
        }

        // check result
        if ($type == 'Transaction') {
            if (is_array($paymillObject) && array_key_exists('status', $paymillObject)) {
                if ($paymillObject['status'] == "closed") {
                    // transaction was successfully issued
                    return true;
                } elseif ($paymillObject['status'] == "open") {
                    // transaction was issued but status is open for any reason
                    $this->_log("Status is open.", var_export($paymillObject, true));
                    throw new Exception("Invalid Result Exception: Invalid Orderstate");
                } else {
                    // another error occured
                    $this->_log("Unknown error." . var_export($paymillObject, true));
                    throw new Exception("Invalid Result Exception: Unknown Error");
                }
            } else {
                // another error occured
                $this->_log("$type could not be issued.", var_export($paymillObject, true));
                throw new Exception("Invalid Result Exception: $type could not be issued.");
            }
        } else {
            return true;
        }
    }

    /**
     * Executes the Capture Process
     * @param $captureNow
     *
     * @return bool
     */
    private function _processPreAuthCapture($captureNow)
    {
        $this->_createPreauthorization();
        if ($captureNow) {
            $this->_createTransaction();
        }
        return true;
    }

    /**
     * Executes the Payment Process
     *
     * @param bool $captureNow
     * @return boolean
*/
    final public function processPayment($captureNow = true)
    {
        $this->_initiatePhpWrapperClasses();
        if (!$this->_validateParameter()) {
            return false;
        }

        $this->_log('Process payment with following data', print_r($this->toArray(), true));

        try {

            $this->_createClient();
            $this->_log('Client API Response', print_r($this->_clientsObject->getResponse(), true));
            $this->_createPayment();
            $this->_log('Payment API Response', print_r($this->_paymentsObject->getResponse(), true));

            //creates a transaction if there is no difference between the amount
            if ($this->_preAuthAmount === $this->_amount && $captureNow) {
                $this->_createTransaction();
                $this->_log('Transaction API Response', print_r($this->getLastResponse(), true));
            } else {
                $this->_processPreAuthCapture($captureNow);
                $this->_log('Pre-Auth API Response', print_r($this->getLastResponse(), true));
            }

            return true;
        } catch (Exception $ex) {
            $this->_errorCode = $ex->getCode();
            // paymill wrapper threw an exception
            $this->_log("Exception thrown from paymill wrapper. Code: " . $ex->getCode() . " Message: " . $ex->getMessage(), print_r($this->_transactionsObject->getResponse(), true));
            return false;
        }
    }

    /**
     * Executes capture
     * @return bool
     */
    final public function capture()
    {
        $this->_initiatePhpWrapperClasses();
        if (!isset($this->_amount) || !isset($this->_currency) || !isset($this->_preauthId)) {
            return false;
        }
        return $this->_createTransaction();
    }

    /**
     * Returns the objects data
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'apiurl' => $this->_apiUrl,
            'libbase' => $this->_libBase,
            'privatekey' => $this->_privateKey,
            'token' => $this->_token,
            'amount' => $this->_amount,
            'preauthamount' => $this->_preAuthAmount,
            'currency' => $this->_currency,
            'description' => $this->_description,
            'email' => $this->_email,
            'name' => $this->_name,
            'source' => $this->_source
        );
    }

    /*     * **************************************************************************************************************
     * ***********************************************    Getter    **************************************************
     * *************************************************************************************************************** */

    /**
     * <p align = 'center'><b>Can only be called after the call of processPayment(). Otherwise null will be returned</b></p>
     * Returns the ClientId
     * @return String ClientId
     */
    public function getClientId()
    {
        return $this->_clientId;
    }

    /**
     * <p align = 'center'><b>Can only be called after the call of processPayment(). Otherwise null will be returned</b></p>
     * Returns the PaymentId
     * @return String PaymentId
     */
    public function getPaymentId()
    {
        return $this->_paymentId;
    }

    /**
     * <p align = 'center'><b>Can only be called after the call of processPayment(). Otherwise null will be returned</b></p>
     * Returns the TransactionId
     * @return String TransactionId
     */
    public function getTransactionId()
    {
        return $this->_transactionId;
    }

    /**
     * <p align = 'center'><b>Can only be called after the call of processPayment(). Otherwise null will be returned</b></p>
     * Returns the preauthId
     * @return String preauthId
     */
    public function getPreauthId()
    {
        return $this->_preauthId;
    }

    /**
     * <p align = 'center'><b>Can only be called after the call of processPayment(). Otherwise null will be returned</b></p>
     * Returns the last response send by Paymill
     * @return array LastResponse
     */
    public function getLastResponse()
    {
        return $this->_lastResponse;
    }

    /**
     * Returns the error code
     * @return mixed
     */
    public function getErrorCode()
    {
        return $this->_errorCode;
    }

    /*     * **************************************************************************************************************
     * ***********************************************    Setter    **************************************************
     * *************************************************************************************************************** */

    /**
     * Sets the clientId
     * @param String $clientId
     */
    public function setClientId($clientId = null)
    {
        $this->_clientId = $clientId;
    }

    /**
     * Sets the paymentId
     * @param String $paymentId
     */
    public function setPaymentId($paymentId = null)
    {
        $this->_paymentId = $paymentId;
    }

    /**
     * This method sets the token
     * @param String $token
     */
    public function setToken($token = null)
    {
        $this->_token = $token;
    }

    /**
     * This method sets the preAuthAmount
     * @param String $preAuthAmount
     */
    public function setPreAuthAmount($preAuthAmount = null)
    {
        $this->_preAuthAmount = $preAuthAmount;
    }

    /**
     * This method sets the amount
     * @param String $amount
     */
    public function setAmount($amount = null)
    {
        $this->_amount = $amount;
    }

    /**
     * Sets the currency
     * @param String $currency
     */
    public function setCurrency($currency = null)
    {
        $this->_currency = $currency;
    }

    /**
     * Sets the Customer name
     * @param String $name
     */
    public function setName($name = null)
    {
        $this->_name = $name;
    }

    /**
     * Sets the Customer Email Adress
     * @param String $email
     */
    public function setEmail($email = null)
    {
        $this->_email = $email;
    }

    /**
     * Sets the Description
     * @param String $description
     */
    public function setDescription($description = null)
    {
        $this->_description = $description;
    }

    /**
     * Sets the Api URL
     * @param String $apiUrl
     */
    public function setApiUrl($apiUrl = null)
    {
        $this->_apiUrl = $apiUrl;
    }

    /**
     * Sets the Path to the libBase
     * @param String $libBase Path to the Lib base. If not set, the default path is set.
     */
    public function setLibBase($libBase = null)
    {
        $this->_libBase = $libBase == null ? dirname(__FILE__) . DIRECTORY_SEPARATOR : $libBase;
    }

    /**
     * Sets up the Logger Object.
     * <b>The Logger object can be any class implementing the Services_Paymill_PaymentProcessorInterface.</b>
     * @param Services_Paymill_LoggingInterface $logger
*/
    public function setLogger(Services_Paymill_LoggingInterface $logger = null)
    {
        $this->_logger = $logger;
    }

    /**
     * Sets the Paymill-PrivateKey
     * @param string $privateKey
     */
    public function setPrivateKey($privateKey = null)
    {
        $this->_privateKey = $privateKey;
    }

    /**
     * Set the request source
     * (Modulversion_Shopname_Shopversion)
     * @param string $source
     */
    public function setSource($source)
    {
        $this->_source = $source;
    }

    /**
     * Set PreauthorizationID to be captured
     *
     * @param string $preauthId
     */
    public function setPreauthId($preauthId)
    {
        $this->_preauthId = $preauthId;
    }

}