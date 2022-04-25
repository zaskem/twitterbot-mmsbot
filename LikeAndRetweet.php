<?php
  if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
  }

  try {
    if (!file_exists(__DIR__ . '/config/bot.php')) {
      throw new Exception('Bot Config File (bot.php) not found.');
    } else {
      require __DIR__ . '/config/bot.php';
    }
    if (!file_exists(__DIR__ . '/TwitterV2API.php')) {
      throw new Exception('Twitter API Class not found.');
    } else {
      require __DIR__ . '/TwitterV2API.php';
    }
  } catch (Exception $e) {
    die($e->getMessage() . " Halting.\n");
  }

  // Create a Twitter API instance...
  $twitterAPI = new TwitterV2API();

  // Load the interval data file (or create one)...
  if (file_exists($tweetIntervalFile)) {
    $nextRetweetAt = include($tweetIntervalFile);
  } else {
    file_put_contents($tweetIntervalFile, '<?php return ' . $timeAtRun . '; ?>');
    $nextRetweetAt = $timeAtRun;
  }


  // If we've reached the cadence interval (auto-metering) proceed with a like/retweet
  if (time() >= $nextRetweetAt) {
    // Retrieve Tweets...
    $tweetDataResults = $twitterAPI->search($twitterSearchDefaults);
    $tweetResultCount = false;
    // We only do something if any results are returned...
    if (false !== $tweetDataResults) {
      $tweetResultCount = $tweetDataResults['meta']['result_count'];

      // Load the history and reference data...
      if (file_exists($tweetDataFile)) {
        $tweetHistoryData = include($tweetDataFile);
      } else {
        $tweetHistoryData = array();
      }
      // Load the activity detail data if enabled...
      if ($logActivity) {
        if (file_exists($activityDetailFile)) {
          $activityDetail = include($activityDetailFile);
        } else {
          $activityDetail = array();
        }
      }

      // Select a random tweet from the result set (try up to $tweetResultCount times)
      $lookupAttempt = 0;
      while ($lookupAttempt <= $tweetResultCount) {
        $selectedTweet = getRandomTweet($tweetDataResults, $autoFollow);
        if (false !== $selectedTweet) {
          break;
        } else {
          $lookupAttempt++;
        }
      }

      // If we were able to find a valid/unused tweet, Like and Retweet
      if (false !== $selectedTweet) {
        $twitterAPI->like($selectedTweet);
        if ($twitterAPI->retweet($selectedTweet)) {
          // Add tweet to the used tweet list
          $tweetHistoryData[] = $selectedTweet;
          file_put_contents($tweetDataFile, '<?php return ' . var_export($tweetHistoryData, true) . '; ?>');
          if ($logActivity) {
            $activityDetail[date('m-d H:i')] = "$selectedTweet (count was $tweetResultCount; lookup attempt $lookupAttempt)";
            file_put_contents($activityDetailFile, '<?php return ' . var_export($activityDetail, true) . '; ?>');
          }
        }
      }
    }
  } else {
    // We just grab the tweet count for the production query (to auto-meter)
    $tweetResultCount = $twitterAPI->count($twitterCountDefaults);
  }


  // Determine and update the next cadence interval (auto-metering)
  $cadenceInterval = determineCadenceInterval($tweetResultCount);

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
   * getRandomTweet($tweetSearchResults, $autoFollowUser = false) - pick a valid and yet unused tweet from $tweetSearchResults
   * 
   * Function randomly selects a valid/unused tweet from search results based on:
   *  - Tweet has not previously been retweeted by the bot;
   *  - Original tweeting user's profile data has been found;
   *  - Original tweeting user's profile is NOT set to "Protected";
   *  - [Not Implemented] Original tweeting user has not restricted who can reply.
   * 
   * $tweetSearchResults - array of results from the `$twitterAPI->search($twitterSearchDefaults)` API function
   * $autoFollowUser - boolean (default false) to auto-follow the tweet author (if not already)
   * 
   * @return boolean false (if randomly selected tweet is not usable/valid)
   * @return string/integer of a valid Tweet ID
   */
  function getRandomTweet($tweetSearchResults, $autoFollowUser = false) {
    global $twitterAPI, $tweetHistoryData, $tweetResultCount, $followingDataFile;
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


    // All of the "fail point" checks have passed, so we have selected a winning tweet!

    // Auto-Follow User (if enabled)
    if ($autoFollowUser) {
      // Load the bot's following data file (or create/populate one)...
      if (!file_exists($followingDataFile)) {
        generateFollowingDataFile();
      }
      $followingData = include($followingDataFile);
      // If bot is not already following user, do so (and quietly proceed if follow action fails)...
      if (!in_array($authorId, $followingData)) {
        $followAction = $twitterAPI->follow($authorId);
        if ($followAction) {
          $followingData[] = $authorId;
          file_put_contents($followingDataFile, '<?php return ' . var_export($followingData, true) . '; ?>');
        }
      }
    }

    // Return Selected Tweet
    return $tweetId;
  }


  /**
   * determineCadenceInterval($tweetResultCount) - calculate the cadence for the next like/retweet interval
   * 
   * This function "calculates" the earliest point (based on tweet history) at which
   *  we might expect at least three tweets to be available. If no recent history is
   *  available (a blank result set), we just use the "lookback interval" as the next
   *  timestamp. This helps keep the bot from attempting to find unused tweets during
   *  quieter times.
   * 
   * $tweetResultCount - integer of recent search result count
   *  (`total_tweet_count`, `result_count` meta data, or boolean false for no results)
   * 
   * @return timestamp of the next like/retweet interval
   */
  function determineCadenceInterval($tweetResultCount) {
    global $queryLookbackUpTo, $timeAtRun;
    $lookbackTimestamp = strtotime($queryLookbackUpTo);
    $lookbackInterval = $timeAtRun - $lookbackTimestamp;
  
    // Set cadence interval to lookback time since no results were returned in the lookback timeframe
    if (false === $tweetResultCount) {
      $nextTweetAt = $timeAtRun + $lookbackInterval;
    } else {
      /**
       * We want to tweet when we might expect three tweets are in the queue, so tweet next in (minutes):
       * 
       * 3 * (($lookbackInterval / 60) / $tweetResultCount);
       */
        $nextTweetAt = round($timeAtRun + (3*(($lookbackInterval/60)/$tweetResultCount))*60);
    }
    return $nextTweetAt;
  }


  /**
   * generateFollowingDataFile() - [re-]generate the bot following data file
   * 
   * Function rebuilds the local cache of accounts the bot is following and writes to file.
   * 
   * Existing cache is completely overwritten.
   */
  function generateFollowingDataFile() {
    global $twitterAPI, $followingDataFile;
    $followingData = array();
    $followingDataDetail = $twitterAPI->following();
    if ($followingDataDetail) {
      foreach ($followingDataDetail as $followingDetail) {
        $followingData[] = $followingDetail['id'];
      }
    }
    file_put_contents($followingDataFile, '<?php return ' . var_export($followingData, true) . '; ?>');  
  }


  /**
   * injectSearchOverrides(array $needles = null) - rewrite search defaults with overridden paramaters 
   * 
   * $needles: array of query parameters to override from the default set (set in `bot.php`), default null
   */
  function injectSearchOverrides(array $needles = null) {
    global $twitterSearchDefaults;

    // Add overridden search needles if provided
    if (!is_null($needles)) {
      $twitterSearchDefaults = array_merge($twitterSearchDefaults, $needles);
    }
  }

?>