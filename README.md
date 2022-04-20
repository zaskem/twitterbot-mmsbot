# MMSMOA Twitter Bot
A [novelty bot](https://twitter.com/mmsmoabot) written in PHP to awareness- and exposure-retweet recent original tagged content (at random) related to the [MMSMOA conference](https://mmsmoa.com/).

Original content is sourced from the [Twitter API v2 Search Recent Tweets](https://developer.twitter.com/en/docs/twitter-api/tweets/search/introduction) API endpoint via selective query parameters.

This bot is not designed to like/retweet every original tweet it finds. It will instead select one at random from recent activity. The designed target is to catch every third or fourth tweet so as to be interesting enough (as a mechanism) but not simply a filtered stream of all matches (folks can do their own searches for that). The [Bot's website](https://mmsbot.mzonline.com/) has additional information about its intent.

## Requirements
To run the bot code, the following libraries/accounts/things are required:

* A bot/user account on Twitter for tweets;
* A project and app configured on the [Twitter Developer Portal](https://developer.twitter.com/);
* A manner by which you can generate an OAuth token and grant permission to the app for the bot account as necessary (such as Twurl); and
* A host on which to run this code (not at a browsable path).

### Twitter API
Applying for access to the [Twitter Developer Portal](https://developer.twitter.com/) is outside the scope of this README. You will need to create a new Project and/or App for the Twitter bot and configure the App permissions to allow `Read and Write` access. You will obtain the API `consumer_key` and `consumer_secret` from the App's keys and tokens page, along with ability to generate a Bearer Token and OAuth 2.0 CLient ID/Secret.

Assuming the account associated with the Developer Portal is _not_ the bot account, you will need to enable `3-Legged OAuth` for the App. This is required to generate a user access token and secret for an independent bot account.

#### A note about generating user access tokens and secrets:
This repo does not include a library/mechanism to address user access and callback for the bot app, which is ___required___ to generate a user access token and secret, and is generally a one-time action. It is recommended to use [Twurl](https://developer.twitter.com/en/docs/tutorials/using-twurl) for its simplicity. The following steps on a local WSL or Ubuntu instance (independent of the bot host if necessary) will generate the token and secret:

1. `gem install twurl` (to install Twurl, also requires ruby)
2. `twurl authorize --consumer-key key --consumer-secret secret` (with your Twitter App key/secret, follow prompts)
3. `more ~/.twurlrc` (to obtain the bot account `token` and `secret` values)

## Bot Configuration
A single configuration file resides in the `config/` directory. An example version is provided, and to get started should be copied without the `.example` extension (e.g. `cp bot.php.example bot.php`)

Edit the `bot.php` file as necessary for your configuration.

## Bot Usage, Likes, Retweets, Follows, and Crontab Setup
The bot process has been designed to run on an interval to search Twitter. The bot then evaluates recent original tweets, grabs a random (not previously retweeted) tweet, follows the original author (if enabled in `bot.php`), likes, and retweets the selection via the simple command:
`php LikeAndRetweet.php`

The bot is also designed to "self-meter" its own volume of retweets. It will adjust the "next retweet" interval based on the volume of tweets from the previous 1-2 hours (as set in `bot.php`). This basically means if tweet volume is low (e.g. 4 tweets in the last 2 hours) it would only attempt to retweet 1 time in about the same interval. However, if tweet volume is high (e.g. 10 tweets in the last 15 minutes), it would attempt to retweet once every 5 minutes.

Cron should be used for production. A simple default crontab setting might look like this:
```bash
*/5 * * * * /path/to/php /path/to/LikeAndRetweet.php
```
The above will run the bot script every 5 minutes, a responsibly-appropriate interval for such a bot.

A cleanup script (`CleanupOldTweets.php`) is included to purge the retweet history cache of tweets that are at least 4-6 days old. This cleanup is not required (Twitter search only goes back a maximum of 7 days), but not cleaning up on some interval could cause the history file to grow in unexpected ways. A simple daily cron job to purge the data is sufficient; however ___it is important to note the cleanup job should not run concurrently with the `LikeAndRetweet.php` job___ due to potential file locking:
```bash
2 3 * * * /path/to/php /path/to/CleanupOldTweets.php
```
The above will purge the history daily at 3:02 a.m.

## Troubleshooting and Tweet Posting
This bot doesn't have a lot of moving parts, so there's not a lot to troubleshoot. There are two general points of failure:

* Failure to obtain data from Twitter; and
* Failure to post a like/retweet.

## Contributors/Acknowledgements
Developed as an opportunity to use Twitter's v2 API over a few lazy afternoons as a novelty bot/project by [@zaskem](https://github.com/zaskem)/[@matt_zaske](https://twitter.com/matt_zaske).

Public release was in advance of MMS (at MOA) 2022 and is not officially associated with [MMSMOA](https://mmsmoa.com/).
