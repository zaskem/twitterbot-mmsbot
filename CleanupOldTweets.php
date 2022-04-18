<?php
  require_once(__DIR__ . '/TwitterAPIFunctions.php');

  if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
  }

  
  // Only run if we have history data...
  if (file_exists($tweetDataFile)) {
    $tweetHistoryData = include($tweetDataFile);

    // Find tweets 4-6 days old
    $overrideValues = array('start_time'=>date('Y-m-d\TH:i:s\Z', strtotime('-6 days')),'end_time'=>date('Y-m-d\TH:i:s\Z', strtotime('-4 days')),'max_results'=>10);
    $tweetDataResults = searchTweets($overrideValues);

    // If we have any matches in that timeframe, grab the first and use it as our reference tweet
    if (false !== $tweetDataResults) {
      $cleanupBaselineId = $tweetDataResults['data'][0]['id'];

      foreach ($tweetHistoryData as $index => $tweetId) {
        // Remove any tweets "older than" the cleanup baseline
        if ($tweetId <= $cleanupBaselineId) {
          unset($tweetHistoryData[$index]);
        }
      }
      
      // Rewrite the trimmed history file
      file_put_contents($tweetDataFile, '<?php return ' . var_export($tweetHistoryData, true) . '; ?>');
    }
  }
?>