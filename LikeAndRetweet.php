<?php
  require_once(__DIR__ . '/TwitterAPIFunctions.php');

  if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
  }


  // First Retrieve Tweets...
  $tweetDataResults = searchTweets();

  
  // Load the interval data file (or create one)...
  if (file_exists($tweetIntervalFile)) {
    $nextRetweetAt = include($tweetIntervalFile);
  } else {
    file_put_contents($tweetIntervalFile, '<?php return ' . $timeAtRun . '; ?>');
    $nextRetweetAt = $timeAtRun;
  }


  // If we've reached the cadence interval (auto-metering) proceed with a like/retweet
  if (time() >= $nextRetweetAt) {
    // We only do something if any results are returned...
    if (false !== $tweetDataResults) {
      $tweetResultCount = $tweetDataResults['meta']['result_count'];

      // Load the history and reference data...
      if (file_exists($tweetDataFile)) {
        $tweetHistoryData = include($tweetDataFile);
      } else {
        $tweetHistoryData = array();
      }

      // Select a random tweet from the result set (try up to $tweetResultCount times)
      $lookupAttempt = 0;
      while ($lookupAttempt <= $tweetResultCount) {
        $selectedTweet = getRandomTweet($tweetDataResults);
        if (false !== $selectedTweet) {
          break;
        } else {
          $lookupAttempt++;
        }
      }

      // If we were able to find a valid/unused tweet, Like and Retweet
      if (false !== $selectedTweet) {
        likeTweet($selectedTweet);
        if (retweetContent($selectedTweet)) {
          // Add tweet to the used tweet list
          $tweetHistoryData[] = $selectedTweet;
          file_put_contents($tweetDataFile, '<?php return ' . var_export($tweetHistoryData, true) . '; ?>');
        }
      }
    }
  }


  // Determine and update the next cadence interval (auto-metering)
  $cadenceInterval = determineCadenceInterval($tweetDataResults);

  // Update if previous interval passed or we notice more tweets than before
  if (($cadenceInterval <= $nextRetweetAt) || (time() > $nextRetweetAt)) {
    file_put_contents($tweetIntervalFile, '<?php return ' . $cadenceInterval . '; ?>');
  }


  /**
   * getUserPosition($authorId, $tweetSearchIncludesUsers) - identify the array key for the $authorId in the $tweetSearchResults
   * 
   * Function returns the matching positional key of the `Includes/Users` subarray.
   *  This is done so other functions (e.g. `getRandomTweet`) can appropriately look
   *  up/reference profile details (such as the "Protected" flag).
   * 
   * $tweetSearchIncludesUsers - subarray of the `$tweetSearchResults` array
   *  (e.g. `$tweetSearchResults['includes']['users']`)
   * 
   * @return boolean false (if a matching `$authorId` is not found)
   * @return integer of positional index for matching `$authorId`
   */
  function getUserPosition($authorId, $tweetSearchIncludesUsers) {
    foreach($tweetSearchIncludesUsers as $key => $value) {
      if ($authorId == $value['id']) {
        return $key;
      }
    }
    return false;
  }


  /**
   * getRandomTweet($tweetSearchResults) - pick a valid and yet unused tweet from $tweetSearchResults
   * 
   * Function randomly selects a valid/unused tweet from search results based on:
   *  - Tweet has not previously been retweeted by the bot;
   *  - Original tweeting user's profile data has been found;
   *  - Original tweeting user's profile is NOT set to "Protected";
   *  - [Not Implemented] Original tweeting user has not restricted who can reply.
   * 
   * $tweetSearchResults - array of results from the `searchTweets()` API function
   * 
   * @return boolean false (if randomly selected tweet is not usable/valid)
   * @return string/integer of a valid Tweet ID
   */
  function getRandomTweet($tweetSearchResults) {
    global $tweetHistoryData, $tweetResultCount;
    $randoTweet = rand(0, $tweetResultCount - 1);

    // Tweet Details
    $selectedTweet = $tweetSearchResults['data'][$randoTweet];
    $tweetId = $selectedTweet['id'];
    $authorId = $selectedTweet['author_id'];
    $replySettings = $selectedTweet['reply_settings'];

    // Tweeting User Details
    $userPosition = getUserPosition($authorId, $tweetSearchResults['includes']['users']);
    $userData = $tweetSearchResults['includes']['users'][$userPosition];
    $protectedUser = $userData['protected'];

    // Did User Lookup Fail?
    if (false === $userPosition) { 
      return false;
    }

    // Is User Protected?
    if (true === $protectedUser) {
      return false;
    }

    /**
     * // Does User Have Reply Restrictions?
     * if ('everyone' != $replySettings) {
     * // Restrictions don't actually prevent likes/retweets, but might be a consideration...
     * return false;
     * }
     */
    
    // Has Tweet Already Been Used
    if (in_array($tweetId, $tweetHistoryData)) {
      return false;
    }

    // Return Selected Tweet
    return $tweetId;
  }


  /**
   * determineCadenceInterval($tweetSearchResults) - calculate the cadence for the next like/retweet interval
   * 
   * This function "calculates" the earliest point (based on tweet history) at which
   *  we might expect at least three tweets to be available. If no recent history is
   *  available (a blank result set), we just use the "lookback interval" as the next
   *  timestamp. This helps keep the bot from attempting to find unused tweets during
   *  quieter times.
   * 
   * $tweetSearchResults - array of results from the `searchTweets()` API function
   * 
   * @return timestamp of the next like/retweet interval
   */
  function determineCadenceInterval($tweetSearchResults) {
    global $queryLookbackUpTo, $timeAtRun;
    $lookbackTimestamp = strtotime($queryLookbackUpTo);
    $lookbackInterval = $timeAtRun - $lookbackTimestamp;
  
    // Set cadence interval to lookback time since no results were returned in the lookback timeframe
    if (false === $tweetSearchResults) {
      $nextTweetAt = $timeAtRun + $lookbackInterval;
    } else {
      $tweetResultCount = $tweetSearchResults['meta']['result_count'];
      /**
       * We want to tweet when we might expect three tweets are in the queue, so tweet next in (minutes):
       * 
       * 3 * (($lookbackInterval / 60) / $tweetResultCount);
       */      
        $nextTweetAt = round($timeAtRun + (3*(($lookbackInterval/60)/$tweetResultCount))*60);
    }
    return $nextTweetAt;
  }
?>