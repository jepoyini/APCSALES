<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/home', 'Home::index');
$routes->get('/test', 'TestController::helloWorld');
$routes->get('/listusers', 'UserController::listUsers');

$routes->post('/checkusername', 'UserController::checkUsername');
$routes->post('/forgotpassword', 'UserController::forgotPassword');
$routes->post('/verifytoken', 'UserController::verifyToken');
$routes->post('/resetpassword', 'UserController::resetPassword');

$routes->get('/logout', 'AuthController::logout');
$routes->post('/login', 'AuthController::login'); 
$routes->post('/register', 'AuthController::register');

$routes->get('/sitelinks', 'UserController::SiteLinks');
$routes->get('/sitelinks2', 'UserController::SiteLinks2');

$routes->post('/checkbalance', 'UserController::checkBalance', ['filter' => 'auth']);
$routes->get('/profile', 'AuthController::profile', ['filter' => 'auth']);
$routes->post('/getuser', 'AuthController::getUser', ['filter' => 'auth']);
$routes->post('/updateuser', 'AuthController::updateUser', ['filter' => 'auth']);
$routes->post('/changepass', 'AuthController::changePass', ['filter' => 'auth']);



$routes->post('/purchaseitem', 'PurchaseController::purchaseItem', ['filter' => 'auth']);
$routes->post('/getpurchasedplans', 'PurchaseController::getPurchasedPlans', ['filter' => 'auth']);

$routes->get('/orderhistory/(:num)', 'PurchaseController::getOrderHistory/$1');

$routes->post('/orderhistory', 'PurchaseController::getOrderHistory', ['filter' => 'auth']);

$routes->post('/downlines', 'UserController::getDownlines', ['filter' => 'auth']);

$routes->post('/sendinvite', 'UserController::sendInvite', ['filter' => 'auth']);

$routes->post('/transactions', 'TransactionController::getList', ['filter' => 'auth']);
$routes->post('/rewardlist', 'TransactionController::getRewardList', ['filter' => 'auth']);
$routes->post('/ewalletlist', 'TransactionController::getEwalletList', ['filter' => 'auth']);

$routes->post('/getsitelinks', 'UserController::getSiteLinks', ['filter' => 'auth']);
$routes->post('/getsites', 'UserController::getSites', ['filter' => 'auth']);
$routes->post('/addsite', 'UserController::addSite', ['filter' => 'auth']);
$routes->post('/updatesite', 'UserController::updateSite', ['filter' => 'auth']);
$routes->post('/deletesite', 'UserController::deleteSite', ['filter' => 'auth']);

$routes->post('/updatesitelinks', 'UserController::UpdateSiteLinks', ['filter' => 'auth']);


$routes->post('/getallactivitylogs', 'AdminController::getActivities', ['filter' => 'auth']);
$routes->post('/getallusersdropdown', 'AdminController::getAllusersdropdown', ['filter' => 'auth']);

$routes->post('/getallusers', 'AdminController::getAllUsers', ['filter' => 'auth']);
$routes->post('/getholdingtank', 'AdminController::getHoldingTank', ['filter' => 'auth']);
$routes->post('/holdingtankcount', 'AdminController::getHoldingCankCount', ['filter' => 'auth']);


$routes->post('/adminadjustment', 'AdminController::adminAdjustment', ['filter' => 'auth']);
$routes->post('/adminchangepass', 'AdminController::changePassword', ['filter' => 'auth']);
$routes->post('/adminchangeusername', 'AdminController::changeUsername', ['filter' => 'auth']);

$routes->post('/getcategories', 'AdminController::getCategories', ['filter' => 'auth']);
$routes->post('/addcategory', 'AdminController::addCategory', ['filter' => 'auth']);
$routes->post('/updatecategory', 'AdminController::updateCategory', ['filter' => 'auth']);
$routes->post('/deletecategory', 'AdminController::deleteCategory', ['filter' => 'auth']);


$routes->post('/loginasuser', 'AdminController::LoginasUser', ['filter' => 'auth']);

$routes->post('/deleteuser', 'AdminController::deleteUser', ['filter' => 'auth']);

$routes->post('/transferfund', 'UserController::transferFund', ['filter' => 'auth']);

$routes->post('/getstats', 'AdminController::getStats', ['filter' => 'auth']);


$routes->group('products', ['namespace' => 'App\Controllers'], function($routes) {
    // main listing
    $routes->post('/', 'ProductController::index');

    $routes->post('productsummary', 'ProductController::productsummary');
    $routes->post('warehouseitems', 'ProductController::WarehouseItems');

    

    // create/update/delete
    $routes->post('create', 'ProductController::create');
    $routes->post('(:num)/update', 'ProductController::update/$1');
    $routes->post('(:num)/delete', 'ProductController::delete/$1');
    
    // details
    $routes->post('(:num)/details', 'ProductController::details/$1');

    // images
    $routes->post('(:num)/images/add', 'ProductController::addImage/$1');

    // items (stock units)
    $routes->post('(:num)/items/', 'ProductController::items/$1');
    $routes->post('(:num)/items/add', 'ProductController::addItem/$1');
    $routes->post('(:num)/items/add-batch', 'ProductController::addBatch/$1');
    $routes->post('(:num)/items/update', 'ProductController::updateItem/$1');
    $routes->post('(:num)/items/delete', 'ProductController::deleteItem/$1');

    // events
    $routes->post('(:num)/events/add', 'ProductController::addEvent/$1');

    // QR
    $routes->post('(:num)/qr', 'ProductController::qr/$1');
    $routes->post('scan', 'ProductController::scan');
    $routes->post('lookup', 'ProductController::lookup');


    // CSV export
    $routes->post('export', 'ProductController::export');

    // PRODUCT IMAGES
    $routes->post('(:num)/images/upload', 'ProductController::uploadImages/$1');
    $routes->post('(:num)/images/update', 'ProductController::updateImage/$1');
    $routes->post('(:num)/images/delete', 'ProductController::deleteImage/$1');

    $routes->post('recent', 'ProductController::recent');     

    $routes->post('categories', 'ProductController::categories');      
    $routes->post('categories/create', 'ProductController::categories_create');      

    $routes->post('sizes', 'ProductController::sizes');      
    $routes->post('sizes/create', 'ProductController::sizes_create');      

});


// ================================================================
// User Management Routes
// ================================================================
$routes->group('users', function($routes) {
    $routes->post('/', 'UserController::list');          // list users + summary
    $routes->post('details', 'UserController::details'); // get single user
    $routes->post('save', 'UserController::save');       // insert/update user
    $routes->post('delete', 'UserController::delete');   // delete user
    $routes->post('changepass', 'UserController::changepass');
    $routes->post('activitylogs', 'UserController::activitylogs');   // delete role
});

// ================================================================
// Role Management Routes
// ================================================================
$routes->group('roles', function($routes) {
    $routes->post('list', 'RoleController::list');       // list all roles
    $routes->post('save', 'RoleController::save');       // insert/update role
    $routes->post('delete', 'RoleController::delete');   // delete role

});


    // // Warehouses
    // $routes->post('/warehouses', 'WarehouseController::index');
    // $routes->post('/warehouses/create', 'WarehouseController::create');
    // $routes->post('/warehouses/(:num)/update', 'WarehouseController::update/$1');
    // $routes->post('/warehouses/(:num)/delete', 'WarehouseController::delete/$1');
    // $routes->post('/warehouses/(:num)/details', 'WarehouseController::details/$1');
    // $routes->post('/warehouses/(:num)/add-product', 'WarehouseController::addProduct/$1');
    // $routes->post('/warehouses/(:num)/move-product', 'WarehouseController::moveProduct/$1');
    // $routes->post('/warehouses/(:num)/remove-product', 'WarehouseController::removeProduct/$1');
    // $routes->post('/warehouses/stat', 'WarehouseController::getWarehousesStat');

$routes->group('warehouses', function($routes) {
    $routes->post('/', 'WarehouseController::index');           // list warehouses with pagination/filter
    $routes->post('list', 'WarehouseController::list');      // lightweight list for dropdown
    $routes->post('create', 'WarehouseController::create');     // create new warehouse
    $routes->post('(:num)/update', 'WarehouseController::update/$1'); // update warehouse
    $routes->post('(:num)/delete', 'WarehouseController::delete/$1'); // delete warehouse
    $routes->post('(:num)/details', 'WarehouseController::details/$1'); // details + stats
    $routes->post('(:num)/add-product', 'WarehouseController::addProduct/$1');
    $routes->post('(:num)/move-product', 'WarehouseController::moveProduct/$1');
    $routes->post('/(:num)/remove-product', 'WarehouseController::removeProduct/$1'); 
    $routes->post('stat', 'WarehouseController::getWarehousesStat');   
});

$routes->group('items', function($routes) {
    $routes->post('/', 'Items::index');              // list items (with warehouse join)
    $routes->post('create', 'Items::create');        // add item
    $routes->post('(:num)/update', 'Items::update/$1'); // edit item
    $routes->post('(:num)/delete', 'Items::delete/$1'); // delete item
    $routes->post('(:num)/details', 'Items::details/$1'); // item details (with warehouse info)
    $routes->post('scan', 'Items::scan');            // scanning API

});


$routes->group('reports', function($routes) {
    $routes->post('summary', 'ReportController::summary');   // delete role
    $routes->post('exportPdf', 'ReportController::exportPdf');   
});


// ================================================================
// ANALYTICS ROUTES
// ================================================================
$routes->group('analytics', function($routes) {
    $routes->post('/', 'AnalyticsController::list');          // list users + summary
    $routes->post('auditlogs', 'AnalyticsController::auditlogs');   // auditlogs
    $routes->get('auditlogs', 'AnalyticsController::auditlogs');   // auditlogs
    $routes->post('orders', 'AnalyticsController::orders');   // orders
    $routes->get('orders', 'AnalyticsController::orders');   // orders
    $routes->post('customers', 'AnalyticsController::customers');   // customers
    $routes->get('customers', 'AnalyticsController::customers');   // customers
    $routes->post('products', 'AnalyticsController::products');   // products
    $routes->get('products', 'AnalyticsController::products');   // products
    $routes->post('dashboard', 'AnalyticsController::dashboard');   // dashboard
    $routes->get('dashboard', 'AnalyticsController::dashboard');   // dashboard

    // manual sales / order write endpoints
    $routes->post('orders/add', 'AnalyticsController::addOrder'); //add order
    $routes->post('orders/update', 'AnalyticsController::updateOrder'); //edit order

    //users endpoints
    $routes->post('users', 'AnalyticsController::users'); //get users
	$routes->get('users', 'AnalyticsController::users'); //get users
    $routes->post('users/add', 'AnalyticsController::addUser'); //add users
    $routes->post('users/update', 'AnalyticsController::updateUser'); //edit/update users

});
