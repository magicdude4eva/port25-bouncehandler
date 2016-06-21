# MailWizz Unsubscribe Extension
This extension needs to be uploaded and installed in MailWizz. The configuration requires only one parameter:

```
[SUBSCRIBER_UID].[LIST_UID].[CAMPAIGN_UID]@fbl-unsub.YOURDOMAIN.COM
```

For the setup to work, the following is required:
- Create a domain `fbl-unsub.YOURDOMAIN.COM`

- Create a MX record for `fbl-unsub.YOURDOMAIN.COM` which points to your Port25 server - i.e. `fbl-unsub.YOURDOMAIN.COM MX 1 mailserver.example.com`
