<?php

/**
* Class CtCt: Define the interface between Constant Contact service (newsletter supcription) and the main web application
* Created at 11/06/2012
*
* @author William Palomino
* @version 1.0
*/

class ConstantContact 
{
   private $username = '';   // Constant Contact API key, obtain one at http://developer.constantcontact.com
   private $apikey = '';     // Consumer secret is issued with an apikey
   private $apisecret = '';
   private $redirectURL = ''; // Redirect URL (set when creating API key), this must match up; otherwise, it causes an error
 
   private $code = '';
   private $list_id = 1;      // id of the subscription list to be used
   private $token = '';       // provided by constant contact after first request
   
   function __construct($user,$api,$secret,$vurl,$list = 1) 
   {
      $this->username = $user;
      $this->apikey = $api;
      $this->apisecret = $secret;
      $this->redirectURL = $vurl;
      $this->list_id = $list;
   }
   
   private function authorisationRequest() 
   {
      $url = 'https://oauth2.constantcontact.com/oauth2/oauth/siteowner/authorize?';
      $url.='response_type=code';
      $url.='&client_id='.$this->apikey;
      $url.='&redirect_uri='.$this->redirectURL;
      
      $response = $this->makeRequest($url);
      echo $response;
      die($response);
      //now wait for redirectURL to be called
   }
   
   public function redirectURLCallback($params) 
   {
      // code     the unique code generated for this request - to be used in the token call later in the flow.
      // username the name of the Constant Contact user that is authenticating.
      if ($params['username']==$this->username) {
         $this->code = $params['code'];
         $this->tokenRequest();
         $this->addContacts();
      }
      
   }
   
   private function tokenRequest() 
   {
      $url='https://oauth2.constantcontact.com/oauth2/oauth/token?';
      $purl='grant_type=authorization_code';
      $purl.='&client_id='.urlencode($this->apikey);
      $purl.='&client_secret='.urlencode($this->apisecret);
      $purl.='&code='.urlencode($this->code);
      $purl.='&redirect_uri='.urlencode($this->redirectURL);
      mail('wr.palomino@ddns.com.au','constantcontact',$purl."\r\n".print_r($_GET,true));
      $response = $this->makeRequest($url.$purl,$purl);
      
      /* sample of the content exepcted
      JSON response
      {
       "access_token":"the_token",
       "expires_in":315359999,
       "token_type":"Bearer"
      } */
      
      die($response.' '.$purl);
      $resp = json_decode($response,true);
      $token = $resp['access_token'];
      
      $db = Doctrine_Manager::getInstance()->getCurrentConnection(); 
      //delete any old ones
      $query = $db->prepare('DELETE FROM ctct_email_cache WHERE email LIKE :token;');
      $query->execute(array('token' => 'token:%'));

      //now save the new token
      $query = $db->prepare('INSERT INTO ctct_email_cache (:token);');
      $query->execute(array('token' => 'token:'.$token));

      $this->token=$token;
      return $token;
   }
   
   public function queueContact($email) 
   {
      $db = Doctrine_Manager::getInstance()->getCurrentConnection(); 
      $query = $db->prepare('INSERT INTO ctct_email_cache VALUES(:email);');
      $query->execute(array('email' => $email));
   }
   
   public function addContacts() 
   {
      if ($this->token=='') {  //generate an auth callback, so this function will be called later when CTCT calls us back on redirectURL()
         $this->authorisationRequest();
      } 
      else {
         $url='https://api.constantcontact.com/ws/customers/';
         $url.=$this->username.'/';
         $url.='contacts?access_token='.$token;
         
         $request='<entry xmlns="http://www.w3.org/2005/Atom">
  <title type="text"> </title>
  <updated>2008-07-23T14:21:06.407Z</updated>
  <author></author>
  <id>data:,none</id>
  <summary type="text">Contact</summary>
  <content type="application/vnd.ctct+xml">';
  
         $db = Doctrine_Manager::getInstance()->getCurrentConnection(); 
         
         $query = $db->prepare('SELECT email FROM ctct_email_cache WHERE email NOT LIKE :token;');
         $r=$query->fetchAssoc(array('token' => 'token:%'));
         foreach ($r as $row) {
            $e=$row['email'];
            $e=str_replace('<','',$e);
            $e=str_replace('>','',$e);
            $request.='    <Contact xmlns="http://ws.constantcontact.com/ns/1.0/">
         <EmailAddress>'.$e.'</EmailAddress>
         <OptInSource>ACTION_BY_CONTACT</OptInSource>
         <ContactLists>
           <ContactList id="http://api.constantcontact.com/ws/customers/'.$this->username.'/lists/'.$this->list_id.'" />
         </ContactLists>
       </Contact>';
            $del.="'".mysql_real_escape_string($e)."',";
         }
         if (strlen($del)>0) {
            $del=substr($del,0,-1); //remove trailing comma
             //delete old ones
             $db = Doctrine_Manager::getInstance()->getCurrentConnection(); 
             $query = $db->prepare('DELETE FROM ctct_email_cache WHERE email IN (:del);');
             $query->execute(array('del' => $del));
         }
         $request.='  </content>
</entry>';
         $response = makeRequest($url,$request);
      }
   }
   
   
   private function getToken() 
   {
      $db = Doctrine_Manager::getInstance()->getCurrentConnection(); 
         
      $query = $db->prepare('SELECT email FROM ctct_email_cache WHERE email LIKE :token LIMIT 1;');
      $r=$query->fetchAssoc(array('token' => 'token:%'));
      if (count($r)<1) return $this->tokenRequest();
      
      return substr($r[0]['email'],6);
   }
   
   
   private function makeRequest($url,$request = '',$post = 1) 
   {
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);      
      $xmlResponse = curl_exec($ch);

      print_r(curl_getinfo($ch));
      
      if ( (curl_errno($ch) == CURLE_OK)&&($xmlResponse !== false) ) {
         return $xmlResponse;
      }
      if ($xmlResponse !== false) {
        return '';
      }
      die(curl_error($ch));
   }
}



/**
* Class MyConstantContactDataStore: Used to add and lookup users in the local Datastore
* Created at 11/06/2012
*
* @author William Palomino
* @version 1.0
*/

require_once 'CTCT/Authentication.php';
class MyConstantContactDataStore extends CTCTDataStore
{

  function addUser($user)
  {
    $connection = Doctrine_Manager::connection();
    $query = "INSERT INTO constant_contact (username, access_token, created_at) VALUES ('".$user['username']."', '".$user['access_token']."', '".date('Y-m-d H:i:s')."')";
    $statement = $connection->execute($query);
  }

  function lookupUser($username)
  {      
    try {
      $connection = Doctrine_Manager::connection();
      $query = "SELECT username, access_token FROM constant_contact WHERE username='".$username."' ORDER BY id DESC LIMIT 1";
      $statement = $connection->execute($query);
      $statement->execute();

      $resultset = $statement->fetch(PDO::FETCH_OBJ);
      
      if (empty($resultset)) {
        $returnUser = false;
        throw new Exception('Username '.$username.' not found in datastore');
      }
      else {
        $fields = array();
        foreach ($resultset as $k => $v)  $fields[$k] = $v;
        $returnUser = $fields;
      }

    }
    catch(Exception $e) {
      echo 'OAuth Exception: '.$e->getMessage();
    }
    return $returnUser;
  }
  
}
?>
