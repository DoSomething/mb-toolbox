[![Latest Stable Version](https://poser.pugx.org/dosomething/mb-toolbox/v/stable.svg)](https://packagist.org/packages/dosomething/mb-toolbox) [![Total Downloads](https://poser.pugx.org/dosomething/mb-toolbox/downloads.svg)](https://packagist.org/packages/dosomething/mb-toolbox) [![Latest Unstable Version](https://poser.pugx.org/dosomething/mb-toolbox/v/unstable.svg)](https://packagist.org/packages/dosomething/mb-toolbox) [![License](https://poser.pugx.org/dosomething/mb-toolbox/license.svg)](https://packagist.org/packages/dosomething/mb-toolbox)
mb-toolbox
==========

A collection of classes and related methods that provide common functionality for many of the producers and consumers applications within the Message Broker system.

####class MB_Toolbox
```
@param array $config
  Connection and configuration settings common to the application
```
**Methods**
- isDSAffiliate($targetCountyCode)
- createDrupalUser($user)
- getDSMemberCount()
- curlPOST($curlUrl, $post)

####class MB_MailChimp($settings)
```
@param array $settings
Settings from external services - Mailchimp
```
**Methods**
- submitBatchToMailChimp($composedBatch)
- submitToMailChimp($composedItem)
