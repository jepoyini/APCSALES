<?php
  // ini_set('display_errors', 1);
  // ini_set('display_startup_errors', 1);
  // error_reporting(E_ALL);

	// require 'vendor/autoload.php';
	// use Sonata\GoogleAuthenticator\GoogleAuthenticator;
	// use Sonata\GoogleAuthenticator\GoogleQrUrl;


function TransferHumanitarianCommissions() 
{
    global $conn;

    // Update transactions with detailed note
   $update_tx_sql = "UPDATE transactions t
                      JOIN users u ON t.user_id = u.id
                      SET t.note = CONCAT('Transferred from ', u.username, ' (#', t.user_id, ')'),
                          t.user_id = 2
                      WHERE t.user_id IN (
                          215, 216, 217, 218, 219, 220, 221, 222, 223,
                          225, 226, 227, 228, 229, 230, 231, 232, 233,
                          234, 235, 236, 237, 238, 239, 285
                      )";    
    $stmt_tx = $conn->prepare($update_tx_sql);
    if ($stmt_tx === false) {
        return array('success' => false, 'message' => 'Failed to prepare transactions update.');
    }
    $stmt_tx->execute();
    $stmt_tx->close();

    UpdateRunningTotals(2,1);

    return array('success' => true, 'message' => 'Update successfully.');
}


function HasPendingWithdrawals($user_id) {

    global $conn;
    $row = $conn->query("SELECT count(0) AS count FROM withdraws WHERE not status in ('success','deleted','cancelled') and user_id ='$user_id'");
    $row = $row->fetch_assoc();
    $count =$row['count'];

    if ($count > 0)
    {
        return true;
    } else {
        return false;
    }
}


function Process_Withdrawal($user_id, $amount, $destination, $coin_type='usdt') {
    global $conn;

    // Insufficient
    $ret = GetWithdrawableBalance($user_id);
    if ($ret['status']=='success') {
        $availabletowithdraw = $ret['availabletowithdraw']; 
        if ($availabletowithdraw < $amount) {
            return array('status' => "error", 'message' =>  "Insufficient Wallet Balance!");
        }
    }

    $signature = null; 
    $time_unix = null; 
    

    // Insert deposit record
    $ip = getUserIP(); 
    $ip_location = getIPLocation($ip); 
    $sql = "INSERT INTO withdraws (user_id, amount,status,userAddress,time_unix,signature,date_created,ip_address,ip_location,coin_type,receiverwalletaddress) VALUES ('$user_id','$amount','pending','$destination','$time_unix','$signature',NOW(),'$ip','$ip_location','$coin_type','$destination')";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $last_id = $conn->insert_id;
    
    // Insert withdrawal record
    // $amount2 =  $amount * -1; 
    // $sql = "INSERT INTO transactions (user_id, amount,status,type,date_created) VALUES (?, ?, '', 'withdrawal',NOW())";
    // $stmt = $conn->prepare($sql);
    // $stmt->bind_param("ii", $user_id,$amount2);
    // $stmt->execute();
    // UpdateRunningTotals($user_id);
    //Run Autoupgrade
    //RunAutoUpgrade($user_id);         
    return array('status' => "success", 'message' => 'Witdrawal request submitted!');

}

function GetWithdrawableBalance($user_id) {
    global $conn;
    $walletbalance = 0;
    $withdrawable = 0; 

   //  $sql = "SELECT SUM(amount) AS balance FROM transactions WHERE user_id = '$user_id'";
   //  $result = $conn->query($sql);
   //  if ($result->num_rows > 0) {
   //      $row = $result->fetch_assoc();
   //      $walletbalance = $row['balance'];
   //      $withdrawable =$row['balance'];
   //      if (is_null($walletbalance))
            // $wallet_balance = 0; 
   //  }
   //  if ($withdrawable == 0)
   //  {
   //   $withdrawable = $walletbalance;
   //  }

    //Latest change
    $walletbalance = GetRunningTotal($user_id);
    $withdrawable = $walletbalance;

    $row = $conn->query("Select sum(amount) as total from withdraws where user_id = $user_id and status <> 'success' and status <> 'cancelled' and status <> 'deleted'");
    $row = $row->fetch_assoc();
    $pending_amount =$row['total'];
     if (is_null($pending_amount))
            $pending_amount = 0; 
    $withdrawable = $withdrawable - $pending_amount; 
    $walletbalance = $walletbalance - $pending_amount; 

    $row = $conn->query("SELECT senderWalletAddress FROM deposits WHERE user_id = $user_id and status = 'success' order by date_created desc limit 1;");
    $row = $row->fetch_assoc();
    
    $senderWalletAddress = ""; 
    if ($row !== null) {
        $senderWalletAddress = $row['senderWalletAddress'];
    }

    return array('status' => "success", 'walletbalance' => $walletbalance, 'availabletowithdraw' => $withdrawable, 'address' => $senderWalletAddress);

}


function UpdateAllUsersRewardCap()
{
    global $conn;

    // Get all user IDs
    $user_query = "SELECT id FROM users";
    $result = $conn->query($user_query);

    if ($result->num_rows === 0) {
        return array('success' => false, 'message' => 'No users found.');
    }

    // Prepare reusable statements
    $reward_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total_amount FROM sharing_purchase WHERE user_id = ? AND status = 'completed'");
    $update_stmt = $conn->prepare("UPDATE users SET reward_cap = ? WHERE id = ?");

    while ($row = $result->fetch_assoc()) {
        $uid = $row['id'];

        // Get total completed sharing_purchase amount
        $reward_stmt->bind_param('i', $uid);
        $reward_stmt->execute();
        $reward_stmt->bind_result($reward_total);
        $reward_stmt->fetch();
        $reward_stmt->reset();

        $reward_cap = $reward_total + 72;

        // Update reward_cap for this user
        $update_stmt->bind_param('di', $reward_cap, $uid);
        $update_stmt->execute();
    }

    // Close statements
    $reward_stmt->close();
    $update_stmt->close();

    return array('success' => true, 'message' => 'All users reward_cap updated successfully.');
}


function UpdateRewardCap($uid) 
{
    global $conn;

    // Calculate reward_cap from sharing_purchase table
    $reward_sql = "SELECT COALESCE(SUM(amount), 0) AS total_amount 
                   FROM sharing_purchase 
                   WHERE user_id = ? AND status = 'completed'";
    $stmt = $conn->prepare($reward_sql);
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->bind_result($reward_total);
    $stmt->fetch();
    $stmt->close();

    $reward_cap = $reward_total + 72;

    // Update users table with the new reward_cap
    $update_user_sql = "UPDATE users SET reward_cap = ? WHERE id = ?";
    $stmt = $conn->prepare($update_user_sql);
    $stmt->bind_param('di', $reward_cap, $uid);
    $stmt->execute();
    $stmt->close();

    return array('success' => true, 'message' => 'Running Totals and Reward Cap updated successfully.');
}



function utf8ize($mixed) {
    if (is_array($mixed)) {
        foreach ($mixed as $key => $value) {
            $mixed[$key] = utf8ize($value);
        }
    } elseif (is_string($mixed)) {
        // Convert from ISO-8859-1 to UTF-8 if malformed
        if (!mb_check_encoding($mixed, 'UTF-8')) {
            return mb_convert_encoding($mixed, 'UTF-8', 'ISO-8859-1');
        }
    }
    return $mixed;
}

function UpdateMatrixPlacement($user_id = null) {


    global $conn;	

    if ($user_id) {

        $row = $conn->query("SELECT sponsor_id FROM users WHERE id = $user_id");
        $row = $row->fetch_assoc();

        $sponsor_id = $row['sponsor_id'];
        $ret = addToSharingMatrix($user_id, $sponsor_id);

        return $ret; 

    } else {

	    // Get users to process
	    $stmt = $conn->prepare("SELECT id, sponsor_id FROM users WHERE status = 'holding' AND date_created <= CURDATE() - INTERVAL 1 DAY ORDER BY date_created");
	    $stmt->execute();
	    $result = $stmt->get_result();

	    while ($row = $result->fetch_assoc()) {
	        $user_id = $row['id'];
	        $sponsor_id = $row['sponsor_id'];

	        echo "Placing user: " . $user_id; 
	        echo "<br />";

	        // Call your matrix placement function
	        addToSharingMatrix($user_id, $sponsor_id);

	    }
    }

    //EXECUTE SPECIAL SQL
    //$stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE status = 'holding' AND date_created <= CURDATE() - INTERVAL 1 DAY;");
    //$stmt->execute();    
}

function Withdraw_Complete($user_id, $amount, $id=0,$hash='',$coin_type='usdt',$hashcheck = true) {

    global $conn;
    // Check hash if already existing
    if ($hashcheck) {
        if (!empty($hash) ) {
            if (empty($coin_type) || $coin_type == 'flr') {
                // Call Flare AvPI to check transaction status
                $apiUrl = "https://flare-explorer.flare.network/api/v2/transactions/$hash";
                $response = file_get_contents($apiUrl);
                if (!$response) {
                    return ['success'=>false,'message'=>'hash not found'];
                }

                $transactionData = json_decode($response, true);
                // Check if the transaction was successful
                if ($transactionData['result'] !== 'success') {
                    // Check if the transaction amount matches
                    return ['success'=>false,'message'=>'hash status not success'];
                }
            } elseif ($coin_type == 'pol') {
                // Call POLY to check transaction status
                // $apiUrl = "https://api.polygonscan.com/api?module=proxy&action=eth_getTransactionByHash&txhash=$hash&apikey=A3G5T81Q5I68ZDHJFQWPQEZDC3QHQP2DUA";
                // $response = file_get_contents($apiUrl);
          //       $response = json_decode($response);
          //        ld($response->result->value); //from,to,hash,value
                $apiUrl = "https://api.polygonscan.com/api?module=transaction&action=gettxreceiptstatus&txhash=$hash&apikey=A3G5T81Q5I68ZDHJFQWPQEZDC3QHQP2DUA";
                $response= file_get_contents($apiUrl);
                if (!$response) {
                    return ['success'=>false,'message'=>'hash not found'];
                }

                $transactionData = json_decode($response);
                // Check if the transaction was successful
                if ($transactionData->result->status !== '1') {
                    // Check if the transaction amount matches
                    return ['success'=>false,'message'=>'hash status not success'];
                }

            }
        } else {
             return ['success'=>false,'message'=>'no hash found'];
        }
    }

    $amount=0; 
    // Get coded upline. 
    if ($id > 0)
    {
        $row = $conn->query("SELECT amount FROM withdraws WHERE id = $id ");
        $row = $row->fetch_assoc();
        $amount =$row['amount'];
    } 
    $amount = floatval($amount) ;

    //check if deposit already exist
    $row = $conn->query("Select count(0) as count from transactions where `user_id` = $user_id and `type` = 'withdrawal' and `ref_id`=$id and `status`='completed'; ");
    $row = $row->fetch_assoc();
    $count =$row['count'];

    $amount =$amount * -1;  
    if ($count==0)
    {
    
        // Change status to success
        $sql = "UPDATE withdraws set status = 'success' where id = $id";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        $sql = "INSERT INTO transactions (user_id, amount,status,type,date_created,ref_id) VALUES (?, ?, 'completed', 'withdrawal',NOW(),'$id')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("id", $user_id, $amount);
        $stmt->execute();
        UpdateRunningTotals($user_id);

        return ['success'=>true,'message'=>'success'];
    }

}


function Deposit_Complete($user_id, $amount, $id = 0, $hash = '', $walletaddress = '', $amount_flr = 0, $coin_type = 'flr', $hashcheck = true)
{
    global $conn;

    if ($hashcheck) {

        if (!empty($hash)) {
            // Check if hash already exists
            $row = $conn->query("SELECT count(0) as count FROM deposits WHERE transactionID='$hash' and status='success'");
            $row = $row->fetch_assoc();
            if ($row['count'] > 0) {
                return ['success' => false, 'message' => 'hash already exists!'];
            }

            if (empty($coin_type) || $coin_type == 'flr') {
                // FLR chain validation
                $apiUrl = "https://flare-explorer.flare.network/api/v2/transactions/$hash";
                $response = file_get_contents($apiUrl);
                if (!$response) return ['success' => false, 'message' => 'hash not found'];

                $transactionData = json_decode($response, true);
                if ($transactionData['result'] == 'success' && $amount_flr > 0) {
                    $value = $transactionData['value'];
                    $amount_in_wei = bcmul($amount_flr, '1000000000000000000', 0);
                    if ($value !== $amount_in_wei) {
                        return ['success' => false, 'message' => 'amount mismatch'];
                    }
                }

                $expectedAddress = "0xe2ed78e829Db9a23E624FCb01143Ca71F35E82D0";
                $expectedAddress2 = "0xDD0f40944A0eC05719141f17AC7f69080b94c118";
                $toAddress = $transactionData['to']['hash'];
                if (strcasecmp($toAddress, $expectedAddress) !== 0 && strcasecmp($toAddress, $expectedAddress2) !== 0) {
                    return ['success' => false, 'message' => 'to address not correct'];
                }

                if (!empty($walletaddress)) {
                    $fromAddress = $transactionData['from']['hash'];
                    if (strcasecmp($fromAddress, $walletaddress) !== 0) {
                        return ['success' => false, 'message' => 'from address mismatch'];
                    }
                }

            // } elseif ($coin_type === 'btc') {
                // BTC verification using Blockstream API
                // $apiUrl = "https://blockstream.info/api/tx/$hash";
                // $response = file_get_contents($apiUrl);
                // if (!$response) return ['success' => false, 'message' => 'BTC transaction not found'];

                // $transactionData = json_decode($response, true);

                // // Check if confirmed
                // if (!isset($transactionData['status']['confirmed']) || !$transactionData['status']['confirmed']) {
                //     return ['success' => false, 'message' => 'BTC transaction is not yet confirmed'];
                // }

                // $matched = false;
                // foreach ($transactionData['vout'] as $output) {
                //     if (isset($output['scriptpubkey_address']) && isset($output['value'])) {
                //         $btcAddress = $output['scriptpubkey_address'];
                //         $btcAmount = $output['value'] / 1e8; // Convert from satoshi to BTC
                //         if (strcasecmp($btcAddress, $walletaddress) === 0 && abs($btcAmount - $amount) < 0.00001) {
                //             $matched = true;
                //             break;
                //         }
                //     }
                // }

                // if (!$matched) {
                //     return ['success' => false, 'message' => 'BTC recipient or amount does not match'];
                // }            


            } elseif (in_array($coin_type, ['usdt-bep20'])) {
 


                $isBEP20 = ($coin_type === 'usdt-bep20');

                $apiBase = 'https://api.bscscan.com';
                $apiKey =  'GN6BUV6CBDSCYUSKQQERU9F9R3Y3RB2X6T';

  
                // Step 1: Verify transaction receipt
                $apiUrl = "$apiBase/api?module=transaction&action=gettxreceiptstatus&txhash=$hash&apikey=$apiKey";
                $response = file_get_contents($apiUrl);
                if (!$response) return ['success' => false, 'message' => 'hash not found'];
                $transactionData = json_decode($response);
                if ($transactionData->result->status !== '1') {
                    return ['success' => false, 'message' => 'hash status not success'];
                }

                // Step 2: Get transaction input data
                $apiUrl = "$apiBase/api?module=proxy&action=eth_getTransactionByHash&txhash=$hash&apikey=$apiKey";
                $response = file_get_contents($apiUrl);
                if (!$response) return ['success' => false, 'message' => 'transaction not found'];
                $transactionData = json_decode($response);

                if (!isset($transactionData->result)) return ['success' => false, 'message' => 'transaction not found'];
                if (in_array($coin_type, ['usdt', 'usdt-bep20'])) {

                    $inputValue = $transactionData->result->input;
                    $hexValue = ltrim(substr($inputValue, -64), '0');
                    $valueInUSDT = hexdec($hexValue) / 1e6;
                    $usdtAmount = $valueInUSDT / 1e12; // or pow(10, 12)
                    if ((float)$usdtAmount != (float)$amount) {
                        return ['success' => false, 'message' => 'Transaction value does not match'];
                    }
                }

                // $fromAddress = $transactionData->result->from;
                // if (strcasecmp($fromAddress, $walletaddress) !== 0) {
                //     return ['success' => false, 'message' => 'Sender Wallet does not match'];
                // }

                //Get the To address
				$inputValue = $transactionData->result->input;
				// Extract the recipient address from input (method ID is 8 chars, next 64 chars is the address)
				$recipientHex = substr($inputValue, 10, 64); // skip "0x" and 8 chars of method ID
				$recipientAddress = '0x' . substr($recipientHex, 24); // get last 40 chars = 20 bytes = Ethereum address
				$expectedRecipient = '0xd549c160b031dd688de9722d8ff13cb9d0f0b64d';
				if (strcasecmp($recipientAddress, $expectedRecipient) !== 0) {
				    return ['success' => false, 'message' => 'Token transfer recipient does not match'];
         		}

                $blockHex = $transactionData->result->blockNumber;
                $blockNumber = hexdec($blockHex);

                // Step 3: Verify timestamp
                $apiUrl = "$apiBase/api?module=block&action=getblockreward&blockno=$blockNumber&apikey=$apiKey";
                $response = file_get_contents($apiUrl);
                if (!$response) return ['success' => false, 'message' => 'Failed to fetch block data'];
                $blockData = json_decode($response);
                if (!isset($blockData->result)) return ['success' => false, 'message' => 'Block data not found'];

				$transactionTimestamp = (int)$blockData->result->timeStamp;

				// Get expected timestamp from database
				$row = $conn->query("SELECT date_created FROM deposits WHERE id=$id");
				$row = $row->fetch_assoc();
				$expectedTimestamp = strtotime($row['date_created']);

				// Define 2-hour range in seconds
				$rangeInSeconds = 2 * 3600;

				// Compare timestamps
				if ($transactionTimestamp < $expectedTimestamp - $rangeInSeconds || $transactionTimestamp > $expectedTimestamp + $rangeInSeconds) {
				    return [
				        'success' => false,
				        'message' => "Transaction date mismatch (transaction time: " . date("Y-m-d H:i", $transactionTimestamp) . 
				                     " vs expected: " . date("Y-m-d H:i", $expectedTimestamp) . " ± 2 hours)"
				    ];
				}


            } elseif (in_array($coin_type, ['pol', 'usdt'])) {

                $apiBase = 'https://api.polygonscan.com';
                $apiKey = 'A3G5T81Q5I68ZDHJFQWPQEZDC3QHQP2DUA';
  
                // Step 1: Verify transaction receipt
                $apiUrl = "$apiBase/api?module=transaction&action=gettxreceiptstatus&txhash=$hash&apikey=$apiKey";
                $response = file_get_contents($apiUrl);
                if (!$response) return ['success' => false, 'message' => 'hash not found'];
                $transactionData = json_decode($response);
                if ($transactionData->result->status !== '1') {
                    return ['success' => false, 'message' => 'hash status not success'];
                }

                // Step 2: Get transaction input data
                $apiUrl = "$apiBase/api?module=proxy&action=eth_getTransactionByHash&txhash=$hash&apikey=$apiKey";
                $response = file_get_contents($apiUrl);

                if (!$response) return ['success' => false, 'message' => 'transaction not found'];
                $transactionData = json_decode($response);

                if (!isset($transactionData->result)) return ['success' => false, 'message' => 'transaction not found'];
                if (in_array($coin_type, ['usdt'])) {
					$inputValue = $transactionData->result->input;

					$inputValue = $transactionData->result->input;

					// Strip the "0x" if present
					$inputValue = ltrim($inputValue, '0x');

					// Now extract the 64-char amount (starts at char 72)
					$amountHex = substr($inputValue, 72, 64);

					// Convert hex to decimal safely
					$rawAmount = gmp_strval(gmp_init($amountHex, 16));

					// USDT has 6 decimals, so divide by 1e6
					$valueInUSDT = bcdiv($rawAmount, '1000000', 6);
				
					if (abs((float)$valueInUSDT - (float)$amount) > 0.1) {
					    return ['success' => false, 'message' => 'Transaction value does not match'];
					}
                }

                // $fromAddress = $transactionData->result->from;
                // if (strcasecmp($fromAddress, $walletaddress) !== 0) {
                //     return ['success' => false, 'message' => 'Sender Wallet does not match'];
                // }

                // Check the ToAddress
				$inputValue = $transactionData->result->input;
				// Remove "0x" if present
				$inputValue = ltrim($inputValue, '0x');
				// Extract recipient address (skip method ID = 8 chars, get next 64)
				$recipientHex = substr($inputValue, 8, 64);
				// Get last 40 chars of that 64-character string (20 bytes = Ethereum address)
				$recipient = '0x' . substr($recipientHex, 24);

     			$expectedTo = '0xead915b3674d475554b8eebceac5baf0ddb8f731';

				if (strcasecmp($recipient, $expectedTo) !== 0) {
				    return ['success' => false, 'message' => "Recipient address does not match: $recipient vs $expectedTo"];
				}

                $blockHex = $transactionData->result->blockNumber;
                $blockNumber = hexdec($blockHex);

                // Step 3: Verify timestamp
                $apiUrl = "$apiBase/api?module=block&action=getblockreward&blockno=$blockNumber&apikey=$apiKey";
                $response = file_get_contents($apiUrl);
                if (!$response) return ['success' => false, 'message' => 'Failed to fetch block data'];
                $blockData = json_decode($response);
                if (!isset($blockData->result)) return ['success' => false, 'message' => 'Block data not found'];

				$transactionTimestamp = (int)$blockData->result->timeStamp;

				// Get expected timestamp from database
				$row = $conn->query("SELECT date_created FROM deposits WHERE id=$id");
				$row = $row->fetch_assoc();
				$expectedTimestamp = strtotime($row['date_created']);

				// Define 2-hour range in seconds
				$rangeInSeconds = 2 * 3600;

				// Compare timestamps
				if ($transactionTimestamp < $expectedTimestamp - $rangeInSeconds || $transactionTimestamp > $expectedTimestamp + $rangeInSeconds) {
				    return [
				        'success' => false,
				        'message' => "Transaction date mismatch (transaction time: " . date("Y-m-d H:i", $transactionTimestamp) . 
				                     " vs expected: " . date("Y-m-d H:i", $expectedTimestamp) . " ± 2 hours)"
				    ];
				}

            } elseif (in_array($coin_type, ['xrp', 'rlusd'])) {
                $verification = verifyXrpTransaction($hash, $walletaddress);
                if (!$verification['success']) {
                    return ['success' => false, 'message' => 'Transaction not verified'];
                }
            } else {
                return ['success' => false, 'message' => 'crypto not supported for auto-verification'];
            }
        } else {
            return ['success' => false, 'message' => 'hash not found'];
        }
    }

    if ($id > 0) {
        $row = $conn->query("SELECT amount FROM deposits WHERE id = $id");
        $row = $row->fetch_assoc();
        $amount = floatval($row['amount']);
    }

    $sql = "UPDATE deposits SET status = 'success' WHERE id = $id";
    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $row = $conn->query("SELECT count(0) as count FROM ewallets WHERE user_id = $user_id AND type = 'deposit' AND ref_id = $id AND status = 'completed'");
    $row = $row->fetch_assoc();
    if ($row['count'] == 0) {
        $sql = "INSERT INTO ewallets (user_id, amount, status, type, date_created, ref_id) VALUES (?, ?, 'completed', 'deposit', NOW(), '$id')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("id", $user_id, $amount);
        $stmt->execute();
        UpdateRunningTotals_Ewallet($user_id);
    }

    return ['success' => true];
}

	function Process_Deposit($user_id, $amount,$amount_flr,$wallet_address="",$coin_type='flr',$is_manual=0,$hash='',$notes='',$status='pending') {

	    global $conn;
	    $ip = getUserIP(); 
	    $ip_location = getIPLocation($ip); 
	    // Insert deposit record
	    $sql = "INSERT INTO deposits (user_id, username,amount,amount_flr,status,date_created,ip_address,ip_location,senderWalletAddress,senderAddress,coin_type,is_manual,transactionID,notes) VALUES ('$user_id','$user_id','$amount','$amount_flr','$status',NOW(),'$ip','$ip_location','$wallet_address','$wallet_address','$coin_type','$is_manual','$hash','$notes')";
	    $stmt = $conn->prepare($sql);
	    $stmt->execute();
	    $last_id = $conn->insert_id;

  		return array('status' => "success", 'message' => 'Deposit successful!','id'=>$last_id);

	}

	function UpdateRunningTotals($uid,$forceupdateall=0) 
	{
		global $conn;

		// Initialize variables
		$prev_total = 0;
		$prev_onhold = 0;
		$current_user = null;

		if ($forceupdateall) {
		    $update_sql = "UPDATE transactions SET running_total = null, running_onhold = null WHERE user_id = $uid";
	        $stmt = $conn->prepare($update_sql);
	        $stmt->execute();
		}

		// Fetch transactions ordered by user_id and date_created
		if (empty($uid))
		    $sql = "SELECT id, user_id, amount, status, type, running_onhold, running_total FROM transactions WHERE running_total is null  ORDER BY user_id, date_created, id";
		else
	    	$sql = "SELECT id, user_id, amount, status, type, running_onhold, running_total FROM transactions WHERE user_id = $uid and running_total is null ORDER BY user_id, date_created, id";			
		    $prev_total = GetRunningTotal($uid);
		    //$prev_onhold = GetRunningOnholdTotal($uid);
		    $current_user = $uid;


		$result = $conn->query($sql);

		if ($result->num_rows > 0) {
		    // Loop through each transaction
		    while ($row = $result->fetch_assoc()) {
		        $transaction_id = $row['id'];
		        $user_id = $row['user_id'];
		        $amount = $row['amount']; // Payment amounts are negative
		        $status = $row['status'];
		        $type = $row['type'];
		        $running_onhold = $row['running_onhold'];
		        $running_total = $row['running_total'];

		        // If the user changes, reset the running totals
		        if ($user_id != $current_user) {
		            $prev_total = 0;
		            $prev_onhold = 0;
		            $current_user = $user_id;
		        }

		        // If running_total is NULL, set it to the previous record's value
		        if (is_null($running_total)) {
		            $running_total = $prev_total;
		        }

		        // If running_onhold is NULL, set it to the previous record's value
		        if (is_null($running_onhold)) {
		            $running_onhold = $prev_onhold;
		        }

		        // Check the status and type
		        if ($status == 'onhold') {
		            // Update running_onhold if the status is 'onhold'
		            $prev_onhold += $amount; // Add to onhold (amount is positive for 'onhold')

		            // Update the transaction record with the new running_onhold
		            $update_sql = "UPDATE transactions SET running_total = ?, running_onhold = ? WHERE id = ?";
		            $stmt = $conn->prepare($update_sql);
		            $stmt->bind_param('ddi', $prev_total, $prev_onhold, $transaction_id);
		            $stmt->execute();
		            $stmt->close();
		        } else if ($type == 'payment' && $prev_onhold > 0) {
		            // Handle payments using running_onhold first
		            $amount_abs = abs($amount); // Get the absolute value of the payment

		            if ($prev_onhold >= $amount_abs) {
		                // Deduct fully from running_onhold
		                $prev_onhold -= $amount_abs;
		            } else {
		                // Use all from running_onhold and remaining from running_total
		                $remaining_amount = $amount_abs - $prev_onhold;
		                $prev_onhold = 0; // running_onhold is fully used
		                $prev_total += -$remaining_amount; // Deduct remaining amount (amount is negative, so add)
		            }

		            // Update the transaction record
		            $update_sql = "UPDATE transactions SET running_total = ?, running_onhold = ? WHERE id = ?";
		            $stmt = $conn->prepare($update_sql);
		            $stmt->bind_param('ddi', $prev_total, $prev_onhold, $transaction_id);
		            $stmt->execute();
		            $stmt->close();
		        } else {
		            // For other types or if no running_onhold, update running_total
		            $prev_total += $amount; // Add the amount to running_total
		            $update_sql = "UPDATE transactions SET running_total = ?, running_onhold = ? WHERE id = ?";
		            $stmt = $conn->prepare($update_sql);
		            $stmt->bind_param('ddi', $prev_total, $prev_onhold, $transaction_id);
		            $stmt->execute();
		            $stmt->close();
		        }
		    }

	    	return array('success' => true, 'message' => 'Running Totals updated successfully.');

		} else {
	    	return array('success' => false, 'message' => 'No transactions found.');
		}
	}

    function UpdateRunningTotals_Ewallet($uid,$forceupdateall=0) 
    {
        global $conn;

        // Initialize variables
        $prev_total = 0;
        $prev_onhold = 0;
        $current_user = null;

        if ($forceupdateall) {
            $update_sql = "UPDATE ewallets SET running_total = null, running_onhold = null WHERE user_id = $uid";
            $stmt = $conn->prepare($update_sql);
            $stmt->execute();
        }

        // Fetch transactions ordered by user_id and date_created
        if (empty($uid))
            $sql = "SELECT id, user_id, amount, status, type, running_onhold, running_total FROM ewallets WHERE running_total is null  ORDER BY user_id, date_created, id";
        else
            $sql = "SELECT id, user_id, amount, status, type, running_onhold, running_total FROM ewallets WHERE user_id = $uid and running_total is null ORDER BY user_id, date_created, id";           
            $prev_total = GetRunningTotal_Ewallet($uid);
            //$prev_onhold = GetRunningOnholdTotal($uid);
            $current_user = $uid;


        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            // Loop through each transaction
            while ($row = $result->fetch_assoc()) {
                $transaction_id = $row['id'];
                $user_id = $row['user_id'];
                $amount = $row['amount']; // Payment amounts are negative
                $status = $row['status'];
                $type = $row['type'];
                $running_onhold = $row['running_onhold'];
                $running_total = $row['running_total'];

                // If the user changes, reset the running totals
                if ($user_id != $current_user) {
                    $prev_total = 0;
                    $prev_onhold = 0;
                    $current_user = $user_id;
                }

                // If running_total is NULL, set it to the previous record's value
                if (is_null($running_total)) {
                    $running_total = $prev_total;
                }

                // If running_onhold is NULL, set it to the previous record's value
                if (is_null($running_onhold)) {
                    $running_onhold = $prev_onhold;
                }

                // Check the status and type
                if ($status == 'onhold') {
                    // Update running_onhold if the status is 'onhold'
                    $prev_onhold += $amount; // Add to onhold (amount is positive for 'onhold')

                    // Update the transaction record with the new running_onhold
                    $update_sql = "UPDATE ewallets SET running_total = ?, running_onhold = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param('ddi', $prev_total, $prev_onhold, $transaction_id);
                    $stmt->execute();
                    $stmt->close();
                } else if ($type == 'payment' && $prev_onhold > 0) {
                    // Handle payments using running_onhold first
                    $amount_abs = abs($amount); // Get the absolute value of the payment

                    if ($prev_onhold >= $amount_abs) {
                        // Deduct fully from running_onhold
                        $prev_onhold -= $amount_abs;
                    } else {
                        // Use all from running_onhold and remaining from running_total
                        $remaining_amount = $amount_abs - $prev_onhold;
                        $prev_onhold = 0; // running_onhold is fully used
                        $prev_total += -$remaining_amount; // Deduct remaining amount (amount is negative, so add)
                    }

                    // Update the transaction record
                    $update_sql = "UPDATE ewallets SET running_total = ?, running_onhold = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param('ddi', $prev_total, $prev_onhold, $transaction_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // For other types or if no running_onhold, update running_total
                    $prev_total += $amount; // Add the amount to running_total
                    $update_sql = "UPDATE ewallets SET running_total = ?, running_onhold = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param('ddi', $prev_total, $prev_onhold, $transaction_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            return array('success' => true, 'message' => 'Running Totals updated successfully.');

        } else {
            return array('success' => false, 'message' => 'No transactions found.');
        }
    }


    function GetTotalDeposits($user_id)
    {
        global $conn;
        $row = $conn->query("SELECT sum(amount) as totaldeposit FROM transactions WHERE status = 'completed' and user_id =$user_id and type = 'deposit'");
        $row = $row->fetch_assoc();
        if (!empty($row['totaldeposit']))
            return $row['totaldeposit'];   
        else
            return 0; 
    }    

    function GetTotalPendingWithdrawal($user_id)
    {
        global $conn;
        $row = $conn->query("SELECT sum(amount) as totalpendingwithdrawal FROM withdraws WHERE status = 'pending' and user_id =$user_id");
        $row = $row->fetch_assoc();
        if (!empty($row['totalpendingwithdrawal']))
            return $row['totalpendingwithdrawal'];   
        else
            return 0; 
    }    


    function GetTotalWithdrawn($user_id)
    {
        global $conn;
        $row = $conn->query("SELECT sum(amount) as totalwithdrawn FROM withdraws WHERE status = 'completed' and user_id =$user_id");
        $row = $row->fetch_assoc();
        if (!empty($row['totalwithdrawn']))
            return $row['totalwithdrawn'];   
        else
            return 0; 
    }   

    function GetRunningTotal_Ewallet($user_id)
    {
        global $conn;
        $row = $conn->query("SELECT running_total FROM ewallets WHERE status <> 'cancelled' and status <> 'deleted' and user_id = $user_id and not running_total is null ORDER BY date_created desc, id desc limit 1 ");
        $row = $row->fetch_assoc();
        if (!empty($row['running_total']))
            return $row['running_total'];   
        else
            return 0; 
    }        

	function GetRunningTotal($user_id)
	{
		global $conn;
		$row = $conn->query("SELECT running_total FROM transactions WHERE status <> 'cancelled' and status <> 'deleted' and user_id = $user_id and not running_total is null ORDER BY date_created desc, id desc limit 1 ");
		$row = $row->fetch_assoc();
		if (!empty($row['running_total']))
			return $row['running_total'];	
		else
			return 0; 
	}


	function GetRunningRewardTotal($user_id)
	{
		global $conn;
		$row = $conn->query("SELECT running_total FROM rewards WHERE status <> 'cancelled' and status <> 'deleted' and  user_id = $user_id and not running_total is null ORDER BY date_created desc, id desc limit 1 ");
		$row = $row->fetch_assoc();
		if (!empty($row['running_total']))
			return $row['running_total'];	
		else
			return 0; 
	}

	function GetRewardCap($user_id)
	{
		global $conn;
		$row = $conn->query("SELECT reward_cap FROM users WHERE id = $user_id ");
		$row = $row->fetch_assoc();
		if (!empty($row['reward_cap']))
			return $row['reward_cap'];	
		else
			return 0; 
	}
	
	function Logged_User($user_id)
	{
		global $conn;



		$ip = getUserIP(); 



		$ip_location =  getIPLocation($ip); 

		// Generate CSRF token
		$csrf_token = bin2hex(random_bytes(32));  //session_id();
		$hashedToken = hash('sha256', $csrf_token); 
        $_SESSION['csrf_token'] = $csrf_token;
		//$sql = "UPDATE users set logged_ip = '$ip', csrf_token = '$hashedToken', logged_time = NOW() WHERE id = $user_id";
		$sql = "UPDATE users set logged_ip = '$ip', csrf_token = '$csrf_token', logged_time = NOW(), logged_location = '$ip_location' WHERE id = $user_id";
		$stmt = $conn->prepare($sql);
		$stmt->execute();	

        // Add activity Log
		add_activity_log($user_id,'login');				

		return $csrf_token; 
	}
	
    function add_activity_log($user_id, $type, $data = '')
    {
        global $conn;

        $ip = getUserIP();
        $ip_location = getIPLocation($ip);

        $sql = "INSERT INTO activity_log 
                (`user_id`, `type`, `date_created`, `data`, `ip_address`, `ip_location`) 
                VALUES (?, ?, NOW(), ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $type, $data, $ip, $ip_location]);
    }
    	

	function getUserIP() {
	    // Check if the user is using a shared internet (e.g., proxies)
	    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
	        $ip = $_SERVER['HTTP_CLIENT_IP'];
	    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	        // Check if the user is using a proxy
	        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	    } else {
	        // Get the user's real IP address
	        $ip = $_SERVER['REMOTE_ADDR'];
	    }
	    return $ip;
	}

	function getIPLocation2($ip) {
	    $url = "http://ip-api.com/json/{$ip}";
	    $response = file_get_contents($url);
	    $data = json_decode($response, true);

	    if ($data['status'] === 'success') {
	        return $data;
	    } else {
	        return null;
	    }
	}


    function getIPLocation3($ip)
    {

        return 'Not available';

        $location = ""; 
        // Use ip-api.com to get location data
        $url = "http://ip-api.com/json/{$ip}";
        $response = file_get_contents($url);
        $data =  json_decode($response, true);
        if (!empty($data['country']))
        {
            $location = $data['country'];
        }
        if (!empty($data['regionName']))
        {
            $location = $data['regionName'].', '. $location;
        }
        return $location; 
    }



	function getIPLocation($ip)
	{
        
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
	    return $location; 
	}

	function add_admin_log($user_id,$type,$data='')
	{
		global $conn;
		$ip = getUserIP(); 
		$ip_location = getIPLocation($ip); 
		$sql = "INSERT INTO admin_log (`user_id`, `type` ,`date_created`,`data`,`ip_address`,`ip_location`) VALUES ($user_id,'$type',NOW(),'$data','$ip','$ip_location')";
		$stmt = $conn->prepare($sql);
		$stmt->execute();			
	}


function ProcessForgotPassword($email)
{
    global $conn;

    if (!isset($email)) {
        echo json_encode(["status" => "error", "message" => "Email is required"]);
        return;
    }

    $query = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "No email found"]);
        return;
    }

    // Generate a raw token and hash it
    $rawToken = bin2hex(random_bytes(32));
    $hashedToken = hash("sha256", $rawToken);

    // Save hashed token to DB
    $updateSql = "UPDATE users SET reset_token = ?, reset_requested_at = NOW() WHERE email = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("ss", $hashedToken, $email);

    if (!$updateStmt->execute()) {
        echo json_encode(["status" => "error", "message" => "Error updating database: " . $conn->error]);
        return;
    }

    // Send plain token to email
    $resetLink = "https://inventory.americanplaquecompany.com/reset-password?token=$rawToken";

    $subject = "APC Inventory Password Reset Request";
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: APC Inventory <support@americanplaquecompany.com>\r\n";
    $headers .= "Reply-To: support@americanplaquecompany.com\r\n";

    $message = "
      <html>
      <head><title>APC Inventory Password Reset</title></head>
      <body>
        <p>Dear User,</p>
        <p>Click the link below to reset your password:</p>
        <p><a href='$resetLink'>Reset Password</a></p>
        <p>If you did not request this, please ignore this email.</p>
        <p>APC Support Team</p>
      </body>
      </html>";

    if (mail($email, $subject, $message, $headers)) {
        echo json_encode(["status" => "success", "message" => "Password reset email sent."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to send email."]);
    }
}


	function ProcessForgotPassword_old($email)
	{
		global $conn;
        if (isset($email)) {
            // Load CodeIgniter database configuration
            $query = "SELECT * FROM users where email='$email'";
            $result = $conn->query($query);
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
            } else {
                echo json_encode(["status" => "error", "message" => "No email found"]);
                exit; 
            }

            // Generate a unique password reset token
            $token = bin2hex(random_bytes(50));

            // Store the token in the database associated with the user's email
            $sql = "UPDATE users SET reset_token='$token', reset_requested_at=NOW() WHERE email='$email'";
            if ($conn->query($sql) === TRUE) {
                // Prepare the reset link
                $resetLink = "https://ibopro.com/dashboard/reset-password?token=$token";

                // Email content
                $subject = "IBPPRO Password Reset Request";
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "From: IBOPRO <support@ibopro.com>\r\n";
                $headers .= "Reply-To: support@ibopro.com\r\n";
      
                $message = "
                  <html>
                  <head>
                    <title>IBPPRO Password Reset Request</title>
                  </head>
                  <body>
                    <p>Dear User,</p>
                    <p>We received a request to reset your password. Click the link below to proceed with the reset:</p>
                    <p><a href='$resetLink'>Reset Password</a></p>
                    <p>If you did not initiate this request, please disregard this email or reach out to our support team for assistance.</p>
                    <p>Best regards,</p>
                    <p>IBOPRO Support Team</p>
                  </body>
                  </html>
                  ";

                // Send the email
                if (mail($email, $subject, $message, $headers)) {
                    echo json_encode(["status" => "success", "message" => "Password reset email sent."]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Failed to send email."]);
                }

            } else {
                echo json_encode(["status" => "error", "message" => "Error updating database: " . $conn->error]);
            }

        } else {
            echo json_encode(["status" => "error", "message" => "Email is required"]);
        }
        return; 
    }


	if (!function_exists('ld')) {
	    function ld($data) {
	       // echo "<pre>";
	       // print_r($data);
	       // echo "</pre>";
	    	var_dump($data);
	        die(); // Stops execution
	    }
	}


	function AddMatrix_5x11_old($user_id, $purchase_id = 0, $root = 0) {
		global $conn;

		if ($root)
		{
			$ref_id = 1; 
		} else {
			//get sponsor_id
			$row = $conn->query("SELECT sponsor_id FROM users WHERE id = $user_id and status = 'active'");
			$row = $row->fetch_assoc();
			$ref_id = $row['sponsor_id']; 
		}

		$upline_id = getAvailableUplineId($ref_id); // Start from referrer
		if ($upline_id === null) {
			return false; // No placement available
		}

		$level = calculateLevel($upline_id) + 1; // Add 1 since we're placing under this upline

		if ($level > 11) {
			return false; // Level too deep
		}

		$stmt = $conn->prepare("INSERT INTO matrix_5x11 (user_id, upline_id,  purchase_id) VALUES (?, ?, ?)");
		$stmt->bind_param("iii", $user_id, $upline_id, $purchase_id);
		return $stmt->execute();
	}

	function getAvailableUplineId_old($ref_id) {
		global $conn;

		// Start from the sponsor/referrer
		$queue = [$ref_id];

		while (!empty($queue)) {
			$currentUpline = array_shift($queue);

			// Check if this upline has less than 5 downlines
			$stmt = $conn->prepare("SELECT COUNT(*) AS downline_count FROM matrix_5x11 WHERE upline_id = ? and done = 0");
			$stmt->bind_param("i", $currentUpline);
			$stmt->execute();
			$result = $stmt->get_result()->fetch_assoc();
			$downlineCount = $result['downline_count'];

			if ($downlineCount < 5) {
				return $currentUpline;
			} else {
				// Add this upline’s downlines to the queue for BFS
				$stmt = $conn->prepare("SELECT user_id FROM matrix_5x11 WHERE upline_id = ? and done = 0");
				$stmt->bind_param("i", $currentUpline);
				$stmt->execute();
				$result = $stmt->get_result();
				while ($row = $result->fetch_assoc()) {
					$queue[] = $row['user_id'];
				}
			}
		}

		return null; // No available slot
	}

	function calculateLevel_old($userId) {
		global $conn;

		$level = 0;
		$currentId = $userId;

		while (true) {
			$stmt = $conn->prepare("SELECT upline_id FROM matrix_5x11 WHERE user_id = ? and done = 0");
			$stmt->bind_param("i", $currentId);
			$stmt->execute();
			$result = $stmt->get_result()->fetch_assoc();

			if (!$result || !$result['upline_id']) {
				break; // Reached root
			}

			$currentId = $result['upline_id'];
			$level++;

			if ($level > 11) return 0; // Exceeded depth
		}

		return $level;
	}

//***********************************************
function addToSharingMatrix($user_id, $sponsor_id) {
    global $conn;

    try {
        // Step 0: Prevent re-entry
        $sql = "SELECT id FROM sharing_matrix WHERE user_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return [
           		'status' => 'error',
            	'message' => "User already exists in the matrix."
	        ];
        }

        // Step 1: Get sponsor's matrix entry ID
        $sql = "SELECT id FROM sharing_matrix WHERE user_id = ? ORDER BY id ASC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $sponsor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $sponsorEntry = $result->fetch_assoc();

        if (!$sponsorEntry) {
         	return [
           		'status' => 'error',
            	'message' => 'Sponsor has no matrix entry.'
	        ];
        }

        $rootId = $sponsorEntry['id'];

        // Step 2: Breadth-first search to find the next available position
        $queue = [$rootId];
        $available_upline_id = null;

        while (!empty($queue)) {
            $current_id = array_shift($queue);

            // Count direct downlines of this node
            $sql = "SELECT COUNT(*) AS count FROM sharing_matrix WHERE upline_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $current_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();

            if ((int)$res['count'] < 5) {
                $available_upline_id = $current_id;
                break;
            }

            // Add this node’s children to the queue
            $sql = "SELECT id FROM sharing_matrix WHERE upline_id = ? ORDER BY entry_position ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $current_id);
            $stmt->execute();
            $children = $stmt->get_result();
            while ($child = $children->fetch_assoc()) {
                $queue[] = $child['id'];
            }
        }

        if (!$available_upline_id) {
            return [
           		'status' => 'error',
            	'message' => "No available slot found in the matrix."
	        ];

        }

        // Step 3: Get entry position
        $sql = "SELECT COUNT(*) AS count FROM sharing_matrix WHERE upline_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $available_upline_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $entry_position = (int)$row['count'] + 1;

        // Step 4: Get the upline_user_id
        $sql = "SELECT user_id FROM sharing_matrix WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $available_upline_id);
        $stmt->execute();
        $uplineUser = $stmt->get_result()->fetch_assoc();
        $upline_user_id = $uplineUser['user_id'];

        // Step 5: Insert new matrix entry
        $sql = "INSERT INTO sharing_matrix (user_id, upline_id, upline_user_id, entry_position) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiii", $user_id, $available_upline_id, $upline_user_id, $entry_position);
        $stmt->execute();


        // // Update status to active
		$updateStmt = $conn->prepare("UPDATE users SET status = 'active', placement_id = ? WHERE id = ?");
    	$updateStmt->bind_param("ii",  $upline_user_id, $user_id);
    	$updateStmt->execute();

        return [
       		'status' => 'success',
        	'message' => "Successful!"
        ];

    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }
}


// Fetch donation amount from DB based on plan_type and level
function getDonationAmountByPlan($plan_type, $level) {
    global $conn;

    $sql = "SELECT amount FROM sharing_donation WHERE plan_type = ? AND level = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $plan_type, $level);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return $row ? $row['amount'] : 0;
}

function processDonation($user_id, $plan_type, $level,$nocommissions = 0, $is_admin_made = 0 ) {
    global $conn;


    $sql = "SELECT placement_id FROM users WHERE status <> 'deleted' and  id = $user_id";
    $result = $conn->query($sql);
    $result = $result->fetch_assoc();
    $placement_id = $result['placement_id'];
    if (!$placement_id) {
        return [
            'status' => 'error',
            'message' => 'You don\'t have a matrix placement yet. Please try again later.'
        ];
    }



    // Step 1: Get plan info (plan_id and amount)
    $sql = "SELECT id, amount FROM sharing_donation WHERE plan_type = ? AND level = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $plan_type, $level);
    $stmt->execute();
    $result = $stmt->get_result();
    $plan = $result->fetch_assoc();

    if (!$plan) {
        return [
            'status' => 'error',
            'message' => 'Donation plan not found.'
        ];
    }

    $plan_id = $plan['id'];
    $amount = $plan['amount'];

	$wallet_amount = GetRunningTotal_Ewallet($user_id);
	if ($wallet_amount < $amount && !$is_admin_made) {
        return [
            'status' => 'error',
            'message' => 'Your purchase could not be processed due to insufficient balance.'
        ];		
	}

    // Step 2: Check for existing completed donation
    $sql = "SELECT id FROM sharing_purchase WHERE user_id = ? AND plan_id = ? AND status = 'completed' AND paid=1 LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $plan_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return [
            'status' => 'error',
            'message' => 'You have already completed this donation.'
        ];
    }

    // Step 3: Save new donation to sharing_purchase
    $sql = "INSERT INTO sharing_purchase (user_id, plan_id, amount, created_at, status, is_admin_made,paid) 
            VALUES (?, ?, ?, NOW(), 'completed',$is_admin_made,1)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iid", $user_id, $plan_id, $amount);
    $stmt->execute();
    $ref_id = $stmt->insert_id;


 	// Insert Payment Transaction 
 	if (!$is_admin_made) {
	 	$payment_amount = $amount * (-1); 
	    $sql = "INSERT INTO ewallets (user_id, amount, plan_id, type, status, date_created,ref_id)
	            VALUES (?, ?, ?,  'payment', 'completed', NOW(),$ref_id)";
	    $stmt = $conn->prepare($sql);
	    $stmt->bind_param("idi", $user_id, $payment_amount, $plan_id);
	    $stmt->execute();
	    $transaction_id = $stmt->insert_id;
		UpdateRunningTotals_Ewallet($user_id,1);    
	}

    if (!$nocommissions) {
	    // Step 4: Distribute commission
// echo "distributeDonationCommission($user_id, $plan_type, $level, $amount);";
// exit; 
	    distributeDonationCommission($user_id, $plan_type, $level);


	    // Success response
	    return [
	        'status' => 'success',
	        'message' => 'Donation completed and commission distributed.'
	    ];

	} else {
	    // Success response
	    return [
	        'status' => 'success',
	        'message' => 'Donation completed and no commission distributed.'
	    ];

	}

}

function distributeDonationCommission($from_user_id, $plan_type, $donation_level) {
    global $conn;

    // Get the plan_id
    $sql = "SELECT id,amount FROM sharing_donation WHERE plan_type = ? AND level = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $plan_type, $donation_level);
    $stmt->execute();
    $result = $stmt->get_result();
    $plan = $result->fetch_assoc();

    if (!$plan) {
        return false;
    }

    $plan_id = $plan['id'];
    $plan_amount = $plan['amount'];
    $current_user_id = $from_user_id;
    $level = 1;

    while (true) {

        // Get upline_id from matrix
        $sql = "SELECT upline_id FROM sharing_matrix WHERE user_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $matrixRow = $result->fetch_assoc();

        if (!$matrixRow || !$matrixRow['upline_id']) {
        	// Before the root of the matrix. Commissions will go to root
        	break;
        }

        $upline_matrix_id = $matrixRow['upline_id'];

        // Get upline user_id from matrix ID
        $sql = "SELECT user_id FROM sharing_matrix WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $upline_matrix_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $upline = $result->fetch_assoc();

        if (!$upline || !$upline['user_id']) break;

        $upline_user_id = $upline['user_id'];

        // Jump to the right level in the matrix
        if ($level < $donation_level) {
            $current_user_id = $upline_user_id;
            $level++;
            continue;
        }


  		// Get the upline's rank
        $sql = "SELECT rank FROM users WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $upline_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $userRankRow = $result->fetch_assoc();
        $rank = "";
        if (!$userRankRow || !$userRankRow['rank']) {
		    $rank = "pioneer";
        } else {
        	$rank = strtolower($userRankRow['rank']);	
        }

  		// Get the upline's total payments
        $sql = "SELECT COALESCE(SUM(amount), 0) AS total FROM transactions WHERE user_id = ? AND status = 'completed' AND type = 'payment'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $upline_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $userPayment = $result->fetch_assoc();
        $totalpayment = $userPayment['total'];


        
        // Check if this upline is qualified
        $sql = "SELECT 1 FROM sharing_purchase 
                WHERE user_id = ? 
                AND plan_id = ? 
                AND status = 'completed'
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $upline_user_id, $plan_id);
        $stmt->execute();
        $hasDonated = $stmt->get_result()->num_rows > 0;

        //Special cases
		if ($rank == 'partner' && $totalpayment >= 50000 && $donation_level == 8) {
			$hasDonated = true; 
		} elseif ($rank == 'partner' && $totalpayment >= 20000 && $donation_level == 7) {
			$hasDonated = true; 
		} elseif ($rank == 'partner' && $totalpayment >= 5000 && $donation_level == 6) {
			$hasDonated = true; 
		} elseif ($rank == 'ambassador' && $totalpayment >= 5000000 && $donation_level == 11) {
			$hasDonated = true; 
		} elseif ($rank == 'ambassador' && $totalpayment >= 2000000 && $donation_level == 10) {
			$hasDonated = true; 
		} elseif ($rank == 'ambassador' && $totalpayment >= 1000000 && $donation_level == 9) {
			$hasDonated = true; 
		} 

		//Need to check if has donation, if none, then go up the the next level. 
        if (!$hasDonated) {
        	$current_user_id = $upline_user_id;
        	$level++;
            continue;
        }

        //*************************************************************
        // BY THIS TIME, THE $upline_user_id will be given commissions
        //*************************************************************

//ld( $upline_user_id."=".$rank."=".$hasDonated);

         // Commission logic by level and rank
        $commissionToUpline = 0.0;
        $commissionToRoot = 0.0;

        if ($donation_level <= 5) {
            $commissionToUpline =  $plan_amount;

        } elseif ($donation_level <= 8) {
            if ($rank === 'partner' || $rank === 'ambassador'  || $rank === 'admin') {
                $commissionToUpline =  $plan_amount * 0.10;
                $commissionToRoot =  $plan_amount * 0.90;
            } 
        } elseif ($donation_level <= 11) {
            if ($rank === 'ambassador' || $rank === 'admin') {
                $commissionToUpline =  $plan_amount * 0.10;
                $commissionToRoot =  $plan_amount * 0.90;
            }
        }

//ld( $upline_user_id."=".$rank."=".$commissionToUpline);

        // If commission goes to upline
        if ($commissionToUpline > 0) {

		    // Check if this upline is qualified
		    $sql = "SELECT id FROM sharing_purchase WHERE user_id = $from_user_id AND plan_id = $plan_id  LIMIT 1";
		    $result = $conn->query($sql);
		    $result = $result->fetch_assoc();
		    $purchase_id=0; 
			if ($result && isset($result['id'])) {
		        $purchase_id = $result['id'];
		    }


		    //Check if commission already exist
		    $sql = "SELECT count(0) as count FROM transactions WHERE user_id = $upline_user_id  AND status='completed' AND type='commission' AND from_id = $from_user_id AND plan_id = $plan_id";
		    $result = $conn->query($sql);
		    $result = $result->fetch_assoc();
		    $count = $result['count'];
		    if (!$count) {
	            $sql = "INSERT INTO transactions (user_id, amount, from_id, plan_id, type, status, date_created,ref_id)
	                    VALUES (?, ?, ?, ?, 'commission', 'completed', NOW(),$purchase_id)";
	            $stmt = $conn->prepare($sql);
	            $stmt->bind_param("idii", $upline_user_id, $commissionToUpline, $from_user_id, $plan_id);
	            $stmt->execute();
	            $transaction_id = $stmt->insert_id;
	            UpdateRunningTotals($upline_user_id, 1);

	            // Update sharing_purchase with commission info
	            $sql = "UPDATE sharing_purchase SET commission_to = ?, commission_id = ? 
	                    WHERE user_id = ? AND plan_id = ? AND status = 'completed' LIMIT 1";
	            $stmt = $conn->prepare($sql);
	            $stmt->bind_param("iiii", $upline_user_id, $transaction_id, $from_user_id, $plan_id);
	            $stmt->execute();	            
	        }

        }

        // If partial commission goes to root
        if ($commissionToRoot > 0) {

		    //Check if commission already exist
		    $sql = "SELECT count(0) as count FROM transactions WHERE user_id = 1  AND status='completed' AND type='commission' AND from_id = $from_user_id AND plan_id = $plan_id";
		    $result = $conn->query($sql);
		    $result = $result->fetch_assoc();
		    $count = $result['count'];
		    if (!$count) {
	            $sql = "INSERT INTO transactions (user_id, amount, from_id, plan_id, type, status, date_created,ref_id)
	                    VALUES (1, ?, ?, ?, 'commission', 'completed', NOW(),$purchase_id)";
	            $stmt = $conn->prepare($sql);
	            $stmt->bind_param("dii", $commissionToRoot, $from_user_id, $plan_id);
	            $stmt->execute();
	            UpdateRunningTotals(1, 1);
	        }
        }

        return true;
    }

    // Check if this upline is qualified
    $sql = "SELECT id FROM sharing_purchase WHERE user_id = $from_user_id AND plan_id = $plan_id LIMIT 1";
    $result = $conn->query($sql);
    $result = $result->fetch_assoc();
    $purchase_id=0; 
	if ($result && isset($result['id'])) {
        $purchase_id = $result['id'];
    }
    
    //Check if commission already exist
    $sql = "SELECT count(0) as count FROM transactions WHERE user_id = 1  AND status='completed' AND type='commission' AND from_id = $from_user_id AND plan_id = $plan_id";
    $result = $conn->query($sql);
    $result = $result->fetch_assoc();
    $count = $result['count'];

    if (!$count) {
	    // Fallback if no one qualified then commissions go to root
	    $sql = "INSERT INTO transactions (user_id, amount, from_id, plan_id, type, status, date_created, ref_id)
	            VALUES (1, ?, ?, ?, 'commission', 'completed', NOW(),$purchase_id)";
	    $stmt = $conn->prepare($sql);
	    $stmt->bind_param("dii", $plan_amount, $from_user_id, $plan_id);
	    $stmt->execute();
	    $transaction_id = $stmt->insert_id;
	    UpdateRunningTotals(1, 1);

	    // Update purchase with root as fallback recipient
	    $sql = "UPDATE sharing_purchase SET commission_to = 1, commission_id = $transaction_id WHERE id = $purchase_id LIMIT 1";
	    $stmt = $conn->prepare($sql);
	    $stmt->execute();

	    return true;
	} else {
		echo "No commission generated. Commission already given to the sponsor.";
		exit; 
	}
}


function distributeDonationCommission_new($from_user_id, $plan_type, $donation_level, $amount) {
    global $conn;

    // Get the plan_id
    $sql = "SELECT id FROM sharing_donation WHERE plan_type = ? AND level = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $plan_type, $donation_level);
    $stmt->execute();
    $result = $stmt->get_result();
    $plan = $result->fetch_assoc();

    if (!$plan) {
        return false;
    }

    $plan_id = $plan['id'];
    $current_user_id = $from_user_id;
    $level = 1;

    while ($level <= 11) {

        // Get upline_id from matrix
        $sql = "SELECT upline_id FROM sharing_matrix WHERE user_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $matrixRow = $result->fetch_assoc();

        if (!$matrixRow || !$matrixRow['upline_id']) break;

        $upline_matrix_id = $matrixRow['upline_id'];

        // Get upline user_id from matrix ID
        $sql = "SELECT user_id FROM sharing_matrix WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $upline_matrix_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $upline = $result->fetch_assoc();

        if (!$upline || !$upline['user_id']) break;

        $upline_user_id = $upline['user_id'];

        // Check if this upline is qualified
        $sql = "SELECT 1 FROM sharing_purchase 
                WHERE user_id = ? 
                AND plan_id = ? 
                AND status = 'completed'
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $upline_user_id, $plan_id);
        $stmt->execute();
        $hasDonated = $stmt->get_result()->num_rows > 0;

        if (!$hasDonated) {
            $current_user_id = $upline_user_id;
            $level++;
            continue;
        }

		//ld($upline_user_id);

        // Get the upline's rank
        $sql = "SELECT rank FROM users WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $upline_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $userRankRow = $result->fetch_assoc();

        if (!$userRankRow || !$userRankRow['rank']) {
            $current_user_id = $upline_user_id;
            $level++;
            continue;
        }

        $rank = strtolower($userRankRow['rank']);

         // Commission logic by level and rank
        $commissionToUpline = 0.0;
        $commissionToRoot = 0.0;

        if ($level <= 5) {
            $commissionToUpline = $amount;

        } elseif ($level <= 8) {
            if ($rank === 'partner' || $rank === 'ambassador') {
                $commissionToUpline = $amount * 0.10;
                $commissionToRoot = $amount * 0.90;
            }
        } elseif ($level <= 11) {
            if ($rank === 'ambassador') {
                $commissionToUpline = $amount * 0.10;
                $commissionToRoot = $amount * 0.90;
            }
        }

        // If commission goes to upline
        if ($commissionToUpline > 0) {
            $sql = "INSERT INTO transactions (user_id, amount, from_id, plan_id, type, status, date_created)
                    VALUES (?, ?, ?, ?, 'commission', 'completed', NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("idii", $upline_user_id, $commissionToUpline, $from_user_id, $plan_id);
            $stmt->execute();
            $transaction_id = $stmt->insert_id;
            UpdateRunningTotals($upline_user_id, 1);

            // Update sharing_purchase with commission info
            $sql = "UPDATE sharing_purchase SET commission_to = ?, commission_id = ? 
                    WHERE user_id = ? AND plan_id = ? AND status = 'completed' LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiii", $upline_user_id, $transaction_id, $from_user_id, $plan_id);
            $stmt->execute();
        }

        // If partial commission goes to root
        if ($commissionToRoot > 0) {
            $sql = "INSERT INTO transactions (user_id, amount, from_id, plan_id, type, status, date_created)
                    VALUES (1, ?, ?, ?, 'commission', 'completed', NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dii", $commissionToRoot, $from_user_id, $plan_id);
            $stmt->execute();
            UpdateRunningTotals(1, 1);
        }

        return true;
    }

    // Fallback if no one qualified in the 11 levels
    $sql = "INSERT INTO transactions (user_id, amount, from_id, plan_id, type, status, date_created)
            VALUES (1, ?, ?, ?, 'commission', 'completed', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("dii", $amount, $from_user_id, $plan_id);
    $stmt->execute();
    $transaction_id = $stmt->insert_id;
    UpdateRunningTotals(1, 1);

    // Update purchase with root as fallback recipient
    $sql = "UPDATE sharing_purchase SET commission_to = 1, commission_id = ? 
            WHERE user_id = ? AND plan_id = ? AND status = 'completed' LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $transaction_id, $from_user_id, $plan_id);
    $stmt->execute();

    return true;
}


function distributeDonationCommission_old($from_user_id, $plan_type, $donation_level, $amount) {
    global $conn;

    // Get the plan_id
    $sql = "SELECT id FROM sharing_donation WHERE plan_type = ? AND level = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $plan_type, $donation_level);
    $stmt->execute();
    $result = $stmt->get_result();
    $plan = $result->fetch_assoc();

    if (!$plan) {
        return false;
    }

    $plan_id = $plan['id'];
    $current_user_id = $from_user_id;
    $level = 1;

    while ($level <= 11) {
        // Get upline_id from matrix
        $sql = "SELECT upline_id FROM sharing_matrix WHERE user_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $matrixRow = $result->fetch_assoc();

        if (!$matrixRow || !$matrixRow['upline_id']) break;

        $upline_matrix_id = $matrixRow['upline_id'];

        // Get upline user_id from matrix ID
        $sql = "SELECT user_id FROM sharing_matrix WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $upline_matrix_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $upline = $result->fetch_assoc();

        if (!$upline || !$upline['user_id']) break;

        $upline_user_id = $upline['user_id'];

        // Check if this upline is qualified
        $sql = "SELECT 1 FROM sharing_purchase 
                WHERE user_id = ? 
                AND plan_id = ? 
                AND status = 'completed'
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $upline_user_id, $plan_id);
        $stmt->execute();
        $hasDonated = $stmt->get_result()->num_rows > 0;

        if ($hasDonated) {
            // Insert commission
            $sql = "INSERT INTO transactions (user_id, amount, from_id, plan_id, type, status, date_created)
                    VALUES (?, ?, ?, ?, 'commission', 'completed', NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("idii", $upline_user_id, $amount, $from_user_id, $plan_id);
            $stmt->execute();
            $transaction_id = $stmt->insert_id;
			UpdateRunningTotals($upline_user_id,1);

            // Update sharing_purchase with commission info
            $sql = "UPDATE sharing_purchase SET commission_to = ?, commission_id = ? 
                    WHERE user_id = ? AND plan_id = ? AND status = 'completed' LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiii", $upline_user_id, $transaction_id, $from_user_id, $plan_id);
            $stmt->execute();

            return true;
        }

        $current_user_id = $upline_user_id;
        $level++;
    }

    // Fallback to root (user_id = 1)
    $sql = "INSERT INTO transactions (user_id, amount, from_id, plan_id, type, status, date_created)
            VALUES (1, ?, ?, ?, 'commission', 'completed', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("dii", $amount, $from_user_id, $plan_id);
    $stmt->execute();
    $transaction_id = $stmt->insert_id;
	UpdateRunningTotals(1,1);

    // Update purchase with root as fallback recipient
    $sql = "UPDATE sharing_purchase SET commission_to = 1, commission_id = ? 
            WHERE user_id = ? AND plan_id = ? AND status = 'completed' LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $transaction_id, $from_user_id, $plan_id);
    $stmt->execute();

    return true;
}




//***********************************************


	function addToMatrix_5x11_old($user_id) {
	    global $conn;

	    // STEP 0: Get sponsor (upline_user_id)
	    $result = $conn->query("SELECT sponsor_id FROM users WHERE id = $user_id AND status = 'active'");
	    $sponsor = $result->fetch_assoc();
	    if (!$sponsor) return false;

	    $upline_user_id = $sponsor['sponsor_id'];

	    // STEP 1: Get sponsor’s matrix entries (ordered by entry_number)
	    $stmt = $conn->prepare("SELECT * FROM matrix_5x11 WHERE user_id = ? AND done = 0 ORDER BY entry_number ASC");
	    $stmt->bind_param("i", $upline_user_id);
	    $stmt->execute();
	    $entries = $stmt->get_result();

	    $sponsorMatrix = null;

	    // STEP 2: Find the first matrix entry of the sponsor that has less than 5 direct placements
	    while ($entry = $entries->fetch_assoc()) {
	        $check = $conn->prepare("SELECT COUNT(*) AS count FROM matrix_5x11 WHERE upline_id = ?");
	        $check->bind_param("i", $entry['id']);
	        $check->execute();
	        $countResult = $check->get_result()->fetch_assoc();

	        if ($countResult['count'] < 5) { 
	            $sponsorMatrix = $entry;
	            break;
	        }
	    }
	     	    
	    // STEP 3: If sponsor has no valid matrix entry → check uplines
	    if (!$sponsorMatrix) {
	        $sponsorMatrix = findNearestUplineMatrix($user_id);
	        if (!$sponsorMatrix) return false;
	    }

	    // STEP 4: Get entry info
	    $upline_id = $sponsorMatrix['id']; // matrix ID
	    $entry_number = getNextEntryNumber($user_id); // support multiple matrix entries
	    $level = calculateLevel($upline_id) + 1;

	    if ($level > 11) return false;

	    // STEP 5: Get the correct entry_position (1 to 5 under this matrix entry)
	    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM matrix_5x11 WHERE upline_id = ?");
	    $stmt->bind_param("i", $upline_id);
	    $stmt->execute();
	    $res = $stmt->get_result()->fetch_assoc();
	    $entry_position = $res['count'] + 1;

	    // STEP 6: Insert new matrix entry
	    $stmt = $conn->prepare("
	        INSERT INTO matrix_5x11 (
	            user_id, upline_id, upline_user_id, level,
	            entry_number, entry_position, done
	        ) VALUES (?, ?, ?, ?, ?, ?, 0)
	    ");
	    $stmt->bind_param(
	        "iiiiii",
	        $user_id,
	        $upline_id,
	        $upline_user_id,
	        $level,
	        $entry_number,
	        $entry_position
	    );
	    $inserted = $stmt->execute();

	    // STEP 7: Update "done" status if matrix is full
	    if ($inserted) {
	        updateMatrixDoneFlag($upline_user_id, $sponsorMatrix['entry_number']);
	    }

	    return $inserted;
	}

	function getNextEntryNumber($user_id) {
	    global $conn;
	    $stmt = $conn->prepare("SELECT MAX(entry_number) AS max_entry FROM matrix_5x11 WHERE user_id = ?");
	    $stmt->bind_param("i", $user_id);
	    $stmt->execute();
	    $res = $stmt->get_result()->fetch_assoc();
	    return ($res['max_entry'] ?? 0) + 1;
	}

	function findNearestUplineMatrix($start_user_id) {
	    global $conn;

	    $queue = [];

	    // Step 1: Start with the sponsor's matrix entries
	    $stmt = $conn->prepare("SELECT * FROM matrix_5x11 WHERE user_id = ? ORDER BY entry_number ASC");
	    $stmt->bind_param("i", $start_user_id);
	    $stmt->execute();
	    $result = $stmt->get_result();

	    while ($row = $result->fetch_assoc()) {
	        $queue[] = $row;
	    }

	    // Step 2: Begin BFS traversal
	    while (!empty($queue)) {
	        $current = array_shift($queue); // pop first in queue

	        // Check how many children this matrix node has
	        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM matrix_5x11 WHERE upline_id = ?");
	        $stmt->bind_param("i", $current['id']);
	        $stmt->execute();
	        $count = $stmt->get_result()->fetch_assoc();

	        if ($count['cnt'] < 5) {
	            return $current; // Found available placement
	        }

	        // Otherwise, add children to the queue for further searching
	        $stmt = $conn->prepare("SELECT * FROM matrix_5x11 WHERE upline_id = ? ORDER BY id ASC");
	        $stmt->bind_param("i", $current['id']);
	        $stmt->execute();
	        $children = $stmt->get_result();

	        while ($child = $children->fetch_assoc()) {
	            $queue[] = $child;
	        }
	    }

	    // No open placement found
	    return null;
	}

	function updateMatrixDoneFlag($user_id, $entry_number) {
	    global $conn;
	    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM matrix_5x11 WHERE upline_id = ? AND entry_number = ?");
	    $stmt->bind_param("ii", $user_id, $entry_number);
	    $stmt->execute();
	    $res = $stmt->get_result()->fetch_assoc();

	    if ($res['count'] >= 5) {
	        $stmt = $conn->prepare("UPDATE matrix_5x11 SET done = 1 WHERE user_id = ? AND entry_number = ?");
	        $stmt->bind_param("ii", $user_id, $entry_number);
	        $stmt->execute();
	    }
	}

	function calculateLevel($user_id) {
		global $conn; 
	    $level = 1;
	    $current = $user_id;

	    while (true) {
	        $stmt = $conn->prepare("SELECT upline_id FROM matrix_5x11 WHERE user_id = ?");
	        $stmt->bind_param("i", $current);
	        $stmt->execute();
	        $result = $stmt->get_result()->fetch_assoc();

	        if (!$result || !$result['upline_id']) {
	            break; // Reached the top/root
	        }

	        $current = $result['upline_id'];
	        $level++;

	        if ($level > 11) {
	            return 11; // Cap level to avoid overflow
	        }
	    }

	    return $level;
	}

?>