<?php
/**
 * @version    1.0.0
 * @category   PayIntelligent
 * @copyright  Copyright (c) 2013 PayIntelligent GmbH (http://payintelligent.de)
 */
class PaymentProcessor
{
    //Options: Variables needed to create Paymill Lib Components
    private $_libBase;
    private $_privateKey;
    private $_apiUrl;
    
    //Objects: Objects used by the methods
    private $_clientsObject;
    private $_transactionsObject;
    private $_paymentsObject;
    private $_refundsObject;
    private $_logger;               //Only this object can be set using a set function.
    
    //Process Payment relevant
    private $_token;                //Token generated for the Transaction
    private $_authorizedAmount;     //Amount the Token was generated with
    private $_amount;               //Current Amount
    private $_currency;             //Currency (of both amounts)
    private $_payment;              //String contaning the current payment type (either cc or elv)
    private $_name;                 //Customername
    private $_email;                //Customer Email Adress
    private $_description;          
    private $_transactionId;        //Transaction Id generated by the createTransaction function.
        
    //Fast Checkout Variables 
    private $_userId = null;
    private $_clientId = null;
    private $_paymentId = null;
    
   /**
    * Constructor
    * @param String <b>$privateKey</b> Paymill-PrivateKey
    * @param String <b>$apiUrl</b> Paymill-Api Url
    * @param String <b>$libBase</b> Path to the lib Base (Can be null, Default Path will be used)
    * @param array <b>$params</b>( <br />
    *    <b>token</b>,               generated Token <br />
    *    <b>authorizedAmount</b>,    Tokenamount <br />
    *    <b>amount</b>,              Basketamount <br />
    *    <b>currency</b>,            Transaction currency <br />
    *    <b>payment</b>,             The chosen payment (cc | elv) <br />
    *    <b>name</b>,                Customer name <br />
    *    <b>email</b>,               Customer emailaddress <br />
    *    <b>description</b>,         Description for transactions <br />
    * ) <p color='red'><b>(If not set here, the use of setters is required for the class to work)</b></p>
    * @param object $loggingClassInstance Instance of Object implementing a log(String,String) function. If not set, there will be no logging.
    */
    public function __construct($privateKey, $apiUrl, $libBase = null, $params = null, $loggingClassInstance = null)
    {
        $this->setPrivateKey($privateKey);
        $this->setApiUrl($apiUrl);
        $this->setLibBase($libBase);
        $this->_token                   = $params['token'];     
        $this->_authorizedAmount        = $params['authorizedAmount'];
        $this->_amount                  = $params['amount'];   
        $this->_currency                = $params['currency'];   
        $this->_payment                 = $params['payment'];    
        $this->_name                    = $params['name'];      
        $this->_email                   = $params['email'];
        $this->_description             = $params['description'];
        $this->setLogger($loggingClassInstance);
        $this->_initiatePhpWrapperClasses();
    }
    
     /**
     * Creates a Paymill-Client with the given Data
     *
     * @param array $params
     * @return boolean
     */
    final private function _createClient()
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
     * @param array $params
     * @return boolean
     */
    final private function _createPayment()
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
     * Creates a Paymill-Refund with the given Data
     *
     * @param array $params
     * @return boolean
     */
    final private function _createRefund()
    {
        $refund = $this->_refundsObject->create(
                array(
                    'transactionId' => $this->_transactionId,
                    'params' => array(
                        'amount' => $this->_amount
                    )
                )
        );
        return $this->_validateResult($refund, 'Refund');
    }
    
    /**
     * Creates a Paymill-Transaction with the given Data
     *
     * @param array $params
     * @return boolean
     */
    final private function _createTransaction()
    {
        $transaction = $this->_transactionsObject->create(
                array(
                    'amount' => $this->_amount,
                    'currency' => $this->_currency,
                    'description' => $this->_description,
                    'payment' => $this->_paymentId,
                    'client' => $this->_clientId
                )
        );
        $this->_validateResult($transaction, 'Transaction'); 
         
        $this->_transactionId = $transaction['id'];
        return true;
    }
    
    /**
     * Load the PhpWrapper-Classes and creates an instance for each class.
     */
    final private function _initiatePhpWrapperClasses()
    {
        require_once $this->_libBase . 'Paymill/Transactions.php';
        require_once $this->_libBase . 'Paymill/Clients.php';
        require_once $this->_libBase . 'Paymill/Payments.php';
        require_once $this->_libBase . 'Paymill/Refunds.php';
        $this->_clientsObject = new Services_Paymill_Clients( $this->_privateKey, $this->_apiUrl );
        $this->_transactionsObject = new Services_Paymill_Transactions( $this->_privateKey, $this->_apiUrl );
        $this->_paymentsObject = new Services_Paymill_Payments( $this->_privateKey, $this->_apiUrl );
        $this->_refundsObject = new Services_Paymill_Refunds( $this->_privateKey, $this->_apiUrl );
    }
    
    /**
     * Calls the log() function of the logger object if the object has been set.
     *
     * @param string $message
     * @param string $debugInfo
     */
    private function _log($message, $debugInfo = null)
    {
        try{
            if(isset($this->_logger)){
                $this->_logger->log($message, $debugInfo);
            }
        }
        catch(Exception $exception){
            return true;
        }
    }
    
    /**
     * Validates the array passed as an argument to be processPayment() compliant
     * @param mixed $parameter
     * @return boolean
     */
    final private function _validateParameter()
    {
        $validation = true;
        $parameter = array(
            "token"             => $this->_token,
            "authorizedAmount"  => $this->_authorizedAmount,
            "amount"            => $this->_amount,
            "currency"          => $this->_currency,
            "payment"           => $this->_payment,
            "name"              => $this->_name,
            "email"             => $this->_email,
            "description"       => $this->_description);
        
        $arrayMask = array(
            "token"             => 'string',
            "authorizedAmount"  => 'integer',
            "amount"            => 'integer',
            "currency"          => 'string',
            "payment"           => 'string',
            "name"              => 'string',
            "email"             => 'string',
            "description"       => 'string');

        if (is_array($parameter)) {
            foreach ($arrayMask as $mask => $type) {
                if (!array_key_exists($mask, $parameter)) {
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
        } else {
            $validation = false;
        }
        return $validation;
    }
        
    /**
     * Validates the created Paymill-Objects
     *
     * @param array $transaction
     * @param string $type
     * @return boolean
     */
    final private function _validateResult($transaction, $type)
    {
        
        if (isset($transaction['data']['response_code']) && $transaction['data']['response_code'] !== 20000) {
            $this->_log("An Error occured: " . $transaction['data']['response_code'], var_export($transaction, true));
            throw new Exception("Invalid Result Exception: Invalid ResponseCode");
        }

        if (!isset($transaction['id']) && !isset($transaction['data']['id'])) {
            $this->_log("No $type created.", var_export($transaction, true));
            throw new Exception("Invalid Result Exception: Invalid Id");
        } else {
            $this->_log("$type created.", $transaction['id']);
        }

        // check result
        if ($type == 'Transaction') {
            if (is_array($transaction) && array_key_exists('status', $transaction)) {
                if ($transaction['status'] == "closed") {
                    // transaction was successfully issued
                    return true;
                } elseif ($transaction['status'] == "open") {
                    // transaction was issued but status is open for any reason
                    $this->_log("Status is open.", var_export($transaction, true));
                    throw new Exception("Invalid Result Exception: Invalid Orderstate");
                } else {
                    // another error occured
                    $this->_log("Unknown error." . var_export($transaction, true));
                    throw new Exception("Invalid Result Exception: Unknown Error");
                }
            } else {
                // another error occured
                $this->_log("$type could not be issued.", var_export($transaction, true));
                throw new Exception("Invalid Result Exception: $type could not be issued.");
            }
        } else {
            return true;
        }
    }
    
    /**
     * Executes the Payment Process
     * 
     * @return boolean
     */
    final public function processPayment()
    {
        if (!$this->_validateParameter()) {
            return false;
        }
        try {            
            $this->_createClient();
            $this->_createPayment();
            $this->_createTransaction();
                        
            if ($this->_authorizedAmount > $this->_amount) {
                // basketamount is lower than the authorized amount
                $this->_amount = $this->_authorizedAmount - $this->_amount;
                $this->_createRefund();
            } elseif($this->_authorizedAmount < $this->_amount) {
                $this->_amount = $this->_amount - $this->_authorizedAmount;
                $this->_createTransaction();
            }
                        
            return true;
        } catch (Exception $ex) {
            // paymill wrapper threw an exception
            $this->_log("Exception thrown from paymill wrapper.", $ex->getMessage());
            return false;
        }
    }
    
    /******************************************************************************************************************
     ************************************************    Getter    ****************************************************
     *****************************************************************************************************************/
    
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
     * @return String ClientId
     */
    public function getPaymentId()
    {
        return $this->_paymentId;
    }
    
    /******************************************************************************************************************
     ************************************************    Setter    ****************************************************
     *****************************************************************************************************************/
    
    
    /**
     * Sets the clientId
     * @param String $arg
     */
    public function setClientId($arg)
    {
        $this->_clientId = $arg;
    }
    
    /**
     * Sets the paymentId
     * @param String $arg
     */
    public function setPaymentId($arg)
    {
        $this->_paymentId = $arg;
    }
    
    /**
     * Sets the token
     * @param String $arg
     */
    public function setToken($arg)
    {
        $this->_token = $arg;
    }
    
    /**
     * Sets the authorizedAmount
     * @param String $arg
     */
    public function setAuthorizedAmount($arg)
    {
        $this->_authorizedAmount = $arg;
    }
    
    /**
     * Sets the amount
     * @param String $arg
     */
    public function setAmount($arg)
    {
        $this->_amount = $arg;
    }

    /**
     * Sets the currency
     * @param String $arg
     */
    public function setCurrency($arg)
    {
        $this->_currency = $arg;
    }
    
    /**
     * Sets the payment string
     * @param String $arg
     */
    public function setPayment($arg)
    {
        $this->_payment = $arg;
    }
    
    /**
     * Sets the Customer name
     * @param String $arg
     */
    public function setName($arg)
    {
        $this->_name = $arg;
    }
    
    /**
     * Sets the Customer Email Adress
     * @param String $arg
     */
    public function setEmail($arg)
    {
        $this->_email = $arg;
    }
    
    /**
     * Sets the Description
     * @param String $arg
     */
    public function setDescription($arg)
    {
        $this->_description = $arg;
    }
    
    /**
     * Sets the User Id
     * @param String $arg
     */
    public function setUserId($arg)
    {
        $this->_userId = $arg;
    }
    
    /**
     * Sets the Api URL
     * @param String $apiUrl
     */
    public function setApiUrl($apiUrl)
    {
        $this->_apiUrl = $apiUrl;
    }
    
    /**
     * Sets the Path to the libBase
     * @param String $path Path to the Lib base. If not set, the default path is set.
     */
    public function setLibBase($path = null)
    {
        $this->_libBase =  $path == null ? dirname(__FILE__) . "/" : $path;
    }
    
    /**
     * Sets up the Logger Object.
     * <b>The Logger object can be any class implementing a log(String, String) function.</b>
     * @param any $object
     */
    public function setLogger($object)
    {
        $this->_logger = $object;
    }
    
    /**
     * Sets the Paymill-PrivateKey
     * @param string $privateKey
     */
    public function setPrivateKey($privateKey)
    {
        $this->_privateKey = $privateKey;
    }
}