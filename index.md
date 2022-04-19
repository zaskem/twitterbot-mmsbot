# Twitter "MMS-MOA" Bot
![MMS-MOA bot profile image](mmsbot.png "MMS-MOA bot")

The [MMS-MOA Bot](https://twitter.com/mmsmoabot) was developed as a novelty bot/project by [@zaskem](https://github.com/zaskem)/[@matt_zaske](https://twitter.com/matt_zaske) to like/retweet original content, randomly selected from recent activity, during the [MMSMOA conference](https://mmsmoa.com/).

The bot process searches for original (e.g. no retweets, quote tweets, or replies) content and, on a self-metered schedule, likes and retweets a randomly-selected match. Not all matching content will be liked/retweeted (not a live-stream bot); the intent is to select one in every 3-4 matches.

Bot code is written in PHP and uses [Twitter's v2 API](https://developer.twitter.com/en/docs/twitter-api) for its functionality. Beyond a Twitter Developer account and method (such as [Twurl](https://developer.twitter.com/en/docs/tutorials/using-twurl)) by which to create [3-Legged OAuth tokens](https://developer.twitter.com/en/docs/authentication/oauth-1-0a/obtaining-user-access-tokens) (assuming a "bot" Twitter account is used), there are no libraries or dependencies to install for bot use.

The bot is designed to self-meter its like/retweet cadence so as not to be "spammy" or overactive. It will like/retweet no more than once every five minutes at peak capacity. Realistically, the bot self-adjusts its cadence to the average interval in which _about three tweets_ have been posted (over the search period of the previous 1-2 hours during conference time).

## Acknowledgements
The [MMS-MOA Bot](https://twitter.com/mmsmoabot) is not officially associated with [MMSMOA](https://mmsmoa.com/). The bot's [profile image](https://twitter.com/mmsmoabot/photo) is a mashup of [Jamie Sale's free clip art](https://www.jamiesale-cartoonist.com/free-cartoon-robot-vector/) and the MMS logo. The bot's header image was semi-randomly selected from the [MMSMOA 2019 photo slideshow](https://mmsmoa.com/past/mms-2019-at-moa-photos.html).

The [GitHub repo](https://github.com/zaskem/twitterbot-mmsbot) contains the basics for getting started with such a bot.