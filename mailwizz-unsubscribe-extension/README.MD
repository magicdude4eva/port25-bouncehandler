# MailWizz Unsubscribe Extension
This extension needs to be uploaded and installed in MailWizz:

![Install the extension](https://raw.githubusercontent.com/magicdude4eva/port25-bouncehandler/master/mailwizz-unsubscribe-extension/mailwizz-list-unsubscribe1.png)

The configuration requires only one parameter:
 
```
[SUBSCRIBER_UID].[LIST_UID].[CAMPAIGN_UID]@fbl-unsub.YOURDOMAIN.COM
```

![Configure the extension](https://raw.githubusercontent.com/magicdude4eva/port25-bouncehandler/master/mailwizz-unsubscribe-extension/mailwizz-list-unsubscribe2.png)


## DNS Changes required
For the setup to work, the following is required:
- Create a domain `fbl-unsub.YOURDOMAIN.COM`

- Create a MX record for `fbl-unsub.YOURDOMAIN.COM` which points to your Port25 server - i.e. `fbl-unsub.YOURDOMAIN.COM MX 1 mailserver.example.com`
