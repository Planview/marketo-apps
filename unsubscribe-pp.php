<?php
require_once('./credentials.php');
$emailMessage = '';
$emailResults = 'Success';
$continue = true;
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Marketo Projectplace Unsubscribe Script Results for <?php echo date("l, d M Y");?></title>
  </head>
<body>
<?php 
// 01 ///////////////////////////////////////////////////////////////////////////////////////////
$emailMessage .= '<h1 style="margin-bottom:0;">Marketo Projectplace Unsubscribe Script Results for ' . date("l, d M Y"). '</h1><p style="margin-top:0;"><strong>(A REST Application)</strong></p>';
$emailMessage .= '<h2>1. Get Projectplace Unsubscribe Emails from Planview List</h2>';
 
$leads = new LeadsByList($GLOBALS['pvMarketoRestHost'],$GLOBALS['pvMarketoRestClientId'],$GLOBALS['pvMarketoMRestClientSecret']);
$leads->listId = $GLOBALS['pvMarketoMRestListId'];
$leads->fields = array("email");

$leadsByListResponse = $leads->getData();
$leadsByListResponseDecoded = json_decode($leadsByListResponse, TRUE);
$leadEmails = $leadsByListResponseDecoded['result'];

$unsubscribeLeadEmails = array();
$unsubscribeLeadEmailsString = '';
$removeLeadIds = array();
$counter = 0;

if ($leadsByListResponseDecoded['success']) { //if successful
	if (is_null($leadEmails[0])) {
		$emailMessage .= '<p>There were no Projectplace unsubscribe emails in the Planview list.</p>';
		$emailMessage .= '<ul><li>Which means we go directly to #6 and send the email</li></ul>';
		$continue = false; //no need to run anything else -- just send the email
	} else {
		$emailMessage .= '<p>The following unsubscribe emails were found in the Planview list:</p><ul>';
		foreach ($leadEmails as $leadEmail) { 
			$unsubscribeLeadEmails[] = $leadEmail["email"];
			$removeLeadIds[] = $leadEmail["id"];
			if ($counter>0) $unsubscribeLeadEmailsString = $unsubscribeLeadEmailsString . ',';
			$unsubscribeLeadEmailsString = $unsubscribeLeadEmailsString . $leadEmail["email"];
			$emailMessage .= '<li>' . $leadEmail["email"] . '</li>';
			$counter++;
		}
		$emailMessage .= '</ul>';
	}
} else { //create error message
	$emailMessage .= '<p><b>ERROR:</b> 1. Get Projectplace Unsubscribe Emails from Planview List</p><p>Error message:</p><ul>';

	foreach($leadsByListResponseDecoded['errors'][0] as $key=>$value) {
		$emailMessage .= '<li><b>' . $key . ':</b> ' . $value . '</li>';
	}
	$emailMessage .= '</ul>';
	$emailResults = 'ERROR';
	$continue = false; //no need to run anything else -- just send the email
}


// 02 ///////////////////////////////////////////////////////////////////////////////////////////
if ($continue) {
	$emailMessage .= '<h2>2. Update Existing Projectplace Lead Records: unsubscribed, unsubscribedReason</h2>';

// 02 a //////////////////////////////////////////////////////////////////////////////////////////
	$emailMessage .= '<h3>a. Get Projectplace Leads</h3>';
	
	$leads2 = new MultipleLeads($GLOBALS['ppMarketoRestHost'],$GLOBALS['ppMarketoRestClientId'],$GLOBALS['ppMarketoMRestClientSecret']);
	$leads2->filterType = "email";
	$leads2->filterValues = array($unsubscribeLeadEmailsString);
	$leads2->fields = array("id,email,unsubscribed,unsubscribedReason");
	
	$leadsToUnsubscribe = $leads2->getData();
	$leadsToUnsubscribeDecoded = json_decode($leadsToUnsubscribe, TRUE);
	
	if ($leadsToUnsubscribeDecoded['success']) { //if successful
		if (empty($leadsToUnsubscribeDecoded['result'])) { //if no results are returned
			$emailMessage .= '<p>There were no Projectplace unsubscribe emails found in the Projectplace lead database.</p>';
			$emailMessage .= '<ul><li>Which means we skip #2b and #3 and go directly to #4</li></ul>';
		} else { 
			$emailMessage .= '<p>Get Projectplace Leads was successful.</p>';
			$emailMessage .= '<pre>' . print_r($leadsToUnsubscribeDecoded['result'], true) . '</pre>';
			
// 02 b //////////////////////////////////////////////////////////////////////////////////////////
			$emailMessage .= '<h3>b. Update Projectplace Leads</h3>';
	
			$emailMessage .= '<p>The following unsubscribe emails were found in the Projectplace lead database:</p><ul>';
	
			$leadsToUpdate = array();
			
			foreach ($leadsToUnsubscribeDecoded['result'] as $unsubscribeLeadFound) { 
				$emailMessage .= '<li>Email: ' . $unsubscribeLeadFound['email'] . ', ID: ' . $unsubscribeLeadFound['id'] . '</li>';
				$leadClass = new stdClass();
				$leadClass->email = $unsubscribeLeadFound['email'];
				$leadClass->unsubscribed = true; 
				$leadClass->id = $unsubscribeLeadFound['id'];
				if (is_null($unsubscribeLeadFound['unsubscribedReason'])) {
					$leadClass->unsubscribedReason = 'Unsubscribed to Projecplace emails via Planview "Email Preferences" subscription form.';
				} else {
					$leadClass->unsubscribedReason = $unsubscribeLeadFound['unsubscribedReason'] . ' Also unsubscribed to Projecplace emails via Planview "Email Preferences" subscription form on ' . date("d M Y") . '.';
				}
				$leadsToUpdate[] = $leadClass;
			}
			$emailMessage .= '</ul>';
				
			$upsert = new UpsertLeads($GLOBALS['ppMarketoRestHost'],$GLOBALS['ppMarketoRestClientId'],$GLOBALS['ppMarketoMRestClientSecret']);
			$upsert->action = 'updateOnly';
			$upsert->input = $leadsToUpdate;
			$upsert->lookupField = 'id';
			$upsertResults = $upsert->postData();
			$upsertResultsDecoded = json_decode($upsertResults, TRUE);
	
			if ($upsertResultsDecoded['success']) { //if successful
				$emailMessage .= '<p>Update Projectplace Leads was successful.</p>';
				$emailMessage .= '<pre>' . print_r($upsertResultsDecoded, true) . '</pre>';


// 03 ///////////////////////////////////////////////////////////////////////////////////////////	
				$emailMessage .= '<h2>3. CONFIRM: Get Projectplace Leads That Were Updated</h2>';
				
				$leads3 = new MultipleLeads($GLOBALS['ppMarketoRestHost'],$GLOBALS['ppMarketoRestClientId'],$GLOBALS['ppMarketoMRestClientSecret']);
				$leads3->filterType = "email";
				$leads3->filterValues = array($unsubscribeLeadEmailsString);
				$leads3->fields = array("email,firstName,lastName,unsubscribed,unsubscribedReason");
			
				$emailMessage .= '<p>Confirmation Results:</p>';
				$leadsResults3 = $leads3->getData();
				$leadsResultsDecoded3 = json_decode($leadsResults3, TRUE);
				$emailMessage .= '<pre>' . print_r($leadsResultsDecoded3, true) . '</pre>';

			} else { //create error message
				$emailMessage .= '<p><b>ERROR:</b> 2. b. Update Projectplace Leads</p><p>Error message:</p><ul>';
				
				foreach($upsertResultsDecoded['errors'][0] as $key=>$value) {
					$emailMessage .= '<li><b>' . $key . ':</b> ' . $value . '</li>';
				}
				$emailMessage .= '</ul>';
				$emailResults = 'ERROR';
				$continue = false; //no need to run anything else -- just send the email
			}
		}
	} else { //create error message
		$emailMessage .= '<p><b>ERROR:</b> 2. a. Get Projectplace Leads</p><p>Error message:</p><ul>';
	
		foreach($leadsToUnsubscribeDecoded['errors'][0] as $key=>$value) {
			$emailMessage .= '<li><b>' . $key . ':</b> ' . $value . '</li>';
		}
		$emailMessage .= '</ul>';
		$emailResults = 'ERROR';
		$continue = false; //no need to run anything else -- just send the email
	}
} // end if (continue)

	
// 04 ///////////////////////////////////////////////////////////////////////////////////////////

if ($continue) {
	$emailMessage .= '<h2>4. Remove Leads Form Planview List</h2>';
	 
	$removeFromList = new RemoveFromList($GLOBALS['pvMarketoRestHost'],$GLOBALS['pvMarketoRestClientId'],$GLOBALS['pvMarketoMRestClientSecret']);
	$removeFromList->listId = $GLOBALS['pvMarketoMRestListId'];
	$removeFromList->leadIds = $removeLeadIds;
	$removeFromListResults = $removeFromList->postData();
	$removeFromListResultsDecoded = json_decode($removeFromListResults, TRUE);
	
	if ($removeFromListResultsDecoded['success']) { //if successful
		$emailMessage .= '<p>Remove Leads Form Planview List was successful.</p>';
		$emailMessage .= '<pre>' . print_r($removeFromListResultsDecoded, true) . '</pre>';
	} else { //create error message
		$emailMessage .= '<p><b>ERROR:</b> 4. Remove Leads Form Planview List</p><p>Error message:</p><ul>';
	
		foreach($removeFromListResultsDecoded['errors'][0] as $key=>$value) {
			$emailMessage .= '<li><b>' . $key . ':</b> ' . $value . '</li>';
		}
		$emailMessage .= '</ul>';
		$emailResults = 'ERROR';
		$continue = false; //no need to run anything else -- just send the email
	}
} // end if (continue)


// 05 ///////////////////////////////////////////////////////////////////////////////////////////

if ($continue) {
	$emailMessage .= '<h2>5. CONFIRM: Get Leads from Planview List</h2>';
	
	$leads5 = new LeadsByList($GLOBALS['pvMarketoRestHost'],$GLOBALS['pvMarketoRestClientId'],$GLOBALS['pvMarketoMRestClientSecret']);
	$leads5->listId = $GLOBALS['pvMarketoMRestListId'];
	$leads5->fields = array("email");
	$leadsResults5 = $leads5->getData();
	$leadsResultsDecoded5 = json_decode($leadsResults5, TRUE);

	$emailMessage .= '<p>Confirmation Results:</p>';
	$emailMessage .= '<pre>' . print_r($leadsResultsDecoded5, true) . '</pre>';
} // end if (continue)


// 06 ///////////////////////////////////////////////////////////////////////////////////////////

if ( emailResults($emailResults . ': ' . date("d M Y") . ' Projectplace Unsubscribe Results', $emailMessage, date("l, d/m/Y")) ) {
	$emailMessage .= '<h2>6. Email Results</h2><p>Email sent.</p>';
} else { 
	$emailMessage .='<h2>6. Email Results</h2><p>There was an issue sending the email.</p>';
}

echo $emailMessage;



// functions ////////////////////////////////////////////////////////////////////////////////////

function emailResults($emailSubject, $emailBody, $emailDate) {
	$to = "webmaster@planview.com";
	$subject = $emailSubject . " for "  . $emailDate;
	
	$message = "
	<html>
	<head>
	<title>Access to the Planview Product Demo site</title>
	</head>
	<body style=\"font-family: 'Avenir W01',Arial,sans-serif;\">"
	. $emailBody .
	"</body>
	</html>
	";
	
	// Always set content-type when sending HTML email
	$headers = "MIME-Version: 1.0" . "\r\n";
	$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
	
	// More headers
	$headers .= 'From: <webmaster@planview.com>' . "\r\n";
	// add CC emails here
	//$headers .= 'Cc: additional_email@planview.com' . "\r\n";
	
	return mail($to,$subject,$message,$headers);
}

// classes //////////////////////////////////////////////////////////////////////////////////////

class LeadsByList{ 
	protected $host = "";
	protected $clientId = "";
	protected $clientSecret = "";

    public function __construct($hostValue,$clientIdValue,$clientSecretValue) {
        $this->host = $hostValue;
		$this->clientId = $clientIdValue;
		$this->clientSecret = $clientSecretValue;
    }

	public $listId;//id of list to retrieve leads from
	public $fields;//one or more fields to return
	public $batchSize; //max 300 default 300
	public $nextPageToken;//token returned from previous call for paging
	
	public function getData(){
		$url = $this->host . "/rest/v1/list/" . $this->listId . "/leads.json?access_token=" . $this->getToken();
		if (isset($this->fields)){
			$url = $url . "&fields=" . $this::csvString($this->fields);
		}
		if (isset($this->batchSize)){
			$url = $url . "&batchSize=" . $this->batchSize;
		}
		if (isset($this->nextPageToken)){
			$url = $url . "&nextPageToken=" . $this->fields;
		}
		$ch = curl_init($url);
		curl_setopt($ch,  CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json',));
		$response = curl_exec($ch);
		return $response;
	}
	
	private function getToken(){
		$ch = curl_init($this->host . "/identity/oauth/token?grant_type=client_credentials&client_id=" . $this->clientId . "&client_secret=" . $this->clientSecret);
		curl_setopt($ch,  CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json',));
		$response = json_decode(curl_exec($ch));
		curl_close($ch);
		$token = $response->access_token;
		return $token;
	}
	private static function csvString($fields){
		$csvString = "";
		$i = 0;
		foreach($fields as $field){
			if ($i > 0){
				$csvString = $csvString . "," . $field;
			}elseif ($i === 0){
				$csvString = $field;
			}
		}
		return $csvString;
	}
}

/////////////////////////////////////////////////////////////////////////////////////////////

class UpsertLeads{
	protected $host = "";
	protected $clientId = "";
	protected $clientSecret = "";

    public function __construct($hostValue,$clientIdValue,$clientSecretValue) {
        $this->host = $hostValue;
		$this->clientId = $clientIdValue;
		$this->clientSecret = $clientSecretValue;
    }

	public $input; //an array of lead records as objects
	public $lookupField; //field used for deduplication
	public $action; //operation type, createOnly, updateOnly, createOrUpdate, createDuplicate
	
	public function postData(){
		$url = $this->host . "/rest/v1/leads.json?access_token=" . $this->getToken();
		$ch = curl_init($url);
		$requestBody = $this->bodyBuilder();
		//debug
		//print_r($requestBody);
		curl_setopt($ch,  CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json','Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
		curl_getinfo($ch);
		$response = curl_exec($ch);
		return $response;
	}
	
	private function getToken(){
		$ch = curl_init($this->host . "/identity/oauth/token?grant_type=client_credentials&client_id=" . $this->clientId . "&client_secret=" . $this->clientSecret);
		curl_setopt($ch,  CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json',));
		$response = json_decode(curl_exec($ch));
		curl_close($ch);
		$token = $response->access_token;
		return $token;
	}
	private function bodyBuilder(){
		$body = new stdClass();
		if (isset($this->action)){
			$body->action = $this->action;
		}
		if (isset($this->lookupField)){
			$body->lookupField = $this->lookupField;
		}
		$body->input = $this->input;
		$json = json_encode($body);
		return $json;
	}
	private static function csvString($fields){
		$csvString = "";
		$i = 0;
		foreach($fields as $field){
			if ($i > 0){
				$csvString = $csvString . "," . $field;
			}elseif ($i === 0){
				$csvString = $field;
			}
		}
		return $csvString;
	}
}

/////////////////////////////////////////////////////////////////////////////////////////////

class MultipleLeads{
	protected $host = "";
	protected $clientId = "";
	protected $clientSecret = "";

    public function __construct($hostValue,$clientIdValue,$clientSecretValue) {
        $this->host = $hostValue;
		$this->clientId = $clientIdValue;
		$this->clientSecret = $clientSecretValue;
    }

	public $filterType; //field to filter off of, required
	public $filterValues; //one or more values for filter, required
	public $fields;//one or more fields to return
	public $batchSize;
	public $nextPageToken;//token returned from previous call for paging

	public function getData(){
		$url = $this->host . "/rest/v1/leads.json?access_token=" . $this->getToken()
						. "&filterType=" . $this->filterType . "&filterValues=" . $this::csvString($this->filterValues);
		
		if (isset($this->batchSize)){
			$url = $url . "&batchSize=" . $this->batchSize;
		}
		if (isset($this->nextPageToken)){
			$url = $url . "&nextPageToken=" . $this->nextPageToken;
		}
		if(isset($this->fields)){
			$url = $url . "&fields=" . $this::csvString($this->fields);
		}
		
		//debug
		//echo '<p>url after = ' . $url . '</p>';
		
		$ch = curl_init($url);
		curl_setopt($ch,  CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json',));
		$response = curl_exec($ch);
		return $response;
	}

	private function getToken(){
		$ch = curl_init($this->host . "/identity/oauth/token?grant_type=client_credentials&client_id=" . $this->clientId . "&client_secret=" . $this->clientSecret);
		curl_setopt($ch,  CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json',));
		$response = json_decode(curl_exec($ch));
		curl_close($ch);
		$token = $response->access_token;
		return $token;
	}
	private static function csvString($fields){
		$csvString = "";
		$i = 0;
		foreach($fields as $field){
			if ($i > 0){
				$csvString = $csvString . "," . $field;
			}elseif ($i === 0){
				$csvString = $field;
			}	
		}
		//debug
		//echo '<p>csvString = ' . $csvString . '</p>';
		return $csvString;
	}
}

/////////////////////////////////////////////////////////////////////////////////////////////

class RemoveFromList{
	protected $host = "";
	protected $clientId = "";
	protected $clientSecret = "";

    public function __construct($hostValue,$clientIdValue,$clientSecretValue) {
        $this->host = $hostValue;
		$this->clientId = $clientIdValue;
		$this->clientSecret = $clientSecretValue;
    }
	
	public $listId;//id of list to add to
	public $leadIds;//array of lead ids to add to list
	
	public function postData(){
		$url = $this->host . "/rest/v1/lists/" . $this->listId . "/leads.json?access_token=" . $this->getToken();
		$ch = curl_init($url);
		$requestBody = $this->bodyBuilder();
		curl_setopt($ch,  CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json','Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
		curl_getinfo($ch);
		$response = curl_exec($ch);
		return $response;
	}
	private function bodyBuilder(){
		$array = [];
		foreach($this->leadIds as $lead){
			$member = new stdClass();
			$member->id = $lead;
			array_push($array, $member);
		}
		$body = new stdClass();
		$body->input = $array;
		$json = json_encode($body);
		return $json;
	}
	private function getToken(){
		$ch = curl_init($this->host . "/identity/oauth/token?grant_type=client_credentials&client_id=" . $this->clientId . "&client_secret=" . $this->clientSecret);
		curl_setopt($ch,  CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json',));
		$response = json_decode(curl_exec($ch));
		curl_close($ch);
		$token = $response->access_token;
		return $token;
	}
}
?>

</body>
</html>
