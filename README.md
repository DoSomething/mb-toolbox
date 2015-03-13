[![Latest Stable Version](https://poser.pugx.org/dosomething/mb-toolbox/v/stable.svg)](https://packagist.org/packages/dosomething/mb-toolbox) [![Total Downloads](https://poser.pugx.org/dosomething/mb-toolbox/downloads.svg)](https://packagist.org/packages/dosomething/mb-toolbox)  [![License](https://poser.pugx.org/dosomething/mb-toolbox/license.svg)](https://packagist.org/packages/dosomething/mb-toolbox)
mb-toolbox
==========

A collection of classes and related methods that provide common functionality for many of the producers and consumers applications within the Message Broker system.

####class MB_Toolbox
```
@param array $settings
  Connection and configuration settings common to the application
```
**Methods**
- isDSAffiliate($targetCountyCode)
- createDrupalUser($user)
- getPasswordResetURL($uid)
- getDSMemberCount()
- subscriptionsLinkGenerator($targetEmail)
- curlPOST($curlUrl, $post)
- curlPOSTauth($curlUrl, $post)
- curlGET($curlUrl)
- curlGETauth($curlUrl)

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



[![Bitdeli Badge](https://d2weczhvl823v0.cloudfront.net/DoSomething/mb-toolbox/trend.png)](https://bitdeli.com/free "Bitdeli Badge")

