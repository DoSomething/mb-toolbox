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
