[![Latest Stable Version](https://poser.pugx.org/dosomething/mb-toolbox/v/stable.svg)](https://packagist.org/packages/dosomething/mb-toolbox) [![Total Downloads](https://poser.pugx.org/dosomething/mb-toolbox/downloads.svg)](https://packagist.org/packages/dosomething/mb-toolbox)  [![License](https://poser.pugx.org/dosomething/mb-toolbox/license.svg)](https://packagist.org/packages/dosomething/mb-toolbox)
mb-toolbox
==========

A collection of classes and related methods that provide common functionality for many of the producers and consumers applications within the Message Broker system.

####class MB_Toolbox
**Methods**
- isDSAffiliate($targetCountyCode)
- createDrupalUser($user)
- getPasswordResetURL($uid)
- getDSMemberCount()
- subscriptionsLinkGenerator($targetEmail)
- curlPOST($curlUrl, $post)
- curlPOSTauth($curlUrl, $post)
- curlDELETE($curlUrl)
- curlDELETEauth($curlUrl)
- authenticate()


####class MB_Toolbox_cURL
**Methods**
- curlGET($curlUrl, $isAuth = FALSE)
- curlGETauth($curlUrl)
- curlGETImage($imageUrl)
- authenticate()


####class MB_Toolbox_BaseConsumer
```
@param string $targetMBconfig
The Message Broker object used to interface the RabbitMQ server exchanges and related queues.
$targetMBconfig = 'messageBroker'
```
**Methods**
- consumeQueue($payload)
- throttle($maxMessageRate)
**abstract protected**
- setter($message)
- canProcess()
- process()

####class MB_MailChimp($settings)
```
@param array $settings
Settings from external services - Mailchimp
```
**Methods**
- submitBatchToMailChimp($composedBatch)
- submitToMailChimp($composedItem)


####class MB_Configuration
```
@param array $source
  The source of configuration settings. This can be from a file or an endpoint.
@param array $applicationSettings
  General application settings for use by all classes in application.
```
**Methods**
- exchangeSettings($targetExchange)

####class MB_Configuration
```
@param array $settings
  Configuration settings defined by the application script accessing the library.
```
**Methods**
- private __construct()
- static getInstance()
- setProperty($key, $value)
- getProperty($key)
- constructRabbitConfig($targetExchange, $targetQueues = NULL)
- exchangeSettings($targetExchange)
- gatherSettings($targetSetting)


####Gulp Support
Use path directly to gulp `./node_modules/.bin/gulp` or add alias to system config (`.bash_profile`) in alias gulp='./node_modules/.bin/gulp'

###Linting
- `gulp lint`

###Linting
- `gulp test`

See `gulpfile.js` for configuration.

### PHP CodeSniffer

- `php ./vendor/bin/phpcs --standard=./ruleset.xml --colors -s src tests`
Listing of all coding volations by file.

- `php ./vendor/bin/phpcbf --standard=./ruleset.xml --colors src tests`
Automated processing of files to adjust to meeting coding standards.
