<?php
/**
 * TwitterV2API Class
 */
class TwitterV2API {
  private $methodType;
  // Bot Authorization and Information
  private $bearerToken;
  private $twitterApiKey;
  private $twitterApiKeySecret;
  private $authToken;
  private $authTokenSecret;
  private $botId;
  private $botUsername;
  // Dynamic variables for individual requests
  private $searchQueryArgs;
  private $postArgs;
  private $postDataContentType = 'Content-Type: application/json';
  private $nonceValue;
  private $timestampValue;
  private $curlEndpoint;
  private $oauthSignature;
  private $oauthHeader;
  private $curlHeader;
  // Twitter REST v2 Endpoints
  private $usersBaseEndpoint = 'https://api.twitter.com/2/users';
  private $tweetsBaseEndpoint = 'https://api.twitter.com/2/tweets';

  public function __construct() {
    $this->setAuthDetail();
  }

  /**
   * getNonce($length = 11) - generate a nonce value for OAuth signatures
   * 
   * 100% stolen from the source at https://github.com/BaglerIT/OAuthSimple/blob/master/src/OAuthSimple.php and full credit goes to them! 
   *
   * @return string of a reasonably-unique nonce value
   */
  private function generateNonce($length = 11) {
    $nonce_chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
    $result = '';
    $cLength = strlen($nonce_chars);
    for ($i = 0; $i < $length; $i++) {
      $rnum = rand(0, $cLength - 1);
      $result .= substr($nonce_chars, $rnum, 1);
    }
    return $result;
  }

  /**
   * setAuthDetail() - Load the bot authorization detals from file
   */
  private function setAuthDetail() {
    try {
      if (!file_exists(__DIR__ . '/config/authorizations.php')) {
        throw new Exception("Both Authorization File (authorizations.php) not found. Halting.\n");
      } else {
        require __DIR__ . '/config/authorizations.php';
      }
    } catch (Exception $e) {
      die($e->getMessage());
    }
    $this->bearerToken = $twitterBearerToken;
    $this->twitterApiKey = $twitterApiKey;
    $this->twitterApiKeySecret = $twitterApiKeySecret;
    $this->authToken = $authToken;
    $this->authTokenSecret = $authTokenSecret;

    $this->botId = $authTokenAccountId;
    $this->botUsername = $authTokenAccount;
  }

  /**
   * setSearchQuery(array $searchQueryParams) - create flattened parameter string from $searchQueryParams
   * 
   * $searchQueryParams: array of query parameters to be flattened and urlencoded
   */
  private function setSearchQuery(array $searchQueryParams) {
    $flattenedArgs = '';
    $argKeys = array_keys($searchQueryParams);
    $lastKey = end($argKeys);
    foreach ($searchQueryParams as $key=>$value) {
      if ('query' == $key) {
        $flattenedArgs .= $key . "=" . rawurlencode($value);
      } else {
        $flattenedArgs .= $key . "=" . $value;
      }
      if ($key != $lastKey) {
        $flattenedArgs .= "&";
      }
    }
    $this->searchQueryArgs = $flattenedArgs;
  }

  /**
   * setCurlHeader() - Set the cURL/authentication header
   * 
   * Supports the methods 'DELETE', 'POST', and 'GET' (default)
   * 
   * 'DELETE' and 'POST' methods require a valid Oauth 1.0 signature/header, which
   *  should be generated before calling setCurlHeader.
   * 
   * 'GET' (default) method uses a bearer token and requires no signature generation.
   */
  private function setCurlHeader() {
    switch ($this->methodType) {
      case "DELETE":
        $this->curlHeader = array("Authorization: OAuth " . $this->oauthHeader);
        break;
      case "POST":
        $this->curlHeader = array("Authorization: OAuth " . $this->oauthHeader, $this->postDataContentType);
        break;
      default: // "GET"
        $this->curlHeader = array("Authorization: Bearer " . $this->bearerToken);
    }
  }

  /**
   * generateOauthSignature() - Dynamically generate valid OAuth 1.0 signature
   * 
   * When an OAuth 1.0 request is required, the following are required in order:
   * *  generateNonce();
   * *  time();
   *=>* generateOauthSignature();
   * *  generateOauthHeader();
   * *  setCurlHeader();
   */
  private function generateOauthSignature() {
    $oauth_hash = 'oauth_consumer_key='.$this->twitterApiKey.'&';
    $oauth_hash .= 'oauth_nonce='.$this->nonceValue.'&';
    $oauth_hash .= 'oauth_signature_method=HMAC-SHA1&';
    $oauth_hash .= 'oauth_timestamp='.$this->timestampValue.'&';
    $oauth_hash .= 'oauth_token='.$this->authToken.'&';
    $oauth_hash .= 'oauth_version=1.0';

    $base = $this->methodType . '&' . rawurlencode($this->curlEndpoint) . '&' .
      rawurlencode($oauth_hash);
    $key = rawurlencode($this->twitterApiKeySecret) . '&' . rawurlencode($this->authTokenSecret);

    $this->oauthSignature = base64_encode(hash_hmac('sha1', $base, $key, true));
  }

  /**
   * generateOauthHeader() - Dynamically generate valid OAuth 1.0 header
   * 
   * When an OAuth 1.0 request is required, the following are required in order:
   * *  generateNonce();
   * *  time();
   * *  generateOauthSignature();
   *=>* generateOauthHeader();
   * *  setCurlHeader();
   */
  private function generateOauthHeader() {
    $oauth_header = 'oauth_consumer_key="'.rawurlencode($this->twitterApiKey).'", ';
    $oauth_header .= 'oauth_nonce="'.$this->nonceValue.'", ';
    $oauth_header .= 'oauth_signature="'.rawurlencode($this->oauthSignature).'", ';
    $oauth_header .= 'oauth_signature_method="'.rawurlencode('HMAC-SHA1').'", ';
    $oauth_header .= 'oauth_timestamp="'.$this->timestampValue.'", ';
    $oauth_header .= 'oauth_token="'.rawurlencode($this->authToken).'", ';
    $oauth_header .= 'oauth_version="1.0"';
    
    $this->oauthHeader = $oauth_header;
  }

  /**
   * curlRequest - invoke the staged cURL request
   * 
   * Dynamically adds POSTFIELDS if method is POST
   * 
   * @return string JSON response of cURL request
   */
  private function curlRequest() {
    $curlOptions = array(
      CURLOPT_URL => $this->curlEndpoint,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => $this->methodType,
      CURLOPT_HTTPHEADER => $this->curlHeader,
    );
    if ('POST' == $this->methodType) {
      $curlOptions[CURLOPT_POSTFIELDS] = $this->postArgs;
    }

    $curl_request = curl_init();
    curl_setopt_array($curl_request, $curlOptions);

    $json = curl_exec($curl_request);
    curl_close($curl_request);

    return $json;
  }

  /**
   * following() - pull the list of users the bot account is following
   * 
   * Used to populate the local cache of accounts the bot is following.
   * 
   * @return array of the Twitter following data
   * @return boolean false for error/empty set
   */
  public function following() {
    $this->methodType = 'GET';
    $this->curlEndpoint = $this->usersBaseEndpoint .'/' . $this->botId . '/following';
    $this->setCurlHeader();

    $result = json_decode($this->curlRequest(), true);
    if (array_key_exists("errors", $result)) {
      return false;
    } else if (0 == $result['meta']['result_count']) {
      return false;
    } else {
      return $result['data'];
    }
  }

  /**
   * search($searchQueryParams) - dataset of tweets matching $searchQueryParams
   * 
   * $searchQueryParams: array of query parameters (defaults set in `bot.php`)
   *
   * @return array of JSON-decoded search results from provided parameters
   * @return boolean false for error/empty set
   */
  public function search($searchQueryParams) {
    $this->setSearchQuery($searchQueryParams);
    $this->methodType = 'GET';
    $this->curlEndpoint = $this->tweetsBaseEndpoint .'/search/recent?' . $this->searchQueryArgs;
    $this->setCurlHeader();

    $result = json_decode($this->curlRequest(), true);
    if (array_key_exists("errors", $result)) {
      return false;
    } else if (0 == $result['meta']['result_count']) {
      return false;
    } else {
      return $result;
    }
  }

  /**
   * count($searchQueryParams) - count of tweets matching $searchQueryParams
   * 
   * $searchQueryParams: array of query parameters (defaults set in `bot.php`)
   *
   * @return integer of `total_tweet_count` from provided parameters
   * @return boolean false for error/empty set
   */
  public function count($searchQueryParams) {
    $this->setSearchQuery($searchQueryParams);
    $this->methodType = 'GET';
    $this->curlEndpoint = $this->tweetsBaseEndpoint .'/counts/recent?' . $this->searchQueryArgs;
    $this->setCurlHeader();

    $result = json_decode($this->curlRequest(), true);
    if (array_key_exists("errors", $result)) {
      return false;
    } else if (0 == $result['meta']['total_tweet_count']) {
      return false;
    } else {
      return $result['meta']['total_tweet_count'];
    }
  }

  /**
   * like($tweetId) - like $tweetId
   * 
   * $tweetId: Twitter's unique id of the source tweet 
   *
   * @return boolean of success or failure
   */
  public function like($tweetId) {
    $this->methodType = 'POST';
    $this->postArgs = '{
      "tweet_id": "'.$tweetId.'"
    }';
    $this->curlEndpoint = $this->usersBaseEndpoint .'/' . $this->botId . '/likes';
    $this->nonceValue = $this->generateNonce();
    $this->timestampValue = time();
    $this->generateOauthSignature();
    $this->generateOauthHeader();
    $this->setCurlHeader();

    if (!array_key_exists("data", json_decode($this->curlRequest()))) {
      return false;
    } else {
      return true;
    }
  }

  /**
   * unlike($tweetId) - remove the like of $tweetId
   * 
   * $tweetId: Twitter's unique id of the source tweet 
   *
   * @return boolean of success or failure
   */
  public function unlike($tweetId) {
    $this->methodType = 'DELETE';
    $this->curlEndpoint = $this->usersBaseEndpoint .'/' . $this->botId . '/likes/' . $tweetId;
    $this->nonceValue = $this->generateNonce();
    $this->timestampValue = time();
    $this->generateOauthSignature();
    $this->generateOauthHeader();
    $this->setCurlHeader();

    if (!array_key_exists("data", json_decode($this->curlRequest()))) {
      return false;
    } else {
      return true;
    }
  }

  /**
   * retweet($tweetId) - retweet $tweetId
   * 
   * $tweetId: Twitter's unique id of the parent/source tweet 
   *
   * @return boolean of success or failure
   */
  public function retweet($tweetId) {
    $this->methodType = 'POST';
    $this->postArgs = '{
      "tweet_id": "'.$tweetId.'"
    }';
    $this->curlEndpoint = $this->usersBaseEndpoint .'/' . $this->botId . '/retweets';
    $this->nonceValue = $this->generateNonce();
    $this->timestampValue = time();
    $this->generateOauthSignature();
    $this->generateOauthHeader();
    $this->setCurlHeader();

    if (!array_key_exists("data", json_decode($this->curlRequest()))) {
      return false;
    } else {
      return true;
    }
  }

  /**
   * unretweet($tweetId) - delete the retweet of $tweetId
   * 
   * $tweetId: Twitter's unique id of the parent/source tweet 
   *
   * @return boolean of success or failure
   */
  public function unretweet($tweetId) {
    $this->methodType = 'DELETE';
    $this->curlEndpoint = $this->usersBaseEndpoint .'/' . $this->botId . '/retweets/' . $tweetId;
    $this->nonceValue = $this->generateNonce();
    $this->timestampValue = time();
    $this->generateOauthSignature();
    $this->generateOauthHeader();
    $this->setCurlHeader();

    if (!array_key_exists("data", json_decode($this->curlRequest()))) {
      return false;
    } else {
      return true;
    }
  }

  /**
   * lookupUser($twitterUsername) - find the user ID of the $twitterUsername user
   * 
   * This call has been deliberately designed as a convenience to only return the first match.
   * 
   * @return string of the Twitter user ID
   * @return boolean false for mismatch/error
   */
  public function lookupUser($twitterUsername) {
    $this->methodType = 'GET';
    $this->curlEndpoint = $this->usersBaseEndpoint .'/by?usernames=' . $twitterUsername;
    $this->setCurlHeader();

    $result = json_decode($this->curlRequest(), true);
    if (array_key_exists("errors", $result)) {
      return false;
    } else {
      return $result['data'][0]['id'];
    }
  }

  /**
   * follow($twitterUserId) - follow the account $twitterUserId
   * 
   * $twitterUserId: Twitter's unique id of the user account to follow 
   *
   * @return boolean of success or failure
   */
  public function follow($twitterUserId) {
    $this->methodType = 'POST';
    $this->postArgs = '{
      "target_user_id": "'.$twitterUserId.'"
    }';
    $this->curlEndpoint = $this->usersBaseEndpoint .'/' . $this->botId . '/following';
    $this->nonceValue = $this->generateNonce();
    $this->timestampValue = time();
    $this->generateOauthSignature();
    $this->generateOauthHeader();
    $this->setCurlHeader();

    if (!array_key_exists("data", json_decode($this->curlRequest()))) {
      return false;
    } else {
      return true;
    }
  }

  /**
   * unfollow($twitterUserId) - unfollow the account $twitterUserId
   * 
   * $twitterUserId: Twitter's unique id of the user account to unfollow  
   *
   * @return boolean of success or failure
   */
  public function unfollow($twitterUserId) {
    $this->methodType = 'DELETE';
    $this->curlEndpoint = $this->usersBaseEndpoint .'/' . $this->botId . '/following/' . $twitterUserId;
    $this->nonceValue = $this->generateNonce();
    $this->timestampValue = time();
    $this->generateOauthSignature();
    $this->generateOauthHeader();
    $this->setCurlHeader();

    if (!array_key_exists("data", json_decode($this->curlRequest()))) {
      return false;
    } else {
      return true;
    }
  }

}
?>