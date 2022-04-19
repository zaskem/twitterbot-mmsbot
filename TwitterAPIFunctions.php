<?php
  require_once(__DIR__ . '/config/bot.php');


  /**
   * getNonce($length = 11) - generate a nonce value for OAuth signatures
   * 
   * 100% stolen from the source at https://github.com/BaglerIT/OAuthSimple/blob/master/src/OAuthSimple.php and full credit goes to them! 
   *
   * @return string of a reasonably-unique nonce value
   */
  function getNonce($length = 11) {
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
   * searchTweets(array $needles = null) - search for tweets
   * 
   * $needles: array of query parameters to override from the default set (set in `bot.php`), default null
   *
   * @return array of JSON-decoded search results, or boolean false if none/error
   */
  function searchTweets(array $needles = null) {
    global $twitterBearerToken, $twitterSearchEndpoint, $twitterQueryDefaults;

    // Add overridden search needles if provided
    if (!is_null($needles)) {
      $twitterQueryDefaults = array_merge($twitterQueryDefaults, $needles);
    }

    $flattenedArgs = '';
    $argKeys = array_keys($twitterQueryDefaults);
    $lastKey = end($argKeys);
    foreach ($twitterQueryDefaults as $key=>$value) {
      if ('query' == $key) {
        $flattenedArgs .= $key . "=" . rawurlencode($value);
      } else {
        $flattenedArgs .= $key . "=" . $value;
      }
      if ($key != $lastKey) {
        $flattenedArgs .= "&";
      }
    }
    $searchEndpointWithArgs = $twitterSearchEndpoint . '?' . $flattenedArgs;

    $curl_header = array("Authorization: Bearer $twitterBearerToken");

    // Create/Submit cURL request
    $curl_request = curl_init();
    curl_setopt_array($curl_request, array(
      CURLOPT_URL => $searchEndpointWithArgs,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => $curl_header
    ));

    $json = curl_exec($curl_request);
    curl_close($curl_request);

    $results = json_decode($json, true);

    if (array_key_exists("errors", $results)) {
      return false;
    } else if (0 == $results['meta']['result_count']) {
      return false;
    } else {
      return $results;
    }
  }


  /**
   * countTweets(array $needles = null) - count of tweets from query
   * 
   * $needles: array of query parameters to override from the default set (set in `bot.php`), default null
   *
   * @return integer of `total_tweet_count` from provided query; boolean false for error/empty result set
   */
  function countTweets(array $needles = null) {
    global $twitterBearerToken, $twitterCountEndpoint, $twitterQueryDefaults;

    // Add overridden search needles if provided
    if (!is_null($needles)) {
      $twitterQueryDefaults = array_merge($twitterQueryDefaults, $needles);
    }

    $flattenedArgs = '';
    $argKeys = array_keys($twitterQueryDefaults);
    $lastKey = end($argKeys);
    foreach ($twitterQueryDefaults as $key=>$value) {
      if ('query' == $key) {
        $flattenedArgs .= $key . "=" . rawurlencode($value);
      } else {
        $flattenedArgs .= $key . "=" . $value;
      }
      if ($key != $lastKey) {
        $flattenedArgs .= "&";
      }
    }
    $countEndpointWithArgs = $twitterCountEndpoint . '?' . $flattenedArgs;

    $curl_header = array("Authorization: Bearer $twitterBearerToken");

    // Create/Submit cURL request
    $curl_request = curl_init();
    curl_setopt_array($curl_request, array(
      CURLOPT_URL => $countEndpointWithArgs,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => $curl_header
    ));

    $json = curl_exec($curl_request);
    curl_close($curl_request);

    $results = json_decode($json, true);

    if (array_key_exists("errors", $results)) {
      return false;
    } else if (0 == $results['meta']['total_tweet_count']) {
      return false;
    } else {
      return $results['meta']['total_tweet_count'];
    }
  }


  /**
   * likeTweet($tweetId) - retweet $tweetId
   * 
   * $tweetId: Twitter's unique id of the parent/source tweet 
   *
   * @return boolean of success or failure
   */
  function likeTweet($tweetId) {
    global $twitterApiKey, $twitterApiKeySecret, $authToken, $authToken_secret, $twitterLikeEndpoint;
    $timestamp = time();
    $nonce = getNonce();

    // Create OAuth Signature
    $oauth_hash = 'oauth_consumer_key='.$twitterApiKey.'&';
    $oauth_hash .= 'oauth_nonce='.$nonce.'&';
    $oauth_hash .= 'oauth_signature_method=HMAC-SHA1&';
    $oauth_hash .= 'oauth_timestamp='.$timestamp.'&';
    $oauth_hash .= 'oauth_token='.$authToken.'&';
    $oauth_hash .= 'oauth_version=1.0';

    $base = 'POST&' . rawurlencode($twitterLikeEndpoint) . '&' .
      rawurlencode($oauth_hash);
    $key = rawurlencode($twitterApiKeySecret) . '&' . rawurlencode($authToken_secret);
    $signature = base64_encode(hash_hmac('sha1', $base, $key, true));

    // Create OAuth Header for cURL
    $oauth_header = 'oauth_consumer_key="'.rawurlencode($twitterApiKey).'", ';
    $oauth_header .= 'oauth_nonce="'.$nonce.'", ';
    $oauth_header .= 'oauth_signature="'.rawurlencode($signature).'", ';
    $oauth_header .= 'oauth_signature_method="'.rawurlencode('HMAC-SHA1').'", ';
    $oauth_header .= 'oauth_timestamp="'.$timestamp.'", ';
    $oauth_header .= 'oauth_token="'.rawurlencode($authToken).'", ';
    $oauth_header .= 'oauth_version="1.0"';

    $curl_header = array("Authorization: OAuth $oauth_header", 'Content-Type: application/json');
    
    // Create/Submit cURL request
    $curl_request = curl_init();
    curl_setopt_array($curl_request, array(
      CURLOPT_URL => $twitterLikeEndpoint,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS =>'{
        "tweet_id": "'.$tweetId.'"
      }',
      CURLOPT_HTTPHEADER => $curl_header,
    ));

    $json = curl_exec($curl_request);
    curl_close($curl_request);

    if (!array_key_exists("data", json_decode($json))) {
      return false;
    } else {
      return true;
    }
  }


  /**
   * unlikeTweet($tweetId) - delete the retweet of $tweetId
   * 
   * $tweetId: Twitter's unique id of the parent/source tweet 
   *
   * @return boolean of success or failure
   */
  function unlikeTweet($tweetId) {
    global $twitterApiKey, $twitterApiKeySecret, $authToken, $authToken_secret, $twitterLikeEndpoint;
    $timestamp = time();
    $nonce = getNonce();
    $unlikeTweetEndpoint = $twitterLikeEndpoint . '/' . $tweetId;

    // Create OAuth Signature
    $oauth_hash = 'oauth_consumer_key='.$twitterApiKey.'&';
    $oauth_hash .= 'oauth_nonce='.$nonce.'&';
    $oauth_hash .= 'oauth_signature_method=HMAC-SHA1&';
    $oauth_hash .= 'oauth_timestamp='.$timestamp.'&';
    $oauth_hash .= 'oauth_token='.$authToken.'&';
    $oauth_hash .= 'oauth_version=1.0';

    $base = 'DELETE&' . rawurlencode($unlikeTweetEndpoint) . '&' .
      rawurlencode($oauth_hash);
    $key = rawurlencode($twitterApiKeySecret) . '&' . rawurlencode($authToken_secret);
    $signature = base64_encode(hash_hmac('sha1', $base, $key, true));

    // Create OAuth Header for cURL
    $oauth_header = 'oauth_consumer_key="'.rawurlencode($twitterApiKey).'", ';
    $oauth_header .= 'oauth_nonce="'.$nonce.'", ';
    $oauth_header .= 'oauth_signature="'.rawurlencode($signature).'", ';
    $oauth_header .= 'oauth_signature_method="'.rawurlencode('HMAC-SHA1').'", ';
    $oauth_header .= 'oauth_timestamp="'.$timestamp.'", ';
    $oauth_header .= 'oauth_token="'.rawurlencode($authToken).'", ';
    $oauth_header .= 'oauth_version="1.0"';

    $curl_header = array("Authorization: OAuth $oauth_header");
    
    // Create/Submit cURL request
    $curl_request = curl_init();
    curl_setopt_array($curl_request, array(
      CURLOPT_URL => $unlikeTweetEndpoint,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'DELETE',
      CURLOPT_HTTPHEADER => $curl_header,
    ));

    $json = curl_exec($curl_request);
    curl_close($curl_request);

    if (!array_key_exists("data", json_decode($json))) {
      return false;
    } else {
      return true;
    }
  }


  /**
   * retweetContent($tweetId) - retweet $tweetId
   * 
   * $tweetId: Twitter's unique id of the parent/source tweet 
   *
   * @return boolean of success or failure
   */
  function retweetContent($tweetId) {
    global $twitterApiKey, $twitterApiKeySecret, $authToken, $authToken_secret, $twitterRetweetEndpoint;
    $timestamp = time();
    $nonce = getNonce();

    // Create OAuth Signature
    $oauth_hash = 'oauth_consumer_key='.$twitterApiKey.'&';
    $oauth_hash .= 'oauth_nonce='.$nonce.'&';
    $oauth_hash .= 'oauth_signature_method=HMAC-SHA1&';
    $oauth_hash .= 'oauth_timestamp='.$timestamp.'&';
    $oauth_hash .= 'oauth_token='.$authToken.'&';
    $oauth_hash .= 'oauth_version=1.0';

    $base = 'POST&' . rawurlencode($twitterRetweetEndpoint) . '&' .
      rawurlencode($oauth_hash);
    $key = rawurlencode($twitterApiKeySecret) . '&' . rawurlencode($authToken_secret);
    $signature = base64_encode(hash_hmac('sha1', $base, $key, true));

    // Create OAuth Header for cURL
    $oauth_header = 'oauth_consumer_key="'.rawurlencode($twitterApiKey).'", ';
    $oauth_header .= 'oauth_nonce="'.$nonce.'", ';
    $oauth_header .= 'oauth_signature="'.rawurlencode($signature).'", ';
    $oauth_header .= 'oauth_signature_method="'.rawurlencode('HMAC-SHA1').'", ';
    $oauth_header .= 'oauth_timestamp="'.$timestamp.'", ';
    $oauth_header .= 'oauth_token="'.rawurlencode($authToken).'", ';
    $oauth_header .= 'oauth_version="1.0"';

    $curl_header = array("Authorization: OAuth $oauth_header", 'Content-Type: application/json');
    
    // Create/Submit cURL request
    $curl_request = curl_init();
    curl_setopt_array($curl_request, array(
      CURLOPT_URL => $twitterRetweetEndpoint,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS =>'{
        "tweet_id": "'.$tweetId.'"
      }',
      CURLOPT_HTTPHEADER => $curl_header,
    ));

    $json = curl_exec($curl_request);
    curl_close($curl_request);

    if (!array_key_exists("data", json_decode($json))) {
      return false;
    } else {
      return true;
    }
  }


  /**
   * deleteRetweet($tweetId) - delete the retweet of $tweetId
   * 
   * $tweetId: Twitter's unique id of the parent/source tweet 
   *
   * @return boolean of success or failure
   */
  function deleteRetweet($tweetId) {
    global $twitterApiKey, $twitterApiKeySecret, $authToken, $authToken_secret, $twitterRetweetEndpoint;
    $timestamp = time();
    $nonce = getNonce();
    $deleteTweetEndpoint = $twitterRetweetEndpoint . '/' . $tweetId;

    // Create OAuth Signature
    $oauth_hash = 'oauth_consumer_key='.$twitterApiKey.'&';
    $oauth_hash .= 'oauth_nonce='.$nonce.'&';
    $oauth_hash .= 'oauth_signature_method=HMAC-SHA1&';
    $oauth_hash .= 'oauth_timestamp='.$timestamp.'&';
    $oauth_hash .= 'oauth_token='.$authToken.'&';
    $oauth_hash .= 'oauth_version=1.0';

    $base = 'DELETE&' . rawurlencode($deleteTweetEndpoint) . '&' .
      rawurlencode($oauth_hash);
    $key = rawurlencode($twitterApiKeySecret) . '&' . rawurlencode($authToken_secret);
    $signature = base64_encode(hash_hmac('sha1', $base, $key, true));

    // Create OAuth Header for cURL
    $oauth_header = 'oauth_consumer_key="'.rawurlencode($twitterApiKey).'", ';
    $oauth_header .= 'oauth_nonce="'.$nonce.'", ';
    $oauth_header .= 'oauth_signature="'.rawurlencode($signature).'", ';
    $oauth_header .= 'oauth_signature_method="'.rawurlencode('HMAC-SHA1').'", ';
    $oauth_header .= 'oauth_timestamp="'.$timestamp.'", ';
    $oauth_header .= 'oauth_token="'.rawurlencode($authToken).'", ';
    $oauth_header .= 'oauth_version="1.0"';

    $curl_header = array("Authorization: OAuth $oauth_header");
    
    // Create/Submit cURL request
    $curl_request = curl_init();
    curl_setopt_array($curl_request, array(
      CURLOPT_URL => $deleteTweetEndpoint,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'DELETE',
      CURLOPT_HTTPHEADER => $curl_header,
    ));

    $json = curl_exec($curl_request);
    curl_close($curl_request);

    if (!array_key_exists("data", json_decode($json))) {
      return false;
    } else {
      return true;
    }
  }


  /**
   * lookupUser($twitterUsername) - find the user ID of the $twitterUsername user
   * 
   * This call has been deliberately designed as a convenience to only return the first match.
   * The function is not used in runtime of the bot; use at your own risk/peril.
   * 
   * @return string of the Twitter user ID
   * @return boolean false for mismatch/error
   */
  function lookupUser($twitterUsername) {
    global $twitterBearerToken, $twitterUserIdLookupEndpoint;

    $curl_header = array('Authorization: Bearer '.$twitterBearerToken);
    // Modify the endpoint URL with argument provided
    $twitterUserIdLookupEndpointWithArgs = $twitterUserIdLookupEndpoint . '?usernames=' . $twitterUsername;

    // Create/Submit cURL request
    $curl_request = curl_init();
    curl_setopt_array($curl_request, array(
      CURLOPT_URL => $twitterUserIdLookupEndpointWithArgs,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => $curl_header,
    ));

    $json = curl_exec($curl_request);
    curl_close($curl_request);

    $result = json_decode($json, true);
    if (array_key_exists("errors", $result)) {
      return false;
    } else {
      return $result['data'][0]['id'];
    }
  }

?>