<?php

header('content-type:text/html;charset=utf-8');
require_once '../include/DbHandler.php';
require_once '../include/EmailService.php';
require_once '../include/SmsService.php';
require '.././libs/Slim/Slim.php';

header("Content-Type: application/json");
header("Acess-Control-Allow-Origin: *");
header("Acess-Control-Allow-Methods: POST");
header("Acess-Control-Allow-Headers: Acess-Control-Allow-Headers,Content-Type,Acess-Control-Allow-Methods, Authorization");
header('Access-Control-Allow-Credentials', 'true');

// \Stripe\Stripe::setApiKey($stripe['secret_key']);

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

// User id from db - Global Variable
$user_id = NULL;
$session_token = NULL;
/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */
function authenticate(\Slim\Route $route)
{
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();
    // Verifying Authorization Header
    if (isset($headers['Authorization'])) {
        $db = new DbHandler();
        // get the api key
        $api_key = $headers['Authorization'];
        // validating api key

        if (!$db->isValidApiKey($api_key)) {
            $response["status"] = "error";
            $response["message"] = "Access Denied";
            //$response["message"] = "Access Denied. Invalid Api key";
            echoRespnse(401, $response);
            $app->stop();
        } else {
            global $user_id;
            //get user primary key id
            $user_id = $db->getUserId($api_key);

        }
    } else {
        // api key is missing in header
        $response["status"] = "error";
        //$response["message"] = "Api key is misssing";
        $response["message"] = "Access Denied";
        echoRespnse(401, $response);
        $app->stop();
    }
}

function accessToken($user_id)
{
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();
    // Verifying Authorization Header
    if (isset($headers['sessiontoken'])) {
        $db = new DbHandler();
        // get the api key
        $api_key = $headers['sessiontoken'];
        // validating api key
        if (!$db->isValidSessionToken($api_key, $user_id)) {
            $response["status"] = "error";
            $response["message"] = "Token Expired";
            //$response["message"] = "Access Denied. Invalid Api key";
            echoRespnse(401, $response);
            $app->stop();
        }
    } else {
        // api key is missing in header
        $response["status"] = "error";
        //$response["message"] = "Api key is misssing";
        $response["message"] = "sessiontoken key is missing";
        echoRespnse(401, $response);
        $app->stop();
    }
}

/*** Indian Date Time Generation ***/
function getCurrentDateTime()
{
    $datetime = date('Y-m-d H:i:s');
    $given = new DateTime($datetime, new DateTimeZone("UTC"));
    $given->setTimezone(new DateTimeZone("asia/kolkata"));
    $output = $given->format("Y-m-d H:i:s");
    return $output;
}

function authenticatedefault(\Slim\Route $route)
{
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();
    $APPKEY = "b8416f2680eb194d61b33f9909f94b9d";
    // Verifying Authorization Header
    //print_r($headers);exit;
    if (isset($headers['Authorization']) || isset($headers['authorization'])) {
        if (isset($headers['authorization'])) {
            $headers['Authorization'] = $headers['authorization'];
        }

        $db = new DbHandler();

        // get the api key
        $api_key = $headers['Authorization'];
        // validating api key

        if ($api_key != $APPKEY) {
            $response["status"] = "error";
            $response["message"] = "Access Denied";
            echoRespnse(401, $response);
            $app->stop();
        } else {
            global $user_id;
            // get user primary key id
            //$user_id = $db->getUserId($api_key);

        }
    } else {
        // api key is missing in header
        $response["status"] = "error";
        //$response["message"] = "Api key is misssing";
        $response["message"] = "Access Denied";
        echoRespnse(401, $response);
        $app->stop();
    }
}

///////////////////////////////////////
/**
 * User Login
 * url - /login
 * method - POST
 * params - username, password,deviceId,pushId,latitude,longitude,platform , 'authenticatedefault'
 */

$app->post('/generate/sessiontoken', 'authenticatedefault', function () use ($app) {
    $json = $app->request->getBody();
    $data = json_decode($json, true);
    // reading post params
    $user_id = $data['user_id'];

    // check for required params
    // verifyRequiredParams(array('user_id','platform'));

    $response = array();
    $db = new DbHandler();
    $result = $db->generateSessionToken($user_id);
    if ($result['status'] == 1) {
        $response["status"] = 1;
        $response['message'] = "Session Token generated in successfully";
        $response["session_token"] = $result['session_token'];
    } else {
        $response['status'] = 0;
        $response['message'] = 'Session Token generation failed';
        $response["session_token"] = array();
    }

    echoRespnse(200, $response);
});

$app->post('/login', 'authenticatedefault', function () use ($app) {

    $json = $app->request->getBody();
    $data = json_decode($json, true);
    // $result = implode(',',$data);

    $username = $data['username'];
    $password = $data['password'];
    // echo $username;die();
    $response = array();
    $db = new DbHandler();
    $result = $db->userLogin($username, $password);

    if ($result['status'] == 1) {
        $response["status"] = 1;
        $response['message'] = "Logged in successfully";
        $response["userDetails"] = $result['userDetails'];

    } else {
        $response['status'] = 0;
        $response['message'] = 'Incorrect Username or Password';
        $response["userDetails"] = array();
    }

    echoRespnse(200, $response);
});

$app->post('/sendOTP', 'authenticatedefault', function () use ($app) {

    $json = $app->request->getBody();
    $data = json_decode($json, true);

    $mobileNumber = $data['mobileNumber'];
    $response = array();
    $db = new DbHandler();
    $result = $db->sendOTP($mobileNumber);

    $response["status"] = $result['status'];
    
    $response['message'] = $result['message'];
    $response["otpDetails"] = $result['otpDetails'];

    echoRespnse(200, $response);
});

$app->post('/signUp', 'authenticatedefault', function () use ($app) {

    $json = $app->request->getBody();
    $data = json_decode($json, true);

    $response = array();
    $db = new DbHandler();
    $result = $db->saveUser($data);

    $response["status"] = $result['status'];    
    $response['message'] = $result['message'];

    echoRespnse(200, $response);
});

$app->get('/categories', 'authenticatedefault', function () use ($app) {

    $response = array();
    $db = new DbHandler();
    $req = $app->request;
    // $base_url = $req->getUrl()."".$req->getRootUri()."/";
    $base_url = $req->getUrl()."/BillsPaye/uploads/";
    // echo $base_url;die;
    $result = $db->getCategories($base_url);

    $response['status'] = $result['status'];
    $response['category'] = $result['category'];

    echoRespnse(200, $response);

});

$app->post('/subCategories', 'authenticatedefault', function () use ($app) {

    $response = array();
    $json = $app->request->getBody();
    $data = json_decode($json, true);
    $db = new DbHandler();
    $req = $app->request;
    // $base_url = $req->getUrl()."".$req->getRootUri()."/";
    $base_url = $req->getUrl()."/BillsPaye/uploads/";
    // echo $base_url;die;
    $result = $db->getSubCategories($data,$base_url);

    $response['status'] = $result['status'];
    $response['sub_categories'] = $result['sub_categories'];
    $response['categoryOffers'] = $result['categoryOffers'];

    echoRespnse(200, $response);

});

$app->get('/states', 'authenticatedefault', function () use ($app) {

    $response = array();
    $db = new DbHandler();
    $req = $app->request;
    $result = $db->getStates();
    $response['status'] = $result['status'];
    $response['states'] = $result['states'];

    echoRespnse(200, $response);

});

$app->post('/citiesByState', 'authenticatedefault', function () use ($app) {

    $response = array();
    $json = $app->request->getBody();
    $data = json_decode($json, true);
    $db = new DbHandler();
    
    $result = $db->getCitiesByState($data);

    $response['status'] = $result['status'];
    $response['cities'] = $result['cities'];

    echoRespnse(200, $response);

});

$app->post('/most_exciting_offers', 'authenticatedefault', function () use ($app) {

    $response = array();
    $json = $app->request->getBody();
    $data = json_decode($json, true);
    $db = new DbHandler();
    $req = $app->request;
    // $base_url = $req->getUrl()."".$req->getRootUri()."/";
    $base_url = $req->getUrl()."/BillsPaye/uploads/";
    $result = $db->getMostExcitingOffers($data,$base_url);

    $response['status'] = $result['status'];
    $response['offers'] = $result['offers'];

    echoRespnse(200, $response);

});

$app->post('/subCategoryDetails', 'authenticatedefault', function () use ($app) {

    $response = array();
    $json = $app->request->getBody();
    $data = json_decode($json, true);
    $db = new DbHandler();
    $req = $app->request;
    // $base_url = $req->getUrl()."".$req->getRootUri()."/";
    $base_url = $req->getUrl()."/BillsPaye/uploads/";
    $result = $db->getSubCategoryDetailsById($data,$base_url);

    $response['status'] = $result['status'];
    $response['subCategoryDetails'] = $result['subCategoryDetails'];

    echoRespnse(200, $response);

});
//  saveUpdateFavoriteItem
$app->post('/addRemoveWishlist', 'authenticatedefault', function () use ($app) {

    $response = array();
    $json = $app->request->getBody();
    $data = json_decode($json, true);
    $db = new DbHandler();
    $result = $db->addRemoveWishlist($data);

    $response['status'] = $result['status'];
    $response['message'] = $result['message'];

    echoRespnse(200, $response);

});
//  saveUpdateFavoriteItem
$app->post('/userWishlist', 'authenticatedefault', function () use ($app) {

    $response = array();
    $json = $app->request->getBody();
    $data = json_decode($json, true);
    $db = new DbHandler();
    $req = $app->request;
    // $base_url = $req->getUrl()."".$req->getRootUri()."/";
    $base_url = $req->getUrl()."/BillsPaye/uploads/";
    $result = $db->getUserWishlist($data,$base_url);

    $response['status'] = $result['status'];
    $response['wishlist'] = $result['wishlist'];

    echoRespnse(200, $response);

});
//  User Reviews
$app->post('/addReview', 'authenticatedefault', function () use ($app) {

    $response = array();
    $json = $app->request->getBody();
    $data = json_decode($json, true);
    $db = new DbHandler();
    $result = $db->saveUserReview($data);

    $response['status'] = $result['status'];
    $response['message'] = $result['message'];

    echoRespnse(200, $response);

});
//  All Category With Offers
$app->post('/allCategoryWithOffers', 'authenticatedefault', function () use ($app) {

    $response = array();
    $json = $app->request->getBody();
    $data = json_decode($json, true);
    $db = new DbHandler();
    $req = $app->request;
    // $base_url = $req->getUrl()."".$req->getRootUri()."/";
    $base_url = $req->getUrl()."/BillsPaye/uploads/";
    $result = $db->getAllCategoryWithOffers($data,$base_url);

    $response['status'] = $result['status'];
    $response['categoryWithOffers'] = $result['categoryWithOffers'];

    echoRespnse(200, $response);

});
//  All Category With Offers
$app->get('/dynamicMenus', 'authenticatedefault', function () use ($app) {

    $response = array();
    $db = new DbHandler();
    $req = $app->request;
    // $base_url = $req->getUrl()."".$req->getRootUri()."/";
    $base_url = $req->getUrl()."/BillsPaye/uploads/";
    $result = $db->getDynamicMenus($base_url);

    $response['status'] = $result['status'];
    $response['level1menus'] = $result['level1menus'];

    echoRespnse(200, $response);

});
//  Items
$app->post('/items', 'authenticatedefault', function () use ($app) {

    $response = array();
    $db = new DbHandler();

    $req = $app->request;
    $base_url = $req->getUrl()."/AdminApis/images/";

    $json = $app->request->getBody();
    $data = json_decode($json, true);

    $result = $db->getItems($data, $base_url);

    $response['status'] = $result['status'];
    $response['items'] = $result['items'];

    echoRespnse(200, $response);

});
//  Item Details
$app->post('/itemDetails', 'authenticatedefault', function () use ($app) {

    $response = array();
    $db = new DbHandler();

    $req = $app->request;
    $base_url = $req->getUrl()."/AdminApis/images/";

    $json = $app->request->getBody();
    $data = json_decode($json, true);

    $result = $db->getItemDetails($data, $base_url);

    $response['status'] = $result['status'];
    $response['itemDetails'] = $result['itemDetails'];

    echoRespnse(200, $response);

});
//  addToCart
$app->post('/addToCart', 'authenticatedefault', function () use ($app) {

    $response = array();
    $db = new DbHandler();

    $req = $app->request;
    $base_url = $req->getUrl()."/AdminApis/images/";

    $json = $app->request->getBody();
    $data = json_decode($json, true);

    $result = $db->addToCart($data, $base_url);

    $response['status'] = $result['status'];
    $response['message'] = $result['message'];

    echoRespnse(200, $response);

});

//  cartItems
$app->get('/cartItems', 'authenticatedefault', function () use ($app) {

    $response = array();
    $db = new DbHandler();

    $req = $app->request;
    $base_url = $req->getUrl()."/AdminApis/images/";

    $result = $db->getCartItems($base_url);

    $response['status'] = $result['status'];
    $response['cartItems'] = $result['cartItems'];
    $response['price_details'] = $result['price_details'];

    echoRespnse(200, $response);

});
//  CartItemById
$app->post('/CartItemById', 'authenticatedefault', function () use ($app) {

    $response = array();
    $db = new DbHandler();

    $json = $app->request->getBody();
    $data = json_decode($json, true);
    $itemId = $data['item_id'];

    $result = $db->getCartItemById($itemId);

    // $response['status'] = $result['status'];
    // $response['cartItems'] = $result['cartItems'];
    // $response['price_details'] = $result['price_details'];

    echoRespnse(200, $result);

});
//  removeItem
$app->post('/removeItem', 'authenticatedefault', function () use ($app) {

    $response = array();
    $db = new DbHandler();

    $json = $app->request->getBody();
    $data = json_decode($json, true);
    $itemId = $data['item_id'];

    $result = $db->RemoveItemById($itemId);

    $response['status'] = $result['status'];
    $response['message'] = $result['message'];

    echoRespnse(200, $response);

});
///////////////////////////////////////////////////
/**
 * Verifying required params posted or not
 */

function verifyRequiredParams($required_fields)
{
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }
    //print_r($error);
//exit;
    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        //$response["error"] = true;
        $response["status"] = 0;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(200, $response);
        $app->stop();
    }
}

/**
 * Validating email address
 */
function validateEmail($email)
{
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        echoRespnse(200, $response);
        $app->stop();
    }
}

/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response)
{
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}
$app->run();


?>