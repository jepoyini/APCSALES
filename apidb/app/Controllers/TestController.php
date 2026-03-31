<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

include 'app/Helpers/db.php';
include 'app/Helpers/functions.php';

class TestController extends ResourceController
{

     public function Sendemail()
     {

            // Recipient
            $to = "jepoyini@yahoo.com";

            // Subject
            $orderNumber = "TEST0001";
            $lineItem    = 1;
            $subject = "Approval Photo for Order # {$orderNumber} Line Item # {$lineItem}";

            // Message (HTML)
            $message = '
            <html>
            <head>
              <title>Approval Photo for Order</title>
            </head>
            <body>
              <p>Hi <b>Customer\'s First Name</b>,</p>
              <p>Good day.</p>
              <p>Kindly check the attached photo of the finished product thoroughly for your review and approval.</p>
              <p>Let us know if it is approved or if there are any corrections.</p>
              <p>Once we receive your confirmation, we will arrange for shipment on our next scheduled delivery.</p>
              <p>Also, verify that your shipping address below is correct:</p>
              <p><b>
                Customer\'s Complete Name<br>
                Business Name or Department (if included)<br>
                Complete Shipping Address
              </b></p>
              <p>Please note that once an order is approved in writing, any subsequent errors found after delivery will be the responsibility of the customer.</p>
              <p>I look forward to hearing from you soon.</p>
              <p>Kind regards,<br><b>Sender\'s Complete Name</b></p>
            </body>
            </html>
            ';

            // Headers
            $headers  = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: APC CRM <approvals@americanplaquecompany.com>" . "\r\n";
            $headers .= "Reply-To: approvals@americanplaquecompany.com" . "\r\n";

            // Send email
            if (mail($to, $subject, $message, $headers)) {
                echo "Approval email sent successfully!";
            } else {
                echo "Failed to send approval email.";
            }
    }

    public function helloWorld()
    {
      $this->Sendemail(); 
      die('done');

      $ip_location =  getIPLocation('120.29.76.74'); 
      echo $ip_location;
      die; 

$ip = "120.29.76.119";
$url = "https://ipwhois.app/json/$ip";

$response = file_get_contents($url);
        $data =  json_decode($response, true);
        if (!empty($data['country']))
        {
            $location = $data['country'];
        }
        if (!empty($data['region']))
        {
            $location = $data['region'].', '. $location;
        }
        echo $location; 

        die; 

if ($response === FALSE) {
    echo "Error: Unable to fetch data.";
} else {
    echo $response;
}

die; 
$ip = "120.29.76.119";
$url = "http://ipinfo.io/$ip/json";

$response = file_get_contents($url);

if ($response === FALSE) {
    echo "Error: Unable to fetch data.";
} else {
    echo $response;
}

die; 
$url = "http://ip-api.com/json/120.29.76.119";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);  // Timeout in seconds
$response = curl_exec($ch);
curl_close($ch);

if ($response === false) {
    echo "Error: " . curl_error($ch);
} else {
    echo $response;
}

die; 

$url = "http://ip-api.com/json/120.29.76.119";
$options = [
    'http' => [
        'timeout' => 10 // Timeout in seconds
    ]
];
$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

if ($response === FALSE) {
    echo "Error: Unable to fetch data.";
} else {
    echo $response;
}

die;

    $location = ""; 
      // Use ip-api.com to get location data
      $url = "http://ip-api.com/json/120.29.76.119";
      $response = file_get_contents($url);
        ld($response);
      $data =  json_decode($response, true);
      if (!empty($data['country']))
      {
        $location = $data['country'];
    }
    if (!empty($data['regionName']))
      {
        $location = $data['regionName'].', '. $location;
      }
      echo $location; 
      die; 


  // $db = \Config\Database::connect();

  //   // List of user IDs to exclude
  //   $excludedIds = [2, 211, 1105, 597, 1];

  //   // Get all users except the excluded ones
  //   $users = $db->table('users')
  //               ->whereNotIn('id', $excludedIds)
  //               ->get()
  //               ->getResult();

  //   // Insert for each user
  //   foreach ($users as $user) {
  //       $data = [
  //           'user_id' => $user->id,
  //           'site_id' => 39,
  //           'reference' => $user->username
  //       ];

  //       $db->table('a9638ae5_dashboard_db.site_links')->insert($data);
  //   }

     ld('done');

$ret = TransferHumanitarianCommissions();
ld($ret);


$ret = Process_Withdrawal(1, 50, '123123123', $coin_type='usdt') ;
ld($ret);


        $ret = UpdateAllUsersRewardCap();
        ld($ret);


        UpdateMatrixPlacement();
        exit; 

        $ret = distributeDonationCommission(200, 'studio', 6);

        //$ret = processDonation(189, 'studio',6);

        ld($ret);

        $r = processDonation(2, 'studio', 1,1,1);
        ld($r);



          $uid = $this->request->getVar('uid');
          $sid = $this->request->getVar('sid');




        $r = processDonation($uid, 'university', $sid);
        
        ld($r);

          $r = addToSharingMatrix($uid,$sid);

          ld($r);

        $password = password_hash('1234', PASSWORD_DEFAULT); 
        echo $password; 
        die; 
    	$csrf_token = "a371d31944856a664c51b95b147c60d82155c58b54507cdef0c4c662a6542871"; // bin2hex(random_bytes(32)); 
    	$token = hash('sha256', $csrf_token);
    	$token2 = hash('sha256', $csrf_token);
    	echo $csrf_token;
    	echo " = ";
    	echo $token; 
    	echo " = ";
    	echo $token2; 
    	exit; 
        //return $this->response->setJSON(['message' => 'Hello World']);
    }
}
